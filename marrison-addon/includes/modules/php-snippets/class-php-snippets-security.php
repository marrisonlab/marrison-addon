<?php
if (!defined('ABSPATH')) { exit; }

final class Marrison_Addon_PHP_Snippets_Security {
    const MAX_CODE_BYTES = 262144;

    private static function key() {
        $parts = array();
        // wp_salt() is pluggable and is not available at this module's early bootstrap.
        foreach (array('AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT') as $name) {
            if (defined($name) && is_string(constant($name)) && strlen(constant($name)) >= 16 && strpos(constant($name), 'put your unique phrase here') === false) { $parts[] = $name . '=' . constant($name); }
        }
        if (defined('MARRISON_ADDON_SNIPPETS_KEY')) {
            if (!is_string(MARRISON_ADDON_SNIPPETS_KEY) || strlen(MARRISON_ADDON_SNIPPETS_KEY) < 32) { return ''; }
            $parts[] = 'extra=' . MARRISON_ADDON_SNIPPETS_KEY;
        }
        return $parts ? hash_hmac('sha256', 'marrison-snippets-signature-v1', implode("\0", $parts)) : '';
    }

    public static function signature($row) {
        $key = self::key();
        if ($key === '' || !isset($row['id'], $row['code'], $row['scope'], $row['revision'])) { return ''; }
        return hash_hmac('sha256', (int) $row['id'] . "\0" . $row['scope'] . "\0" . $row['revision'] . "\0" . $row['code'], $key);
    }

    public static function verify($row) {
        $expected = self::signature($row);
        return $expected !== '' && isset($row['signature']) && is_string($row['signature']) && hash_equals($expected, $row['signature']);
    }

    public static function can_edit() {
        // DISALLOW_FILE_EDIT concerns WordPress's file editors, not database snippets.
        return !(defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS);
    }

    public static function safe_mode() {
        return (defined('MARRISON_ADDON_PHP_SNIPPETS_SAFE_MODE') && MARRISON_ADDON_PHP_SNIPPETS_SAFE_MODE)
            || (defined('WP_CONTENT_DIR') && is_file(rtrim(WP_CONTENT_DIR, '/\\') . '/uploads/marrison-snippets-off'));
    }

    public static function recent_authentication($password = '') {
        if (!function_exists('wp_get_session_token') || !class_exists('WP_Session_Tokens') || !function_exists('wp_get_current_user')) { return false; }
        $user = wp_get_current_user(); $token = wp_get_session_token();
        if (!$user->ID || $token === '') { return false; }
        $minutes = defined('MARRISON_ADDON_SNIPPETS_REAUTH_MINUTES') ? max(1, min(60, (int) MARRISON_ADDON_SNIPPETS_REAUTH_MINUTES)) : 15;
        $session = WP_Session_Tokens::get_instance($user->ID)->get($token);
        $login = is_array($session) ? (int) ($session['login'] ?? 0) : 0;
        $key = 'marrison_snippets_reauth_' . hash('sha256', $user->ID . "\0" . $token);
        $recent = (int) get_transient($key);
        if ($session && max($login, $recent) >= time() - $minutes * 60 && max($login, $recent) <= time() + 60) { return true; }
        if ($session && is_string($password) && $password !== '' && wp_check_password($password, $user->user_pass, $user->ID)) {
            set_transient($key, time(), $minutes * 60); return true;
        }
        return false;
    }

    public static function rate_allowed() {
        $key = 'marrison_snippets_rate_' . (int) get_current_user_id();
        $count = (int) get_transient($key);
        if ($count >= 20) { return false; }
        set_transient($key, $count + 1, 60); return true;
    }

    public static function warnings($code) {
        $warnings = array();
        if (!function_exists('token_get_all')) { return array('tokenizer non disponibile'); }
        foreach (token_get_all('<?php ' . $code) as $token) {
            if (is_array($token) && ($token[0] === T_EVAL || ($token[0] === T_STRING && in_array(strtolower($token[1]), array('exec', 'system', 'shell_exec', 'passthru', 'proc_open'), true)))) { $warnings[] = strtolower($token[1]); }
        }
        if ($warnings && stripos($code, 'base64_decode') !== false) { $warnings[] = 'base64_decode con esecuzione'; }
        if (preg_match('/\b(file_put_contents|fopen|copy|rename|unlink)\s*\(/i', $code) && (stripos($code, 'WP_PLUGIN_DIR') !== false || stripos($code, '/plugins/') !== false)) { $warnings[] = 'scrittura nella directory plugin'; }
        return array_values(array_unique($warnings));
    }

    public static function audit($action, $row, $outcome) {
        $lock_key = 'marrison_addon_php_snippets_audit_lock';
        $lock = get_option($lock_key, array());
        if (is_array($lock) && ($lock['expires'] ?? 0) < time()) { delete_option($lock_key); }
        $token = bin2hex(random_bytes(8));
        if (!add_option($lock_key, array('token' => $token, 'expires' => time() + 30), '', false)) { return false; }
        try {
            $log = self::audit_log();
            $log[] = array('user_id' => function_exists('get_current_user_id') ? get_current_user_id() : 0, 'time' => time(),
                'action' => substr($action, 0, 60), 'snippet_id' => (int) ($row['id'] ?? 0), 'code_hash' => hash('sha256', $row['code'] ?? ''), 'outcome' => substr($outcome, 0, 80));
            return update_option('marrison_addon_php_snippets_audit', array_slice($log, -2000), false);
        } finally {
            $held = get_option($lock_key, array());
            if (is_array($held) && ($held['token'] ?? '') === $token) { delete_option($lock_key); }
        }
    }

    public static function audit_log() {
        $days = max(7, min(365, (int) get_option('marrison_addon_php_snippets_log_retention', 90)));
        $log = get_option('marrison_addon_php_snippets_audit', array());
        return is_array($log) ? array_values(array_filter($log, function($entry) use ($days) { return is_array($entry) && ($entry['time'] ?? 0) >= time() - $days * 86400; })) : array();
    }

    public static function schedule_cleanup() {
        if (function_exists('wp_next_scheduled') && !wp_next_scheduled('marrison_snippets_audit_cleanup')) { wp_schedule_event(time() + 86400, 'daily', 'marrison_snippets_audit_cleanup'); }
    }

    public static function cleanup_audit() {
        $lock_key = 'marrison_addon_php_snippets_audit_lock';
        $lock = get_option($lock_key, array());
        if (is_array($lock) && ($lock['expires'] ?? 0) < time()) { delete_option($lock_key); }
        $token = bin2hex(random_bytes(8));
        if (!add_option($lock_key, array('token' => $token, 'expires' => time() + 30), '', false)) { return; }
        try {
            if (get_option('marrison_addon_php_snippets_audit', false) !== false) { update_option('marrison_addon_php_snippets_audit', self::audit_log(), false); }
        } finally {
            $held = get_option($lock_key, array());
            if (is_array($held) && ($held['token'] ?? '') === $token) { delete_option($lock_key); }
        }
    }

    public static function clear_schedule() {
        if (function_exists('wp_clear_scheduled_hook')) { wp_clear_scheduled_hook('marrison_snippets_audit_cleanup'); }
    }
}
