<?php
define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', __DIR__ . '/.snippet-safe-' . bin2hex(random_bytes(4)));
$mode = $argv[1] ?? '';
if ($mode === 'file_edit') { define('DISALLOW_FILE_EDIT', true); }
if ($mode === 'file_mods') { define('DISALLOW_FILE_MODS', true); }
$session_token = 'one'; $sessions = true; $transients = array();
function wp_get_session_token() { return $GLOBALS['session_token']; }
function wp_get_current_user() { return (object) array('ID' => 1, 'user_pass' => 'stored-hash'); }
function wp_check_password($password, $hash, $id) { return $password === 'correct' && $hash === 'stored-hash' && $id === 1; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $time) { $GLOBALS['transients'][$key] = $value; }
function get_current_user_id() { return 1; }
class WP_Session_Tokens { public static function get_instance($id) { return new self(); } public function get($token) { return $GLOBALS['sessions'] ? array('login' => time() - 3600) : null; } }
require __DIR__ . '/../marrison-addon/includes/modules/php-snippets/class-php-snippets-security.php';
function auth_check($value, $message) { if (!$value) { throw new RuntimeException($message); } }
if ($mode) {
    auth_check(!Marrison_Addon_PHP_Snippets_Security::can_edit(), 'WordPress edit constant ignored');
    function add_action() {} function current_user_can($cap) { return true; } function wp_unslash($value) { return $value; }
    function absint($value) { return abs((int) $value); } function wp_verify_nonce($value, $action) { return true; } function __($value) { return $value; }
    class WP_Error { public function __construct($code, $message) {} }
    require __DIR__ . '/../marrison-addon/includes/modules/php-snippets/class-php-snippets-store.php';
    require __DIR__ . '/../marrison-addon/includes/modules/php-snippets/class-php-snippets-admin.php';
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = array('operation' => 'save', 'id' => '0', '_wpnonce' => 'valid');
    $admin = new Marrison_Addon_PHP_Snippets_Admin(new Marrison_Addon_PHP_Snippets_Store());
    auth_check($admin->process_action() instanceof WP_Error, 'Write endpoint ignored WordPress constants');
    echo "PASS snippet read-only: $mode\n"; exit;
}
auth_check(!Marrison_Addon_PHP_Snippets_Security::recent_authentication(), 'Old session accepted');
auth_check(!Marrison_Addon_PHP_Snippets_Security::recent_authentication('wrong'), 'Wrong password accepted');
auth_check(Marrison_Addon_PHP_Snippets_Security::recent_authentication('correct'), 'Correct reauthentication rejected');
auth_check(Marrison_Addon_PHP_Snippets_Security::recent_authentication(), 'Same recent session rejected');
$session_token = 'two'; auth_check(!Marrison_Addon_PHP_Snippets_Security::recent_authentication(), 'Reauthentication leaked to another session');
$sessions = false; auth_check(!Marrison_Addon_PHP_Snippets_Security::recent_authentication('correct'), 'Absent session reauthenticated');
for ($i = 0; $i < 20; $i++) { auth_check(Marrison_Addon_PHP_Snippets_Security::rate_allowed(), 'Rate limit too early'); }
auth_check(!Marrison_Addon_PHP_Snippets_Security::rate_allowed(), 'Rate limit missing');
try {
    mkdir(WP_CONTENT_DIR . '/uploads', 0777, true); touch(WP_CONTENT_DIR . '/uploads/marrison-snippets-off');
    auth_check(Marrison_Addon_PHP_Snippets_Security::safe_mode(), 'Emergency flag ignored');
} finally { unlink(WP_CONTENT_DIR . '/uploads/marrison-snippets-off'); rmdir(WP_CONTENT_DIR . '/uploads'); rmdir(WP_CONTENT_DIR); }
foreach (array('file_edit', 'file_mods') as $case) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($case), $status); auth_check($status === 0, 'Read-only child failed');
}
echo "PASS snippet reauthentication: old/wrong/correct password, session binding, missing session, save rate and emergency file\n";
