<?php
/** Real JSON/locking lifecycle, crawler and browser-result protocol around WP service fixtures. */
require __DIR__ . '/cookie-observed-scan-php-test.php';
function wp_normalize_path($path) { return str_replace('\\', '/', $path); }
function wp_salt($scheme) { return 'fixture-signing-key-' . $scheme; }
function sanitize_key($text) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $text)); }
function sanitize_text_field($text) { return is_scalar($text) ? trim(strip_tags((string) $text)) : ''; }
function wp_unslash($text) { return $text; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_next_scheduled() { return false; }
function wp_schedule_single_event() { return true; }
function add_query_arg($args, $url) {
    $parts = parse_url($url); $query = array();
    if (isset($parts['query'])) { parse_str($parts['query'], $query); }
    return preg_replace('/\?.*$/', '', $url) . '?' . http_build_query(array_merge($query, $args));
}
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function engine_ajax($engine, $method, $data = array()) {
    $_POST = array_merge(array('nonce' => 'fixture'), $data);
    return scan_json(array($engine, 'ajax_' . $method));
}
function html_response($body, $headers = array()) {
    return array('response' => array('code' => 200), 'body' => $body, 'response_headers' => array('content-type' => 'text/html; charset=UTF-8'), 'headers' => new HeaderValues($headers), 'raw_headers' => implode(',', $headers), 'cookies' => array());
}
$test_directory = __DIR__ . '/.cookie-engine-' . uniqid();
if (!is_dir(ABSPATH . 'wp-admin/includes')) { mkdir(ABSPATH . 'wp-admin/includes', 0777, true); }
file_put_contents(ABSPATH . 'wp-admin/includes/upgrade.php', '<?php');
require __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php';
$engine = Marrison_Addon_Cookie_Scan_Engine::get_instance();
$engine_validate_nonce = true;
$engine_can_manage = true;
$responses = array();
$http_requests = array();
try {
    verify($engine->diagnostics()['compatible'], 'Runtime fingerprint does not recognize the current scanner');
    foreach (array('https://outside.test/', '//outside.test/', 'javascript:alert(1)', 'https://user:password@example.test/', '/wp-admin/', '/%77p-admin/', '/wp-login.php', '/?action=delete', '/?add-to-cart=1', '/file.pdf') as $url) {
        verify($engine->normalize_url($url, home_url('/')) === false, 'Unsafe/out-of-scope URL accepted: ' . $url);
    }
    verify($engine->normalize_url('../inside/?utm_source=x#fragment', home_url('/one/two/')) === home_url('/one/inside/'), 'Relative URL/tracking-parameter normalization failed');
    $wpdb = new ObservedCookieDatabase();
    $wpdb->rows[1] = record(1, '_ga', 'third_party');
    $responses[home_url('/')] = html_response('<a href="/inside/">Inside</a><a href="/inside/?utm_source=x#duplicate">Duplicate</a><a href="https://outside.test/">External</a><a href="/wp-admin/">Admin</a><a href="/?action=delete">Action</a>', array('root_session=PRIVATE_VALUE; Path=/; HttpOnly; Secure; SameSite=Lax'));
    $responses[home_url('/inside/')] = html_response('<a href="/">Home</a><a href="/last/">Last</a>', array('page_cookie=PRIVATE_VALUE; Max-Age=120; Path=/inside/'));
    $responses[home_url('/last/')] = html_response('<h1>Last page</h1>');
    $start = engine_ajax($engine, 'start', array('limit' => 20));
    verify($start->success && $start->data['job']['status'] === 'http' && !$wpdb->rows, 'Job did not clear legacy records or start HTTP stage');
    $id = $start->data['job']['id'];
    for ($step = 0; $step < 15; $step++) {
        $result = engine_ajax($engine, 'step', array('job' => $id));
        verify($result->success, 'HTTP step failed: ' . json_encode($result->data));
        $job = $result->data['job'];
        if ($job['status'] !== 'http') { break; }
    }
    verify($job['status'] === 'browser' && $job['http_done'] === 3 && $job['browser_total'] === 6 && $job['cookie_count'] === 2, 'Crawler did not visit all internal pages once');
    foreach ($http_requests as $request) {
        verify(strpos($request[0], 'https://example.test/') === 0 && strpos($request[0], 'wp-admin') === false && $request[1]['redirection'] === 0 && !$request[1]['cookies'], 'Crawler followed unsafe redirects or sent administrator cookies');
    }
    $before = $job;
    $bad = engine_ajax($engine, 'browser', array('job' => $id, 'result' => json_encode(array('url' => $job['next']['url'], 'phase' => 'after', 'token' => 'forged'))));
    verify(!$bad->success, 'Forged browser report was accepted');
    verify(engine_ajax($engine, 'state')->data['job']['browser_done'] === $before['browser_done'], 'Rejected browser report advanced progress');
    $wpdb->rows[998] = record(998, '_ga_fixture', 'browser', 'marketing');
    $wpdb->rows[999] = record(999, 'removed_service', 'homepage');
    for ($index = 0; $index < 6; $index++) {
        $next = $job['next'];
        $cookie = array('cookie_name' => $next['phase'] === 'after' ? '_ga_fixture' : 'session_js', 'cookie_domain' => 'example.test', 'cookie_path' => '/', 'scope_known' => true,
            'expires' => $next['phase'] === 'after' ? (time() + 3600) * 1000 : null, 'same_site' => 'lax', 'secure' => true, 'value' => 'MUST_NEVER_BE_SAVED');
        $payload = $next + array('cookies' => array($cookie, array('cookie_name' => 'bad', 'cookie_domain' => 'evil.test', 'cookie_path' => '/')));
        $result = engine_ajax($engine, 'browser', array('job' => $id, 'result' => json_encode($payload)));
        verify($result->success, 'Browser report rejected: ' . json_encode($result->data));
        $job = $result->data['job'];
    }
    verify($job['status'] === 'completed' && $job['browser_done'] === 6 && $job['cookie_count'] === 4, 'Completed scan has inflated or missing cookies');
    $export = engine_ajax($engine, 'export')->data['report'];
    verify(strpos(json_encode($export), 'PRIVATE_VALUE') === false && strpos(json_encode($export), 'MUST_NEVER_BE_SAVED') === false, 'Cookie values leaked into report');
    verify(!isset($export['saved_categories']), 'Internal category map leaked into exported report');
    foreach ($export['cookies'] as $entry) {
        verify(!empty($entry['observations']) && isset($entry['cookie_path']), 'Cookie lacks page/phase evidence');
    }
    $inventory = engine_ajax($engine, 'inventory')->data;
    verify($inventory['count'] === 4, 'Admin inventory bypasses normalized observed records');
    foreach ($inventory['cookies'] as $entry) {
        if ($entry->cookie_name === '_ga_fixture') { verify($entry->cookie_category === 'marketing', 'A rescan overwrote a manual category'); }
        verify($entry->cookie_name !== 'removed_service', 'Complete scan retained a cookie that is no longer observed');
    }
    $state_file = file_get_contents($test_directory . '/marrison-cookie/scan-engine.php');
    verify(strpos($state_file, "<?php exit; ?>\n") === 0 && strpos($state_file, 'PRIVATE_VALUE') === false, 'Private scan-state file is not protected or contains cookie values');

    $start = engine_ajax($engine, 'start', array('limit' => 1));
    $id = $start->data['job']['id'];
    for ($step = 0; $step < 10; $step++) {
        $job = engine_ajax($engine, 'step', array('job' => $id))->data['job'];
        if ($job['status'] !== 'http') { break; }
    }
    verify($job['http_done'] === 1 && $job['limit_reached'], 'Page limit did not mark incomplete coverage');
    $result = engine_ajax($engine, 'browser', array('job' => $id, 'result' => json_encode($job['next'] + array('error' => 'iframe blocked'))));
    verify($result->data['job']['browser_failed'] === 1, 'Browser failure was hidden');
    $cancelled = engine_ajax($engine, 'cancel', array('job' => $id));
    verify($cancelled->data['job']['status'] === 'cancelled' && $cancelled->data['job']['next'] === null, 'Cancellation left a signed browser page active');
    $wpdb->fail_table = true;
    invoke($scanner, 'write_json_cookies', array(array(record(998, 'removed_json_service', 'browser'))));
    $entry = reset($export['cookies']);
    verify($scanner->engine_store(array($entry), array(), true), 'Engine JSON fallback failed to store a complete snapshot');
    verify(count(invoke($scanner, 'read_json_cookies')) === 1 && $scanner->get_cookies('all')[0]->cookie_name === $entry['cookie_name'], 'Complete JSON snapshot retained old records');
    verify($scanner->engine_store(array(), array(), true) && count(invoke($scanner, 'read_json_cookies')) === 0, 'Empty complete JSON snapshot retained old records');
    $wpdb->fail_table = false;
    $engine_can_manage = false;
    verify(!engine_ajax($engine, 'start')->success, 'Unauthenticated administrator operation accepted');
    $engine_can_manage = true;
    $_POST = array('nonce' => 'bad');
    try { $engine->ajax_start(); throw new RuntimeException('Invalid nonce accepted'); } catch (RuntimeException $error) { verify($error->getMessage() === 'Invalid nonce', 'Wrong nonce failure'); }
    echo "PASS progressive cookie engine: recursive crawl, scope/redirect guards, no admin cookies, signed before/after browser reports, real JSON locks, privacy, normalized inventory, page limits, cancellation and authorization\n";
} finally {
    $responses = null;
    foreach (array('scan-engine.php', 'scan-engine.lock', 'scanned-cookies.json', 'index.html', '.htaccess') as $file) {
        if (is_file($test_directory . '/marrison-cookie/' . $file)) { unlink($test_directory . '/marrison-cookie/' . $file); }
    }
    if (is_dir($test_directory . '/marrison-cookie')) { rmdir($test_directory . '/marrison-cookie'); }
    if (is_dir($test_directory)) { rmdir($test_directory); }
    unlink(ABSPATH . 'wp-admin/includes/upgrade.php');
    rmdir(ABSPATH . 'wp-admin/includes'); rmdir(ABSPATH . 'wp-admin'); rmdir(ABSPATH);
}
