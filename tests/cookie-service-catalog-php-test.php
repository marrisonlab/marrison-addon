<?php
/** Run: php tests/cookie-service-catalog-php-test.php (no WordPress or network needed). */
define('ABSPATH', __DIR__ . '/');
$options = array();
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function home_url($path = '/') { return 'https://example.test' . $path; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; }
function add_action() {}
function add_shortcode() {}
function wp_unslash($value) { return $value; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return esc_attr($value); }
function esc_url($value) { return esc_attr($value); }
function __($text) { return $text; }
function esc_html_e($text) { echo esc_html($text); }
function selected($value, $expected) { if ($value === $expected) { echo 'selected="selected"'; } }
class Marrison_Addon_Cookie_Scan_Engine {
    public static function get_instance() { return new self(); }
    public function frame_phase() { return $GLOBALS['scan_phase'] ?? null; }
    public function service_inventory() {
        return array(array('host' => 'unknown.example', 'profiles' => array()), array('host' => 'cdn.example', 'profiles' => array()),
            array('host' => 'www.youtube.com', 'profiles' => array('youtube' => array('id' => 'youtube'))));
    }
}
require __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-scan-services.php';
require __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-cookie-consent.php';
require __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-admin-settings.php';
function check_catalog($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }

if (($argv[1] ?? '') === '--runtime-json') {
    echo json_encode(Marrison_Addon_Cookie_Scan_Services::runtime_config());
    exit;
}
$catalog = Marrison_Addon_Cookie_Scan_Services::catalog();
check_catalog(count($catalog) === 10 && !Marrison_Addon_Cookie_Scan_Services::catalog_error(), 'Catalog missing or invalid');
$json = json_encode(array_values($catalog));
foreach (array('', '{bad', 'null', '[]', str_repeat(' ', 262145)) as $bad) {
    check_catalog(Marrison_Addon_Cookie_Scan_Services::validate_catalog($bad) === array(), 'Malformed JSON accepted');
}
foreach (array('verified' => 'true', 'category' => 'other', 'source_url' => 'javascript:alert(1)', 'last_reviewed' => '2026-02-31', 'cookies' => 'x', 'provider' => array()) as $field => $bad) {
    $rows = json_decode($json, true); $rows[0][$field] = $bad;
    check_catalog(!Marrison_Addon_Cookie_Scan_Services::validate_catalog(json_encode($rows)), 'Invalid field accepted: ' . $field);
}
$rows = json_decode($json, true); $rows[] = $rows[0];
check_catalog(!Marrison_Addon_Cookie_Scan_Services::validate_catalog(json_encode($rows)), 'Duplicate service ID accepted');
$rows = json_decode($json, true); $rows[0]['patterns'][0]['path_regex'] = '[';
check_catalog(!Marrison_Addon_Cookie_Scan_Services::validate_catalog(json_encode($rows)), 'Invalid regex accepted');

$matches = array(
    'https://www.googletagmanager.com/gtag/js?id=G-123&l=dataLayer' => 'google-analytics',
    'https://region1.google-analytics.com/g/collect' => 'google-analytics',
    '//www.googletagmanager.com/gtm.js?id=GTM-123' => 'google-tag-manager',
    'https://www.googletagmanager.com/ns.html?id=GTM-123' => 'google-tag-manager',
    'https://connect.facebook.net/it_IT/fbevents.js' => 'meta-pixel',
    'https://www.facebook.com/tr?id=1' => 'meta-pixel',
    'https://www.youtube-nocookie.com/embed/test' => 'youtube',
    'https://maps.googleapis.com/maps/api/js?key=secret' => 'google-maps',
    'https://www.google.com/maps/embed?pb=secret' => 'google-maps',
    'https://maps.google.com/maps?q=test' => 'google-maps',
    'https://example.test/wp-content/plugins/woocommerce/assets/js/cart.js' => 'woocommerce',
    'https://js.stripe.com/v3/' => 'stripe',
    'https://example.test/wp-content/plugins/woocommerce-payments/a.js' => 'woopayments',
    'https://www.paypal.com/sdk/js?client-id=test' => 'paypal',
    'https://x.klarnacdn.net/kp/lib/v1/api.js' => 'klarna',
);
foreach ($matches as $url => $id) {
    $profiles = Marrison_Addon_Cookie_Scan_Services::identify($url);
    check_catalog(count($profiles) === 1 && $profiles[0]['id'] === $id, 'Missed service: ' . $url);
    foreach ($profiles[0]['cookies'] as $cookie) { check_catalog($cookie['observed'] === false && $cookie['evidence'] === 'provider_documentation', 'Catalog cookie presented as observed'); }
}
foreach (array('https://google-analytics.com.evil.test/g/collect', 'https://notgoogle-analytics.com/g/collect', 'https://evil.test/?url=https://google-analytics.com', 'https://www.google.com/search', 'https://www.googletagmanager.com/gtag/js?id=AW-1', 'https://www.googletagmanager.com/gtag/js?id=UA-1', 'https://example.test@google-analytics.com/g/collect', 'javascript://google-analytics.com/x', 'https://evil.test/wp-content/plugins/woocommerce/test.js') as $url) {
    check_catalog(!Marrison_Addon_Cookie_Scan_Services::identify($url), 'False identification: ' . $url);
}
check_catalog(array_column($catalog['google-analytics']['cookies'], 'name') === array('_ga', '_ga_*'), 'GA4 invented legacy cookies');
check_catalog(!$catalog['google-tag-manager']['cookies'], 'GTM invented its own cookies');
check_catalog($catalog['woocommerce']['category'] === 'necessary', 'WooCommerce category changed');
check_catalog(!$catalog['paypal']['verified'] && !$catalog['paypal']['cookies'] && !$catalog['klarna']['cookies'], 'Uncertain provider data fabricated');

$options['marrison_cookie_analytics_sources'] = "stats.example\n/custom-tracking/";
check_catalog(Marrison_Addon_Cookie_Scan_Services::identify('https://stats.example/a.js')[0]['category'] === 'analytics', 'Custom host not classified');
check_catalog(Marrison_Addon_Cookie_Scan_Services::identify('https://sub.stats.example/a.js')[0]['category'] === 'analytics', 'Custom subdomain not classified');
check_catalog(!Marrison_Addon_Cookie_Scan_Services::identify('https://stats.example.evil.test/a.js'), 'Custom host rule crossed a domain boundary');
check_catalog(Marrison_Addon_Cookie_Scan_Services::identify('https://example.test/custom-tracking/a.js')[0]['category'] === 'analytics', 'Custom path not classified');
unset($options['marrison_cookie_analytics_sources']);
check_catalog(Marrison_Addon_Cookie_Scan_Services::resource_category('https://unknown.example/a.js') === 'unclassified', 'Unknown host was implicitly allowed');
check_catalog(Marrison_Addon_Cookie_Scan_Services::resource_category('https://unseen.example/a.js') === 'unclassified', 'Unscanned external host was implicitly allowed');
$consent = Marrison_Cookie_Consent::get_instance();
$json_ld = '<script type="application/ld+json">{"@context":"https://schema.org","url":"https://stripe.com"}</script>';
check_catalog($consent->filter_output($json_ld) === $json_ld, 'Inline JSON-LD was mistaken for a network request');
check_catalog(strpos($consent->filter_output('<img src="/wp-content/plugins/woocommerce/a.png" srcset="https://unknown.example/a.png 2x">'), 'data-marrison-cookie-category="unclassified"') !== false, 'Mixed srcset bypassed unknown-host blocking');
$unknown_html = '<script src="https://unknown.example/a.js"></script>';
check_catalog(strpos($consent->filter_output($unknown_html), 'data-marrison-cookie-category="unclassified"') !== false, 'Unknown HTML resource not blocked');
$GLOBALS['scan_phase'] = 'after';
check_catalog(strpos($consent->filter_output($unknown_html), 'data-marrison-blocked-src') !== false, 'After-consent phase allowed an unclassified host');
unset($GLOBALS['scan_phase']);
$admin = Marrison_Cookie_Admin_Settings::get_instance();
$options['marrison_cookie_service_categories'] = array('not-in-current-report' => 'marketing');
$_GET = array(); $_POST = array('service_categories' => array('stripe' => 'necessary', Marrison_Addon_Cookie_Scan_Services::host_id('cdn.example') => 'necessary', 'paypal' => 'invalid', 'bad[]' => 'marketing'));
$save = new ReflectionMethod($admin, 'save_form_settings'); if (PHP_VERSION_ID < 80100) { $save->setAccessible(true); }
ob_start(); $save->invoke($admin); ob_end_clean();
check_catalog(count($options['marrison_cookie_service_categories']) === 3 && $options['marrison_cookie_service_categories']['not-in-current-report'] === 'marketing', 'Category save failed sanitization or lost an existing classification');
check_catalog(!isset(Marrison_Addon_Cookie_Scan_Services::merge_categories(array('not-in-current-report' => ''))['not-in-current-report']), 'Explicit unclassification did not clear its override');
check_catalog($consent->filter_output('<script src="https://js.stripe.com/v3/"></script>') === '<script src="https://js.stripe.com/v3/"></script>', 'Necessary payment resource blocked');
check_catalog($consent->filter_output('<script src="https://cdn.example/a.js"></script>') === '<script src="https://cdn.example/a.js"></script>', 'Explicit host classification not honored');
check_catalog(strpos($consent->filter_output('<script src="/wp-content/plugins/woocommerce/a.js"></script>'), 'data-marrison-cookie-blocked') === false, 'Relative technical resource blocked');
$options['marrison_cookie_analytics_sources'] = 'js.stripe.com';
check_catalog(strpos($consent->filter_output('<script src="https://js.stripe.com/v3/"></script>'), 'data-marrison-cookie-category="analytics"') !== false, 'Custom category did not override catalog');
$categories = array('necessary' => 'Necessari', 'functional' => 'Funzionali', 'analytics' => 'Analitici', 'marketing' => 'Marketing');
ob_start(); include __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/templates/service-catalog.php'; $html = ob_get_clean();
check_catalog(substr_count($html, '<select ') === 12 && strpos($html, 'Servizio non classificato') !== false, 'Admin fields missing');
check_catalog(strpos($html, 'name="service_categories[stripe]"') !== false && strpos($html, 'Da verificare') !== false, 'Admin classification/review badge missing');
if (($argv[1] ?? '') === '--admin-html') {
    echo '<!doctype html><html lang="it"><meta charset="utf-8"><style>body{font:14px system-ui;background:#f0f0f1;margin:24px}th{text-align:left;width:170px}td{padding:14px}select{padding:7px}a{color:#225ca0}p{max-width:850px}.marrison-card{background:white;padding:24px}.marrison-category-badge{display:inline-block;padding:4px 8px;margin:6px;background:#eee;border-radius:10px}.description{font-size:13px}</style><div class="marrison-cookie-settings"><form>' . $html . '</form></div></html>';
    exit;
}
echo "PASS catalog: 10 services, malformed JSON/schema/regex, host boundaries, declared evidence, custom rules, payment and unknown-host classification, protected-form save sanitization\n";
