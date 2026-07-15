<?php
/**
 * Classe per la scansione dei cookie
 */
class Marrison_Cookie_Scanner {
    
    private static $instance = null;
    
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
        $this->clear_old_scans();
        $this->scan_current_cookies();
        $this->scan_wordpress_cookies();
        $this->scan_third_party_cookies();
        
        update_option('marrison_cookie_last_scan', time());
        
        return true;
    }
    
    /**
     * Pulisce scansioni vecchie
     */
    private function clear_old_scans() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'marrison_cookies';
        
        // Mantieni solo l'ultima scansione
        $wpdb->query("DELETE FROM $table_name WHERE scan_date < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    }
    
    /**
     * Scansiona i cookie correnti
     */
    private function scan_current_cookies() {
        if (empty($_COOKIE)) {
            return;
        }
        
        foreach ($_COOKIE as $name => $value) {
            $category = $this->categorize_cookie($name);
            $description = $this->get_cookie_description($name, $category);
            $this->save_cookie($name, 'current', $category, $description);
        }
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
        
        foreach ($wp_cookies as $category => $patterns) {
            foreach ($patterns as $pattern => $description) {
                $this->save_cookie($pattern, 'wordpress', $category, $description);
            }
        }
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
        
        foreach ($third_party_cookies as $cookie => $info) {
            $this->save_cookie($cookie, 'third_party', $info['category'], $info['description']);
        }
    }
    
    /**
     * Salva un cookie nel database
     */
    private function save_cookie($cookie_name, $source = 'unknown', $category = 'functional', $description = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'marrison_cookies';
        
        // Verifica se il cookie esiste già
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE cookie_name = %s AND scan_date > DATE_SUB(NOW(), INTERVAL 1 DAY)",
            $cookie_name
        ));
        
        if ($existing) {
            return;
        }
        
        $wpdb->insert($table_name, array(
            'cookie_name' => $cookie_name,
            'cookie_domain' => $this->get_cookie_domain(),
            'cookie_path' => '/',
            'cookie_category' => $category,
            'cookie_description' => $description,
            'source' => $source,
            'scan_date' => current_time('mysql'),
        ));
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
        
        if ($result) {
            wp_send_json_success(array('message' => 'Scansione completata con successo'));
        } else {
            wp_send_json_error(array('message' => 'Errore durante la scansione'));
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
        $table_name = $wpdb->prefix . 'marrison_cookies';
        $valid_categories = array_keys($this->get_categories());
        
        $where = "WHERE scan_date > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        if ($category !== 'all' && in_array($category, $valid_categories, true)) {
            $where .= $wpdb->prepare(" AND cookie_category = %s", $category);
        }
        
        $cookies = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY scan_date DESC");

        foreach ($cookies as $cookie) {
            if ($cookie->cookie_category === 'uncategorized') {
                $cookie->cookie_category = 'functional';
            }
        }
        
        return $cookies;
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
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'marrison_cookies';
        
        $result = $wpdb->delete($table_name, array('id' => $cookie_id), array('%d'));
        
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
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'marrison_cookies';
        
        $result = $wpdb->update(
            $table_name,
            array('cookie_category' => $category),
            array('id' => $cookie_id),
            array('%s'),
            array('%d')
        );
        
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
