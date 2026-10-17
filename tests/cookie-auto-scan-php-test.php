<?php
/** WP-Cron fixture for automatic scan interval, lifecycle and manual independence. */
define('DAY_IN_SECONDS', 86400);
$options = array();
$actions = array();
$timestamps = array();
$scheduled_count = 0;
function add_action($hook, $callback) { global $actions; $actions[$hook] = $callback; }
function get_option($key, $default = false) { global $options; return isset($options[$key]) ? $options[$key] : $default; }
function update_option($key, $value) { global $options; $options[$key] = $value; }
function wp_next_scheduled($hook) { global $timestamps; return $timestamps ? min($timestamps) : false; }
function wp_schedule_event($timestamp, $recurrence, $hook) {
    global $timestamps, $scheduled_count;
    verify($recurrence === 'daily' && $hook === 'marrison_cookie_daily_scan', 'Unexpected schedule');
    $timestamps[] = $timestamp;
    $scheduled_count++;
    return true;
}
function wp_unschedule_event($timestamp, $hook) { global $timestamps; $index = array_search($timestamp, $timestamps, true); unset($timestamps[$index]); return true; }
function verify($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
require __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-cookie-scanner.php';
class PeriodicScannerFixture extends Marrison_Cookie_Scanner {
    public $scans = 0;
    public function __construct() {}
    public function perform_scan() { $this->scans++; update_option('marrison_cookie_last_scan', time()); return 1; }
}
$scanner = new PeriodicScannerFixture();
$hooks = new ReflectionMethod('Marrison_Cookie_Scanner', 'init_hooks');
if (PHP_VERSION_ID < 80100) { $hooks->setAccessible(true); }
$hooks->invoke($scanner);
verify($actions['init'][1] === 'schedule_cron' && $actions['marrison_cookie_daily_scan'][1] === 'maybe_auto_scan', 'Visitor/cron hooks bypass scheduling or interval');
call_user_func($actions['init']);
call_user_func($actions['wp']);
verify($scheduled_count === 1 && count($timestamps) === 1 && $scanner->scans === 0, 'Visitor request scans inline or schedules duplicate jobs');
call_user_func($actions['marrison_cookie_daily_scan']);
verify($scanner->scans === 1, 'First automatic scan missing');
call_user_func($actions['marrison_cookie_daily_scan']);
verify($scanner->scans === 1, 'Fresh scan repeated');
update_option('marrison_cookie_last_scan', time() - 6 * DAY_IN_SECONDS);
call_user_func($actions['marrison_cookie_daily_scan']);
verify($scanner->scans === 1, 'Default seven-day interval ignored');
update_option('marrison_cookie_last_scan', time() - 7 * DAY_IN_SECONDS);
call_user_func($actions['marrison_cookie_daily_scan']);
verify($scanner->scans === 2, 'Seven-day interval boundary skipped');
update_option('marrison_cookie_scan_interval', 1);
update_option('marrison_cookie_last_scan', time() - DAY_IN_SECONDS);
call_user_func($actions['marrison_cookie_daily_scan']);
verify($scanner->scans === 3, 'Daily interval ignored');
update_option('marrison_cookie_scan_interval', 99);
update_option('marrison_cookie_last_scan', time() - 30 * DAY_IN_SECONDS);
call_user_func($actions['marrison_cookie_daily_scan']);
verify($scanner->scans === 4, 'Thirty-day upper bound ignored');
update_option('marrison_cookie_scan_interval', 0);
update_option('marrison_cookie_last_scan', time() - 3600);
call_user_func($actions['marrison_cookie_daily_scan']);
verify($scanner->scans === 4, 'Invalid interval caused hourly scans');
update_option('marrison_cookie_auto_scan', false);
update_option('marrison_cookie_last_scan', 0);
call_user_func($actions['marrison_cookie_daily_scan']);
verify($scanner->scans === 4, 'Disabled automatic scan executed');
$timestamps[] = time() + 100;
call_user_func($actions['init']);
verify(!$timestamps, 'Disabled scan left scheduled events');
$scanner->perform_scan();
verify($scanner->scans === 5, 'Manual scan blocked by automatic settings');
update_option('marrison_cookie_auto_scan', true);
call_user_func($actions['init']);
verify(count($timestamps) === 1 && $scheduled_count === 2 && $scanner->scans === 5, 'Re-enable did not repair scheduling or scanned inline');
echo "PASS automatic scan: deferred visitor execution, no duplicate schedules, due/default/daily/bounded intervals, disable cleanup, re-enable and manual independence\n";
