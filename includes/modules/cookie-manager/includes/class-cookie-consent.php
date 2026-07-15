<?php
/**
 * Classe per la gestione del consenso dei cookie
 */
class Marrison_Cookie_Consent {
    
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
        // Hook per bloccare script prima del consenso
        add_action('wp_enqueue_scripts', array($this, 'manage_scripts'), 999);
        
        // Hook per shortcode per mostrare preferenze
        add_shortcode('marrison_cookie_preferences', array($this, 'render_preferences_shortcode'));
        
        // AJAX per aggiornare preferenze
        add_action('wp_ajax_marrison_update_preferences', array($this, 'ajax_update_preferences'));
        add_action('wp_ajax_nopriv_marrison_update_preferences', array($this, 'ajax_update_preferences'));
    }
    
    /**
     * Gestisci script in base al consenso
     */
    public function manage_scripts() {
        if (class_exists('Marrison_Addon_Context') && !Marrison_Addon_Context::is_public_frontend_request()) {
            return;
        }

        $consent = $this->get_user_consent();

        if ($consent === 'accept_all') {
            return;
        }
        
        // Blocca script di analisi e marketing se non c'è consenso
        if (!$consent || $consent === 'reject_all') {
            $this->block_analytics_scripts();
            $this->block_marketing_scripts();
        } elseif ($consent === 'custom') {
            $categories = $this->get_consent_categories();
            
            if (!in_array('analytics', $categories, true)) {
                $this->block_analytics_scripts();
            }
            
            if (!in_array('marketing', $categories, true)) {
                $this->block_marketing_scripts();
            }
        }
    }
    
    /**
     * Verifica se l'utente ha dato il consenso
     */
    private function has_consent() {
        return isset($_COOKIE['marrison_cookie_consent']);
    }
    
    /**
     * Ottieni il consenso dell'utente
     */
    private function get_user_consent() {
        if (isset($_COOKIE['marrison_cookie_consent'])) {
            return sanitize_text_field(wp_unslash($_COOKIE['marrison_cookie_consent']));
        }
        
        $user_id = get_current_user_id();
        if ($user_id) {
            $consent = get_user_meta($user_id, 'marrison_cookie_consent', true);
            if ($consent && isset($consent['consent_type'])) {
                return $consent['consent_type'];
            }
        }
        
        return false;
    }
    
    /**
     * Ottieni le categorie accettate
     */
    private function get_consent_categories() {
        if (isset($_COOKIE['marrison_cookie_categories'])) {
            $categories = explode('|', sanitize_text_field(wp_unslash($_COOKIE['marrison_cookie_categories'])));
            return $this->sanitize_categories($categories);
        }

        $user_id = get_current_user_id();
        
        if ($user_id) {
            $consent = get_user_meta($user_id, 'marrison_cookie_consent', true);
            if ($consent && isset($consent['categories'])) {
                return $this->sanitize_categories($consent['categories']);
            }
        }
        
        return array();
    }
    
    /**
     * Blocca script di analisi
     */
    private function block_analytics_scripts() {
        // Rimuovi Google Analytics
        wp_dequeue_script('google-analytics');
        wp_dequeue_script('google-analytics-gtag');
        
        // Rimuovi altri script di analisi comuni
        wp_dequeue_script('jetpack-stats');
        wp_dequeue_script('monsterinsights-frontend-script');
    }
    
    /**
     * Blocca script di marketing
     */
    private function block_marketing_scripts() {
        // Rimuovi Facebook Pixel
        wp_dequeue_script('facebook-pixel');
        
        // Rimuovi altri script di marketing
        wp_dequeue_script('hotjar');
        wp_dequeue_script('addthis');
    }
    
    /**
     * Renderizza shortcode preferenze
     */
    public function render_preferences_shortcode() {
        ob_start();
        include MARRISON_COOKIE_PLUGIN_DIR . 'templates/cookie-preferences.php';
        return ob_get_clean();
    }
    
    /**
     * AJAX: Aggiorna preferenze
     */
    public function ajax_update_preferences() {
        check_ajax_referer('marrison_cookie_nonce', 'nonce');
        
        $categories = isset($_POST['categories']) && is_array($_POST['categories']) ? $this->sanitize_categories(wp_unslash($_POST['categories'])) : array();
        if (!in_array('necessary', $categories, true)) {
            array_unshift($categories, 'necessary');
        }
        
        // Aggiorna consenso
        $this->save_user_consent('custom', $categories);
        
        // Aggiorna cookie
        $consent_duration = get_option('marrison_cookie_consent_duration', 30);
        $expiry = time() + ($consent_duration * DAY_IN_SECONDS);
        
        // Il frontend deve poter leggere questi cookie per mantenere nascoste le preferenze tra le pagine.
        setcookie('marrison_cookie_consent', 'custom', $expiry, '/', COOKIE_DOMAIN, is_ssl(), false);
        setcookie('marrison_cookie_categories', implode('|', $categories), $expiry, '/', COOKIE_DOMAIN, is_ssl(), false);
        
        // Gestisci cookie
        $banner = Marrison_Cookie_Banner::get_instance();
        $banner->handle_consent('custom', $categories);
        
        wp_send_json_success(array('message' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Preferenze aggiornate con successo', 'Preferences updated successfully') : 'Preferenze aggiornate con successo'));
    }
    
    /**
     * Salva consenso utente
     */
    private function save_user_consent($consent_type, $categories) {
        $user_id = get_current_user_id();
        $ip_address = $this->get_user_ip();
        
        $consent_data = array(
            'consent_type' => $consent_type,
            'categories' => $categories,
            'timestamp' => current_time('mysql'),
            'user_id' => $user_id,
            'ip_address' => $ip_address,
        );
        
        if ($user_id) {
            update_user_meta($user_id, 'marrison_cookie_consent', $consent_data);
        } else {
            set_transient('marrison_consent_' . md5($ip_address), $consent_data, 30 * DAY_IN_SECONDS);
        }
    }
    
    /**
     * Ottieni IP utente
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded_for = explode(',', wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
            return sanitize_text_field(trim($forwarded_for[0]));
        } else {
            return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        }
    }

    /**
     * Mantiene solo categorie supportate.
     */
    private function sanitize_categories($categories) {
        $valid_categories = array('necessary', 'functional', 'analytics', 'marketing');
        $sanitized = array();

        foreach ($categories as $category) {
            $category = sanitize_key($category);
            if (in_array($category, $valid_categories, true)) {
                $sanitized[] = $category;
            }
        }

        return array_values(array_unique($sanitized));
    }
    
    /**
     * Ottieni statistiche consenso
     */
    public function get_consent_stats() {
        global $wpdb;
        
        // Conta consensi da user meta
        $user_consents = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->usermeta}
            WHERE meta_key = 'marrison_cookie_consent'
        ");
        
        // Conta consensi da transients (approssimazione)
        // Nota: i transients non sono facilmente queryabili, quindi questo è un'approssimazione
        
        return array(
            'total_consents' => $user_consents,
            'accept_all' => $this->count_consent_type('accept_all'),
            'reject_all' => $this->count_consent_type('reject_all'),
            'custom' => $this->count_consent_type('custom'),
        );
    }
    
    /**
     * Conta consensi per tipo
     */
    private function count_consent_type($type) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->usermeta}
            WHERE meta_key = 'marrison_cookie_consent'
            AND meta_value LIKE %s
        ", '%' . $type . '%'));
    }
}
