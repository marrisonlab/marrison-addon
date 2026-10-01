<?php
// Probe solo parsing/coverage/cache contract; nessun download o file uploads.
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

$fonts = new Marrison_Addon_Local_Google_Fonts();
$parse = new ReflectionMethod( $fonts, 'parse_google_stylesheet_families' );
$parse->setAccessible( true );
$build = new ReflectionMethod( $fonts, 'build_google_css_url' );
$build->setAccessible( true );
$css2 = 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap';
$parsed = $parse->invoke( $fonts, $css2 );
$result = array(
	'css2_family_count' => isset( $parsed['Roboto'] ) ? count( $parsed['Roboto'] ) : 0,
	'css2_first_last' => isset( $parsed['Roboto'] ) && $parsed['Roboto'] ? array( reset( $parsed['Roboto'] ), end( $parsed['Roboto'] ) ) : array(),
	'built_italic_url' => $build->invoke( $fonts, 'Roboto', array( array( 'style' => 'normal', 'weight' => '400' ), array( 'style' => 'italic', 'weight' => '700' ) ) ),
	'built_axis_url' => $build->invoke( $fonts, 'Roboto', array( array( 'style' => 'normal', 'weight' => '400' ), array( 'style' => 'normal', 'weight' => '700' ) ) ),
);
echo json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
