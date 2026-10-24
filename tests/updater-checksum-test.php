<?php
define('ABSPATH', __DIR__ . '/'); define('HOUR_IN_SECONDS', 3600); define('MINUTE_IN_SECONDS', 60);
$cache = array(); $remote_body = ''; $package_bytes = 'verified archive bytes'; $downloads = 0;
function add_action() {} function add_filter() {} function plugin_basename($file) { return 'marrison-addon/marrison-addon.php'; }
function get_plugins() { return array('marrison-addon/marrison-addon.php' => array('Version' => '1.0.0')); }
function get_transient($key) { return $GLOBALS['cache'][$key] ?? false; }
function set_transient($key, $value, $ttl) { if ($key === 'marrison_addon_github_version') { if ($ttl !== 21600) { throw new RuntimeException('Six-hour version cache changed'); } } $GLOBALS['cache'][$key] = $value; }
function delete_transient($key) { unset($GLOBALS['cache'][$key]); }
function wp_remote_get($url, $args) { if ($url !== 'https://api.github.com/repos/test/test/releases/latest') { throw new RuntimeException('Unexpected metadata URL'); } return array('tag_name' => 'v2.0.0', 'body' => $GLOBALS['remote_body']); }
function wp_remote_retrieve_response_code($response) { return 200; } function wp_remote_retrieve_body($response) { return json_encode($response); }
function download_url($url, $timeout) { if ($url !== 'https://github.com/test/test/archive/refs/tags/v2.0.0.zip') { throw new RuntimeException('Download URL changed'); } $GLOBALS['downloads']++; $path = __DIR__ . '/.update-test-' . bin2hex(random_bytes(5)); file_put_contents($path, $GLOBALS['package_bytes']); return $path; }
function wp_delete_file($path) { unlink($path); } function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_Error { public function __construct($code, $message) {} }
require __DIR__ . '/../marrison-addon/includes/class-marrison-addon-updater.php';
function checksum_check($value, $message) { if (!$value) { throw new RuntimeException($message); } }
$updater = new Marrison_Addon_Updater('/plugin.php', 'test', 'test');
$update = $updater->check_update((object) array());
checksum_check(empty($update->response) && $downloads === 0 && isset($cache['marrison_addon_checksum_notice']), 'Release without digest offered');
$updater->clean_cache(); $remote_body = 'Marrison-Archive-SHA256: ' . hash('sha256', $package_bytes);
$update = $updater->check_update((object) array());
checksum_check(isset($update->response['marrison-addon/marrison-addon.php']) && $downloads === 1, 'Verified release not offered');
$updater->check_update((object) array()); checksum_check($downloads === 1, 'Verified package downloaded again within cache');
$url = $update->response['marrison-addon/marrison-addon.php']->package;
$file = $updater->verify_download(false, $url, null, array('plugin' => 'marrison-addon/marrison-addon.php'));
checksum_check(is_string($file) && is_file($file), 'Valid install download rejected'); unlink($file);
$package_bytes = 'replaced bytes';
checksum_check(is_wp_error($updater->verify_download(false, $url, null, array('plugin' => 'marrison-addon/marrison-addon.php'))), 'Package changed after offer was accepted');
$updater->clean_cache(); $update = $updater->check_update((object) array()); checksum_check(empty($update->response), 'Checksum mismatch offered update');
checksum_check($updater->verify_download(false, 'https://other.example/plugin.zip', null, array('plugin' => 'other/plugin.php')) === false, 'Unrelated updater intercepted');
checksum_check(Marrison_Addon_Updater::release_checksum('SHA256: abc') === '', 'Malformed digest accepted');
checksum_check(!glob(__DIR__ . '/.update-test-*'), 'Temporary archive leaked');
echo "PASS updater checksum: missing/mismatched digest blocks offer, valid release, unchanged URL/cache, install-time recheck and unrelated plugins preserved\n";
