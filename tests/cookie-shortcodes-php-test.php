<?php
/** Run: php tests/cookie-shortcodes-php-test.php <wordpress-root> [--fixture-wizard|--fixture-policy]. */
if (empty($argv[1]) || !is_file($argv[1] . '/wp-includes/shortcodes.php')) {
    throw new RuntimeException('Pass an installed WordPress root for its native shortcode and hook APIs.');
}
define('ABSPATH', rtrim($argv[1], '/\\') . '/');
define('MARRISON_COOKIE_PLUGIN_DIR', __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/');
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/shortcodes.php';

$options = array('marrison_wizard_current_step' => 5, 'marrison_cookie_show_banner' => false);
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
    public static function get_instance() { return new self(); }
    public function get_cookies() { return self::$cookies; }
    public function get_categories() { return array('necessary' => 'Necessari'); }
}
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

$_GET['tab'] = 'shortcodes';
ob_start();
Marrison_Cookie_Admin_Settings::get_instance()->render_settings_page();
$shortcodes_tab = ob_get_clean();
verify(strpos($shortcodes_tab, 'tab=shortcodes') !== false && substr_count($shortcodes_tab, 'class="marrison-shortcode-value"') === 3, 'Shortcode tab or copy fields missing');
verify(strpos($shortcodes_tab, 'name="marrison_save_settings"') === false, 'Read-only shortcode tab exposes settings submission');
unset($_GET['tab']);

verify(shortcode_exists('marrison_privacy_policy') && shortcode_exists('marrison_cookie_policy_content') && shortcode_exists('marrison_cookie_policy'), 'Public policy shortcodes missing');
foreach (array(false, true) as $locale) {
    $english = $locale;
    $privacy = do_shortcode('[marrison_privacy_policy]');
    $cookie = do_shortcode('[marrison_cookie_policy_content]');
    verify(strpos($privacy, '<h1>Privacy Policy</h1>') !== false && strpos($privacy, 'Test &lt;Site&gt;') !== false, 'Privacy content or escaping missing');
    verify(strpos($privacy, $locale ? 'Introduction' : 'Introduzione') !== false, 'Privacy language missing');
    verify(strpos($cookie, $locale ? 'What cookies are' : 'Cosa sono i cookie') !== false && strpos($cookie, 'current_scan') !== false, 'Full Cookie Policy or current scan missing');
    verify(substr_count($cookie, 'class="marrison-cookie-policy-details"') === 1 && strpos($cookie, '[marrison_cookie_policy]') === false, 'Nested shortcode did not render once');
    verify(strpos($cookie, 'PRIVATE_VALUE') === false && strpos($cookie, '<script>bad') === false, 'Sensitive/unsafe cookie metadata published');
    verify(strpos($privacy . $cookie, 'href=""') === false, 'Unconfigured policy URL generated an empty link');
}
$options['marrison_cookie_privacy_policy_url'] = 'https://example.test/my-privacy';
$options['marrison_cookie_cookie_policy_url'] = 'https://example.test/my-cookies';
verify(strpos(do_shortcode('[marrison_privacy_policy]'), 'href="https://example.test/my-cookies"') !== false, 'Privacy ignores configured Cookie URL');
verify(strpos(do_shortcode('[marrison_cookie_policy_content]'), 'href="https://example.test/my-privacy"') !== false, 'Cookie ignores configured Privacy URL');
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
$can_manage = false;
$response = ajax('wp_ajax_marrison_wizard_finish', array('nonce' => 'fixture', 'data' => array('cookie_policy_url' => 'https://example.test/unapproved')));
verify(!$response->success && $options === $before, 'Unauthorized wizard request changed URLs');
$can_manage = true;
try {
    ajax('wp_ajax_marrison_wizard_create_pages', array('nonce' => 'bad'));
    throw new RuntimeException('Missing nonce validation');
} catch (RuntimeException $error) {
    verify($error->getMessage() === 'Invalid nonce', 'Invalid nonce was accepted');
}
echo "PASS policy shortcodes: native WordPress parsing/hooks, full Privacy/Cookie content, live scan, legacy table, configured URLs, no page creation, wizard persistence and authorization\n";
