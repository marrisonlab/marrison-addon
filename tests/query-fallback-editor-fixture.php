<?php
/** Capture actual module control definitions and native Elementor control templates. */
namespace Elementor {
    class Controls_Manager {
        const SWITCHER = 'switcher', SELECT2 = 'select2', CHOOSE = 'choose', RAW_HTML = 'raw_html';
    }
    class Base_Data_Control {
        public function print_control_uid($suffix = '') { echo 'fixture-{{data.name}}' . ($suffix ? '-' . $suffix : ''); }
    }
    class Base_UI_Control extends Base_Data_Control {}
}
namespace Jet_Engine\Query_Builder {
    class Manager {
        public static function instance() { return new self(); }
        public function get_queries_for_options($blocks = false, $type = null) { return ['' => 'Select query...', 1 => 'Prodotti correlati', 2 => 'Ultimi prodotti', 3 => 'Prodotti in offerta']; }
        public function get_query_by_id_for_context($id, $context) { return false; }
    }
}
namespace {
    define('ABSPATH', __DIR__ . '/');
    function __($value, $domain = '') { return $value; }
    function add_action() {}
    function add_filter() {}
    function wp_enqueue_script() {}
    function wp_enqueue_style() {}
    function plugins_url($path, $file) { return $path; }
    function wp_localize_script($handle, $name, $data) { $GLOBALS['editor_config'] = $data; }
    class Marrison_Addon {
        public static function plugin_file() { return dirname(__DIR__) . '/marrison-addon/marrison-addon.php'; }
        public static function asset_version($path) { return 'fixture'; }
    }
    $elementor = rtrim($argv[1] ?? '', '/\\');
    require dirname(__DIR__) . '/marrison-addon/includes/modules/class-marrison-addon-query-fallback.php';
    $module = new Marrison_Addon_Query_Fallback();
    $widget = new class { public $controls = []; public function add_control($name, $args) { $this->controls[$name] = $args; } };
    $module->register_controls($widget);
    $module->enqueue_editor();
    $templates = [];
    foreach (['choose' => 'Control_Choose', 'select2' => 'Control_Select2', 'raw-html' => 'Control_Raw_Html'] as $file => $class) {
        require $elementor . '/includes/controls/' . $file . '.php';
        $class = 'Elementor\\' . $class;
        ob_start(); (new $class())->content_template(); $templates[$file] = ob_get_clean();
    }
    echo json_encode(['controls' => $widget->controls, 'config' => $GLOBALS['editor_config'], 'templates' => $templates]);
}
