<?php
/** php tests/module-dependencies-php-test.php /path/to/wordpress [none|elementor|woocommerce|jetengine|all] */
namespace { error_reporting(E_ALL); }
namespace {
define('ABSPATH', rtrim($argv[1] ?? '', '/\\') . '/');
if (!is_file(ABSPATH . 'wp-includes/plugin.php')) { fwrite(STDERR, "Provide WordPress root.\n"); exit(1); }
define('WP_PLUGIN_DIR', dirname(__DIR__)); define('WPMU_PLUGIN_DIR', dirname(__DIR__) . '/mu-plugins');
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/class-wp-error.php';
$mode = $argv[2] ?? 'none';
if (in_array($mode, ['elementor','all'], true)) { eval('namespace Elementor; class Plugin {}'); do_action('elementor/loaded'); }
if (in_array($mode, ['woocommerce','all'], true)) eval('class WooCommerce {}');
if (in_array($mode, ['jetengine','all'], true)) eval('class Jet_Engine {}');
$options = []; $wp_plugin_paths = [];
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return esc_html($text); }
function esc_attr__( $text, $domain = '' ) { return esc_attr($text); }
function esc_html( $value ) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function esc_attr( $value ) { return esc_html($value); }
function esc_url( $value ) { return esc_attr($value); }
function admin_url( $path = '' ) { return '/wp-admin/' . $path; }
function current_user_can( $cap ) { return true; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function is_admin() { return false; }
function wp_normalize_path( $path ) { return str_replace('\\', '/', $path); }
function trailingslashit( $path ) { return rtrim($path, '/\\') . '/'; }
function sanitize_key( $value ) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function wp_doing_cron() { return false; }
function remove_accents( $value ) { return $value; }
function wp_strip_all_tags( $value ) { return strip_tags($value); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function add_settings_error() {}
require dirname(__DIR__) . '/marrison-addon/marrison-addon.php';
new Marrison_Addon();
$checks = 0;
function check( $condition, $message ) { $GLOBALS['checks']++; if (!$condition) throw new RuntimeException($message); }
$definitions = Marrison_Addon::get_module_definitions(false);
check(!class_exists('Marrison_Addon_Query_Fallback', false), 'Disabled module file loaded');
foreach ($definitions as $id => $definition) {
    $expected = (empty($definition['requires_elementor']) || in_array($mode, ['elementor','all'], true))
        && (empty($definition['requires_woocommerce']) || in_array($mode, ['woocommerce','all'], true))
        && (empty($definition['requires_jet_engine']) || in_array($mode, ['jetengine','all'], true));
    check(Marrison_Addon::module_dependencies_available($definition) === $expected, "$id runtime dependency mismatch");
    $options['marrison_addon_modules'] = [$id => 1]; Marrison_Addon::clear_runtime_caches();
    check(Marrison_Addon::is_module_enabled($id) === $expected, "$id stale activation ignored dependencies");
    $updated = Marrison_Addon::prepare_module_update([$id => 1], []);
    check($expected ? $updated === [$id => 1] : ($updated === [] && is_wp_error(Marrison_Addon::get_module_update_error())), "$id direct activation bypassed dependencies");
}
$options['marrison_addon_modules'] = array_fill_keys(array_keys($definitions), 1);
Marrison_Addon::clear_runtime_caches();
$admin = (new ReflectionClass('Marrison_Addon_Admin'))->newInstanceWithoutConstructor();
ob_start(); $admin->dashboard_page_html(); $html = ob_get_clean();
foreach ($definitions as $id => $definition) {
    preg_match('/<div class="marrison-module-card" data-module="' . $id . '".*?(?=<div class="marrison-module-card"|$)/s', $html, $match);
    $card = $match[0] ?? '';
    check((bool)$card, "$id card absent");
    foreach (Marrison_Addon::module_required_plugins($definition) as $name) check(strpos($card, 'marrison-module-dependency') !== false && strpos($card, $name) !== false && strpos($card, 'Necessita di ') === false, "$id requirements absent");
    if (!Marrison_Addon::module_dependencies_available($definition)) {
        preg_match('/<input type="checkbox".*?>/s', $card, $input);
        check(strpos($input[0] ?? '', 'disabled') !== false && strpos($input[0] ?? '', 'checked') === false, "$id looks enabled while unavailable");
    }
}
echo in_array('--fixture', $argv, true) ? $html : "PASS $checks module dependency/dashboard checks ($mode).\n";
}
