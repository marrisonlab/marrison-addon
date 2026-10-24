<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Classe per la scansione dei cookie
 */
class Marrison_Cookie_Scanner {
    
    private static $instance = null;
    private $storage_mode = 'table';
    private $last_scan_error = '';
    private $last_scan_succeeded = false;
    private $scan_categories = array();
    
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
        // Ripristina la pianificazione senza eseguire la scansione nella richiesta del visitatore.
        add_action('init', array($this, 'schedule_cron'));
        
        // Hook AJAX per scansione manuale
        add_action('wp_ajax_marrison_scan_cookies', array($this, 'ajax_scan_cookies'));
        add_action('wp_ajax_marrison_get_scanned_cookies', array($this, 'ajax_get_scanned_cookies'));
        add_action('wp_ajax_marrison_delete_cookie', array($this, 'ajax_delete_cookie'));
        add_action('wp_ajax_marrison_update_cookie_category', array($this, 'ajax_update_cookie_category'));
        
        // Cron job per scansione periodica
        add_action('marrison_cookie_daily_scan', array($this, 'maybe_auto_scan'));
        
        // Schedula cron se non esiste
        add_action('wp', array($this, 'schedule_cron'));
    }
    
    /**
     * Schedula cron job
     */
    public static function auto_scan_enabled() {
        // Older installations stored true by default: it is not evidence of an opt-in.
        return get_option('marrison_cookie_auto_scan', false) && get_option('marrison_cookie_auto_scan_confirmed', false);
    }

    public function schedule_cron() {
        if (class_exists('Marrison_Addon') && method_exists('Marrison_Addon', 'is_module_enabled') && !Marrison_Addon::is_module_enabled('cookie_manager')) { return; }
        if (!self::auto_scan_enabled()) {
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
        if (class_exists('Marrison_Addon') && method_exists('Marrison_Addon', 'is_module_enabled') && !Marrison_Addon::is_module_enabled('cookie_manager')) { return; }
        if (!self::auto_scan_enabled()) {
            return;
        }
        
        $last_scan = get_option('marrison_cookie_last_scan', 0);
        $interval = max(1, min(30, (int) get_option('marrison_cookie_scan_interval', 7))) * DAY_IN_SECONDS;
        
        if (time() - $last_scan >= $interval) {
            if (class_exists('Marrison_Addon_Cookie_Scan_Engine', false)) {
                Marrison_Addon_Cookie_Scan_Engine::get_instance()->start_scheduled_scan();
            } else {
                $this->perform_scan();
            }
        }
    }
    
    /**
     * Esegue la scansione dei cookie
     */
    public function perform_scan() {
        if (class_exists('Marrison_Addon_Cookie_Scan_Engine', false)) {
            try { return Marrison_Addon_Cookie_Scan_Engine::get_instance()->start_local_scan(); }
            catch (Throwable $error) { $this->set_last_scan_error($error->getMessage()); return 0; }
        }
        $this->last_scan_error = '';
        $this->last_scan_succeeded = false;

        if (!$this->ensure_table_exists()) {
            $this->storage_mode = 'json';

            if (!$this->prepare_json_scan()) {
                update_option('marrison_cookie_last_scan', time());
                return 0;
            }
        } else {
            $this->storage_mode = 'table';
        }

        // Un fallback JSON riuscito conserva la diagnosi DB senza fallire la scansione.
        $this->last_scan_succeeded = true;
        $saved_count = 0;

        if (!$this->clear_old_scans()) {
            return 0;
        }
        $saved_count += $this->scan_current_cookies();
        $saved_count += $this->scan_homepage_cookies();
        
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
        // Runtime rules are read on public pages too; schema migrations belong to installation/recovery.
        if ($this->table_exists($table_name) && $wpdb->get_var("SHOW COLUMNS FROM $quoted_table_name WHERE Field = 'cookie_expiration_status'") === 'cookie_expiration_status') {
            return true;
        }
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $quoted_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            cookie_name varchar(255) NOT NULL,
            cookie_domain varchar(255) DEFAULT '',
            cookie_path varchar(255) DEFAULT '/',
            cookie_expiration datetime DEFAULT NULL,
            cookie_expiration_status varchar(20) DEFAULT 'unknown',
            cookie_category varchar(50) DEFAULT 'functional',
            cookie_description text,
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
                cookie_expiration_status varchar(20) DEFAULT 'unknown',
                cookie_category varchar(50) DEFAULT 'functional',
                cookie_description text,
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

        if ($wpdb->get_var("SHOW COLUMNS FROM $quoted_table_name WHERE Field = 'cookie_expiration_status'") !== 'cookie_expiration_status') {
            $this->set_last_scan_error('Tabella cookie non aggiornata: manca il campo cookie_expiration_status.');
            $this->storage_mode = 'json';
            return false;
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
        $described = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name)));

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
     * Rimuove dati sintetici e duplicati, preservando categorie e metadati osservati.
     */
    private function clear_old_scans() {
        if ('json' === $this->storage_mode) {
            return true;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'marrison_cookies';
        
        // L'anzianità non annulla osservazioni o classificazioni amministrative.
        $rows = $wpdb->get_results("SELECT * FROM $table_name ORDER BY scan_date DESC, id DESC");
        if (!is_array($rows)) {
            $this->set_last_scan_error('Pulizia cookie fallita: ' . $this->get_db_error_message());
            return false;
        }
        $records = $this->normalize_scan_records($rows);
        $originals = array();
        foreach ($rows as $row) {
            $originals[(int) $row->id] = (array) $row;
        }
        $kept_ids = array();
        foreach ($records as $record) {
            $id = (int) $record->id;
            $kept_ids[$id] = true;
            $changes = array();
            foreach (array('cookie_category', 'cookie_domain', 'cookie_path', 'cookie_expiration', 'cookie_expiration_status', 'source') as $field) {
                if (property_exists($record, $field) && (!array_key_exists($field, $originals[$id]) || $record->$field !== $originals[$id][$field])) {
                    $changes[$field] = $record->$field;
                }
            }
            if ($changes && false === $wpdb->update($table_name, $changes, array('id' => $id))) {
                $this->set_last_scan_error('Consolidamento cookie fallito: ' . $this->get_db_error_message());
                return false;
            }
        }
        $remove_ids = array();
        foreach ($rows as $row) {
            if (!isset($kept_ids[(int) $row->id]) && in_array($row->source, array('wordpress', 'third_party', 'current', 'homepage', 'crawl', 'browser'), true)) {
                $remove_ids[] = (int) $row->id;
            }
        }
        foreach (array_chunk($remove_ids, 200) as $ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            if (false === $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id IN ($placeholders)", $ids))) {
                $this->set_last_scan_error('Rimozione duplicati cookie fallita: ' . $this->get_db_error_message());
                return false;
            }
        }
        return true;
    }

    /** Le richieste admin non descrivono i cookie di un visitatore anonimo. */
    private function is_administrative_cookie($name) {
        return $name === 'wordpress_test_cookie'
            || strpos($name, 'wordpress_logged_in_') === 0
            || strpos($name, 'wordpress_sec_') === 0
            || preg_match('/^wordpress_[a-f0-9]{32}$/i', $name)
            || preg_match('/^wp-settings(?:-time)?-\d+$/', $name);
    }

    private function cookie_record_key($name, $domain, $path) {
        return $name . "\0" . strtolower(ltrim($domain, '.')) . "\0" . ($path === '' ? '/' : $path);
    }

    /** I cataloghi restano dizionari di classificazione, mai prove di presenza. */
    private function normalize_scan_records($rows) {
        $records = array();
        foreach ($rows as $row) {
            $records[] = (object) (array) $row;
        }
        usort($records, array($this, 'sort_cookies_by_scan_date_desc'));
        $this->remember_scan_categories($records);
        $unique = array();
        foreach ($records as $record) {
            if (empty($record->cookie_name) || !isset($record->source) || !in_array($record->source, array('current', 'homepage', 'crawl', 'browser', 'catalogo'), true)) {
                continue;
            }
            if ($record->source === 'current' && $this->is_administrative_cookie($record->cookie_name)) {
                continue;
            }
            $record->cookie_domain = !empty($record->cookie_domain) ? strtolower(ltrim($record->cookie_domain, '.')) : $this->get_cookie_domain();
            $record->observed = $record->source !== 'catalogo';
            $record->cookie_path = !empty($record->cookie_path) ? $record->cookie_path : '/';
            $default_category = $this->categorize_cookie($record->cookie_name);
            $category = isset($record->cookie_category) ? $record->cookie_category : $default_category;
            $record->cookie_category = array_key_exists($category, $this->get_categories()) ? $category : 'functional';
            $key = $this->cookie_record_key($record->cookie_name, $record->cookie_domain, $record->cookie_path);
            if ($record->cookie_category === $default_category && isset($this->scan_categories[$key])) {
                $record->cookie_category = $this->scan_categories[$key];
            }
            if (!isset($unique[$key])) {
                $unique[$key] = $record;
                continue;
            }
            $kept = $unique[$key];
            // Una categoria diversa da quella automatica conserva la scelta manuale.
            if ($kept->cookie_category === $default_category && $record->cookie_category !== $default_category) {
                $kept->cookie_category = $record->cookie_category;
            }
            if (!in_array($kept->source, array('homepage', 'crawl', 'browser'), true) && in_array($record->source, array('homepage', 'crawl', 'browser'), true)) {
                foreach (array('cookie_expiration', 'cookie_expiration_status', 'source') as $field) {
                    $kept->$field = isset($record->$field) ? $record->$field : null;
                }
            }
        }
        foreach ($unique as $record) {
            // scan_date is stored in the site's local timezone by current_time().
            $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $observed = !empty($record->scan_date) ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $record->scan_date, $timezone) : false;
            $record->observation_stale = !$observed || $observed->getTimestamp() < time() - 30 * 86400;
        }
        return array_values($unique);
    }

    private function remember_scan_categories($rows) {
        $this->scan_categories = array();
        foreach ($rows as $row) {
            $row = (array) $row;
            if (empty($row['cookie_name']) || !isset($row['cookie_category']) || !array_key_exists($row['cookie_category'], $this->get_categories())) {
                continue;
            }
            $key = $this->cookie_record_key($row['cookie_name'], isset($row['cookie_domain']) ? $row['cookie_domain'] : $this->get_cookie_domain(), isset($row['cookie_path']) ? $row['cookie_path'] : '/');
            $default_category = $this->categorize_cookie($row['cookie_name']);
            if (!isset($this->scan_categories[$key]) || $this->scan_categories[$key] === $default_category) {
                $this->scan_categories[$key] = $row['cookie_category'];
            }
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
            if ($this->is_administrative_cookie($name)) {
                continue;
            }
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

        $headers = wp_remote_retrieve_headers($response);
        // getValues evita di unire gli header Set-Cookie alle virgole di Expires.
        $set_cookie_headers = is_object($headers) && is_callable(array($headers, 'getValues'))
            ? $headers->getValues('set-cookie') : wp_remote_retrieve_header($response, 'set-cookie');
        if (is_string($set_cookie_headers)) {
            $set_cookie_headers = preg_split('/,(?=\s*[^=;,\s]+=)/', $set_cookie_headers);
        }
        $set_cookie_headers = is_array($set_cookie_headers) ? $set_cookie_headers : array();
        $observed_at = time();

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

            $expiration = $this->get_http_cookie_expiration($cookie, $set_cookie_headers, $observed_at);
            $saved_count += $this->save_cookie($name, 'homepage', $category, $description, $domain, $path, $expiration['value'], $expiration['status']);
        }

        return $saved_count;
    }

    /**
     * Legge solo attributi osservati: Max-Age prevale su Expires.
     * L'assenza di entrambi prova la sessione solo in un header Set-Cookie.
     */
    private function get_http_cookie_expiration($cookie, $headers, $observed_at) {
        $unknown = array('value' => null, 'status' => 'unknown');
        $attributes = null;
        foreach ($headers as $header) {
            if (!is_string($header)) {
                continue;
            }
            $parts = explode(';', $header);
            $pair = explode('=', array_shift($parts), 2);
            if (count($pair) !== 2 || trim($pair[0]) !== $cookie->name) {
                continue;
            }
            $candidate = array();
            foreach ($parts as $part) {
                $attribute = explode('=', trim($part), 2);
                $candidate[strtolower(trim($attribute[0]))] = isset($attribute[1]) ? trim($attribute[1]) : '';
            }
            if (isset($candidate['domain']) && strtolower(ltrim($candidate['domain'], '.')) !== strtolower(ltrim((string) $cookie->domain, '.'))) {
                continue;
            }
            if (isset($candidate['path']) && $candidate['path'] !== $cookie->path) {
                continue;
            }
            $attributes = $candidate;
        }

        if ($attributes !== null) {
            if (isset($attributes['max-age']) && preg_match('/^-?\d+$/', $attributes['max-age'])) {
                if ((float) $attributes['max-age'] <= 253402300799 - $observed_at) {
                    $seconds = (int) $attributes['max-age'];
                    return $this->normalize_cookie_expiration($observed_at + max(0, $seconds));
                }
            }
            if (isset($attributes['expires'])) {
                if (preg_match('/^-?\d+$/', $attributes['expires'])) {
                    return $unknown;
                }
                return $this->normalize_cookie_expiration($attributes['expires']);
            }
            if (!isset($attributes['max-age'])) {
                return array('value' => null, 'status' => 'session');
            }
            return $unknown;
        }

        return isset($cookie->expires) ? $this->normalize_cookie_expiration($cookie->expires) : $unknown;
    }

    /** Memorizza le date in UTC, senza durata convenzionale per cookie sconosciuti. */
    private function normalize_cookie_expiration($expires) {
        if (is_string($expires) && !preg_match('/^-?\d+$/', $expires)) {
            $expires = preg_replace('/^(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)(?:day)?[,\s]+/i', '', trim($expires));
            $parsed = date_parse($expires);
            foreach (array('year', 'month', 'day', 'hour', 'minute', 'second') as $part) {
                if ($parsed[$part] === false || $parsed['error_count'] || $parsed['warning_count']) {
                    return array('value' => null, 'status' => 'unknown');
                }
            }
        }
        $timestamp = is_int($expires) || (is_string($expires) && preg_match('/^-?\d+$/', $expires))
            ? (int) $expires : (is_string($expires) ? strtotime($expires) : false);
        $date = $timestamp !== false ? gmdate('Y-m-d H:i:s', $timestamp) : '';
        if (!preg_match('/^[1-9]\d{3}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date)) {
            return array('value' => null, 'status' => 'unknown');
        }
        return array('value' => $date, 'status' => 'persistent');
    }

    /**
     * Categorizza un cookie in base al nome
     */
    private function categorize_cookie($cookie_name) {
        if (in_array($cookie_name, array('marrison_cookie_consent', 'marrison_cookie_categories', 'marrison_cookie_state', 'PHPSESSID', 'woocommerce_cart_hash', 'woocommerce_items_in_cart'), true) || strpos($cookie_name, 'wp_woocommerce_session_') === 0) {
            return 'necessary';
        }
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
        if (in_array($cookie_name, array('marrison_cookie_consent', 'marrison_cookie_categories', 'marrison_cookie_state'), true)) {
            return 'Memorizza la scelta e le categorie del consenso cookie';
        }
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
     * Cookie di terze parti comuni
     */
    private function get_third_party_cookies() {
        return array(
            '_ga' => array('category' => 'analytics', 'description' => 'Google Analytics cookie'),
            '_ga_*' => array('category' => 'analytics', 'description' => 'Stato della sessione Google Analytics'),
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
     * Salva un cookie nel database
     */
    private function save_cookie($cookie_name, $source = 'unknown', $category = 'functional', $description = '', $domain = '', $path = '/', $expiration = null, $expiration_status = 'unknown') {
        $domain = strtolower(ltrim($domain === '' ? $this->get_cookie_domain() : $domain, '.'));
        $path = $path === '' ? '/' : $path;
        if ('json' === $this->storage_mode) {
            return $this->save_cookie_to_json($cookie_name, $source, $category, $description, $domain, $path, $expiration, $expiration_status);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'marrison_cookies';

        if (!is_string($cookie_name) || '' === $cookie_name) {
            return 0;
        }
        
        // Cookie con dominio o percorso diversi restano osservazioni distinte.
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE cookie_name = %s AND cookie_domain = %s AND cookie_path = %s ORDER BY scan_date DESC, id DESC LIMIT 1",
            $cookie_name, $domain, $path
        ));
        if (!empty($wpdb->last_error)) {
            $this->set_last_scan_error('Verifica cookie "' . $cookie_name . '" fallita: ' . $this->get_db_error_message());
            return 0;
        }
        
        if ($existing) {
            if ($source === 'catalogo' && $existing->source !== 'catalogo') { return 1; }
            $data = array('scan_date' => current_time('mysql'));
            if (!in_array($existing->source, array('homepage', 'crawl', 'browser'), true)) {
                $data['source'] = $source;
            }
            if (in_array($source, array('homepage', 'crawl', 'browser'), true) && ($expiration_status !== 'unknown' || empty($existing->cookie_expiration_status) || $existing->cookie_expiration_status === 'unknown')) {
                $data = array_merge($data, array(
                    'cookie_expiration' => $expiration,
                    'cookie_expiration_status' => $expiration_status,
                    'source' => $source,
                ));
            }
            if (false === $wpdb->update($table_name, $data, array('id' => $existing->id))) {
                $this->set_last_scan_error('Aggiornamento cookie "' . $cookie_name . '" fallito: ' . $this->get_db_error_message());
                return 0;
            }
            return 1;
        }

        $key = $this->cookie_record_key($cookie_name, $domain, $path);
        $inserted = $wpdb->insert($table_name, array(
            'cookie_name' => $cookie_name,
            'cookie_domain' => $domain,
            'cookie_path' => $path,
            'cookie_expiration' => $expiration,
            'cookie_expiration_status' => $expiration_status,
            'cookie_category' => isset($this->scan_categories[$key]) ? $this->scan_categories[$key] : $category,
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
        $rows = $this->normalize_scan_records($this->read_json_cookies());
        return $this->write_json_cookies(array_map(function($row) { return (array) $row; }, $rows));
    }

    /**
     * Salva un cookie nello storage JSON quando non e disponibile una tabella custom.
     */
    private function save_cookie_to_json($cookie_name, $source = 'unknown', $category = 'functional', $description = '', $domain = '', $path = '/', $expiration = null, $expiration_status = 'unknown') {
        if (!is_string($cookie_name) || '' === $cookie_name) {
            return 0;
        }

        $cookies = $this->read_json_cookies();

        $max_id = 0;
        $key = $this->cookie_record_key($cookie_name, $domain, $path);

        foreach ($cookies as $index => $cookie) {
            if (!is_array($cookie)) {
                continue;
            }

            if (isset($cookie['cookie_name']) && $this->cookie_record_key($cookie['cookie_name'], isset($cookie['cookie_domain']) ? $cookie['cookie_domain'] : $this->get_cookie_domain(), isset($cookie['cookie_path']) ? $cookie['cookie_path'] : '/') === $key) {
                if ($source === 'catalogo' && ($cookie['source'] ?? '') !== 'catalogo') { return 1; }
                $cookies[$index]['scan_date'] = current_time('mysql');
                if (!isset($cookie['source']) || !in_array($cookie['source'], array('homepage', 'crawl', 'browser'), true)) {
                    $cookies[$index]['source'] = $source;
                }
                if (in_array($source, array('homepage', 'crawl', 'browser'), true) && ($expiration_status !== 'unknown' || empty($cookie['cookie_expiration_status']) || $cookie['cookie_expiration_status'] === 'unknown')) {
                    $cookies[$index]['cookie_expiration'] = $expiration;
                    $cookies[$index]['cookie_expiration_status'] = $expiration_status;
                    $cookies[$index]['cookie_domain'] = $domain;
                    $cookies[$index]['cookie_path'] = $path;
                    $cookies[$index]['source'] = $source;
                    $cookies[$index]['scan_date'] = current_time('mysql');
                }
                return $this->write_json_cookies($cookies) ? 1 : 0;
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
            'cookie_expiration' => $expiration,
            'cookie_expiration_status' => $expiration_status,
            'cookie_category' => isset($this->scan_categories[$key]) ? $this->scan_categories[$key] : $category,
            'cookie_description' => $description,
            'source' => $source,
            'observed' => $source !== 'catalogo',
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
        return $this->filter_scan_category($this->normalize_scan_records($this->read_json_cookies()), $category);
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
        $declared = (int) (($a->source ?? '') === 'catalogo') <=> (int) (($b->source ?? '') === 'catalogo');
        if ($declared) { return $declared; }
        $by_date = strcmp(isset($b->scan_date) ? (string) $b->scan_date : '', isset($a->scan_date) ? (string) $a->scan_date : '');
        return $by_date !== 0 ? $by_date : ((isset($b->id) ? (int) $b->id : 0) <=> (isset($a->id) ? (int) $a->id : 0));
    }

    /**
     * Registra l'ultimo errore utile senza sovrascrivere la causa iniziale.
     */
    private function set_last_scan_error($message) {
        $this->last_scan_succeeded = false;
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

    public function was_last_scan_successful() {
        return $this->last_scan_succeeded;
    }

    /** Prepare the real storage once without treating administrator cookies as scan results. */
    public function engine_prepare() {
        $this->last_scan_error = '';
        if ($this->ensure_table_exists()) {
            $this->storage_mode = 'table';
            if (!$this->clear_old_scans()) { return false; }
        } else {
            $this->storage_mode = 'json';
            $this->last_scan_error = '';
            $cookies = $this->normalize_scan_records($this->read_json_cookies());
            if ($this->last_scan_error !== '') { return false; }
            if (!$this->write_json_cookies(array_map(function($cookie) { return (array) $cookie; }, $cookies))) { return false; }
        }
        return $this->scan_categories;
    }

    public function classify_cookie($name) {
        $category = $this->categorize_cookie($name);
        $description = $this->get_cookie_description($name, $category);
        return array('category' => $category, 'description' => $description, 'recognized' => $description !== '' || $category === 'necessary');
    }

    public function runtime_cookie_rules() {
        $rules = array();
        foreach ($this->get_cookies('all') as $cookie) { $rules[] = array('name' => $cookie->cookie_name, 'category' => $cookie->cookie_category, 'domain' => $cookie->cookie_domain, 'path' => isset($cookie->cookie_path) ? $cookie->cookie_path : '/'); }
        foreach ($this->get_wordpress_cookies() as $category => $patterns) {
            foreach ($patterns as $pattern => $description) { $rules[] = array('name' => $pattern, 'category' => $category); }
        }
        foreach ($this->get_third_party_cookies() as $pattern => $info) { $rules[] = array('name' => $pattern, 'category' => $info['category']); }
        return $rules;
    }

    public function engine_store($records, $categories, $complete = false) {
        if ($this->ensure_table_exists()) { $this->storage_mode = 'table'; }
        else { $this->storage_mode = 'json'; }
        // An unavailable table is acceptable only after a successful JSON write.
        $this->last_scan_error = '';
        $existing = $this->get_cookies('all');
        if ($this->last_scan_error !== '') { return false; }
        $this->scan_categories = array_replace(is_array($categories) ? $categories : array(), $this->scan_categories);
        $observed = array();
        foreach ($records as $record) {
            $observed[$this->cookie_record_key($record['cookie_name'], $record['cookie_domain'], $record['cookie_path'])] = true;
            $classification = $this->classify_cookie($record['cookie_name']);
            $source = ($record['source'] ?? '') === 'catalogo' ? 'catalogo' : 'crawl';
            if ($source === 'catalogo') { $classification = array('category' => $record['category'], 'description' => $record['description']); }
            foreach ($record['observations'] ?? array() as $observation) { if (in_array($observation['method'], array('browser', 'remote_browser'), true)) { $source = 'browser'; break; } }
            if (!$this->save_cookie($record['cookie_name'], $source, $classification['category'], $classification['description'], $record['cookie_domain'], $record['cookie_path'], $record['cookie_expiration'], $record['cookie_expiration_status'])) {
                return false;
            }
        }
        // An incomplete crawl must retain earlier observations; a complete one replaces the inventory.
        if ($complete) {
            if ($this->storage_mode === 'json') {
                $rows = array_filter($this->read_json_cookies(), function($row) use ($observed) {
                    return is_array($row) && isset($row['cookie_name']) && isset($observed[$this->cookie_record_key($row['cookie_name'], $row['cookie_domain'] ?? '', $row['cookie_path'] ?? '/')]);
                });
                return $this->write_json_cookies(array_values($rows));
            }
            $ids = array();
            foreach ($existing as $cookie) {
                if (!isset($observed[$this->cookie_record_key($cookie->cookie_name, $cookie->cookie_domain, $cookie->cookie_path)])) { $ids[] = (int) $cookie->id; }
            }
            if ($ids) {
                global $wpdb;
                $table = $wpdb->prefix . 'marrison_cookies';
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                if ($wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($placeholders)", $ids)) === false) {
                    $this->set_last_scan_error('Rimozione dei cookie non più osservati fallita: ' . $this->get_db_error_message());
                    return false;
                }
            }
        }
        return true;
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
        if (class_exists('Marrison_Addon_Cookie_Scan_Engine', false)) {
            wp_send_json_error(array('message' => 'Lo scanner è stato aggiornato. Ricarica il pannello Cookie Manager per avviare la scansione completa.'));
        }
        $this->perform_scan();
        $cookies = $this->get_cookies('all');

        if ($this->was_last_scan_successful()) {
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
                'message' => 'Scansione non completata: ' . $error,
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
        $cookies = $wpdb->get_results("SELECT * FROM $table_name ORDER BY scan_date DESC, id DESC");

        if (!is_array($cookies)) {
            $this->set_last_scan_error('Lettura cookie fallita: ' . $this->get_db_error_message());
            return array();
        }

        return $this->filter_scan_category($this->normalize_scan_records($cookies), $category);
    }

    private function filter_scan_category($cookies, $category) {
        if (!array_key_exists($category, $this->get_categories())) {
            return $cookies;
        }
        return array_values(array_filter($cookies, function($cookie) use ($category) {
            return $cookie->cookie_category === $category;
        }));
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
