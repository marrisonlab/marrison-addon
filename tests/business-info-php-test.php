<?php
/** Native WP hooks/shortcodes + isolated option storage. No database or live site changes. */
$wordpress = $argv[1] ?? '';
if ( ! is_file( $wordpress . '/wp-includes/plugin.php' ) ) { exit( "Pass a WordPress source root.\n" ); }
error_reporting( E_ALL );
define( 'ABSPATH', rtrim( $wordpress, '/\\' ) . '/' );
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/shortcodes.php';
require ABSPATH . 'wp-includes/class-wp-error.php';
$options = []; $styles = []; $scripts = []; $caps = true;
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) { if ( ! empty( $GLOBALS['write_failure'] ) ) { return false; } $GLOBALS['options'][ $key ] = $value; return true; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $value : ''; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url_raw( $value ) { return preg_match( '#^https?://#i', $value ) ? $value : ''; }
function esc_url( $value ) { return esc_html( $value ); }
function __( $value ) { return $value; }
function esc_html__( $value ) { return esc_html( $value ); }
function wp_generate_uuid4() { return 'test-uuid-' . bin2hex( random_bytes( 8 ) ); }
function wp_attachment_is_image( $id ) { return $id === 123; }
function wp_get_attachment_image_url( $id ) { return $id === 123 ? 'https://example.test/logo.png' : false; }
function wp_get_attachment_image( $id ) { return '<img src="' . esc_url( wp_get_attachment_image_url( $id ) ) . '" alt="Logo">'; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Rome' ); }
function wp_date( $format, $timestamp ) { return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() )->format( $format ); }
function wp_enqueue_style( $name ) { $GLOBALS['styles'][] = $name; }
function wp_enqueue_script( $name ) { $GLOBALS['scripts'][] = $name; }
function wp_register_style() {}
function wp_register_script() {}
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function plugins_url( $path ) { return 'https://example.test/plugins/marrison-addon/' . $path; }
function wp_enqueue_media() {}
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value ) { $GLOBALS['transients'][ $key ] = $value; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function get_current_user_id() { return 1; }
function wp_localize_script( $handle, $name, $value ) { $GLOBALS['localized'][ $name ] = $value; }
function wp_nonce_field() { echo '<input type="hidden" name="_wpnonce" value="fixture">'; }
function check_admin_referer() { if ( 'fixture' !== ( $_POST['_wpnonce'] ?? '' ) ) { throw new LogicException( 'Invalid nonce' ); } }
function wp_die( $message ) { throw new LogicException( $message ); }
class Fixture_Redirect extends RuntimeException { public $url; public function __construct( $url ) { $this->url = $url; } }
function wp_safe_redirect( $url ) { throw new Fixture_Redirect( $url ); }
function is_admin() { return false; }
function current_user_can() { return $GLOBALS['caps']; }
function nocache_headers() { $GLOBALS['no_cache'] = true; }
function wp_unslash( $value ) { return $value; }
function wp_html_split( $text ) { return preg_split( '/(<[^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE ); }
class Fixture_JSON extends RuntimeException { public $data; public function __construct( $data ) { $this->data = $data; } }
function wp_send_json_success( $data ) { throw new Fixture_JSON( $data ); }
function marrison_cookie_site_text( $it, $en ) { return $it; }
class Marrison_Addon {
	public static $enabled = true;
	public static function is_module_enabled( $name ) { return self::$enabled; }
	public static function is_jet_engine_active() { return false; }
	public static function plugin_file() { return __DIR__ . '/../marrison-addon/marrison-addon.php'; }
	public static function asset_version( $file ) { return 'fixture'; }
}
$root = __DIR__ . '/../marrison-addon/includes/modules/';
require $root . 'class-marrison-addon-business-info.php';
$module = new Marrison_Addon_Business_Info(); do_action( 'init' );
require $root . 'cookie-manager/includes/class-policy-content.php';
require $root . 'business-info/class-business-info-jet-engine.php';
$jet = new Marrison_Addon_Business_Info_Jet_Engine();
function verify( $condition, $message ) { $GLOBALS['assertions'] = ( $GLOBALS['assertions'] ?? 0 ) + 1; if ( ! $condition ) { throw new RuntimeException( $message ); } }
function save( $data ) { $result = Marrison_Addon_Business_Info_Store::sanitize( $data ); if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); } update_option( 'marrison_business_info', $result ); return $result; }
function at( $time ) { return Marrison_Addon_Business_Hours::status( new DateTimeImmutable( $time, wp_timezone() ) ); }

$data = Marrison_Addon_Business_Info_Store::data();
verify( count( $data['contacts'] ) === 5 && $data['locations'][0]['key'] === 'sede_principale', 'Ready-made directory missing' );
verify( count( Marrison_Addon_Business_Info_Store::missing_privacy() ) === 3, 'Missing essential identity not detected' );
$data['company'] = [ 'legal_name' => 'Marrison & Figli', 'vat' => '12345678901', 'tax_code' => 'CF123', 'logo' => 123, 'description' => '<script>x</script>Descrizione' ];
$data['contacts'][0]['value'] = 'privacy@example.test'; $data['contacts'][1]['value'] = '+39 055 123';
$data['contacts'][] = [ 'id' => 'contact-admin-extra', 'key' => 'email_amministrazione', 'label' => 'Email amministrazione', 'type' => 'email', 'value' => 'admin@example.test', 'default' => false ];
$data['locations'][0] = array_merge( $data['locations'][0], [ 'street' => 'Via Roma 1', 'postcode' => '00100', 'city' => 'Roma', 'country' => 'Italia' ] );
$data['locations'][] = [ 'id' => 'location-shop-extra', 'key' => 'negozio', 'label' => 'Negozio', 'type' => 'shop', 'street' => 'Via Milano 2', 'city' => 'Milano', 'country' => 'Italia', 'default' => false ];
$data['custom'] = [ [ 'id' => 'custom-fiscal-year', 'key' => 'esercizio', 'label' => 'Esercizio', 'type' => 'number', 'value' => '2026' ] ];
$data['privacy'] = [ 'email_key' => 'email_amministrazione', 'location_key' => 'negozio', 'phone_key' => 'telefono_ufficio', 'dpo' => 'Responsabile privacy', 'representative' => 'Rappresentante UE' ];
$slots = [ [ 'start' => '09:00', 'end' => '14:00' ], [ 'start' => '16:00', 'end' => '20:00' ] ];
foreach ( range( 1, 5 ) as $day ) { $data['hours']['weekly'][ $day ] = [ 'open' => true, 'slots' => $slots ]; }
$data['hours']['exceptions'] = [ [ 'id' => 'exception-holiday', 'date' => '2026-12-25', 'label' => 'Festività', 'open' => false, 'slots' => [] ], [ 'id' => 'exception-sunday', 'date' => '2026-10-25', 'label' => 'Apertura straordinaria', 'open' => true, 'slots' => [ [ 'start' => '10:00', 'end' => '12:00' ] ] ] ];
$data = save( $data );
$options['marrison_cookie_privacy_profile'] = [ 'identity_source' => 'business_info' ];
if ( in_array( $argv[2] ?? '', [ '--fixture-admin', '--fixture-admin-empty' ], true ) ) {
	if ( '--fixture-admin-empty' === $argv[2] ) { unset( $options['marrison_business_info'] ); }
	require $root . 'business-info/class-business-info-admin.php';
	$admin = new Marrison_Addon_Business_Info_Admin(); $_GET['page'] = 'marrison-business-info'; $admin->assets();
	ob_start(); $admin->render(); $html = ob_get_clean();
	echo wp_json_encode( [ 'html' => $html, 'config' => $GLOBALS['localized']['marrisonBusinessInfoAdmin'] ] ); exit;
}
if ( '--fixture-front' === ( $argv[2] ?? '' ) ) {
	echo do_shortcode( '[marrison_business_hours group_days="yes"][marrison_business_status]' ) . Marrison_Addon_Business_Info_Display::dynamic_field( 'today' ); exit;
}
verify( strpos( $data['company']['description'], '<script>' ) === false, 'Stored HTML not stripped' );
verify( Marrison_Addon_Business_Info_Store::get( 'email_amministrazione' ) === 'admin@example.test', 'Universal alias missing' );
verify( Marrison_Addon_Business_Info_Store::get( 'locations.negozio.city' ) === 'Milano', 'Address component missing' );
verify( Marrison_Addon_Business_Info_Store::controller()['controller_email'] === 'admin@example.test', 'Privacy selection ignored' );
verify( Marrison_Addon_Business_Info_Store::controller()['controller_address'] === 'Via Milano 2, Milano, Italia', 'Selected address ignored' );
$data['privacy']['controller_name'] = 'Mario Rossi'; $data = save( $data );
$identity = Marrison_Addon_Business_Info_Store::controller();
verify( $identity['controller_name'] === 'Mario Rossi' && $identity['controller_email'] === 'admin@example.test' && $identity['controller_address'] === 'Via Milano 2, Milano, Italia', 'Controller name requires duplicated contact details' );
verify( strpos( Marrison_Addon_Cookie_Policy_Content::content( 'privacy' ), 'Mario Rossi' ) !== false, 'Always-editable controller name omitted from policy' );
$data['privacy']['different_controller'] = true; $data = save( $data );
verify( Marrison_Addon_Business_Info_Store::controller() === $identity, 'Empty dedicated fields erased shared company details' );
unset( $data['privacy']['controller_name'], $data['privacy']['different_controller'] ); $data = save( $data );
$data['contacts'] = array_reverse( $data['contacts'] );
foreach ( $data['contacts'] as &$row ) { if ( 'email_amministrazione' === $row['key'] ) { $row['key'] = 'changed_by_accident'; $row['value'] = 'new@example.test'; } } unset( $row );
$data = save( $data );
verify( Marrison_Addon_Business_Info_Store::get( 'email_amministrazione' ) === 'new@example.test' && Marrison_Addon_Business_Info_Store::get( 'changed_by_accident' ) === '', 'Stable key changed on reorder/update' );
$sources = apply_filters( 'jet-engine/listings/data/sources', [ 'object' => 'Object' ] );
verify( isset( $sources['marrison_business_info:contacts.email_amministrazione'] ) && $sources['object'] === 'Object', 'JetEngine readable source missing or existing sources changed' );
verify( apply_filters( 'jet-engine/listings/dynamic-field/field-value', null, [ 'dynamic_field_source' => 'marrison_business_info:contacts.email_amministrazione' ] ) === 'new@example.test', 'JetEngine current value missing' );
verify( $jet->value( 'original', [ 'dynamic_field_source' => 'object' ] ) === 'original', 'JetEngine unrelated source altered' );
verify( do_shortcode( '[marrison_info key="email_amministrazione" output="link"]' ) === '<a href="mailto:new@example.test">new@example.test</a>', 'Email link incorrect' );
verify( strpos( do_shortcode( '[marrison_info key="telefono_ufficio" output="link"]' ), 'tel:+39055123' ) !== false, 'Phone link incorrect' );
verify( do_shortcode( '[marrison_info key="missing" fallback="<img>"]' ) === '&lt;img&gt;', 'Fallback escaped incorrectly' );
verify( strpos( do_shortcode( '[marrison_info key="logo" output="html"]' ), '<img' ) !== false, 'Logo output missing' );
verify( do_shortcode( '[marrison_info key="esercizio"]' ) === '2026', 'Custom field missing' );
verify( at( '2026-10-26 09:00:00' )['state'] === 'open', 'Opening boundary incorrect' );
verify( at( '2026-10-26 13:59:59' )['state'] === 'open', 'Pre-break state incorrect' );
verify( at( '2026-10-26 14:00:00' )['state'] === 'pause', 'Lunch break incorrect' );
verify( at( '2026-10-26 16:00:00' )['state'] === 'open', 'Reopening boundary incorrect' );
verify( at( '2026-10-26 20:00:00' )['state'] === 'closed', 'Closing boundary incorrect' );
verify( at( '2026-10-25 11:00:00' )['state'] === 'open' && at( '2026-12-25 11:00:00' )['state'] === 'closed', 'Date exceptions not prioritized' );
$pause = at( '2026-10-26 15:00:00' );
verify( strpos( Marrison_Addon_Business_Hours::status_text( $pause ), 'Riapre alle 16:00' ) !== false, 'Pause message missing' );
verify( wp_date( 'H:i', $pause['next_open'] ) === '16:00' && wp_date( 'H:i', $pause['next_close'] ) === '20:00', 'Next opening/closing or WP timezone incorrect' );
verify( wp_date( 'Y-m-d H:i', at( '2026-10-24 21:00:00' )['next_open'] ) === '2026-10-25 10:00', 'DST transition next opening incorrect' );
$utc = new DateTimeImmutable( '2026-10-26 13:30:00', new DateTimeZone( 'UTC' ) );
verify( Marrison_Addon_Business_Hours::status( $utc )['state'] === 'pause', 'Caller timezone did not convert to WordPress timezone' );
verify( count( Marrison_Addon_Business_Hours::rows( [ 'group_days' => true ] ) ) === 2, 'Consecutive days not grouped' );
verify( count( Marrison_Addon_Business_Hours::rows( [ 'hide_closed' => true ] ) ) === 5, 'Closed days not hidden' );
verify( strpos( Marrison_Addon_Business_Hours::format_day( [ 'open' => true, 'slots' => $slots ], [ 'format' => '12' ] ), '4:00 pm' ) !== false, '12h output incorrect' );
foreach ( [ [ [ 'start' => '09:00', 'end' => '08:00' ] ], [ [ 'start' => '25:00', 'end' => '26:00' ] ], [ [ 'start' => '09:00', 'end' => '14:00' ], [ 'start' => '13:00', 'end' => '16:00' ] ] ] as $invalid ) {
	$bad = $data; $bad['hours']['weekly'][1]['slots'] = $invalid; verify( is_wp_error( Marrison_Addon_Business_Info_Store::sanitize( $bad ) ), 'Invalid/overlapping hours accepted' );
}
$bad = $data; $bad['contacts'][] = [ 'id' => 'duplicate-contact', 'key' => 'email_amministrazione', 'label' => 'Duplicate', 'type' => 'email', 'value' => 'new@example.test' ];
verify( is_wp_error( Marrison_Addon_Business_Info_Store::sanitize( $bad ) ), 'Duplicate key accepted' );
$bad = $data; $bad['custom'][0]['type'] = 'url'; $bad['custom'][0]['value'] = 'javascript:alert(1)';
verify( is_wp_error( Marrison_Addon_Business_Info_Store::sanitize( $bad ) ), 'Unsafe URL accepted' );
$bad = $data; $bad['hours']['exceptions'][] = $bad['hours']['exceptions'][0];
verify( is_wp_error( Marrison_Addon_Business_Info_Store::sanitize( $bad ) ), 'Duplicate exception accepted' );
$bad = $data; $bad['hours']['exceptions'][0]['date'] = '2026-02-30';
verify( is_wp_error( Marrison_Addon_Business_Info_Store::sanitize( $bad ) ), 'Invalid date accepted' );

$options['marrison_cookie_privacy_profile'] = [ 'identity_source' => 'business_info', 'controller_name' => 'OLD COPY', 'dpo' => 'OLD DPO', 'logs_retention' => '30 giorni' ];
$snapshot = Marrison_Addon_Cookie_Policy_Content::snapshot(); $consent = Marrison_Addon_Cookie_Policy_Content::consent_version( $snapshot );
verify( strpos( $snapshot['generated_privacy'], 'new@example.test' ) !== false && strpos( $snapshot['generated_privacy'], 'OLD COPY' ) === false, 'Policy uses legacy identity copy' );
verify( $snapshot['privacy_profile']['dpo'] === 'Responsabile privacy', 'DPO not sourced from Business Info' );
$data['company']['legal_name'] = 'Azienda aggiornata'; $data = save( $data );
verify( strpos( Marrison_Addon_Cookie_Policy_Content::content( 'privacy' ), 'Azienda aggiornata' ) !== false, 'Policy not updated on next rendering' );
verify( Marrison_Addon_Cookie_Policy_Content::consent_version() === $consent, 'Company edit unnecessarily changed consent contract' );
verify( $snapshot['privacy_profile']['controller_name'] === 'Marrison & Figli' && Marrison_Addon_Cookie_Policy_Content::revision( $snapshot ) !== Marrison_Addon_Cookie_Policy_Content::revision(), 'Historical snapshot mutated or new evidence failed to change' );
verify( Marrison_Addon_Cookie_Policy_Content::stored_profile()['controller_name'] === 'OLD COPY', 'Reading Business Info replaced the saved manual identity' );
$data['contacts'] = array_values( array_filter( $data['contacts'], static function( $r ) { return 'email_amministrazione' !== $r['key']; } ) ); $data = save( $data );
verify( Marrison_Addon_Business_Info_Store::controller()['controller_email'] === '', 'Deleting selected contact silently rebound privacy to a different contact' );
verify( in_array( 'Email per la privacy', Marrison_Addon_Business_Info_Store::missing_privacy(), true ), 'Missing deleted contact not reported' );
$data['privacy'] = [ 'different_controller' => true, 'controller_name' => 'Altro titolare', 'controller_email' => 'other@example.test', 'dpo' => 'Altro DPO' ]; $data = save( $data );
verify( Marrison_Addon_Cookie_Policy_Content::profile()['controller_name'] === 'Altro titolare' && Marrison_Addon_Cookie_Policy_Content::profile()['dpo'] === 'Altro DPO', 'Separate controller not resolved' );
Marrison_Addon::$enabled = false;
verify( Marrison_Addon_Cookie_Policy_Content::profile()['controller_name'] === '' && strpos( Marrison_Addon_Cookie_Policy_Content::content( 'privacy' ), 'Privacy Policy' ) !== false, 'Disabled Business Info breaks Cookie Manager' );
Marrison_Addon::$enabled = true;
$options['marrison_business_info'] = [ 'hours' => [ 'weekly' => [], 'exceptions' => [ [ 'date' => '2035-01-01', 'open' => true, 'slots' => [ [ 'start' => '10:00', 'end' => '12:00' ] ] ] ] ] ];
verify( wp_date( 'Y-m-d', at( '2026-10-24 00:00' )['next_open'] ) === '2035-01-01', 'Distant one-off opening not found' );
unset( $options['marrison_business_info'] );
$empty_policy = Marrison_Addon_Cookie_Policy_Content::content( 'privacy' );
verify( strpos( $empty_policy, 'Privacy Policy' ) !== false && strpos( $empty_policy, 'OLD COPY' ) === false && strpos( $empty_policy, 'Contatto privacy: </p>' ) === false, 'Empty directory blocks publication or publishes broken identity' );
$html = do_shortcode( '[marrison_business_hours][marrison_business_status]' );
verify( substr_count( $html, 'data-state="unknown"' ) === 2 && strpos( $html, 'Aperto ora' ) === false, 'Cached HTML embeds an opening claim' );
verify( in_array( 'marrison-business-hours', $styles, true ) && in_array( 'marrison-business-hours', $scripts, true ), 'Display assets not enqueued on use' );
$_GET = [ 'field' => 'today' ];
try { Marrison_Addon_Business_Info_Display::ajax(); } catch ( Fixture_JSON $response ) { verify( ! empty( $GLOBALS['no_cache'] ) && isset( $response->data['value'], $response->data['status'] ), 'Public state not fresh or malformed' ); }
require_once $root . 'business-info/class-business-info-admin.php';
$admin = new Marrison_Addon_Business_Info_Admin();
$input = Marrison_Addon_Business_Info_Store::data(); $input['company']['legal_name'] = 'Salvataggio sicuro';
$_POST = [ '_wpnonce' => 'fixture', 'business_info_payload' => wp_json_encode( $input ) ];
$caps = false;
try { $admin->save(); throw new RuntimeException( 'Unauthorized save accepted' ); } catch ( LogicException $error ) { verify( ! isset( $options['marrison_business_info'] ), 'Unauthorized write changed data' ); }
$caps = true; $_POST['_wpnonce'] = 'invalid';
try { $admin->save(); throw new RuntimeException( 'Invalid nonce accepted' ); } catch ( LogicException $error ) { verify( ! isset( $options['marrison_business_info'] ), 'Invalid nonce changed data' ); }
$_POST['_wpnonce'] = 'fixture';
try { $admin->save(); } catch ( Fixture_Redirect $redirect ) { verify( strpos( $redirect->url, 'saved=1' ) !== false && $options['marrison_business_info']['company']['legal_name'] === 'Salvataggio sicuro', 'Valid admin save failed' ); }
$before = $options['marrison_business_info'];
$input['company']['legal_name'] = 'Modifiche da recuperare'; $_POST['business_info_payload'] = wp_json_encode( $input ); $GLOBALS['write_failure'] = true;
try { $admin->save(); } catch ( Fixture_Redirect $redirect ) { verify( strpos( $redirect->url, 'invalid=1' ) !== false && $options['marrison_business_info'] === $before, 'Database failure reported success or changed data' ); }
$GLOBALS['write_failure'] = false;
verify( get_transient( 'marrison_bi_draft_1' )['data']['company']['legal_name'] === 'Modifiche da recuperare', 'Failed save lost submitted draft' );
$_POST['business_info_payload'] = '{broken';
try { $admin->save(); } catch ( Fixture_Redirect $redirect ) { verify( strpos( $redirect->url, 'invalid=1' ) !== false && $options['marrison_business_info'] === $before, 'Malformed payload cleared saved data' ); }
echo 'PASS Business Info: ' . $GLOBALS['assertions'] . " assertions; stable IDs, selectors, sanitization, shortcodes, intervals, DST, exceptions, dynamic policies, snapshots and no-cache state.\n";
