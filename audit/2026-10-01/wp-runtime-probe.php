<?php
/** Local audit harness; does not modify plugin source or persist test settings. */
$mode = $argv[1] ?? 'inventory';
$module_id = $argv[2] ?? '';
$root = 'C:/Users/Angelo/Local Sites/tesy/app/public/';
define('DISABLE_WP_CRON', true);
$_SERVER['HTTP_HOST'] = 'localhost:10004';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '10004';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$GLOBALS['audit_errors'] = [];
set_error_handler(function ($level, $message, $file, $line) {
    if (strpos(str_replace('\\', '/', $file), '/marrison-addon/') !== false) {
        $GLOBALS['audit_errors'][] = compact('level', 'message', 'file', 'line');
    }
    return false;
});
function audit_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['wp_filter'][$tag][$priority][] = ['function' => $callback, 'accepted_args' => $accepted_args];
}
audit_filter('option_active_plugins', function ($plugins) use ($mode) {
    global $wpdb;
    if (empty($GLOBALS['audit_transaction'])) {
        $wpdb->query('START TRANSACTION');
        $GLOBALS['audit_transaction'] = true;
    }
    $allowed = [
        'elementor/elementor.php', 'jet-engine/jet-engine.php',
        'woocommerce/woocommerce.php', 'marrison-addon/marrison-addon.php',
    ];
    $remove = ['no-elementor'=>'elementor/elementor.php', 'no-jetengine'=>'jet-engine/jet-engine.php', 'no-woocommerce'=>'woocommerce/woocommerce.php'];
    if (isset($remove[$mode])) { $allowed = array_diff($allowed, [$remove[$mode]]); }
    return array_values(array_intersect($plugins, $allowed));
});
if ($mode === 'isolation' || $mode === 'off') {
    audit_filter('pre_option_marrison_addon_modules', function () use ($module_id, $mode) {
        return $mode === 'off' ? [] : [$module_id => 1];
    });
}
audit_filter('pre_http_request', function ($pre, $args, $url) {
    return new WP_Error('audit_network_blocked', 'External HTTP disabled in isolated audit harness.');
}, 10, 3);
register_shutdown_function(function () {
    if (empty($GLOBALS['audit_final_rollback_registered']) && !empty($GLOBALS['audit_transaction']) && isset($GLOBALS['wpdb'])) {
        $GLOBALS['wpdb']->query('ROLLBACK');
    }
});
require $root . 'wp-load.php';
$GLOBALS['audit_final_rollback_registered'] = true;
register_shutdown_function(function () {
    if (!empty($GLOBALS['audit_transaction']) && isset($GLOBALS['wpdb'])) {
        $GLOBALS['wpdb']->query('ROLLBACK');
    }
});
$definitions = Marrison_Addon::get_module_definitions(false);
$modules = [];
foreach ($definitions as $id => $definition) {
    $modules[$id] = [
        'title' => $definition['title'],
        'enabled' => Marrison_Addon::is_module_enabled($id),
        'loaded' => class_exists($definition['class'], false),
        'dependencies' => Marrison_Addon::module_dependencies_available($definition),
    ];
}
$hooks = [];
foreach ($GLOBALS['wp_filter'] as $tag => $hook) {
    foreach ($hook->callbacks ?? [] as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            $fn = $callback['function'];
            if (is_array($fn) && is_object($fn[0]) && strpos(get_class($fn[0]), 'Marrison') === 0) {
                $hooks[] = ['tag' => $tag, 'class' => get_class($fn[0]), 'method' => $fn[1], 'priority' => $priority];
            }
        }
    }
}
$result = [
    'mode' => $mode, 'module' => $module_id,
    'versions' => ['php' => PHP_VERSION, 'wordpress' => $wp_version, 'addon' => Marrison_Addon::VERSION,
        'elementor' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : null,
        'jetengine' => defined('JET_ENGINE_VERSION') ? JET_ENGINE_VERSION : null,
        'woocommerce' => defined('WC_VERSION') ? WC_VERSION : null],
    'modules' => $modules, 'hooks' => $hooks,
    'public_frontend' => Marrison_Addon_Context::is_public_frontend_request(),
    'errors' => $GLOBALS['audit_errors'],
];
if ($mode === 'inventory') {
    $result['posts'] = get_posts(['post_type' => ['page','product','jet-engine'], 'post_status' => ['publish','draft'], 'numberposts' => 30, 'fields' => 'ids']);
    $result['image_support'] = ['gd' => extension_loaded('gd'), 'imagick' => extension_loaded('imagick'),
        'webp' => wp_image_editor_supports(['mime_type'=>'image/webp']), 'avif' => wp_image_editor_supports(['mime_type'=>'image/avif'])];
    $result['widgets'] = array_keys(\Elementor\Plugin::$instance->widgets_manager->get_widget_types());
    $result['shortcodes'] = array_values(array_filter(array_keys($GLOBALS['shortcode_tags']), function($tag) { return strpos($tag, 'marrison') !== false; }));
    $result['errors'] = $GLOBALS['audit_errors'];
}
echo "\nAUDIT_JSON\n" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
