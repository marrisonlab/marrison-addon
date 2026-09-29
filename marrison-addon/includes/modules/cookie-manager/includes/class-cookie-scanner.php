<?php
/**
 * Classe per la scansione dei cookie
 */
class Marrison_Cookie_Scanner {
    
    private static $instance = null;
    private $storage_mode = 'table';
    private $last_scan_error = '';
    
    /**
     * Ottieni istanza singleton
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Costruttore
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inizializza hooks
     */
    private function init_hooks() {
        // Hook per scansione automatica
        add_action('init', array($this, 'maybe_auto_scan'));
        
        // Hook AJAX per scansione manuale
        add_action('wp_ajax_marrison_scan_cookies', array($this, 'ajax_scan_cookies'));
        add_action('wp_ajax_marrison_get_scanned_cookies', array($this, 'ajax_get_scanned_cookies'));
        add_action('wp_ajax_marrison_delete_cookie', array($this, 'ajax_delete_cookie'));
        add_action('wp_ajax_marrison_update_cookie_category', array($this, 'ajax_update_cookie_category'));
        
        // Cron job per scansione periodica
        add_action('marrison_cookie_daily_scan', array($this, 'perform_scan'));
        
        // Schedula cron se non esiste
        add_action('wp', array($this, 'schedule_cron'));
    }
    
    /**
     * Schedula cron job
     */
    public function schedule_cron() {
        if (!get_option('marrison_cookie_auto_scan', true)) {
            $timestamp = wp_next_scheduled('marrison_cookie_daily_scan');
            while ($timestamp) {
                wp_unschedule_event($timestamp, 'marrison_cookie_daily_scan');
                $timestamp = wp_next_scheduled('marrison_cookie_daily_scan');
            }
            return;
        }

        if (!wp_next_scheduled('marrison_cookie_daily_scan')) {
            wp_schedule_event(time(), 'daily', 'marrison_cookie_daily_scan');
        }
    }
    
    /**
     * Esegue scansione automatica se necessario
     */
    public function maybe_auto_scan() {
        if (!get_option('marrison_cookie_auto_scan', true)) {
            return;
        }
        
        $last_scan = get_option('marrison_cookie_last_scan', 0);
        $interval = get_option('marrison_cookie_scan_interval', 7) * DAY_IN_SECONDS;
        
        if (time() - $last_scan > $interval) {
            $this->perform_scan();
        }
    }
    
    /**
     * Esegue la scansione dei cookie
     */
    public function perform_scan() {
        $this->last_scan_error = '';

        if (!$this->ensure_table_exists()) {
            $this->storage_mode = 'json';

            if (!$this->prepare_json_scan()) {
                update_option('marrison_cookie_last_scan', time());
                return 0;
            }
        } else {
            $this->storage_mode = 'table';
        }

        $saved_count = 0;

        $this->clear_old_scans();
        $saved_count += $this->scan_current_cookies();
        $saved_count += $this->scan_homepage_cookies();
        $saved_count += $this->scan_wordpress_cookies();
        $saved_count += $this->scan_third_party_cookies();
        
        update_option('marrison_cookie_last_scan', time());
        
        return $saved_count;
    }

    /**
     * Verifica che la tabella esista anche se l'installazione e stata aggiornata
     * da una versione precedente o la creazione iniziale non e andata a buon fine.
     */
    private function ensure_table_exists() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'marrison_cookies';
        $quoted_table_name = $this->quote_identifier($table_name);
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $quoted_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            cookie_name varchar(255) NOT NULL,
            cookie_domain varchar(255) DEFAULT '',
            cookie_path varchar(255) DEFAULT '/',
            cookie_expiration datetime DEFAULT NULL,
            cookie_category varchar(50) DEFAULT 'functional',
            cookie_description text DEFAULT '',
            source varchar(255) DEFAULT '',
            scan_date datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY cookie_name (cookie_name),
            KEY cookie_category (cookie_category)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        $table_exists = $this->table_exists($table_name);

        if (!$table_exists) {
            $wpdb->last_error = '';
            $created = $wpdb->query("CREATE TABLE IF NOT EXISTS $quoted_table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                cookie_name varchar(255) NOT NULL,
                cookie_domain varchar(255) DEFAULT '',
                cookie_path varchar(255) DEFAULT '/',
                cookie_expiration datetime DEFAULT NULL,
                cookie_category varchar(50) DEFAULT 'functional',
                cookie_description text DEFAULT '',
                source varchar(255) DEFAULT '',
                scan_date datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY cookie_name (cookie_name),
                KEY cookie_category (cookie_category)
            ) $charset_collate");
            $create_error = $wpdb->last_error;

            $table_exists = $this->table_exists($table_name);

            if (false === $created || !$table_exists) {
                $error_message = $create_error ? $create_error : 'CREATE TABLE eseguito, ma la tabella non risulta presente dopo la verifica';
                $this->set_last_scan_error('Tabella cookie non creata: ' . $error_message . '. Database: ' . DB_NAME . '. Tabella attesa: ' . $table_name);
                $this->storage_mode = 'json';
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica l'esistenza della tabella senza usare LIKE, per evitare wildcard
     * involontari nei prefissi che contengono underscore.
     */
    private function table_exists($table_name) {
        global $wpdb;

        $previous_error = $wpdb->last_error;

        $found = $wpdb->get_var($wpdb->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s LIMIT 1',
            $table_name
        ));

        if ($found === $table_name) {
            $wpdb->last_error = $previous_error;
            return true;
        }

        $quoted_table_name = $this->quote_identifier($table_name);
        $described = $wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($wpdb->esc_like($table_name)) . "'");

        if ($described === $table_name) {
            $wpdb->last_error = $previous_error;
            return true;
        }

        $probe = $wpdb->get_var("SHOW COLUMNS FROM $quoted_table_name LIKE 'id'");

        $exists = 'id' === $probe;
        $wpdb->last_error = $previous_error;

        return $exists;
    }

    /**
     * Quota un identificatore SQL composto da WordPress, come il nome tabella.
     */
    private function quote_identifier($identifier) {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
    
    /**
     * Pulisce scansioni vecchie
     */
    private function clear_old_scans() {
        if ('json' === $this->storage_mode) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'marrison_cookies';
        
        // Mantieni solo l'ultima scansione
        $deleted = $wpdb->query("DELETE FROM $table_name WHERE scan_date < DATE_SUB(NOW(), INTERVAL 30 DAY)");

        if (false === $deleted) {
            $this->set_last_scan_error('Pulizia scansioni fallita: ' . $this->get_db_error_message());
        }
    }
    
    /**
     * Scansiona i cookie correnti
     */
    private function scan_current_cookies() {
        if (empty($_COOKIE)) {
            return 0;
        }

        $saved_count = 0;
        
        foreach ($_COOKIE as $name => $value) {
            $category = $this->categorize_cookie($name);
            $description = $this->get_cookie_description($name, $category);
            $saved_count += $this->save_cookie($name, 'current', $category, $description);
        }

        return $saved_count;
    }

    /**
     * Interroga la home pubblica e registra i cookie impostati dagli header HTTP.
     */
    private function scan_homepage_cookies() {
        $response = wp_remote_get(home_url('/'), array(
            'timeout' => 15,
            'redirection' => 5,
            'user-agent' => 'Marrison Cookie Manager/' . (defined('MARRISON_COOKIE_VERSION') ? MARRISON_COOKIE_VERSION : '1.0') . '; ' . home_url('/'),
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ),
        ));

        if (is_wp_error($response)) {
            $this->set_last_scan_error('Richiesta home fallita: ' . $response->get_error_message());
            return 0;
        }

        $cookies = wp_remote_retrieve_cookies($response);

        if (empty($cookies)) {
            return 0;
        }

        $saved_count = 0;

        foreach ($cookies as $cookie) {
            if (!is_a($cookie, 'WP_Http_Cookie')) {
                continue;
            }

            $name = $cookie->name;

            if (!is_string($name) || '' === $name) {
                continue;
            }

            $category = $this->categorize_cookie($name);
            $description = $this->get_cookie_description($name, $category);
            $domain = $cookie->domain ? $cookie->domain : $this->get_cookie_domain();
            $path = $cookie->path ? $cookie->path : '/';

            $saved_count += $this->save_cookie($name, 'homepage', $category, $description, $domain, $path);
        }

        return $saved_count;
    }
    
    /**
     * Categorizza un cookie in base al nome
     */
    private function categorize_cookie($cookie_name) {
        $wp_cookies = $this->get_wordpress_cookies();
        
        foreach ($wp_cookies['necessary'] as $pattern => $description) {
            if ($this->match_cookie_pattern($cookie_name, $pattern)) {
                return 'necessary';
            }
        }
        
        foreach ($wp_cookies['functional'] as $pattern => $description) {
            if ($this->match_cookie_pattern($cookie_name, $pattern)) {
                return 'functional';
            }
        }
        
        $third_party_cookies = $this->get_third_party_cookies();
        foreach ($third_party_cookies as $pattern => $info) {
            if ($this->match_cookie_pattern($cookie_name, $pattern)) {
                return $info['category'];
            }
        }
        
        return 'functional';
    }
    
    /**
     * Verifica se un nome cookie corrisponde a un pattern
     */
    private function match_cookie_pattern($cookie_name, $pattern) {
        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
            return preg_match($regex, $cookie_name);
        }
        return $cookie_name === $pattern;
    }
    
    /**
     * Ottieni descrizione per un cookie
     */
    private function get_cookie_description($cookie_name, $category) {
        $wp_cookies = $this->get_wordpress_cookies();
        
        foreach ($wp_cookies as $cat => $patterns) {
            foreach ($patterns as $pattern => $description) {
                if ($this->match_cookie_pattern($cookie_name, $pattern)) {
                    return $description;
                }
            }
        }
        
        $third_party_cookies = $this->get_third_party_cookies();
        foreach ($third_party_cookies as $pattern => $info) {
            if ($this->match_cookie_pattern($cookie_name, $pattern)) {
                return $info['description'];
            }
        }
        
        return '';
    }
    
    /**
     * Cookie base di WordPress divisi per categoria
     */
    private function get_wordpress_cookies() {
        return array(
            'necessary' => array(
                'wordpress_test_cookie' => 'Verifica che il browser accetti i cookie',
                'wordpress_logged_in_*' => 'Cookie di autenticazione utente',
                'wordpress_sec_*' => 'Cookie di sicurezza per l\'autenticazione',
                'wp_-*' => 'Cookie di sessione e autenticazione WordPress',
            ),
            'functional' => array(
                'wp-settings-*' => 'Preferenze dell\'interfaccia di amministrazione',
                'wp-settings-time-*' => 'Timestamp delle preferenze utente',
                'comment_author_*' => 'Nome memorizzato per il form dei commenti',
                'comment_author_email_*' => 'Email memorizzata per il form dei commenti',
                'comment_author_url_*' => 'URL memorizzato per il form dei commenti',
                'wp_lang' => 'Preferenza della lingua dell\'utente',
            ),
        );
    }
    
    /**
     * Scansiona i cookie di WordPress
     */
    private function scan_wordpress_cookies() {
        $wp_cookies = $this->get_wordpress_cookies();
        $saved_count = 0;
        
        foreach ($wp_cookies as $category => $patterns) {
            foreach ($patterns as $pattern => $description) {
                $saved_count += $this->save_cookie($pattern, 'wordpress', $category, $description);
            }
        }

        return $saved_count;
    }
    
    /**
     * Cookie di terze parti comuni
     */
    private function get_third_party_cookies() {
        return array(
            '_ga' => array('category' => 'analytics', 'description' => 'Google Analytics cookie'),
            '_gid' => array('category' => 'analytics', 'description' => 'Google Analytics cookie'),
            '_gat' => array('category' => 'analytics', 'description' => 'Google Analytics cookie'),
            '_gac_*' => array('category' => 'marketing', 'description' => 'Google Ads cookie'),
            'NID' => array('category' => 'marketing', 'description' => 'Google cookie'),
            'IDE' => array('category' => 'marketing', 'description' => 'Google DoubleClick cookie'),
            'VISITOR_INFO1_LIVE' => array('category' => 'marketing', 'description' => 'YouTube cookie'),
            'YSC' => array('category' => 'marketing', 'description' => 'YouTube cookie'),
            'PREF' => array('category' => 'marketing', 'description' => 'YouTube cookie'),
            'FPLC' => array('category' => 'marketing', 'description' => 'Facebook Pixel cookie'),
            'fr' => array('category' => 'marketing', 'description' => 'Facebook cookie'),
            'tr' => array('category' => 'marketing', 'description' => 'Facebook cookie'),
            '_fbp' => array('category' => 'marketing', 'description' => 'Facebook Pixel cookie'),
            '_fbc' => array('category' => 'marketing', 'description' => 'Facebook Pixel cookie'),
        );
    }
    
    /**
     * Scansiona i cookie di terze parti comuni
     */
    private function scan_third_party_cookies() {
        $third_party_cookies = $this->get_third_party_cookies();
        $saved_count = 0;
        
        foreach ($third_party_cookies as $cookie => $info) {
            $saved_count += $this->save_cookie($cookie, 'third_party', $info['category'], $info['description']);
        }

        return $saved_count;
    }
    
    /**
     * Salva un cookie nel database
     */
    private function save_cookie($cookie_name, $source = 'unknown', $category = 'functional', $description = '', $domain = '', $path = '/') {
        if ('json' === $this->storage_mode) {
            return $this->save_cookie_to_json($cookie_name, $source, $category, $description, $domain, $path);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'marrison_cookies';

        if (!is_string($cookie_name) || '' === $cookie_name) {
            return 0;
        }
        
        // Verifica se il cookie esiste già
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE cookie_name = %s AND scan_date > DATE_SUB(NOW(), INTERVAL 1 DAY)",
            $cookie_name
        ));
        
        if ($existing) {
            return 0;
        }

        if ('' === $domain) {
            $domain = $this->get_cookie_domain();
        }
        
        $inserted = $wpdb->insert($table_name, array(
            'cookie_name' => $cookie_name,
            'cookie_domain' => $domain,
            'cookie_path' => $path,
            'cookie_category' => $category,
            'cookie_description' => $description,
            'source' => $source,
            'scan_date' => current_time('mysql'),
        ));

        if (false === $inserted) {
            $this->set_last_scan_error('Inserimento cookie "' . $cookie_name . '" fallito: ' . $this->get_db_error_message());
            return 0;
        }

        return 1;
    }

    /**
     * Prepara lo storage fallback basato su file JSON.
     */
    private function prepare_json_scan() {
        return $this->write_json_cookies(array());
    }

    /**
     * Salva un cookie nello storage JSON quando non e disponibile una tabella custom.
     */
    private function save_cookie_to_json($cookie_name, $source = 'unknown', $category = 'functional', $description = '', $domain = '', $path = '/') {
        if (!is_string($cookie_name) || '' === $cookie_name) {
            return 0;
        }

        $cookies = $this->read_json_cookies();

        $max_id = 0;

        foreach ($cookies as $cookie) {
            if (!is_array($cookie)) {
                continue;
            }

            if (isset($cookie['cookie_name']) && $cookie['cookie_name'] === $cookie_name) {
                return 0;
            }

            $max_id = max($max_id, isset($cookie['id']) ? intval($cookie['id']) : 0);
        }

        if ('' === $domain) {
            $domain = $this->get_cookie_domain();
        }

        $cookies[] = array(
            'id' => $max_id + 1,
            'cookie_name' => $cookie_name,
            'cookie_domain' => $domain,
            'cookie_path' => $path ? $path : '/',
            'cookie_expiration' => null,
            'cookie_category' => $category,
            'cookie_description' => $description,
            'source' => $source,
            'scan_date' => current_time('mysql'),
        );

        if (!$this->write_json_cookies($cookies)) {
            return 0;
        }

        return 1;
    }

    /**
     * Legge i cookie dallo storage JSON.
     */
    private function get_json_cookies($category = 'all') {
        $cookies = $this->read_json_cookies();
        $valid_categories = array_keys($this->get_categories());

        $results = array();

        foreach ($cookies as $cookie) {
            if (!is_array($cookie)) {
                continue;
            }

            $cookie_category = isset($cookie['cookie_category']) ? $cookie['cookie_category'] : 'functional';

            if ('uncategorized' === $cookie_category) {
                $cookie_category = 'functional';
            }

            if ($category !== 'all' && in_array($category, $valid_categories, true) && $category !== $cookie_category) {
                continue;
            }

            $cookie['cookie_category'] = $cookie_category;
            $results[] = (object) $cookie;
        }

        usort($results, array($this, 'sort_cookies_by_scan_date_desc'));

        return $results;
    }

    /**
     * Legge il file JSON dei cookie scansionati.
     */
    private function read_json_cookies() {
        $path = $this->get_json_storage_path();

        if (!$path || !file_exists($path)) {
            return array();
        }

        $contents = file_get_contents($path);

        if (false === $contents || '' === trim($contents)) {
            return array();
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            $this->set_last_scan_error('Storage JSON non leggibile: contenuto non valido');
            return array();
        }

        return isset($decoded['cookies']) && is_array($decoded['cookies']) ? $decoded['cookies'] : array();
    }

    /**
     * Scrive il file JSON dei cookie scansionati.
     */
    private function write_json_cookies($cookies) {
        $path = $this->get_json_storage_path();

        if (!$path) {
            return false;
        }

        $payload = array(
            'generated_at' => current_time('mysql'),
            'site_url' => home_url('/'),
            'cookies' => array_values($cookies),
        );

        $written = file_put_contents(
            $path,
            wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );

        if (false === $written) {
            $this->set_last_scan_error('Storage JSON non scrivibile: impossibile scrivere ' . $path);
            return false;
        }

        return true;
    }

    /**
     * Restituisce il percorso del file JSON fallback in uploads.
     */
    private function get_json_storage_path() {
        $upload_dir = wp_upload_dir(null, false);

        if (!empty($upload_dir['error'])) {
            $this->set_last_scan_error('Storage JSON non disponibile: ' . $upload_dir['error']);
            return false;
        }

        if (empty($upload_dir['basedir'])) {
            $this->set_last_scan_error('Storage JSON non disponibile: directory uploads non trovata');
            return false;
        }

        $directory = trailingslashit($upload_dir['basedir']) . 'marrison-cookie';

        if (!wp_mkdir_p($directory)) {
            $this->set_last_scan_error('Storage JSON non disponibile: impossibile creare ' . $directory);
            return false;
        }

        $index_path = trailingslashit($directory) . 'index.html';

        if (!file_exists($index_path)) {
            file_put_contents($index_path, '', LOCK_EX);
        }

        $htaccess_path = trailingslashit($directory) . '.htaccess';

        if (!file_exists($htaccess_path)) {
            file_put_contents($htaccess_path, "Deny from all\n", LOCK_EX);
        }

        return trailingslashit($directory) . 'scanned-cookies.json';
    }

    /**
     * Ordina i cookie piu recenti per primi.
     */
    private function sort_cookies_by_scan_date_desc($a, $b) {
        return strcmp((string) $b->scan_date, (string) $a->scan_date);
    }

    /**
     * Registra l'ultimo errore utile senza sovrascrivere la causa iniziale.
     */
    private function set_last_scan_error($message) {
        if ('' === $this->last_scan_error) {
            $this->last_scan_error = $message;
        }
    }

    /**
     * Restituisce l'errore dell'ultima scansione nella richiesta corrente.
     */
    public function get_last_scan_error() {
        return $this->last_scan_error;
    }

    /**
     * Restituisce informazioni sullo storage usato nella richiesta corrente.
     */
    public function get_storage_info() {
        global $wpdb;

        if ('json' === $this->storage_mode) {
            return array(
                'type' => 'json',
                'label' => 'JSON',
                'detail' => $this->get_json_storage_path(),
            );
        }

        return array(
            'type' => 'table',
            'label' => 'DB',
            'detail' => DB_NAME . '.' . $wpdb->prefix . 'marrison_cookies',
        );
    }

    /**
     * Restituisce un errore DB leggibile anche quando wpdb non popola last_error.
     */
    private function get_db_error_message() {
        global $wpdb;

        return !empty($wpdb->last_error) ? $wpdb->last_error : 'errore database non specificato';
    }
    
    /**
     * Ottieni il dominio del cookie
     */
    private function get_cookie_domain() {
        $domain = parse_url(home_url(), PHP_URL_HOST);
        return $domain ? $domain : '';
    }
    
    /**
     * AJAX: Scansiona i cookie
     */
    public function ajax_scan_cookies() {
        check_ajax_referer('marrison_cookie_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }

        $result = $this->perform_scan();
        $cookies = $this->get_cookies('all');

        if ($result || !empty($cookies)) {
            $storage_info = $this->get_storage_info();
            wp_send_json_success(array(
                'message' => 'Scansione completata con successo',
                'count' => count($cookies),
                'storage' => $storage_info['type'],
                'storage_label' => $storage_info['label'],
                'storage_detail' => $storage_info['detail'],
            ));
        } else {
            $error = $this->last_scan_error;
            wp_send_json_error(array(
                'message' => $error ? 'Scansione completata senza cookie rilevati. Dettaglio: ' . $error : 'Scansione completata senza cookie rilevati',
            ));
        }
    }
    
    /**
     * AJAX: Ottieni i cookie scansionati
     */
    public function ajax_get_scanned_cookies() {
        check_ajax_referer('marrison_cookie_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'all';
        
        $cookies = $this->get_cookies($category);
        
        wp_send_json_success(array('cookies' => $cookies));
    }
    
    /**
     * Ottieni i cookie dal database
     */
    public function get_cookies($category = 'all') {
        global $wpdb;

        if ('json' === $this->storage_mode || !$this->ensure_table_exists()) {
            $this->storage_mode = 'json';
            return $this->get_json_cookies($category);
        }

        $table_name = $wpdb->prefix . 'marrison_cookies';
        $valid_categories = array_keys($this->get_categories());
        
        $where = "WHERE scan_date > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        if ($category !== 'all' && in_array($category, $valid_categories, true)) {
            $where .= $wpdb->prepare(" AND cookie_category = %s", $category);
        }
        
        $cookies = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY scan_date DESC");

        if (!is_array($cookies)) {
            $this->set_last_scan_error('Lettura cookie fallita: ' . $this->get_db_error_message());
            return array();
        }

        foreach ($cookies as $cookie) {
            if ($cookie->cookie_category === 'uncategorized') {
                $cookie->cookie_category = 'functional';
            }
        }
        
        return $cookies;
    }

    /**
     * Elimina un cookie dallo storage attivo.
     */
    public function delete_cookie_record($cookie_id) {
        $cookie_id = intval($cookie_id);

        if (!$cookie_id) {
            return false;
        }

        if ('json' === $this->storage_mode || !$this->ensure_table_exists()) {
            $this->storage_mode = 'json';
            $cookies = $this->read_json_cookies();

            $new_cookies = array();
            $deleted = false;

            foreach ($cookies as $cookie) {
                if (is_array($cookie) && isset($cookie['id']) && intval($cookie['id']) === $cookie_id) {
                    $deleted = true;
                    continue;
                }

                $new_cookies[] = $cookie;
            }

            if ($deleted) {
                return $this->write_json_cookies($new_cookies);
            }

            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'marrison_cookies';

        return (bool) $wpdb->delete($table_name, array('id' => $cookie_id), array('%d'));
    }

    /**
     * Aggiorna la categoria di un cookie nello storage attivo.
     */
    public function update_cookie_record_category($cookie_id, $category) {
        $cookie_id = intval($cookie_id);
        $category = sanitize_key($category);

        if ('uncategorized' === $category) {
            $category = 'functional';
        }

        if (!$cookie_id || !in_array($category, array_keys($this->get_categories()), true)) {
            return false;
        }

        if ('json' === $this->storage_mode || !$this->ensure_table_exists()) {
            $this->storage_mode = 'json';
            $cookies = $this->read_json_cookies();

            $updated = false;

            foreach ($cookies as $index => $cookie) {
                if (is_array($cookie) && isset($cookie['id']) && intval($cookie['id']) === $cookie_id) {
                    $cookies[$index]['cookie_category'] = $category;
                    $updated = true;
                    break;
                }
            }

            if ($updated) {
                return $this->write_json_cookies($cookies);
            }

            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'marrison_cookies';

        return false !== $wpdb->update(
            $table_name,
            array('cookie_category' => $category),
            array('id' => $cookie_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * AJAX: Elimina un cookie
     */
    public function ajax_delete_cookie() {
        check_ajax_referer('marrison_cookie_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $cookie_id = isset($_POST['cookie_id']) ? intval($_POST['cookie_id']) : 0;
        
        if (!$cookie_id) {
            wp_send_json_error(array('message' => 'ID cookie non valido'));
        }
        
        $result = $this->delete_cookie_record($cookie_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Cookie eliminato con successo'));
        } else {
            wp_send_json_error(array('message' => 'Errore durante l\'eliminazione'));
        }
    }
    
    /**
     * AJAX: Aggiorna categoria cookie
     */
    public function ajax_update_cookie_category() {
        check_ajax_referer('marrison_cookie_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $cookie_id = isset($_POST['cookie_id']) ? intval($_POST['cookie_id']) : 0;
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'functional';
        if ($category === 'uncategorized') {
            $category = 'functional';
        }
        $valid_categories = array_keys($this->get_categories());
        
        if (!$cookie_id) {
            wp_send_json_error(array('message' => 'ID cookie non valido'));
        }

        if (!in_array($category, $valid_categories, true)) {
            wp_send_json_error(array('message' => 'Categoria non valida'));
        }
        
        $result = $this->update_cookie_record_category($cookie_id, $category);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Categoria aggiornata con successo'));
        } else {
            wp_send_json_error(array('message' => 'Errore durante l\'aggiornamento'));
        }
    }
    
    /**
     * Ottieni le categorie disponibili
     */
    public function get_categories() {
        return array(
            'necessary' => 'Necessari',
            'functional' => 'Funzionali',
            'analytics' => 'Analitici',
            'marketing' => 'Marketing',
        );
    }
}
