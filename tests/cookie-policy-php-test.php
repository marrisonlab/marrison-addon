<?php
/** Focused WordPress fixture for generated/legacy cookie policies and consent UI gating. */
define('ABSPATH', __DIR__ . '/');
define('MARRISON_COOKIE_PLUGIN_DIR', __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/');
require __DIR__ . '/cookie-test-support.php';
$options = array('marrison_cookie_cookie_policy_url' => 'http://cookie-fixture.local/cookie-policy', 'marrison_cookie_show_banner' => true);
$queried_id = 42;
$loop_id = 42;
$admin = false;
$feed = false;
$english = false;
$post_content = '';
$elementor_data = '';
$localized = array();
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function get_option($name, $default = false) { global $options; return array_key_exists($name, $options) ? $options[$name] : $default; }
function is_page($id = null) { global $queried_id; return $queried_id > 0 && ($id === null || $id === $queried_id); }
function is_singular() { return is_page(); }
function get_queried_object_id() { global $queried_id; return $queried_id; }
function get_the_ID() { global $loop_id; return $loop_id; }
function url_to_postid($url) { return $url === 'http://cookie-fixture.local/cookie-policy' ? 42 : 0; }
function get_post() { global $post_content; return (object) array('post_content' => $post_content); }
function get_post_meta() { global $elementor_data; return $elementor_data; }
function has_shortcode($content, $tag) { return strpos($content, '[' . $tag . ']') !== false; }
function is_admin() { global $admin; return $admin; }
function is_feed() { global $feed; return $feed; }
function marrison_cookie_is_english_site() { global $english; return $english; }
function marrison_cookie_site_text($it, $en) { return marrison_cookie_is_english_site() ? $en : $it; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function sanitize_key($value) { return strtolower($value); }
function __($value) { return $value; }
function _e($value) { echo esc_html($value); }
function checked($value, $expected) { if ($value === $expected) { echo 'checked'; } }
function disabled($value, $expected) { if ($value === $expected) { echo 'disabled'; } }
function get_bloginfo() { return 'Test site'; }
function home_url() { return 'http://cookie-fixture.local'; }
function get_page_by_title() { return null; }
function wp_date($format, $timestamp) { return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Europe/Rome'))->format($format); }
class Marrison_Cookie_Scanner {
    public static $cookies = array();
    public static function get_instance() { return new self(); }
    public function get_cookies($category) { return self::$cookies; }
}
require MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-cookie-banner.php';
require MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-setup-wizard.php';
$banner = Marrison_Cookie_Banner::get_instance();
Marrison_Cookie_Scanner::$cookies = array(
    (object) array('cookie_name' => 'wordpress_test_cookie', 'cookie_domain' => 'example.test', 'cookie_category' => 'necessary', 'cookie_description' => 'Verifica browser', 'cookie_expiration_status' => 'session'),
    (object) array('cookie_name' => '_ga', 'cookie_domain' => 'example.test', 'cookie_category' => 'analytics', 'cookie_expiration' => '2026-12-01 12:00:00', 'cookie_description' => 'Misurazione visite'),
    (object) array('cookie_name' => '<script>alert(1)</script>', 'cookie_domain' => '<img src=x>', 'cookie_category' => 'uncategorized', 'cookie_description' => '<b>Descrizione</b>', 'cookie_value' => 'DO_NOT_PUBLISH')
);
if (isset($argv[1]) && $argv[1] === '--fixture') {
    $options['marrison_cookie_show_banner'] = !isset($argv[2]) || $argv[2] !== 'off';
    echo '<!doctype html><html lang="it"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main>';
    echo $banner->append_policy_details('<h1>Cookie Policy</h1><p>Testo originale della pagina.</p>');
    echo '</main>';
    $banner->render_banner();
    echo '</body></html>';
    exit;
}
function verify($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function invoke_private($object, $method) { $reflection = new ReflectionMethod($object, $method); if (PHP_VERSION_ID < 80100) { $reflection->setAccessible(true); } return $reflection->invoke($object); }
$original = '<p>Testo personalizzato da conservare.</p>';
$html = $banner->append_policy_details($original);
verify(strpos($html, $original) === 0 && strpos($html, 'wordpress_test_cookie') !== false && strpos($html, '_ga') !== false, 'Legacy policy content or scanned records missing');
verify(strpos($html, '&lt;script&gt;') !== false && strpos($html, '<script>') === false && strpos($html, 'DO_NOT_PUBLISH') === false, 'Unsafe cookie metadata/value published');
verify(strpos($html, 'href="#marrison-cookie-preferences"') !== false && strpos($html, 'Sessione') !== false && strpos($html, 'Non rilevabile') !== false && strpos($html, '01/12/2026 13:00') !== false, 'Preferences trigger, expiration status or site timezone incorrect');
verify($banner->append_policy_details($html) === $html, 'Repeated filtering duplicated details');
verify($banner->append_policy_details('[marrison_cookie_policy]') === '[marrison_cookie_policy]', 'Unprocessed shortcode duplicated');
foreach (array('admin', 'feed') as $flag) {
    $$flag = true;
    verify($banner->append_policy_details($original) === $original, 'Policy appended to ' . $flag);
    $$flag = false;
}
$loop_id = 7;
verify($banner->append_policy_details($original) === $original, 'Secondary loop changed');
$loop_id = 42;
$queried_id = 7;
verify($banner->append_policy_details($original) === $original, 'Unrelated page changed');
$options['marrison_cookie_show_banner'] = false;
verify(invoke_private($banner, 'should_render_consent_ui'), 'Consent bootstrap missing on unrelated page with disabled banner');
$post_content = '[marrison_cookie_policy]';
verify(invoke_private($banner, 'should_render_consent_ui'), 'Explicit shortcode cannot open preferences with banner disabled');
$post_content = '[marrison_cookie_policy_content]';
verify(invoke_private($banner, 'should_render_consent_ui'), 'Full policy shortcode cannot open preferences with banner disabled');
$post_content = '';
$elementor_data = json_encode(array(array('widgetType' => 'shortcode', 'settings' => array('shortcode' => '[marrison_cookie_policy_content]'))));
verify(invoke_private($banner, 'should_render_consent_ui'), 'Elementor policy shortcode cannot open preferences with banner disabled');
$queried_id = 42;
verify($banner->append_policy_details('<div>Elementor policy layout</div>') === '<div>Elementor policy layout</div>', 'Elementor policy gets a duplicate cookie list');
$elementor_data = '';
verify(invoke_private($banner, 'should_render_consent_ui'), 'Policy cannot open preferences with banner disabled');
ob_start(); $banner->render_banner(); $hidden = ob_get_clean();
verify(strpos($hidden, 'style="display: none;') !== false && strpos($hidden, 'id="marrison-cookie-modal"') !== false, 'Disabled banner visible or modal missing');
verify(strpos($hidden, 'role="dialog" aria-modal="true" aria-labelledby="marrison-cookie-dialog-title"') !== false && strpos($hidden, 'id="marrison-cookie-dialog-title"') !== false, 'Preferences dialog has no accessible name or semantics');
verify(strpos($hidden, 'aria-label="Chiudi preferenze cookie"') !== false && strpos($hidden, 'aria-labelledby="marrison-cookie-category-analytics"') !== false && strpos($hidden, 'id="marrison-cookie-category-analytics"') !== false, 'Preferences controls have no accessible labels');
verify(strpos($hidden, 'id="marrison-modal-status" role="status" aria-live="polite"') !== false, 'Modal cannot announce consent failures');
Marrison_Cookie_Scanner::$cookies = array((object) array('cookie_name' => 'aged_cookie', 'cookie_category' => 'analytics', 'observation_stale' => true));
$aged_policy = $banner->render_policy_shortcode();
verify(strpos($aged_policy, 'aged_cookie') !== false && strpos($aged_policy, 'oltre 30 giorni') !== false && strpos($aged_policy, 'Osservazione da aggiornare') !== false, 'Old observations are missing from policy or presented as current');
Marrison_Cookie_Scanner::$cookies = array((object) array('cookie_name' => 'new_scan_cookie', 'cookie_category' => 'marketing'));
$updated = $banner->render_policy_shortcode();
verify(strpos($updated, 'new_scan_cookie') !== false && strpos($updated, '_ga') === false, 'Policy retains old scan snapshot');
Marrison_Cookie_Scanner::$cookies = array();
$empty = $banner->render_policy_shortcode();
verify(strpos($empty, 'Nessun cookie rilevato') !== false && strpos($empty, 'marrison-open-cookie-preferences') !== false && strpos($empty, '<table') === false, 'Empty scan handling failed');
$wizard = Marrison_Setup_Wizard::get_instance();
foreach (array(false, true) as $locale) {
    $english = $locale;
    $generated = invoke_private($wizard, 'get_cookie_page_content');
    verify(strpos($generated, 'Google Analytics') === false && strpos($generated, '[marrison_cookie_policy]') === false, 'Unconfigured policy invents services or recursive shortcodes');
    $translated = $banner->render_policy_shortcode();
    verify(strpos($translated, $locale ? 'No cookies detected' : 'Nessun cookie rilevato') !== false, 'Policy language failed');
    verify($banner->get_cookie_expiration_label((object) array('cookie_expiration_status' => 'session')) === ($locale ? 'Session' : 'Sessione'), 'Session language failed');
    verify($banner->get_cookie_expiration_label((object) array()) === ($locale ? 'Not detectable' : 'Non rilevabile'), 'Unknown expiration language failed');
}
$options['marrison_cookie_privacy_profile'] = array('identity_source' => 'manual', 'controller_name' => 'Titolare indipendente', 'controller_email' => 'indipendente@example.test');
verify(!class_exists('Marrison_Addon_Business_Info_Store', false) && !class_exists('Marrison_Addon', false), 'Standalone fixture unexpectedly loaded Business Info');
$standalone = Marrison_Addon_Cookie_Policy_Content::content('privacy');
verify(strpos($standalone, 'Titolare indipendente') !== false && strpos($standalone, 'indipendente@example.test') !== false, 'Manual identity requires Business Info classes');
echo "PASS cookie policy: legacy/generated, fresh/empty scan, escaping, no cookie values, no duplication, context isolation, disabled banner, Italian/English and standalone manual identity\n";
