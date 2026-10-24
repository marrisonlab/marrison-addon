<?php
/** Current local engine; legacy remote orchestration is intentionally retired. */
ob_start(); require __DIR__ . '/cookie-static-scanner-php-test.php'; ob_end_clean();
$engine_dir = __DIR__ . '/.marrison-local-engine-' . bin2hex(random_bytes(6));
function add_action() {}
function wp_upload_dir() { return array('basedir' => $GLOBALS['engine_dir']); }
function wp_mkdir_p($path) { return is_dir($path) || mkdir($path, 0777, true); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_next_scheduled() { return $GLOBALS['scheduled'] ?? false; }
function wp_schedule_single_event($time) { $GLOBALS['scheduled'] = $time; }
function update_option($name, $value) {}
function do_action($name, $job) { $GLOBALS['finished'][] = $job; }
class Marrison_Cookie_Scanner {
    public static $records = array();
    public static function get_instance() { return new self(); }
    public function engine_prepare() { return array(); }
    public function engine_store($rows, $categories, $complete) { check_static(!$complete, 'Static scan deleted prior observations'); self::$records = $rows; return true; }
    public function get_cookies($category) { return array(); }
}
require __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php';
try {
    $engine = Marrison_Addon_Cookie_Scan_Engine::get_instance();
    $engine->start_local_scan();
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
    $remote = new ReflectionMethod($engine, 'remote_step'); if (PHP_VERSION_ID < 80100) { $remote->setAccessible(true); }
    try { $remote->invoke($engine); throw new RuntimeException('Remote path remained reachable'); }
    catch (RuntimeException $error) { check_static(strpos($error->getMessage(), 'dismessa') !== false, 'Unexpected retirement error'); }
    echo "PASS local engine: persisted cursor, cron resume, static completion, catalog storage preserves observations and remote execution disabled\n";
} finally {
    foreach (glob($engine_dir . '/marrison-cookie/*') ?: array() as $file) { unlink($file); }
    if (is_dir($engine_dir . '/marrison-cookie')) { rmdir($engine_dir . '/marrison-cookie'); }
    if (is_dir($engine_dir)) { rmdir($engine_dir); }
}
