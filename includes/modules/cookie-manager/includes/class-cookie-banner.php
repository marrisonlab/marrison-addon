<?php
/**
 * Classe per la visualizzazione del banner cookie
 */
class Marrison_Cookie_Banner {
    
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
        // Carica asset frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Nascondi banner in head se consenso già dato (evita flickering)
        add_action('wp_head', array($this, 'maybe_hide_banner_css'));
        
        // Mostra banner nel footer
        add_action('wp_footer', array($this, 'render_banner'));
        
        // Mostra widget flottante sempre nel footer
        add_action('wp_footer', array($this, 'render_floating_widget'), 20);
        
        // AJAX per salvare consenso
        add_action('wp_ajax_marrison_save_consent', array($this, 'ajax_save_consent'));
        add_action('wp_ajax_nopriv_marrison_save_consent', array($this, 'ajax_save_consent'));
        
        // AJAX per ottenere elenco cookie scansionati
        add_action('wp_ajax_marrison_get_cookie_list', array($this, 'ajax_get_cookie_list'));
        add_action('wp_ajax_nopriv_marrison_get_cookie_list', array($this, 'ajax_get_cookie_list'));
    }
    
    /**
     * Carica asset frontend
     */
    public function enqueue_assets() {
        if (!$this->should_render_consent_ui()) {
            return;
        }

        wp_enqueue_style(
            'marrison-cookie-frontend',
            MARRISON_COOKIE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            MARRISON_COOKIE_VERSION
        );
        
        wp_enqueue_script(
            'marrison-cookie-frontend',
            MARRISON_COOKIE_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            MARRISON_COOKIE_VERSION,
            true
        );
        
        wp_localize_script('marrison-cookie-frontend', 'marrisonCookie', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('marrison_cookie_nonce'),
            'consentDuration' => get_option('marrison_cookie_consent_duration', 30),
            'loadingText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Caricamento cookie...', 'Loading cookies...') : __('Caricamento cookie...', 'marrison-cookie'),
            'customizeTitle' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Personalizza Cookie', 'Customize Cookies') : __('Personalizza Cookie', 'marrison-cookie'),
            'acceptAll' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Accetta tutti', 'Accept all') : __('Accetta tutti', 'marrison-cookie'),
            'rejectAll' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Rifiuta tutti', 'Reject all') : __('Rifiuta tutti', 'marrison-cookie'),
            'savePreferences' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Salva Preferenze', 'Save preferences') : __('Salva Preferenze', 'marrison-cookie'),
            'privacyPolicy' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Privacy Policy', 'Privacy Policy') : __('Privacy Policy', 'marrison-cookie'),
            'cookiePolicy' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Cookie Policy', 'Cookie Policy') : __('Cookie Policy', 'marrison-cookie'),
            'hasConsent' => isset($_COOKIE['marrison_cookie_consent']),
        ));
    }
    
    /**
     * Renderizza widget flottante
     */
    public function render_floating_widget() {
        if (!$this->should_render_consent_ui()) {
            return;
        }
        include MARRISON_COOKIE_PLUGIN_DIR . 'templates/floating-widget.php';
    }
    
    /**
     * Stampa CSS in head per nascondere banner se consenso esiste
     */
    public function maybe_hide_banner_css() {
        if (!$this->should_render_consent_ui()) {
            return;
        }
        if (isset($_COOKIE['marrison_cookie_consent'])) {
            echo '<style>#marrison-cookie-banner{display:none}</style>' . "\n";
        }
    }
    
    /**
     * Verifica se mostrare il banner
     */
    private function should_show_banner() {
        if (!get_option('marrison_cookie_show_banner', true)) {
            return false;
        }
        
        // Verifica se l'utente ha già dato il consenso
        if (isset($_COOKIE['marrison_cookie_consent'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Renderizza il banner
     */
    public function render_banner() {
        if (!$this->should_render_consent_ui()) {
            return;
        }
        
        // Se l'utente ha già dato il consenso, renderizza il banner nascosto
        $banner_hidden = isset($_COOKIE['marrison_cookie_consent']);
        $is_english = function_exists('marrison_cookie_is_english_site') && marrison_cookie_is_english_site();
        
        $banner_layout = get_option('marrison_cookie_banner_layout', 'bar');
        $banner_position = get_option('marrison_cookie_banner_position', 'bottom');
        $box_position = get_option('marrison_cookie_box_position', 'bottom-right');
        $banner_title = get_option('marrison_cookie_banner_title', $is_english ? 'Cookie Management' : 'Gestione Cookie');
        $banner_description = get_option('marrison_cookie_banner_description', $is_english ? 'We use cookies to improve your experience. For more information, read our privacy policy.' : 'Utilizziamo i cookie per migliorare la tua esperienza. Per maggiori informazioni, leggi la nostra privacy policy.');
        $accept_button_text = get_option('marrison_cookie_accept_button_text', $is_english ? 'Accept all' : 'Accetta tutti');
        $reject_button_text = get_option('marrison_cookie_reject_button_text', $is_english ? 'Reject all' : 'Rifiuta tutti');
        $customize_button_text = get_option('marrison_cookie_customize_button_text', $is_english ? 'Customize' : 'Personalizza');
        $privacy_policy_url = get_option('marrison_cookie_privacy_policy_url', '');
        $cookie_policy_url = get_option('marrison_cookie_cookie_policy_url', '');

        if ($is_english) {
            if ($banner_title === 'Gestione Cookie') {
                $banner_title = 'Cookie Management';
            }
            if ($banner_description === 'Utilizziamo i cookie per migliorare la tua esperienza. Per maggiori informazioni, leggi la nostra privacy policy.') {
                $banner_description = 'We use cookies to improve your experience. For more information, read our privacy policy.';
            }
            if ($accept_button_text === 'Accetta tutti') {
                $accept_button_text = 'Accept all';
            }
            if ($reject_button_text === 'Rifiuta tutti') {
                $reject_button_text = 'Reject all';
            }
            if ($customize_button_text === 'Personalizza') {
                $customize_button_text = 'Customize';
            }
        }
        
        $banner_bg_color = get_option('marrison_cookie_banner_background_color', '#ffffff');
        $banner_text_color = get_option('marrison_cookie_banner_text_color', '#333333');
        $button_bg_color = get_option('marrison_cookie_button_background_color', '#0073aa');
        $button_text_color = get_option('marrison_cookie_button_text_color', '#ffffff');
        
        include MARRISON_COOKIE_PLUGIN_DIR . 'templates/cookie-banner.php';
    }

    /**
     * Mostra la UI se il banner è attivo o se esiste già un consenso da gestire.
     */
    private function should_render_consent_ui() {
        if (class_exists('Marrison_Addon_Context') && !Marrison_Addon_Context::is_public_frontend_request()) {
            return false;
        }

        return get_option('marrison_cookie_show_banner', true) || isset($_COOKIE['marrison_cookie_consent']);
    }
    
    /**
     * AJAX: Salva consenso dell'utente
     */
    public function ajax_save_consent() {
        check_ajax_referer('marrison_cookie_nonce', 'nonce');
        
        $consent_type = isset($_POST['consent_type']) ? sanitize_text_field(wp_unslash($_POST['consent_type'])) : '';
        $categories = isset($_POST['categories']) && is_array($_POST['categories']) ? $this->sanitize_categories(wp_unslash($_POST['categories'])) : array();
        $valid_consent_types = array('accept_all', 'reject_all', 'custom');
        
        if (!in_array($consent_type, $valid_consent_types, true)) {
            wp_send_json_error(array('message' => 'Tipo di consenso non valido'));
        }

        if ($consent_type === 'accept_all') {
            $categories = array('necessary', 'functional', 'analytics', 'marketing');
        } elseif ($consent_type === 'reject_all') {
            $categories = array('necessary');
        }
        
        // Salva consenso nel database
        $this->save_user_consent($consent_type, $categories);
        
        // Imposta cookie
        $consent_duration = get_option('marrison_cookie_consent_duration', 30);
        $expiry = time() + ($consent_duration * DAY_IN_SECONDS);
        
        // Il frontend deve poter leggere questi cookie per mantenere nascosto il banner tra le pagine.
        setcookie('marrison_cookie_consent', $consent_type, $expiry, '/', COOKIE_DOMAIN, is_ssl(), false);
        setcookie('marrison_cookie_categories', implode('|', $categories), $expiry, '/', COOKIE_DOMAIN, is_ssl(), false);
        
        // Gestisci i cookie in base al consenso
        $this->handle_consent($consent_type, $categories);
        
        wp_send_json_success(array('message' => 'Consenso salvato con successo'));
    }
    
    /**
     * AJAX: Ottieni elenco cookie scansionati
     */
    public function ajax_get_cookie_list() {
        check_ajax_referer('marrison_cookie_nonce', 'nonce');
        
        $scanner = Marrison_Cookie_Scanner::get_instance();
        $cookies = $scanner->get_cookies('all');
        $categories = $this->get_cookie_categories();
        $cookies_by_category = array();

        foreach (array_keys($categories) as $category_key) {
            $cookies_by_category[$category_key] = array();
        }
        
        if (empty($cookies)) {
            wp_send_json_success(array(
                'categories' => array(),
                'message' => __('Nessun cookie rilevato dalla scansione.', 'marrison-cookie')
            ));
        }
        
        foreach ($cookies as $cookie) {
            $cat = $this->normalize_cookie_category($cookie->cookie_category);
            $cookies_by_category[$cat][] = $cookie;
        }
        
        $category_html = array();

        foreach ($cookies_by_category as $cat_key => $cat_cookies) {
            if (empty($cat_cookies)) {
                $category_html[$cat_key] = '';
                continue;
            }

            ob_start();
            ?>
            <div class="marrison-modal-cookie-category" data-category="<?php echo esc_attr($cat_key); ?>">
                <ul>
                    <?php foreach ($cat_cookies as $cookie): ?>
                        <li>
                            <strong><?php echo esc_html($cookie->cookie_name); ?></strong>
                            <?php if (!empty($cookie->cookie_domain)): ?>
                                <span class="marrison-cookie-domain"><?php echo esc_html($cookie->cookie_domain); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
            $category_html[$cat_key] = ob_get_clean();
        }
        
        wp_send_json_success(array('categories' => $category_html));
    }
    
    /**
     * Salva consenso dell'utente nel database
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
        
        // Salva come opzione per utenti loggati, o in transients per visitatori
        if ($user_id) {
            update_user_meta($user_id, 'marrison_cookie_consent', $consent_data);
        } else {
            set_transient('marrison_consent_' . md5($ip_address), $consent_data, 30 * DAY_IN_SECONDS);
        }
    }
    
    /**
     * Ottieni IP dell'utente
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
     * Gestisci i cookie in base al consenso
     */
    public function handle_consent($consent_type, $categories) {
        if ($consent_type === 'accept_all') {
            // Accetta tutti i cookie - non fare nulla, lascia i cookie esistenti
            return;
        } elseif ($consent_type === 'reject_all') {
            // Elimina i cookie non necessari
            $this->delete_non_essential_cookies();
        } elseif ($consent_type === 'custom') {
            // Gestisci in base alle categorie selezionate
            $this->handle_custom_consent($categories);
        }
    }
    
    /**
     * Elimina i cookie non essenziali
     */
    private function delete_non_essential_cookies() {
        $essential_cookies = array(
            'wordpress_test_cookie',
            'wp-settings-*',
            'wp-settings-time-*',
            'wordpress_logged_in_*',
            'wordpress_sec_*',
            'comment_author_*',
            'comment_author_email_*',
            'comment_author_url_*',
        );
        
        foreach ($_COOKIE as $name => $value) {
            $is_essential = false;
            
            foreach ($essential_cookies as $pattern) {
                if (fnmatch($pattern, $name)) {
                    $is_essential = true;
                    break;
                }
            }
            
            if (!$is_essential) {
                $this->delete_cookie($name);
            }
        }
    }
    
    /**
     * Gestisci consenso personalizzato
     */
    private function handle_custom_consent($categories) {
        // Ottieni i cookie scansionati per categoria
        $scanner = Marrison_Cookie_Scanner::get_instance();
        $all_cookies = $scanner->get_cookies('all');
        
        foreach ($all_cookies as $cookie) {
            $category = $this->normalize_cookie_category($cookie->cookie_category);
            
            // Se la categoria non è stata accettata, elimina il cookie
            if (!in_array($category, $categories, true) && $category !== 'necessary') {
                $this->delete_cookie($cookie->cookie_name);
            }
        }
    }
    
    /**
     * Elimina un cookie
     */
    private function delete_cookie($name) {
        $domain = COOKIE_DOMAIN;
        $path = '/';
        
        setcookie($name, '', time() - 3600, $path, $domain);
        setcookie($name, '', time() - 3600, $path, $domain, is_ssl());
        
        // Elimina anche con sottodomini
        if (strpos($domain, '.') !== false) {
            $wildcard_domain = '.' . $domain;
            setcookie($name, '', time() - 3600, $path, $wildcard_domain);
            setcookie($name, '', time() - 3600, $path, $wildcard_domain, is_ssl());
        }
    }
    
    /**
     * Ottieni le categorie di cookie
     */
    public function get_cookie_categories() {
        return array(
            'necessary' => array(
                'name' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Necessari', 'Necessary') : 'Necessari',
                'description' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Cookie essenziali per il funzionamento del sito', 'Essential cookies required for the website to function') : 'Cookie essenziali per il funzionamento del sito',
                'required' => true,
            ),
            'functional' => array(
                'name' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Funzionali', 'Functional') : 'Funzionali',
                'description' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Cookie che migliorano le funzionalità del sito', 'Cookies that improve website functionality') : 'Cookie che migliorano le funzionalità del sito',
                'required' => false,
            ),
            'analytics' => array(
                'name' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Analitici', 'Analytics') : 'Analitici',
                'description' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Cookie per analizzare il traffico del sito', 'Cookies used to analyze website traffic') : 'Cookie per analizzare il traffico del sito',
                'required' => false,
            ),
            'marketing' => array(
                'name' => 'Marketing',
                'description' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Cookie per il marketing e la pubblicità', 'Cookies used for marketing and advertising') : 'Cookie per il marketing e la pubblicità',
                'required' => false,
            ),
        );
    }

    /**
     * Mantiene solo categorie supportate.
     */
    private function sanitize_categories($categories) {
        $valid_categories = array_keys($this->get_cookie_categories());
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
     * Gli uncategorized vengono trattati come funzionali nel frontend e nel consenso.
     */
    private function normalize_cookie_category($category) {
        $category = sanitize_key($category);

        if ($category === 'uncategorized' || !array_key_exists($category, $this->get_cookie_categories())) {
            return 'functional';
        }

        return $category;
    }
}
