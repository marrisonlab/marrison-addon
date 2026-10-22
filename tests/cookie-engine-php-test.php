<?php
/** Real JSON/locking lifecycle, crawler and browser-result protocol around WP service fixtures. */
ob_start();
require __DIR__ . '/cookie-observed-scan-php-test.php';
require __DIR__ . '/cookie-sampling-fixtures.php';
$finished_scans = array();
function do_action($hook, ...$args) { if ($hook === 'marrison_addon/cookie_scan_finished') { $GLOBALS['finished_scans'][] = $args[0]; } }
function wp_normalize_path($path) { return str_replace('\\', '/', $path); }
function wp_salt($scheme) { return 'fixture-signing-key-' . $scheme; }
function sanitize_key($text) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $text)); }
function sanitize_text_field($text) { return is_scalar($text) ? trim(strip_tags((string) $text)) : ''; }
function wp_unslash($text) { return $text; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_next_scheduled($hook) { return $GLOBALS['engine_cron'][$hook] ?? false; }
function wp_schedule_single_event($time, $hook) { $GLOBALS['engine_cron'][$hook] = $time; return true; }
function wp_clear_scheduled_hook($hook) { unset($GLOBALS['engine_cron'][$hook]); }
function admin_url($path) { return home_url('/wp-admin/' . $path); }
function wp_remote_post($url, $args) {
    verify($url === admin_url('admin-ajax.php') && $args['blocking'] === false && $args['redirection'] === 0 && empty($args['cookies']), 'Unsafe background dispatch');
    if (!empty($GLOBALS['loopback_fail'])) { return new WP_Error('loopback', 'blocked'); }
    $GLOBALS['worker_queue'][] = $args['body'];
    return array('response' => array('code' => 0));
}
function nocache_headers() {}
function is_user_logged_in() { return false; }
function apply_filters($name, $value) { return $value; }
class WP_REST_Response {
    public $data;
    public function __construct($data) { $this->data = $data; }
    public function header($name, $value) {}
}
class ScanProofRequest {
    private $data;
    public function __construct($data) { $this->data = $data; }
    public function get_param($name) { return $this->data[$name] ?? null; }
}
function wp_safe_remote_get($url, $args) {
    verify($args['redirection'] === 0, 'Remote preflight followed redirects');
    return html_response(json_encode(array('protocol' => 1, 'enabled' => empty($GLOBALS['remote_disabled']))));
}
function wp_safe_remote_post($url, $args) {
    global $engine;
    verify($url === 'https://www.marrisonlab.com/commander/public/cookie-scan.php' && $args['redirection'] === 0, 'Unexpected Commander endpoint');
    $work = json_decode($args['body'], true);
    verify(count($work) === 4 && !isset($work['cookies']), 'Remote request exposed visitor state');
    $proof = $engine->scan_proof(new ScanProofRequest($work));
    verify($proof instanceof WP_REST_Response && $proof->data['proof_hash'] === hash('sha256', $work['proof'] . "\n" . $work['url']), 'Scan proof did not verify the active page');
    if (!empty($GLOBALS['remote_fail'])) { return new WP_Error('timeout', 'timeout'); }
    if (!empty($GLOBALS['remote_cancel'])) { $saved = $_POST; engine_ajax($engine, 'cancel', array('job' => $work['job'])); $_POST = $saved; }
    if (!empty($GLOBALS['remote_change_mode'])) { $saved = $_POST; engine_ajax($engine, 'mode', array('mode' => 'local')); $_POST = $saved; }
    return html_response(json_encode(array('success' => true, 'result' => array('protocol' => 1, 'engine' => 'commander_browserless', 'url' => $work['url'], 'consent_verified' => true,
        'resources' => array(array('url' => 'https://www.youtube.com/embed/example', 'kind' => 'iframe')),
        'cookies' => array(array('cookie_name' => 'YSC', 'cookie_domain' => '.youtube.com', 'cookie_path' => '/', 'expires' => null, 'http_only' => true, 'secure' => true, 'same_site' => 'none', 'phase' => 'after', 'value' => 'PRIVATE_VALUE'),
            array('cookie_name' => 'foreign', 'cookie_domain' => 'unrelated.test', 'cookie_path' => '/', 'phase' => 'before')),
        'storage' => array(array('key' => 'elementor', 'type' => 'localStorage', 'origin' => 'https://example.test', 'phase' => 'after', 'value' => 'PRIVATE_VALUE'),
            array('key' => 'wpEmojiSettingsSupports', 'type' => 'sessionStorage', 'origin' => 'https://example.test', 'phase' => 'before'),
            array('key' => 'injected', 'type' => 'localStorage', 'origin' => 'https://unrelated.test', 'phase' => 'after')),
        'issues' => array(), 'request_id' => 'remote-request-fixture'))));
}
class Marrison_Addon_Cookie_Consent_Store {
    public static $scan_calls = 0;
    public static function get_instance() { return new self(); }
    public function record($type, $categories, $source) { verify($type === 'accept_all' && $source === 'scan', 'Frame used the visitor receipt path'); self::$scan_calls++; return array('expires' => time() + 184 * 86400); }
}
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
    $worker_queue = array();
    $engine->start_scheduled_scan();
    verify(!invoke($engine, 'read_job') && !$worker_queue && !$http_requests, 'Fresh install started scheduled work without permission');
    update_option('marrison_cookie_auto_scan', true);
    $engine->start_scheduled_scan();
    verify(!invoke($engine, 'read_job') && !$worker_queue && !$http_requests, 'Legacy enabled default started scheduled work without opt-in');
    verify(engine_ajax($engine, 'state')->data['job'] === null && !$worker_queue && !$http_requests, 'Opening the scanner started work');
    $mode = engine_ajax($engine, 'mode', array('mode' => 'local'));
    verify($mode->success && $mode->data['job'] === null && !$worker_queue && !$http_requests, 'Selecting a method created a scan');
    foreach (array('https://outside.test/', '//outside.test/', 'javascript:alert(1)', 'https://user:password@example.test/', '/wp-admin/', '/%77p-admin/', '/wp-login.php', '/?action=delete', '/?add-to-cart=1', '/file.pdf', '/itemDataObject.url', '{{page.url}}', '/${page.url}') as $url) {
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
    verify(!$finished_scans, 'HTTP-only work acknowledged the rescan notice');
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
        $payload = $next + array('storage_access' => 'available', 'cookies' => array($cookie, array('cookie_name' => 'bad', 'cookie_domain' => 'evil.test', 'cookie_path' => '/')));
        $result = engine_ajax($engine, 'browser', array('job' => $id, 'result' => json_encode($payload)));
        verify($result->success, 'Browser report rejected: ' . json_encode($result->data));
        $job = $result->data['job'];
    }
    verify($job['status'] === 'completed' && $job['browser_done'] === 6 && $job['cookie_count'] === 4, 'Completed scan has inflated or missing cookies');
    verify(count($finished_scans) === 1 && $finished_scans[0]['status'] === 'completed' && array_key_exists('plugin_version', $finished_scans[0]), 'Persisted browser completion did not notify the module with its starting version');
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

    // Stale policy links and removed routes are skipped, not failed page measurements.
    $removed_root = $responses[home_url('/')];
    $responses[home_url('/')] = html_response('<a href="/removed-policy/">Deleted policy</a><a href="/gone/">Gone</a>');
    $responses[home_url('/removed-policy/')] = array('response' => array('code' => 404), 'headers' => array(), 'raw_headers' => array(), 'body' => 'Not found');
    $responses[home_url('/gone/')] = array('response' => array('code' => 410), 'headers' => array(), 'raw_headers' => array(), 'body' => 'Gone');
    $options['marrison_cookie_privacy_policy_url'] = home_url('/removed-policy/');
    $fixture_sample_posts[999] = (object) array('ID' => 999, 'post_type' => 'page', 'post_status' => 'trash', 'post_password' => '', 'url' => home_url('/trashed/'));
    $options['marrison_cookie_cookie_policy_url'] = home_url('/trashed/');
    $samples = new Marrison_Addon_Cookie_Scan_Sampler();
    verify($samples->describe(home_url('/trashed/')) === null && !in_array(home_url('/trashed/'), $samples->seeds(), true), 'Trashed policy forced into public samples');
    $job = engine_ajax($engine, 'start', array('limit' => 20))->data['job'];
    for ($step = 0; $step < 15 && $job['status'] === 'http'; $step++) { $job = engine_ajax($engine, 'step', array('job' => $job['id']))->data['job']; }
    verify($job['http_failed'] === 0 && count($job['skipped_missing']) === 2 && $job['issues_total'] === 0 && $job['browser_total'] === 2, 'Deleted routes produced an incomplete scan or browser requests');
    $next = $job['next'];
    foreach (array('before', 'after') as $phase) { $next = $job['next']; $job = engine_ajax($engine, 'browser', array('job' => $job['id'], 'result' => json_encode($next + array('storage_access' => 'available', 'cookies' => array()))))->data['job']; }
    verify($job['status'] === 'completed', '404/410 still marked the finished scan partial');
    $responses[home_url('/')] = $removed_root;
    unset($options['marrison_cookie_privacy_policy_url'], $options['marrison_cookie_cookie_policy_url'], $fixture_sample_posts[999]);
    echo "PASS deleted routes: trashed policy excluded, deleted 404 and 410 skipped, browser not sent to missing pages, completed status preserved\n";

    // 100 articles and 100 CPT records share templates; only genuine variants get extra visits.
    $original_root = $responses[home_url('/')];
    $links = '';
    for ($index = 1; $index <= 100; $index++) {
        foreach (array('post' => $index, 'annunci-lavoro' => 2000 + $index) as $type => $post_id) {
            $url = home_url('/' . ($type === 'post' ? 'post' : 'annuncio') . '/' . $index . '/');
            $fixture_sample_posts[$post_id] = (object) array('ID' => $post_id, 'post_type' => $type, 'post_status' => 'publish', 'post_password' => '', 'post_name' => 'item-' . $index,
                'post_content' => '<p>Testo differente del contenuto ' . $index . '</p>', 'url' => $url);
            $fixture_sample_meta[$post_id]['_elementor_data'] = json_encode(array(array('id' => 'random-' . $index, 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array('editor' => 'Testo ' . $index))));
            $links .= '<a href="' . $url . '">Content</a>';
            $responses[$url] = html_response('<h1>Representative</h1>');
        }
    }
    foreach (array(101 => 'special', 102 => 'youtube', 103 => 'vimeo') as $post_id => $variant) {
        $url = home_url('/post/' . $post_id . '/');
        $fixture_sample_posts[$post_id] = (object) array('ID' => $post_id, 'post_type' => 'post', 'post_status' => 'publish', 'post_password' => '', 'post_name' => $variant,
            'post_content' => $variant === 'special' ? '<p>Pagina speciale</p>' : '<iframe src="https://' . ($variant === 'youtube' ? 'www.youtube.com' : 'player.vimeo.com') . '/embed/123"></iframe>', 'url' => $url);
        if ($variant === 'special') { $fixture_sample_meta[$post_id]['_wp_page_template'] = 'special.php'; }
        $links .= '<a href="' . $url . '">Variant</a>'; $responses[$url] = html_response('<p>Variant</p>');
    }
    foreach (array('a', 'b') as $slug) {
        $url = home_url('/topic/' . $slug . '/');
        $fixture_sample_terms[$slug] = (object) array('slug' => $slug, 'taxonomy' => 'topic', 'url' => $url, 'description' => '');
        $links .= '<a href="' . $url . '">Term</a>'; $responses[$url] = html_response('<p>Archive</p>');
    }
    $responses[home_url('/annunci/')] = html_response('<p>CPT archive</p>');
    $links .= '<a href="' . home_url('/annunci/') . '">Archive</a>';
    $responses[home_url('/')] = html_response($links); $http_requests = array();
    $sample_start = engine_ajax($engine, 'start', array('limit' => 50));
    verify($sample_start->success, 'Sampling start failed: ' . json_encode($sample_start->data));
    $job = $sample_start->data['job']; $sample_id = $job['id'];
    for ($step = 0; $step < 20 && $job['status'] === 'http'; $step++) { $job = engine_ajax($engine, 'step', array('job' => $sample_id))->data['job']; }
    verify($job['http_done'] === 9 && $job['browser_total'] === 18, 'Equivalent post/CPT URLs were visited individually or an embedded/template variant was lost: ' . json_encode($job));
    verify($job['sampling']['candidates'] === 207 && $job['sampling']['equivalents'] === 198 && $job['sampling']['selected'] === 9, 'Sampling coverage does not explain grouped URLs');
    $article_visits = array_filter($http_requests, function($request) { return strpos($request[0], home_url('/post/')) === 0; });
    $cpt_visits = array_filter($http_requests, function($request) { return strpos($request[0], home_url('/annuncio/')) === 0; });
    verify(count($article_visits) === 4 && count($cpt_visits) === 1, '100 equivalent articles/CPTs generated repeated HTTP work');
    for ($index = 0; $index < 18; $index++) { $job = engine_ajax($engine, 'browser', array('job' => $sample_id, 'result' => json_encode($job['next'] + array('storage_access' => 'available', 'cookies' => array()))))->data['job']; }
    verify($job['status'] === 'completed' && $job['cookie_count'] === 0 && $job['browser_done'] === 18, 'Sample scan invented cookies or expanded browser work');
    $sample_report = engine_ajax($engine, 'export')->data['report'];
    verify(count($sample_report['sampling']['groups']) === 9, 'Export omitted selection reasons');
    // Video IDs, map locations and form titles do not create fake service variants.
    $sampler_check = new Marrison_Addon_Cookie_Scan_Sampler();
    foreach (array(104 => '[video src="https://www.youtube.com/watch?v=one"]', 105 => '[video src="https://www.youtube.com/watch?v=two"]',
        106 => '[contact-form-7 id="12" title="Titolo uno"]', 107 => '[contact-form-7 id="12" title="Titolo due"]', 108 => '[contact-form-7 id="13" title="Titolo due"]') as $post_id => $content) {
        $fixture_sample_posts[$post_id] = clone $fixture_sample_posts[102];
        $fixture_sample_posts[$post_id]->ID = $post_id; $fixture_sample_posts[$post_id]->url = home_url('/post/' . $post_id . '/'); $fixture_sample_posts[$post_id]->post_content = $content;
    }
    verify($sampler_check->describe(home_url('/post/104/'))['key'] === $sampler_check->describe(home_url('/post/105/'))['key'], 'Different video URLs using the same provider multiplied visits');
    verify($sampler_check->describe(home_url('/post/106/'))['key'] === $sampler_check->describe(home_url('/post/107/'))['key'], 'A cosmetic form title multiplied visits');
    verify($sampler_check->describe(home_url('/post/107/'))['key'] !== $sampler_check->describe(home_url('/post/108/'))['key'], 'Different form components were collapsed');
    $fixture_sample_meta[104]['_elementor_data'] = json_encode(array(array('widgetType' => 'heading'), array('widgetType' => 'image-box')));
    $fixture_sample_meta[105]['_elementor_data'] = json_encode(array(array('widgetType' => 'heading'), array('widgetType' => 'icon-list')));
    verify($sampler_check->describe(home_url('/post/104/'))['key'] === $sampler_check->describe(home_url('/post/105/'))['key'], 'Cosmetic Elementor widgets multiplied cookie samples');
    verify($sampler_check->describe(home_url('/2021/08/31/'))['key'] === $sampler_check->describe(home_url('/2017/01/13/'))['key'], 'Date archives were sampled individually');
    foreach (range(104, 108) as $post_id) { unset($fixture_sample_posts[$post_id]); }

    $job = engine_ajax($engine, 'start', array('additional_urls' => home_url('/post/2/') . "\n"))->data['job'];
    verify(count(array_filter($job['sampling']['groups'], function($group) { return $group['kind'] === 'additional' && $group['url'] === home_url('/post/2/'); })) === 1, 'Explicit additional page was collapsed into its template');
    $before_invalid = $job['id'];
    verify(!engine_ajax($engine, 'start', array('additional_urls' => 'https://outside.test/private'))->success, 'Additional URLs bypassed same-origin guards');
    verify(engine_ajax($engine, 'state')->data['job']['id'] === $before_invalid, 'Invalid additional URL replaced the saved scan');
    $legacy = $sample_report; $legacy['status'] = 'browser'; $legacy['revision'] = 'old';
    invoke($engine, 'write_job', array($legacy));
    $stale = engine_ajax($engine, 'state')->data['job'];
    verify($stale['stale'] && $stale['status'] === 'cancelled' && $stale['next'] === null, 'A legacy full-crawl job was silently resumed by the sampled engine');
    $fixture_sample_posts = array(); $fixture_sample_meta = array(); $fixture_sample_terms = array();
    $responses[home_url('/')] = $original_root;
    echo "PASS representative sampling: 207 candidate URLs (100 articles + 100 CPT entries + template/provider/archive variants), 9 HTTP samples, 18 browser passes, 198 equivalent URLs avoided, explicit extra URL, export reasons and legacy-job guard\n";

    // Reproduce the uploaded Ceris report: JetMenu :href templates must never become requests.
    $responses[home_url('/')] = html_response('<script type="text/x-template"><a :href="itemDataObject.url">Menu</a><a href="/fake-script/">Inert</a></script><template><a href="/fake-template/">Inert</a></template><a data-href="/fake-data/">Inert</a><a :href="itemDataObject.url">Binding</a><a href="/inside/">Real</a><iframe src="https://www.google.com/maps/embed?private=query"></iframe>');
    $responses[home_url('/inside/')] = html_response('<h1>Inside</h1>');
    $http_requests = array();
    $job = engine_ajax($engine, 'start')->data['job']; $id = $job['id'];
    while ($job['status'] === 'http') { $job = engine_ajax($engine, 'step', array('job' => $id))->data['job']; }
    verify($job['http_done'] === 2 && !$job['http_failed'] && !$job['issues_total'], 'JetMenu placeholders generated fake 404s');
    verify(count($job['services']) === 1 && $job['services'][0]['host'] === 'www.google.com' && !$job['services'][0]['cookies_verified'], 'Embedded third-party service was lost or presented as verified cookies');
    verify($job['documented_cookie_count'] === 1 && $job['cookie_count'] === 0, 'Provider declarations inflated the observed cookie count');
    $map_profile = $job['services'][0]['profiles']['google-maps'];
    verify($map_profile['cookies'][0]['name'] === 'NID' && $map_profile['cookies'][0]['observed'] === false && $map_profile['cookies'][0]['evidence'] === 'provider_documentation', 'Map cookie has no honest evidence marker');
    verify(Marrison_Addon_Cookie_Scan_Services::identify('https://www.google.com/search') === array(), 'Unrelated Google resource declared map cookies');
    verify(Marrison_Addon_Cookie_Scan_Services::identify('https://www.googletagmanager.com/gtag/js?id=AW-123') === array(), 'Advertising gtag falsely declared Analytics');
    verify(count(Marrison_Addon_Cookie_Scan_Services::identify('https://www.googletagmanager.com/gtag/js?id=G-123')) === 1, 'Analytics tag was missed');
    verify(strpos(json_encode(engine_ajax($engine, 'export')->data['report']['services']), 'private=query') === false, 'External resource query leaked into report');
    while ($job['status'] === 'browser') { $job = engine_ajax($engine, 'browser', array('job' => $id, 'result' => json_encode($job['next'] + array('storage_access' => 'available', 'cookies' => array()))))->data['job']; }
    verify($job['status'] === 'completed' && $job['cookie_count'] === 0, 'A genuinely empty verified scan invented cookies or failed');
    $job = engine_ajax($engine, 'start')->data['job']; $id = $job['id'];
    while ($job['status'] === 'http') { $job = engine_ajax($engine, 'step', array('job' => $id))->data['job']; }
    $job = engine_ajax($engine, 'browser', array('job' => $id, 'result' => json_encode($job['next'] + array('storage_access' => 'blocked', 'cookies' => array()))))->data['job'];
    verify($job['browser_failed'] === 1 && $job['issues_total'] === 1, 'Blocked cookie access was accepted as a successful empty scan');
    $responses[home_url('/')] = $original_root;
    echo "PASS Ceris regression: inert JetMenu links ignored, real links retained, external map identified without invented cookies, blocked cookie access reported\n";

    $responses[home_url('/')] = html_response('<h1>No optional service</h1>');
    $job = engine_ajax($engine, 'start')->data['job']; $id = $job['id'];
    while ($job['status'] === 'http') { $job = engine_ajax($engine, 'step', array('job' => $id))->data['job']; }
    $_SERVER['HTTP_SEC_FETCH_DEST'] = 'iframe';
    $frame_property = new ReflectionProperty($engine, 'frame');
    if (PHP_VERSION_ID < 80100) { $frame_property->setAccessible(true); }
    $frame_property->setValue($engine, $job['next'] + array('job' => $id)); $engine->frame_headers();
    verify(Marrison_Addon_Cookie_Consent_Store::$scan_calls === 0, 'Before-consent pass wrote a simulated choice');
    $job = engine_ajax($engine, 'browser', array('job' => $id, 'result' => json_encode($job['next'] + array('storage_access' => 'available', 'cookies' => array()))))->data['job'];
    $frame_property->setValue($engine, $job['next'] + array('job' => $id)); $engine->frame_headers();
    verify(Marrison_Addon_Cookie_Consent_Store::$scan_calls === 1, 'After-consent pass skipped the native consent cookie response');
    verify(engine_ajax($engine, 'state')->data['job']['cookie_count'] === 0, 'Native cookie names were invented without emitted response headers');
    set_error_handler(function($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
    try { $engine->capture_frame_cookies(''); } // Native WP do_action() supplies '' when there are no arguments.
    finally { restore_error_handler(); }
    $engine->capture_frame_cookies(array('Set-Cookie: marrison_cookie_state=PRIVATE_VALUE; Path=/; Max-Age=3600; Secure; HttpOnly; SameSite=Lax',
        'Set-Cookie: marrison_cookie_consent=accept_all; Path=/; Max-Age=3600',
        'Set-Cookie: marrison_cookie_categories=PRIVATE_VALUE; Path=/; Max-Age=3600',
        'Set-Cookie: server_late=PRIVATE_VALUE; Path=/; HttpOnly',
        'Set-Cookie: forged=PRIVATE_VALUE; Domain=outside.test; Path=/'));
    $job = engine_ajax($engine, 'browser', array('job' => $id, 'result' => json_encode($job['next'] + array('storage_access' => 'available', 'cookies' => array()))))->data['job'];
    verify($job['status'] === 'completed' && $job['cookie_count'] === 4, 'Real response headers lost native or late HttpOnly cookies, or accepted a foreign domain');
    verify($job['response_pages'] === 1, 'Response header capture was not recorded');
    $consent_export = engine_ajax($engine, 'export')->data['report'];
    foreach ($consent_export['cookies'] as $entry) { verify($entry['observations'][0]['phase'] === 'after' && $entry['observations'][0]['method'] === 'http_browser', 'Native consent headers have incorrect provenance'); }
    verify(strpos(json_encode($consent_export), 'PRIVATE_VALUE') === false, 'Response cookie values leaked');
    $frame_property->setValue($engine, array('phase' => 'after', 'token' => 'forged')); $engine->frame_headers();
    verify(Marrison_Addon_Cookie_Consent_Store::$scan_calls === 1 && !empty($frame_property->getValue($engine)['error']), 'Stale/forged frame created consent cookies');
    $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
    $frame_property->setValue($engine, $job['next'] ?? array('phase' => 'after', 'token' => 'forged'));
    $engine->frame_headers();
    verify(Marrison_Addon_Cookie_Consent_Store::$scan_calls === 1, 'Top-level navigation issued a scan consent');
    $frame_property->setValue($engine, null);
    unset($_SERVER['HTTP_SEC_FETCH_DEST']);
    $responses[home_url('/')] = $original_root;
    echo "PASS scan consent: clean before pass, signed after pass, real response headers including late HttpOnly cookies, no cookie values or fabricated observations, stale/forged frame rejected\n";

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
    $saved_root = $responses[home_url('/')];
    $responses[home_url('/')] = html_response('<h1>Remote scan</h1>');
    $remote_disabled = true;
    verify(!engine_ajax($engine, 'start', array('mode' => 'commander'))->success, 'Unavailable remote service accepted');
    $remote_disabled = false;
    $start = engine_ajax($engine, 'start', array('mode' => 'commander'));
    $id = $start->data['job']['id'];
    for ($step = 0; $step < 10; $step++) { $job = engine_ajax($engine, 'step', array('job' => $id))->data['job']; if ($job['status'] !== 'http') break; }
    verify($job['mode'] === 'commander' && $job['next']['phase'] === 'both' && !isset($job['remote_proof']), 'Remote page contract or secret filtering failed');
    verify(!engine_ajax($engine, 'browser', array('job' => $id))->success, 'Remote scan accepted local browser results');
    $remote_fail = true;
    verify(!engine_ajax($engine, 'remote', array('job' => $id))->success, 'Remote timeout was marked successful');
    verify(engine_ajax($engine, 'state')->data['job']['browser_done'] === 0, 'Timeout advanced the remote cursor');
    $remote_fail = false;
    $job = engine_ajax($engine, 'remote', array('job' => $id))->data['job'];
    verify($job['status'] === 'completed' && $job['browser_done'] === 2 && $job['cookie_count'] === 1 && $job['remote_requests'] === 1, 'Remote import did not complete or imported an unrelated cookie');
    $remote_export = engine_ajax($engine, 'export')->data['report'];
    $remote_cookie = reset($remote_export['cookies']);
    verify($remote_cookie['cookie_name'] === 'YSC' && $remote_cookie['http_only'] && $remote_cookie['cookie_domain'] === 'youtube.com', 'External HttpOnly metadata lost');
    verify($remote_cookie['observations'][0]['method'] === 'remote_browser' && $scanner->get_cookies('all')[0]->source === 'browser', 'Remote evidence provenance lost');
    verify(count($remote_export['storage']) === 2 && $job['storage_supported'] && $job['cookie_count'] === 1, 'Browser storage missing, accepted unrelated origin or inflated cookie count');
    verify(!isset($remote_export['remote_proof']) && strpos(json_encode($remote_export), 'PRIVATE_VALUE') === false, 'Remote export leaked proof or cookie values');
    verify(is_wp_error($engine->scan_proof(new ScanProofRequest(array('job' => $id, 'proof' => str_repeat('a', 64), 'url' => home_url('/'))))), 'Completed/forged proof accepted');
    $start = engine_ajax($engine, 'start', array('mode' => 'commander')); $id = $start->data['job']['id'];
    for ($step = 0; $step < 10; $step++) { $job = engine_ajax($engine, 'step', array('job' => $id))->data['job']; if ($job['status'] !== 'http') break; }
    $remote_cancel = true;
    verify(!engine_ajax($engine, 'remote', array('job' => $id))->success && engine_ajax($engine, 'state')->data['job']['status'] === 'cancelled', 'Late remote response revived a cancelled job');
    $remote_cancel = false; $responses[home_url('/')] = $saved_root;
    $start = engine_ajax($engine, 'start', array('mode' => 'commander')); $id = $start->data['job']['id'];
    for ($step = 0; $step < 10; $step++) { $job = engine_ajax($engine, 'step', array('job' => $id))->data['job']; if ($job['status'] !== 'http') break; }
    $remote_change_mode = true;
    verify(!engine_ajax($engine, 'remote', array('job' => $id))->success && engine_ajax($engine, 'state')->data['job']['status'] === 'cancelled', 'Late remote response revived a job after changing method');
    $remote_change_mode = false;
    echo "PASS Commander integration: preflight, expiring site proof, remote-only results, timeout/resume, foreign-domain HttpOnly import, unrelated cookie rejection, no cookie values or proof export, cancellation race\n";
    $worker_queue = array();
    $responses[home_url('/')] = html_response('<h1>Background scan</h1>');
    $start = engine_ajax($engine, 'start', array('mode' => 'commander')); $background_id = $start->data['job']['id'];
    verify($start->success && $start->data['job']['background'] && count($worker_queue) === 1, 'Start did not dispatch background work');
    $first_worker = $worker_queue[0];
    $before = invoke($engine, 'read_job');
    $_POST = array('job' => $background_id, 'token' => 'forged');
    verify(!scan_json(array($engine, 'ajax_worker'))->success && invoke($engine, 'read_job')['http_done'] === $before['http_done'], 'Unsigned worker performed work');
    $engine_can_manage = false; // No browser, admin nonce, cookie or authenticated request participates.
    $batches = 0;
    while ($worker_queue && $batches++ < 30) {
        $_POST = array_shift($worker_queue);
        verify(scan_json(array($engine, 'ajax_worker'))->success, 'Signed background batch failed');
    }
    $engine_can_manage = true;
    $finished = invoke($engine, 'read_job');
    verify($finished['status'] === 'completed' && $finished['browser_cursor'] === 2 && !$worker_queue && !wp_next_scheduled('marrison_addon_cookie_engine_tick'), 'Background did not finish without UI polling or left queued work');
    verify(count($finished['cookies']) === 1 && count($finished['storage']) === 2 && strpos(json_encode($finished), $first_worker['token']) === false, 'Background lost observations or stored its authentication token');
    $_POST = $first_worker;
    verify(!scan_json(array($engine, 'ajax_worker'))->success, 'Terminal worker replay accepted');
    $worker_queue = array(); $start = engine_ajax($engine, 'start', array('mode' => 'commander')); $pending_worker = array_shift($worker_queue);
    $leased = invoke($engine, 'read_job'); $leased['worker']['lease_until'] = time() + 90; invoke($engine, 'write_job', array($leased));
    $_POST = $pending_worker; scan_json(array($engine, 'ajax_worker'));
    verify(invoke($engine, 'read_job')['http_done'] === 0 && !$worker_queue, 'A second worker ignored the active lease');
    $leased['worker']['lease_until'] = time() - 1; invoke($engine, 'write_job', array($leased));
    $engine->scheduled_step();
    verify(invoke($engine, 'read_job')['http_done'] > 0 && $worker_queue, 'Watchdog did not recover an expired worker lease');
    $cancelled_id = $leased['id']; engine_ajax($engine, 'cancel', array('job' => $cancelled_id));
    $_POST = $pending_worker; verify(!scan_json(array($engine, 'ajax_worker'))->success, 'Cancelled worker accepted');
    $worker_queue = array(); $loopback_fail = true;
    $start = engine_ajax($engine, 'start', array('mode' => 'commander'));
    engine_ajax($engine, 'state'); // Throttled dispatch must not create duplicate requests.
    $blocked = invoke($engine, 'read_job');
    verify($blocked['worker']['dispatch_failures'] === 1 && !empty($blocked['worker']['error']), 'Loopback failure was hidden or aggressively retried');
    $loopback_fail = false;
    engine_ajax($engine, 'resume', array('job' => $blocked['id']));
    verify(count($worker_queue) === 1 && invoke($engine, 'read_job')['worker']['error'] === '', 'Resume did not restart the server worker');
    engine_ajax($engine, 'cancel', array('job' => $blocked['id']));
    // Viewing state must remain read-only even when a remote job could be dispatched.
    $worker_queue = array();
    $start = engine_ajax($engine, 'start', array('mode' => 'commander'));
    $pending_worker = array_shift($worker_queue);
    $pending = invoke($engine, 'read_job');
    $pending['worker']['dispatch_at'] = 0;
    invoke($engine, 'write_job', array($pending));
    engine_ajax($engine, 'state');
    verify(!$worker_queue && invoke($engine, 'read_job') === $pending, 'Reading state dispatched a worker or changed the job');
    $mode = engine_ajax($engine, 'mode', array('mode' => 'local'));
    verify($mode->success && $mode->data['interrupted'] && $mode->data['job']['status'] === 'cancelled'
        && $mode->data['job']['mode'] === 'commander' && get_option('marrison_cookie_scan_mode') === 'local', 'Method change failed to cancel and preserve the old report');
    verify(!$worker_queue && !wp_next_scheduled('marrison_addon_cookie_engine_tick'), 'Method change started replacement work or left a watchdog');
    $_POST = $pending_worker;
    verify(!scan_json(array($engine, 'ajax_worker'))->success, 'Method change left a queued remote worker active');
    $engine->scheduled_step();
    verify(invoke($engine, 'read_job')['status'] === 'cancelled' && !$worker_queue, 'Watchdog revived work after changing method');
    verify(!engine_ajax($engine, 'mode', array('mode' => 'invalid'))->success && get_option('marrison_cookie_scan_mode') === 'local', 'Invalid mode changed settings');
    update_option('marrison_cookie_auto_scan_confirmed', true);
    update_option('marrison_cookie_scan_mode', 'commander');
    $engine->start_scheduled_scan();
    $authorized = invoke($engine, 'read_job');
    verify($authorized['status'] === 'http' && $authorized['mode'] === 'commander' && count($worker_queue) === 1, 'Explicit automatic opt-in did not schedule selected remote work');
    engine_ajax($engine, 'cancel', array('job' => $authorized['id']));
    echo "PASS scan authorization: no fresh/legacy automatic work, read-only state, method change cancels queued workers without replacement and preserves report\n";
    $responses[home_url('/')] = $saved_root;
    echo "PASS background worker: no browser/admin session, signed loopback chain, storage separation and privacy, no duplicate lease, watchdog recovery, cancellation/replay guards, visible loopback failure and server-side resume\n";
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
    ob_end_flush();
}
