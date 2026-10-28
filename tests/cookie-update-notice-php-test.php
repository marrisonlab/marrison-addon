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
    verify_notice( has_action( 'admin_notices' ) === false, 'Update notice is still registered.' );
    verify_notice( has_action( 'marrison_addon/cookie_scan_finished' ) === false, 'Scan completion still acknowledges plugin updates.' );
    $preserved = array(
        'marrison_cookie_last_scan' => 1793098800,
        'marrison_cookie_scan_report' => array( 'status' => 'completed', 'cookie_count' => 3 ),
        'marrison_cookie_banner_title' => 'My banner',
        'marrison_cookie_auto_scan' => true,
        'marrison_cookie_auto_scan_opt_in' => true,
    );
    foreach ( $preserved as $key => $value ) { update_option( $key, $value ); }
    foreach ( array( '1.9.0', '2.0.0', '3.0.0' ) as $installed_version ) {
        update_option( 'marrison_cookie_module_version', $installed_version );
        $install->invoke( $module );
        verify_notice( ! get_option( 'marrison_cookie_rescan_required_version' ), 'Version change requested a scan: ' . $installed_version );
        verify_notice( get_option( 'marrison_cookie_module_version' ) === '2.0.0', 'Installation metadata was not maintained.' );
        // Also retire notices already saved by an older release, even at the same version.
        update_option( 'marrison_cookie_rescan_required_version', '1.9.0' );
        foreach ( array( false, true ) as $language ) {
            $english = $language;
            ob_start(); do_action( 'admin_notices' ); $notice = ob_get_clean();
            verify_notice( $notice === '', 'A saved update request still displays a notice.' );
        }
        $install->invoke( $module );
        verify_notice( ! get_option( 'marrison_cookie_rescan_required_version' ), 'Legacy request was not removed without rescanning.' );
        foreach ( $preserved as $key => $value ) {
            verify_notice( get_option( $key ) === $value, 'Plugin update changed existing cookie data: ' . $key );
        }
    }
    echo "PASS cookie updates: fresh install, upgrades, same-version reload, downgrade, legacy request cleanup, no IT/EN notices, preserved scan results and settings.\n";
} finally {
    unlink( $temporary . '/wp-admin/includes/upgrade.php' );
    rmdir( $temporary . '/wp-admin/includes' ); rmdir( $temporary . '/wp-admin' ); rmdir( $temporary );
}
