<?php
/** php tests/cookie-update-notice-php-test.php /path/to/wordpress (native hooks, no database). */
error_reporting( E_ALL );
$wordpress = $argv[1] ?? '';
if ( ! is_file( $wordpress . '/wp-includes/plugin.php' ) ) { throw new RuntimeException( 'Pass an installed WordPress root.' ); }
require $wordpress . '/wp-includes/plugin.php';
$temporary = sys_get_temp_dir() . '/marrison-cookie-update-' . bin2hex( random_bytes( 8 ) );
mkdir( $temporary . '/wp-admin/includes', 0777, true );
file_put_contents( $temporary . '/wp-admin/includes/upgrade.php', '<?php' );
define( 'ABSPATH', $temporary . '/' );
define( 'DB_NAME', 'fixture' );
define( 'MARRISON_COOKIE_VERSION', '2.0.0' );
$options = array();
$enabled = $can_manage = true;
$english = false;
class Marrison_Addon { public static function is_module_enabled( $id ) { return $GLOBALS['enabled']; } }
class Marrison_Addon_Cookie_Consent_Store { public static function duration() { return 184; } }
class NoticeDatabase {
    public $prefix = 'wp_', $last_error = '';
    public function get_charset_collate() { return ''; }
    public function prepare( $sql, $value ) { return $sql; }
    public function get_var( $sql ) { return 'wp_marrison_cookies'; }
}
$wpdb = new NoticeDatabase();
function dbDelta( $sql ) {}
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function add_option( $key, $value ) { if ( isset( $GLOBALS['options'][ $key ] ) ) { return false; } return update_option( $key, $value ); }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function set_transient() {}
function current_user_can( $cap ) { return $GLOBALS['can_manage']; }
function marrison_cookie_site_text( $it, $en ) { return $GLOBALS['english'] ? $en : $it; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return esc_html( $value ); }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function verify_notice( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
require __DIR__ . '/../marrison-addon/includes/modules/class-marrison-addon-cookie-manager.php';
$module = ( new ReflectionClass( 'Marrison_Addon_Cookie_Manager_Module' ) )->newInstanceWithoutConstructor();
$install = new ReflectionMethod( $module, 'ensure_installation' );
$hooks = new ReflectionMethod( $module, 'init_hooks' );
if ( PHP_VERSION_ID < 80100 ) { $install->setAccessible( true ); $hooks->setAccessible( true ); }
try {
    $hooks->invoke( $module );
    $install->invoke( $module );
    verify_notice( get_option( 'marrison_cookie_module_version' ) === '2.0.0' && ! get_option( 'marrison_cookie_rescan_required_version' ), 'Fresh installation must use the wizard without a false update notice.' );
    update_option( 'marrison_cookie_module_version', '1.9.0' );
    $install->invoke( $module );
    verify_notice( get_option( 'marrison_cookie_rescan_required_version' ) === '2.0.0', 'An update must request a fresh scan.' );
    $install->invoke( $module );
    verify_notice( get_option( 'marrison_cookie_rescan_required_version' ) === '2.0.0', 'Reload cleared the outstanding scan request.' );
    ob_start(); do_action( 'admin_notices' ); $notice = ob_get_clean();
    verify_notice( strpos( $notice, 'notice notice-info' ) !== false && strpos( $notice, 'nuova scansione' ) !== false && strpos( $notice, 'page=marrison-cookie&amp;tab=scanner' ) !== false, 'Notice/link missing or incorrectly escaped.' );
    $english = true;
    ob_start(); do_action( 'admin_notices' ); $notice = ob_get_clean();
    verify_notice( strpos( $notice, 'a new cookie scan is required' ) !== false, 'English notice missing.' );
    foreach ( array( 'enabled', 'can_manage' ) as $guard ) {
        $GLOBALS[ $guard ] = false;
        ob_start(); do_action( 'admin_notices' ); $notice = ob_get_clean();
        verify_notice( $notice === '', 'Notice leaked when ' . $guard . ' is false.' );
        $GLOBALS[ $guard ] = true;
    }
    $finished = array( 'plugin_version' => '2.0.0', 'status' => 'completed', 'pages' => array( '/' ), 'http_done' => 1, 'browser_cursor' => 2, 'browser_failed' => 0 );
    foreach ( array( array( 'plugin_version' => '1.9.0' ), array( 'plugin_version' => '' ), array( 'status' => 'http' ), array( 'status' => 'browser' ), array( 'status' => 'cancelled' ), array( 'status' => 'failed' ), array( 'pages' => array() ), array( 'http_done' => 0 ), array( 'browser_cursor' => 1 ), array( 'browser_failed' => 1 ) ) as $incomplete ) {
        do_action( 'marrison_addon/cookie_scan_finished', array_replace( $finished, $incomplete ) );
        verify_notice( get_option( 'marrison_cookie_rescan_required_version' ) === '2.0.0', 'Old/failed/incomplete scan cleared the notice: ' . json_encode( $incomplete ) );
    }
    do_action( 'marrison_addon/cookie_scan_finished', $finished );
    verify_notice( ! get_option( 'marrison_cookie_rescan_required_version' ), 'A newly completed scan did not clear the notice.' );
    update_option('marrison_cookie_rescan_required_version', '2.0.0');
    do_action('marrison_addon/cookie_scan_finished', array('plugin_version' => '2.0.0', 'status' => 'completed', 'static_scan' => array('done' => true), 'local_iframe' => false));
    verify_notice(!get_option('marrison_cookie_rescan_required_version'), 'A completed static scan required an optional iframe before clearing the notice');
    update_option( 'marrison_cookie_rescan_required_version', '2.0.0' );
    do_action( 'marrison_addon/cookie_scan_finished', array_replace( $finished, array( 'status' => 'partial', 'http_failed' => 1 ) ) );
    verify_notice( ! get_option( 'marrison_cookie_rescan_required_version' ), 'A finished scan with an HTTP 404 must retain its partial result without leaving a false not-scanned notice.' );
    update_option( 'marrison_cookie_module_version', '3.0.0' );
    $install->invoke( $module );
    verify_notice( ! get_option( 'marrison_cookie_rescan_required_version' ), 'A downgrade generated an update notice.' );
    echo "PASS cookie update notice: upgrade/fresh install, persistence, active/capability guards, IT/EN, scanner link, old/incomplete/failed scans and completed/partial acknowledgment.\n";
} finally {
    unlink( $temporary . '/wp-admin/includes/upgrade.php' );
    rmdir( $temporary . '/wp-admin/includes' ); rmdir( $temporary . '/wp-admin' ); rmdir( $temporary );
}
