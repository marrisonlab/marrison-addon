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
$GLOBALS['marrison_test_image_flags'] = [];
$GLOBALS['marrison_test_registered_sizes'] = [];
$GLOBALS['marrison_test_wp_metadata'] = [];
$GLOBALS['marrison_test_editor_saves'] = [];
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

	public function __construct( $file ) {
		$this->file = $file;
	}

	public function set_quality( $quality ) {
		$this->quality = (int) $quality;
	}

	public function get_size() {
		return [
			'width' => 1,
			'height' => 1,
		];
	}

	public function resize( $width, $height, $crop = false ) {
		return true;
	}

	public function generate_filename( $suffix ) {
		$info = pathinfo( $this->file );
		return $info['dirname'] . '/' . $info['filename'] . '-' . $suffix . '.' . $info['extension'];
	}

	public function save( $output_path, $mime_type = null ) {
		$is_base_size = null === $mime_type;
		$mime_type = $mime_type ? $mime_type : 'image/jpeg';

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
			'width' => 1,
			'height' => 1,
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

function wp_get_registered_image_subsizes() {
	return $GLOBALS['marrison_test_registered_sizes'];
}

function get_attached_file( $attachment_id ) {
	return isset( $GLOBALS['marrison_test_attachments'][ $attachment_id ] ) ? $GLOBALS['marrison_test_attachments'][ $attachment_id ] : false;
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
	return apply_filters( 'wp_generate_attachment_metadata', $GLOBALS['marrison_test_wp_metadata'], $attachment_id, 'create' );
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

	echo "Image Sizes automatic modern upload generation: PASS\n";
} finally {
	rrmdir( $GLOBALS['marrison_test_root'] );
}
