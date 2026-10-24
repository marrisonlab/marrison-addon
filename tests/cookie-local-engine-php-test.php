<?php
/** Current local engine; legacy remote orchestration is intentionally retired. */
ob_start(); require __DIR__ . '/cookie-static-scanner-php-test.php'; ob_end_clean();
$engine_dir = __DIR__ . '/.marrison-local-engine-' . bin2hex(random_bytes(6));
function add_action() {}
function wp_unslash($value) { return stripslashes($value); }
function wp_upload_dir() { return array('basedir' => $GLOBALS['engine_dir']); }
function wp_mkdir_p($path) { return is_dir($path) || mkdir($path, 0777, true); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_next_scheduled() { return $GLOBALS['scheduled'] ?? false; }
function wp_schedule_single_event($time) { $GLOBALS['scheduled'] = $time; }
function update_option($name, $value) {}
function do_action($name, $job) { $GLOBALS['finished'][] = $job; }
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
    $remote = new ReflectionMethod($engine, 'remote_step'); if (PHP_VERSION_ID < 80100) { $remote->setAccessible(true); }
    try { $remote->invoke($engine); throw new RuntimeException('Remote path remained reachable'); }
    catch (RuntimeException $error) { check_static(strpos($error->getMessage(), 'dismessa') !== false, 'Unexpected retirement error'); }
    echo "PASS local engine: persisted cursor, cron resume, static completion, fresh inventory per scan, legacy reports isolated and remote execution disabled\n";
} finally {
    foreach (glob($engine_dir . '/marrison-cookie/*') ?: array() as $file) { unlink($file); }
    if (is_dir($engine_dir . '/marrison-cookie')) { rmdir($engine_dir . '/marrison-cookie'); }
    if (is_dir($engine_dir)) { rmdir($engine_dir); }
}
