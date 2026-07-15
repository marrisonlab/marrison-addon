<?php
/**
 * Classe per il wizard di configurazione iniziale
 */
class Marrison_Setup_Wizard {
    
    private static $instance = null;
    private $wizard_step = 1;
    private $total_steps = 5;
    
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
            'creatingPagesText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Creazione pagine in corso...', 'Creating pages...') : __('Creazione pagine in corso...', 'marrison-cookie'),
            'pagesCreatedText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Pagine create con successo!', 'Pages created successfully!') : __('Pagine create con successo!', 'marrison-cookie'),
            'pagesErrorText' => function_exists('marrison_cookie_site_text') ? marrison_cookie_site_text('Errore durante la creazione delle pagine', 'Error while creating pages') : __('Errore durante la creazione delle pagine', 'marrison-cookie'),
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
        
        $scanner = Marrison_Cookie_Scanner::get_instance();
        $result = $scanner->perform_scan();
        
        if ($result) {
            $cookies = $scanner->get_cookies('all');
            wp_send_json_success(array(
                'message' => 'Scansione completata',
                'cookies' => $cookies
            ));
        } else {
            wp_send_json_error(array('message' => 'Errore durante la scansione'));
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

                global $wpdb;
                $table_name = $wpdb->prefix . 'marrison_cookies';
                
                $wpdb->update(
                    $table_name,
                    array('cookie_category' => sanitize_text_field($category)),
                    array('id' => intval($cookie_id)),
                    array('%s'),
                    array('%d')
                );
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
     * AJAX: Crea pagine
     */
    public function ajax_create_pages() {
        check_ajax_referer('marrison_wizard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $create_privacy = isset($_POST['create_privacy']) && $_POST['create_privacy'] === 'true';
        $create_cookie = isset($_POST['create_cookie']) && $_POST['create_cookie'] === 'true';
        
        $pages = array();
        
        if ($create_privacy) {
            $privacy_page_id = $this->create_privacy_page();
            if ($privacy_page_id) {
                $pages['privacy'] = array(
                    'id' => $privacy_page_id,
                    'url' => get_permalink($privacy_page_id)
                );
                update_option('marrison_cookie_privacy_policy_url', get_permalink($privacy_page_id));
            }
        }
        
        if ($create_cookie) {
            $cookie_page_id = $this->create_cookie_page();
            if ($cookie_page_id) {
                $pages['cookie'] = array(
                    'id' => $cookie_page_id,
                    'url' => get_permalink($cookie_page_id)
                );
                update_option('marrison_cookie_cookie_policy_url', get_permalink($cookie_page_id));
            }
        }
        
        wp_send_json_success(array('message' => 'Pagine create', 'pages' => $pages));
    }
    
    /**
     * Crea pagina privacy
     */
    private function create_privacy_page() {
        $page_title = 'Privacy Policy';
        $page_content = $this->get_privacy_page_content();
        
        // Verifica se esiste già
        $existing_page = get_page_by_title($page_title);
        
        if ($existing_page) {
            return $existing_page->ID;
        }
        
        $page_data = array(
            'post_title' => $page_title,
            'post_content' => $page_content,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
        );
        
        $page_id = wp_insert_post($page_data);
        
        return $page_id;
    }
    
    /**
     * Crea pagina cookie
     */
    private function create_cookie_page() {
        $page_title = 'Cookie Policy';
        $page_content = $this->get_cookie_page_content();
        
        // Verifica se esiste già
        $existing_page = get_page_by_title($page_title);
        
        if ($existing_page) {
            return $existing_page->ID;
        }
        
        $page_data = array(
            'post_title' => $page_title,
            'post_content' => $page_content,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
        );
        
        $page_id = wp_insert_post($page_data);
        
        return $page_id;
    }

    /**
     * Ottieni permalink pagina per titolo, se esiste.
     */
    private function get_policy_page_url($page_title) {
        $page = get_page_by_title($page_title);

        if (!$page) {
            return '';
        }

        return get_permalink($page->ID);
    }
    
    /**
     * Ottieni contenuto pagina privacy
     */
    private function get_privacy_page_content() {
        $site_name = get_bloginfo('name');
        $site_url = home_url();

        if (function_exists('marrison_cookie_is_english_site') && marrison_cookie_is_english_site()) {
            return '<h1>Privacy Policy</h1>'
                . '<p>Last updated: ' . date('d/m/Y') . '</p>'
                . '<h2>1. Introduction</h2>'
                . '<p>This Privacy Policy explains how ' . esc_html($site_name) . ' ("we", "us", "our") collects, uses, and protects personal information when you visit our website ' . esc_url($site_url) . '.</p>'
                . '<h2>2. Information we collect</h2>'
                . '<p>We may collect personal information that you voluntarily provide, including name, email address, phone number, and other information you choose to submit.</p>'
                . '<h2>3. Use of cookies</h2>'
                . '<p>We use cookies to improve your browsing experience. For more details, see our <a href="' . esc_url($this->get_policy_page_url('Cookie Policy')) . '">Cookie Policy</a>.</p>'
                . '<h2>4. Sharing of information</h2>'
                . '<p>We do not share your personal information with third parties except when required by law or necessary to provide our services.</p>'
                . '<h2>5. Your rights</h2>'
                . '<p>You have the right to access, correct, delete, or object to the processing of your personal information.</p>'
                . '<h2>6. Contact</h2>'
                . '<p>If you have any questions about this Privacy Policy, contact us by email.</p>';
        }
        
        $content = '<h1>Privacy Policy</h1>';
        $content .= '<p>Ultimo aggiornamento: ' . date('d/m/Y') . '</p>';
        $content .= '<h2>1. Introduzione</h2>';
        $content .= '<p>Questa Privacy Policy spiega come ' . esc_html($site_name) . ' ("noi", "ci", "nostro") raccoglie, utilizza e protegge le informazioni personali quando visiti il nostro sito web ' . esc_url($site_url) . '.</p>';
        $content .= '<h2>2. Informazioni che raccogliamo</h2>';
        $content .= '<h3>2.1 Dati personali</h3>';
        $content .= '<p>Possiamo raccogliere informazioni personali che ci fornisci volontariamente, tra cui:</p>';
        $content .= '<ul>';
        $content .= '<li>Nome e cognome</li>';
        $content .= '<li>Indirizzo email</li>';
        $content .= '<li>Numero di telefono</li>';
        $content .= '<li>Altre informazioni che decidi di fornire</li>';
        $content .= '</ul>';
        $content .= '<h3>2.2 Dati tecnici</h3>';
        $content .= '<p>Raccogliamo automaticamente informazioni tecniche, tra cui:</p>';
        $content .= '<ul>';
        $content .= '<li>Indirizzo IP</li>';
        $content .= '<li>Tipo di browser e versione</li>';
        $content .= '<li>Sistema operativo</li>';
        $content .= '<li>Siti web di riferimento</li>';
        $content .= '<li>Tempo di visita e pagine visualizzate</li>';
        $content .= '</ul>';
        $content .= '<h2>3. Utilizzo dei cookie</h2>';
        $cookie_policy_url = $this->get_policy_page_url('Cookie Policy');
        $content .= '<p>Utilizziamo i cookie per migliorare la tua esperienza di navigazione. Per maggiori dettagli, consulta la nostra <a href="' . esc_url($cookie_policy_url) . '">Cookie Policy</a>.</p>';
        $content .= '<h2>4. Condivisione delle informazioni</h2>';
        $content .= '<p>Non condividiamo le tue informazioni personali con terze parti, tranne quando richiesto dalla legge o necessario per fornire i nostri servizi.</p>';
        $content .= '<h2>5. I tuoi diritti</h2>';
        $content .= '<p>Hai il diritto di:</p>';
        $content .= '<ul>';
        $content .= '<li>Accedere alle tue informazioni personali</li>';
        $content .= '<li>Richiedere la correzione delle tue informazioni</li>';
        $content .= '<li>Richiedere la cancellazione delle tue informazioni</li>';
        $content .= '<li>Opporre al trattamento delle tue informazioni</li>';
        $content .= '</ul>';
        $content .= '<h2>6. Contatti</h2>';
        $content .= '<p>Per qualsiasi domanda relativa a questa Privacy Policy, contattaci via email.</p>';
        
        return $content;
    }
    
    /**
     * Ottieni contenuto pagina cookie
     */
    private function get_cookie_page_content() {
        $site_name = get_bloginfo('name');
        $site_url = home_url();

        if (function_exists('marrison_cookie_is_english_site') && marrison_cookie_is_english_site()) {
            return '<h1>Cookie Policy</h1>'
                . '<p>Last updated: ' . date('d/m/Y') . '</p>'
                . '<h2>1. What cookies are</h2>'
                . '<p>Cookies are small text files stored on your device when you visit our website. They are used to remember your preferences and improve your browsing experience.</p>'
                . '<h2>2. Types of cookies we use</h2>'
                . '<h3>2.1 Necessary cookies</h3>'
                . '<p>These cookies are essential for the website to function. They cannot be disabled.</p>'
                . '<h3>2.2 Functional cookies</h3>'
                . '<p>These cookies improve website functionality by remembering your preferences.</p>'
                . '<h3>2.3 Analytics cookies</h3>'
                . '<p>These cookies help us understand how visitors use the website by collecting anonymous information.</p>'
                . '<h3>2.4 Marketing cookies</h3>'
                . '<p>These cookies are used to show you relevant advertising based on your interests.</p>'
                . '<h2>3. Third-party cookies</h2>'
                . '<p>Our website may use third-party cookies for services such as Google Analytics and Facebook Pixel.</p>'
                . '<h2>4. Cookie management</h2>'
                . '<p>You can manage your cookie preferences using the banner that appears when you visit our website, or change your browser settings to block or delete cookies.</p>'
                . '<h2>5. Useful links</h2>'
                . '<ul>'
                . '<li><a href="' . esc_url($this->get_policy_page_url('Privacy Policy')) . '">Privacy Policy</a></li>'
                . '<li><a href="https://www.allaboutcookies.org/" target="_blank" rel="noopener">All About Cookies</a></li>'
                . '</ul>'
                . '<h2>6. Contact</h2>'
                . '<p>If you have any questions about this Cookie Policy, contact us by email.</p>';
        }
        
        $content = '<h1>Cookie Policy</h1>';
        $content .= '<p>Ultimo aggiornamento: ' . date('d/m/Y') . '</p>';
        $content .= '<h2>1. Cosa sono i cookie</h2>';
        $content .= '<p>I cookie sono piccoli file di testo che vengono salvati sul tuo dispositivo quando visiti il nostro sito web. Vengono utilizzati per ricordare le tue preferenze e migliorare la tua esperienza di navigazione.</p>';
        $content .= '<h2>2. Tipi di cookie che utilizziamo</h2>';
        $content .= '<h3>2.1 Cookie necessari</h3>';
        $content .= '<p>Questi cookie sono essenziali per il funzionamento del sito web. Non possono essere disabilitati.</p>';
        $content .= '<h3>2.2 Cookie funzionali</h3>';
        $content .= '<p>Questi cookie migliorano le funzionalità del sito web, ricordando le tue preferenze.</p>';
        $content .= '<h3>2.3 Cookie analitici</h3>';
        $content .= '<p>Questi cookie ci aiutano a capire come i visitatori utilizzano il sito web, raccogliendo informazioni anonime.</p>';
        $content .= '<h3>2.4 Cookie di marketing</h3>';
        $content .= '<p>Questi cookie vengono utilizzati per mostrarti pubblicità pertinente in base ai tuoi interessi.</p>';
        $content .= '<h2>3. Cookie di terze parti</h2>';
        $content .= '<p>Il nostro sito web può utilizzare cookie di terze parti per servizi come Google Analytics, Facebook Pixel, ecc.</p>';
        $content .= '<h2>4. Gestione dei cookie</h2>';
        $content .= '<p>Puoi gestire le tue preferenze sui cookie utilizzando il banner che appare quando visiti il nostro sito. Puoi anche modificare le impostazioni del tuo browser per bloccare o eliminare i cookie.</p>';
        $content .= '<h2>5. Link utili</h2>';
        $content .= '<ul>';
        $privacy_policy_url = $this->get_policy_page_url('Privacy Policy');
        $content .= '<li><a href="' . esc_url($privacy_policy_url) . '">Privacy Policy</a></li>';
        $content .= '<li><a href="https://www.allaboutcookies.org/" target="_blank" rel="noopener">All About Cookies</a></li>';
        $content .= '</ul>';
        $content .= '<h2>6. Contatti</h2>';
        $content .= '<p>Per qualsiasi domanda relativa a questa Cookie Policy, contattaci via email.</p>';
        
        return $content;
    }
    
    /**
     * AJAX: Completa wizard
     */
    public function ajax_finish() {
        check_ajax_referer('marrison_wizard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
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
