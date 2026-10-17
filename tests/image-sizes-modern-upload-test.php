<?php
/**
 * Run with: php tests/image-sizes-modern-upload-test.php
 * Contract test for Image Sizes automatic WebP/AVIF generation on upload metadata.
 */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WP_DEBUG', true );

$GLOBALS['marrison_test_filters'] = [];
$GLOBALS['marrison_test_options'] = [];
$GLOBALS['marrison_test_attachments'] = [];
$GLOBALS['marrison_test_attachment_mimes'] = [];
$GLOBALS['marrison_test_image_flags'] = [];
$GLOBALS['marrison_test_registered_sizes'] = [];
$GLOBALS['marrison_test_wp_metadata'] = [];
$GLOBALS['marrison_test_editor_saves'] = [];
$GLOBALS['marrison_test_editor_resizes'] = [];
$GLOBALS['marrison_test_metadata_generations'] = 0;
$GLOBALS['marrison_test_saved_metadata'] = null;
$GLOBALS['marrison_test_resize_error'] = false;
$GLOBALS['marrison_test_conversion_error'] = '';
$GLOBALS['marrison_test_persistence_error'] = false;
$GLOBALS['marrison_test_next_attachment_id'] = 1000;
$GLOBALS['marrison_test_root'] = sys_get_temp_dir() . '/marrison-image-sizes-upload-test-' . uniqid();

mkdir( $GLOBALS['marrison_test_root'], 0777, true );

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code = $code;
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

class Marrison_Test_Image_Editor {
	private $file;
	private $quality = 0;
	private $width = 1;
	private $height = 1;

	public function __construct( $file ) {
		$this->file = $file;
	}

	public function set_quality( $quality ) {
		$this->quality = (int) $quality;
	}

	public function get_size() {
		return [
			'width' => $this->width,
			'height' => $this->height,
		];
	}

	public function resize( $width, $height, $crop = false ) {
		$GLOBALS['marrison_test_editor_resizes'][] = [ $width, $height, $crop ];
		if ( $GLOBALS['marrison_test_resize_error'] ) {
			return new WP_Error( 'resize_failed', 'Fixture resize failed.' );
		}
		$this->width = $width ? $width : $height;
		$this->height = $height ? $height : $width;
		return true;
	}

	public function generate_filename( $suffix ) {
		$info = pathinfo( $this->file );
		return $info['dirname'] . '/' . $info['filename'] . '-' . $suffix . '.' . $info['extension'];
	}

	public function save( $output_path, $mime_type = null ) {
		$is_base_size = null === $mime_type;
		$native_mimes = [ 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'avif' => 'image/avif' ];
		$mime_type = $mime_type ? $mime_type : $native_mimes[ pathinfo( $this->file, PATHINFO_EXTENSION ) ];
		if ( ! $is_base_size && $mime_type === $GLOBALS['marrison_test_conversion_error'] ) {
			return new WP_Error( 'conversion_failed', 'Fixture conversion failed.' );
		}

		if ( ! is_dir( dirname( $output_path ) ) ) {
			mkdir( dirname( $output_path ), 0777, true );
		}

		file_put_contents( $output_path, $is_base_size ? file_get_contents( $this->file ) : 'converted:' . $mime_type . ':q' . $this->quality . ':' . basename( $this->file ) );

		$GLOBALS['marrison_test_editor_saves'][] = [
			'input' => $this->file,
			'output' => $output_path,
			'mime_type' => $mime_type,
			'quality' => $this->quality,
		];

		return [
			'file' => basename( $output_path ),
			'path' => $output_path,
			'width' => $this->width,
			'height' => $this->height,
			'mime-type' => $mime_type,
		];
	}
}

function add_action() {
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['marrison_test_filters'][ $hook ][ $priority ][] = [
		'callback' => $callback,
		'accepted_args' => $accepted_args,
	];
}

function apply_filters( $hook, $value ) {
	$args = func_get_args();
	array_shift( $args );

	if ( empty( $GLOBALS['marrison_test_filters'][ $hook ] ) ) {
		return $value;
	}

	ksort( $GLOBALS['marrison_test_filters'][ $hook ] );
	foreach ( $GLOBALS['marrison_test_filters'][ $hook ] as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$args[0] = $value;
			$value = call_user_func_array( $entry['callback'], array_slice( $args, 0, $entry['accepted_args'] ) );
		}
	}

	return $value;
}

function did_action() {
	return 0;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function absint( $value ) {
	return abs( (int) $value );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function get_option( $name, $default = [] ) {
	return array_key_exists( $name, $GLOBALS['marrison_test_options'] ) ? $GLOBALS['marrison_test_options'][ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['marrison_test_options'][ $name ] = $value;
	return true;
}

function wp_generate_uuid4() {
	return uniqid( 'revision-', true );
}

function wp_get_registered_image_subsizes() {
	return $GLOBALS['marrison_test_registered_sizes'];
}

function get_attached_file( $attachment_id ) {
	return isset( $GLOBALS['marrison_test_attachments'][ $attachment_id ] ) ? $GLOBALS['marrison_test_attachments'][ $attachment_id ] : false;
}

function get_post_mime_type( $attachment_id ) {
	return isset( $GLOBALS['marrison_test_attachment_mimes'][ $attachment_id ] ) ? $GLOBALS['marrison_test_attachment_mimes'][ $attachment_id ] : false;
}

function wp_attachment_is_image( $attachment_id ) {
	return ! empty( $GLOBALS['marrison_test_image_flags'][ $attachment_id ] );
}

function wp_image_editor_supports( $args ) {
	return ! empty( $args['mime_type'] ) && in_array( $args['mime_type'], [ 'image/webp', 'image/avif' ], true );
}

function wp_get_image_editor( $file ) {
	return new Marrison_Test_Image_Editor( $file );
}

function wp_generate_attachment_metadata( $attachment_id, $file ) {
	++$GLOBALS['marrison_test_metadata_generations'];
	return apply_filters( 'wp_generate_attachment_metadata', $GLOBALS['marrison_test_wp_metadata'], $attachment_id, 'create' );
}

function wp_get_attachment_metadata( $attachment_id ) {
	return $GLOBALS['marrison_test_wp_metadata'];
}

function wp_update_attachment_metadata( $attachment_id, $metadata ) {
	if ( $GLOBALS['marrison_test_persistence_error'] ) {
		return false;
	}
	$GLOBALS['marrison_test_saved_metadata'] = $metadata;
	$GLOBALS['marrison_test_wp_metadata'] = $metadata;
	return true;
}

function wp_unique_filename( $dir, $filename ) {
	$info = pathinfo( $filename );
	$candidate = $filename;
	for ( $i = 1; file_exists( $dir . '/' . $candidate ); ++$i ) {
		$candidate = $info['filename'] . '-' . $i . '.' . $info['extension'];
	}
	return $candidate;
}

function wp_unslash( $value ) { return $value; }
function check_ajax_referer() {}
function current_user_can() { return true; }
class Image_Sizes_Json_Response extends Exception {
	public $success;
	public $data;
	public function __construct( $success, $data ) { $this->success = $success; $this->data = $data; }
}
function wp_send_json_success( $data ) { throw new Image_Sizes_Json_Response( true, $data ); }
function wp_send_json_error( $data ) { throw new Image_Sizes_Json_Response( false, $data ); }
function regenerate_attachment( $module, $id, $size ) {
	$_POST = [ 'id' => $id, 'size' => $size, 'cleanup' => 'true' ];
	$GLOBALS['marrison_test_saved_metadata'] = null;
	try {
		$module->ajax_regenerate_single_image();
	} catch ( Image_Sizes_Json_Response $response ) {
		return $response;
	}
	throw new RuntimeException( 'Expected an AJAX response.' );
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function fixture_bytes( $extension ) {
	if ( 'png' === $extension ) {
		return base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=' );
	}

	return base64_decode( '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/ASP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/ASP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Ar//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z' );
}

function prepare_upload_case( $extension, array $size_slugs ) {
	$case_dir = $GLOBALS['marrison_test_root'] . '/case-' . uniqid();
	mkdir( $case_dir, 0777, true );

	$attachment_id = ++$GLOBALS['marrison_test_next_attachment_id'];
	$original = $case_dir . '/upload.' . $extension;
	file_put_contents( $original, fixture_bytes( $extension ) );

	$metadata = [
		'file' => '2026/09/' . basename( $original ),
		'width' => 1,
		'height' => 1,
		'sizes' => [],
	];

	foreach ( $size_slugs as $slug ) {
		$size_file = 'upload-' . $slug . '.' . $extension;
		file_put_contents( $case_dir . '/' . $size_file, fixture_bytes( $extension ) );
		$metadata['sizes'][ $slug ] = [
			'file' => $size_file,
			'width' => 1,
			'height' => 1,
			'mime-type' => 'png' === $extension ? 'image/png' : 'image/jpeg',
		];
	}

	$GLOBALS['marrison_test_attachments'][ $attachment_id ] = $original;
	$GLOBALS['marrison_test_image_flags'][ $attachment_id ] = true;
	$GLOBALS['marrison_test_wp_metadata'] = $metadata;

	return [ $attachment_id, $original, $case_dir, $metadata ];
}

function configure_custom_sizes( array $sizes, array $disabled = [] ) {
	$GLOBALS['marrison_test_options']['marrison_addon_image_sizes'] = $sizes;
	$GLOBALS['marrison_test_options']['marrison_addon_disabled_sizes'] = $disabled;
	$GLOBALS['marrison_test_options']['marrison_addon_registered_size_webp'] = [];
	$GLOBALS['marrison_test_registered_sizes'] = [];
}

function custom_size( $slug, $webp, $avif ) {
	return [
		'slug' => $slug,
		'width' => 1,
		'height' => 1,
		'crop' => false,
		'webp' => $webp,
		'webp_quality' => 81,
		'avif' => $avif,
		'avif_quality' => 71,
	];
}

function run_upload_metadata_filter( $attachment_id ) {
	return apply_filters( 'wp_generate_attachment_metadata', $GLOBALS['marrison_test_wp_metadata'], $attachment_id, 'create' );
}

function source_mimes( $metadata, $slug ) {
	$mimes = [];
	if ( empty( $metadata['sizes'][ $slug ]['sources'] ) ) {
		return $mimes;
	}

	foreach ( $metadata['sizes'][ $slug ]['sources'] as $source ) {
		$mimes[] = isset( $source['mime_type'] ) ? $source['mime_type'] : $source['mime-type'];
	}

	sort( $mimes );
	return $mimes;
}

function invoke_private( $object, $method, array $args = [] ) {
	$reflection = new ReflectionMethod( $object, $method );
	if ( PHP_VERSION_ID < 80100 ) {
		$reflection->setAccessible( true );
	}

	return $reflection->invokeArgs( $object, $args );
}

function rrmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}

	rmdir( $dir );
}

require_once __DIR__ . '/../marrison-addon/includes/modules/class-marrison-addon-image-sizes.php';

$module = new Marrison_Addon_Image_Sizes();
check( ! empty( $GLOBALS['marrison_test_filters']['wp_generate_attachment_metadata'] ), 'Upload metadata filter was not registered.' );

try {
	configure_custom_sizes( [ custom_size( 'hero', true, false ) ] );
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'jpg', [ 'hero' ] );
	$result = run_upload_metadata_filter( $attachment_id );
	check( file_exists( $dir . '/upload.webp' ), 'Test 1: full WebP was not created for JPG upload.' );
	check( file_exists( $dir . '/upload-hero.webp' ), 'Test 1: size WebP was not created for JPG upload.' );
	check( ! file_exists( $dir . '/upload.avif' ), 'Test 1: AVIF should not be created when disabled.' );
	check( source_mimes( $result, 'hero' ) === [ 'image/webp' ], 'Test 1: metadata should contain only WebP source.' );
	$revision = get_option( Marrison_Addon_Image_Sizes::BACKGROUND_CACHE_REVISION_OPTION );
	$GLOBALS['marrison_test_wp_metadata'] = $result;
	check( run_upload_metadata_filter( $attachment_id ) === $result, 'Existing generated metadata should remain stable.' );
	check( get_option( Marrison_Addon_Image_Sizes::BACKGROUND_CACHE_REVISION_OPTION ) === $revision, 'Reusing existing files must not invalidate cache unnecessarily.' );
	unlink( $dir . '/upload-hero.webp' );
	check( run_upload_metadata_filter( $attachment_id ) === $result, 'Restoring a missing file should retain existing metadata.' );
	check( get_option( Marrison_Addon_Image_Sizes::BACKGROUND_CACHE_REVISION_OPTION ) !== $revision, 'Restoring a missing variant must invalidate cached misses even when metadata is unchanged.' );

	configure_custom_sizes( [ custom_size( 'card', false, true ) ] );
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'png', [ 'card' ] );
	$result = run_upload_metadata_filter( $attachment_id );
	check( file_exists( $dir . '/upload.avif' ), 'Test 2: full AVIF was not created for PNG upload.' );
	check( file_exists( $dir . '/upload-card.avif' ), 'Test 2: size AVIF was not created for PNG upload.' );
	check( ! file_exists( $dir . '/upload.webp' ), 'Test 2: WebP should not be created when disabled.' );
	check( source_mimes( $result, 'card' ) === [ 'image/avif' ], 'Test 2: metadata should contain only AVIF source.' );

	configure_custom_sizes( [ custom_size( 'both', true, true ) ] );
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'jpg', [ 'both' ] );
	$result = run_upload_metadata_filter( $attachment_id );
	check( file_exists( $dir . '/upload.webp' ) && file_exists( $dir . '/upload.avif' ), 'Test 3: full WebP and AVIF were not both created.' );
	check( source_mimes( $result, 'both' ) === [ 'image/avif', 'image/webp' ], 'Test 3: metadata should contain both modern sources.' );

	configure_custom_sizes( [ custom_size( 'missing', true, true ) ] );
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'jpg', [] );
	$result = run_upload_metadata_filter( $attachment_id );
	check( isset( $result['sizes']['missing']['file'], $result['sizes']['missing']['width'], $result['sizes']['missing']['height'], $result['sizes']['missing']['mime-type'] ), 'Missing base size must be registered in returned metadata.' );
	check( file_exists( $dir . '/' . $result['sizes']['missing']['file'] ), 'Registered base metadata must resolve to its generated file.' );
	check( source_mimes( $result, 'missing' ) === [ 'image/avif', 'image/webp' ], 'Missing base size must expose both modern sources.' );

	configure_custom_sizes( [ custom_size( 'none', false, false ) ] );
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'jpg', [ 'none' ] );
	$result = run_upload_metadata_filter( $attachment_id );
	check( ! file_exists( $dir . '/upload.webp' ) && ! file_exists( $dir . '/upload.avif' ), 'Test 4: no modern full files should be created when both formats are off.' );
	check( source_mimes( $result, 'none' ) === [], 'Test 4: metadata should not contain modern sources.' );

	configure_custom_sizes(
		[
			custom_size( 'webp_only', true, false ),
			custom_size( 'avif_only', false, true ),
			custom_size( 'both_formats', true, true ),
			custom_size( 'disabled_size', true, true ),
		],
		[ 'disabled_size' => 1 ]
	);
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'png', [ 'webp_only', 'avif_only', 'both_formats', 'disabled_size' ] );
	$result = run_upload_metadata_filter( $attachment_id );
	check( source_mimes( $result, 'webp_only' ) === [ 'image/webp' ], 'Test 5: webp_only should contain only WebP.' );
	check( source_mimes( $result, 'avif_only' ) === [ 'image/avif' ], 'Test 5: avif_only should contain only AVIF.' );
	check( source_mimes( $result, 'both_formats' ) === [ 'image/avif', 'image/webp' ], 'Test 5: both_formats should contain both formats.' );
	check( source_mimes( $result, 'disabled_size' ) === [], 'Test 5: disabled size should not receive modern sources.' );
	check( ! file_exists( $dir . '/upload-disabled_size.webp' ) && ! file_exists( $dir . '/upload-disabled_size.avif' ), 'Test 5: disabled size should not write modern files.' );

	configure_custom_sizes( [ custom_size( 'manual', true, true ) ] );
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'jpg', [ 'manual' ] );
	$result = invoke_private( $module, 'generate_metadata_with_upscaling', [ $attachment_id, $original ] );
	check( file_exists( $dir . '/upload-manual.webp' ) && file_exists( $dir . '/upload-manual.avif' ), 'Test 6: manual regeneration did not create both modern files.' );
	check( source_mimes( $result, 'manual' ) === [ 'image/avif', 'image/webp' ], 'Test 6: manual regeneration metadata should contain both sources once.' );

	$GLOBALS['marrison_test_options']['marrison_addon_image_sizes'] = [];
	$GLOBALS['marrison_test_options']['marrison_addon_registered_size_webp'] = [
		'registered_thumb' => [
			'enabled' => 1,
			'quality' => 80,
			'avif_enabled' => 0,
			'avif_quality' => 70,
		],
	];
	$GLOBALS['marrison_test_registered_sizes'] = [
		'registered_thumb' => [
			'width' => 1,
			'height' => 1,
			'crop' => false,
		],
	];
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'jpg', [ 'registered_thumb' ] );
	$result = run_upload_metadata_filter( $attachment_id );
	check( source_mimes( $result, 'registered_thumb' ) === [ 'image/webp' ], 'Registered image size WebP setting was not respected.' );

	// A saved custom edit must replace only its own base and enabled modern variants.
	$cover = custom_size( 'cover', true, true );
	$cover['width'] = 700;
	$cover['height'] = 450;
	$cover['crop'] = true;
	configure_custom_sizes( [ $cover, custom_size( 'sibling', true, true ), custom_size( 'disabled', true, true ) ], [ 'disabled' => 1 ] );
	[ $attachment_id, $original, $dir, $before ] = prepare_upload_case( 'jpg', [ 'cover', 'sibling', 'disabled' ] );
	$before['original_image'] = 'original-upload.jpg';
	$before['image_meta'] = [ 'caption' => 'Preserve attachment data.' ];
	foreach ( [ 'webp', 'avif' ] as $format ) {
		file_put_contents( $dir . '/upload.' . $format, 'untouched-full-' . $format );
		$before[ 'marrison_' . $format . '_full' ] = [ 'file' => 'upload.' . $format, 'width' => 1, 'height' => 1 ];
		foreach ( [ 'cover', 'sibling', 'disabled' ] as $slug ) {
			file_put_contents( $dir . '/upload-' . $slug . '.' . $format, 'untouched-' . $slug . '-' . $format );
			$before['sizes'][ $slug ]['sources'][] = [ 'file' => 'upload-' . $slug . '.' . $format, 'mime_type' => 'image/' . $format ];
		}
	}
	// Shared old files must survive when another size still references them.
	$before['sizes']['alias'] = $before['sizes']['cover'];
	$GLOBALS['marrison_test_wp_metadata'] = $before;
	$files_before = [];
	foreach ( glob( $dir . '/*' ) as $file ) { $files_before[ $file ] = hash_file( 'sha256', $file ); }
	$generations_before = $GLOBALS['marrison_test_metadata_generations'];
	$saves_before = count( $GLOBALS['marrison_test_editor_saves'] );
	$response = regenerate_attachment( $module, $attachment_id, 'cover' );
	$result = $GLOBALS['marrison_test_saved_metadata'];
	check( $response->success && false !== strpos( $response->data['message'], 'cover' ), 'Selected size must be reported explicitly.' );
	check( $GLOBALS['marrison_test_metadata_generations'] === $generations_before, 'Selected-size regeneration must never call the all-sizes metadata generator.' );
	check( count( $GLOBALS['marrison_test_editor_saves'] ) === $saves_before + 3, 'Selected size should save only one base, one WebP and one AVIF.' );
	check( $result['sizes']['cover']['width'] === 700 && $result['sizes']['cover']['height'] === 450, 'Saved size dimensions must be applied.' );
	check( end( $GLOBALS['marrison_test_editor_resizes'] ) === [ 700, 450, [ 'center', 'center' ] ], 'Selected custom crop must be applied.' );
	check( source_mimes( $result, 'cover' ) === [ 'image/avif', 'image/webp' ], 'Selected modern sources must be regenerated without stale entries.' );
	unset( $result['sizes']['cover'], $before['sizes']['cover'] );
	check( $result === $before, 'All unrelated sizes, full sources and attachment metadata must remain identical.' );
	foreach ( $files_before as $file => $hash ) { check( file_exists( $file ) && hash_file( 'sha256', $file ) === $hash, 'Existing/shared file was changed or deleted: ' . $file ); }
	$new_saves = array_slice( $GLOBALS['marrison_test_editor_saves'], $saves_before );
	check( $new_saves[1]['quality'] === 81 && $new_saves[2]['quality'] === 71, 'Selected variants must use their saved quality settings.' );

	// Invalid or disabled selection must never fall back to a full regeneration or cleanup.
	foreach ( [ 'disabled', 'missing', '!!!', '../cover', [ 'cover' ] ] as $invalid ) {
		$response = regenerate_attachment( $module, $attachment_id, $invalid );
		check( ! $response->success && null === $GLOBALS['marrison_test_saved_metadata'], 'Invalid/disabled size must fail without saving metadata.' );
	}
	check( count( $GLOBALS['marrison_test_editor_saves'] ) === $saves_before + 3, 'Invalid requests must not write files.' );

	// Default/third-party sizes also support an unconstrained dimension and directional crops.
	configure_custom_sizes( [] );
	$GLOBALS['marrison_test_registered_sizes'] = [ 'medium_large' => [ 'width' => 768, 'height' => 0, 'crop' => [ 'left', 'top' ] ] ];
	$GLOBALS['marrison_test_options']['marrison_addon_registered_size_webp'] = [ 'medium_large' => [ 'enabled' => 0, 'avif_enabled' => 1, 'avif_quality' => 66 ] ];
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'jpg', [] );
	$response = regenerate_attachment( $module, $attachment_id, 'medium_large' );
	check( $response->success && source_mimes( $GLOBALS['marrison_test_saved_metadata'], 'medium_large' ) === [ 'image/avif' ], 'Default size must honor AVIF-only settings with height zero.' );
	check( end( $GLOBALS['marrison_test_editor_resizes'] ) === [ 768, 0, [ 'left', 'top' ] ], 'Registered crop positions and unconstrained dimensions must survive.' );
	check( ! file_exists( $dir . '/upload.webp' ) && ! file_exists( $dir . '/upload.avif' ), 'Targeting a default size must not generate full-size variants.' );

	configure_custom_sizes( [ custom_size( 'plain', false, false ) ] );
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'jpg', [] );
	$response = regenerate_attachment( $module, $attachment_id, 'plain' );
	check( $response->success && source_mimes( $GLOBALS['marrison_test_saved_metadata'], 'plain' ) === [], 'Single-size generation must also work without modern conversion.' );
	$GLOBALS['marrison_test_resize_error'] = true;
	$response = regenerate_attachment( $module, $attachment_id, 'plain' );
	check( ! $response->success && null === $GLOBALS['marrison_test_saved_metadata'], 'Resize failure must retain old metadata and return an error.' );
	$GLOBALS['marrison_test_resize_error'] = false;
	$GLOBALS['marrison_test_wp_metadata'] = [];
	$response = regenerate_attachment( $module, $attachment_id, 'plain' );
	check( ! $response->success && null === $GLOBALS['marrison_test_saved_metadata'], 'Missing original metadata must not trigger a destructive partial replacement.' );

	configure_custom_sizes( [ custom_size( 'partial', true, true ) ] );
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'jpg', [] );
	$GLOBALS['marrison_test_conversion_error'] = 'image/avif';
	$response = regenerate_attachment( $module, $attachment_id, 'partial' );
	check( ! $response->success && ! empty( $response->data['partial'] ), 'Conversion failure must be returned as partial, not success.' );
	check( source_mimes( $GLOBALS['marrison_test_saved_metadata'], 'partial' ) === [ 'image/webp' ], 'Valid selected-size output must survive a partial conversion failure.' );
	$GLOBALS['marrison_test_conversion_error'] = '';

	// Native modern originals must retain their bytes, while the selected size gets its saved quality.
	foreach ( [ 'webp' => 81, 'avif' => 71 ] as $native => $quality ) {
		configure_custom_sizes( [ custom_size( 'native', true, true ) ] );
		[ $attachment_id, $original, $dir ] = prepare_upload_case( $native, [] );
		$original_hash = hash_file( 'sha256', $original );
		$saves_before = count( $GLOBALS['marrison_test_editor_saves'] );
		$response = regenerate_attachment( $module, $attachment_id, 'native' );
		check( $response->success && hash_file( 'sha256', $original ) === $original_hash, 'Native modern original must remain unchanged.' );
		check( $GLOBALS['marrison_test_editor_saves'][ $saves_before ]['quality'] === $quality, 'Native selected size must use its enabled format quality.' );
	}
	configure_custom_sizes( [ custom_size( 'isolated', true, true ) ] );
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'jpg', [] );
	file_put_contents( $dir . '/upload-isolated.webp', 'shared-modern-source' );
	$response = regenerate_attachment( $module, $attachment_id, 'isolated' );
	check( $response->success && file_get_contents( $dir . '/upload-isolated.webp' ) === 'shared-modern-source', 'Existing modern files must remain intact even when their base file is missing.' );
	$GLOBALS['marrison_test_persistence_error'] = true;
	$response = regenerate_attachment( $module, $attachment_id, 'isolated' );
	check( ! $response->success && null === $GLOBALS['marrison_test_saved_metadata'], 'A failed metadata save must never be reported as successful regeneration.' );
	$GLOBALS['marrison_test_persistence_error'] = false;

	// SVGs are skipped before file checks, cleanup, metadata writes, resizing or encoding.
	configure_custom_sizes( [ custom_size( 'vector', true, true ) ], [ 'disabled' => 1 ] );
	foreach ( [ [ 'svg', 'image/svg+xml' ], [ 'SVG', 'application/octet-stream' ], [ 'dat', 'image/svg+xml' ], [ 'svgz', 'application/octet-stream' ] ] as $svg_case ) {
		[ $attachment_id, $original, $dir, $metadata ] = prepare_upload_case( 'jpg', [ 'disabled' ] );
		$svg_path = $dir . '/icon.' . $svg_case[0];
		file_put_contents( $svg_path, '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"></svg>' );
		$GLOBALS['marrison_test_attachments'][ $attachment_id ] = $svg_path;
		$GLOBALS['marrison_test_attachment_mimes'][ $attachment_id ] = $svg_case[1];
		$saves_before = count( $GLOBALS['marrison_test_editor_saves'] );
		$resizes_before = count( $GLOBALS['marrison_test_editor_resizes'] );
		$generations_before = $GLOBALS['marrison_test_metadata_generations'];
		$files_before = [];
		foreach ( glob( $dir . '/*' ) as $file ) { $files_before[ $file ] = hash_file( 'sha256', $file ); }
		foreach ( [ '', 'vector' ] as $scope ) {
			$response = regenerate_attachment( $module, $attachment_id, $scope );
			check( $response->success && ! empty( $response->data['skipped'] ) && 'svg' === $response->data['reason'], 'SVG must return a skipped result in both full and single-size modes.' );
			check( false !== strpos( $response->data['message'], 'Saltato:' ), 'SVG must be clearly reported as skipped.' );
			check( null === $GLOBALS['marrison_test_saved_metadata'] && $GLOBALS['marrison_test_wp_metadata'] === $metadata, 'Skipping SVG must not save or change metadata.' );
		}
		check( run_upload_metadata_filter( $attachment_id ) === $metadata, 'Automatic SVG conversion must preserve the incoming metadata.' );
		check( invoke_private( $module, 'generate_modern_formats_for_metadata', [ $attachment_id, $svg_path, $metadata ] ) === $metadata, 'Shared conversion routine must also exclude SVGs.' );
		check( count( $GLOBALS['marrison_test_editor_saves'] ) === $saves_before && count( $GLOBALS['marrison_test_editor_resizes'] ) === $resizes_before, 'SVG must never reach resizing or encoding.' );
		check( $GLOBALS['marrison_test_metadata_generations'] === $generations_before, 'SVG must skip all-sizes metadata generation.' );
		foreach ( $files_before as $file => $hash ) { check( file_exists( $file ) && hash_file( 'sha256', $file ) === $hash, 'SVG skip must bypass disabled-size cleanup and retain all files.' ); }
		check( count( glob( $dir . '/*' ) ) === count( $files_before ), 'SVG skip must not create any conversion output.' );
		$property = new ReflectionProperty( $module, 'modern_format_generation_errors' );
		if ( PHP_VERSION_ID < 80100 ) { $property->setAccessible( true ); }
		check( [] === $property->getValue( $module ), 'SVG must not record conversion errors.' );
	}
	// A stored SVG MIME is sufficient even when its path is missing.
	$GLOBALS['marrison_test_attachments'][ $attachment_id ] = false;
	$GLOBALS['marrison_test_attachment_mimes'][ $attachment_id ] = 'image/svg+xml';
	$response = regenerate_attachment( $module, $attachment_id, '' );
	check( $response->success && ! empty( $response->data['skipped'] ), 'An SVG must not become a raster file-not-found error.' );
	check( run_upload_metadata_filter( $attachment_id ) === $metadata, 'Automatic SVG skip must occur before missing-file error logging.' );

	// Full regeneration with cleanup must retain its full scope after iterating old size slugs.
	configure_custom_sizes( [ custom_size( 'active', true, false ) ], [ 'disabled' => 1 ] );
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'jpg', [ 'active', 'disabled' ] );
	$response = regenerate_attachment( $module, $attachment_id, '' );
	check( $response->success && ! isset( $response->data['skipped'] ) && ! file_exists( $dir . '/upload-disabled.jpg' ), 'Full regeneration must still work with disabled-size cleanup.' );

	// Unreadable raster input must remain a genuine conversion error.
	configure_custom_sizes( [ custom_size( 'broken', true, true ) ] );
	[ $attachment_id, $original, $dir ] = prepare_upload_case( 'jpg', [ 'broken' ] );
	$GLOBALS['marrison_test_attachment_mimes'][ $attachment_id ] = 'image/jpeg';
	file_put_contents( $original, 'invalid-raster-data' );
	file_put_contents( $dir . '/upload-broken.jpg', 'invalid-raster-data' );
	set_error_handler( function () { return true; } );
	try { $response = regenerate_attachment( $module, $attachment_id, '' ); } finally { restore_error_handler(); }
	check( ! $response->success && ! empty( $response->data['partial'] ) && ! isset( $response->data['skipped'] ), 'Unreadable raster files must remain errors, not skipped successes.' );

	echo "Image Sizes automatic/full/selected-size/SVG skip regeneration: PASS\n";
} finally {
	rrmdir( $GLOBALS['marrison_test_root'] );
}
