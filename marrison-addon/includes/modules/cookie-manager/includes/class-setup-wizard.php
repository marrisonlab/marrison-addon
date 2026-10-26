<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Classe per il wizard di configurazione iniziale
 */
class Marrison_Setup_Wizard {
    
    private static $instance = null;
    private $wizard_step = 1;
    private $total_steps = 6;
    
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
        // Aggiungi trigger per aprire wizard dopo attivazione
        add_action('admin_init', array($this, 'check_wizard_activation'));
        
        // AJAX per wizard
        add_action('wp_ajax_marrison_wizard_scan_cookies', array($this, 'ajax_scan_cookies'));
        add_action('wp_ajax_marrison_wizard_save_step', array($this, 'ajax_save_step'));
        add_action('wp_ajax_marrison_wizard_create_pages', array($this, 'ajax_create_pages'));
        add_action('wp_ajax_marrison_wizard_finish', array($this, 'ajax_finish'));
        add_action('wp_ajax_marrison_wizard_dismiss', array($this, 'ajax_dismiss'));
        add_action('wp_ajax_marrison_wizard_open', array($this, 'ajax_open'));

        add_shortcode('marrison_privacy_policy', array($this, 'render_privacy_policy_shortcode'));
        add_shortcode('marrison_cookie_policy_content', array($this, 'render_cookie_policy_shortcode'));
        
        // Enqueue wizard assets solo nella pagina del plugin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_wizard_assets'));
        
        // Output wizard popup
        add_action('admin_footer', array($this, 'output_wizard_popup'));
    }
    
    /**
     * Controlla se il wizard deve essere aperto dopo l'attivazione
     */
    public function check_wizard_activation() {
        // Verifica se è stata appena attivata una nuova installazione
        if (get_transient('marrison_cookie_just_activated')) {
            delete_transient('marrison_cookie_just_activated');
            
            // Verifica se il wizard è già stato completato
            if (!get_option('marrison_cookie_wizard_completed', false)) {
                // Imposta flag per aprire il wizard
                update_option('marrison_cookie_wizard_should_open', true);
            }
        }
    }
    
    /**
     * Enqueue wizard assets
     */
    public function enqueue_wizard_assets($hook) {
        if (!$this->is_plugin_admin_page($hook)) {
            return;
        }

        wp_enqueue_script(
            'marrison-cookie-privacy-profile',
            MARRISON_COOKIE_PLUGIN_URL . 'assets/js/privacy-profile.js',
            array(),
            (string) filemtime(MARRISON_COOKIE_PLUGIN_DIR . 'assets/js/privacy-profile.js'),
            true
        );

        wp_enqueue_style(
            'marrison-wizard',
            MARRISON_COOKIE_PLUGIN_URL . 'assets/css/wizard.css',
            array(),
            MARRISON_COOKIE_VERSION
        );
        
        wp_enqueue_script(
            'marrison-wizard',
            MARRISON_COOKIE_PLUGIN_URL . 'assets/js/wizard.js',
            array('jquery'),
            MARRISON_COOKIE_VERSION,
            true
        );
        
        wp_localize_script('marrison-wizard', 'marrisonWizard', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('marrison_wizard_nonce'),
            'currentStep' => $this->get_current_step(),
            'totalSteps' => $this->total_steps,
            'shouldOpen' => get_option('marrison_cookie_wizard_should_open', false),
            'stepText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Step {step} di {total}', 'Step {step} of {total}') : __('Step {step} di {total}', 'marrison-cookie'),
            'nextText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Avanti', 'Next') : __('Avanti', 'marrison-cookie'),
            'completeText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Completa', 'Finish') : __('Completa', 'marrison-cookie'),
            'scanningText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Scansione in corso...', 'Scanning...') : __('Scansione in corso...', 'marrison-cookie'),
            'scanSuccessText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Scansione completata!', 'Scan completed!') : __('Scansione completata!', 'marrison-cookie'),
            'scanErrorText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Errore durante la scansione', 'Error while scanning') : __('Errore durante la scansione', 'marrison-cookie'),
            'connectionErrorText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Errore di connessione', 'Connection error') : __('Errore di connessione', 'marrison-cookie'),
            'noCookiesText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Nessun cookie rilevato', 'No cookies found') : __('Nessun cookie rilevato', 'marrison-cookie'),
            'finishErrorText' => marrison_cookie_site_text('Impossibile completare la configurazione.', 'Unable to finish setup.'),
            'categoryNecessary' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Necessari', 'Necessary') : __('Necessari', 'marrison-cookie'),
            'categoryFunctional' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Funzionali', 'Functional') : __('Funzionali', 'marrison-cookie'),
            'categoryAnalytics' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Analitici', 'Analytics') : __('Analitici', 'marrison-cookie'),
            'categoryMarketing' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Marketing', 'Marketing') : __('Marketing', 'marrison-cookie'),
        ));
    }
    
    /**
     * Output wizard popup nel footer
     */
    public function output_wizard_popup() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!$this->is_plugin_admin_page()) {
            return;
        }
        
        // Includi sempre il wizard nel DOM per permettere riapertura manuale
        $current_step = $this->get_current_step();
        
        include MARRISON_COOKIE_PLUGIN_DIR . 'templates/setup-wizard.php';
    }

    /**
     * Verifica se siamo nella pagina admin del plugin.
     */
    private function is_plugin_admin_page($hook = '') {
        if (isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === 'marrison-cookie') {
            return true;
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && false !== strpos((string) $screen->id, 'marrison-cookie')) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Ottieni step corrente
     */
    public function get_current_step() {
        // Recupera step salvato o default a 1
        $step = get_option('marrison_wizard_current_step', 1);
        return max(1, min($step, $this->total_steps));
    }

    /**
     * Ottieni numero totale step
     */
    public function get_total_steps() {
        return $this->total_steps;
    }
    
    /**
     * AJAX: Scansiona cookie
     */
    public function ajax_scan_cookies() {
        check_ajax_referer('marrison_wizard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        if (class_exists('Marrison_Addon_Cookie_Scan_Engine', false)) {
            wp_send_json_error(array('message' => 'Lo scanner è stato aggiornato. Ricarica Cookie Manager per usare la scansione completa.'));
        }
        
        $scanner = Marrison_Cookie_Scanner::get_instance();
        $scanner->perform_scan();
        $cookies = $scanner->get_cookies('all');
        $error = $scanner->get_last_scan_error();
        
        if ($scanner->was_last_scan_successful()) {
            wp_send_json_success(array(
                'message' => 'Scansione completata',
                'cookies' => $cookies
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Scansione non completata: ' . $error
            ));
        }
    }
    
    /**
     * AJAX: Salva step
     */
    public function ajax_save_step() {
        check_ajax_referer('marrison_wizard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $step = isset($_POST['step']) ? intval($_POST['step']) : 1;
        $data = isset($_POST['data']) && is_array($_POST['data']) ? wp_unslash($_POST['data']) : array();
        
        // Salva i dati temporanei
        update_option('marrison_wizard_step_' . $step, $this->sanitize_step_data($data));
        update_option('marrison_wizard_current_step', max(1, min($step, $this->total_steps)));
        
        // Se è lo step 2, salva le impostazioni del banner
        if ($step === 2) {
            $this->save_banner_settings($data);
        }
        
        // Se è lo step 3, salva le impostazioni delle categorie
        if ($step === 3) {
            $this->save_category_settings($data);
        }
        
        // Se è lo step 4, salva le impostazioni dell'aspetto
        if ($step === 4) {
            $this->save_appearance_settings($data);
        }
        if ($step === 5 && isset($data['privacy_profile']) && is_array($data['privacy_profile'])) {
            update_option('marrison_cookie_privacy_profile', Marrison_Addon_Cookie_Policy_Content::sanitize_profile($data['privacy_profile']));
        }
        
        wp_send_json_success(array('message' => 'Step salvato', 'next_step' => $step + 1));
    }
    
    /**
     * Salva impostazioni banner
     */
    private function save_banner_settings($data) {
        if (isset($data['banner_title'])) {
            update_option('marrison_cookie_banner_title', sanitize_text_field($data['banner_title']));
        }
        if (isset($data['banner_description'])) {
            update_option('marrison_cookie_banner_description', sanitize_textarea_field($data['banner_description']));
        }
        if (isset($data['accept_button_text'])) {
            update_option('marrison_cookie_accept_button_text', sanitize_text_field($data['accept_button_text']));
        }
        if (isset($data['reject_button_text'])) {
            update_option('marrison_cookie_reject_button_text', sanitize_text_field($data['reject_button_text']));
        }
        if (isset($data['customize_button_text'])) {
            update_option('marrison_cookie_customize_button_text', sanitize_text_field($data['customize_button_text']));
        }
    }

    /**
     * Ripulisce i dati temporanei del wizard prima di salvarli.
     */
    private function sanitize_step_data($data) {
        $sanitized = array();

        foreach ($data as $key => $value) {
            $clean_key = sanitize_key($key);

            if (is_array($value)) {
                $sanitized[$clean_key] = array();
                foreach ($value as $nested_key => $nested_value) {
                    $sanitized[$clean_key][sanitize_key($nested_key)] = sanitize_text_field($nested_value);
                }
            } else {
                $sanitized[$clean_key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }
    
    /**
     * Salva impostazioni categorie
     */
    private function save_category_settings($data) {
        if (isset($data['categories']) && is_array($data['categories'])) {
            $scanner = Marrison_Cookie_Scanner::get_instance();
            $valid_categories = array_keys($scanner->get_categories());

            foreach ($data['categories'] as $cookie_id => $category) {
                $category = sanitize_key($category);
                if ($category === 'uncategorized') {
                    $category = 'functional';
                }

                if (!in_array($category, $valid_categories, true)) {
                    continue;
                }

                $scanner->update_cookie_record_category($cookie_id, $category);
            }
        }
    }
    
    /**
     * Salva impostazioni aspetto
     */
    private function save_appearance_settings($data) {
        if (isset($data['banner_layout'])) {
            $banner_layout = sanitize_key($data['banner_layout']);
            update_option('marrison_cookie_banner_layout', in_array($banner_layout, array('bar', 'box'), true) ? $banner_layout : 'bar');
        }
        if (isset($data['banner_position'])) {
            $banner_position = sanitize_key($data['banner_position']);
            update_option('marrison_cookie_banner_position', in_array($banner_position, array('top', 'bottom'), true) ? $banner_position : 'bottom');
        }
        if (isset($data['box_position'])) {
            $box_position = sanitize_key($data['box_position']);
            update_option('marrison_cookie_box_position', in_array($box_position, array('top-left', 'top-right', 'bottom-left', 'bottom-right'), true) ? $box_position : 'bottom-right');
        }
        if (isset($data['banner_background_color'])) {
            update_option('marrison_cookie_banner_background_color', sanitize_hex_color($data['banner_background_color']));
        }
        if (isset($data['banner_text_color'])) {
            update_option('marrison_cookie_banner_text_color', sanitize_hex_color($data['banner_text_color']));
        }
        if (isset($data['button_background_color'])) {
            update_option('marrison_cookie_button_background_color', sanitize_hex_color($data['button_background_color']));
        }
        if (isset($data['button_text_color'])) {
            update_option('marrison_cookie_button_text_color', sanitize_hex_color($data['button_text_color']));
        }
    }
    
    /**
     * Compatibilità con vecchi wizard: restituisce gli shortcode senza creare pagine.
     */
    public function ajax_create_pages() {
        check_ajax_referer('marrison_wizard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        wp_send_json_error(array(
            'message' => marrison_cookie_site_text('La creazione delle pagine è stata sostituita dagli shortcode disponibili nelle impostazioni.', 'Page creation has been replaced by the shortcodes available in settings.'),
            'pages' => array(),
            'shortcodes' => array(
                'privacy' => '[marrison_privacy_policy]',
                'cookie' => '[marrison_cookie_policy_content]',
            ),
        ));
    }
    
    /**
     * Testo Privacy Policy inseribile anche nel widget Shortcode di Elementor.
     */
    public function render_privacy_policy_shortcode() {
        return '<div class="marrison-privacy-policy-content">' . $this->get_privacy_page_content() . '</div>';
    }
    
    /**
     * Testo Cookie Policy completo di elenco aggiornato e accesso alle preferenze.
     */
    public function render_cookie_policy_shortcode() {
        return '<div class="marrison-cookie-policy-content">' . $this->get_cookie_page_content() . Marrison_Cookie_Banner::get_instance()->render_policy_shortcode() . '</div>';
    }

    /**
     * Usa gli URL scelti nelle impostazioni, indipendentemente da titolo ed editor.
     */
    private function get_policy_page_url($page_title) {
        $options = array(
            'Privacy Policy' => 'marrison_cookie_privacy_policy_url',
            'Cookie Policy' => 'marrison_cookie_cookie_policy_url',
        );
        if (!isset($options[$page_title])) {
            return '';
        }

        return get_option($options[$page_title], '');
    }

    private function get_policy_page_link($page_title) {
        $url = $this->get_policy_page_url($page_title);
        return $url ? '<a href="' . esc_url($url) . '">' . esc_html($page_title) . '</a>' : esc_html($page_title);
    }

    private function save_policy_settings($data) {
        foreach (array('privacy_policy_url', 'cookie_policy_url') as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                update_option('marrison_cookie_' . $key, esc_url_raw($data[$key]));
            }
        }
    }
    
    /**
     * Ottieni contenuto pagina privacy
     */
    private function get_privacy_page_content() {
        return Marrison_Addon_Cookie_Policy_Content::content('privacy');
    }

    /**
     * Ottieni contenuto pagina cookie
     */
    private function get_cookie_page_content() {
        return Marrison_Addon_Cookie_Policy_Content::content('cookie');
    }

    /**
     * AJAX: Completa wizard
     */
    public function ajax_finish() {
        check_ajax_referer('marrison_wizard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $data = isset($_POST['data']) && is_array($_POST['data']) ? wp_unslash($_POST['data']) : array();
        $this->save_policy_settings($data);

        // Segna wizard come completato
        update_option('marrison_cookie_wizard_completed', true);
        update_option('marrison_cookie_wizard_completed_date', current_time('mysql'));
        
        // Rimuovi flag di apertura per evitare riapertura
        delete_option('marrison_cookie_wizard_should_open');
        
        // Attiva il banner
        update_option('marrison_cookie_show_banner', true);
        
        wp_send_json_success(array(
            'message' => 'Wizard completato',
            'redirect_url' => admin_url('admin.php?page=marrison-cookie')
        ));
    }
    
    /**
     * AJAX: Dismiss wizard
     */
    public function ajax_dismiss() {
        check_ajax_referer('marrison_wizard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        // Rimuovi flag di apertura
        delete_option('marrison_cookie_wizard_should_open');
        
        wp_send_json_success(array('message' => 'Wizard chiuso'));
    }
    
    /**
     * AJAX: Open wizard
     */
    public function ajax_open() {
        // Accetta entrambi i nonce per compatibilità, ma richiede sempre un nonce valido.
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$nonce && isset($_POST['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        }

        if (!$nonce || (
            !wp_verify_nonce($nonce, 'marrison_admin_nonce') &&
            !wp_verify_nonce($nonce, 'marrison_wizard_nonce') &&
            !wp_verify_nonce($nonce, 'marrison_cookie_nonce')
        )) {
            wp_send_json_error(array('message' => 'Nonce non valido'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        // Imposta flag per aprire il wizard
        update_option('marrison_cookie_wizard_should_open', true);
        
        // Reset step corrente a 1
        update_option('marrison_wizard_current_step', 1);
        
        // Se il wizard era già completato, resetta per permettere riesecuzione
        if (get_option('marrison_cookie_wizard_completed', false)) {
            delete_option('marrison_cookie_wizard_completed');
            delete_option('marrison_cookie_wizard_completed_date');
            
            for ($i = 1; $i <= $this->total_steps; $i++) {
                delete_option('marrison_wizard_step_' . $i);
            }
        }
        
        wp_send_json_success(array('message' => 'Wizard aperto'));
    }
    
    /**
     * Reset wizard per riesecuzione
     */
    public function reset_wizard() {
        delete_option('marrison_cookie_wizard_completed');
        delete_option('marrison_cookie_wizard_completed_date');
        delete_option('marrison_cookie_wizard_should_open');
        
        for ($i = 1; $i <= $this->total_steps; $i++) {
            delete_option('marrison_wizard_step_' . $i);
        }
    }
    
    /**
     * Verifica se il wizard è completato
     */
    public function is_wizard_completed() {
        return get_option('marrison_cookie_wizard_completed', false);
    }
}
