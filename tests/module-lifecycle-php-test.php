<?php
/** php tests/module-lifecycle-php-test.php /path/to/wordpress
 * Real WordPress hook dispatcher; in-memory options/cron and real temporary htaccess.
 */
namespace Elementor {
    class Plugin { public static $instance; }
}
namespace {
error_reporting( E_ALL );
$wordpress = $argv[1] ?? getenv( 'WORDPRESS_PATH' );
if ( ! $wordpress || ! is_file( rtrim( $wordpress, '/\\' ) . '/wp-includes/plugin.php' ) ) {
    fwrite( STDERR, "Pass an installed WordPress root (hooks only; no database required).\n" ); exit( 1 );
}
define( 'ABSPATH', rtrim( $wordpress, '/\\' ) . '/' );
define( 'WP_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'WPMU_PLUGIN_DIR', dirname( __DIR__ ) . '/mu-plugins' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'YEAR_IN_SECONDS', 31536000 );
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/class-wp-error.php';
$wp_plugin_paths = [];
$options = [ 'marrison_addon_modules' => [], 'fixture_preserved' => 'keep' ];
$events = $cron = $actions = $notices = [];
$caps = [ 'manage_options' => true, 'unfiltered_html' => true ];
$cron_fail = false;
$directory = sys_get_temp_dir() . '/marrison-lifecycle-' . uniqid();
mkdir( $directory );
class Lifecycle_Json extends \Exception {
    public $success;
    public function __construct( $success ) { $this->success = $success; }
}
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function is_wp_error( $value ) { return $value instanceof \WP_Error; }
function get_option( $key, $default = false ) { global $options; return $options[ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) {
    global $options;
    $old = get_option( $key );
    $value = apply_filters( 'sanitize_option_' . $key, $value );
    $value = apply_filters( 'pre_update_option_' . $key, $value, $old, $key );
    if ( ! empty( $GLOBALS['write_fail'] ) ) { return false; }
    if ( $old === $value ) { return false; }
    $options[ $key ] = $value;
    do_action( 'update_option_' . $key, $old, $value, $key );
    return true;
}
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['caps'][ $cap ] ); }
function is_admin() { return false; }
function wp_doing_cron() { return false; }
function wp_doing_ajax() { return false; }
function wp_is_json_request() { return false; }
function is_customize_preview() { return false; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return $value; }
function check_ajax_referer() { return true; }
function wp_send_json_error() { throw new Lifecycle_Json( false ); }
function wp_send_json_success() { throw new Lifecycle_Json( true ); }
function add_settings_error( $setting, $code, $message ) { $GLOBALS['notices'][] = $message; }
function wp_unschedule_hook( $hook, $wp_error = false ) {
    if ( $GLOBALS['cron_fail'] ) { return new \WP_Error( 'fixture_cron', 'Cron cleanup failed.' ); }
    $count = count( $GLOBALS['cron'][ $hook ] ?? [] ); unset( $GLOBALS['cron'][ $hook ] ); return $count;
}
function as_unschedule_all_actions( $hook, $args = null, $group = '' ) { unset( $GLOBALS['actions'][ $hook ] ); }
function as_has_scheduled_action( $hook, $args = null, $group = '' ) { return ! empty( $GLOBALS['actions'][ $hook ] ); }
function is_multisite() { return false; }
function wp_die( $message ) { throw new \RuntimeException( $message ); }
function get_home_path() { return $GLOBALS['directory'] . '/'; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function current_time( $format ) { return '2026-10-19 18:00:00'; }
function home_url( $path = '' ) { return 'https://fixture.test' . $path; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function check( $value, $message ) { if ( ! $value ) { throw new \RuntimeException( $message ); } }
$plugin = dirname( __DIR__ ) . '/marrison-addon/marrison-addon.php';
require $plugin;
new \Marrison_Addon();
check( count( \Marrison_Addon::get_module_definitions( false ) ) === 28, 'Registry changed unexpectedly.' );
check( ! array_filter( get_included_files(), function( $path ) { return strpos( str_replace( '\\', '/', $path ), '/includes/modules/' ) !== false; } ), 'OFF included module PHP.' );
foreach ( \Marrison_Addon::get_module_definitions( false ) as $definition ) {
    check( ! class_exists( $definition['class'], false ), 'OFF loaded a module class.' );
}
\Elementor\Plugin::$instance = (object) [ 'files_manager' => new class {
    public $clears = 0;
    public function clear_cache() { $this->clears++; }
} ];
add_action( 'marrison_addon/module_status_changed', function( $id, $enabled ) { $GLOBALS['events'][] = [ $id, $enabled ]; }, 10, 2 );
$admin = ( new \ReflectionClass( 'Marrison_Addon_Admin' ) )->newInstanceWithoutConstructor();
function ajax( $data ) {
    $_POST = $data;
    try { $GLOBALS['admin']->ajax_save_option(); }
    catch ( Lifecycle_Json $result ) { return $result->success; }
    throw new \RuntimeException( 'No AJAX result.' );
}
try {
    $options['marrison_addon_modules'] = [ 'image_sizes' => 1 ];
    \Marrison_Addon::clear_runtime_caches();
    check( \Marrison_Addon::is_module_enabled( 'image_sizes' ), 'Cannot prime registry cache.' );
    check( ajax( [ 'option_name' => 'marrison_addon_modules', 'key' => 'image_sizes', 'value' => '0' ] ), 'Single toggle failed.' );
    check( ! \Marrison_Addon::is_module_enabled( 'image_sizes' ), 'Registry cache survived OFF.' );
    check( $events === [ [ 'image_sizes', false ] ] && \Elementor\Plugin::$instance->files_manager->clears === 1, 'Single toggle must invalidate and dispatch once.' );

    $options['marrison_addon_modules'] = [ 'cookie_manager' => 1, 'product_discount' => 1, 'browser_cache' => 1, 'image_sizes' => 1, 'dynamic_background_image_size' => 1 ];
    foreach ( [ 'marrison_cookie_daily_scan', 'marrison_addon_cookie_engine_tick', 'marrison_cookie_consent_cleanup', 'marrison_product_discount_backfill_batch', 'marrison_product_discount_scheduled_sync', 'marrison_product_discount_scheduled_sync_batch', 'marrison_product_discount_queued_sync_batch' ] as $hook ) {
        $cron[ $hook ] = [ [ 'args' => [ 'product_ids' => [ 1, 2 ] ] ] ];
        if ( strpos( $hook, 'marrison_product_discount_' ) === 0 ) { $actions[ $hook ] = [ 'product_ids' => [ 1, 2 ] ]; }
    }
    $managed = "# BEGIN Marrison Optimizer Browser Cache\nHeader set Cache-Control test\n# END Marrison Optimizer Browser Cache\n";
    file_put_contents( $directory . '/.htaccess', "# Preserve WordPress\n" . $managed );
    $events = [];
    $before = \Elementor\Plugin::$instance->files_manager->clears;
    check( ajax( [ 'option_name' => 'marrison_addon_modules', 'value' => [] ] ), 'Bulk OFF failed.' );
    check( ! $cron && ! $actions, 'Bulk OFF left scheduled jobs (including jobs with args).' );
    check( count( $events ) === 5 && \Elementor\Plugin::$instance->files_manager->clears === $before + 1, 'Bulk must dispatch each change once and clear CSS once.' );
    check( file_get_contents( $directory . '/.htaccess' ) === "# Preserve WordPress\n", 'Managed cleanup changed unrelated server configuration.' );

    $events = [];
    update_option( 'marrison_addon_modules', [ 'image_sizes' => 1 ] );
    update_option( 'marrison_addon_modules', [ 'image_sizes' => 'false' ] );
    check( ! \Marrison_Addon::is_module_enabled( 'image_sizes' ) && $events === [ [ 'image_sizes', true ], [ 'image_sizes', false ] ], 'Direct option path differs from AJAX.' );
    $caps['unfiltered_html'] = false;
    update_option( 'marrison_addon_modules', [ 'php_snippets' => 1 ] );
    check( ! \Marrison_Addon::is_module_enabled( 'php_snippets' ), 'Settings API bypassed snippet capability.' );
    $caps['unfiltered_html'] = true;

    $options['marrison_addon_modules'] = [ 'browser_cache' => 1 ];
    file_put_contents( $directory . '/.htaccess', "# BEGIN Marrison Optimizer Browser Cache\n# incomplete\n" );
    check( ! ajax( [ 'option_name' => 'marrison_addon_modules', 'value' => [] ] ), 'Broken htaccess reported successful OFF.' );
    check( get_option( 'marrison_addon_modules' )['browser_cache'] === 1 && is_wp_error( \Marrison_Addon::get_module_update_error() ), 'Failed cleanup changed the toggle.' );
    check( count( $notices ) > 0, 'Settings API cleanup failure must be visible.' );

    $options['marrison_addon_modules'] = [ 'cookie_manager' => 1 ];
    $cron_fail = true;
    check( ! ajax( [ 'option_name' => 'marrison_addon_modules', 'key' => 'cookie_manager', 'value' => '0' ] ), 'Cron failure reported successful OFF.' );
    check( get_option( 'marrison_addon_modules' )['cookie_manager'] === 1, 'Cron failure changed the toggle.' );
    $cron_fail = false;
    $options['marrison_addon_modules'] = [];
    $GLOBALS['write_fail'] = true;
    check( ! ajax( [ 'option_name' => 'marrison_addon_modules', 'key' => 'image_sizes', 'value' => '1' ] ), 'Database failure reported success.' );
    $GLOBALS['write_fail'] = false;
    check( ajax( [ 'option_name' => 'marrison_addon_modules', 'value' => [] ] ), 'Idempotent save must succeed.' );
    file_put_contents( $directory . '/.htaccess', "# Preserve WordPress\n" . $managed );
    $cron['marrison_cookie_daily_scan'] = [ [] ];
    $actions['marrison_product_discount_queued_sync_batch'] = [ 'product_ids' => [ 3 ] ];
    $saved = $options['marrison_addon_modules'];
    do_action( 'deactivate_' . plugin_basename( $plugin ), false );
    check( ! $cron && ! $actions && file_get_contents( $directory . '/.htaccess' ) === "# Preserve WordPress\n", 'Plugin deactivation left effects behind.' );
    check( $options['marrison_addon_modules'] === $saved && $options['fixture_preserved'] === 'keep', 'Deactivation changed saved settings.' );
    echo "PASS module lifecycle: 28 OFF, single/bulk/direct writes, CSS invalidation, cron with args, server cleanup/errors, snippet capabilities and plugin deactivation.\n";
} finally {
    if ( is_file( $directory . '/.htaccess' ) ) { unlink( $directory . '/.htaccess' ); }
    rmdir( $directory );
}
}

