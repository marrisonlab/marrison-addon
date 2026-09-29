<?php
/**
 * Run with: php tests/dynamic-svg-php-test.php
 * Small WordPress stubs for the Dynamic SVG module contract.
 */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['marrison_test_modules']       = [];
$GLOBALS['marrison_test_hooks']         = [];
$GLOBALS['marrison_test_filters']       = [];
$GLOBALS['marrison_test_cache']         = [];
$GLOBALS['marrison_test_upload_basedir'] = sys_get_temp_dir() . '/marrison-dynamic-svg-test-' . uniqid();
$GLOBALS['marrison_test_upload_baseurl'] = 'https://example.test/wp-content/uploads';
$GLOBALS['marrison_test_attachments']   = [];

mkdir( $GLOBALS['marrison_test_upload_basedir'] . '/icons', 0777, true );

function add_action( $name, $callback ) {
	$GLOBALS['marrison_test_hooks'][ $name ][] = $callback;
}
function add_filter( $name, $callback ) {
	$GLOBALS['marrison_test_filters'][ $name ][] = $callback;
}
function apply_filters( $name, $value ) {
	if ( empty( $GLOBALS['marrison_test_filters'][ $name ] ) ) {
		return $value;
	}

	foreach ( $GLOBALS['marrison_test_filters'][ $name ] as $callback ) {
		$value = $callback( $value );
	}

	return $value;
}
function esc_html__( $text ) { return $text; }
function get_option( $name, $default = [] ) {
	return 'marrison_addon_modules' === $name ? $GLOBALS['marrison_test_modules'] : $default;
}
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_basename( $file ) { return basename( $file ); }
function is_admin() { return false; }
function did_action() { return 0; }
function wp_doing_ajax() { return false; }
function wp_is_json_request() { return false; }
function is_customize_preview() { return false; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return $value; }
function admin_url( $path = '' ) { return '/wp-admin/' . $path; }
function wp_upload_dir() {
	return [
		'basedir' => $GLOBALS['marrison_test_upload_basedir'],
		'baseurl' => $GLOBALS['marrison_test_upload_baseurl'],
	];
}
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function wp_parse_url( $url ) { return parse_url( $url ); }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function site_url( $path = '/' ) { return 'https://example.test' . $path; }
function absint( $value ) { return abs( (int) $value ); }
function get_attached_file( $attachment_id ) {
	return isset( $GLOBALS['marrison_test_attachments'][ $attachment_id ] ) ? $GLOBALS['marrison_test_attachments'][ $attachment_id ] : false;
}
function wp_cache_get( $key, $group = '' ) {
	$full_key = $group . ':' . $key;
	return array_key_exists( $full_key, $GLOBALS['marrison_test_cache'] ) ? $GLOBALS['marrison_test_cache'][ $full_key ] : false;
}
function wp_cache_set( $key, $value, $group = '' ) {
	$GLOBALS['marrison_test_cache'][ $group . ':' . $key ] = $value;
	return true;
}

function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

class Fake_JetEngine_Callback_Manager {
	public $callbacks = [];

	public function register_callback( $callback, $label, $args = [] ) {
		$this->callbacks[ $callback ] = [
			'label' => $label,
			'args' => $args,
		];
	}
}

$plugin_file = dirname( __DIR__ ) . '/marrison-addon.php';
if ( ! file_exists( $plugin_file ) ) {
	$plugin_file = dirname( __DIR__ ) . '/marrison-addon/marrison-addon.php';
}

require $plugin_file;

new Marrison_Addon();
check( ! class_exists( 'Marrison_Addon_Dynamic_SVG', false ), 'Off module loaded its PHP file.' );
check( empty( $GLOBALS['marrison_test_hooks']['jet-engine/callbacks/register'] ), 'Off module registered a JetEngine callback hook.' );

$GLOBALS['marrison_test_modules'] = [ 'dynamic_svg' => 1 ];
new Marrison_Addon();
check( ! class_exists( 'Marrison_Addon_Dynamic_SVG', false ), 'JetEngine-inactive module loaded its PHP file.' );
check( empty( $GLOBALS['marrison_test_hooks']['jet-engine/callbacks/register'] ), 'JetEngine-inactive module registered a hook.' );

eval( 'class Jet_Engine {}' );

new Marrison_Addon();
check( class_exists( 'Marrison_Addon_Dynamic_SVG', false ), 'Enabled module PHP was not loaded.' );
check( 1 === count( $GLOBALS['marrison_test_hooks']['jet-engine/callbacks/register'] ), 'JetEngine callback hook was not registered exactly once.' );
check( 1 === count( $GLOBALS['marrison_test_filters']['jet-engine/listings/allowed-callbacks'] ), 'Allowed callback fallback was not registered exactly once.' );

$manager = new Fake_JetEngine_Callback_Manager();
$GLOBALS['marrison_test_hooks']['jet-engine/callbacks/register'][0]( $manager );
check( isset( $manager->callbacks['marrison_addon_inline_svg'] ), 'Inline SVG callback was not registered with JetEngine manager.' );
check( isset( $manager->callbacks['marrison_addon_inline_svg_current_color'] ), 'Current Color callback was not registered with JetEngine manager.' );

$allowed_callbacks = $GLOBALS['marrison_test_filters']['jet-engine/listings/allowed-callbacks'][0]( [] );
check( isset( $allowed_callbacks['marrison_addon_inline_svg'] ), 'Inline SVG fallback callback is missing.' );
check( isset( $allowed_callbacks['marrison_addon_inline_svg_current_color'] ), 'Current Color fallback callback is missing.' );

$svg_path = $GLOBALS['marrison_test_upload_basedir'] . '/icons/original.svg';
file_put_contents(
	$svg_path,
	'<svg viewBox="0 0 10 10" width="10" height="10" class="icon"><g><path class="shape" fill="#111111" d="M0 0h10v10z"/><path fill="none" stroke="#222222" stroke-width="2" d="M1 1h8"/></g></svg>'
);

$style_svg_path = $GLOBALS['marrison_test_upload_basedir'] . '/icons/style.svg';
file_put_contents(
	$style_svg_path,
	'<svg viewBox="0 0 10 10"><defs><clipPath id="c"><rect width="10" height="10"/></clipPath></defs><path style="fill:#123456; stroke: rgb(0,0,0); stroke-width:2; clip-path:url(#c)" d="M0 0h10v10z"/></svg>'
);

$danger_svg_path = $GLOBALS['marrison_test_upload_basedir'] . '/icons/danger.svg';
file_put_contents(
	$danger_svg_path,
	'<svg viewBox="0 0 10 10" onclick="alert(1)"><script>alert(1)</script><path fill="url(https://evil.test/a.svg#x)" stroke="javascript:alert(1)" d="M0 0h10v10z"/><use href="javascript:alert(1)"/></svg>'
);

$png_path = $GLOBALS['marrison_test_upload_basedir'] . '/icons/not-svg.png';
file_put_contents( $png_path, 'png' );

$GLOBALS['marrison_test_attachments'] = [
	101 => $svg_path,
	102 => $style_svg_path,
	103 => $danger_svg_path,
	104 => $png_path,
	105 => $GLOBALS['marrison_test_upload_basedir'] . '/icons/missing.svg',
];

$inline = marrison_addon_inline_svg( 101 );
check( false !== strpos( $inline, '<span class="marrison-inline-svg">' ), 'Attachment ID SVG was not wrapped.' );
check( false !== strpos( $inline, '<svg' ), 'Attachment ID SVG did not render inline.' );
check( false !== strpos( $inline, 'viewBox="0 0 10 10"' ), 'viewBox was not preserved.' );
check( false !== strpos( $inline, 'width="10"' ) && false !== strpos( $inline, 'height="10"' ), 'Width or height was not preserved.' );
check( false !== strpos( $inline, 'fill="#111111"' ), 'Normal callback did not preserve fill color.' );
check( false !== strpos( $inline, 'stroke="#222222"' ), 'Normal callback did not preserve stroke color.' );

$inline_url = marrison_addon_inline_svg( 'https://example.test/wp-content/uploads/icons/original.svg?ver=1' );
check( false !== strpos( $inline_url, '<svg' ), 'Local upload URL SVG did not render inline.' );

$current = marrison_addon_inline_svg_current_color( 101 );
check( false !== strpos( $current, 'fill="currentColor"' ), 'Current Color callback did not convert fill.' );
check( false !== strpos( $current, 'stroke="currentColor"' ), 'Current Color callback did not convert stroke.' );
check( false !== strpos( $current, 'fill="none"' ), 'Current Color callback converted fill none.' );

$style_current = marrison_addon_inline_svg_current_color( 102 );
check( false !== strpos( $style_current, 'fill: currentColor' ), 'Current Color callback did not convert style fill.' );
check( false !== strpos( $style_current, 'stroke: currentColor' ), 'Current Color callback did not convert style stroke.' );
check( false !== strpos( $style_current, 'clip-path: url(#c)' ), 'Safe local style URL was not preserved.' );

check( '' === marrison_addon_inline_svg( 104 ), 'PNG attachment was interpreted as SVG.' );
check( '' === marrison_addon_inline_svg( 105 ), 'Missing attachment file produced output.' );
check( '' === marrison_addon_inline_svg( 'https://evil.test/wp-content/uploads/icons/original.svg' ), 'External URL was accepted.' );

$danger = marrison_addon_inline_svg( 103 );
check( false === stripos( $danger, '<script' ), 'Dangerous script element survived sanitization.' );
check( false === stripos( $danger, 'onclick' ), 'Event handler survived sanitization.' );
check( false === stripos( $danger, 'javascript:' ), 'JavaScript URL survived sanitization.' );
check( false === stripos( $danger, 'https://evil.test' ), 'External URL reference survived sanitization.' );

check( count( $GLOBALS['marrison_test_cache'] ) >= 2, 'Original and Current Color SVG variants were not cached separately.' );

echo "Dynamic SVG PHP contract: PASS\n";
