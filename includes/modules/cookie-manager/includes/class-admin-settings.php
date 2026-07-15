<?php
/**
 * Classe per le impostazioni di amministrazione
 */
class Marrison_Cookie_Admin_Settings {
    
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
        // Aggiungi menu admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Registra impostazioni
        add_action('admin_init', array($this, 'register_settings'));
        
        // Salva impostazioni
        add_action('wp_ajax_marrison_save_settings', array($this, 'ajax_save_settings'));
    }
    
    /**
     * Aggiungi menu admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'marrison_addon_panel',
            'Marrison Cookie Manager',
            'Cookie Manager',
            'manage_options',
            'marrison-cookie',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Registra impostazioni
     */
    public function register_settings() {
        // Banner settings
        register_setting('marrison_cookie_settings', 'marrison_cookie_banner_title');
        register_setting('marrison_cookie_settings', 'marrison_cookie_banner_description');
        register_setting('marrison_cookie_settings', 'marrison_cookie_accept_button_text');
        register_setting('marrison_cookie_settings', 'marrison_cookie_reject_button_text');
        register_setting('marrison_cookie_settings', 'marrison_cookie_customize_button_text');
        register_setting('marrison_cookie_settings', 'marrison_cookie_privacy_policy_url');
        register_setting('marrison_cookie_settings', 'marrison_cookie_cookie_policy_url');
        
        // Appearance settings
        register_setting('marrison_cookie_settings', 'marrison_cookie_banner_layout');
        register_setting('marrison_cookie_settings', 'marrison_cookie_banner_position');
        register_setting('marrison_cookie_settings', 'marrison_cookie_box_position');
        register_setting('marrison_cookie_settings', 'marrison_cookie_banner_background_color');
        register_setting('marrison_cookie_settings', 'marrison_cookie_banner_text_color');
        register_setting('marrison_cookie_settings', 'marrison_cookie_button_background_color');
        register_setting('marrison_cookie_settings', 'marrison_cookie_button_text_color');
        
        // Behavior settings
        register_setting('marrison_cookie_settings', 'marrison_cookie_show_banner');
        register_setting('marrison_cookie_settings', 'marrison_cookie_consent_duration');
        register_setting('marrison_cookie_settings', 'marrison_cookie_auto_scan');
        register_setting('marrison_cookie_settings', 'marrison_cookie_scan_interval');
    }
    
    /**
     * Renderizza pagina impostazioni
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Salva impostazioni se submitted
        if (isset($_POST['marrison_save_settings']) && check_admin_referer('marrison_cookie_settings_nonce')) {
            $this->save_form_settings();
        }
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'banner';
        
        include MARRISON_COOKIE_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Salva impostazioni dal form
     */
    private function save_form_settings() {
        // Banner settings
        if (isset($_POST['banner_title'])) {
            update_option('marrison_cookie_banner_title', sanitize_text_field($_POST['banner_title']));
        }
        if (isset($_POST['banner_description'])) {
            update_option('marrison_cookie_banner_description', sanitize_textarea_field($_POST['banner_description']));
        }
        if (isset($_POST['accept_button_text'])) {
            update_option('marrison_cookie_accept_button_text', sanitize_text_field($_POST['accept_button_text']));
        }
        if (isset($_POST['reject_button_text'])) {
            update_option('marrison_cookie_reject_button_text', sanitize_text_field($_POST['reject_button_text']));
        }
        if (isset($_POST['customize_button_text'])) {
            update_option('marrison_cookie_customize_button_text', sanitize_text_field($_POST['customize_button_text']));
        }
        if (isset($_POST['privacy_policy_url'])) {
            update_option('marrison_cookie_privacy_policy_url', esc_url_raw($_POST['privacy_policy_url']));
        }
        if (isset($_POST['cookie_policy_url'])) {
            update_option('marrison_cookie_cookie_policy_url', esc_url_raw($_POST['cookie_policy_url']));
        }
        
        // Appearance settings
        if (isset($_POST['banner_layout'])) {
            $banner_layout = sanitize_key(wp_unslash($_POST['banner_layout']));
            update_option('marrison_cookie_banner_layout', in_array($banner_layout, array('bar', 'box'), true) ? $banner_layout : 'bar');
        }
        if (isset($_POST['banner_position'])) {
            $banner_position = sanitize_key(wp_unslash($_POST['banner_position']));
            update_option('marrison_cookie_banner_position', in_array($banner_position, array('top', 'bottom'), true) ? $banner_position : 'bottom');
        }
        if (isset($_POST['box_position'])) {
            $box_position = sanitize_key(wp_unslash($_POST['box_position']));
            update_option('marrison_cookie_box_position', in_array($box_position, array('top-left', 'top-right', 'bottom-left', 'bottom-right'), true) ? $box_position : 'bottom-right');
        }
        if (isset($_POST['banner_background_color'])) {
            update_option('marrison_cookie_banner_background_color', sanitize_hex_color($_POST['banner_background_color']));
        }
        if (isset($_POST['banner_text_color'])) {
            update_option('marrison_cookie_banner_text_color', sanitize_hex_color($_POST['banner_text_color']));
        }
        if (isset($_POST['button_background_color'])) {
            update_option('marrison_cookie_button_background_color', sanitize_hex_color($_POST['button_background_color']));
        }
        if (isset($_POST['button_text_color'])) {
            update_option('marrison_cookie_button_text_color', sanitize_hex_color($_POST['button_text_color']));
        }
        
        // Behavior settings
        update_option('marrison_cookie_show_banner', isset($_POST['show_banner']));

        if (isset($_POST['consent_duration'])) {
            update_option('marrison_cookie_consent_duration', max(1, min(365, intval($_POST['consent_duration']))));
        }

        update_option('marrison_cookie_auto_scan', isset($_POST['auto_scan']));

        if (isset($_POST['scan_interval'])) {
            update_option('marrison_cookie_scan_interval', max(1, min(30, intval($_POST['scan_interval']))));
        }
        
        echo '<div class="notice notice-success"><p>Impostazioni salvate con successo!</p></div>';
    }
    
    /**
     * AJAX: Salva impostazioni
     */
    public function ajax_save_settings() {
        check_ajax_referer('marrison_cookie_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        $settings = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : array();
        $allowed_settings = array(
            'banner_title',
            'banner_description',
            'accept_button_text',
            'reject_button_text',
            'customize_button_text',
            'privacy_policy_url',
            'cookie_policy_url',
            'banner_layout',
            'banner_position',
            'box_position',
            'banner_background_color',
            'banner_text_color',
            'button_background_color',
            'button_text_color',
            'show_banner',
            'consent_duration',
            'auto_scan',
            'scan_interval',
        );
        
        foreach ($settings as $key => $value) {
            $key = sanitize_key($key);

            if (!in_array($key, $allowed_settings, true)) {
                continue;
            }

            $option_key = 'marrison_cookie_' . $key;
            
            if (in_array($key, array('show_banner', 'auto_scan'), true)) {
                update_option($option_key, (bool) $value);
            } elseif (in_array($key, array('consent_duration', 'scan_interval'), true)) {
                $max = $key === 'consent_duration' ? 365 : 30;
                update_option($option_key, max(1, min($max, intval($value))));
            } elseif (in_array($key, array('privacy_policy_url', 'cookie_policy_url'), true)) {
                update_option($option_key, esc_url_raw($value));
            } elseif ($key === 'banner_layout') {
                $value = sanitize_key($value);
                update_option($option_key, in_array($value, array('bar', 'box'), true) ? $value : 'bar');
            } elseif ($key === 'banner_position') {
                $value = sanitize_key($value);
                update_option($option_key, in_array($value, array('top', 'bottom'), true) ? $value : 'bottom');
            } elseif ($key === 'box_position') {
                $value = sanitize_key($value);
                update_option($option_key, in_array($value, array('top-left', 'top-right', 'bottom-left', 'bottom-right'), true) ? $value : 'bottom-right');
            } elseif (strpos($key, '_color') !== false) {
                update_option($option_key, sanitize_hex_color($value));
            } else {
                update_option($option_key, sanitize_text_field($value));
            }
        }
        
        wp_send_json_success(array('message' => 'Impostazioni salvate con successo'));
    }
    
    /**
     * Ottieni impostazioni correnti
     */
    public function get_settings() {
        return array(
            'banner_title' => get_option('marrison_cookie_banner_title', 'Gestione Cookie'),
            'banner_description' => get_option('marrison_cookie_banner_description', 'Utilizziamo i cookie per migliorare la tua esperienza.'),
            'accept_button_text' => get_option('marrison_cookie_accept_button_text', 'Accetta tutti'),
            'reject_button_text' => get_option('marrison_cookie_reject_button_text', 'Rifiuta tutti'),
            'customize_button_text' => get_option('marrison_cookie_customize_button_text', 'Personalizza'),
            'privacy_policy_url' => get_option('marrison_cookie_privacy_policy_url', ''),
            'cookie_policy_url' => get_option('marrison_cookie_cookie_policy_url', ''),
            'banner_layout' => get_option('marrison_cookie_banner_layout', 'bar'),
            'banner_position' => get_option('marrison_cookie_banner_position', 'bottom'),
            'box_position' => get_option('marrison_cookie_box_position', 'bottom-right'),
            'banner_background_color' => get_option('marrison_cookie_banner_background_color', '#ffffff'),
            'banner_text_color' => get_option('marrison_cookie_banner_text_color', '#333333'),
            'button_background_color' => get_option('marrison_cookie_button_background_color', '#0073aa'),
            'button_text_color' => get_option('marrison_cookie_button_text_color', '#ffffff'),
            'show_banner' => get_option('marrison_cookie_show_banner', true),
            'consent_duration' => get_option('marrison_cookie_consent_duration', 30),
            'auto_scan' => get_option('marrison_cookie_auto_scan', true),
            'scan_interval' => get_option('marrison_cookie_scan_interval', 7),
        );
    }
}
