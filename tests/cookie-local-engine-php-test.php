<?php
/** Current local engine; legacy remote orchestration is intentionally retired. */
ob_start(); require __DIR__ . '/cookie-static-scanner-php-test.php'; ob_end_clean();
$engine_dir = __DIR__ . '/.marrison-local-engine-' . bin2hex(random_bytes(6));
function add_action() {}
function wp_unslash($value) { return stripslashes($value); }
function wp_upload_dir() { return array('basedir' => $GLOBALS['engine_dir']); }
function wp_mkdir_p($path) { return is_dir($path) || mkdir($path, 0777, true); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_salt($scheme) { return 'local-engine-fixture-only'; }
function add_query_arg($args, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args); }
function wp_next_scheduled() { return $GLOBALS['scheduled'] ?? false; }
function wp_schedule_single_event($time) { $GLOBALS['scheduled'] = $time; }
function update_option($name, $value) {}
function do_action($name, $job) { $GLOBALS['finished'][] = $job; }
function add_filter() {}
function is_user_logged_in() { return !empty($GLOBALS['fixture_logged_in']); }
class Marrison_Cookie_Scanner {
    public static $records = array();
    public static $writes = array();
    public static function get_instance() { return new self(); }
    public function engine_prepare() { return array(); }
    public function engine_store($rows, $categories, $complete) { check_static($complete, 'New scan retained an earlier inventory'); self::$writes[] = $rows; self::$records = $rows; return true; }
    public function get_cookies($category) { return array(); }
}
require __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php';
try {
    $engine = Marrison_Addon_Cookie_Scan_Engine::get_instance();
    Marrison_Cookie_Scanner::$records = array(array('cookie_name' => 'previous_scan_only'));
    $engine->start_local_scan();
    check_static(Marrison_Cookie_Scanner::$writes[0] === array(), 'New scan did not clear old inventory at start');
    $path = $engine_dir . '/marrison-cookie/scan-engine.php';
    $job = json_decode(substr(file_get_contents($path), strlen("<?php exit; ?>\n")), true);
    check_static($job['status'] === 'http' && $job['static_scan']['posts'] === 20 && $job['mode'] === 'local' && $GLOBALS['scheduled'], 'Initial static cursor not persisted/scheduled');
    $engine->scheduled_step(); $engine->scheduled_step();
    $job = json_decode(substr(file_get_contents($path), strlen("<?php exit; ?>\n")), true);
    check_static($job['status'] === 'completed' && $job['static_scan']['posts'] === 41 && !$job['cookies'] && count($GLOBALS['finished']) === 1, 'Static completion was not truthful');
    check_static(count(Marrison_Cookie_Scanner::$records) === 3, 'Catalog records not handed to existing storage');
    foreach (Marrison_Cookie_Scanner::$records as $row) { check_static($row['observed'] === false && $row['source'] === 'catalogo', 'Declared cookie became observed'); }
    check_static(!$engine->technical_only(), 'Optional services disabled the banner');
    $saved_job = $job;
    $job['services'] = array(); file_put_contents($path, "<?php exit; ?>\n" . json_encode($job));
    check_static($engine->technical_only(), 'Technical-only completed scan did not disable banner');
    $job['services'] = array(array('host' => 'unknown.example', 'profiles' => array())); file_put_contents($path, "<?php exit; ?>\n" . json_encode($job));
    check_static(!$engine->technical_only(), 'Unclassified host falsely became technical');
    file_put_contents($path, "<?php exit; ?>\n" . json_encode($saved_job));
    $summary_method = new ReflectionMethod($engine, 'summary');
    $require_method = new ReflectionMethod($engine, 'require_job');
    if (PHP_VERSION_ID < 80100) { $summary_method->setAccessible(true); $require_method->setAccessible(true); }
    foreach (array('remote-old', 'remote-current', 'local-old', 'local-no-cursor') as $scenario) {
        $legacy = $saved_job;
        if (strpos($scenario, 'remote') === 0) { $legacy['mode'] = 'commander'; }
        if (strpos($scenario, 'old') !== false) { $legacy['revision'] = '20261021.1'; }
        if ($scenario === 'local-no-cursor') { unset($legacy['static_scan']); }
        $legacy['status'] = $scenario === 'remote-current' ? 'browser' : 'partial';
        $legacy['issues'] = array(array('message' => 'Richieste non pubbliche o navigazioni fuori sito bloccate: 2.', 'url' => 'https://example.test/hello-world/'));
        $legacy['issues_total'] = 1;
        file_put_contents($path, "<?php exit; ?>\n" . json_encode($legacy));
        $before_state = file_get_contents($path); $before_records = Marrison_Cookie_Scanner::$records;
        $summary = $summary_method->invoke($engine, $legacy);
        check_static($summary['stale'] && !$summary['static_pending'] && $summary['next'] === null, 'Legacy report looked resumable: ' . $scenario);
        check_static($summary['status'] !== 'browser' && $summary['issues'] === $legacy['issues'], 'Legacy history changed/lost');
        check_static(file_get_contents($path) === $before_state && Marrison_Cookie_Scanner::$records === $before_records, 'Reading legacy history changed inventory');
        $_POST['job'] = $legacy['id'];
        try { $require_method->invoke($engine); throw new LogicException('Legacy job could resume'); }
        catch (RuntimeException $error) { check_static(strpos($error->getMessage(), 'nuova scansione locale') !== false, 'Unclear legacy restart error'); }
        $engine->scheduled_step();
        check_static(Marrison_Cookie_Scanner::$records === $before_records, 'Legacy cron changed inventory');
        $legacy['status'] = 'completed'; $legacy['services'] = array();
        file_put_contents($path, "<?php exit; ?>\n" . json_encode($legacy));
        check_static(!$engine->technical_only(), 'Legacy scan disabled banner as technical-only');
    }
    file_put_contents($path, "<?php exit; ?>\n" . json_encode($saved_job));
    $current = $summary_method->invoke($engine, $saved_job);
    check_static(!$current['stale'] && $current['status'] === 'completed', 'Current static job became stale');
    check_static(!$saved_job['pages'] && !$saved_job['limit_reached'] && !$saved_job['issues_total'], 'Disabled iframe selected samples or generated visit warnings');
    $static_legacy_counters = $saved_job;
    $static_legacy_counters['pages'] = array('https://example.test/', 'https://example.test/a/', 'https://example.test/b/');
    $static_legacy_counters['issues'] = array(array('url' => '', 'message' => 'Limite di 3 campioni raggiunto: altri gruppi non sono stati visitati.'));
    $static_legacy_counters['issues_total'] = 1; $static_legacy_counters['limit_reached'] = true;
    $summary = $summary_method->invoke($engine, $static_legacy_counters);
    check_static($summary['static_posts'] === 41 && !$summary['local_iframe'] && $summary['browser_total'] === 0 && $summary['browser_done'] === 0, 'Static contents confused with browser checks');
    check_static($summary['sampling'] === null && !$summary['issues_total'] && !$summary['issues'] && !$summary['limit_reached'], 'Disabled iframe exposed old sample-limit warning');
    $static_legacy_counters['local_iframe'] = true; $static_legacy_counters['browser_cursor'] = 2;
    $summary = $summary_method->invoke($engine, $static_legacy_counters);
    check_static($summary['local_iframe'] && $summary['browser_total'] === 6 && $summary['browser_done'] === 2 && $summary['issues_total'] === 1 && $summary['limit_reached'], 'Enabled browser lost real counters/warnings');

    $new_job = new ReflectionMethod($engine, 'new_job'); if (PHP_VERSION_ID < 80100) { $new_job->setAccessible(true); }
    $browser_job = $new_job->invoke($engine, 1, array('https://example.test/extra/'), 'local', true, 20);
    check_static($browser_job['local_iframe'] && $browser_job['pages'] && $browser_job['local_max_seconds'] === 20, 'Explicit browser command ignored');
    check_static(!get_option('marrison_cookie_local_iframe', false), 'Manual browser request changed the saved preference');
    $urls = array(); for ($i = 1; $i <= 12; $i++) { $urls[] = 'https://example.test/sample-' . $i . '/'; }
    $all_job = $new_job->invoke($engine, 0, $urls, 'local', true, 0);
    check_static(count($all_job['pages']) === 13 && count($all_job['sampling']['groups']) === 13 && !$all_job['sampling']['unselected'] && !$all_job['limit_reached'] && !$all_job['issues_total'], 'Default full scan still dropped groups at 3 or 10 samples');
    $all_job['static_scan']['done'] = true; $all_job['status'] = 'browser'; $all_job['local_started'] = time() - 300;
    $browser_next = new ReflectionMethod($engine, 'browser_next'); if (PHP_VERSION_ID < 80100) { $browser_next->setAccessible(true); }
    check_static($all_job['local_max_seconds'] === 0 && $browser_next->invoke($engine, $all_job) !== null, 'Full scan stopped at the former total time budget');
    $summary = $summary_method->invoke($engine, $all_job);
    check_static($summary['browser_total'] === 26 && $summary['sampling']['selected'] === 13, 'Full scan lost before/after checks');
    $all_job['local_max_seconds'] = 20;
    check_static($browser_next->invoke($engine, $all_job) === null, 'Explicit total time budget ignored');
    $limited_job = $new_job->invoke($engine, 3, $urls, 'local', true, 20);
    check_static(count($limited_job['pages']) === 3 && count($limited_job['sampling']['unselected']) === 10 && $limited_job['limit_reached'], 'Explicit reduced scan limit ignored');
    $GLOBALS['static_bodies'] = array(40 => '<iframe src="https://player.vimeo.com/video/late-service"></iframe>');
    $coverage_job = $new_job->invoke($engine, 0, array(), 'local', true, 0);
    $http_step = new ReflectionMethod($engine, 'http_step'); if (PHP_VERSION_ID < 80100) { $http_step->setAccessible(true); }
    do { $arguments = array(&$coverage_job); $http_step->invokeArgs($engine, $arguments); } while ($coverage_job['status'] === 'http');
    check_static(count($coverage_job['seen']) === 42 && $coverage_job['sampling']['candidates'] === 42, 'Published routes were never offered to the browser grouping plan');
    check_static(in_array('https://example.test/post-40/', $coverage_job['pages'], true), 'A unique service on a later post was omitted from browser samples');
    check_static($coverage_job['sampling']['equivalents'] > 30 && count($coverage_job['pages']) < 10, 'Equivalent posts were all visited instead of grouped');
    unset($GLOBALS['static_bodies']);
    // WooCommerce redirects an empty checkout to the cart. Only a server-recorded,
    // same-origin target may retain the current signed anonymous scan context.
    $redirect_job = $new_job->invoke($engine, 0, array(), 'local', true, 0);
    $redirect_job['status'] = 'browser'; $redirect_job['static_scan']['done'] = true;
    file_put_contents($path, "<?php exit; ?>\n" . json_encode($redirect_job));
    $next = $browser_next->invoke($engine, $redirect_job);
    $frame_property = new ReflectionProperty($engine, 'frame'); if (PHP_VERSION_ID < 80100) { $frame_property->setAccessible(true); }
    $frame_property->setValue($engine, $next + array('job' => $redirect_job['id'], 'actual_url' => $next['url']));
    $_SERVER['HTTP_SEC_FETCH_DEST'] = 'iframe';
    check_static($engine->scan_redirect('https://other.test/cart/') === 'https://other.test/cart/', 'Signed scan context leaked cross-origin');
    check_static($engine->scan_redirect('https://example.test/wp-admin/') === 'https://example.test/wp-admin/', 'Signed scan entered an admin route');
    $redirect_url = $engine->scan_redirect('https://example.test/cart/');
    parse_str(parse_url($redirect_url, PHP_URL_QUERY), $_GET);
    check_static(($_GET['marrison_scan_token'] ?? '') === $next['token'], 'Legitimate redirect lost the signed scan token');
    $frame_context = new ReflectionMethod($engine, 'frame_context'); if (PHP_VERSION_ID < 80100) { $frame_context->setAccessible(true); }
    $_SERVER['REQUEST_URI'] = '/cart/?' . parse_url($redirect_url, PHP_URL_QUERY);
    check_static(($frame_context->invoke($engine)['actual_url'] ?? '') === 'https://example.test/cart/', 'Recorded redirect did not restore the frame agent');
    $_SERVER['REQUEST_URI'] = '/unrecorded/?' . parse_url($redirect_url, PHP_URL_QUERY);
    check_static($frame_context->invoke($engine) === null, 'Copied token authorized an unrecorded route');
    $_SERVER['REQUEST_URI'] = '/cart/?' . parse_url($redirect_url, PHP_URL_QUERY); $GLOBALS['fixture_logged_in'] = true;
    check_static($frame_context->invoke($engine) === null, 'Authenticated browser entered anonymous scan');
    unset($GLOBALS['fixture_logged_in'], $_SERVER['HTTP_SEC_FETCH_DEST'], $_SERVER['REQUEST_URI']); $_GET = array();
    $frame_property->setValue($engine, null);
    $static_job = $new_job->invoke($engine, 3, array(), 'local', false, 500);
    check_static(!$static_job['local_iframe'] && !$static_job['pages'] && $static_job['local_max_seconds'] === 120, 'Manual static command/browser bounds ignored');
    $remote = new ReflectionMethod($engine, 'remote_step'); if (PHP_VERSION_ID < 80100) { $remote->setAccessible(true); }
    try { $remote->invoke($engine); throw new RuntimeException('Remote path remained reachable'); }
    catch (RuntimeException $error) { check_static(strpos($error->getMessage(), 'dismessa') !== false, 'Unexpected retirement error'); }
    echo "PASS local engine: persisted cursor, cron resume, static completion, fresh inventory per scan, legacy reports isolated and remote execution disabled\n";
} finally {
    foreach (glob($engine_dir . '/marrison-cookie/*') ?: array() as $file) { unlink($file); }
    if (is_dir($engine_dir . '/marrison-cookie')) { rmdir($engine_dir . '/marrison-cookie'); }
    if (is_dir($engine_dir)) { rmdir($engine_dir); }
}
