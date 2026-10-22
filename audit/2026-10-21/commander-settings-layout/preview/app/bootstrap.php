<?php
// Local layout preview only: no credentials, provider calls or database writes.
namespace App\Models {
    class Settings {
        public static function get($key, $default = '') {
            return array('cookie_browserless_enabled' => '1', 'cookie_browserless_monthly_units' => '900')[$key] ?? $default;
        }
    }
}
namespace App\Services {
    class CookieBrowserlessService { public static function token() { return 'configured-preview'; } }
    class MaintenanceSecretVault {}
}
namespace {
    function require_admin_login() {}
    function csrf_field() { return '<input type="hidden" name="csrf_token" value="local-preview">'; }
    function e($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
