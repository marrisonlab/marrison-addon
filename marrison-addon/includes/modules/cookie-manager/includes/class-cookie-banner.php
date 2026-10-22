<?php
/**
 * Classe per la visualizzazione del banner cookie
 */
class Marrison_Cookie_Banner {
    
    private static $instance = null;
    private $policy_page_id = null;
    
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

        // AJAX per rinnovare il nonce quando la pagina arriva da cache.
        add_action('wp_ajax_marrison_cookie_refresh_nonce', array($this, 'ajax_refresh_nonce'));
        add_action('wp_ajax_nopriv_marrison_cookie_refresh_nonce', array($this, 'ajax_refresh_nonce'));

        add_shortcode('marrison_cookie_policy', array($this, 'render_policy_shortcode'));
        add_filter('the_content', array($this, 'append_policy_details'), 20);
    }
    
    /**
     * Carica asset frontend
     */
    public function enqueue_assets() {
        if (!$this->should_render_consent_ui()) {
            return;
        }

        $frontend_css_path = MARRISON_COOKIE_PLUGIN_DIR . 'assets/css/frontend.css';
        $frontend_js_path = MARRISON_COOKIE_PLUGIN_DIR . 'assets/js/frontend.js';
        $frontend_css_version = class_exists('Marrison_Addon') ? Marrison_Addon::asset_version('includes/modules/cookie-manager/assets/css/frontend.css') : (file_exists($frontend_css_path) ? (string) filemtime($frontend_css_path) : MARRISON_COOKIE_VERSION);
        $frontend_js_version = class_exists('Marrison_Addon') ? Marrison_Addon::asset_version('includes/modules/cookie-manager/assets/js/frontend.js') : (file_exists($frontend_js_path) ? (string) filemtime($frontend_js_path) : MARRISON_COOKIE_VERSION);

        wp_enqueue_style(
            'marrison-cookie-frontend',
            MARRISON_COOKIE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            $frontend_css_version
        );
        
        wp_enqueue_script(
            'marrison-cookie-frontend',
            MARRISON_COOKIE_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            $frontend_js_version,
            true
        );
        
        wp_localize_script('marrison-cookie-frontend', 'marrisonCookie', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('marrison_cookie_nonce'),
            'consentDuration' => Marrison_Addon_Cookie_Consent_Store::duration(),
            'loadingText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Caricamento cookie...', 'Loading cookies...') : __('Caricamento cookie...', 'marrison-cookie'),
            'customizeTitle' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Personalizza Cookie', 'Customize Cookies') : __('Personalizza Cookie', 'marrison-cookie'),
            'acceptAll' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Accetta tutti', 'Accept all') : __('Accetta tutti', 'marrison-cookie'),
            'rejectAll' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Rifiuta tutti', 'Reject all') : __('Rifiuta tutti', 'marrison-cookie'),
            'savePreferences' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Salva Preferenze', 'Save preferences') : __('Salva Preferenze', 'marrison-cookie'),
            'privacyPolicy' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Privacy Policy', 'Privacy Policy') : __('Privacy Policy', 'marrison-cookie'),
            'cookiePolicy' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Cookie Policy', 'Cookie Policy') : __('Cookie Policy', 'marrison-cookie'),
            'hasConsent' => false,
            'showBanner' => (bool) get_option('marrison_cookie_show_banner', true),
            'scanPhase' => class_exists('Marrison_Addon_Cookie_Scan_Engine', false) ? Marrison_Addon_Cookie_Scan_Engine::get_instance()->frame_phase() : null,
            'closedTriggerModes' => $this->get_closed_trigger_modes(),
        ));
    }

    /**
     * Ottiene la modalità di accesso alle preferenze a banner chiuso per device.
     */
    private function get_closed_trigger_modes() {
        return array(
            'desktop' => $this->sanitize_closed_trigger_mode(get_option('marrison_cookie_closed_trigger_desktop', 'floating')),
            'tablet'  => $this->sanitize_closed_trigger_mode(get_option('marrison_cookie_closed_trigger_tablet', 'floating')),
            'mobile'  => $this->sanitize_closed_trigger_mode(get_option('marrison_cookie_closed_trigger_mobile', 'floating')),
        );
    }

    /**
     * Sanitizza la modalità di accesso alle preferenze dopo la chiusura del banner.
     */
    private function sanitize_closed_trigger_mode($mode) {
        $mode = sanitize_key((string) $mode);
        return in_array($mode, array('floating', 'link'), true) ? $mode : 'floating';
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
        echo '<style>#marrison-cookie-banner{display:none}</style>' . "\n";
    }
    
    /**
     * Verifica se mostrare il banner
     */
    private function should_show_banner() {
        if (!get_option('marrison_cookie_show_banner', true)) {
            return false;
        }
        
        // Verifica se l'utente ha già dato il consenso
        if (Marrison_Addon_Cookie_Consent_Store::get_instance()->current()) {
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
        $banner_hidden = !get_option('marrison_cookie_show_banner', true);
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

        return true;
    }

    /**
     * Riconosce solo la pagina locale configurata come Cookie Policy.
     */
    private function is_cookie_policy_page() {
        if (!is_page()) {
            return false;
        }

        if ($this->policy_page_id === null) {
            $url = get_option('marrison_cookie_cookie_policy_url', '');
            $this->policy_page_id = $url ? url_to_postid($url) : 0;
        }

        return $this->policy_page_id > 0 && is_page($this->policy_page_id);
    }

    private function page_has_policy_shortcode() {
        if (!is_singular()) {
            return false;
        }
        $post = get_post(get_queried_object_id());
        if ($post && $this->has_policy_shortcode($post->post_content)) {
            return true;
        }

        // Il widget Shortcode di Elementor salva il contenuto nei dati del documento.
        $elementor_data = get_post_meta(get_queried_object_id(), '_elementor_data', true);
        return is_string($elementor_data) && $this->has_policy_shortcode($elementor_data);
    }

    private function has_policy_shortcode($content) {
        return has_shortcode($content, 'marrison_cookie_policy') || has_shortcode($content, 'marrison_cookie_policy_content');
    }

    /**
     * Integra anche le policy esistenti, senza modificare il contenuto salvato.
     */
    public function append_policy_details($content) {
        if (is_admin() || is_feed() || !$this->is_cookie_policy_page() || get_the_ID() !== get_queried_object_id()) {
            return $content;
        }
        if ($this->has_policy_shortcode($content) || $this->page_has_policy_shortcode() || strpos($content, 'marrison-cookie-policy-details') !== false) {
            return $content;
        }

        return $content . $this->render_policy_shortcode();
    }

    /**
     * Pubblica i metadati della scansione corrente tramite lo storage esistente.
     */
    public function render_policy_shortcode() {
        $cookies = Marrison_Cookie_Scanner::get_instance()->get_cookies('all');
        $categories = $this->get_cookie_categories();
        $cookies_by_category = array_fill_keys(array_keys($categories), array());
        foreach ($cookies as $cookie) {
            $cookies_by_category[$this->normalize_cookie_category($cookie->cookie_category)][] = $cookie;
        }

        ob_start();
        include MARRISON_COOKIE_PLUGIN_DIR . 'templates/cookie-policy.php';
        return ob_get_clean();
    }

    /** Le date salvate in UTC vengono mostrate nel fuso orario del sito. */
    public function get_cookie_expiration_label($cookie) {
        if (!empty($cookie->cookie_expiration)) {
            $timestamp = strtotime($cookie->cookie_expiration . ' UTC');
            if ($timestamp !== false) {
                return wp_date('d/m/Y H:i', $timestamp);
            }
        }
        if (isset($cookie->cookie_expiration_status) && $cookie->cookie_expiration_status === 'session') {
            return marrison_cookie_site_text('Sessione', 'Session');
        }
        return marrison_cookie_site_text('Non rilevabile', 'Not detectable');
    }
    
    /**
     * AJAX: Salva consenso dell'utente
     */
    public function ajax_save_consent() {
        $type = isset($_POST['consent_type']) && is_string($_POST['consent_type']) ? sanitize_key(wp_unslash($_POST['consent_type'])) : '';
        $categories = isset($_POST['categories']) && is_array($_POST['categories']) ? $this->sanitize_categories(wp_unslash($_POST['categories'])) : array();
        Marrison_Addon_Cookie_Consent_Store::get_instance()->save_request($type, $categories);
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

    public function ajax_refresh_nonce() {
        wp_send_json_success(array(
            'nonce' => wp_create_nonce('marrison_cookie_nonce'),
        ));
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
            
            if (in_array($name, array('marrison_cookie_consent', 'marrison_cookie_categories', 'marrison_cookie_state'), true)) { $is_essential = true; }
            if (!$is_essential && Marrison_Cookie_Scanner::get_instance()->classify_cookie($name)['category'] === 'necessary') { $is_essential = true; }
            if (!$is_essential) {
                $this->delete_cookie($name);
            }
        }
        $this->handle_custom_consent(array('necessary'));
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
                $this->delete_cookie($cookie->cookie_name, isset($cookie->cookie_domain) ? $cookie->cookie_domain : '', isset($cookie->cookie_path) ? $cookie->cookie_path : '/');
            }
        }
    }
    
    /**
     * Elimina un cookie
     */
    private function delete_cookie($name, $domain = '', $path = '/') {
        if (in_array($name, array('marrison_cookie_consent', 'marrison_cookie_categories', 'marrison_cookie_state'), true)) { return; }
        $host = strtolower(wp_parse_url(home_url('/'), PHP_URL_HOST));
        $domain = strtolower(ltrim((string) $domain, '.'));
        if ($domain && $domain !== $host && substr($host, -strlen('.' . $domain)) !== '.' . $domain) { return; }
        $domains = array_unique(array('', (string) COOKIE_DOMAIN, $domain, $domain ? '.' . $domain : ''));
        foreach ($domains as $scope) {
            setcookie($name, '', array('expires' => time() - 3600, 'path' => $path ?: '/', 'domain' => $scope, 'secure' => is_ssl(), 'httponly' => false, 'samesite' => 'Lax'));
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
