<?php
/** Real Elementor base classes and JetEngine Dynamic Field renderer, isolated WP adapters. */
require __DIR__ . '/business-info-php-test.php';
define( 'WPINC', 'wp-includes' );
function wp_parse_args( $args, $defaults = [] ) { return array_merge( $defaults, $args ); }
function wp_kses_post( $value ) { return $value; }
$plugin_root = ABSPATH . 'wp-content/plugins/';
$el = $plugin_root . 'elementor/';
if ( ! is_file( $el . 'includes/autoloader.php' ) ) { throw new RuntimeException( 'This test requires installed Elementor sources.' ); }
require $el . 'includes/autoloader.php';
require $el . 'includes/managers/controls.php';
\Elementor\Autoloader::run( $el );
// Explicit files keep this source-level fixture independent of Elementor's plugin bootstrap.
require_once $el . 'core/base/base-object.php';
require_once $el . 'includes/base/controls-stack.php';
require_once $el . 'core/dynamic-tags/base-tag.php';
require_once $el . 'core/dynamic-tags/tag.php';
require_once $el . 'core/dynamic-tags/data-tag.php';
require_once $el . 'includes/utils.php';
require_once $el . 'includes/base/element-base.php';
require_once $el . 'includes/base/widget-base.php';
$manager = new class {
	public $tags = [];
	public function register_group( $name, $args ) {}
	public function register( $tag ) { $this->tags[ $tag->get_name() ] = $tag; }
};
$module->tags( $manager );
if ( ! $manager->tags ) { throw new RuntimeException( 'Elementor load: ' . $el . ' Tag=' . (int) class_exists( '\Elementor\Core\DynamicTags\Tag' ) . ' Data=' . (int) class_exists( '\Elementor\Core\DynamicTags\Data_Tag' ) ); }
verify( count( $manager->tags ) === 4, 'Real Elementor tag registration failed' );
$options['marrison_business_info'] = [ 'company' => [ 'legal_name' => 'Native Elementor & JetEngine', 'logo' => 123 ], 'contacts' => [ [ 'id' => 'native-contact', 'key' => 'email_principale', 'label' => 'Email principale', 'type' => 'email', 'value' => 'first@example.test' ] ] ];
$settings = new ReflectionProperty( '\Elementor\Core\Base\Base_Object', 'settings' ); if ( PHP_VERSION_ID < 80100 ) { $settings->setAccessible( true ); }
$text = $manager->tags['marrison-business-info-text']; $settings->setValue( $text, [ 'field' => 'contacts.email_principale' ] );
verify( $text->get_content() === 'first@example.test', 'Real text Dynamic Tag render failed' );
$options['marrison_business_info']['contacts'][0]['value'] = 'second@example.test';
verify( $text->get_content() === 'second@example.test', 'Same tag instance retained a stale value' );
$url = $manager->tags['marrison-business-info-url']; $settings->setValue( $url, [ 'field' => 'contacts.email_principale' ] );
verify( $url->get_content() === 'mailto:second@example.test', 'Real URL category failed' );
$image = $manager->tags['marrison-business-info-image']; $settings->setValue( $image, [ 'field' => 'logo' ] );
verify( $image->get_content()['id'] === 123 && $image->get_content()['url'] === 'https://example.test/logo.png', 'Real image Data_Tag contract failed' );
require $root . 'business-info/widgets/business-hours-widget.php';
// Use the native Widget_Base ancestry, replacing only display settings to avoid an editor/database bootstrap.
class Native_Business_Hours_Fixture extends \Marrison_Addon\Modules\Business_Info\Widgets\Business_Hours_Widget {
	public function get_settings_for_display( $setting_key = null ) { return [ 'layout' => 'compact', 'group_days' => 'yes' ]; }
}
$widget = ( new ReflectionClass( 'Native_Business_Hours_Fixture' ) )->newInstanceWithoutConstructor();
$render = new ReflectionMethod( $widget, 'render' ); if ( PHP_VERSION_ID < 80100 ) { $render->setAccessible( true ); }
ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
verify( strpos( $html, 'marrison-bh--compact' ) !== false && strpos( $html, 'data-state="unknown"' ) !== false, 'Real widget render failed' );

$jet_root = $plugin_root . 'jet-engine/includes/';
foreach ( [ 'traits/setup-listing.php', 'modules/performance/inc/traits/prevent-wrap.php', 'classes/tools.php', 'components/listings/render/base.php', 'components/listings/render/dynamic-field.php' ] as $path ) { require_once $jet_root . $path; }
$renderer = new Jet_Engine_Render_Dynamic_Field( [ 'dynamic_field_source' => 'marrison_business_info:contacts.email_principale' ] );
verify( $renderer->get_field_content( $renderer->get_settings() ) === 'second@example.test', 'Native JetEngine renderer did not route custom source' );
$options['marrison_business_info']['contacts'][0]['value'] = 'third@example.test';
verify( $renderer->get_field_content( $renderer->get_settings() ) === 'third@example.test', 'Native JetEngine renderer retained a stale value' );
echo "PASS native integration: Elementor Tag/Data_Tag categories and live reads, Widget_Base rendering, JetEngine Dynamic Field source and live reads. Editor styling still requires an installed-site visual check.\n";
