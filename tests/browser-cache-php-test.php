<?php
/** Run: php tests/browser-cache-php-test.php [optional-output-directory-for-nginx]. */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'YEAR_IN_SECONDS', 31536000 );
define( 'MINUTE_IN_SECONDS', 60 );
$options = [];
$responses = [];
$requests = [];
$root = sys_get_temp_dir() . '/marrison-browser-cache-' . uniqid();
mkdir( $root );
class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
class Marrison_Addon { const VERSION = 'test'; public static function clear_runtime_caches() {} }
class Json_Response extends Exception {
	public $success;
	public function __construct( $success ) { $this->success = $success; }
}
function __( $text, $domain = '' ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function get_option( $key, $default = null ) { return isset( $GLOBALS['options'][$key] ) ? $GLOBALS['options'][$key] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][$key] = $value; return true; }
function current_time() { return '2026-10-09 16:00:00'; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_parse_str( $query, &$result ) { parse_str( $query, $result ); }
function home_url( $path = '' ) { return 'https://cache.example' . $path; }
function get_home_path() { return $GLOBALS['root'] . '/'; }
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
function wp_normalize_path( $value ) { return str_replace( '\\', '/', $value ); }
function add_action() {}
function check_admin_referer() {}
function check_ajax_referer() {}
function apply_filters( $hook, $value ) { return $value; }
function do_action() {}
function wp_send_json_error() { throw new Json_Response( false ); }
function wp_send_json_success() { throw new Json_Response( true ); }
function current_user_can() { return true; }
function wp_doing_ajax() { return false; }
function wp_remote_get( $url, $args ) {
	$GLOBALS['requests'][] = [ $url, $args ];
	return isset( $GLOBALS['responses'][$url] ) ? $GLOBALS['responses'][$url] : new WP_Error( 'missing_fixture', $url );
}
function wp_remote_head() { throw new RuntimeException( 'Diagnostics must use GET.' ); }
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function wp_remote_retrieve_body( $response ) { return isset( $response['body'] ) ? $response['body'] : ''; }
function wp_remote_retrieve_headers( $response ) { return isset( $response['headers'] ) ? $response['headers'] : []; }
function check( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
function invoke( $object, $method, $args = [] ) {
	$reflection = new ReflectionMethod( $object, $method );
	if ( PHP_VERSION_ID < 80100 ) { $reflection->setAccessible( true ); }
	return $reflection->invokeArgs( $object, $args );
}
$module_dir = __DIR__ . '/../marrison-addon/includes/modules/browser-cache/';
require $module_dir . 'class-browser-cache-server.php';
require $module_dir . 'class-browser-cache-diagnostics.php';
require $module_dir . 'class-browser-cache-admin.php';
require __DIR__ . '/../marrison-addon/includes/admin/class-marrison-addon-admin.php';
$server = new Marrison_Addon_Browser_Cache_Server();
$diagnostics = new Marrison_Addon_Browser_Cache_Diagnostics( $server );
$admin = new Marrison_Addon_Browser_Cache_Admin( $server, $diagnostics );
try {
	$settings = $server->get_default_settings();
	check( WEEK_IN_SECONDS === $settings['versioned_ttl'] && 0 === $settings['immutable_versioned'], 'New settings must not assume a query version is immutable for one year.' );
	foreach ( [ 'style.css?ver=1', 'style.css?foo=1&ver=2', 'app.abc123ff.js', 'app-v1.2.js' ] as $url ) { check( $server->is_versioned_url( $url ), 'Versioned URL not recognized: ' . $url ); }
	foreach ( [ 'style.css?ver=', 'style.css?ver[]=1', 'logo.png' ] as $url ) { check( ! $server->is_versioned_url( $url ), 'Invalid/empty version must not be accepted: ' . $url ); }
	$stored = $settings;
	$stored['versioned_ttl'] = YEAR_IN_SECONDS;
	$stored['immutable_versioned'] = 1;
	$options[$server::OPTION_SETTINGS] = $stored;
	check( $server->get_settings()['versioned_ttl'] === YEAR_IN_SECONDS, 'Existing chosen settings must remain intact.' );
	$options[$server::OPTION_SETTINGS] = $settings;
	$rules = $server->build_htaccess_rules();
	check( false !== strpos( $rules, '%{QUERY_STRING}' ) && false !== strpos( $rules, '-f %{REQUEST_FILENAME}' ) && false === strpos( $rules, 'ExpiresByType' ), 'Apache must distinguish queries and avoid caching dynamic MIME responses.' );
	file_put_contents( $root . '/.htaccess', "# Original WordPress rules\nRewriteEngine On\n" );
	$_SERVER['SERVER_SOFTWARE'] = 'Apache';
	check( $server->apply_htaccess_rules() === true && $server->get_state()['status'] === 'pending_verification', 'Writing rules must require real-header verification.' );
	$first = file_get_contents( $root . '/.htaccess' );
	$server->apply_htaccess_rules();
	check( file_get_contents( $root . '/.htaccess' ) === $first && substr_count( $first, $server::BEGIN_MARKER ) === 1, 'Applying rules must be idempotent and preserve existing content.' );
	$before = count( $requests );
	unlink( $root . '/.htaccess' );
	$_GET['page'] = 'marrison_addon_panel';
	$admin->maybe_auto_configure();
	check( $server->htaccess_has_rules() && count( $requests ) === $before, 'Removed rules must be restored without HTTP diagnostics on ordinary page loads.' );
	check( $server->remove_htaccess_rules() === true && ! $server->htaccess_has_rules(), 'Disabling must remove only the managed block.' );
	file_put_contents( $root . '/.htaccess', $server::BEGIN_MARKER . "\n# incomplete\n" );
	check( is_wp_error( $server->apply_htaccess_rules() ) && is_wp_error( $server->remove_htaccess_rules() ), 'Incomplete blocks must fail without declaring success.' );
	$options['marrison_addon_modules'] = [ 'browser_cache' => 1, 'image_sizes' => 1 ];
	$_POST = [ 'option_name' => 'marrison_addon_modules', 'key' => 'browser_cache', 'value' => 'false' ];
	$general_admin = ( new ReflectionClass( 'Marrison_Addon_Admin' ) )->newInstanceWithoutConstructor();
	try { $general_admin->ajax_save_option(); } catch ( Json_Response $response ) {
		check( ! $response->success && 1 === $options['marrison_addon_modules']['browser_cache'], 'Cleanup failure must not report successful disabling or change the toggle.' );
	}
	file_put_contents( $root . '/.htaccess', "# Original WordPress rules\n" );
	try { $general_admin->ajax_save_option(); } catch ( Json_Response $response ) {
		check( $response->success && 0 === $options['marrison_addon_modules']['browser_cache'] && 1 === $options['marrison_addon_modules']['image_sizes'], 'Successful cleanup must disable Browser Cache without changing other modules.' );
	}
	$_POST = [];
	$_SERVER['SERVER_SOFTWARE'] = 'nginx';
	$nginx = $server->build_nginx_config();
	check( false === strpos( $nginx, ' always;' ) && false === strpos( $nginx, 'location ~' ) && false !== strpos( $nginx, '$status' ) && false !== strpos( $nginx, 'expires off;' ), 'Nginx must protect errors and integrate with existing static locations.' );
	check( false !== strpos( $nginx, '"~*' ) && false !== strpos( $nginx, '$sent_http_content_type' ), 'Nginx regex must be quoted and response MIME checked.' );
	$_SERVER['SERVER_SOFTWARE'] = 'OpenLiteSpeed';
	check( 'manual' === $server->get_method()['slug'], 'OpenLiteSpeed must not pretend to apply Header through htaccess.' );
	$_SERVER['SERVER_SOFTWARE'] = 'nginx';
	$settings['asset_types'] = [ 'css' => 1, 'js' => 0, 'fonts' => 0, 'images' => 0 ];
	$options[$server::OPTION_SETTINGS] = $settings;
	$url = home_url( '/style.css?ver=1' );
	$responses[home_url( '/' )] = [ 'code' => 200, 'body' => '<link href="' . $url . '" rel="stylesheet">' ];
	$good = [ 'code' => 200, 'headers' => [ 'content-type' => 'text/css', 'cache-control' => 'public, max-age=604800' ] ];
	$responses[$url] = $good;
	check( $diagnostics->run()['summary']['all_ok'], 'Valid public CSS cache must pass.' );
	foreach ( [
		[ 'code' => 404, 'headers' => $good['headers'] ],
		[ 'code' => 500, 'headers' => $good['headers'] ],
		[ 'code' => 200, 'headers' => [ 'content-type' => 'text/html', 'cache-control' => 'public, max-age=604800' ] ],
		[ 'code' => 200, 'headers' => [ 'content-type' => 'text/css', 'cache-control' => 'no-store, max-age=604800' ] ],
		[ 'code' => 200, 'headers' => [ 'content-type' => 'text/css', 'cache-control' => 'private, max-age=604800' ] ],
		[ 'code' => 200, 'headers' => [ 'content-type' => 'text/css', 'cache-control' => 'no-cache, max-age=604800' ] ],
		[ 'code' => 200, 'headers' => [ 'content-type' => 'text/css', 'cache-control' => 'max-age=604800, max-age=31536000' ] ],
	] as $bad ) {
		$responses[$url] = $bad;
		check( ! $diagnostics->run()['summary']['all_ok'], 'Invalid response incorrectly passed diagnostics: ' . json_encode( $bad ) );
	}
	$responses[$url] = $good;
	$_POST['marrison_browser_cache_action'] = 'diagnostics';
	invoke( $admin, 'handle_page_action' );
	check( 'already_configured' === $server->get_state()['status'], 'Nginx success must describe verified headers, not a managed Apache block.' );
	$responses[$url]['code'] = 404;
	invoke( $admin, 'handle_page_action' );
	check( 'error' === $server->get_state()['status'] && 'error' === invoke( $admin, 'get_display_status', [ $server->get_state(), $server->get_method() ] )['class'], 'A failed fresh verification must replace the previous green badge.' );
	$responses[$url] = $good;
	$settings['asset_types']['fonts'] = 1;
	check( ! $diagnostics->run( $settings )['summary']['all_ok'], 'An enabled category with no inspected assets cannot be declared verified.' );
	$settings['asset_types'] = [ 'css' => 0, 'js' => 0, 'fonts' => 1, 'images' => 0 ];
	$font_url = home_url( '/font.woff2' );
	$responses[$url]['body'] = '@font-face {src:url(' . $font_url . ')}';
	$responses[$font_url] = [ 'code' => 200, 'headers' => [ 'content-type' => 'font/woff2', 'cache-control' => 'max-age=604800' ] ];
	check( $diagnostics->run( $settings )['summary']['all_ok'], 'Font-only diagnostics must discover stylesheets even when CSS checks are disabled.' );
	$settings['asset_types'] = [ 'css' => 0, 'js' => 0, 'fonts' => 0, 'images' => 1 ];
	$image_url = home_url( '/hero.webp' );
	$responses[$url]['body'] = '.hero {background-image:url(' . $image_url . ')}';
	$responses[$image_url] = [ 'code' => 200, 'headers' => [ 'content-type' => 'image/webp', 'cache-control' => 'max-age=604800' ] ];
	check( $diagnostics->run( $settings )['summary']['all_ok'], 'CSS hero backgrounds must be included in diagnostics.' );
	$responses[home_url( '/' )]['body'] .= '<img src="https://cache.example/logo.webp"><img src="https://cache.example/menu.webp"><img src="https://cache.example/card.webp">';
	$sampled = $diagnostics->run( $settings );
	check( $sampled['samples'][0]['url'] === $image_url, 'CSS backgrounds must remain represented when HTML already contains many menu/logo images.' );
	$responses[home_url( '/' )] = [ 'code' => 503 ];
	check( false !== strpos( $diagnostics->run( $settings )['summary']['message'], '503' ), 'Homepage errors must be reported as errors, not absence of static assets.' );
	if ( ! empty( $argv[1] ) ) {
		$out = $argv[1];
		check( is_dir( $out ), 'Missing Nginx output directory.' );
		$custom = $server->get_default_settings();
		$custom['versioned_ttl'] = YEAR_IN_SECONDS;
		$custom['immutable_versioned'] = 1;
		file_put_contents( $out . '/generated-nginx.txt', $server->build_nginx_config( $custom ) );
		file_put_contents( $out . '/generated-apache.txt', $server->build_htaccess_rules( $custom ) );
	}
	echo "Browser Cache settings/rules/diagnostics/admin contracts: PASS\n";
} finally {
	$resolved = realpath( $root );
	check( $resolved && strpos( $resolved, realpath( sys_get_temp_dir() ) . DIRECTORY_SEPARATOR . 'marrison-browser-cache-' ) === 0, 'Unsafe cleanup path.' );
	foreach ( glob( $resolved . '/*' ) as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
	foreach ( glob( $resolved . '/.htaccess*' ) as $file ) { unlink( $file ); }
	rmdir( $resolved );
}
