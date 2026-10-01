<?php
/**
 * Run with: php tests/module-definitions-i18n-test.php
 * Verifies module bootstrapping does not trigger translations before init.
 */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['marrison_test_modules'] = [];
$GLOBALS['marrison_test_hooks'] = [];
$GLOBALS['marrison_test_translate_calls'] = 0;

function add_action( $name, $callback ) {
	$GLOBALS['marrison_test_hooks'][ $name ][] = $callback;
}

function add_filter( $name, $callback ) {
	$GLOBALS['marrison_test_hooks'][ $name ][] = $callback;
}

function esc_html__( $text ) {
	$GLOBALS['marrison_test_translate_calls']++;
	return $text;
}

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

function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$plugin_file = dirname( __DIR__ ) . '/marrison-addon.php';
if ( ! file_exists( $plugin_file ) ) {
	$plugin_file = dirname( __DIR__ ) . '/marrison-addon/marrison-addon.php';
}

require $plugin_file;
new Marrison_Addon();

check( 0 === $GLOBALS['marrison_test_translate_calls'], 'Module bootstrap triggered translations before init.' );

Marrison_Addon::get_module_definitions( false );
check( 0 === $GLOBALS['marrison_test_translate_calls'], 'Raw module definitions triggered translations.' );

$translated_definitions = Marrison_Addon::get_module_definitions( true );
check( ! empty( $translated_definitions['read_more']['title'] ), 'Translated module definitions are missing Read More.' );
check( $GLOBALS['marrison_test_translate_calls'] > 0, 'Translated module definitions did not call the translation helper.' );

echo "Module definitions i18n contract: PASS\n";
