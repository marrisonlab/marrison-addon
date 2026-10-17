<?php
/**
 * Real WordPress/Elementor CSS integration. Use a disposable database only.
 * MARRISON_TEST_WP_ROOT: WordPress source directory (with installed Elementor).
 * MARRISON_TEST_DB_HOST / MARRISON_TEST_DB_NAME: isolated test database.
 * Run once with --install, then without it. No production database or uploads used.
 */
$wp_root = getenv( 'MARRISON_TEST_WP_ROOT' );
$db_name = getenv( 'MARRISON_TEST_DB_NAME' );
if ( ! $wp_root || ! $db_name || ! preg_match( '/_test$/D', $db_name ) ) {
	exit( "Set MARRISON_TEST_WP_ROOT and a disposable MARRISON_TEST_DB_NAME ending in _test.\n" );
}
$install = in_array( '--install', $argv, true );
$formats_enabled = ! in_array( '--formats-off', $argv, true );
$workspace = dirname( __DIR__ );
$scratch = $workspace . '/.tmp-dynamic-background-db';
@mkdir( $scratch . '/uploads', 0777, true );
@mkdir( $scratch . '/themes/fixture', 0777, true );
file_put_contents( $scratch . '/themes/fixture/style.css', "/* Theme Name: CSS fixture */\n" );
file_put_contents( $scratch . '/themes/fixture/index.php', '<?php // Isolated integration fixture.' );
define( 'ABSPATH', rtrim( $wp_root, '/\\' ) . '/' );
define( 'DB_HOST', getenv( 'MARRISON_TEST_DB_HOST' ) ?: '127.0.0.1:13379' );
define( 'DB_NAME', $db_name );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'WP_CONTENT_DIR', $scratch );
define( 'WP_CONTENT_URL', 'https://fixture.example/wp-content' );
define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
define( 'WP_PLUGIN_URL', 'https://fixture.example/wp-content/plugins' );
define( 'WP_SITEURL', 'https://fixture.example' );
define( 'WP_HOME', 'https://fixture.example' );
define( 'WP_INSTALLING', $install );
define( 'DISABLE_WP_CRON', true );
define( 'WP_DEBUG', true );
define( 'AUTH_KEY', 'disposable-css-integration-fixture' );
define( 'SECURE_AUTH_KEY', AUTH_KEY );
define( 'LOGGED_IN_KEY', AUTH_KEY );
define( 'NONCE_KEY', AUTH_KEY );
$table_prefix = 'wp_';
$_SERVER['HTTP_HOST'] = 'fixture.example';
$_SERVER['SERVER_NAME'] = 'fixture.example';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SCRIPT_NAME'] = '/index.php';
function fixture_pre_filter( $tag, $callback, $args = 1 ) {
	$GLOBALS['wp_filter'][ $tag ][10][] = [ 'function' => $callback, 'accepted_args' => $args ];
}
fixture_pre_filter( 'pre_http_request', function () { return new WP_Error( 'fixture_http_blocked', 'External HTTP disabled.' ); } );
fixture_pre_filter( 'pre_option_active_plugins', function () use ( $install ) { return $install ? [] : [ 'elementor/elementor.php', 'jet-engine/jet-engine.php' ]; } );
fixture_pre_filter( 'pre_option_marrison_addon_modules', function () use ( $formats_enabled ) { return [ 'dynamic_background_image_size' => 1, 'image_sizes' => (int) $formats_enabled ]; } );
fixture_pre_filter( 'pre_option_template', function () { return 'fixture'; } );
fixture_pre_filter( 'pre_option_stylesheet', function () { return 'fixture'; } );
fixture_pre_filter( 'pre_option_marrison_addon_registered_size_webp', function () { return [ 'medium' => [ 'enabled' => 1 ], 'large' => [ 'enabled' => 1 ] ]; } );
fixture_pre_filter( 'upload_dir', function ( $dir ) use ( $scratch ) {
	return array_merge( $dir, [ 'basedir' => $scratch . '/uploads', 'path' => $scratch . '/uploads', 'baseurl' => 'https://fixture.example/uploads', 'url' => 'https://fixture.example/uploads', 'subdir' => '' ] );
} );
if ( ! $install ) {
	fixture_pre_filter( 'plugins_loaded', function () use ( $workspace ) { require_once $workspace . '/marrison-addon/marrison-addon.php'; marrison_addon_init(); } );
}
require ABSPATH . 'wp-settings.php';
if ( $install ) {
	require ABSPATH . 'wp-admin/includes/upgrade.php';
	if ( ! is_blog_installed() ) {
		wp_install( 'CSS fixture', 'fixture_admin', 'fixture@example.invalid', false, '', wp_generate_password() );
	}
	require_once WP_PLUGIN_DIR . '/jet-engine/jet-engine.php';
	jet_engine()->activation();
	exit( "Disposable WordPress database installed.\n" );
}

$assertions = 0;
function verify( $condition, $message ) {
	$GLOBALS['assertions']++;
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}

// Use real Data_Tag parsing with the same ID/URL contract as Elementor/ACF/JetEngine.
class Fixture_Background_Tag extends \Elementor\Core\DynamicTags\Data_Tag {
	public function get_name() { return 'fixture-background'; }
	public function get_title() { return 'Fixture background'; }
	public function get_group() { return 'site'; }
	public function get_categories() { return [ \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY ]; }
	protected function get_value( array $options = [] ) { return $GLOBALS['fixture_tag_value']; }
}
jet_engine()->dynamic_tags->init_module();
\Elementor\Plugin::$instance->dynamic_tags->register( new Fixture_Background_Tag() );
$GLOBALS['wpdb']->query( 'START TRANSACTION' );
register_shutdown_function( function () { $GLOBALS['wpdb']->query( 'ROLLBACK' ); } );
$uploads = wp_get_upload_dir();
$base = $uploads['baseurl'] . '/';
$metadata = [ 'file' => 'hero.jpg', 'width' => 1600, 'height' => 1000, 'sizes' => [
	'medium' => [ 'file' => 'hero-300x188.jpg', 'width' => 300, 'height' => 188, 'mime-type' => 'image/jpeg', 'sources' => [ 'image/webp' => [ 'file' => 'hero-300x188.webp', 'width' => 300, 'height' => 188 ] ] ],
	'large' => [ 'file' => 'hero-1024x640.jpg', 'width' => 1024, 'height' => 640, 'mime-type' => 'image/jpeg' ],
], 'marrison_webp_full' => [ 'file' => 'hero.webp', 'width' => 1600, 'height' => 1000 ] ];
foreach ( [ 'hero.jpg', 'hero.webp', 'hero-300x188.jpg', 'hero-300x188.webp', 'hero-1024x640.jpg' ] as $file ) { file_put_contents( $uploads['basedir'] . '/' . $file, 'fixture-existing-image' ); }
$id = wp_insert_attachment( [ 'post_title' => 'Fixture image', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'guid' => $base . 'hero.jpg' ], $uploads['basedir'] . '/hero.jpg' );
wp_update_attachment_metadata( $id, $metadata );
update_option( 'marrison_addon_registered_size_webp', [ 'medium' => [ 'enabled' => 1 ], 'large' => [ 'enabled' => 1 ] ] );
update_option( 'marrison_addon_full_replacement_size', 'medium' );
$page = wp_insert_post( [ 'post_title' => 'CSS fixture', 'post_type' => 'page', 'post_status' => 'publish' ] );
$tag = \Elementor\Plugin::$instance->dynamic_tags->tag_data_to_tag_text( 'fixture', 'fixture-background' );
function container_settings( $size = 'medium', $extra = [] ) {
	return array_merge( [ 'background_background' => 'classic', 'background_image' => [ 'url' => '', 'id' => '', 'size' => $size ], 'background_size' => 'cover', 'background_position' => 'center center', '__dynamic__' => [ 'background_image' => $GLOBALS['tag'] ] ], $extra );
}
function render_fixture_css( $settings, $value ) {
	$GLOBALS['fixture_tag_value'] = $value;
	$data = [ 'id' => 'fixture', 'elType' => 'container', 'settings' => $settings, 'elements' => [] ];
	$page = wp_insert_post( [ 'post_title' => 'Dynamic CSS fixture', 'post_type' => 'page', 'post_status' => 'publish' ] );
	foreach ( [ '_elementor_edit_mode' => 'builder', '_elementor_template_type' => 'wp-page', '_elementor_version' => ELEMENTOR_VERSION, '_elementor_data' => wp_slash( wp_json_encode( [ $data ] ) ) ] as $key => $value ) {
		update_post_meta( $page, $key, $value );
	}
	$GLOBALS['post'] = get_post( $page );
	$static = \Elementor\Core\Files\CSS\Post::create( $page );
	$static->update();
	verify( in_array( 'fixture', $static->get_meta( 'dynamic_elements_ids' ), true ), 'Static CSS generation must discover the dynamic container.' );
	$dynamic = new \Elementor\Core\DynamicTags\Dynamic_CSS( $page, $static );
	$css = $dynamic->get_content();
	verify( json_decode( get_post_meta( $page, '_elementor_data', true ), true )[0]['settings'] === $settings, 'CSS resolution must not modify saved Elementor settings.' );
	$element = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $data );
	return [ $css, $element, $static->get_content(), $page ];
}

$medium_file = $formats_enabled ? 'hero-300x188.webp' : 'hero-300x188.jpg';
$full_file = $formats_enabled ? 'hero.webp' : 'hero.jpg';
list( $css, $element ) = render_fixture_css( container_settings(), [ 'id' => $id, 'url' => $base . 'hero.jpg' ] );
verify( false !== strpos( $css, $medium_file ), 'Real dynamic CSS must use the selected Medium variant.' );
verify( false === strpos( $css, 'hero.webp' ), 'Medium must not use the full variant.' );
do_action( 'elementor/frontend/container/before_render', $element );
verify( false !== strpos( $element->get_render_attribute_string( '_wrapper' ), 'data-marrison-dynamic-bg-size="1"' ), 'Rendered marker must prevent automatic browser size selection.' );
list( $css ) = render_fixture_css( container_settings( 'large' ), [ 'url' => $base . 'hero.jpg?cache=1' ] );
verify( false !== strpos( $css, 'hero-1024x640.jpg' ), 'URL-only dynamic values must resolve their local attachment.' );
list( $css ) = render_fixture_css( container_settings( '' ), [ 'id' => $id, 'url' => $base . 'hero.jpg' ] );
verify( false !== strpos( $css, $full_file ) && false === strpos( $css, '300x188' ), 'Full must ignore the global Medium replacement.' );
list( $css ) = render_fixture_css( container_settings( 'nonexistent' ), [ 'id' => $id, 'url' => $base . 'hero.jpg' ] );
verify( false !== strpos( $css, 'hero.jpg' ), 'Missing size must fall back to the original.' );
list( $css ) = render_fixture_css( container_settings(), [ 'url' => 'https://external.example/hero.jpg' ] );
verify( false !== strpos( $css, 'https://external.example/hero.jpg' ), 'Unresolved external URL must stay unchanged.' );

// Responsive and overlay CSS must retain Elementor's own selectors/media queries.
$responsive = container_settings( 'medium', [
	'background_image_mobile' => [ 'size' => 'large' ],
	'background_position_mobile' => 'bottom right',
	'background_overlay_background' => 'classic',
	'background_overlay_image' => [ 'size' => 'large' ],
	'background_overlay_opacity' => [ 'size' => 0.4 ],
	'__dynamic__' => [ 'background_image' => $tag, 'background_image_mobile' => $tag, 'background_overlay_image' => $tag ],
] );
list( $css, , $static_css ) = render_fixture_css( $responsive, [ 'id' => $id, 'url' => $base . 'hero.jpg' ] );
verify( false !== strpos( $css, $medium_file ) && false !== strpos( $css, 'hero-1024x640.jpg' ), 'Each dynamic responsive/overlay control must retain its own selected size.' );
verify( false !== strpos( $css, '@media' ) && false !== strpos( $css, ':before' ), 'Native responsive and overlay selectors must survive.' );
verify( false !== strpos( $static_css, 'background-size:cover' ) && false !== strpos( $static_css, 'bottom right' ), 'Native cover and responsive position must survive static CSS generation.' );
verify( false === strpos( $static_css, 'hero-' ), 'Context-dependent image URLs must stay outside the persisted static stylesheet.' );

// The existing final HTML rewriter must not change the fallback/full resolution.
if ( $formats_enabled ) {
	$format_module = $GLOBALS['wp_filter']['marrison_addon/image_sizes/selected_background_url']->callbacks[10];
	$format_module = reset( $format_module )['function'][0];
	list( $css ) = render_fixture_css( container_settings( 'nonexistent' ), [ 'id' => $id, 'url' => $base . 'hero.jpg' ] );
	$rewritten = $format_module->rewrite_frontend_webp_urls( '<html><head></head><body><style>' . $css . '</style></body></html>' );
	verify( false !== strpos( $rewritten, $base . 'hero.jpg' ), 'Final HTML rewriting must preserve original fallback dimensions.' );
	$hero_settings = apply_filters( 'marrison_addon/image_sizes/lcp_background_settings', $element->get_settings_for_display(), $element );
	verify( $base . $medium_file === $hero_settings['background_image']['url'], 'Hero preloads must share the native selected size.' );
	$_SERVER['HTTP_ACCEPT'] = 'image/avif,image/webp';
	list( $css ) = render_fixture_css( container_settings(), [ 'id' => $id, 'url' => $base . 'hero.jpg' ] );
	verify( false !== strpos( $css, $medium_file ), 'CSS output must not change with the generating request Accept header.' );
}

unlink( $uploads['basedir'] . '/hero-1024x640.jpg' );
list( $css ) = render_fixture_css( container_settings( 'large' ), [ 'id' => $id, 'url' => $base . 'hero.jpg' ] );
verify( false !== strpos( $css, 'hero.jpg' ), 'A deleted intermediate file must fall back to the original.' );
file_put_contents( $uploads['basedir'] . '/hero-1024x640.jpg', 'fixture-existing-image' );

// Real JetEngine Custom Image tag, resolving the current listing object's meta.
update_post_meta( $page, '_fixture_image', $id );
jet_engine()->listings->data->set_current_object( get_post( $page ) );
$jet_tag = \Elementor\Plugin::$instance->dynamic_tags->tag_data_to_tag_text( 'jetfixture', 'jet-post-custom-image', [ 'img_field' => '_fixture_image', 'object_context' => 'default_object' ] );
list( $css, , , $jet_template ) = render_fixture_css( container_settings( 'medium', [ '__dynamic__' => [ 'background_image' => $jet_tag ] ] ), [] );
verify( false !== strpos( $css, $medium_file ), 'JetEngine Custom Image must use the selected Medium variant.' );

// A cached listing template must resolve the image separately for each current object.
$first_listing = new Jet_Engine_Elementor_Dynamic_CSS( $page, $jet_template );
$first_listing->set_listing_unique_selector( '.listing-first' );
verify( false !== strpos( $first_listing->get_content(), $medium_file ), 'JetEngine listing CSS must retain the selected size.' );
file_put_contents( $uploads['basedir'] . '/second.jpg', 'fixture-existing-image' );
file_put_contents( $uploads['basedir'] . '/second-300x188.jpg', 'fixture-existing-image' );
$second_id = wp_insert_attachment( [ 'post_title' => 'Second image', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ], $uploads['basedir'] . '/second.jpg' );
wp_update_attachment_metadata( $second_id, [ 'file' => 'second.jpg', 'width' => 1600, 'height' => 1000, 'sizes' => [ 'medium' => [ 'file' => 'second-300x188.jpg', 'width' => 300, 'height' => 188, 'mime-type' => 'image/jpeg' ] ] ] );
update_post_meta( $jet_template, '_fixture_image', $second_id );
jet_engine()->listings->data->set_current_object( get_post( $jet_template ) );
$second_listing = new Jet_Engine_Elementor_Dynamic_CSS( $jet_template, $jet_template );
$second_listing->set_listing_unique_selector( '.listing-second' );
$listing_css = $second_listing->get_content();
verify( false !== strpos( $listing_css, 'second-300x188.jpg' ) && false === strpos( $listing_css, $medium_file ), 'A reused listing template must not retain the previous object image.' );

// CSS regeneration happens only after a version change, not on every request.
$background_module = $GLOBALS['wp_filter']['elementor/files/css/property']->callbacks[5];
$background_module = reset( $background_module )['function'][0];
$purges = 0;
add_action( 'elementor/core/files/clear_cache', function () use ( &$purges ) { $purges++; } );
$background_module->maybe_clear_css_cache();
verify( 0 === $purges, 'An unchanged cache version must not purge CSS again.' );
update_option( Marrison_Addon_Dynamic_Background_Image_Size::CACHE_VERSION_OPTION, 'stale' );
$background_module->maybe_clear_css_cache();
verify( 1 === $purges, 'A changed cache version must purge Elementor CSS once.' );
$background_module->maybe_clear_css_cache();
verify( 1 === $purges, 'The new cache version must prevent repeated purges.' );

// Exercise the actual dashboard endpoint for both state changes, including ON after OFF.
define( 'DOING_AJAX', true );
wp_set_current_user( 1 );
remove_all_filters( 'pre_option_marrison_addon_modules' );
update_option( 'marrison_addon_modules', [ 'dynamic_background_image_size' => 1, 'image_sizes' => (int) $formats_enabled ] );
class Fixture_Ajax_Complete extends RuntimeException {}
add_filter( 'wp_die_ajax_handler', function () { return function () { throw new Fixture_Ajax_Complete(); }; } );
$admin = new Marrison_Addon_Admin();
foreach ( [ '0', '1' ] as $enabled ) {
	$_POST = [ 'option_name' => 'marrison_addon_modules', 'key' => 'dynamic_background_image_size', 'value' => $enabled, 'nonce' => wp_create_nonce( 'marrison_save_option_nonce' ) ];
	$_REQUEST = $_POST;
	$before = $purges;
	ob_start();
	try { $admin->ajax_save_option(); } catch ( Fixture_Ajax_Complete $complete ) {}
	$response = json_decode( ob_get_clean(), true );
	verify( ! empty( $response['success'] ), 'The dashboard toggle must succeed.' );
	verify( (int) $enabled === get_option( 'marrison_addon_modules' )['dynamic_background_image_size'], 'The dashboard must save the actual selected state.' );
	verify( $purges === $before + 1, 'Both dashboard state changes must invalidate Elementor CSS.' );
}

echo 'Elementor ' . ELEMENTOR_VERSION . '; WordPress ' . $GLOBALS['wp_version'] . '; formats ' . ( $formats_enabled ? 'ON' : 'OFF' ) . '; real dynamic CSS: ' . $assertions . " assertions PASS\n";
