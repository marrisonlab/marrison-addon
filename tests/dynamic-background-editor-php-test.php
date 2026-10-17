<?php
/** Run: php tests/dynamic-background-editor-php-test.php. Editor-only asset boundary. */
define( 'ABSPATH', __DIR__ . '/' );
$actions = [];
$scripts = [];
$styles = [];
$localizations = [];
function add_action( $name, $callback ) { $GLOBALS['actions'][ $name ][] = $callback; }
function add_filter() {}
function plugins_url( $asset, $file ) { return 'https://fixture.example/plugins/' . basename( dirname( $file ) ) . '/' . $asset; }
function wp_enqueue_script( $handle, $url, $dependencies, $version, $footer ) { $GLOBALS['scripts'][ $handle ] = compact( 'url', 'dependencies', 'version', 'footer' ); }
function wp_enqueue_style( $handle, $url, $dependencies, $version ) { $GLOBALS['styles'][ $handle ] = compact( 'url', 'dependencies', 'version' ); }
function wp_localize_script( $handle, $object, $values ) { $GLOBALS['localizations'][ $handle ] = compact( 'object', 'values' ); }
function __( $message ) { return $message; }
class Marrison_Addon { public static function asset_version( $file ) { return 'fixture-' . basename( $file ); } }
require dirname( __DIR__ ) . '/marrison-addon/includes/modules/class-marrison-addon-dynamic-background-image-size.php';
$module = new Marrison_Addon_Dynamic_Background_Image_Size();
$assertions = 0;
function verify( $condition, $message ) {
	$GLOBALS['assertions']++;
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
verify( [] === $scripts && [] === $styles, 'Constructing the active module must enqueue no frontend assets.' );
verify( ! isset( $actions['wp_enqueue_scripts'] ) && ! isset( $actions['admin_enqueue_scripts'] ), 'Editor assets must not use frontend or general admin hooks.' );
foreach ( $actions['elementor/editor/after_enqueue_scripts'] as $callback ) { call_user_func( $callback ); }
verify( 1 === count( $scripts ) && [] === $styles, 'The editor script hook must enqueue only its script.' );
$script = $scripts['marrison-dynamic-background-size-editor'];
verify( [ 'elementor-editor' ] === $script['dependencies'], 'The native Elementor editor must load first.' );
verify( false !== strpos( $script['url'], '/marrison-addon/assets/js/marrison-dynamic-background-size-editor.js' ), 'The script must use the plugin asset URL.' );
verify( isset( $localizations['marrison-dynamic-background-size-editor']['values']['notice'] ), 'The corrected notice must be translated by PHP and localized.' );
foreach ( $actions['elementor/editor/after_enqueue_styles'] as $callback ) { call_user_func( $callback ); }
verify( 1 === count( $styles ), 'The editor stylesheet hook must enqueue its scoped CSS.' );
verify( false !== strpos( $styles['marrison-dynamic-background-size-editor']['url'], '/marrison-addon/assets/css/marrison-dynamic-background-size-editor.css' ), 'The stylesheet must use the plugin asset URL.' );
echo "Dynamic background editor assets: $assertions assertions PASS\n";
