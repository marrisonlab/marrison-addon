<?php
/** Run: php tests/wrapped-link-php-test.php */
define( 'ABSPATH', __DIR__ . '/' );
$hooks = $scripts = [];
$public = true;
$editor = false;
function add_action( $hook, $callback ) { $GLOBALS['hooks'][ $hook ][] = $callback; }
function wp_enqueue_script( $handle, $url, $deps, $version, $footer ) {
	$GLOBALS['scripts'][ $handle ] = compact( 'url', 'deps', 'version', 'footer' );
}
function plugins_url( $path, $file ) { return 'https://example.test/plugins/marrison-addon/' . $path; }
function wp_json_encode( $value ) { return json_encode( $value ); }
class Marrison_Addon {
	public static function plugin_file() { return __DIR__ . '/../marrison-addon/marrison-addon.php'; }
	public static function asset_version( $path ) { return 'fixture-version'; }
}
class Marrison_Addon_Context {
	public static function is_public_frontend_request() { return $GLOBALS['public']; }
	public static function is_elementor_editor_context() { return $GLOBALS['editor']; }
}
class Wrapped_Link_Element {
	public $attributes = [];
	public $settings = [ 'marrison_addon_url' => [ 'url' => 'https://www.marrisonlab.com/projects/marrison-assistant/', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ] ];
	public function get_settings_for_display() { return $this->settings; }
	public function add_render_attribute( $wrapper, $name, $value ) { $this->attributes[ $name ] = $value; }
}
function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
require __DIR__ . '/../marrison-addon/includes/modules/class-marrison-addon-wrapped-link.php';
$module = new Marrison_Addon_Wrapped_Link();
check( empty( $scripts ), 'Constructing the module must not enqueue assets.' );

// Warm Elementor cache: HTML is returned without any per-element render hooks.
foreach ( $hooks['elementor/frontend/after_enqueue_scripts'] ?? [] as $callback ) { call_user_func( $callback ); }
check( isset( $scripts['marrison-addon'] ), 'Cached containers must retain their navigation runtime without before_render.' );
check( $scripts['marrison-addon']['footer'] && [] === $scripts['marrison-addon']['deps'], 'Keep the standalone footer runtime.' );
check( 'fixture-version' === $scripts['marrison-addon']['version'], 'Keep asset cache invalidation.' );

// Normal rendering still emits the same configuration and can enqueue by itself.
$scripts = [];
$element = new Wrapped_Link_Element();
$module->before_render( $element );
check( isset( $scripts['marrison-addon'] ), 'Uncached rendering must still enqueue the runtime.' );
check( json_decode( $element->attributes['data-marrison-addon'], true ) === $element->settings['marrison_addon_url'], 'Preserve URL configuration.' );
check( 'cursor: pointer;' === $element->attributes['style'], 'Preserve the clickable cursor.' );

foreach ( [ '', '#' ] as $url ) {
	$scripts = [];
	$element = new Wrapped_Link_Element();
	$element->settings['marrison_addon_url']['url'] = $url;
	$module->before_render( $element );
	check( ! $scripts && ! $element->attributes, 'Empty links must not activate an element.' );
}

$scripts = [];
$public = false;
$module->maybe_enqueue_scripts();
check( ! $scripts, 'No automatic runtime in editor/admin/AJAX contexts.' );
$editor = true;
$element = new Wrapped_Link_Element();
$module->before_render( $element );
check( ! $scripts && ! $element->attributes, 'Editor elements must remain editable.' );
echo "Wrapped Link: cached/uncached assets, URL output, empty links and editor isolation PASS.\n";
