<?php
/** Observed-only scan, legacy cleanup and deduplication in DB/JSON fixtures. */
require __DIR__ . '/cookie-expiration-php-test.php';
function update_option($name, $value) {}
function check_ajax_referer($action = '', $key = '') { global $engine_validate_nonce; if ($engine_validate_nonce && ($_POST[$key] ?? '') !== 'fixture') { throw new RuntimeException('Invalid nonce'); } }
function current_user_can() { global $engine_can_manage; return $engine_can_manage !== false; }
function get_option($name, $default = false) { return $default; }
function add_shortcode() {}
function esc_sql($text) { return $text; }
class ScanJsonResponse extends RuntimeException {
    public $success, $data;
    public function __construct($success, $data) { $this->success = $success; $this->data = $data; parent::__construct('JSON'); }
}
function wp_send_json_success($data) { throw new ScanJsonResponse(true, $data); }
function wp_send_json_error($data) { throw new ScanJsonResponse(false, $data); }
function scan_json($callback) {
    try { call_user_func($callback); } catch (ScanJsonResponse $response) { return $response; }
    throw new RuntimeException('Missing scan JSON response');
}
require __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-setup-wizard.php';
$wizard = Marrison_Setup_Wizard::get_instance();
define('DB_NAME', 'cookie_fixture');
$test_directory = sys_get_temp_dir() . '/marrison-observed-scan-' . uniqid();
define('ABSPATH', $test_directory . '/');
mkdir(ABSPATH . 'wp-admin/includes', 0777, true);
file_put_contents(ABSPATH . 'wp-admin/includes/upgrade.php', '<?php function dbDelta($sql) {}');
class ObservedCookieDatabase extends CookieDatabase {
    public $fail_lookup = false, $fail_delete = false, $fail_table = false;
    public function get_charset_collate() { return ''; }
    public function prepare($sql, ...$values) {
        if (isset($values[0]) && is_array($values[0])) { $values = $values[0]; }
        return preg_replace_callback('/%[sd]/', function($match) use (&$values) {
            $value = array_shift($values);
            return $match[0] === '%d' ? (string) (int) $value : "'" . addslashes($value) . "'";
        }, $sql);
    }
    public function get_var($sql) { return $this->fail_table ? false : (strpos($sql, 'information_schema') !== false ? 'wp_marrison_cookies' : 'cookie_expiration_status'); }
    public function esc_like($text) { return $text; }
    public function get_results($sql) {
        $this->last_error = '';
        $rows = array_values($this->rows);
        usort($rows, function($a, $b) { return strcmp($b['scan_date'], $a['scan_date']) ?: ($b['id'] <=> $a['id']); });
        return array_map(function($row) { return (object) $row; }, $rows);
    }
    public function get_row($sql) {
        $this->last_error = '';
        if ($this->fail_lookup) { $this->last_error = 'fixture lookup failed'; return null; }
        preg_match_all("/(cookie_name|cookie_domain|cookie_path) = '([^']*)'/", $sql, $matches, PREG_SET_ORDER);
        foreach ($this->get_results('') as $row) {
            $match = true;
            foreach ($matches as $field) { $match = $match && $row->{$field[1]} === stripslashes($field[2]); }
            if ($match) { return $row; }
        }
        return null;
    }
    public function insert($table, $data) {
        $data['id'] = $this->rows ? max(array_keys($this->rows)) + 1 : 1;
        $this->rows[$data['id']] = $data;
        return 1;
    }
    public function query($sql) {
        $this->last_error = '';
        if ($this->fail_table && strpos($sql, 'CREATE TABLE') === 0) { $this->last_error = 'fixture table unavailable'; return false; }
        if ($this->fail_delete) { $this->last_error = 'fixture delete failed'; return false; }
        if (preg_match('/WHERE id IN \(([^)]+)\)/', $sql, $match)) {
            $ids = array_map('intval', explode(',', $match[1]));
            foreach ($ids as $id) { unset($this->rows[$id]); }
            return count($ids);
        }
        return 0;
    }
}
function record($id, $name, $source, $category = 'functional', $path = '/', $domain = 'example.test') {
    return array('id' => $id, 'cookie_name' => $name, 'cookie_domain' => $domain, 'cookie_path' => $path,
        'cookie_category' => $category, 'cookie_expiration' => null, 'cookie_expiration_status' => 'unknown',
        'cookie_description' => 'Fixture', 'source' => $source, 'scan_date' => gmdate('Y-m-d H:i:s'));
}
try {
    $legacy = array();
    $id = 1;
    for ($repeat = 0; $repeat < 18; $repeat++) {
        foreach (invoke($scanner, 'get_wordpress_cookies') as $category => $patterns) {
            foreach ($patterns as $name => $description) { $legacy[] = record($id++, $name, 'wordpress', $category); }
        }
        foreach (invoke($scanner, 'get_third_party_cookies') as $name => $info) { if ($name !== '_ga_*') { $legacy[] = record($id++, $name, 'third_party', $info['category']); } }
    }
    verify(count($legacy) === 432, 'Expected the 24 catalog entries repeated 18 times');
    $legacy[] = record($id++, '_ga', 'homepage', 'marketing'); // Manually assigned category.
    $legacy[] = record($id++, '_ga', 'homepage', 'analytics');
    $legacy[count($legacy) - 1]['cookie_expiration_status'] = 'session';
    $legacy[] = record($id++, '_ga', 'current', 'analytics');
    $legacy[count($legacy) - 1]['cookie_domain'] = '.EXAMPLE.TEST';
    $legacy[count($legacy) - 1]['cookie_expiration'] = '2030-01-01 00:00:00';
    $legacy[] = record($id++, 'same_name', 'homepage', 'functional', '/');
    $legacy[] = record($id++, 'same_name', 'homepage', 'functional', '/other');
    $legacy[] = record($id++, 'same_name', 'homepage', 'functional', '/', 'other.test');
    $legacy[] = record($id++, 'wordpress_logged_in_admin', 'current', 'necessary');
    $legacy[] = record($id++, 'wp-settings-1', 'current');
    $legacy[] = record($id++, 'unproven', 'unknown');
    $wpdb = new ObservedCookieDatabase();
    foreach ($legacy as $row) { $wpdb->rows[$row['id']] = $row; }
    storage($scanner, 'table');
    $public = $scanner->get_cookies();
    verify(count($public) === 4, 'Public list exposes catalog/admin/duplicate/unknown records');
    $ga = array_values(array_filter($public, function($row) { return $row->cookie_name === '_ga'; }))[0];
    verify($ga->cookie_category === 'marketing' && $ga->cookie_expiration_status === 'session' && $ga->cookie_expiration === null, 'Deduplication lost manual category or observed session metadata');
    verify(count($scanner->get_cookies('marketing')) === 1 && count($scanner->get_cookies('analytics')) === 0, 'Category filtering runs before consolidation');
    verify(invoke($scanner, 'clear_old_scans'), 'Legacy cleanup failed');
    verify(count($wpdb->rows) === 5, 'Cleanup failed to remove synthetic/admin/duplicate rows or removed unproven data');
    $stored_ga = array_values(array_filter($wpdb->rows, function($row) { return $row['cookie_name'] === '_ga'; }))[0];
    verify($stored_ga['cookie_expiration'] === null && $stored_ga['cookie_category'] === 'marketing', 'Database consolidation did not persist null expiration/manual category');

    $response = array('cookies' => array(http_cookie('_ga'), http_cookie('session')), 'headers' => new HeaderValues(array('_ga=value; Max-Age=3600; Path=/', 'session=value; Path=/')), 'raw_headers' => '');
    $_COOKIE = array('wordpress_logged_in_admin' => 'secret', 'wordpress_sec_admin' => 'secret', 'wordpress_' . str_repeat('a', 32) => 'secret', 'wordpress_test_cookie' => 'secret', 'wp-settings-1' => 'secret', 'wp-settings-time-1' => 'secret', '_ga' => 'private', 'request_only' => 'private');
    $scanner->perform_scan();
    verify($scanner->get_last_scan_error() === '', 'Observed scan reported an unexpected error');
    $count = count($wpdb->rows);
    $scanner->perform_scan();
    verify(count($wpdb->rows) === $count, 'Repeated scans inserted duplicates');
    $public = $scanner->get_cookies();
    verify(count($public) === 6, 'Observed scan count is wrong');
    foreach ($public as $row) { verify(in_array($row->source, array('current', 'homepage'), true) && strpos($row->cookie_name, '*') === false, 'A catalog entry was recreated'); }
    verify(strpos(json_encode($wpdb->rows), 'private') === false && strpos(json_encode($wpdb->rows), 'secret') === false, 'Cookie values leaked into storage');
    $wpdb->fail_lookup = true;
    verify(invoke($scanner, 'save_cookie', array('new_cookie', 'current')) === 0 && count($wpdb->rows) === $count, 'Failed existence lookup inserted a duplicate/new record');

    $wpdb = new ObservedCookieDatabase();
    $_COOKIE = array();
    $response['cookies'] = array();
    $result = scan_json(array($scanner, 'ajax_scan_cookies'));
    verify($result->success && $result->data['count'] === 0 && !$wpdb->rows, 'Empty observed scan was reported as failure or generated catalog rows');
    $result = scan_json(array($wizard, 'ajax_scan_cookies'));
    verify($result->success && !$result->data['cookies'], 'Wizard treats an empty observed scan as failure');
    $wpdb->rows[1] = record(1, 'real_cookie', 'current');
    $wpdb->fail_delete = true;
    foreach (array($scanner, $wizard) as $endpoint) {
        $result = scan_json(array($endpoint, 'ajax_scan_cookies'));
        verify(!$result->success && strpos($result->data['message'], 'fixture delete failed') !== false, 'Cleanup failure was hidden by existing records');
    }
    $wpdb = new ObservedCookieDatabase();
    $wpdb->fail_table = true;
    $result = scan_json(array($scanner, 'ajax_scan_cookies'));
    verify($result->success && $result->data['storage'] === 'json' && $result->data['count'] === 0 && strpos($scanner->get_last_scan_error(), 'fixture table unavailable') !== false, 'Working JSON fallback failed or discarded the database diagnostic');

    storage($scanner, 'json');
    invoke($scanner, 'write_json_cookies', array($legacy));
    verify(count(invoke($scanner, 'get_json_cookies')) === 4, 'JSON public list exposes catalog/admin/duplicate records');
    invoke($scanner, 'prepare_json_scan');
    $_COOKIE = array('wordpress_logged_in_admin' => 'secret', 'wp-settings-1' => 'secret', '_ga' => 'private', 'request_only' => 'private');
    $response['cookies'] = array(http_cookie('_ga'), http_cookie('session'));
    invoke($scanner, 'scan_current_cookies');
    invoke($scanner, 'scan_homepage_cookies');
    invoke($scanner, 'scan_current_cookies');
    $json = invoke($scanner, 'read_json_cookies');
    verify(count($json) === 3 && count(invoke($scanner, 'get_json_cookies', array('marketing'))) === 1, 'JSON scan inflated counts or lost manual category');
    verify(strpos(json_encode($json), 'private') === false && strpos(json_encode($json), 'secret') === false, 'JSON stored cookie values');
    invoke($scanner, 'save_cookie', array('session', 'homepage', 'functional', '', 'other.test', '/other'));
    verify(count(invoke($scanner, 'read_json_cookies')) === 4, 'JSON merged distinct cookie domain/path identities');
    echo "PASS observed-only scan: 432 synthetic rows removed, admin filtering, DB/JSON deduplication, per-domain/path identity, manual categories, null expiration, repeated/empty scans and failure diagnostics\n";
} finally {
    foreach (array('marrison-cookie/scanned-cookies.json', 'marrison-cookie/index.html', 'marrison-cookie/.htaccess', 'wp-admin/includes/upgrade.php') as $file) {
        if (is_file($test_directory . '/' . $file)) { unlink($test_directory . '/' . $file); }
    }
    foreach (array('marrison-cookie', 'wp-admin/includes', 'wp-admin', '') as $directory) {
        if (is_dir($test_directory . '/' . $directory)) { rmdir($test_directory . '/' . $directory); }
    }
}
