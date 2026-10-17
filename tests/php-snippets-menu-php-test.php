<?php
/**
 * Native WordPress hook/menu API regression. No WordPress database is used.
 * php tests/php-snippets-menu-php-test.php <wordpress-root> [normal|no-unfiltered|no-manage|disabled]
 */
error_reporting( E_ALL );
$wordpress_root = $argv[1] ?? '';
$scenario = $argv[2] ?? 'normal';
if ( ! is_file( $wordpress_root . '/wp-admin/includes/plugin.php' ) ) {
	fwrite( STDERR, "Pass an installed WordPress root.\n" );
	exit( 1 );
}
define( 'ABSPATH', rtrim( $wordpress_root, '/\\' ) . '/' );
define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
define( 'WPMU_PLUGIN_DIR', ABSPATH . 'wp-content/mu-plugins' );
define( 'WP_DEBUG', false );
$GLOBALS['caps'] = [ 'manage_options' => 'no-manage' !== $scenario, 'unfiltered_html' => 'no-unfiltered' !== $scenario ];
$menu = $submenu = $admin_page_hooks = $_registered_pages = $_parent_pages = $_wp_real_parent_file = $_wp_menu_nopriv = $_wp_submenu_nopriv = $wp_plugin_paths = [];
$pagenow = 'admin.php';
$plugin_page = 'marrison_addon_php_snippets';
$_GET['page'] = $plugin_page;
$_SERVER['SCRIPT_NAME'] = '/wp-admin/admin.php';
function get_option( $name, $default = false ) { return 'marrison_addon_modules' === $name ? [ 'php_snippets' => 'disabled' !== $GLOBALS['scenario'] ] : $default; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['caps'][ $cap ] ); }
function is_admin() { return true; }
function wp_is_recovery_mode() { return false; }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function sanitize_title( $text ) { return strtolower( str_replace( ' ', '-', $text ) ); }
function set_url_scheme( $url ) { return $url; }
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }

// Use the real priority handling, parent slug resolution, page registry and
// access checks that the earlier CRUD/CodeMirror fixtures did not exercise.
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-admin/includes/plugin.php';
require dirname( __DIR__ ) . '/marrison-addon/marrison-addon.php';
do_action( 'plugins_loaded' );
do_action( 'admin_menu' );
$hook = get_plugin_page_hookname( $plugin_page, 'marrison_addon_panel' );
$allowed = 'normal' === $scenario;
$registered = isset( $_registered_pages[ $hook ] ) && false !== has_action( $hook );
$accessible = user_can_access_admin_page();
if ( $allowed !== $registered || $allowed !== $accessible ) {
	fwrite( STDERR, json_encode( [ 'scenario' => $scenario, 'expected_access' => $allowed, 'actual_access' => $accessible, 'expected_hook' => $hook, 'registered_hooks' => array_keys( $_registered_pages ) ], JSON_PRETTY_PRINT ) . "\n" );
	exit( 1 );
}
if ( $allowed && ( $submenu['marrison_addon_panel'][0][2] ?? '' ) !== 'marrison_addon_panel' ) {
	fwrite( STDERR, "The parent dashboard must remain the first submenu.\n" );
	exit( 1 );
}
if ( 'disabled' === $scenario && class_exists( 'Marrison_Addon_PHP_Snippets', false ) ) {
	fwrite( STDERR, "Disabled module loaded its code.\n" );
	exit( 1 );
}
echo "PHP Snippets native menu/access: PASS ($scenario; $hook).\n";
