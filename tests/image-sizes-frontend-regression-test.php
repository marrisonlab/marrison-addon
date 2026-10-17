<?php
/** Run: php tests/image-sizes-frontend-regression-test.php (isolated WordPress/Elementor contracts). */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_DEBUG', false );
$root = sys_get_temp_dir() . '/marrison-frontend-' . uniqid();
mkdir( $root . '/2026/09', 0777, true );
$base = 'https://fixture.example/wp-content/uploads/2026/09/';
$options = [
	'marrison_addon_image_sizes' => [
		[ 'slug' => 'cover', 'webp' => true, 'width' => 1440, 'height' => 550 ],
		[ 'slug' => 'thumbnail', 'webp' => true, 'width' => 150, 'height' => 150 ],
	],
	'marrison_addon_full_replacement_size' => 'cover',
];
$metadata = [
	'file' => '2026/09/hero.png', 'width' => 2000, 'height' => 1333,
	'marrison_webp_full' => [ 'file' => 'hero.webp', 'width' => 2000, 'height' => 1333 ],
	'sizes' => [
		'cover' => [ 'file' => 'hero-1440x550.png', 'width' => 1440, 'height' => 550, 'sources' => [
			'image/avif' => [ 'file' => 'hero-1440x550.avif', 'width' => 1440, 'height' => 550 ],
			'image/webp' => [ 'file' => 'hero-1440x550.webp', 'width' => 1440, 'height' => 550 ],
		] ],
		'thumbnail' => [ 'file' => 'hero-150x150.webp', 'width' => 150, 'height' => 150, 'mime-type' => 'image/webp' ],
	],
];
$admin = true;
$editor = false;
$saved_metadata = null;
class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
class Json_Response extends Exception {
	public $success;
	public $data;
	public function __construct( $success, $data ) { $this->success = $success; $this->data = $data; }
}
class Marrison_Addon_Context {
	public static function is_public_frontend_request() { return ! $GLOBALS['admin'] && ! $GLOBALS['editor']; }
	public static function is_elementor_editor_context() { return $GLOBALS['editor']; }
}
class Hero_Container {
	private $settings;
	public function __construct( $settings ) { $this->settings = $settings; }
	public function get_settings_for_display() { return $this->settings; }
	public function add_render_attribute() {}
}
class Elementor_Document_Fixture {
	private $id;
	public function __construct( $id ) { $this->id = $id; }
	public function get_elements_data() {
		$GLOBALS['elementor_layout_reads'][ $this->id ] = ( $GLOBALS['elementor_layout_reads'][ $this->id ] ?? 0 ) + 1;
		return $GLOBALS['elementor_layouts'][ $this->id ];
	}
}
class Elementor_Documents_Fixture {
	public function get_doc_for_frontend( $id ) {
		$GLOBALS['elementor_document_reads'][ $id ] = ( $GLOBALS['elementor_document_reads'][ $id ] ?? 0 ) + 1;
		return isset( $GLOBALS['elementor_layouts'][ $id ] ) ? new Elementor_Document_Fixture( $id ) : false;
	}
}
class Elementor_Plugin_Fixture { public static $instance; }
class Elementor_Breakpoint_Fixture {
	private $value;
	private $direction;
	public function __construct( $value, $direction ) { $this->value = $value; $this->direction = $direction; }
	public function get_value() { return $this->value; }
	public function get_direction() { return $this->direction; }
}
class Elementor_Breakpoints_Fixture {
	public function get_active_breakpoints() {
		$result = [];
		foreach ( $GLOBALS['active_breakpoints'] as $device => $settings ) {
			$result[ $device ] = new Elementor_Breakpoint_Fixture( $settings[0], $settings[1] );
		}
		return $result;
	}
}
class_alias( 'Elementor_Plugin_Fixture', 'Elementor\\Plugin' );
Elementor_Plugin_Fixture::$instance = (object) [ 'documents' => new Elementor_Documents_Fixture(), 'breakpoints' => new Elementor_Breakpoints_Fixture() ];
$active_breakpoints = [ 'mobile' => [767, 'max'], 'tablet' => [1024, 'max'] ];
$elementor_layouts = [];
$elementor_layout_reads = [];
$elementor_document_reads = [];
function get_option( $key, $default = [] ) { return isset( $GLOBALS['options'][ $key ] ) ? $GLOBALS['options'][ $key ] : $default; }
function apply_filters( $tag, $value, ...$args ) { return $value; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function wp_generate_uuid4() { return uniqid( 'revision-', true ); }
function wp_get_registered_image_subsizes() { return []; }
function wp_get_attachment_metadata( $id ) { return $GLOBALS['metadata']; }
function wp_update_attachment_metadata( $id, $data ) { $GLOBALS['saved_metadata'] = $data; return $id; }
function wp_generate_attachment_metadata( $id, $file ) { return $GLOBALS['metadata']; }
function wp_get_upload_dir() { return [ 'basedir' => $GLOBALS['root'], 'baseurl' => 'https://fixture.example/wp-content/uploads' ]; }
function get_attached_file( $id ) { return $GLOBALS['root'] . '/2026/09/hero.png'; }
function attachment_url_to_postid( $url ) { return false !== strpos( $url, '/hero' ) ? 2855 : 0; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function is_admin() { return $GLOBALS['admin']; }
function wp_doing_ajax() { return false; }
function is_feed() { return false; }
function is_robots() { return false; }
function is_trackback() { return false; }
function absint( $value ) { return abs( (int) $value ); }
function esc_url( $url ) { return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function wp_get_attachment_url( $id ) { return $GLOBALS['base'] . basename( $GLOBALS['metadata']['file'] ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function check_ajax_referer() {}
function current_user_can() { return true; }
function wp_send_json_error( $data ) { throw new Json_Response( false, $data ); }
function wp_send_json_success( $data ) { throw new Json_Response( true, $data ); }
function wp_image_editor_supports() { return false; }
function wp_get_attachment_image_url( $id, $size ) {
	$source = $GLOBALS['module']->serve_webp_downsize( false, $id, $size );
	if ( $source ) { return $source[0]; }
	$data = $GLOBALS['metadata'];
	return $GLOBALS['base'] . ( isset( $data['sizes'][ $size ] ) ? $data['sizes'][ $size ]['file'] : basename( $data['file'] ) );
}
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function lcp_preloads( $html ) {
	preg_match_all( '~<link\b[^>]*rel="preload"[^>]*>~i', $html, $links );
	$preloads = [];
	foreach ( $links[0] as $link ) {
		preg_match( '~href="([^"]+)"~', $link, $href );
		preg_match( '~media="([^"]+)"~', $link, $media );
		$preloads[] = [ 'url' => html_entity_decode( $href[1], ENT_QUOTES, 'UTF-8' ), 'media' => isset( $media[1] ) ? html_entity_decode( $media[1], ENT_QUOTES, 'UTF-8' ) : '' ];
	}
	return $preloads;
}
function invoke( $method, array $args = [] ) {
	$reflection = new ReflectionMethod( 'Marrison_Addon_Image_Sizes', $method );
	if ( PHP_VERSION_ID < 80100 ) { $reflection->setAccessible( true ); }
	return $reflection->invokeArgs( $GLOBALS['module'], $args );
}
function new_module() { return ( new ReflectionClass( 'Marrison_Addon_Image_Sizes' ) )->newInstanceWithoutConstructor(); }
// Model the actual Elementor media-control contract: a nonempty ID overrides URL.
function elementor_css_url( $value ) { return ! empty( $value['id'] ) ? wp_get_attachment_image_url( $value['id'], $value['size'] ) : $value['url']; }
require __DIR__ . '/../marrison-addon/includes/modules/class-marrison-addon-image-sizes.php';
$module = new_module();
$initial_metadata = $metadata;
try {
	foreach ( [ 'hero.png', 'hero.webp', 'hero-1440x550.png', 'hero-1440x550.webp', 'hero-150x150.webp' ] as $file ) {
		file_put_contents( $root . '/2026/09/' . $file, 'file-existence fixture' );
	}
	$input = [ 'id' => 2855, 'url' => $base . 'hero.png', 'size' => 'full' ];
	$_SERVER['HTTP_ACCEPT'] = 'image/avif,image/webp';
	$css = $module->filter_elementor_css_property_value( $input, 'URL', [], [ 'type' => 'media' ] );
	check( elementor_css_url( $css ) === $base . 'hero-1440x550.webp', 'Admin CSS must use the generated WebP despite Elementor resolving media IDs.' );
	check( $input['id'] === 2855, 'Stored settings must keep their media ID.' );
	file_put_contents( $root . '/2026/09/hero-1440x550.avif', 'existing avif' );
	$module = new_module();
	$css = $module->filter_elementor_css_property_value( $input, 'URL', [], [] );
	check( elementor_css_url( $css ) === $base . 'hero-1440x550.webp', 'Shared CSS must remain deterministic with an AVIF Accept header.' );
	$thumbnail = $input;
	$thumbnail['size'] = 'thumbnail';
	$css = $module->filter_elementor_css_property_value( $thumbnail, 'URL', [], [] );
	check( elementor_css_url( $css ) === $base . 'hero-150x150.webp', 'Native WebP thumbnails must respect Elementor selected size.' );
	check( invoke( 'get_lcp_preload_url_from_container_settings', [ [ 'background_image' => $thumbnail ] ] ) === elementor_css_url( $css ), 'Preload must match the selected CSS size.' );
	$admin = false;
	$module = new_module();
	check( $module->serve_webp_downsize( false, 2855, 'full' )[0] === $base . 'hero-1440x550.avif', 'Existing AVIF must remain available on the public frontend.' );
	unlink( $root . '/2026/09/hero-1440x550.avif' );
	$module = new_module();
	check( $module->serve_webp_downsize( false, 2855, 'full' )[0] === $base . 'hero-1440x550.webp', 'Missing AVIF must fall back to an existing WebP.' );
	$sources = [ 2000 => [ 'url' => $base . 'hero.png', 'descriptor' => 'w', 'value' => 2000 ] ];
	$result = $module->serve_webp_srcset( $sources, [], '', $metadata, 2855 );
	check( $result[2000]['url'] === $base . 'hero.webp' && $result[2000]['value'] === 2000, 'Srcset must keep a real 2000px full candidate rather than a 1440px crop.' );
	$html = '<html><head></head><body><img srcset="' . $base . 'hero.png 2000w, ' . $base . 'hero-150x150.webp 150w"><div style="background-image:url(' . $base . 'hero.png)"></div></body></html>';
	$result = $module->rewrite_frontend_webp_urls( $html );
	check( false !== strpos( $result, 'hero.webp 2000w' ) && false === strpos( $result, 'hero-1440x550.webp 2000w' ), 'HTML rewrite must preserve srcset geometry.' );
	check( false !== strpos( $result, 'background-image:url(' . $base . 'hero-1440x550.webp)' ), 'Inline CSS must use the same deterministic format as generated CSS.' );
	$outside = '<div data-marrison-lcp-hero="1"><div><h1>Hero</h1></div></div><section><img loading="lazy" src="card.webp"></section>';
	check( invoke( 'optimize_lcp_hero_internal_images', [ $outside ] ) === $outside, 'An image outside a hero must keep its lazy loading.' );
	$section = '<section data-marrison-lcp-hero="1"><div><a href="/contact"><img loading="lazy" src="hero.webp"></a></div></section><img loading="lazy" src="outside.webp">';
	check( substr_count( invoke( 'optimize_lcp_hero_internal_images', [ $section ] ), 'fetchpriority="high"' ) === 1, 'Elementor semantic container tags must keep LCP behavior.' );
	$inside = '<div data-marrison-lcp-hero="1"><script>var html="<div><img src=\'fake.webp\'>";</script><!-- <img> --><div><img loading="lazy" src="hero.webp"><img loading="lazy" src="second.webp"></div></div><img loading="lazy" src="outside.webp">';
	$result = invoke( 'optimize_lcp_hero_internal_images', [ $inside ] );
	check( substr_count( $result, 'fetchpriority="high"' ) === 1 && substr_count( $result, 'loading="lazy"' ) === 2, 'Only the first real image in the marked subtree receives LCP priority.' );
	$empty_hero = '<div data-marrison-lcp-hero="1"><h1>Hero</h1></div><div style="background-image:url(' . $base . 'hero.png)"></div>';
	check( invoke( 'find_lcp_hero_background_preload_urls', [ $empty_hero ] ) === [], 'Background preloads must not cross the hero closing tag.' );
	$module = new_module();
	ob_start();
	$module->before_render_lcp_hero_container( new Hero_Container( [ 'marrison_image_lcp_hero' => 'yes', 'background_image' => $input ] ) );
	check( ob_get_clean() === '', 'Container rendering must not emit a duplicate body preload.' );
	$module->after_render_lcp_hero_container();
	$result = $module->rewrite_frontend_webp_urls( '<html><head></head><body><div data-marrison-lcp-hero="1"></div></body></html>' );
	check( substr_count( $result, 'rel="preload"' ) === 1 && strpos( $result, 'rel="preload"' ) < strpos( $result, '</head>' ), 'A single LCP preload must appear in the head.' );
	$existing = '<html><head><link href=\'' . $base . 'hero-1440x550.webp\' as=\'image\' rel=\'preload\'></head><body><div data-marrison-lcp-hero="1"></div></body></html>';
	check( substr_count( $module->rewrite_frontend_webp_urls( $existing ), 'preload' ) === 1, 'Existing preloads must be deduplicated with either quote and attribute order.' );
	// Elementor cache hits return static container HTML without running before_render.
	$elementor_layouts[50] = [ [ 'id' => 'outer', 'settings' => [], 'elements' => [
		[ 'id' => 'ca4fe83', 'settings' => [ 'marrison_image_lcp_hero' => 'yes', 'background_image' => $input ], 'elements' => [] ],
		[ 'id' => 'outside', 'settings' => [ 'background_image' => $thumbnail ], 'elements' => [] ],
	] ] ];
	$cached_hero = '<html><head><link rel="stylesheet" href="' . $base . 'elementor/css/post-50.css"></head><body><main data-elementor-id="50"><section data-marrison-lcp-hero="1" data-id="ca4fe83" data-settings="{&quot;background_background&quot;:&quot;classic&quot;}"><h1>Hero cached by Elementor</h1></section></main></body></html>';
	$module = new_module();
	$result = $module->rewrite_frontend_webp_urls( $cached_hero );
	check( substr_count( $result, 'rel="preload"' ) === 1 && false !== strpos( $result, 'href="' . $base . 'hero-1440x550.webp" fetchpriority="high"' ) && strpos( $result, 'rel="preload"' ) < strpos( $result, '</head>' ), 'Cached Elementor containers with external CSS must preload the selected WebP in the head without render hooks.' );
	$module = new_module();
	$cached_existing = str_replace( '</head>', '<link href="' . $base . 'hero-1440x550.webp" rel="preload" as="image" fetchpriority="high"></head>', $cached_hero );
	check( substr_count( $module->rewrite_frontend_webp_urls( $cached_existing ), 'rel="preload"' ) === 1, 'Cached-container fallback must deduplicate an existing head preload.' );
	$elementor_layouts[50][0]['elements'][0] = [ 'id' => 'ca4fe83', 'settings' => [ 'marrison_image_lcp_hero' => 'yes' ], 'elements' => [
		[ 'id' => 'emptychild', 'settings' => [], 'elements' => [] ],
		[ 'id' => 'backgroundchild', 'settings' => [ 'background_image' => $input ], 'elements' => [] ],
	] ];
	$module = new_module();
	$result = $module->rewrite_frontend_webp_urls( $cached_hero );
	check( false !== strpos( $result, 'href="' . $base . 'hero-1440x550.webp"' ) && false === strpos( $result, 'href="' . $base . 'hero-150x150.webp"' ), 'Cached hero fallback must find a descendant background without using a sibling outside the hero.' );
	$elementor_layouts[50][0]['elements'][0]['elements'] = [];
	$module = new_module();
	check( false === strpos( $module->rewrite_frontend_webp_urls( $cached_hero ), 'rel="preload"' ), 'An empty cached hero must not preload an outside sibling background.' );
	$elementor_layouts[50][0]['elements'][0]['settings']['background_image'] = $input;
	$elementor_layouts[728] = [ [ 'id' => 'ca4fe83', 'settings' => [ 'marrison_image_lcp_hero' => 'yes', 'background_image' => $thumbnail ], 'elements' => [] ] ];
	$colliding_ids = '<html><head></head><body><main data-elementor-id="50"><header data-elementor-id="728"><div data-marrison-lcp-hero="1" data-id="ca4fe83"></div></header><section data-marrison-lcp-hero="1" data-id="ca4fe83"></section></main></body></html>';
	$module = new_module();
	$result = $module->rewrite_frontend_webp_urls( $colliding_ids );
	check( substr_count( $result, 'rel="preload"' ) === 2 && false !== strpos( $result, 'href="' . $base . 'hero-150x150.webp"' ) && false !== strpos( $result, 'href="' . $base . 'hero-1440x550.webp"' ), 'Identical element IDs in nested template/page documents must resolve their own backgrounds.' );
	$elementor_layouts[50][0]['elements'][1] = [ 'id' => 'secondhero', 'settings' => [ 'marrison_image_lcp_hero' => 'yes', 'background_image' => $thumbnail ], 'elements' => [] ];
	$two_heroes = str_replace( '</main>', '<div data-marrison-lcp-hero="1" data-id="secondhero"></div></main>', $cached_hero );
	$reads_before = $elementor_layout_reads[50];
	$module = new_module();
	check( substr_count( $module->rewrite_frontend_webp_urls( $two_heroes ), 'rel="preload"' ) === 2 && $elementor_layout_reads[50] === $reads_before + 1, 'Multiple cached heroes must read their document layout once per request.' );
	$missing_document = str_replace( 'data-elementor-id="50"', 'data-elementor-id="999"', $two_heroes );
	$module = new_module();
	check( false === strpos( $module->rewrite_frontend_webp_urls( $missing_document ), 'rel="preload"' ) && $elementor_document_reads[999] === 1, 'Missing documents must be remembered once and produce no invented preload.' );
	$missing_node = str_replace( 'data-id="ca4fe83"', 'data-id="absent"', $cached_hero );
	$module = new_module();
	check( false === strpos( $module->rewrite_frontend_webp_urls( $missing_node ), 'rel="preload"' ), 'A missing cached element must not borrow another element background.' );
	$elementor_layouts[50][0]['elements'][0]['settings']['__dynamic__'] = [ 'background_image' => '[unresolved-elementor-tag]' ];
	$module = new_module();
	check( false === strpos( $module->rewrite_frontend_webp_urls( $cached_hero ), 'rel="preload"' ), 'Unresolved dynamic backgrounds must not preload a stale saved fallback.' );
	unset( $elementor_layouts[50][0]['elements'][0]['settings']['__dynamic__'] );
	$elementor_layouts[50][0]['elements'][0]['settings']['marrison_image_lcp_hero'] = '';
	$module = new_module();
	check( false === strpos( $module->rewrite_frontend_webp_urls( $cached_hero ), 'rel="preload"' ), 'A stale cached marker must not restore a disabled document LCP setting.' );
	$elementor_layouts[50][0]['elements'][0]['settings']['marrison_image_lcp_hero'] = 'yes';
	$responsive_options = $options;
	$responsive_metadata = $metadata;
	$responsive_settings = $elementor_layouts[50][0]['elements'][0]['settings'];
	$options['marrison_addon_image_sizes'][] = [ 'slug' => 'portrait', 'webp' => true, 'width' => 767, 'height' => 1024 ];
	$metadata['sizes']['portrait'] = [ 'file' => 'hero-767x1024.webp', 'width' => 767, 'height' => 1024, 'mime-type' => 'image/webp' ];
	file_put_contents( $root . '/2026/09/hero-767x1024.webp', 'file-existence fixture' );
	$portrait = [ 'id' => 2855, 'url' => $base . 'hero.png', 'size' => 'portrait' ];
	$elementor_layouts[50][0]['elements'][0]['settings']['background_image_tablet'] = $thumbnail;
	$elementor_layouts[50][0]['elements'][0]['settings']['background_image_mobile'] = $portrait;
	$module = new_module();
	$responsive_result = $module->rewrite_frontend_webp_urls( $cached_hero );
	check( substr_count( $responsive_result, 'rel="preload"' ) === 3 && substr_count( $responsive_result, 'media="' ) === 3 && false !== strpos( $responsive_result, 'href="' . $base . 'hero-767x1024.webp"' ), 'Responsive cached heroes must preload the matching desktop, tablet and mobile variants with disjoint media conditions.' );
	$responsive_hero_settings = $elementor_layouts[50][0]['elements'][0]['settings'];
	$module = new_module();
	$module->before_render_lcp_hero_container( new Hero_Container( $responsive_hero_settings ) );
	$module->after_render_lcp_hero_container();
	check( lcp_preloads( $module->rewrite_frontend_webp_urls( $cached_hero ) ) === lcp_preloads( $responsive_result ), 'Fresh and cached Elementor renders must produce identical responsive preloads.' );
	$viewport_fixtures = [ 'standard' => [ 'preloads' => lcp_preloads( $responsive_result ), 'css' => [
		[ 'url' => $base . 'hero-1440x550.webp', 'media' => '' ],
		[ 'url' => $base . 'hero-150x150.webp', 'media' => '(max-width:1024px)' ],
		[ 'url' => $base . 'hero-767x1024.webp', 'media' => '(max-width:767px)' ],
	] ] ];
	$module = new_module();
	$with_existing = str_replace( '</head>', '<link href="' . $base . 'hero-1440x550.webp" rel="preload" as="image" crossorigin="anonymous" data-retained="yes"></head>', $cached_hero );
	$existing_responsive = $module->rewrite_frontend_webp_urls( $with_existing );
	check( count( lcp_preloads( $existing_responsive ) ) === 3 && substr_count( $existing_responsive, 'media="' ) === 3 && false !== strpos( $existing_responsive, 'crossorigin="anonymous" data-retained="yes"' ), 'Existing desktop preloads must receive their responsive media without duplicates or lost attributes.' );
	$missing_mobile = $responsive_hero_settings;
	unset( $missing_mobile['background_image_mobile'] );
	$missing_mobile['background_image_mobile_extra'] = $portrait;
	$module = new_module();
	$profiles = invoke( 'get_responsive_lcp_preloads_from_settings', [ $missing_mobile ] );
	check( count( $profiles ) === 2 && $profiles[1] === [ 'url' => $base . 'hero-150x150.webp', 'media' => '(width <= 1024px)' ], 'Missing mobile overrides must inherit tablet, and inactive custom breakpoints must be ignored.' );
	$empty_tablet = $responsive_hero_settings;
	$empty_tablet['background_image_tablet'] = [ 'id' => 0, 'url' => '' ];
	$module = new_module();
	$profiles = invoke( 'get_responsive_lcp_preloads_from_settings', [ $empty_tablet ] );
	check( count( $profiles ) === 2 && $profiles[0]['media'] === '(width > 767px)', 'An empty tablet override must inherit desktop through the tablet interval.' );
	$id_only = $responsive_hero_settings;
	unset( $id_only['background_image_mobile']['url'] );
	$module = new_module();
	check( invoke( 'get_responsive_lcp_preloads_from_settings', [ $id_only ] ) === lcp_preloads( $responsive_result ), 'Media controls with an ID and no URL must retain their selected modern-format size.' );
	$same_image = $responsive_hero_settings;
	$same_image['background_image_mobile'] = $input;
	$same_image['background_image_tablet'] = $input;
	$module = new_module();
	check( invoke( 'get_responsive_lcp_preloads_from_settings', [ $same_image ] ) === [ [ 'url' => $base . 'hero-1440x550.webp', 'media' => '' ] ], 'Identical responsive URLs must collapse to one unconditional preload.' );
	$same_image['background_image_tablet'] = $thumbnail;
	$module = new_module();
	$profiles = invoke( 'get_responsive_lcp_preloads_from_settings', [ $same_image ] );
	check( count( $profiles ) === 2 && false !== strpos( $profiles[0]['media'], ', ' ), 'The same URL in desktop and mobile intervals must use one preload with OR media.' );
	$active_breakpoints['mobile_extra'] = [880, 'max'];
	$with_extra = $responsive_hero_settings;
	$with_extra['background_image_mobile_extra'] = $input;
	$module = new_module();
	$profiles = invoke( 'get_responsive_lcp_preloads_from_settings', [ $with_extra ] );
	check( count( $profiles ) === 3 && false !== strpos( $profiles[0]['media'], '880px' ), 'Active additional breakpoints must affect preload intervals.' );
	unset( $active_breakpoints['mobile_extra'] );
	$active_breakpoints['laptop'] = [1366, 'max'];
	$active_breakpoints['widescreen'] = [2400, 'min'];
	$with_widescreen = $responsive_hero_settings;
	$with_widescreen['background_image_laptop'] = $thumbnail;
	$with_widescreen['background_image_widescreen'] = $portrait;
	$module = new_module();
	$wide_profiles = invoke( 'get_responsive_lcp_preloads_from_settings', [ $with_widescreen ] );
	check( count( $wide_profiles ) === 3 && false !== strpos( $wide_profiles[0]['media'], '>= 2400px' ) && $wide_profiles[1]['media'] === '(width > 1366px) and (width < 2400px)', 'Widescreen must start at its inclusive min boundary without contaminating smaller max devices.' );
	$viewport_fixtures['widescreen'] = [ 'preloads' => $wide_profiles, 'css' => [
		[ 'url' => $base . 'hero-1440x550.webp', 'media' => '' ],
		[ 'url' => $base . 'hero-767x1024.webp', 'media' => '(min-width:2400px)' ],
		[ 'url' => $base . 'hero-150x150.webp', 'media' => '(max-width:1366px)' ],
		[ 'url' => $base . 'hero-150x150.webp', 'media' => '(max-width:1024px)' ],
		[ 'url' => $base . 'hero-767x1024.webp', 'media' => '(max-width:767px)' ],
	] ];
	unset( $with_widescreen['background_image_widescreen'] );
	$module = new_module();
	$profiles = invoke( 'get_responsive_lcp_preloads_from_settings', [ $with_widescreen ] );
	check( $profiles[0]['url'] === $base . 'hero-1440x550.webp' && $profiles[0]['media'] === '(width > 1366px)', 'A missing widescreen override must inherit desktop, not the laptop source.' );
	$active_breakpoints = [ 'mobile' => [767, 'max'], 'tablet' => [1024, 'max'] ];
	$dynamic_mobile = $responsive_hero_settings;
	$dynamic_mobile['__dynamic__'] = [ 'background_image_mobile' => '[unresolved-tag]' ];
	$module = new_module();
	$profiles = invoke( 'get_responsive_lcp_preloads_from_settings', [ $dynamic_mobile ] );
	check( count( $profiles ) === 2 && false === strpos( json_encode( $profiles ), 'hero-767x1024' ), 'An unresolved dynamic mobile override must not preload its stale saved image.' );
	$mobile_only = [ 'marrison_image_lcp_hero' => 'yes', 'background_image_mobile' => $portrait ];
	$module = new_module();
	check( invoke( 'get_responsive_lcp_preloads_from_settings', [ $mobile_only ] ) === [ [ 'url' => $base . 'hero-767x1024.webp', 'media' => '(width <= 767px)' ] ], 'A mobile-only background must not be preloaded on desktop.' );
	$json_settings = wp_json_encode( $responsive_hero_settings );
	$module = new_module();
	$inline_settings_hero = '<html><head></head><body><div data-marrison-lcp-hero="1" data-settings="' . htmlspecialchars( $json_settings, ENT_QUOTES, 'UTF-8' ) . '"></div></body></html>';
	check( lcp_preloads( $module->rewrite_frontend_webp_urls( $inline_settings_hero ) ) === lcp_preloads( $responsive_result ), 'HTML data-settings must use the same responsive profile as render hooks and document caching.' );
	$options = $responsive_options;
	$metadata = $responsive_metadata;
	$elementor_layouts[50][0]['elements'][0]['settings'] = $responsive_settings;
	$metadata = [];
	$module = new_module();
	check( invoke( 'get_preload_webp_url', [ $base . 'missing.png', 2855 ] ) === $base . 'missing.png', 'No nonexistent WebP preload may be invented.' );
	check( invoke( 'replace_single_payload_image_url', [ $base . 'missing.png', 2855 ] ) === $base . 'missing.png', 'Slideshow/lightbox must keep original URLs when conversion is missing.' );
	$metadata = $initial_metadata;
	unlink( $root . '/2026/09/hero-1440x550.webp' );
	$module = new_module();
	check( $module->serve_webp_downsize( false, 2855, 'full' )[0] === $base . 'hero.webp', 'A missing chosen size must fall back to the existing full WebP.' );
	$metadata['file'] = '2026/09/hero.webp';
	unset( $metadata['marrison_webp_full'] );
	$options['marrison_addon_full_replacement_size'] = '';
	$module = new_module();
	check( $module->serve_webp_downsize( false, 2855, 'full' )[0] === $base . 'hero.webp', 'A native WebP full source needs no Marrison metadata key.' );
	$key_before = invoke( 'get_background_resolve_cache_key', [ $base . 'hero.png', 1400, 500, 'cover', 'webp' ] );
	invoke( 'clear_elementor_css_cache' );
	check( invoke( 'get_background_resolve_cache_key', [ $base . 'hero.png', 1400, 500, 'cover', 'webp' ] ) !== $key_before, 'Clearing CSS must also invalidate positive and negative background caches.' );
	check( invoke( 'get_or_create_modern_format_file', [ $root . '/2026/09/hero.webp', 'image/webp', 85, false ] ) === $root . '/2026/09/hero.webp', 'Native WebP regeneration must reuse its original without overwriting it.' );
	$metadata = $initial_metadata;
	$admin = true;
	$module = new_module();
	$_POST = [ 'id' => 2855 ];
	try {
		$module->ajax_regenerate_single_image();
		throw new RuntimeException( 'Expected an AJAX result.' );
	} catch ( Json_Response $response ) {
		check( ! $response->success && ! empty( $response->data['partial'] ) && false !== strpos( $response->data['message'], 'WEBP' ), 'Conversion failure must return a partial error even with WP_DEBUG disabled.' );
		check( is_array( $saved_metadata ), 'Successfully generated metadata must survive partial conversion failure.' );
	}
	$editor = true;
	$admin = false;
	$level = ob_get_level();
	$module->start_frontend_webp_rewrite();
	check( ob_get_level() === $level, 'Editor/preview must not start the public HTML buffer.' );
	$options['marrison_addon_image_sizes'] = [];
	$options['marrison_addon_registered_size_webp'] = [];
	$editor = false;
	$module = new_module();
	$module->start_frontend_webp_rewrite();
	check( ob_get_level() === $level + 1, 'LCP must work when modern-format conversion is disabled.' );
	ob_end_clean();
	check( false !== strpos( $module->rewrite_frontend_webp_urls( $cached_hero ), 'href="' . $base . 'hero.png" fetchpriority="high"' ), 'Cached heroes must still preload their original image when conversion is disabled.' );
	if ( isset( $argv[1] ) ) {
		file_put_contents( $argv[1], json_encode( $viewport_fixtures, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}
	echo "Image Sizes frontend/LCP/cache/partial errors: PASS\n";
} finally {
	// Resolve and bound the recursive cleanup to this test's unique temporary directory.
	$resolved = realpath( $root );
	check( $resolved && strpos( $resolved, realpath( sys_get_temp_dir() ) . DIRECTORY_SEPARATOR . 'marrison-frontend-' ) === 0, 'Unsafe fixture cleanup path.' );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $resolved, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $item ) { $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
	rmdir( $resolved );
}
