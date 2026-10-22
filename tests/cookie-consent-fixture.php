<?php
/** Loopback-only HTTP fixture for the real Cookie Manager classes and browser assets. */
ob_start();
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', array('127.0.0.1', '::1'), true)) { http_response_code(403); exit; }
$test_dir = getenv('COOKIE_TEST_DIRECTORY');
$wordpress = getenv('COOKIE_TEST_WORDPRESS');
if (!$test_dir || !$wordpress) { http_response_code(500); exit; }
define('ABSPATH', rtrim($wordpress, '/\\') . '/');
define('MARRISON_COOKIE_PLUGIN_DIR', __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/');
define('COOKIE_DOMAIN', '');
define('DAY_IN_SECONDS', 86400);
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/shortcodes.php';
$options_file = $test_dir . '/options.json';
$options = is_file($options_file) ? json_decode(file_get_contents($options_file), true) : array();
function get_option($key, $default = false) { global $options; return array_key_exists($key, $options) ? $options[$key] : $default; }
function update_option($key, $value) { global $options, $options_file; $options[$key] = $value; file_put_contents($options_file, json_encode($options), LOCK_EX); return true; }
function add_option($key, $value, $unused = '', $autoload = null) { global $options; if (array_key_exists($key, $options)) { return false; } return update_option($key, $value); }
function home_url($path = '') { return 'http://127.0.0.1:' . $_SERVER['SERVER_PORT'] . $path; }
function admin_url($path = '') { return home_url('/wp-admin/' . $path); }
function wp_salt($scheme) { return get_option('fixture_salt', 'fixture-signing-key-' . $scheme); }
function wp_json_encode($data, $flags = 0) { return json_encode($data, $flags); }
function wp_unslash($data) { return $data; }
function wp_upload_dir() { global $test_dir; return array('basedir' => $test_dir, 'error' => get_option('fixture_upload_error') ? 'fixture storage failure' : false); }
function wp_mkdir_p($path) { return is_dir($path) || mkdir($path, 0777, true); }
function nocache_headers() { header('Cache-Control: no-store, private, max-age=0'); }
function wp_create_nonce() { return 'valid'; }
function check_ajax_referer($action, $key) { if (($_POST[$key] ?? '') !== 'valid') { http_response_code(403); echo '-1'; exit; } }
function check_admin_referer() { return ($_POST['_wpnonce'] ?? '') === 'valid'; }
function wp_nonce_field() { echo '<input type="hidden" name="_wpnonce" value="valid">'; }
function wp_send_json_success($data) { header('Content-Type: application/json'); echo json_encode(array('success' => true, 'data' => $data)); exit; }
function wp_send_json_error($data, $status = 200) { http_response_code($status); header('Content-Type: application/json'); echo json_encode(array('success' => false, 'data' => $data)); exit; }
function current_user_can() { return ($_SERVER['HTTP_X_FIXTURE_ADMIN'] ?? '') === '1'; }
function get_current_user_id() { return 0; }
function is_admin() { return strpos($_SERVER['REQUEST_URI'], '/wp-admin/') === 0; }
function wp_doing_ajax() { return strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php') !== false; }
function is_ssl() { return false; }
function is_page() { return false; }
function is_singular() { return false; }
function is_feed() { return false; }
function get_queried_object_id() { return 0; }
function get_bloginfo() { return 'Fixture Site'; }
function get_post($id = 0) { return $id ? (object) array('post_content' => get_option('marrison_cookie_' . ($id === 1 ? 'privacy' : 'cookie') . '_policy_content', ''), 'post_status' => 'publish') : null; }
function get_post_meta() { return ''; }
function url_to_postid($url) { return $url === home_url('/privacy') ? 1 : ($url === home_url('/cookies') ? 2 : 0); }
function plugins_url($path, $plugin = '') { return home_url('/plugin/' . $path); }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script($handle, $name, $data) { foreach ($data as $key => $value) { if (is_scalar($value)) { $data[$key] = (string) $value; } } $GLOBALS[$name] = $data; }
function wp_next_scheduled() { return false; }
function wp_schedule_event() { return true; }
function marrison_cookie_site_text($it, $en) { return $it; }
function marrison_cookie_is_english_site() { return false; }
function __($text) { return $text; }
function _e($text) { echo $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_url($text) { return esc_html($text); }
function esc_url_raw($text) { return filter_var($text, FILTER_VALIDATE_URL) ? $text : ''; }
function esc_textarea($text) { return esc_html($text); }
function esc_attr__($text) { return esc_attr($text); }
function sanitize_key($text) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($text)); }
function sanitize_text_field($text) { return trim(strip_tags($text)); }
function sanitize_textarea_field($text) { return strip_tags($text); }
function sanitize_hex_color($text) { return preg_match('/^#[a-f0-9]{6}$/i', $text) ? $text : ''; }
function wp_kses_post($text) { return preg_replace('#<script\b[^>]*>.*?</script>#is', '', $text); }
function wpautop($text) { return $text; }
function selected($a, $b) { if ($a === $b) { echo 'selected'; } }
function checked($a, $b) { if ($a === $b) { echo 'checked'; } }
function disabled($a, $b) { if ($a === $b) { echo 'disabled'; } }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_date($format, $timestamp) { return gmdate($format, $timestamp); }
define('MARRISON_COOKIE_PLUGIN_URL', home_url('/plugin/includes/modules/cookie-manager/'));
define('MARRISON_COOKIE_VERSION', 'test');
class Marrison_Cookie_Scanner {
    public static function auto_scan_enabled() { return get_option('marrison_cookie_auto_scan', false) && get_option('marrison_cookie_auto_scan_confirmed', false); }
    public static function get_instance() { return new self(); }
    public function get_cookies() { return array(); }
    public function get_categories() { return array('necessary' => 'Necessari', 'functional' => 'Funzionali', 'analytics' => 'Analitici', 'marketing' => 'Marketing'); }
    public function runtime_cookie_rules() { return array(array('name' => '_ga*', 'category' => 'analytics'), array('name' => '_fbp', 'category' => 'marketing')); }
    public function classify_cookie($name) { return array('category' => strpos($name, 'marrison_cookie_') === 0 || $name === 'essential' ? 'necessary' : (strpos($name, '_ga') === 0 ? 'analytics' : 'marketing')); }
}
require __DIR__ . '/cookie-test-support.php';
require_once MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-consent-store.php';
require MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-cookie-banner.php';
require MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-cookie-consent.php';
require MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-admin-settings.php';
require MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-setup-wizard.php';
$store = Marrison_Addon_Cookie_Consent_Store::get_instance();
$banner = Marrison_Cookie_Banner::get_instance();
$consent = Marrison_Cookie_Consent::get_instance();
$settings = Marrison_Cookie_Admin_Settings::get_instance();
$wizard = Marrison_Setup_Wizard::get_instance();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/wp-admin/admin-ajax.php') {
    nocache_headers();
    $action = $_POST['action'] ?? '';
    do_action((current_user_can('manage_options') ? 'wp_ajax_' : 'wp_ajax_nopriv_') . $action);
    wp_send_json_error(array('message' => 'Unknown action'), 403);
}
if ($path === '/configure') {
    if (!current_user_can('manage_options')) { http_response_code(403); exit; }
    foreach (json_decode(file_get_contents('php://input'), true) ?: array() as $key => $value) { update_option($key, $value); }
    wp_send_json_success(array());
}
if (strpos($path, '/tracking/') === 0 || strpos($path, '/ads/') === 0) {
    file_put_contents($test_dir . '/tracking.log', $path . "\n", FILE_APPEND | LOCK_EX);
    header('Content-Type: text/javascript');
    echo strpos($path, '/ads/') === 0 ? "window.adsRan=true;document.cookie='_fbp=PRIVATE_VALUE; Path=/';" : "window.analyticsRan=true;document.cookie='_ga_fixture=PRIVATE_VALUE; Path=/; Max-Age=3600';document.cookie='_ga_nested=PRIVATE_VALUE; Path=/nested/; Max-Age=3600';";
    exit;
}
if (preg_match('#/(frontend|admin|consent-runtime)\.js$#', $path, $match)) { header('Content-Type: text/javascript'); readfile(MARRISON_COOKIE_PLUGIN_DIR . 'assets/js/' . $match[1] . '.js'); exit; }
if ($path === '/jquery.js') { header('Content-Type: text/javascript'); readfile(ABSPATH . 'wp-includes/js/jquery/jquery.min.js'); exit; }
if ($path === '/admin.css' || $path === '/frontend.css') { header('Content-Type: text/css'); readfile(MARRISON_COOKIE_PLUGIN_DIR . 'assets/css' . $path); exit; }
if ($path === '/wp-admin/admin.php') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/admin.css"></head><body style="font:14px Arial">';
    $settings->render_settings_page();
    echo '<script>window.marrisonCookieAdmin=' . json_encode(array('ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => 'valid')) . ';</script><script src="/jquery.js"></script><script src="/admin.js"></script></body></html>';
    exit;
}
if ($path === '/privacy' || $path === '/cookies') {
    header('Content-Type: text/html; charset=UTF-8'); echo $path === '/privacy' ? $wizard->render_privacy_policy_shortcode() : $wizard->render_cookie_policy_shortcode(); exit;
}
$banner->enqueue_assets();
if (isset($_GET['old_nonce'])) { $GLOBALS['marrisonCookie']['nonce'] = 'expired'; }
header('Content-Type: text/html; charset=UTF-8');
ob_start();
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/frontend.css">';
$consent->render_runtime(); $banner->maybe_hide_banner_css();
echo '<script src="/tracking/fixture.js"></script><script src="/ads/fixture.js"></script></head><body><h1>Consent fixture</h1>';
$banner->render_banner(); $banner->render_floating_widget();
echo '<script>window.marrisonCookie=' . json_encode($GLOBALS['marrisonCookie']) . ';</script><script src="/jquery.js"></script><script src="/frontend.js"></script></body></html>';
echo $consent->filter_output(ob_get_clean());
