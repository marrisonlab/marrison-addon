<?php
// Probe isolato: carica WordPress LocalWP, usa solo fixture nella directory audit e non salva opzioni.
$root = 'C:/Users/Angelo/Local Sites/tesy/app/public/';
$_SERVER['HTTP_HOST'] = 'localhost:10004';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '10004';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
define( 'DISABLE_WP_CRON', true );
require $root . 'wp-load.php';

$dir = __DIR__ . '/functional-fixtures';
wp_mkdir_p( $dir );
$png = $dir . '/alpha-source.png';
$webp = $dir . '/alpha-output.webp';
$avif = $dir . '/alpha-output.avif';
$image = imagecreatetruecolor( 16, 16 );
imagealphablending( $image, false );
imagesavealpha( $image, true );
$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
imagefill( $image, 0, 0, $transparent );
$red = imagecolorallocatealpha( $image, 220, 30, 30, 32 );
imagefilledrectangle( $image, 4, 4, 11, 11, $red );
imagepng( $image, $png );
imagedestroy( $image );

$module = new Marrison_Addon_Image_Sizes();
$convert = new ReflectionMethod( $module, 'convert_to_format' );
$convert->setAccessible( true );
$results = array();
foreach ( array( 'image/webp' => $webp, 'image/avif' => $avif ) as $mime => $target ) {
	$value = $convert->invoke( $module, $png, $mime, 80 );
	$target = is_string( $value ) ? $value : $target;
	$decoded = false;
	$alpha = null;
	if ( file_exists( $target ) ) {
		$decoded_image = 'image/avif' === $mime ? imagecreatefromavif( $target ) : imagecreatefromwebp( $target );
		$decoded = false !== $decoded_image;
		if ( $decoded ) {
			$pixel = imagecolorat( $decoded_image, 0, 0 );
			$alpha = ( $pixel >> 24 ) & 0x7f;
			imagedestroy( $decoded_image );
		}
	}
	$results['conversion'][ $mime ] = array( 'return' => $value, 'exists' => file_exists( $target ), 'decoded' => $decoded, 'transparent_alpha' => $alpha );
}

$video = new Marrison_Addon_Video_Thumbnail();
$ffmpeg = new ReflectionMethod( $video, 'get_ffmpeg_status' );
$ffmpeg->setAccessible( true );
$results['ffmpeg'] = $ffmpeg->invoke( $video );

$svg = '<svg viewBox="0 0 10 10" onclick="bad()"><script>alert(1)</script><path fill="#123456" stroke="none" d="M0 0h10v10z"/><use href="https://evil.test/x"/></svg>';
$sanitize = new ReflectionMethod( 'Marrison_Addon_Dynamic_SVG', 'sanitize_svg' );
$sanitize->setAccessible( true );
$clean = $sanitize->invoke( null, $svg, true );
$results['svg'] = array( 'has_script' => false !== stripos( $clean, '<script' ), 'has_event' => false !== stripos( $clean, 'onclick' ), 'has_external' => false !== stripos( $clean, 'evil.test' ), 'has_current_color' => false !== strpos( $clean, 'currentColor' ), 'has_none' => false !== strpos( $clean, 'stroke="none"' ) );

$fonts = new Marrison_Addon_Local_Google_Fonts();
$coverage = new ReflectionMethod( $fonts, 'evaluate_google_stylesheet_coverage' );
$coverage->setAccessible( true );
$font_url = 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap';
$partial = array( 'families' => array( 'poppins' => array( 'variants' => array( 'normal|400' => true ) ) ) );
$full = array( 'families' => array( 'poppins' => array( 'variants' => array( 'normal|400' => true, 'normal|700' => true ) ) ) );
$results['fonts_coverage'] = array( 'partial' => $coverage->invoke( $fonts, $font_url, $partial ), 'full' => $coverage->invoke( $fonts, $font_url, $full ) );

echo json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
@unlink( $png );
@unlink( $webp );
@unlink( $avif );
@rmdir( $dir );
