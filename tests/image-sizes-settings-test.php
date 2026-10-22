<?php
/** Run: php tests/image-sizes-settings-test.php. Admin uniqueness and bounded public resolver contracts. */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
$options = array( 'marrison_addon_image_sizes' => array() );
$registered = array( 'thumbnail' => array( 'width' => 150, 'height' => 150 ), 'theme-cover' => array( 'width' => 900, 'height' => 400 ) );
$errors = $transients = $cache = $lookups = array();
function get_option( $key, $default = null ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function wp_get_registered_image_subsizes() { return $GLOBALS['registered']; }
function add_image_size( $slug, $width, $height, $crop ) { $GLOBALS['registered'][ $slug ] = compact( 'width', 'height', 'crop' ); }
function add_settings_error( $setting, $code, $message, $type ) { $GLOBALS['errors'][] = compact( 'code', 'message', 'type' ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $value ) ), '-' ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return $value; }
function __( $value, $domain = '' ) { return $value; }
function wp_generate_uuid4() { return uniqid(); }
function wp_get_upload_dir() { return array( 'baseurl' => 'https://fixture.example/wp-content/uploads', 'basedir' => '/uploads' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
function wp_cache_get( $key, $group ) { return $GLOBALS['cache'][ $group ][ $key ] ?? false; }
function wp_cache_set( $key, $value, $group, $ttl ) { $GLOBALS['cache'][ $group ][ $key ] = $value; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = $value; }
function attachment_url_to_postid( $url ) { $GLOBALS['lookups'][] = $url; return 12; }
function wp_get_attachment_metadata( $id ) { return false; }
class Image_Settings_Response extends Exception { public $data; public function __construct( $data ) { $this->data = $data; } }
function wp_send_json_success( $data ) { throw new Image_Settings_Response( $data ); }
function check( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
require __DIR__ . '/../marrison-addon/includes/modules/class-marrison-addon-image-sizes.php';
$module = ( new ReflectionClass( 'Marrison_Addon_Image_Sizes' ) )->newInstanceWithoutConstructor();
function invoke( $method, $args = array() ) {
    $ref = new ReflectionMethod( 'Marrison_Addon_Image_Sizes', $method );
    if ( PHP_VERSION_ID < 80100 ) { $ref->setAccessible( true ); }
    return $ref->invokeArgs( $GLOBALS['module'], $args );
}
function save_size( $name, $width = 640, $height = 0 ) {
    $_POST = array( 'marrison_action' => 'add_size', 'size_name' => $name, 'size_width' => $width, 'size_height' => $height );
    invoke( 'handle_form_submission' );
}
save_size( 'Hero Card' );
check( count( $options['marrison_addon_image_sizes'] ) === 1 && $options['marrison_addon_image_sizes'][0]['height'] === 0, 'A proportional size with height 0 must be saved.' );
foreach ( array( 'Hero Card', 'Hero--Card', 'thumbnail', 'theme-cover', 'full', '!!!' ) as $duplicate ) {
    $before = $options['marrison_addon_image_sizes'];
    save_size( $duplicate );
    check( $options['marrison_addon_image_sizes'] === $before && end( $errors )['type'] === 'error', 'Duplicate/reserved/invalid slug must show an error without changing saved sizes: ' . $duplicate );
}
$_POST = array( 'marrison_action' => 'update_size', 'size_index' => 0, 'size_name' => 'Renamed Hero', 'size_width' => 800, 'size_height' => 0 );
invoke( 'handle_form_submission' );
check( $options['marrison_addon_image_sizes'][0]['slug'] === 'hero-card' && $options['marrison_addon_image_sizes'][0]['width'] === 800 && $options['marrison_addon_image_sizes'][0]['height'] === 0, 'Editing a proportional size must retain its slug and height 0.' );
$module->register_image_sizes();
check( $registered['hero-card']['height'] === 0 && $registered['hero-card']['width'] === 800, 'Proportional sizes must be registered with WordPress.' );
$registered['hero-card']['width'] = 1200; // A theme may register the same slug later in after_setup_theme.
$before = $options['marrison_addon_image_sizes'];
invoke( 'handle_form_submission' );
check( $options['marrison_addon_image_sizes'] === $before && end( $errors )['type'] === 'error', 'A later theme/plugin registration must be detected when editing a conflicting custom size.' );
$options['marrison_addon_image_sizes'][] = array( 'slug' => 'theme-cover', 'name' => 'Legacy collision', 'width' => 300, 'height' => 300 );
$module = ( new ReflectionClass( 'Marrison_Addon_Image_Sizes' ) )->newInstanceWithoutConstructor();
$module->register_image_sizes();
check( $registered['theme-cover']['width'] === 900, 'A legacy collision must never overwrite a registered theme/plugin size.' );

$base = 'https://fixture.example/wp-content/uploads/2026/10/';
check( invoke( 'normalize_background_resolve_url', array( $base . 'photo.jpg?random=1#hash' ) ) === $base . 'photo.jpg', 'Query and fragment must not create resolver cache entries.' );
foreach ( array( $base . '../secret.jpg', $base . '%2e%2e/secret.jpg', 'https://other.example/wp-content/uploads/a.jpg', 'https://fixture.example/wp-content/uploads-fake/a.jpg', $base . 'a.php' ) as $bad ) {
    check( invoke( 'normalize_background_resolve_url', array( $bad ) ) === '', 'Resolver must reject non-upload image paths: ' . $bad );
}
$_POST = array( 'items' => array() );
for ( $i = 0; $i < 50; $i++ ) { $_POST['items'][] = array( 'key' => 'a' . $i, 'url' => $base . $i . '.jpg', 'width' => 400, 'height' => 200 ); }
try { $module->ajax_resolve_background_webp(); } catch ( Image_Settings_Response $response ) {}
check( count( $lookups ) === 10, 'A public request must perform no more than ten uncached attachment resolutions.' );
$transients = $cache = array();
$_POST['items'] = array( array( 'key' => 'a', 'url' => $base . '0.jpg', 'width' => 400, 'height' => 200 ) );
try { $module->ajax_resolve_background_webp(); } catch ( Image_Settings_Response $response ) {}
$lookups = array();
$_POST = array( 'items' => array( array( 'key' => 'a', 'url' => $base . '0.jpg?cachebuster=2', 'width' => 399, 'height' => 199 ), array( 'key' => 'bad', 'url' => $base . 'huge.jpg', 'width' => 999999 ) ) );
try { $module->ajax_resolve_background_webp(); } catch ( Image_Settings_Response $response ) {}
check( empty( $lookups ), 'Negative cache must survive cache busters and same dimension bucket; oversized targets must not query metadata.' );
for ( $i = 0; $i < 2000; $i++ ) { invoke( 'set_background_resolve_cache', array( md5( 'url-' . $i ), array() ) ); }
check( count( $transients ) <= 256 && count( $cache[ Marrison_Addon_Image_Sizes::BACKGROUND_RESOLVE_CACHE_GROUP ] ) <= 256, 'Arbitrary resolver keys must never grow the fixed 256-slot persistent cache.' );
echo "Image Sizes settings/slug collisions/proportions/public resolver bounds: PASS\n";
