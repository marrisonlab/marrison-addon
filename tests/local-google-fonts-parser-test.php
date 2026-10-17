<?php
define( 'ABSPATH', __DIR__ . '/../' );

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		return str_replace( '\\', '/', (string) $path );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		$base = sys_get_temp_dir() . '/marrison-lgf-test';

		return array(
			'basedir' => $base,
			'baseurl' => 'https://example.test/wp-content/uploads',
			'error'   => false,
		);
	}
}

if ( ! function_exists( 'is_ssl' ) ) {
	function is_ssl() {
		return true;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {
		return true;
	}
}

if ( ! class_exists( 'Marrison_Addon_Context' ) ) {
	class Marrison_Addon_Context {
		public static function is_public_frontend_request() {
			return true;
		}
	}
}

require_once __DIR__ . '/../marrison-addon/includes/modules/class-marrison-addon-local-google-fonts.php';

function lgf_invoke( $object, $method, array $args = array() ) {
	$reflection = new ReflectionMethod( $object, $method );
	if ( PHP_VERSION_ID < 80100 ) {
		$reflection->setAccessible( true );
	}

	return $reflection->invokeArgs( $object, $args );
}

function lgf_get_property( $object, $property ) {
	$reflection = new ReflectionProperty( $object, $property );
	if ( PHP_VERSION_ID < 80100 ) {
		$reflection->setAccessible( true );
	}

	return $reflection->getValue( $object );
}

function lgf_set_property( $object, $property, $value ) {
	$reflection = new ReflectionProperty( $object, $property );
	if ( PHP_VERSION_ID < 80100 ) {
		$reflection->setAccessible( true );
	}

	$reflection->setValue( $object, $value );
}

function lgf_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function wp_safe_remote_get( $url, $args ) {
	$GLOBALS['lgf_home_request'] = array( 'url' => $url, 'args' => $args );
	return $GLOBALS['lgf_home_response'];
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['status'] ) ? $response['status'] : 0;
}

function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? $response['body'] : '';
}

function wp_remote_retrieve_header( $response, $header ) {
	return isset( $response['headers'][ $header ] ) ? $response['headers'][ $header ] : '';
}

class LGF_Test_Style {
	public $src;
	public $deps;

	public function __construct( $src, array $deps = array() ) {
		$this->src  = $src;
		$this->deps = $deps;
	}
}

$module = new Marrison_Addon_Local_Google_Fonts();

$fonts = array();
$remote = array();
lgf_invoke( $module, 'extract_fonts_from_css', array( "body{font-family:'Poppins',sans-serif!important;font-weight:400;}", 'Test CSS', &$fonts, &$remote ) );
lgf_assert( isset( $fonts['poppins'] ), 'Poppins should be extracted from minified CSS with !important.' );
lgf_assert( 'Poppins' === $fonts['poppins']['family'], 'Poppins family name should be clean.' );

$fonts = array();
$remote = array();
lgf_invoke( $module, 'extract_fonts_from_css', array( 'body{font-family:inherit!important;}', 'Test CSS', &$fonts, &$remote ) );
lgf_assert( empty( $fonts ), 'inherit!important should not be treated as a font family.' );
$ignored = lgf_get_property( $module, 'scan_ignored_fonts' );
lgf_assert( ! empty( $ignored ), 'Ignored font diagnostics should record discarded CSS keywords.' );

$fonts = array();
$remote = array();
lgf_invoke( $module, 'extract_fonts_from_css', array( 'body{font-family:"Helvetica Neue",Arial,sans-serif;}', 'Theme CSS: style.css', &$fonts, &$remote ) );
lgf_invoke( $module, 'classify_fonts', array( &$fonts ) );
lgf_assert( isset( $fonts['helvetica neue'] ), 'Helvetica Neue should be detected as a used family.' );
lgf_assert( 'unknown' === $fonts['helvetica neue']['classification'], 'Helvetica Neue must not be classified as a Google Font automatically.' );

$fonts = array();
$remote = array();
lgf_invoke( $module, 'extract_fonts_from_css', array( '@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap");body{font-family:Poppins,sans-serif;font-weight:600;}', 'Plugin CSS: app.css', &$fonts, &$remote ) );
lgf_invoke( $module, 'classify_fonts', array( &$fonts ) );
lgf_assert( isset( $fonts['poppins'] ), 'Poppins should be detected when used with an explicit Google Fonts URL.' );
lgf_assert( 'google' === $fonts['poppins']['classification'], 'Poppins should be classified as Google only when confirmed by Google evidence.' );

$fonts = array();
$remote = array();
lgf_invoke( $module, 'extract_remote_google_stylesheets', array( '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">', &$remote, &$fonts, 'Plugin source: plugin.php' ) );
lgf_invoke( $module, 'classify_fonts', array( &$fonts ) );
lgf_assert( isset( $fonts['poppins']['variants']['normal|500'] ), 'Google stylesheet URL variants should be scanned even when the source file has no CSS declarations.' );
lgf_assert( 'google' === $fonts['poppins']['classification'], 'Families found only through a Google stylesheet URL should be classified as Google.' );

$fonts = array();
$remote = array();
lgf_invoke( $module, 'extract_fonts_from_css', array( '@font-face{font-family:"Metric";src:url("Metric-Regular.woff2") format("woff2");}.x{font-family:"Metric";font-weight:500;}', 'Theme CSS: fonts.css', &$fonts, &$remote ) );
lgf_invoke( $module, 'classify_fonts', array( &$fonts ) );
lgf_assert( isset( $fonts['metric'] ), 'Metric should be detected when used.' );
lgf_assert( 'local' === $fonts['metric']['classification'], 'Metric with a local @font-face should be classified as local/custom.' );

$active_complete = array(
	'families' => array(
		'poppins' => array(
			'variants' => array(
				'normal|400' => array(
					'style' => 'normal',
					'weight' => '400',
				),
				'normal|500' => array(
					'style' => 'normal',
					'weight' => '500',
				),
				'normal|600' => array(
					'style' => 'normal',
					'weight' => '600',
				),
				'normal|700' => array(
					'style' => 'normal',
					'weight' => '700',
				),
			),
		),
		'roboto' => array(
			'variants' => array(
				'normal|400' => array(
					'style' => 'normal',
					'weight' => '400',
				),
				'normal|500' => array(
					'style' => 'normal',
					'weight' => '500',
				),
			),
		),
	),
);

$active_incomplete = $active_complete;
unset( $active_incomplete['families']['poppins']['variants']['normal|500'] );

$active_missing_roboto = $active_complete;
unset( $active_missing_roboto['families']['roboto'] );

lgf_assert(
	true === lgf_invoke( $module, 'is_google_stylesheet_covered_by_active_manifest', array( 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap', $active_complete ) ),
	'Poppins stylesheet should be removable when all requested variants are local.'
);

lgf_assert(
	false === lgf_invoke( $module, 'is_google_stylesheet_covered_by_active_manifest', array( 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap', $active_incomplete ) ),
	'Poppins stylesheet should stay when a requested local variant is missing.'
);

lgf_assert(
	true === lgf_invoke( $module, 'is_google_stylesheet_covered_by_active_manifest', array( 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&family=Roboto:wght@400;500&display=swap', $active_complete ) ),
	'Multi-family Google stylesheet should be removable only when every family and variant is local.'
);

lgf_assert(
	false === lgf_invoke( $module, 'is_google_stylesheet_covered_by_active_manifest', array( 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&family=Roboto:wght@400&display=swap', $active_missing_roboto ) ),
	'Multi-family Google stylesheet should stay when one requested family is not local.'
);

$wp_styles = (object) array(
	'queue' => array( 'marrison-site-agent' ),
	'registered' => array(
		'marrison-site-agent-fonts' => new LGF_Test_Style( 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap' ),
		'marrison-site-agent' => new LGF_Test_Style( 'https://example.test/wp-content/plugins/marrison-assistant/assets/css/site-agent.css', array( 'dependency-a', 'marrison-site-agent-fonts', 'dependency-b' ) ),
		'dependency-a' => new LGF_Test_Style( 'https://example.test/a.css' ),
		'dependency-b' => new LGF_Test_Style( 'https://example.test/b.css' ),
	),
);

$diagnostics = lgf_invoke( $module, 'process_google_font_style_handles', array( $wp_styles, $active_complete ) );
lgf_assert( array( 'marrison-site-agent' ) === $wp_styles->queue, 'Dependent stylesheet should remain queued after Google Fonts removal.' );
lgf_assert( array( 'dependency-a', 'dependency-b' ) === $wp_styles->registered['marrison-site-agent']->deps, 'Only the Google Fonts dependency should be removed from dependent styles.' );
lgf_assert( ! empty( $diagnostics[0]['dependency_removals'][0]['handle'] ) && 'marrison-site-agent' === $diagnostics[0]['dependency_removals'][0]['handle'], 'Dependency removal diagnostics should name the dependent handle.' );

$wp_styles = (object) array(
	'queue' => array( 'marrison-site-agent' ),
	'registered' => array(
		'marrison-site-agent-fonts' => new LGF_Test_Style( 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap' ),
		'marrison-site-agent' => new LGF_Test_Style( 'https://example.test/wp-content/plugins/marrison-assistant/assets/css/site-agent.css', array( 'marrison-site-agent-fonts' ) ),
	),
);

lgf_invoke( $module, 'process_google_font_style_handles', array( $wp_styles, $active_incomplete ) );
lgf_assert( array( 'marrison-site-agent-fonts' ) === $wp_styles->registered['marrison-site-agent']->deps, 'Google Fonts dependency should remain when local coverage is incomplete.' );

$upload_dir = wp_upload_dir();
if ( ! is_dir( $upload_dir['basedir'] . '/marrison-addon/fonts/local-google-fonts' ) ) {
	mkdir( $upload_dir['basedir'] . '/marrison-addon/fonts/local-google-fonts', 0777, true );
}
file_put_contents( $upload_dir['basedir'] . '/marrison-addon/fonts/local-google-fonts/local.css', '@font-face{font-family:"Poppins";font-style:normal;font-weight:500;src:url("./poppins.woff2") format("woff2");}' );
file_put_contents( $upload_dir['basedir'] . '/marrison-addon/fonts/local-google-fonts/poppins.woff2', 'wOF2test' );
file_put_contents( $upload_dir['basedir'] . '/marrison-addon/fonts/local-google-fonts/roboto.woff2', 'wOF2test' );

$elementor_css_dir = $upload_dir['basedir'] . '/elementor/google-fonts/css';
$elementor_font_dir = $upload_dir['basedir'] . '/elementor/google-fonts/fonts';
if ( ! is_dir( $elementor_css_dir ) ) {
	mkdir( $elementor_css_dir, 0777, true );
}
if ( ! is_dir( $elementor_font_dir ) ) {
	mkdir( $elementor_font_dir, 0777, true );
}
file_put_contents( $elementor_font_dir . '/poppins-400.woff2', 'wOF2test' );
file_put_contents( $elementor_font_dir . '/raleway-400.woff2', 'wOF2test' );
file_put_contents( $elementor_font_dir . '/inter-700.woff2', 'wOF2test' );
file_put_contents( $elementor_css_dir . '/poppins.css', '@font-face{font-family:"Poppins";font-style:normal;font-weight:400;src:url("../fonts/poppins-400.woff2") format("woff2");}' );
file_put_contents( $elementor_css_dir . '/raleway.css', '@font-face{font-family:"Raleway";font-style:normal;font-weight:400;src:url("../fonts/raleway-400.woff2") format("woff2");}' );
file_put_contents( $elementor_css_dir . '/inter.css', '@font-face{font-family:"Inter";font-style:normal;font-weight:700;src:url("/wp-content/uploads/elementor/google-fonts/fonts/inter-700.woff2") format("woff2");}' );

$elementor_fonts = lgf_invoke( $module, 'get_elementor_local_google_fonts_map' );
lgf_assert( ! empty( $elementor_fonts['poppins']['variants']['normal|400'] ), 'Elementor local Google Fonts map should include Poppins 400.' );
lgf_assert( ! empty( $elementor_fonts['inter']['variants']['normal|700'] ), 'Elementor local Google Fonts map should resolve root-relative uploads URLs.' );

$external_complete = array(
	'elementor' => array(
		'poppins' => array(
			'family' => 'Poppins',
			'variants' => $active_complete['families']['poppins']['variants'],
		),
	),
);

$external_partial = array(
	'elementor' => array(
		'poppins' => array(
			'family' => 'Poppins',
			'variants' => array(
				'normal|400' => array(
					'style' => 'normal',
					'weight' => '400',
				),
			),
		),
	),
);

lgf_assert(
	true === lgf_invoke( $module, 'is_google_stylesheet_covered_by_active_manifest', array( 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap', array( 'families' => array(), 'external_local_fonts' => $external_complete ) ) ),
	'Remote Google stylesheet should be removable when Elementor covers every requested variant.'
);

$missing_from_elementor = lgf_invoke(
	$module,
	'filter_variants_missing_from_external_local_fonts',
	array(
		'Poppins',
		array_values( $active_complete['families']['poppins']['variants'] ),
		$external_partial,
	)
);
lgf_assert( 3 === count( $missing_from_elementor ), 'Marrison should keep only Poppins variants not covered by Elementor.' );
lgf_assert( '500' === $missing_from_elementor[0]['weight'], 'The first missing Poppins variant should be 500.' );

$manifest_fallback = $active_incomplete;
$manifest_fallback['css_file'] = 'local.css';
$manifest_fallback['families']['poppins']['files'] = array( 'poppins.woff2' );

lgf_assert(
	true === lgf_invoke( $module, 'is_google_stylesheet_covered_by_active_manifest', array( 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap', $manifest_fallback ) ),
	'Local CSS @font-face declarations should verify coverage when an older manifest lacks a variant entry.'
);

$manifest_active = $active_complete;
$manifest_active['css_file'] = 'local.css';
$manifest_active['version'] = 'test';
$manifest_active['families']['poppins']['files'] = array( 'poppins.woff2' );
$manifest_active['families']['roboto']['files'] = array( 'roboto.woff2' );

lgf_set_property(
	$module,
	'manifest_cache',
	array(
		'active' => $manifest_active,
	)
);

$filtered = $module->filter_google_font_style_tag(
	'<link rel="stylesheet" id="marrison-site-agent-fonts-css" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&amp;display=swap" media="all" />',
	'marrison-site-agent-fonts',
	'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
	'all'
);

lgf_assert( '' === $filtered, 'Final style_loader_tag filter should suppress covered Google Fonts links.' );

lgf_assert(
	false === lgf_invoke( $module, 'is_google_stylesheet_covered_by_active_manifest', array( 'https://fonts.googleapis.com/css2?family=Poppins:wght@100..900&display=swap', $active_complete ) ),
	'Variable weight ranges should stay remote unless every requested static weight is local.'
);

$source = file_get_contents( __DIR__ . '/../marrison-addon/includes/modules/class-marrison-addon-local-google-fonts.php' );
lgf_assert( false !== strpos( $source, "post_type <> %s" ) && false !== strpos( $source, "'revision'" ), 'Scanner queries should explicitly exclude revision post types.' );

// Production regression: DM Sans declares wght@100..1000; the old parser kept only 100.
lgf_assert( '1000' === lgf_invoke( $module, 'normalize_font_weight', array( '1000' ) ), 'Weight 1000 must not collapse to 100.' );
lgf_assert( '450' === lgf_invoke( $module, 'normalize_font_weight', array( '450' ) ), 'Intermediate variable weights must not collapse to 400.' );
$dm_range_url = 'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap';
$dm_range = lgf_invoke( $module, 'parse_google_stylesheet_families', array( $dm_range_url ) );
lgf_assert( isset( $dm_range['DM Sans']['normal|100 1000'], $dm_range['DM Sans']['italic|100 1000'] ), 'DM Sans continuous ranges must survive parsing for both styles.' );
$variable_active = array( 'families' => array( 'dm sans' => array( 'variants' => $dm_range['DM Sans'] ) ) );
lgf_assert( lgf_invoke( $module, 'active_manifest_has_variant', array( $variable_active, 'DM Sans', 'normal', '450' ) ), 'Local variable faces must cover intermediate weights.' );
lgf_assert( ! lgf_invoke( $module, 'active_manifest_has_variant', array( $variable_active, 'DM Sans', 'normal', '1 1000' ) ), 'Partial variable coverage must not suppress a broader request.' );
$static_range_faces = array();
for ( $weight = 100; $weight <= 1000; $weight += 100 ) {
	$static_range_faces[] = array( 'style' => 'normal', 'weight' => (string) $weight );
}
lgf_assert( ! lgf_invoke( $module, 'font_variants_cover_weight', array( $static_range_faces, 'normal', '100 1000' ) ), 'Separate static faces cannot prove a continuous variable range.' );
$gapped_faces = array( array( 'style' => 'normal', 'weight' => '100 400' ), array( 'style' => 'normal', 'weight' => '500 1000' ) );
lgf_assert( ! lgf_invoke( $module, 'font_variants_cover_weight', array( $gapped_faces, 'normal', '100 1000' ) ), 'A gap between local variable faces must remain uncovered.' );
$joined_faces = array( array( 'style' => 'normal', 'weight' => '100 500' ), array( 'style' => 'normal', 'weight' => '500 1000' ) );
lgf_assert( lgf_invoke( $module, 'font_variants_cover_weight', array( $joined_faces, 'normal', '100 1000' ) ), 'Touching continuous local ranges may jointly cover a request.' );
lgf_assert( ! lgf_invoke( $module, 'is_google_stylesheet_covered_by_active_manifest', array( $dm_range_url, $variable_active ) ), 'Additional optical-size axes must stay remote when their coverage has not been proved.' );
lgf_assert( array() === lgf_invoke( $module, 'parse_google_stylesheet_families', array( 'https://fonts.googleapis.com/css?family=%1$s%2$s' ) ), 'Unresolved PHP format placeholders are not real Google font families.' );
$placeholder_fonts = $placeholder_urls = array();
lgf_invoke( $module, 'extract_remote_google_stylesheets', array( 'https://fonts.googleapis.com/css?family=%1$s%2$s', &$placeholder_urls, &$placeholder_fonts, 'PHP template' ) );
lgf_assert( empty( $placeholder_urls ) && empty( $placeholder_fonts ), 'An unresolved template must not enter the remote download list.' );

// Runtime links from Urbe: these may be constructed dynamically or beyond the source scan limit.
$runtime_urls = array(
	'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
	'https://fonts.googleapis.com/css?family=DM+Sans:100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic&display=swap',
	'https://fonts.googleapis.com/css?family=Poppins:100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic&display=swap',
);
$runtime_html = '<html><head>';
foreach ( $runtime_urls as $runtime_url ) {
	$runtime_html .= '<link rel="stylesheet" href="' . htmlspecialchars( $runtime_url, ENT_QUOTES, 'UTF-8' ) . '">';
}
$runtime_html .= '</head><body></body></html>';
$GLOBALS['lgf_home_response'] = array( 'status' => 200, 'headers' => array( 'content-type' => 'text/html; charset=UTF-8' ), 'body' => $runtime_html );
$runtime_module = new Marrison_Addon_Local_Google_Fonts();
$runtime_fonts = $runtime_remote = $runtime_warnings = array();
$runtime_stats = array( 'frontend_pages' => 0 );
lgf_invoke( $runtime_module, 'scan_frontend_google_stylesheets', array( &$runtime_fonts, &$runtime_remote, &$runtime_stats, &$runtime_warnings ) );
lgf_invoke( $runtime_module, 'classify_fonts', array( &$runtime_fonts ) );
lgf_assert( 1 === $runtime_stats['frontend_pages'] && empty( $runtime_warnings ), 'A public homepage scan should find dynamic font URLs.' );
lgf_assert( 18 === count( $runtime_fonts['poppins']['variants'] ) && 'google' === $runtime_fonts['poppins']['classification'], 'Runtime Poppins links must provide every requested weight and style.' );
lgf_assert( home_url( '/' ) === $GLOBALS['lgf_home_request']['url'], 'The scan must request only the configured homepage.' );
lgf_assert( 0 === $GLOBALS['lgf_home_request']['args']['redirection'] && $GLOBALS['lgf_home_request']['args']['limit_response_size'] <= 1048576, 'Loopback requests must have bounded redirects and body size.' );
lgf_assert( ! isset( $GLOBALS['lgf_home_request']['args']['cookies'] ) && ! isset( $GLOBALS['lgf_home_request']['args']['headers']['Authorization'] ), 'The public scan must not forward an authenticated session.' );
$runtime_active = array( 'families' => array() );
foreach ( $runtime_fonts as $key => $font ) {
	$runtime_active['families'][ $key ] = array( 'variants' => $font['variants'] );
}
foreach ( $runtime_urls as $runtime_url ) {
	lgf_assert( lgf_invoke( $runtime_module, 'is_google_stylesheet_covered_by_active_manifest', array( $runtime_url, $runtime_active ) ), 'Each observed runtime URL must be suppressible after complete local coverage.' );
}

// A second scan must retain proved sources for fonts still used after remote links disappear.
lgf_set_property( $runtime_module, 'manifest_cache', array( 'active' => array( 'source_css_urls' => $runtime_urls ) ) );
$rescan_fonts = $rescan_remote = array();
lgf_invoke( $runtime_module, 'add_font_variant', array( &$rescan_fonts, 'Poppins', '600', 'normal', 'Current Elementor CSS' ) );
lgf_invoke( $runtime_module, 'scan_previous_google_stylesheets', array( &$rescan_fonts, &$rescan_remote ) );
lgf_invoke( $runtime_module, 'classify_fonts', array( &$rescan_fonts ) );
lgf_assert( 18 === count( $rescan_fonts['poppins']['variants'] ), 'A second scan must retain coverage for a font still used locally.' );
lgf_assert( ! isset( $rescan_fonts['dm sans'] ), 'Historical sources must not reintroduce a family absent from the current scan.' );
$rescan_active = array( 'families' => array( 'poppins' => array( 'variants' => $rescan_fonts['poppins']['variants'] ) ) );
lgf_assert( lgf_invoke( $runtime_module, 'is_google_stylesheet_covered_by_active_manifest', array( $runtime_urls[2], $rescan_active ) ), 'The second update must retain enough Poppins coverage to suppress the full Elementor URL.' );

$selected_url = lgf_invoke( $runtime_module, 'get_google_css_url_for_font', array( array( 'remote_stylesheets' => array_merge( $runtime_urls, array( $dm_range_url ) ) ), 'DM Sans', array_values( $dm_range['DM Sans'] ) ) );
lgf_assert( $dm_range_url === $selected_url, 'Download requests must preserve original optical-size axes when they cover the requested family.' );
$merged_url = lgf_invoke( $runtime_module, 'build_google_css_url', array( 'DM Sans', array( array( 'style' => 'normal', 'weight' => '100 1000' ), array( 'style' => 'normal', 'weight' => '400' ) ) ) );
lgf_assert( false !== strpos( $merged_url, 'wght@100..1000&' ) && false === strpos( $merged_url, ';400' ), 'CSS2 requests must preserve ranges and omit overlapping static tuples.' );

// Variable CSS files must remain usable through the existing Elementor provider and legacy fallback.
file_put_contents( $elementor_font_dir . '/dm-variable.woff2', 'wOF2test' );
file_put_contents( $elementor_css_dir . '/dm-variable.css', '@font-face{font-family:"DM Sans";font-style:normal;font-weight:100 1000;src:url("../fonts/dm-variable.woff2") format("woff2");}' );
$variable_elementor = lgf_invoke( $runtime_module, 'get_elementor_local_google_fonts_map' );
lgf_assert( lgf_invoke( $runtime_module, 'external_local_fonts_have_variant', array( array( 'elementor' => $variable_elementor ), 'DM Sans', 'normal', '450' ) ), 'Elementor variable files must cover nonstandard requested weights.' );
file_put_contents( $upload_dir['basedir'] . '/marrison-addon/fonts/local-google-fonts/variable.css', '@font-face{font-family:"DM Sans";font-style:italic;font-weight:100 1000;src:url("./dm-variable.woff2") format("woff2");}' );
file_put_contents( $upload_dir['basedir'] . '/marrison-addon/fonts/local-google-fonts/dm-variable.woff2', 'wOF2test' );
lgf_assert( lgf_invoke( $runtime_module, 'active_local_css_has_variant', array( array( 'css_file' => 'variable.css' ), 'DM Sans', 'italic', '450' ) ), 'Legacy local CSS fallback must also recognize variable font intervals.' );

$GLOBALS['lgf_home_response'] = array( 'status' => 403, 'body' => '<html>Blocked</html>' );
$failed_fonts = $failed_remote = $failed_warnings = array();
$failed_stats = array( 'frontend_pages' => 0 );
lgf_invoke( $runtime_module, 'scan_frontend_google_stylesheets', array( &$failed_fonts, &$failed_remote, &$failed_stats, &$failed_warnings ) );
lgf_assert( empty( $failed_fonts ) && 0 === $failed_stats['frontend_pages'] && ! empty( $failed_warnings ), 'A blocked homepage must warn without inventing fonts.' );

if ( isset( $argv[1] ) ) {
	$google_response_active = array( 'families' => array() );
	foreach ( array( 'dm-sans', 'poppins' ) as $fixture_name ) {
		$css = file_get_contents( rtrim( $argv[1], '/\\' ) . '/' . $fixture_name . '-google.css' );
		$faces = lgf_invoke( $runtime_module, 'parse_google_font_faces', array( $css ) );
		lgf_assert( ! empty( $faces ), 'Official Google CSS responses must contain readable WOFF2 faces.' );
		foreach ( $faces as $face ) {
			$key = strtolower( $face['family'] );
			$variant_key = lgf_invoke( $runtime_module, 'variant_key', array( $face['style'], $face['weight'] ) );
			$google_response_active['families'][ $key ]['variants'][ $variant_key ] = array( 'style' => $face['style'], 'weight' => $face['weight'] );
		}
	}
	foreach ( $runtime_urls as $runtime_url ) {
		lgf_assert( lgf_invoke( $runtime_module, 'is_google_stylesheet_covered_by_active_manifest', array( $runtime_url, $google_response_active ) ), 'Actual Google CSS faces must cover all three observed Urbe URLs.' );
	}
	echo "Official Google CSS response coverage passed.\n";
}
echo "Local Google Fonts parser tests passed.\n";
