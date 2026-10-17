<?php
/** php tests/inline-css-php-test.php [official-core-fixture.php] [public-css-manifest.json] */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
$root = sys_get_temp_dir() . '/marrison-inline-css-' . uniqid();
mkdir( $root . '/wp-content/plugins/demo/assets', 0777, true );
mkdir( $root . '/wp-includes/css', 0777, true );
define( 'ABSPATH', $root . '/' );
define( 'WP_CONTENT_DIR', $root . '/wp-content' );
define( 'WPINC', 'wp-includes' );
$GLOBALS['test_admin'] = false;
$GLOBALS['test_ajax'] = false;
$GLOBALS['test_json'] = false;
$GLOBALS['test_customizer'] = false;
$GLOBALS['test_inline_calls'] = 0;
$GLOBALS['test_inline_limit'] = 40000;
$GLOBALS['test_files'] = [];

function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['test_hooks'][ $hook ][] = [ $callback, $priority ]; }
function is_admin() { return $GLOBALS['test_admin']; }
function wp_doing_ajax() { return $GLOBALS['test_ajax']; }
function wp_is_json_request() { return $GLOBALS['test_json']; }
function is_customize_preview() { return $GLOBALS['test_customizer']; }
function sanitize_text_field( $v ) { return (string) $v; }
function wp_unslash( $v ) { return $v; }
function wp_parse_url( $v ) { return parse_url( $v ); }
function home_url( $path = '' ) { return 'https://urbevigilanza.it' . $path; }
function content_url( $path = '' ) { return ( $GLOBALS['test_content_origin'] ?? 'https://urbevigilanza.it' ) . '/wp-content' . $path; }
function includes_url( $path = '' ) { return 'https://urbevigilanza.it/wp-includes' . $path; }
function wp_styles() { return $GLOBALS['wp_styles']; }
function wp_style_add_data( $handle, $key, $value ) { return wp_styles()->add_data( $handle, $key, $value ); }
function wp_filesize( $path ) { return filesize( $path ); }
function apply_filters( $hook, $v ) { return 'styles_inline_size_limit' === $hook ? $GLOBALS['test_inline_limit'] : $v; }
function wp_make_link_relative( $url ) { return preg_replace( '|^(https?:)?//[^/]+|i', '', $url ); }
function __( $text ) { return $text; }
function esc_html( $v ) { return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ); }
function _doing_it_wrong( $function, $message ) { throw new RuntimeException( $function . ': ' . $message ); }

class Inline_Test_Styles {
	public $registered = [];
	public $queue = [];
	public $done = [];
	public function add( $handle, $src, $media = 'all', $extra = [] ) {
		$this->registered[ $handle ] = (object) [ 'src' => $src, 'args' => $media, 'extra' => $extra ];
		$this->queue[] = $handle;
	}
	public function get_data( $handle, $key ) { return $this->registered[ $handle ]->extra[ $key ] ?? false; }
	public function add_data( $handle, $key, $value ) { $this->registered[ $handle ]->extra[ $key ] = $value; return true; }
}

$native = isset( $argv[1] ) && is_file( $argv[1] );
if ( $native ) {
	require $argv[1];
} else {
	// Default tests verify opt-in and guards; pass the official fixture for real core behavior.
	function wp_maybe_inline_styles() { $GLOBALS['test_inline_calls']++; }
	function _wp_normalize_relative_css_links( $css, $url ) { return $css; }
}

require dirname( __DIR__ ) . '/marrison-addon/includes/class-marrison-addon-context.php';
require dirname( __DIR__ ) . '/marrison-addon/includes/modules/class-marrison-addon-inline-css.php';

function check_inline( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$GLOBALS['test_checks'] = ( $GLOBALS['test_checks'] ?? 0 ) + 1;
}
function css_file( $relative, $css ) {
	$path = ABSPATH . $relative;
	if ( ! is_dir( dirname( $path ) ) ) { mkdir( dirname( $path ), 0777, true ); }
	file_put_contents( $path, $css );
	$GLOBALS['test_files'][] = $path;
	return $path;
}
function fresh_styles() { $GLOBALS['wp_styles'] = new Inline_Test_Styles(); return $GLOBALS['wp_styles']; }
function selected_inline( $handle ) { return false !== wp_styles()->get_data( $handle, 'path' ); }

$small = css_file( 'wp-content/plugins/demo/assets/small.css', '.demo{color:red;background:url(../images/a.svg)}' );
$include = css_file( 'wp-includes/css/small.css', '.core{color:blue}' );
css_file( 'wp-content/plugins/demo/assets/large.css', str_repeat( 'a', 16385 ) );
css_file( 'wp-content/plugins/demo/assets/import.css', '@import "small.css";.demo{color:red}' );
css_file( 'wp-content/plugins/demo/assets/escape.css', '.demo{content:"</style><script>bad</script>"}' );
css_file( 'outside.css', '.outside{color:red}' );

$s = fresh_styles();
$s->add( 'small', content_url( '/plugins/demo/assets/small.css?ver=1.3.55' ), 'all', [ 'after' => [ '.demo{color:green}' ] ] );
$s->add( 'include', includes_url( '/css/small.css' ) );
$s->add( 'relative', '/wp-content/plugins/demo/assets/small.css' );
$s->add( 'protocol-relative', '//urbevigilanza.it/wp-content/plugins/demo/assets/small.css' );
$skipped = [
	'remote' => [ 'https://other.test/wp-content/plugins/demo/assets/small.css' ],
	'wrong-port' => [ 'https://urbevigilanza.it:8443/wp-content/plugins/demo/assets/small.css' ],
	'credentials' => [ 'https://u:p@urbevigilanza.it/wp-content/plugins/demo/assets/small.css' ],
	'http' => [ 'http://urbevigilanza.it/wp-content/plugins/demo/assets/small.css' ],
	'dynamic-query' => [ content_url( '/plugins/demo/assets/small.css?variant=mobile' ) ],
	'large' => [ content_url( '/plugins/demo/assets/large.css' ) ],
	'import' => [ content_url( '/plugins/demo/assets/import.css' ) ],
	'escape' => [ content_url( '/plugins/demo/assets/escape.css' ) ],
	'missing' => [ content_url( '/plugins/demo/assets/missing.css' ) ],
	'traversal' => [ content_url( '/../outside.css' ) ],
	'encoded-traversal' => [ content_url( '/%2e%2e/outside.css' ) ],
	'encoded-null' => [ content_url( '/plugins/demo/assets/%00small.css' ) ],
	'print' => [ content_url( '/plugins/demo/assets/small.css' ), 'print' ],
	'conditional' => [ content_url( '/plugins/demo/assets/small.css' ), 'all', [ 'conditional' => 'lt IE 9' ] ],
	'rtl' => [ content_url( '/plugins/demo/assets/small.css' ), 'all', [ 'rtl' => 'replace' ] ],
	'alternate' => [ content_url( '/plugins/demo/assets/small.css' ), 'all', [ 'alt' => true ] ],
	'titled' => [ content_url( '/plugins/demo/assets/small.css' ), 'all', [ 'title' => 'Alternate' ] ],
	'printed' => [ content_url( '/plugins/demo/assets/small.css' ) ],
];
foreach ( $skipped as $handle => $args ) { $s->add( $handle, ...$args ); }
$s->done[] = 'printed';
$module = new Marrison_Addon_Inline_CSS();
$module->prepare_styles();
foreach ( [ 'small', 'include', 'relative', 'protocol-relative' ] as $handle ) { check_inline( selected_inline( $handle ), 'Local CSS not selected: ' . $handle ); }
foreach ( $skipped as $handle => $args ) {
	check_inline( ! selected_inline( $handle ), 'Unsafe/unsupported CSS selected: ' . $handle );
	check_inline( $s->registered[ $handle ]->src === $args[0], 'Fallback URL changed: ' . $handle );
}
if ( $native ) {
	check_inline( false === $s->registered['small']->src, 'Native core did not inline the selected style.' );
	check_inline( '.demo{color:green}' === $s->registered['small']->extra['after'][1], 'Existing override order changed.' );
	check_inline( false !== strpos( $s->registered['small']->extra['after'][0], '/wp-content/plugins/demo/assets/../images/a.svg' ), 'Relative asset URL was not rebased.' );
}
$before = serialize( $s );
$module->prepare_styles();
check_inline( $before === serialize( $s ), 'Repeated preparation changed CSS.' );

foreach ( [ 'test_admin', 'test_ajax', 'test_json', 'test_customizer' ] as $context ) {
	$s = fresh_styles(); $s->add( 'small', content_url( '/plugins/demo/assets/small.css' ) );
	$GLOBALS[ $context ] = true;
	( new Marrison_Addon_Inline_CSS() )->prepare_styles();
	check_inline( ! selected_inline( 'small' ), 'Excluded request changed CSS: ' . $context );
	$GLOBALS[ $context ] = false;
}
foreach ( [ [ 'elementor-preview' => '50' ], [ 'action' => 'elementor' ] ] as $request ) {
	$_REQUEST = $request;
	$s = fresh_styles(); $s->add( 'small', content_url( '/plugins/demo/assets/small.css' ) );
	( new Marrison_Addon_Inline_CSS() )->prepare_styles();
	check_inline( ! selected_inline( 'small' ), 'Elementor context changed CSS.' );
}
$_REQUEST = [];
$GLOBALS['pagenow'] = 'wp-login.php';
$s = fresh_styles(); $s->add( 'small', content_url( '/plugins/demo/assets/small.css' ) );
( new Marrison_Addon_Inline_CSS() )->prepare_styles();
check_inline( ! selected_inline( 'small' ), 'Login CSS changed.' );
unset( $GLOBALS['pagenow'] );

foreach ( [ 'https://cdn.test', 'https://urbevigilanza.it:8443' ] as $origin ) {
	$GLOBALS['test_content_origin'] = $origin;
	$s = fresh_styles();
	$s->add( 'different-origin', content_url( '/plugins/demo/assets/small.css' ) );
	$s->add( 'different-default-port', 'https://urbevigilanza.it/wp-content/plugins/demo/assets/small.css' );
	( new Marrison_Addon_Inline_CSS() )->prepare_styles();
	check_inline( ! selected_inline( 'different-origin' ) && ! selected_inline( 'different-default-port' ), 'CDN or non-canonical port incorrectly mapped to local CSS.' );
}
unset( $GLOBALS['test_content_origin'] );

$s = fresh_styles();
for ( $i = 0; $i < 5; $i++ ) {
	css_file( 'wp-content/plugins/demo/assets/budget-' . $i . '.css', str_repeat( ' ', 8990 ) . '.x{color:red}' );
	$s->add( 'budget-' . $i, content_url( '/plugins/demo/assets/budget-' . $i . '.css' ) );
}
( new Marrison_Addon_Inline_CSS() )->prepare_styles();
$selected = array_filter( $s->registered, static function ( $style ) { return ! empty( $style->extra['path'] ); } );
check_inline( 3 === count( $selected ), '32 KiB selection budget exceeded.' );
if ( $native ) {
	$s = fresh_styles(); $s->add( 'small', content_url( '/plugins/demo/assets/small.css' ) );
	$GLOBALS['test_inline_limit'] = 1;
	( new Marrison_Addon_Inline_CSS() )->prepare_styles();
	check_inline( is_string( $s->registered['small']->src ), 'Configured lower core budget was overridden.' );
	$GLOBALS['test_inline_limit'] = 40000;
}

if ( $native && isset( $argv[2] ) ) {
	$manifest = json_decode( file_get_contents( $argv[2] ), true );
	$s = fresh_styles();
	foreach ( $manifest['rows'] as $row ) {
		$relative = ltrim( parse_url( $row['url'], PHP_URL_PATH ), '/' );
		css_file( $relative, file_get_contents( $row['path'] ) );
		$s->add( preg_replace( '/-css$/', '', $row['id'] ), $row['url'], $row['media'] );
	}
	( new Marrison_Addon_Inline_CSS() )->prepare_styles();
	$embedded = [];
	$inline_content = [];
	$total = 0;
	foreach ( $s->registered as $handle => $style ) {
		if ( false === $style->src ) {
			$embedded[] = $handle . '-css';
			$inline_content[ $handle . '-css' ] = $style->extra['after'][0];
			$total += filesize( $style->extra['path'] );
			check_inline( ! empty( $style->extra['after'][0] ), 'Public CSS was removed without inline content: ' . $handle );
		}
	}
	check_inline( $manifest['selected_ids'] === $embedded || ! array_diff( $manifest['selected_ids'], $embedded ) && ! array_diff( $embedded, $manifest['selected_ids'] ), 'Actual core selection differs from the public profile.' );
	file_put_contents( $manifest['folder'] . '/native-inline-content.json', json_encode( $inline_content ) );
	echo json_encode( [ 'public_head_sheets_before' => count( $s->queue ), 'external_head_sheets_after' => count( $s->queue ) - count( $embedded ), 'embedded_count' => count( $embedded ), 'embedded_bytes' => $total, 'embedded_ids' => $embedded ] ) . "\n";
}

foreach ( array_unique( $GLOBALS['test_files'] ) as $path ) { unlink( $path ); }
echo 'Inline CSS guards/core contract: PASS (' . $GLOBALS['test_checks'] . ' checks; official core: ' . ( $native ? 'yes' : 'not loaded' ) . ")\n";
