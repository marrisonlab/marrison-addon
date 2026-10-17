<?php
/** Local contracts + real isolated PHP fatal processes; no WordPress/database server required. */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
$child = isset( $argv[1] ) && '--child' === $argv[1];
$scenario = $child ? $argv[2] : '';
$state_file = $child ? $argv[3] : null;
$GLOBALS['modules'] = $child && 'disabled' !== $scenario ? [ 'php_snippets' => 1 ] : [];
$GLOBALS['caps'] = [ 'manage_options' => true, 'unfiltered_html' => true ];
$GLOBALS['hooks'] = $GLOBALS['shortcodes'] = $GLOBALS['trace'] = [];
$GLOBALS['admin'] = in_array( $scenario, [ 'backend', 'recovery', 'dashboard', 'toggle' ], true );
$_SERVER['SCRIPT_NAME'] = 'login' === $scenario ? '/wp-login.php' : '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
if ( 'recovery' === $scenario ) { $_GET['page'] = 'marrison_addon_php_snippets'; }
if ( 'dashboard' === $scenario ) { $_GET['page'] = 'marrison_addon_panel'; }
if ( 'toggle' === $scenario ) { $_SERVER['SCRIPT_NAME'] = '/wp-admin/admin-ajax.php'; $_POST['action'] = 'marrison_save_option'; }
if ( 'safe' === $scenario ) { define( 'MARRISON_ADDON_PHP_SNIPPETS_SAFE_MODE', true ); }
if ( in_array( $scenario, [ 'memory', 'dependency-memory' ], true ) ) { ini_set( 'memory_limit', '24M' ); }

class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_attr( $text ); }
function esc_textarea( $text ) { return esc_html( $text ); }
function esc_html_e( $text, $domain = '' ) { echo esc_html( $text ); }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . ( $args ? '&' . http_build_query( $args ) : '' ); }
function plugins_url( $path, $file ) { return 'https://example.test/plugin/' . $path; }
function wp_nonce_field( $action ) { echo '<input name="_wpnonce" type="hidden" value="valid-' . esc_attr( $action ) . '">'; }
function selected( $current, $value ) { if ( $current === $value ) { echo 'selected'; } }
function submit_button( $label ) { echo '<button type="submit">' . esc_html( $label ) . '</button>'; }
function wp_enqueue_style( ...$args ) { $GLOBALS['styles'][] = $args; }
function wp_enqueue_code_editor( $settings ) { $GLOBALS['editor_settings'] = $settings; return ( $GLOBALS['editor_disabled'] ?? false ) ? false : $settings; }
function wp_add_inline_script( $handle, $script ) { $GLOBALS['inline_scripts'][] = $script; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function current_user_can( $cap ) { return ! empty( $GLOBALS['caps'][ $cap ] ); }
function is_admin() { return $GLOBALS['admin']; }
function wp_is_recovery_mode() { return 'native-recovery' === $GLOBALS['scenario']; }
function wp_doing_cron() { return false; }
function did_action() { return 0; }
function plugin_dir_path( $path ) { return dirname( $path ) . '/'; }
function plugin_basename( $path ) { return basename( $path ); }
function wp_unslash( $text ) { return $text; }
function wp_check_invalid_utf8( $text, $strip = false ) { return preg_match( '//u', $text ) ? $text : ''; }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function wp_html_excerpt( $text, $length, $suffix = '' ) { return substr( $text, 0, $length ) . ( strlen( $text ) > $length ? $suffix : '' ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_verify_nonce( $nonce, $action ) { return hash_equals( 'valid-' . $action, $nonce ); }
function get_option( $name, $default = false ) {
	if ( 'marrison_addon_modules' === $name ) { return $GLOBALS['modules']; }
	if ( 'marrison_addon_php_snippets_schema' === $name ) { return '1'; }
	return $default;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][ $priority ][] = $callback; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { add_action( $hook, $callback, $priority, $args ); }
function do_action( $hook ) {
	$callbacks = $GLOBALS['hooks'][ $hook ] ?? [];
	ksort( $callbacks );
	foreach ( $callbacks as $items ) { foreach ( $items as $callback ) { call_user_func( $callback ); } }
}
function apply_filters( $hook, $value ) {
	$callbacks = $GLOBALS['hooks'][ $hook ] ?? [];
	ksort( $callbacks );
	foreach ( $callbacks as $items ) { foreach ( $items as $callback ) { $value = call_user_func( $callback, $value ); } }
	return $value;
}
function add_shortcode( $tag, $callback ) { $GLOBALS['shortcodes'][ $tag ] = $callback; }
function test_dependency_failure() { marrison_missing_dependency_function(); }
function test_dependency_memory() { return str_repeat( 'x', 67108864 ); }

// wpdb fixture preserves per-row revision predicates and persists writes made
// by shutdown handlers, so the parent can inspect an actually crashed process.
class Snippets_Test_DB {
	public $prefix = 'test_';
	public $insert_id = 0;
	public $rows = [];
	public $queries = [];
	public $fail = false;
	private $file;
	public function __construct( $file ) { $this->file = $file; if ( $file ) { $this->rows = json_decode( file_get_contents( $file ), true ); } }
	private function flush() { if ( $this->file ) { file_put_contents( $this->file, json_encode( $this->rows ) ); } }
	public function prepare( $sql, ...$args ) {
		foreach ( $args as $arg ) { $sql = preg_replace_callback( '/%[ds]/', function ( $match ) use ( $arg ) { return '%d' === $match[0] ? (string) (int) $arg : "'" . addslashes( $arg ) . "'"; }, $sql, 1 ); }
		return $sql;
	}
	public function get_results( $sql, $format ) {
		$this->queries[] = $sql;
		$rows = array_values( $this->rows );
		return false !== strpos( $sql, 'WHERE active = 1' ) ? array_values( array_filter( $rows, function ( $row ) { return 1 === (int) $row['active']; } ) ) : $rows;
	}
	public function get_row( $sql, $format ) { preg_match( '/WHERE id = (\d+)/', $sql, $matches ); return $this->rows[ $matches[1] ] ?? null; }
	public function insert( $table, $data ) {
		if ( $this->fail ) { return false; }
		$this->insert_id = $this->rows ? max( array_keys( $this->rows ) ) + 1 : 1;
		$this->rows[ $this->insert_id ] = array_merge( [ 'id' => $this->insert_id ], $data );
		$this->flush(); return 1;
	}
	public function update( $table, $data, $where ) {
		if ( $this->fail ) { return false; }
		$id = $where['id'];
		if ( ! isset( $this->rows[ $id ] ) || $this->rows[ $id ]['revision'] !== $where['revision'] ) { return 0; }
		$this->rows[ $id ] = array_merge( $this->rows[ $id ], $data );
		$this->flush(); return 1;
	}
	public function delete( $table, $where ) {
		if ( $this->fail ) { return false; }
		$id = $where['id'];
		if ( ! isset( $this->rows[ $id ] ) || $this->rows[ $id ]['revision'] !== $where['revision'] ) { return 0; }
		unset( $this->rows[ $id ] ); $this->flush(); return 1;
	}
	public function query( $sql ) {
		if ( $this->fail ) { return false; }
		preg_match( "/revision = '([^']+)'/", $sql, $matches );
		foreach ( $this->rows as &$row ) { if ( $row['active'] ) { $row['active'] = 0; $row['revision'] = $matches[1]; } }
		$this->flush(); return 1;
	}
}
$wpdb = new Snippets_Test_DB( $state_file );
$plugin = dirname( __DIR__ ) . '/marrison-addon/';
require $plugin . 'marrison-addon.php';

if ( $child ) {
	if ( in_array( $scenario, [ 'bootstrap', 'frontend', 'backend' ], true ) ) {
		new Marrison_Addon_PHP_Snippets(); // Idempotent, despite an additional instance.
		if ( 'bootstrap' === $scenario ) {
			do_action( 'plugins_loaded' ); do_action( 'init' );
			check( isset( $GLOBALS['shortcodes']['test_snippet'] ), 'Shortcode was not registered.' );
			check( 'works' === call_user_func( $GLOBALS['shortcodes']['test_snippet'] ), 'Shortcode output mismatch.' );
			$callbacks = apply_filters( 'jet-engine/listings/allowed-callbacks', [] );
			check( isset( $callbacks['test_jet_callback'] ) && 'JET' === test_jet_callback( 'jet' ), 'JetEngine callback registration failed.' );
		}
	}
	if ( 'stale-fatal' === $scenario ) { $wpdb->rows[1]['revision'] = 'newer'; }
	if ( 'timeout' === $scenario ) { set_time_limit( 1 ); }
	if ( in_array( $scenario, [ 'deferred', 'dependency', 'memory', 'dependency-memory', 'timeout', 'stale-fatal' ], true ) ) { do_action( 'late_snippet_test' ); }
	if ( 'wp-shutdown' === $scenario ) { register_shutdown_function( function () { do_action( 'late_snippet_test' ); } ); }
	echo json_encode( [ 'trace' => $GLOBALS['trace'], 'queries' => $wpdb->queries, 'class_loaded' => class_exists( 'Marrison_Addon_PHP_Snippets', false ) ] );
	exit;
}

$checks = 0;
function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$GLOBALS['checks'] = ( $GLOBALS['checks'] ?? 0 ) + 1;
}
check( ! class_exists( 'Marrison_Addon_PHP_Snippets', false ), 'Disabled module was loaded.' );
check( [] === $wpdb->queries, 'Disabled module queried snippet storage.' );
$definition = Marrison_Addon::get_module_definitions( false )['php_snippets'];
check( 'early' === $definition['boot'] && ! $definition['requires_elementor'], 'Module boot contract is incorrect.' );
require $plugin . 'includes/modules/php-snippets/class-php-snippets-store.php';
require $plugin . 'includes/modules/php-snippets/class-php-snippets-runtime.php';
require $plugin . 'includes/modules/php-snippets/class-php-snippets-admin.php';
$store = new Marrison_Addon_PHP_Snippets_Store();
$admin = new Marrison_Addon_PHP_Snippets_Admin( $store );

foreach ( [ 'echo "ok";', 'function local_test() { return 1; }', 'namespace Custom; function callback() {}', 'declare(strict_types=1); $x = 1;', '$x = "<?php ?>";' ] as $code ) {
	check( true === $store::validate( $code ), 'Valid PHP was rejected: ' . $code );
}
foreach ( [ '', 'function () {', '$x = ;', 'echo "ok"; ?>html', '__halt_compiler();' ] as $code ) {
	check( is_wp_error( $store::validate( $code ) ), 'Invalid/unsupported PHP was accepted: ' . $code );
}
check( '$x = 1;' === $store::normalize_code( '<?php $x = 1;' ), 'Optional opening tag was not normalized.' );
check( '$x = "<script>untouched</script>";' === $store::normalize_code( '$x = "<script>untouched</script>";' ), 'PHP content was sanitized.' );
function post_action( $admin, $operation, $id = 0, $extra = [] ) {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = array_merge( [ 'operation' => $operation, 'id' => (string) $id, '_wpnonce' => 'valid-marrison_php_snippets_' . $operation . '_' . $id ], $extra );
	return $admin->process_action();
}
$id = post_action( $admin, 'save', 0, [ 'name' => 'First', 'code' => 'add_shortcode("kept", function(){ return "<b>ok</b>"; });', 'scope' => 'everywhere' ] );
check( 1 === $id && ! $store->get( $id )['active'], 'Create must save an inactive snippet.' );
$row = $store->get( $id );
check( 1 === post_action( $admin, 'activate', $id, [ 'revision' => $row['revision'] ] ) && 1 === $store->get( $id )['active'], 'Activation failed.' );
check( is_wp_error( post_action( $admin, 'deactivate', $id, [ 'revision' => $row['revision'] ] ) ), 'Stale action must fail.' );
$row = $store->get( $id );
check( 1 === post_action( $admin, 'save', $id, [ 'name' => 'Edited', 'code' => '$x = "kept";', 'scope' => 'backend', 'revision' => $row['revision'] ] ), 'Edit failed.' );
$row = $store->get( $id );
check( 'Edited' === $row['name'] && 'backend' === $row['scope'] && ! $row['active'], 'Edit must preserve code and deactivate.' );
check( is_wp_error( post_action( $admin, 'save', $id, [ 'name' => 'Bad', 'code' => 'syntax???', 'scope' => 'backend', 'revision' => $row['revision'] ] ) ) && $row === $store->get( $id ), 'Syntax error changed stored code.' );
check( is_wp_error( post_action( $admin, 'save', 0, [ 'name' => 'Bad scope', 'code' => 'return;', 'scope' => 'bogus' ] ) ), 'Invalid scope accepted.' );
$copy = post_action( $admin, 'duplicate', $id, [ 'revision' => $row['revision'] ] );
check( 2 === $copy && ! $store->get( $copy )['active'] && $row['code'] === $store->get( $copy )['code'], 'Duplicate must retain code and be inactive.' );
check( 1 === post_action( $admin, 'deactivate', $id, [ 'revision' => $row['revision'] ] ), 'Deactivation failed.' );
$row = $store->get( $id );
check( is_wp_error( post_action( $admin, 'delete', $id, [ 'revision' => $row['revision'], '_wpnonce' => 'invalid' ] ) ) && null !== $store->get( $id ), 'Invalid nonce deleted snippet.' );
foreach ( [ 'manage_options', 'unfiltered_html' ] as $cap ) {
	$GLOBALS['caps'][ $cap ] = false;
	check( ! $store::can_manage(), 'Missing capability must deny access.' );
	check( is_wp_error( post_action( $admin, 'delete', $id, [ 'revision' => $row['revision'] ] ) ), 'Missing capability permitted a mutation.' );
	check( empty( marrison_addon_protect_php_snippets_toggle( [ 'php_snippets' => 1, 'cursor' => 1 ] )['php_snippets'] ), 'Restricted user enabled PHP module.' );
	$GLOBALS['caps'][ $cap ] = true;
}
$GLOBALS['modules'] = [ 'php_snippets' => 1 ]; $GLOBALS['caps']['unfiltered_html'] = false;
$protected = marrison_addon_protect_php_snippets_toggle( [ 'php_snippets' => 0, 'cursor' => 1 ] );
check( 1 === $protected['php_snippets'] && 1 === $protected['cursor'], 'Module protection changed another module or allowed unauthorized disable.' );
$GLOBALS['caps']['unfiltered_html'] = true;
check( 1 === post_action( $admin, 'delete', $id, [ 'revision' => $row['revision'] ] ) && null === $store->get( $id ), 'Delete failed.' );
$copyrow = $store->get( $copy );
$wpdb->rows[ $copy ]['code'] = '$bad = ;';
check( is_wp_error( post_action( $admin, 'activate', $copy, [ 'revision' => $copyrow['revision'] ] ) ) && ! $store->get( $copy )['active'], 'Activation accepted corrupt/invalid stored code.' );
$copyrow = $store->get( $copy );
post_action( $admin, 'save', $copy, [ 'name' => $copyrow['name'], 'code' => 'return;', 'scope' => 'everywhere', 'revision' => $copyrow['revision'] ] );
$copyrow = $store->get( $copy );
post_action( $admin, 'activate', $copy, [ 'revision' => $copyrow['revision'] ] );
check( true === post_action( $admin, 'deactivate_all' ) && ! $store->get( $copy )['active'], 'Recovery deactivate-all failed.' );
$wpdb->rows[ $copy ]['code'] = '$x = "</textarea><script>alert(1)</script>";';
$wpdb->rows[ $copy ]['name'] = '<script>name</script>';
$wpdb->rows[ $copy ]['last_error'] = '<img src=x onerror=alert(1)>';
$_GET['page'] = 'marrison_addon_php_snippets'; $_GET['edit'] = $copy;
$fresh_admin = new Marrison_Addon_PHP_Snippets_Admin( $store );
ob_start(); $fresh_admin->render(); $html = ob_get_clean();
check( false === strpos( $html, '<script>name</script>' ) && false === strpos( $html, '<img src=x onerror=alert(1)>' ) && false !== strpos( $html, '&lt;/textarea&gt;&lt;script&gt;' ), 'Stored code/name/error escaped incorrectly in the admin page.' );
check( 5 === substr_count( $html, '<form ' ) && false !== strpos( $html, 'MARRISON_ADDON_PHP_SNIPPETS_SAFE_MODE' ), 'CRUD/recovery controls missing from rendered page.' );
$GLOBALS['styles'] = $GLOBALS['inline_scripts'] = [];
$_GET['page'] = 'unrelated'; $fresh_admin->assets();
check( [] === $GLOBALS['styles'] && [] === $GLOBALS['inline_scripts'], 'Assets leaked onto another screen.' );
$_GET['page'] = 'marrison_addon_php_snippets'; $GLOBALS['caps']['unfiltered_html'] = false; $fresh_admin->assets();
check( [] === $GLOBALS['styles'], 'Assets loaded for a restricted user.' );
$GLOBALS['caps']['unfiltered_html'] = true; $fresh_admin->assets();
check( 1 === count( $GLOBALS['styles'] ) && true === $GLOBALS['editor_settings']['codemirror']['mode']['startOpen'] && false !== strpos( $GLOBALS['inline_scripts'][0], 'wp.codeEditor.initialize' ), 'PHP CodeMirror initialization is missing.' );
if ( getenv( 'MARRISON_SNIPPETS_HTML' ) ) { file_put_contents( getenv( 'MARRISON_SNIPPETS_HTML' ), json_encode( [ 'html' => $html, 'script' => $GLOBALS['inline_scripts'][0] ] ) ); }
$GLOBALS['editor_disabled'] = true; $GLOBALS['inline_scripts'] = []; $fresh_admin->assets();
check( [] === $GLOBALS['inline_scripts'], 'Disabled syntax highlighting preference was ignored.' );
unset( $_GET['page'], $_GET['edit'] );
$wpdb->fail = true;
check( is_wp_error( post_action( $admin, 'save', 0, [ 'name' => 'Failed write', 'code' => 'return;', 'scope' => 'frontend' ] ) ), 'Failed DB write reported success.' );
$wpdb->fail = false;

function snippet_row( $id, $code, $scope = 'everywhere', $active = 1 ) {
	return [ 'id' => $id, 'name' => 'Fixture ' . $id, 'code' => $code, 'scope' => $scope, 'active' => $active, 'revision' => str_repeat( dechex( $id ), 32 ), 'last_error' => '' ];
}
function run_child( $scenario, $rows ) {
	$file = tempnam( sys_get_temp_dir(), 'marrison-snippets-' );
	file_put_contents( $file, json_encode( $rows ) );
	$process = proc_open( [ PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=0', __FILE__, '--child', $scenario, $file ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	fclose( $pipes[0] ); $output = stream_get_contents( $pipes[1] ); fclose( $pipes[1] );
	$error = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
	$status = proc_close( $process );
	$stored = json_decode( file_get_contents( $file ), true ); unlink( $file );
	return [ 'status' => $status, 'out' => json_decode( $output, true ), 'error' => $error . $output, 'stored' => $stored ];
}
$scope_rows = [
	1 => snippet_row( 1, '$GLOBALS["trace"][] = "all";' ),
	2 => snippet_row( 2, '$GLOBALS["trace"][] = "front";', 'frontend' ),
	3 => snippet_row( 3, '$GLOBALS["trace"][] = "back";', 'backend' ),
	4 => snippet_row( 4, '$GLOBALS["trace"][] = "inactive";', 'everywhere', 0 ),
];
foreach ( [ 'frontend' => [ 'all', 'front' ], 'backend' => [ 'all', 'back' ] ] as $context => $expected ) {
	$result = run_child( $context, $scope_rows );
	check( 0 === $result['status'] && $expected === ( $result['out']['trace'] ?? null ), 'Execution scope/once-only failed: ' . $context . ' ' . $result['error'] );
	check( 1 === count( $result['out']['queries'] ), 'Normal request must perform one active-code query.' );
}
foreach ( [ 'disabled', 'recovery', 'dashboard', 'toggle', 'login', 'safe', 'native-recovery' ] as $context ) {
	$result = run_child( $context, [ 1 => snippet_row( 1, 'trigger_error("must not execute", E_USER_ERROR);' ) ] );
	check( 0 === $result['status'] && [] === ( $result['out']['trace'] ?? null ) && [] === $result['out']['queries'], 'Recovery bypass failed: ' . $context . ' ' . $result['error'] );
	check( 1 === $result['stored'][1]['active'], 'Request bypass must not mutate activation state.' );
}
$code = '$GLOBALS["trace"][]="load"; add_action("plugins_loaded", function(){$GLOBALS["trace"][]="plugins_loaded";}, -100); add_action("init", function(){$GLOBALS["trace"][]="init";}); add_shortcode("test_snippet", function(){return "works";}); function test_jet_callback($value){return strtoupper($value);} add_filter("jet-engine/listings/allowed-callbacks", function($callbacks){$callbacks["test_jet_callback"]="Test";return $callbacks;});';
$result = run_child( 'bootstrap', [ 1 => snippet_row( 1, $code ) ] );
check( 0 === $result['status'] && [ 'load', 'plugins_loaded', 'init' ] === ( $result['out']['trace'] ?? null ), 'Early hooks, shortcodes or JetEngine callbacks failed: ' . $result['error'] );
$result = run_child( 'retry', [ 1 => snippet_row( 1, 'declare(strict_types=1); namespace SnippetFixture; function callback() { return "namespace"; } $GLOBALS["trace"][] = callback();' ) ] );
check( 0 === $result['status'] && [ 'namespace' ] === ( $result['out']['trace'] ?? null ), 'Native namespace/strict_types compilation failed: ' . $result['error'] );
$failures = [
	'load-error' => 'marrison_missing_function();',
	'load-invalid-utf8' => 'throw new Exception("\xff");',
	'compile' => 'break;',
	'parse' => '$broken = ;',
	'deferred' => 'add_action("late_snippet_test", function(){marrison_missing_function();});',
	'dependency' => 'add_action("late_snippet_test", function(){test_dependency_failure();});',
	'user-fatal' => 'trigger_error("fixture fatal", E_USER_ERROR);',
	'memory' => 'add_action("late_snippet_test", function(){$x = str_repeat("x", 67108864);});',
	'timeout' => 'add_action("late_snippet_test", function(){while(true){}});',
];
foreach ( $failures as $context => $code ) {
	$result = run_child( $context, [ 1 => snippet_row( 1, $code ), 2 => snippet_row( 2, '$GLOBALS["trace"][]="healthy";' ) ] );
	check( 0 !== $result['status'], 'Failure fixture did not crash: ' . $context );
	check( 0 === $result['stored'][1]['active'] && '' !== $result['stored'][1]['last_error'], 'Failed revision was not quarantined: ' . $context . ' ' . $result['error'] );
	check( 1 === $result['stored'][2]['active'], 'Healthy snippet quarantined with identifiable failure: ' . $context );
	$retry = run_child( 'retry', $result['stored'] );
	check( 0 === $retry['status'] && [ 'healthy' ] === ( $retry['out']['trace'] ?? null ), 'Failed snippet retried on next request: ' . $context . ' ' . $retry['error'] );
}
$result = run_child( 'dependency-memory', [ 1 => snippet_row( 1, 'add_action("late_snippet_test", function(){test_dependency_memory();});' ), 2 => snippet_row( 2, 'return;' ) ] );
check( 0 !== $result['status'] && ! $result['stored'][1]['active'] && ! $result['stored'][2]['active'], 'Unattributed resource exhaustion did not suspend loaded snippets.' );
$result = run_child( 'stale-fatal', [ 1 => snippet_row( 1, 'add_action("late_snippet_test", function(){marrison_missing_function();});' ) ] );
check( 0 !== $result['status'] && 1 === $result['stored'][1]['active'], 'An older failed request quarantined a newer revision.' );
echo "PHP Snippets: PASS ($checks checks; CRUD/capabilities/nonces/scopes/early hooks/shortcode/JetEngine contract/native fatal processes/retry/recovery).\n";
