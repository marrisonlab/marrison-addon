<?php
/** Run: php tests/cookie-shortcodes-php-test.php <wordpress-root> [--fixture-wizard|--fixture-policy]. */
if (empty($argv[1]) || !is_file($argv[1] . '/wp-includes/shortcodes.php')) {
    throw new RuntimeException('Pass an installed WordPress root for its native shortcode and hook APIs.');
}
define('ABSPATH', rtrim($argv[1], '/\\') . '/');
define('MARRISON_COOKIE_PLUGIN_DIR', __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/');
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/shortcodes.php';

$options = array('marrison_wizard_current_step' => 5, 'marrison_cookie_show_banner' => false,
    'marrison_cookie_privacy_policy_content' => '<h1>Privacy Policy</h1><p>Test &lt;Site&gt;: informativa approvata.</p>',
    'marrison_cookie_cookie_policy_content' => '<h1>Cookie Policy</h1><p>Testo cookie approvato per questo sito.</p>');
$options['marrison_cookie_privacy_profile'] = array(
    'identity_source' => 'business_info', 'controller_name' => 'Manual Controller', 'controller_email' => 'manual@example.test',
    'contact_enabled' => true, 'logs_enabled' => true, 'logs_retention' => '30 giorni', 'recipients' => 'Hosting ed email', 'transfers' => 'none');
$options['marrison_business_info'] = array('company' => array('legal_name' => 'Test & Site'), 'contacts' => array(array('key' => 'privacy', 'type' => 'email', 'value' => 'privacy@example.test')), 'locations' => array(array('key' => 'sede', 'street' => 'Via Esempio 1', 'country' => 'Italia')));
class Marrison_Addon { public static $business_enabled = true; public static function is_module_enabled($module) { return self::$business_enabled && $module === 'business_info'; } }
require __DIR__ . '/../marrison-addon/includes/modules/business-info/class-business-info-store.php';
$english = false;
$can_manage = true;
$post_content = '';
$elementor_data = '';
function get_option($key, $default = false) { global $options; return array_key_exists($key, $options) ? $options[$key] : $default; }
function update_option($key, $value) { global $options; $options[$key] = $value; }
function delete_option($key) { global $options; unset($options[$key]); }
function current_user_can() { global $can_manage; return $can_manage; }
function check_ajax_referer($action, $key) { if (($_POST[$key] ?? '') !== 'fixture') { throw new RuntimeException('Invalid nonce'); } }
function wp_unslash($value) { return $value; }
function current_time() { return '2026-10-17 15:00:00'; }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function home_url() { return 'https://example.test'; }
function get_bloginfo() { return 'Test <Site>'; }
function get_post() { global $post_content; return (object) array('post_content' => $post_content); }
function get_post_meta() { global $elementor_data; return $elementor_data; }
function get_queried_object_id() { return 42; }
function get_the_ID() { return 42; }
function is_singular() { return true; }
function is_page($id = null) { return $id === null || $id === 42; }
function url_to_postid() { return 42; }
function is_admin() { return false; }
function is_feed() { return false; }
function marrison_cookie_is_english_site() { global $english; return $english; }
function marrison_cookie_site_text($it, $en) { return marrison_cookie_is_english_site() ? $en : $it; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_textarea($text) { return esc_html($text); }
function esc_url($text) { return esc_html($text); }
function esc_url_raw($text) { return preg_match('#^https?://#i', $text) ? trim($text) : ''; }
function sanitize_key($text) { return strtolower($text); }
function sanitize_text_field($text) { return strip_tags($text); }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="fixture">'; }
function __($text) { return $text; }
function _e($text) { echo esc_html($text); }
function esc_html_e($text) { echo esc_html($text); }
function esc_attr__($text) { return esc_attr($text); }
function selected($value, $expected) { if ($value === $expected) { echo 'selected'; } }
function checked($value, $expected) { if ($value === $expected) { echo 'checked'; } }
function disabled($value, $expected) { if ($value === $expected) { echo 'disabled'; } }
function wp_html_split($text) { return preg_split('/(<[^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE); }
function wp_date($format, $timestamp) { return gmdate($format, $timestamp); }
function wp_insert_post() { throw new RuntimeException('Policy setup must never create a WordPress page'); }
class ShortcodeJsonResponse extends RuntimeException {
    public $success, $data;
    public function __construct($success, $data) { $this->success = $success; $this->data = $data; parent::__construct('JSON response'); }
}
function wp_send_json_success($data) { throw new ShortcodeJsonResponse(true, $data); }
function wp_send_json_error($data) { throw new ShortcodeJsonResponse(false, $data); }
function verify($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function ajax($action, $data) {
    $_POST = $data;
    try { do_action($action); } catch (ShortcodeJsonResponse $response) { return $response; }
    throw new RuntimeException('Missing AJAX response');
}
class Marrison_Cookie_Scanner {
    public static $cookies = array();
    public static function auto_scan_enabled() { return get_option('marrison_cookie_auto_scan', false) && get_option('marrison_cookie_auto_scan_confirmed', false); }
    public static function get_instance() { return new self(); }
    public function get_cookies() { return self::$cookies; }
    public function get_categories() { return array('necessary' => 'Necessari'); }
}
require __DIR__ . '/cookie-test-support.php';
require MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-cookie-banner.php';
require MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-setup-wizard.php';
$banner = Marrison_Cookie_Banner::get_instance();
$wizard = Marrison_Setup_Wizard::get_instance();
require MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-admin-settings.php';
Marrison_Cookie_Scanner::$cookies = array((object) array('cookie_name' => 'current_scan', 'cookie_category' => 'necessary', 'cookie_description' => '<script>bad</script>', 'cookie_expiration_status' => 'session', 'cookie_value' => 'PRIVATE_VALUE'));

if (($argv[2] ?? '') === '--fixture-wizard') {
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
    include MARRISON_COOKIE_PLUGIN_DIR . 'templates/setup-wizard.php';
    echo '</body></html>';
    exit;
}
if (($argv[2] ?? '') === '--fixture-policy') {
    $post_content = '[marrison_cookie_policy_content]';
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main>' . do_shortcode($post_content) . '</main>';
    $banner->render_banner();
    echo '</body></html>';
    exit;
}
if (($argv[2] ?? '') === '--fixture-shortcodes') {
    $_GET['tab'] = 'shortcodes';
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
    Marrison_Cookie_Admin_Settings::get_instance()->render_settings_page();
    echo '</body></html>';
    exit;
}
if (in_array($argv[2] ?? '', array('--fixture-privacy', '--fixture-privacy-disabled'), true)) {
    if ($argv[2] === '--fixture-privacy-disabled') { Marrison_Addon::$business_enabled = false; }
    $_GET['tab'] = 'policies';
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
    Marrison_Cookie_Admin_Settings::get_instance()->render_settings_page();
    echo '</body></html>'; exit;
}

$_GET['tab'] = 'shortcodes';
ob_start();
Marrison_Cookie_Admin_Settings::get_instance()->render_settings_page();
$shortcodes_tab = ob_get_clean();
verify(strpos($shortcodes_tab, 'tab=shortcodes') !== false && substr_count($shortcodes_tab, 'class="marrison-shortcode-value"') === 3, 'Shortcode tab or copy fields missing');
verify(strpos($shortcodes_tab, 'name="marrison_save_settings"') === false, 'Read-only shortcode tab exposes settings submission');
unset($_GET['tab']);
$_GET['tab'] = 'banner'; ob_start(); Marrison_Cookie_Admin_Settings::get_instance()->render_settings_page(); $banner_tab = ob_get_clean(); unset($_GET['tab']);
verify(strpos($banner_tab, 'class="marrison-shortcode-value"') === false, 'Banner still duplicates shortcode controls');

verify(shortcode_exists('marrison_privacy_policy') && shortcode_exists('marrison_cookie_policy_content') && shortcode_exists('marrison_cookie_policy'), 'Public policy shortcodes missing');
foreach (array(false, true) as $locale) {
    $english = $locale;
    $privacy = do_shortcode('[marrison_privacy_policy]');
    $cookie = do_shortcode('[marrison_cookie_policy_content]');
    verify(strpos($privacy, '<strong>Privacy Policy</strong>') !== false && strpos($privacy, 'Test &amp; Site') !== false, 'Generated privacy or escaping missing');
    verify(!preg_match('/<h[1-6]\b/i', $privacy . $cookie), 'Shortcodes must not produce headings');
    verify(stripos($cookie, 'localStorage') !== false && strpos($cookie, 'current_scan') !== false, 'Generated Cookie Policy or current scan missing');
    verify(substr_count($cookie, 'class="marrison-cookie-policy-details"') === 1 && strpos($cookie, '[marrison_cookie_policy]') === false, 'Nested shortcode did not render once');
    verify(strpos($cookie, 'PRIVATE_VALUE') === false && strpos($cookie, '<script>bad') === false, 'Sensitive/unsafe cookie metadata published');
    verify(strpos($privacy . $cookie, 'href=""') === false, 'Unconfigured policy URL generated an empty link');
}
$options['marrison_cookie_privacy_policy_url'] = 'https://example.test/my-privacy';
$options['marrison_cookie_cookie_policy_url'] = 'https://example.test/my-cookies';
verify(strpos(do_shortcode('[marrison_privacy_policy]'), 'Test &amp; Site') !== false, 'Changing page URLs replaced generated policy content');
verify(stripos(do_shortcode('[marrison_cookie_policy_content]'), 'localStorage') !== false, 'Changing page URLs replaced generated Cookie content');
$table = do_shortcode('[marrison_cookie_policy]');
verify(strpos($table, 'current_scan') !== false && strpos($table, '<h1>') === false, 'Legacy table shortcode changed its meaning');
$before = $options;
$response = ajax('wp_ajax_marrison_wizard_create_pages', array('nonce' => 'fixture', 'create_privacy' => 'true', 'create_cookie' => 'true'));
verify(!$response->success && empty($response->data['pages']) && $response->data['shortcodes']['cookie'] === '[marrison_cookie_policy_content]' && $options === $before, 'Legacy request created pages, reported creation success or changed stored URLs');
$response = ajax('wp_ajax_marrison_wizard_finish', array('nonce' => 'fixture', 'data' => array('privacy_policy_url' => 'https://example.test/elementor-privacy', 'cookie_policy_url' => 'https://example.test/elementor-cookies')));
verify($response->success && $options['marrison_cookie_privacy_policy_url'] === 'https://example.test/elementor-privacy' && $options['marrison_cookie_cookie_policy_url'] === 'https://example.test/elementor-cookies', 'Wizard did not save chosen page URLs');
$before = $options;
$response = ajax('wp_ajax_marrison_wizard_finish', array('nonce' => 'fixture'));
verify($response->success && $options === $before, 'Finishing without URL fields overwrote existing settings');
$saved_profile = Marrison_Addon_Cookie_Policy_Content::stored_profile();
$options['marrison_cookie_privacy_profile'] = $saved_profile;
$response = ajax('wp_ajax_marrison_wizard_save_step', array('nonce' => 'fixture', 'step' => 6, 'data' => array('current_step' => 6)));
verify($response->success && $options['marrison_cookie_privacy_profile'] === $saved_profile, 'Skipping privacy changed the saved profile');
$response = ajax('wp_ajax_marrison_wizard_save_step', array('nonce' => 'fixture', 'step' => 5, 'data' => array('privacy_profile' => $saved_profile + array('other_processing' => '<script>bad</script>Account: art. 6.1.b, 12 mesi'))));
verify($response->success && strpos($options['marrison_cookie_privacy_profile']['other_processing'], '<script>') === false, 'Wizard profile was not sanitized/persisted');
verify(Marrison_Setup_Wizard::get_instance()->get_total_steps() === 6, 'Optional privacy wizard step missing');
$before = $options;
$can_manage = false;
$response = ajax('wp_ajax_marrison_wizard_finish', array('nonce' => 'fixture', 'data' => array('cookie_policy_url' => 'https://example.test/unapproved')));
verify(!$response->success && $options === $before, 'Unauthorized wizard request changed URLs');
$can_manage = true;
// Every field is optional: empty and partial profiles must still render.
$english = false;
$profile_before = $options['marrison_cookie_privacy_profile'];
foreach (array(array(), array('controller_name' => 'Example Controller'), array('controller_email' => 'privacy@example.test')) as $partial) {
    $options['marrison_cookie_privacy_profile'] = $partial;
    $partial_html = do_shortcode('[marrison_privacy_policy]');
    verify(strpos($partial_html, 'non è ancora stata completata') === false && strpos($partial_html, 'Privacy Policy') !== false, 'An optional empty field blocked the shortcode');
    foreach ($partial as $value) { verify(strpos($partial_html, $value) !== false, 'A populated field was omitted from the partial policy'); }
    verify(strpos($partial_html, '4. Trasferimenti internazionali') === false && strpos($partial_html, 'artt. 46') === false, 'An unspecified transfer was presented as declared');
    verify(strpos($partial_html, 'Conservazione: </li>') === false && strpos($partial_html, 'Destinatari o categorie di destinatari: .') === false, 'An empty field produced an empty statement');
}
verify(Marrison_Addon_Cookie_Policy_Content::required_fields() === array() && Marrison_Addon_Cookie_Policy_Content::missing() === array(), 'Optional fields remain mandatory');
ob_start(); include MARRISON_COOKIE_PLUGIN_DIR . 'templates/privacy-fields.php'; $profile_form = ob_get_clean();
$profile_dom = new DOMDocument(); @$profile_dom->loadHTML('<?xml encoding="utf-8" ?>' . $profile_form); $profile_xpath = new DOMXPath($profile_dom);
verify($profile_xpath->query('//*[@required or @aria-required="true"]')->length === 0 && strpos($profile_form, ' *') === false, 'Optional fields still have required attributes or markers');
verify(strpos($profile_form, 'Tutti i campi sono facoltativi') !== false && strpos($profile_form, 'non è ancora pubblicabile') === false, 'Form still reports a publication block');
$profile = $profile_before;
$profile['marketing_enabled'] = false;
$profile['automated'] = false;
$profile['unknown_field'] = 'must not persist';
$response = ajax('wp_ajax_marrison_save_settings', array('nonce' => 'fixture', 'settings' => array('privacy_profile' => $profile)));
verify($response->success && !isset($options['marrison_cookie_privacy_profile']['unknown_field']), 'Admin profile did not whitelist or persist fields');
$generated = do_shortcode('[marrison_privacy_policy]');
verify(strpos($generated, 'Newsletter e marketing') === false && strpos($generated, 'Hosting ed email') !== false && strpos($generated, 'artt. 15–22') !== false, 'Generated purposes, recipients or rights are incorrect');
$options['marrison_cookie_privacy_profile']['marketing_enabled'] = true;
verify(strpos(do_shortcode('[marrison_privacy_policy]'), 'Newsletter e marketing') !== false, 'Enabled newsletter not disclosed');
$options['marrison_cookie_privacy_profile']['transfers'] = 'scc';
$options['marrison_cookie_privacy_profile']['transfer_details'] = '';
verify(Marrison_Addon_Cookie_Policy_Content::missing() === array() && strpos(do_shortcode('[marrison_privacy_policy]'), 'Privacy Policy') !== false, 'Optional transfer details blocked the policy');
$options['marrison_cookie_privacy_profile'] = $profile_before;
$options['marrison_business_info'] = array('company' => array('legal_name' => 'Test & Site'), 'contacts' => array(array('key' => 'privacy', 'type' => 'email', 'value' => 'privacy@example.test')), 'locations' => array(array('key' => 'sede', 'street' => 'Via Esempio 1', 'country' => 'Italia')));
// Explicit source selection, independent module lifecycle, and no copying between stores.
$manual = array('identity_source' => 'manual', 'controller_name' => 'Manual <b>Controller</b>', 'controller_address' => 'Via Manuale 2, Italia', 'controller_email' => 'manual@example.test', 'controller_phone' => '+39 123', 'controller_vat' => '123', 'controller_tax_code' => 'CF123', 'dpo' => 'DPO manuale', 'representative' => 'Rappresentante manuale');
foreach (array(true, false) as $business_enabled) {
    Marrison_Addon::$business_enabled = $business_enabled;
    $response = ajax('wp_ajax_marrison_save_settings', array('nonce' => 'fixture', 'settings' => array('privacy_profile' => $manual)));
    $manual_saved = $options['marrison_cookie_privacy_profile'];
    verify($response->success && $manual_saved['controller_name'] === 'Manual Controller', 'Manual identity was not sanitized/saved');
    foreach (array('[marrison_privacy_policy]', '[marrison_cookie_policy_content]') as $shortcode) {
        $output = do_shortcode($shortcode);
        verify(strpos($output, 'Manual Controller') !== false && strpos($output, 'manual@example.test') !== false && strpos($output, 'Test &amp; Site') === false, 'Manual mode depends on Business Info or mixes identities');
    }
    $switched = $manual_saved; $switched['identity_source'] = 'business_info';
    $response = ajax('wp_ajax_marrison_wizard_save_step', array('nonce' => 'fixture', 'step' => 5, 'data' => array('privacy_profile' => $switched)));
    verify($response->success && Marrison_Addon_Cookie_Policy_Content::stored_profile()['controller_name'] === 'Manual Controller', 'Switching source destroyed manual data');
    verify(Marrison_Addon_Cookie_Policy_Content::profile()['controller_name'] === ($business_enabled ? 'Test & Site' : ''), 'Business source silently uses manual data');
    $business_snapshot = Marrison_Addon_Cookie_Policy_Content::snapshot();
    $options['marrison_cookie_privacy_profile']['identity_source'] = 'manual';
    verify(Marrison_Addon_Cookie_Policy_Content::profile()['controller_name'] === 'Manual Controller', 'Switching back failed to restore manual data');
    verify($business_snapshot['privacy_profile']['controller_name'] === ($business_enabled ? 'Test & Site' : ''), 'Source switch changed historical evidence');
}
Marrison_Addon::$business_enabled = false;
unset($options['marrison_cookie_privacy_profile']['identity_source']);
verify(Marrison_Addon_Cookie_Policy_Content::profile()['identity_source'] === 'manual' && Marrison_Addon_Cookie_Policy_Content::profile()['controller_name'] === 'Manual Controller', 'Legacy manual fields were lost');
verify(Marrison_Addon_Cookie_Policy_Content::sanitize_profile(array('identity_source' => 'invalid'))['identity_source'] === 'manual', 'Invalid source was accepted');
ob_start(); include MARRISON_COOKIE_PLUGIN_DIR . 'templates/privacy-fields.php'; $manual_form = ob_get_clean();
verify(strpos($manual_form, 'value="Manual Controller"') !== false && strpos($manual_form, 'name="privacy_profile[identity_source]"') !== false, 'Independent manual UI missing');
Marrison_Addon::$business_enabled = true;
$options['marrison_cookie_privacy_profile'] = $profile_before;
try {
    ajax('wp_ajax_marrison_wizard_create_pages', array('nonce' => 'bad'));
    throw new RuntimeException('Missing nonce validation');
} catch (RuntimeException $error) {
    verify($error->getMessage() === 'Invalid nonce', 'Invalid nonce was accepted');
}
echo "PASS policy shortcodes: native WordPress parsing/hooks, full Privacy/Cookie content, live scan, legacy table, configured URLs, no page creation, wizard persistence and authorization\n";
