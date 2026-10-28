<?php
/** Isolated bootstrap and request-scope audit, no WordPress database required. */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
$hooks = []; $options = [ 'marrison_addon_modules'=>[] ]; $option_reads = []; $assets = []; $admin_context = false;
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { add_action( $hook, $callback ); }
function register_deactivation_hook( $file, $callback ) { $GLOBALS['deactivation'] = $callback; }
function delete_option( $name ) { if ( ! empty( $GLOBALS['delete_fail'] ) && 'marrison_addon_login_designer' === $name ) { return false; } unset( $GLOBALS['options'][$name], $GLOBALS['autoload'][$name] ); return true; }
class WP_Error { public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; } public function get_error_code() { return $this->code; } public function get_error_message() { return $this->message; } public $code; public $message; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function __( $s, $domain = '' ) { return $s; }
function get_home_path() { return __DIR__ . '/'; }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function current_time( $format ) { return '2026-10-28 12:00:00'; }
function is_multisite() { return false; }
function wp_unschedule_hook( $hook, $error = false ) { return 0; }
function update_option( $name, $v ) { $GLOBALS['options'][$name] = $v; return true; }
function get_option( $name, $default = false ) { $GLOBALS['option_reads'][] = $name; return $GLOBALS['options'][$name] ?? $default; }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_basename( $file ) { return basename( $file ); }
function is_admin() { return $GLOBALS['admin_context']; }
function did_action() { return 0; }
function wp_doing_ajax() { return false; }
function wp_is_json_request() { return false; }
function is_customize_preview() { return false; }
function wp_unslash( $v ) { return $v; }
function wp_parse_url( $v, $component ) { return parse_url( $v, $component ); }
function current_user_can( $cap ) { return ! empty( $GLOBALS['authorized'] ); }
function sanitize_text_field( $v ) { return strip_tags( $v ); }
function sanitize_hex_color( $v ) { return preg_match( '/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $v ) ? $v : null; }
function wp_strip_all_tags( $v ) { return strip_tags( $v ); }
function esc_url_raw( $v, $protocols = [] ) { return preg_match( '~^https?://~', $v ) ? $v : ''; }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function plugins_url( $path, $file ) { return '/plugins/' . $path; }
function wp_enqueue_style( $handle, $url, $deps, $version ) { $GLOBALS['assets']['styles'][$handle] = $url; }
function wp_add_inline_style( $handle, $css ) { $GLOBALS['assets']['inline'][$handle][] = $css; }
function wp_enqueue_script( $handle, $url, $deps, $version, $footer ) { $GLOBALS['assets']['scripts'][$handle] = $url; }
function wp_localize_script( $handle, $name, $data ) {}
function add_option( $name, $value, $deprecated = '', $autoload = null ) {
    if ( isset( $GLOBALS['options'][$name] ) ) { return false; }
    $GLOBALS['options'][$name] = $value; $GLOBALS['autoload'][$name] = $autoload; return true;
}
function wp_set_option_autoload( $name, $autoload ) { $GLOBALS['autoload'][$name] = $autoload; }
function esc_html__( $s, $domain = '' ) { return $s; }
function wp_die( $message, $title = '', $args = [] ) { throw new RuntimeException( 'denied:' . $args['response'] ); }
function verify_scope( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function module_hooks() {
    $result = [];
    foreach ( $GLOBALS['hooks'] as $name => $callbacks ) {
        foreach ( $callbacks as $cb ) {
            if ( is_array( $cb ) && $cb[0] instanceof Marrison_Addon_Login_Designer ) { $result[] = $name; }
        }
    }
    sort( $result ); return $result;
}
require __DIR__ . '/../marrison-addon/marrison-addon.php';
new Marrison_Addon();
verify_scope( ! class_exists( 'Marrison_Addon_Login_Designer', false ), 'OFF must not include module PHP.' );
verify_scope( [] === $assets && ! in_array( 'marrison_addon_login_designer', $option_reads, true ), 'OFF must not load assets or read settings.' );
verify_scope( [] === module_hooks(), 'OFF must not register module hooks.' );
$options['marrison_addon_login_designer'] = [ 'form_bg'=>'#123456' ];
$options['unrelated_option'] = 'keep';
$deactivation(false);
verify_scope( ! isset( $options['marrison_addon_login_designer'] ), 'Plugin deactivation must remove stale settings even while module is OFF.' );
verify_scope( ! class_exists( 'Marrison_Addon_Login_Designer', false ), 'Deactivation cleanup must not load Designer PHP.' );
verify_scope( 'keep' === $options['unrelated_option'], 'Plugin deactivation must preserve unrelated settings.' );
$options['marrison_addon_login_designer'] = [ 'form_bg'=>'#654321' ];
$delete_fail = true;
$result = Marrison_Addon::prepare_module_update( [], [ 'login_designer'=>1 ] );
verify_scope( [ 'login_designer'=>1 ] === $result && is_wp_error( Marrison_Addon::get_module_update_error() ), 'Failed deletion must prevent successful OFF.' );
$delete_fail = false;
verify_scope( [] === Marrison_Addon::prepare_module_update( [], [ 'login_designer'=>1 ] ) && ! isset( $options['marrison_addon_login_designer'] ), 'Successful OFF must remove settings.' );
// Start the ON request payload counters afresh after transitional cleanup.
$option_reads = [];
$options['marrison_addon_modules'] = [ 'login_designer'=>1 ];
new Marrison_Addon();
verify_scope( class_exists( 'Marrison_Addon_Login_Designer', false ), 'ON registry must include module.' );
verify_scope( [ 'customize_register' ] === module_hooks(), 'Ordinary frontend must have only lazy Customizer registration.' );
verify_scope( [] === $assets && ! in_array( 'marrison_addon_login_designer', $option_reads, true ), 'ON frontend must not read settings or load assets.' );
foreach ( [ 'frontend', 'admin', 'login', 'preview' ] as $context ) {
    $hooks = []; $assets = []; $option_reads = []; $_GET = [];
    $admin_context = 'admin' === $context;
    $GLOBALS['pagenow'] = 'login' === $context ? 'wp-login.php' : 'index.php';
    if ( 'preview' === $context ) { $_GET['marrison_login_preview'] = '1'; }
    $module = new Marrison_Addon_Login_Designer();
    verify_scope( [] === $assets && [] === $option_reads, $context . ': constructor must not load payload or settings.' );
    if ( 'login' !== $context ) { verify_scope( ! isset( $hooks['login_enqueue_scripts'] ) && ! isset( $hooks['login_head'] ), $context . ': login hooks leaked.' ); }
    if ( 'preview' !== $context ) { verify_scope( ! isset( $hooks['template_redirect'] ), $context . ': preview handler leaked.' ); }
    if ( 'login' === $context ) {
        foreach ( $hooks['login_enqueue_scripts'] as $cb ) { $cb(); }
        verify_scope( isset( $assets['styles']['marrison-login-designer'] ) && empty( $assets['scripts'] ), 'Login must load CSS only.' );
        $module->display_language_dropdown( true ); $module->logo_text( 'WordPress' );
        verify_scope( [ 'marrison_addon_login_designer' ] === $option_reads, 'Login must read settings once per request.' );
    }
    if ( 'admin' === $context ) {
        $authorized = true; $module->controls_assets();
        verify_scope( [] === $assets && [] === $option_reads, 'Ordinary backend must not load editor assets or settings.' );
        $_GET['url'] = [ 'invalid' ]; $module->controls_assets();
        verify_scope( [] === $assets, 'Malformed URL must not enqueue assets.' );
        $_GET['url'] = 'https://example.test/?marrison_login_preview=1'; $module->controls_assets();
        verify_scope( isset( $assets['scripts']['marrison-login-controls'] ), 'Dedicated editor must load controls.' );
        $options[Marrison_Addon_Login_Designer::OPTION] = [ 'custom_css'=>'body{color:red}' ];
        $autoload[Marrison_Addon_Login_Designer::OPTION] = true;
        $options['unrelated_option'] = 'keep';
        Marrison_Addon::cleanup_module_effects( 'login_designer' );
        verify_scope( ! isset( $options[Marrison_Addon_Login_Designer::OPTION] ), 'OFF must delete all Designer settings.' );
        verify_scope( 'keep' === $options['unrelated_option'], 'Cleanup must not touch other settings.' );

    }
    if ( 'preview' === $context ) {
        $authorized = false;
        try { $module->preview(); throw new RuntimeException( 'Anonymous preview allowed.' ); }
        catch ( RuntimeException $e ) { verify_scope( 'denied:403' === $e->getMessage(), 'Preview authorization failed.' ); }
        verify_scope( [] === $assets && [] === $option_reads, 'Unauthorized preview must not read data or enqueue assets.' );
    }
}
echo "PASS Login Designer scope: OFF bootstrap; ON frontend/backend zero assets and settings reads; login CSS only and one settings read; editor assets gated; unauthorized preview denied; Designer option removed on disable and plugin deactivation with class unloaded; deletion failure blocks OFF; unrelated values preserved.\n";
