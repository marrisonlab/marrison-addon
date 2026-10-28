<?php
/** php tests/login-designer-php-test.php */
define( 'ABSPATH', __DIR__ );
$hooks = []; $reads = 0; $caps = [ 'manage_options'=>true, 'edit_css'=>true ];
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
function wp_unslash( $v ) { return $v; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function is_admin() { return false; }
class Marrison_Addon_Context { public static function is_login_request() { return false; } }
function add_action( $hook, $callback ) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_filter( $hook, $callback ) { add_action( $hook, $callback ); }
function sanitize_hex_color( $v ) { return preg_match( '/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $v ) ? $v : null; }
function esc_url_raw( $v, $protocols = [] ) { return preg_match( '~^https?://~', $v ) ? filter_var( $v, FILTER_SANITIZE_URL ) : ''; }
function sanitize_text_field( $v ) { return strip_tags( $v ); }
function wp_strip_all_tags( $v ) { return strip_tags( $v ); }
function get_option( $key, $default ) { ++$GLOBALS['reads']; return [ 'layout'=>'split', 'unexpected'=>'bad', 'form_width'=>99999 ]; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['caps'][$cap] ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
class WP_Customize_Color_Control { public function __construct( $m, $id, $args ) {} }
class WP_Customize_Image_Control extends WP_Customize_Color_Control {}
class Manager {
    public $settings = []; public $controls = [];
    public function unsanitized_post_values() { return []; }
    public function add_panel( $id, $a ) {}
    public function add_section( $id, $a ) {}
    public function add_setting( $id, $a ) { $this->settings[$id] = (object) $a; }
    public function get_setting( $id ) { return $this->settings[$id]; }
    public function add_control( $id, $args = [] ) { $this->controls[] = $id; }
}
function verify( $condition, $message ) { if ( ! $condition ) { throw new Exception( $message ); } }
require __DIR__ . '/../marrison-addon/includes/modules/class-marrison-addon-login-designer.php';
verify( [] === $hooks, 'Including the class must not register hooks.' );
$m = new Marrison_Addon_Login_Designer();
foreach ( array_keys( $hooks ) as $hook ) { verify( ! preg_match( '/authenticate|login_redirect|set_auth_cookie|wp_authenticate/', $hook ), 'No authentication changes' ); }
$c = 'Marrison_Addon_Login_Designer';
verify( 800 === $c::sanitize_value( 99999, 'form_width' ), 'Clamp maximum' );
verify( 240 === $c::sanitize_value( -1, 'form_width' ), 'Clamp minimum' );
verify( 'center' === $c::sanitize_value( 'bad; color:red', 'layout' ), 'Reject CSS injection' );
verify( '#f0f0f1' === $c::sanitize_value( 'red;} body{}', 'page_bg' ), 'Reject invalid colors' );
verify( '' === $c::sanitize_value( 'javascript:alert(1)', 'logo_url' ), 'Reject unsafe URL' );
verify( false === $c::sanitize_value( 'false', 'logo_enabled' ), 'Parse false checkbox' );
verify( false === strpos( $c::sanitize_value( '</style><script>alert(1)</script>', 'custom_css' ), '<' ), 'CSS cannot escape style element' );
$s = $m->settings(); $m->settings(); verify( 1 === $reads, 'Cache option within request' );
verify( ! isset( $s['unexpected'] ) && 800 === $s['form_width'], 'Normalize persisted options' );
foreach ( $c::presets() as $name => $preset ) {
    verify( array_keys( $preset ) === array_keys( $c::defaults() ), 'Every preset is complete' );
    foreach ( $preset as $key => $value ) { verify( $value === $c::sanitize_value( $value, $key ), 'Valid preset: ' . $name . '/' . $key ); }
    verify( false !== strpos( $c::variables( $preset ), 'body.login{' ), 'Login-scoped variables' );
}
$_GET['marrison_login_preview'] = '1';
$manager = new Manager(); $m->customize( $manager );
foreach ( $c::schema() as $key => $field ) {
    $setting = $manager->settings[$c::OPTION . '[' . $key . ']'];
    verify( 'option' === $setting->type && 'postMessage' === $setting->transport, 'One option and live transport' );
}
$caps['edit_css'] = false; $manager = new Manager(); $m->customize( $manager );
verify( ! isset( $manager->settings[$c::OPTION . '[custom_css]'] ), 'Restrict CSS capability' );
$caps['manage_options'] = false; $manager = new Manager(); $m->customize( $manager );
verify( [] === $manager->settings, 'Restrict editor capability' );
echo "Login Designer: sanitization, presets, option cache, capability gates, hook isolation and Customizer contracts PASS\n";

$alpha = $c::defaults(); $alpha['input_bg'] = '#abc'; $alpha['input_opacity'] = 25;
verify( false !== strpos( $c::variables( $alpha ), '--mld-input-bg-alpha:rgba(170,187,204,0.25);' ), 'Server alpha matches live rendering' );
verify( 0 === $c::sanitize_value( -5, 'input_opacity' ) && 100 === $c::sanitize_value( 150, 'input_opacity' ), 'Clamp alpha bounds' );

verify( true === $m->display_language_dropdown( true ), 'Keep default language selector' );
verify( false === $m->display_language_dropdown( false ), 'Preserve another plugin hiding selector' );
$property = new ReflectionProperty( $m, 'settings' ); if ( PHP_VERSION_ID < 80100 ) { $property->setAccessible( true ); }
$hidden = $c::defaults(); $hidden['hide_language_dropdown'] = true; $property->setValue( $m, $hidden );
verify( false === $m->display_language_dropdown( true ), 'Hide selector when enabled' );
echo "Login Designer: native language dropdown filter PASS\n";

$caps['manage_options'] = true; $caps['edit_css'] = true; $_GET = [];
$unrelated = new Manager(); $m->customize( $unrelated );
verify( [] === $unrelated->settings, 'Do not register settings in unrelated Customizer' );
$_GET['url'] = 'https://example.test/?marrison_login_preview=1';
$dedicated = new Manager(); $m->customize( $dedicated );
verify( isset( $dedicated->settings[$c::OPTION . '[layout]'] ), 'Dedicated editor registers settings' );
echo "Login Designer: unrelated Customizer isolation PASS\n";

$_GET = []; $_POST = [];
foreach ( [ 'customize_changeset_data', 'customized' ] as $field ) {
    $_POST = [ $field=>json_encode( [ $c::OPTION . '[layout]'=>[ 'value'=>'split' ], $c::OPTION . '[hide_language_dropdown]'=>[ 'value'=>true ] ] ) ];
    $ajax = new Manager(); $m->customize( $ajax );
    verify( isset( $ajax->settings[$c::OPTION . '[layout]'], $ajax->settings[$c::OPTION . '[hide_language_dropdown]'] ), 'Register AJAX settings before changeset validation: ' . $field );
}
$_POST = [ 'customize_changeset_data'=>'{invalid' ];
$invalid = new Manager(); $m->customize( $invalid ); verify( [] === $invalid->settings, 'Malformed JSON does not activate module editor' );
$_POST = [ 'customize_changeset_data'=>[ 'invalid' ] ];
$invalid = new Manager(); $m->customize( $invalid ); verify( [] === $invalid->settings, 'Malformed POST type does not activate module editor' );
$_POST = [ 'customize_changeset_data'=>json_encode( [ 'blogname'=>[ 'value'=>'Example' ] ] ) ];
$other = new Manager(); $m->customize( $other ); verify( [] === $other->settings, 'Unrelated changeset stays isolated' );
$property->setValue( $m, $c::defaults() );
verify( 'https://example.test/' === $m->logo_url( 'https://wordpress.org/' ), 'Default logo links to site home' );
$custom = $c::defaults(); $custom['logo_enabled'] = true; $custom['logo_image'] = 'https://example.test/logo.png'; $custom['logo_url'] = 'https://example.test/custom';
$property->setValue( $m, $custom );
verify( 'https://example.test/custom' === $m->logo_url( 'https://wordpress.org/' ), 'Preserve explicit custom logo destination' );
echo "Login Designer: AJAX changeset registration, invalid payload isolation and logo URLs PASS\n";
