<?php
/** Real private files, signatures, expiry, revisions, journal paging and retention. */
ob_start();
define('DAY_IN_SECONDS', 86400);
define('COOKIE_DOMAIN', '');
$options = array(); $cookie_salt = 'test-auth-key'; $can_manage = true;
$test_dir = __DIR__ . '/.consent-store-' . bin2hex(random_bytes(4));
function add_action() {}
function get_option($name, $default = false) { global $options; return $options[$name] ?? $default; }
function add_option($name, $value) { global $options; if (isset($options[$name])) { return false; } $options[$name] = $value; return true; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_salt() { global $cookie_salt; return $cookie_salt; }
function home_url() { return 'https://example.test'; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_unslash($value) { return $value; }
function wp_upload_dir() { global $test_dir; return array('basedir' => $test_dir, 'baseurl' => 'https://example.test/uploads'); }
function wp_mkdir_p($path) { return is_dir($path) || mkdir($path, 0777, true); }
function is_ssl() { return true; }
function check_ajax_referer($action, $key) { if (($_POST[$key] ?? '') !== 'valid') { throw new LogicException('Invalid nonce'); } }
function current_user_can() { global $can_manage; return $can_manage; }
function nocache_headers() {}
class ReceiptResponse extends Exception { public $success, $data; public function __construct($success, $data) { $this->success = $success; $this->data = $data; } }
function wp_send_json_success($data) { throw new ReceiptResponse(true, $data); }
function wp_send_json_error($data, $status = 200) { throw new ReceiptResponse(false, $data); }
function verify($value, $message) { if (!$value) { throw new RuntimeException($message); } }
function invoke_ajax($store, $method, $data = array()) {
    $_POST = array_merge(array('nonce' => 'valid'), $data);
    try { $store->$method(); } catch (ReceiptResponse $response) { return $response; }
    throw new RuntimeException('Missing response');
}
require __DIR__ . '/cookie-test-support.php';
$store = Marrison_Addon_Cookie_Consent_Store::get_instance();
$_SERVER['HTTP_ORIGIN'] = 'https://example.test';
try {
    $options['marrison_cookie_consent_duration'] = 30;
    verify($store::duration() === 180, 'Legacy consent duration was not raised');
    $first = $store->record('accept_all', array());
    verify($store->current()['receipt_id'] === $first['receipt_id'], 'Signed receipt is not readable');
    $encoded = $_COOKIE[$store::COOKIE];
    verify($store->decode(substr($encoded, 0, -1) . ($encoded[strlen($encoded) - 1] === 'a' ? 'b' : 'a')) === null, 'Tampered receipt was accepted');
    foreach (array('receipt_id', 'visitor_id', 'revision', 'consent_version') as $key) {
        $malformed = $first; $malformed[$key] = array('unexpected');
        verify($store->decode($store->encode($malformed)) === null, 'Malformed signed state was accepted');
    }
    $expired = $first; $expired['expires'] = time() - 1;
    verify($store->decode($store->encode($expired)) === null, 'Expired choice was accepted');
    $options['marrison_cookie_privacy_policy_content'] = '<p>Correzione di impaginazione</p>';
    verify($store->current() !== null, 'A text/layout correction solicited a new choice');
    $options['marrison_cookie_analytics_sources'] = '/new-service/';
    verify($store->current() === null, 'New service rules did not require a choice');
    $options['marrison_cookie_analytics_sources'] = '';
    $options['marrison_cookie_policy_revision'] = 'new';
    verify($store->current() === null, 'Old policy revision still authorizes tracking');
    $withdrawn = $store->record('reject_all', array());
    verify($withdrawn['visitor_id'] === $first['visitor_id'], 'Revision update lost the receipt chain');
    $_COOKIE = array();
    $other = $store->record('reject_all', array());
    verify($other['visitor_id'] !== $first['visitor_id'], 'Independent visitors were merged');
    $archive = $test_dir . '/marrison-cookie/consent/';
    $log = glob($archive . 'receipts-' . gmdate('Y-m-d') . '-*.php')[0];
    $body = file_get_contents($log);
    verify(strpos($body, $store::PREFIX) === 0 && strpos($body, 'ip_address') === false && strpos($body, 'user_agent') === false, 'Journal privacy/protection failed');
    $response = invoke_ajax($store, 'ajax_receipts');
    verify($response->success && count($response->data['receipts']) === 3 && $response->data['receipts'][1]['event'] === 'withdrawal', 'Withdrawal evidence missing');
    verify(!in_array(false, $response->data['snapshot_integrity'], true), 'Policy snapshots fail integrity');
    $cookie_salt = 'rotated';
    verify($store->current() === null, 'Rotated authentication key did not invalidate browser state');
    verify(invoke_ajax($store, 'ajax_receipts')->data['receipts'][0]['integrity_valid'], 'Authentication-key rotation broke historical proof');
    $tampered = str_replace('"type":"accept_all"', '"type":"reject_all"', $body);
    file_put_contents($log, $tampered);
    verify(!invoke_ajax($store, 'ajax_receipts')->data['receipts'][0]['integrity_valid'], 'Altered journal record was not detected');
    file_put_contents($log, $body);
    $policy = glob($archive . 'policy-' . $first['revision'] . '-*.php')[0];
    $original = file_get_contents($policy);
    file_put_contents($policy, $store::PREFIX . '{}');
    verify(invoke_ajax($store, 'ajax_receipts')->data['snapshot_integrity'][$first['revision']] === false, 'Altered policy snapshot was not detected');
    file_put_contents($policy, $original);
    $can_manage = false;
    verify(!invoke_ajax($store, 'ajax_receipts')->success, 'Anonymous receipt export accepted');
    $can_manage = true;
    verify(!invoke_ajax($store, 'ajax_receipts', array('date' => '../private'))->success, 'Archive traversal accepted');
    $_SERVER['HTTP_ORIGIN'] = 'https://outside.test';
    verify(!invoke_ajax($store, 'ajax_state')->success, 'Cross-site consent state request accepted');
    $_SERVER['HTTP_ORIGIN'] = 'https://example.test';
    $_COOKIE = array('marrison_cookie_consent' => 'reject_all');
    verify(invoke_ajax($store, 'ajax_state')->data['state']['valid'], 'Legacy refusal was not preserved');
    $after = invoke_ajax($store, 'ajax_receipts')->data['receipts'];
    verify(end($after)['event'] === 'legacy_refusal', 'Migration fabricated an affirmative consent event');
    file_put_contents($archive . 'receipts-2000-01-01.php', $store::PREFIX);
    $store->cleanup();
    verify(!file_exists($archive . 'receipts-2000-01-01.php') && file_exists($log), 'Retention cleanup removed current history or kept expired history');
    $before_scan_log = file_get_contents($log);
    $scan_choice = $store->record('accept_all', array(), 'scan');
    verify($store->current()['type'] === 'accept_all', 'Scan did not use the native signed consent cookies');
    verify(file_get_contents($log) === $before_scan_log, 'A scan fabricated a visitor consent receipt');
    verify(isset($_COOKIE['marrison_cookie_state'], $_COOKIE['marrison_cookie_consent'], $_COOKIE['marrison_cookie_categories']), 'Native scan consent cookies missing');
    // Some Unix hosts forbid IIS configuration files while allowing PHP receipts.
    mkdir($archive . 'web.config');
    $_SERVER['SERVER_SOFTWARE'] = 'LiteSpeed';
    $linux_choice = $store->record('reject_all', array());
    verify($store->current()['receipt_id'] === $linux_choice['receipt_id'], 'An unused IIS guard prevented saving on a Unix server');
    $_SERVER['SERVER_SOFTWARE'] = 'Microsoft-IIS/10.0';
    $before_failure = file_get_contents($log);
    $_POST = array('nonce' => 'valid', 'revision' => Marrison_Addon_Cookie_Policy_Content::revision());
    try { $store->save_request('accept_all', array()); throw new LogicException('Missing storage error'); }
    catch (ReceiptResponse $result) {
        verify(!$result->success && $result->data['code'] === 'consent_storage_error' && strpos($result->data['message'], 'web.config') === false, 'Public save leaked a storage diagnostic or reported success');
    }
    verify(file_get_contents($log) === $before_failure && $store->current()['receipt_id'] === $linux_choice['receipt_id'], 'Failed protection changed the receipt or consent');
    $diagnostic = invoke_ajax($store, 'ajax_receipts');
    verify(!$diagnostic->success && strpos($diagnostic->data['message'], 'web.config') !== false, 'Admin receipt view lost the actionable storage diagnostic');
    rmdir($archive . 'web.config');
    $iis_choice = $store->record('reject_all', array());
    verify(is_file($archive . 'web.config') && strpos(file_get_contents($archive . 'web.config'), '<deny users="*"') !== false, 'IIS did not install its access protection');
    verify($store->current()['receipt_id'] === $iis_choice['receipt_id'], 'IIS choice did not persist after restoring protection');
    unset($_SERVER['SERVER_SOFTWARE']);
    echo "PASS consent store: signed/expired/tampered states, revisions, withdrawals, integrity, authorization, retention; isolated scan sets all three real cookies without visitor receipts or value leaks\n";
} finally {
    if (is_dir($test_dir . '/marrison-cookie/consent/web.config')) { rmdir($test_dir . '/marrison-cookie/consent/web.config'); }
    foreach (glob($test_dir . '/marrison-cookie/consent/*') ?: array() as $file) { unlink($file); }
    foreach (array('.htaccess', 'web.config') as $file) { if (is_file($test_dir . '/marrison-cookie/consent/' . $file)) { unlink($test_dir . '/marrison-cookie/consent/' . $file); } }
    if (is_file($test_dir . '/marrison-cookie/request-limits.php')) { unlink($test_dir . '/marrison-cookie/request-limits.php'); }
    if (is_dir($test_dir . '/marrison-cookie/consent')) { rmdir($test_dir . '/marrison-cookie/consent'); rmdir($test_dir . '/marrison-cookie'); rmdir($test_dir); }
    ob_end_flush();
}
