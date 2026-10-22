<?php
/** Scanner fixture: observed headers, existing DB records and real JSON file persistence. */
function add_action() {}
function home_url($path = '') { return 'https://example.test' . $path; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function current_time($format) { return gmdate('Y-m-d H:i:s'); }
function wp_remote_get($url = '', $args = array()) {
    global $response, $responses, $http_requests;
    if (is_array($responses)) {
        $http_requests[] = array($url, $args);
        return isset($responses[$url]) ? $responses[$url] : array('response' => array('code' => 404), 'body' => '', 'response_headers' => array(), 'cookies' => array(), 'headers' => array(), 'raw_headers' => '');
    }
    return $response;
}
function is_wp_error($response) { return $response instanceof WP_Error; }
function wp_remote_retrieve_cookies($response) { return $response['cookies']; }
function wp_remote_retrieve_headers($response) { return $response['headers']; }
function wp_remote_retrieve_header($response, $name) { return isset($response['response_headers'][$name]) ? $response['response_headers'][$name] : ($name === 'set-cookie' ? $response['raw_headers'] : ''); }
function wp_upload_dir() { global $test_directory; return array('basedir' => $test_directory, 'error' => false); }
function trailingslashit($path) { return rtrim($path, '/\\') . '/'; }
function wp_mkdir_p($path) { return is_dir($path) || mkdir($path, 0777, true); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
if (isset($argv[1])) {
    require $argv[1]; // Optionally use the installed WordPress WP_Http_Cookie class.
} else {
    class WP_Http_Cookie {
        public $name, $domain, $path, $expires;
        public function __construct($data) { foreach ($data as $key => $value) { $this->$key = $value; } }
    }
}
class HeaderValues {
    private $values;
    public function __construct($values) { $this->values = $values; }
    public function getValues($name) { return $this->values; }
}
class CookieDatabase {
    public $prefix = 'wp_', $last_error = '', $rows = array(), $fail_update = false;
    public function prepare($sql, $name) { return $name; }
    public function get_row($name) { return isset($this->rows[$name]) ? (object) $this->rows[$name] : null; }
    public function insert($table, $data) { $data['id'] = count($this->rows) + 1; $this->rows[$data['cookie_name']] = $data; return 1; }
    public function update($table, $data, $where) {
        if ($this->fail_update) { $this->last_error = 'fixture update failed'; return false; }
        foreach ($this->rows as $name => $row) {
            if ($row['id'] === $where['id']) { $this->rows[$name] = array_merge($row, $data); return 1; }
        }
        return 0;
    }
}
function verify($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function invoke($object, $method, $args = array()) { $r = new ReflectionMethod($object, $method); if (PHP_VERSION_ID < 80100) { $r->setAccessible(true); } return $r->invokeArgs($object, $args); }
function storage($scanner, $mode) { $r = new ReflectionProperty($scanner, 'storage_mode'); if (PHP_VERSION_ID < 80100) { $r->setAccessible(true); } $r->setValue($scanner, $mode); }
function http_cookie($name, $expires = null, $path = '/') { return new WP_Http_Cookie(array('name' => $name, 'domain' => 'example.test', 'path' => $path, 'expires' => $expires)); }
require __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-cookie-scanner.php';
$scanner = Marrison_Cookie_Scanner::get_instance();
$observed_at = 2000000000;
$cookie = http_cookie('test');
$cases = array(
    array('test=v; Expires=Wed, 09 Jun 2027 10:18:14 GMT', 'persistent', '2027-06-09 10:18:14'),
    array('test=v; Expires=Thu, 09 Jun 2027 10:18:14 GMT', 'persistent', '2027-06-09 10:18:14'),
    array('test=v; Expires=Wed, 09-Jun-27 10:18:14 GMT', 'persistent', '2027-06-09 10:18:14'),
    array('test=v; Max-Age=3600; Expires=Wed, 09 Jun 2027 10:18:14 GMT', 'persistent', gmdate('Y-m-d H:i:s', $observed_at + 3600)),
    array('test=v; Max-Age=0', 'persistent', gmdate('Y-m-d H:i:s', $observed_at)),
    array('test=v; Max-Age=-100', 'persistent', gmdate('Y-m-d H:i:s', $observed_at)),
    array('test=v; Max-Age=003600', 'persistent', gmdate('Y-m-d H:i:s', $observed_at + 3600)),
    array('test=v; HttpOnly; Secure', 'session', null),
    array('test=v; Expires=nonsense', 'unknown', null),
    array('test=v; Expires=tomorrow', 'unknown', null),
    array('test=v; Expires=Wed, 31 Feb 2027 10:18:14 GMT', 'unknown', null),
    array('test=v; Expires=3600', 'unknown', null),
    array('test=v; Max-Age=nonsense', 'unknown', null),
    array('test=v; Max-Age=nonsense; Expires=Wed, 09 Jun 2027 10:18:14 GMT', 'persistent', '2027-06-09 10:18:14'),
    array('test=v; Max-Age=999999999999999999999999', 'unknown', null),
    array('test=v; Max-Age=1.5', 'unknown', null)
);
foreach ($cases as $case) {
    $actual = invoke($scanner, 'get_http_cookie_expiration', array($cookie, array($case[0]), $observed_at));
    verify($actual === array('value' => $case[2], 'status' => $case[1]), 'Expiration parsing failed: ' . $case[0]);
}
verify(invoke($scanner, 'get_http_cookie_expiration', array($cookie, array(), $observed_at))['status'] === 'unknown', 'No observed attributes incorrectly imply session');
verify(invoke($scanner, 'get_http_cookie_expiration', array(http_cookie('test', 2000000123), array(), $observed_at))['value'] === gmdate('Y-m-d H:i:s', 2000000123), 'WordPress timestamp fallback failed');
verify(invoke($scanner, 'get_http_cookie_expiration', array(http_cookie('test', 'Wed, 09 Jun 2027 10:18:14 GMT'), array(), $observed_at))['value'] === '2027-06-09 10:18:14', 'WordPress formatted date fallback failed');
$matching = invoke($scanner, 'get_http_cookie_expiration', array($cookie, array('test=v; Path=/other; Max-Age=10', 'test=v; Domain=other.test; Max-Age=10', 'test=v; Domain=.example.test; Path=/; Max-Age=100'), $observed_at));
verify($matching['value'] === gmdate('Y-m-d H:i:s', $observed_at + 100), 'Cookie path/domain association failed');
$raw_headers = array(
    'persistent=v; Expires=Wed, 09 Jun 2027 10:18:14 GMT; Path=/',
    'maxage=v; Max-Age=3600; Path=/',
    'session=v; Path=/; HttpOnly',
    'invalid=v; Expires=nonsense; Path=/'
);
$response = array('headers' => new HeaderValues($raw_headers), 'raw_headers' => implode(',', $raw_headers), 'cookies' => array(http_cookie('persistent'), http_cookie('maxage'), http_cookie('session'), http_cookie('invalid')));
$test_directory = sys_get_temp_dir() . '/marrison-cookie-expiration-' . uniqid();
try {
    foreach (array('table', 'json') as $mode) {
        $wpdb = new CookieDatabase();
        storage($scanner, $mode);
        if ($mode === 'json') { invoke($scanner, 'prepare_json_scan'); }
        $_COOKIE = array('persistent' => 'private', 'request_only' => 'private');
        invoke($scanner, 'scan_current_cookies');
        if ($mode === 'table') { $wpdb->rows['persistent']['cookie_category'] = 'marketing'; }
        else { $json = invoke($scanner, 'read_json_cookies'); $json[0]['cookie_category'] = 'marketing'; invoke($scanner, 'write_json_cookies', array($json)); }
        invoke($scanner, 'scan_homepage_cookies');
        $rows = $mode === 'table' ? $wpdb->rows : array_column(invoke($scanner, 'read_json_cookies'), null, 'cookie_name');
        verify(count($rows) === 5, 'Duplicate or missing cookies in ' . $mode);
        verify($rows['persistent']['cookie_expiration'] === '2027-06-09 10:18:14' && $rows['persistent']['cookie_category'] === 'marketing', 'Expiration update lost metadata/manual category in ' . $mode);
        verify($rows['session']['cookie_expiration_status'] === 'session' && $rows['session']['cookie_expiration'] === null, 'Session persistence failed in ' . $mode);
        verify($rows['request_only']['cookie_expiration_status'] === 'unknown' && $rows['invalid']['cookie_expiration_status'] === 'unknown', 'Unknown persistence failed in ' . $mode);
        verify(abs(strtotime($rows['maxage']['cookie_expiration'] . ' UTC') - time() - 3600) <= 2, 'Max-Age not persisted in ' . $mode);
        verify(strpos(json_encode($rows), 'private') === false, 'Cookie values persisted');
        invoke($scanner, 'scan_current_cookies');
        $rows = $mode === 'table' ? $wpdb->rows : array_column(invoke($scanner, 'read_json_cookies'), null, 'cookie_name');
        verify($rows['persistent']['cookie_expiration_status'] === 'persistent', 'Request cookies overwrote observed expiration');
        // Switch persistent to session via a fresh observed header, keeping its category.
        $response['headers'] = new HeaderValues(array('persistent=v; Path=/'));
        $response['cookies'] = array(http_cookie('persistent'));
        invoke($scanner, 'scan_homepage_cookies');
        $rows = $mode === 'table' ? $wpdb->rows : array_column(invoke($scanner, 'read_json_cookies'), null, 'cookie_name');
        verify($rows['persistent']['cookie_expiration_status'] === 'session' && $rows['persistent']['cookie_expiration'] === null && $rows['persistent']['cookie_category'] === 'marketing', 'Persistent-to-session update failed');
        // Also cover flattened headers while preserving the comma inside Expires.
        $response['headers'] = array();
        $response['cookies'] = array(http_cookie('persistent'), http_cookie('maxage'), http_cookie('session'), http_cookie('invalid'));
        invoke($scanner, 'scan_homepage_cookies');
        $rows = $mode === 'table' ? $wpdb->rows : array_column(invoke($scanner, 'read_json_cookies'), null, 'cookie_name');
        verify($rows['persistent']['cookie_expiration'] === '2027-06-09 10:18:14', 'Flattened Set-Cookie headers parsed incorrectly');
        $response['headers'] = new HeaderValues($raw_headers);
        if ($mode === 'json') {
            invoke($scanner, 'prepare_json_scan');
            invoke($scanner, 'scan_current_cookies');
            invoke($scanner, 'scan_homepage_cookies');
            $rows = array_column(invoke($scanner, 'read_json_cookies'), null, 'cookie_name');
            verify($rows['persistent']['cookie_category'] === 'marketing' && $rows['persistent']['cookie_expiration_status'] === 'persistent', 'New JSON scan lost manual category/expiration');
        }
    }
    storage($scanner, 'table');
    $wpdb = new CookieDatabase();
    invoke($scanner, 'save_cookie', array('persistent'));
    $response['cookies'] = array(http_cookie('persistent'));
    $wpdb->fail_update = true;
    verify(invoke($scanner, 'scan_homepage_cookies') === 0 && strpos($scanner->get_last_scan_error(), 'fixture update failed') !== false, 'DB update failure not reported');
    echo "PASS expiration: Expires/Max-Age/precedence/session/unknown, timestamps, malformed headers, path/domain, existing records/categories, DB failure and JSON file round-trip\n";
} finally {
    foreach (array('scanned-cookies.json', 'index.html', '.htaccess') as $file) {
        $path = $test_directory . '/marrison-cookie/' . $file;
        if (is_file($path)) { unlink($path); }
    }
    if (is_dir($test_directory . '/marrison-cookie')) { rmdir($test_directory . '/marrison-cookie'); }
    if (is_dir($test_directory)) { rmdir($test_directory); }
}
