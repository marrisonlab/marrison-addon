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
        add_action('template_redirect', array($this, 'start_output_buffer'), 0);
        add_action('wp_head', array($this, 'render_runtime'), -10000);
        
        // Hook per shortcode per mostrare preferenze
        add_shortcode('marrison_cookie_preferences', array($this, 'render_preferences_shortcode'));
        
        // AJAX per aggiornare preferenze
        add_action('wp_ajax_marrison_update_preferences', array($this, 'ajax_update_preferences'));
        add_action('wp_ajax_nopriv_marrison_update_preferences', array($this, 'ajax_update_preferences'));
    }

    /**
     * Avvia un buffer HTML per neutralizzare script/iframe traccianti hardcoded.
     */
    public function start_output_buffer() {
        if (is_admin() || wp_doing_ajax() || (class_exists('Marrison_Addon_Context') && !Marrison_Addon_Context::is_public_frontend_request())) {
            return;
        }

        ob_start(array($this, 'filter_output'));
    }

    /**
     * Blocca preventivamente script e iframe noti finche manca il consenso alla categoria.
     */
    public function filter_output($html) {
        if (!is_string($html) || '' === $html) {
            return $html;
        }

        $html = preg_replace_callback('/<script\b([^>]*)>(.*?)<\/script>/is', array($this, 'filter_script_tag'), $html);
        $html = preg_replace_callback('/<iframe\b([^>]*)>/is', array($this, 'filter_iframe_tag'), $html);
        $html = preg_replace_callback('/<img\b([^>]*)>/is', function($matches) {
            $category = $this->detect_tracking_category($matches[1]);
            if (!$category || $this->is_category_allowed($category)) { return $matches[0]; }
            $attributes = $this->get_blocked_attributes($matches[1], false);
            return '<img data-marrison-cookie-blocked="img" data-marrison-cookie-category="' . esc_attr($category) . '"' . $attributes . '>';
        }, $html);

        return $html;
    }

    /**
     * Neutralizza script esterni o inline associati ad analytics/marketing.
     */
    private function filter_script_tag($matches) {
        $attributes = isset($matches[1]) ? $matches[1] : '';
        if (preg_match('/\bid\s*=\s*["\'](?:marrison-cookie-runtime-config|marrison-cookie-runtime-js|marrison-scan-config)["\']/i', $attributes)) { return $matches[0]; }
        $body = isset($matches[2]) ? $matches[2] : '';
        $haystack = $attributes . "\n" . $body;
        $category = $this->detect_tracking_category($haystack);

        if (!$category || $this->is_category_allowed($category)) {
            return $matches[0];
        }

        $blocked_attributes = $this->get_blocked_attributes($attributes, true);

        return '<script type="text/plain" data-marrison-cookie-blocked="script" data-marrison-cookie-category="' . esc_attr($category) . '"' . $blocked_attributes . '>' . $body . '</script>';
    }

    /**
     * Neutralizza iframe di terze parti che possono impostare tracciamenti.
     */
    private function filter_iframe_tag($matches) {
        $attributes = isset($matches[1]) ? $matches[1] : '';
        $category = $this->detect_tracking_category($attributes);

        if (!$category || $this->is_category_allowed($category)) {
            return $matches[0];
        }

        $blocked_attributes = $this->get_blocked_attributes($attributes, false);

        return '<iframe data-marrison-cookie-blocked="iframe" data-marrison-cookie-category="' . esc_attr($category) . '"' . $blocked_attributes . '>';
    }

    /** Preserve original values while neutralizing quoted and unquoted attributes. */
    private function get_blocked_attributes($attributes, $is_script) {
        return preg_replace_callback('/\s+([^\s=\/>]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'<>`]+))?/', function($attribute) use ($is_script) {
            $name = strtolower($attribute[1]);
            if ('src' !== $name && 'srcset' !== $name && !($is_script && 'type' === $name)) {
                return $attribute[0];
            }

            $value = isset($attribute[2]) ? $attribute[2] : '';
            if (isset($value[0]) && ('"' === $value[0] || "'" === $value[0])) {
                $value = substr($value, 1, -1);
            }
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            $blocked_name = 'type' === $name ? 'data-marrison-script-type' : 'data-marrison-blocked-' . $name;
            return ' ' . $blocked_name . '="' . esc_attr($value) . '"';
        }, $attributes);
    }

    /**
     * Associa URL/snippet comuni alle categorie consenso.
     */
    private function detect_tracking_category($content) {
        $content = strtolower((string) $content);
        if (preg_match('/data-marrison-cookie-category\s*=\s*["\']?(necessary|functional|analytics|marketing)\b/', $content, $manual)) { return $manual[1]; }

        foreach ($this->tracking_rules() as $category => $patterns) {
            foreach ($patterns as $pattern) { if ($pattern !== '' && strpos($content, strtolower($pattern)) !== false) { return $category; } }
        }

        $analytics_patterns = array(
            'google-analytics.com',
            'googletagmanager.com/gtag/js',
            'gtag(',
            'ga(',
            'dataLayer',
            'monsterinsights',
            'stats.wp.com',
            'matomo',
            'plausible.io',
        );

        foreach ($analytics_patterns as $pattern) {
            if (false !== strpos($content, strtolower($pattern))) {
                return 'analytics';
            }
        }

        $marketing_patterns = array(
            'googletagmanager.com/gtm.js',
            'connect.facebook.net',
            'facebook.com/tr',
            'fbq(',
            'doubleclick.net',
            'googleadservices.com',
            'googlesyndication.com',
            'hotjar',
            'clarity.ms',
            'youtube.com',
            'youtube-nocookie.com',
            'vimeo.com',
            'google.com/maps',
            'maps.googleapis.com',
        );

        foreach ($marketing_patterns as $pattern) {
            if (false !== strpos($content, strtolower($pattern))) {
                return 'marketing';
            }
        }

        return false;
    }

    /**
     * Verifica se una categoria opzionale e consentita.
     */
    private function is_category_allowed($category) {
        if ($category === 'necessary') { return true; }
        if (class_exists('Marrison_Addon_Cookie_Scan_Engine', false)) {
            $phase = Marrison_Addon_Cookie_Scan_Engine::get_instance()->frame_phase();
            if ($phase !== null) { return $category === 'necessary' || $phase === 'after'; }
        }
        $consent = $this->get_user_consent();

        if ('accept_all' === $consent) {
            return true;
        }

        if ('custom' === $consent) {
            return in_array($category, $this->get_consent_categories(), true);
        }

        return false;
    }
    
    /**
     * Gestisci script in base al consenso
     */
    public function manage_scripts() {
        if (class_exists('Marrison_Addon_Context') && !Marrison_Addon_Context::is_public_frontend_request()) {
            return;
        }

        if (!$this->is_category_allowed('analytics')) { $this->block_analytics_scripts(); }
        if (!$this->is_category_allowed('marketing')) { $this->block_marketing_scripts(); }
    }

    public function tracking_rules() {
        $rules = array(
            'analytics' => array('google-analytics.com', 'googletagmanager.com/gtag/js', 'stats.wp.com', 'matomo', 'plausible.io', 'clarity.ms', 'hotjar'),
            'marketing' => array('googletagmanager.com/gtm.js', 'connect.facebook.net', 'facebook.com/tr', 'doubleclick.net', 'googleadservices.com', 'googlesyndication.com', 'youtube.com', 'youtube-nocookie.com', 'vimeo.com', 'google.com/maps', 'maps.googleapis.com'),
            'functional' => array(),
        );
        foreach (array_keys($rules) as $category) {
            $custom = get_option('marrison_cookie_' . $category . '_sources', '');
            foreach (is_string($custom) ? preg_split('/\R/', $custom) : array() as $rule) {
                $rule = trim($rule);
                if ($rule !== '' && strlen($rule) <= 255) { $rules[$category][] = $rule; }
            }
        }
        return $rules;
    }

    public function render_runtime() {
        if (is_admin() || (class_exists('Marrison_Addon_Context') && !Marrison_Addon_Context::is_public_frontend_request())) { return; }
        $scanner = Marrison_Cookie_Scanner::get_instance();
        $cookies = $scanner->runtime_cookie_rules();
        $phase = class_exists('Marrison_Addon_Cookie_Scan_Engine', false) ? Marrison_Addon_Cookie_Scan_Engine::get_instance()->frame_phase() : null;
        echo '<script id="marrison-cookie-runtime-config">window.marrisonConsentRules=' . wp_json_encode(array('sources' => $this->tracking_rules(), 'cookies' => $cookies, 'phase' => $phase), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
        $plugin = dirname(__DIR__, 4) . '/marrison-addon.php';
        $revision = class_exists('Marrison_Addon_Cookie_Scan_Engine', false) ? Marrison_Addon_Cookie_Scan_Engine::REVISION : '20261018.1';
        echo '<script id="marrison-cookie-runtime-js" src="' . esc_url(plugins_url('includes/modules/cookie-manager/assets/js/consent-runtime.js', $plugin)) . '?ver=' . esc_attr($revision) . '"></script>';
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
