<?php
namespace App\Models {
    class Settings {
        public static $values = ['cookie_browserless_enabled' => '1', 'cookie_browserless_token' => 'encrypted-fixture', 'cookie_browserless_monthly_units' => '4'];
        public static function get($key, $default = null) { return self::$values[$key] ?? $default; }
    }
}
namespace App\Services {
    class MaintenanceSecretVault { public static function decrypt($value): string { return $value === 'encrypted-fixture' ? 'private-fixture-token' : ''; } }
    function dns_get_record($host, $type) { return [['ip' => $host === 'private.example' ? '127.0.0.1' : '93.184.216.34']]; }
    function curl_init($url) { return (object) ['url' => $url, 'options' => []]; }
    function curl_setopt_array($handle, $options) { $handle->options = array_replace($handle->options, $options); return true; }
    function curl_exec($handle) {
        $GLOBALS['requests'][] = $handle;
        if (strpos($handle->url, 'rest_route=') !== false) {
            parse_str(parse_url($handle->url, PHP_URL_QUERY), $query);
            $body = ['proof_hash' => empty($GLOBALS['bad_proof']) ? hash('sha256', $query['proof'] . "\n" . $query['url']) : 'invalid'];
        } else {
            $input = json_decode($handle->options[CURLOPT_POSTFIELDS], true);
            $GLOBALS['provider_calls']++;
            $body = ['data' => ['protocol' => 1, 'engine' => 'commander_browserless', 'url' => $input['context']['url'], 'consent_verified' => true,
                'cookies' => [['cookie_name' => 'YSC', 'cookie_domain' => 'youtube.com', 'cookie_path' => '/', 'phase' => 'after', 'value' => 'PRIVATE_COOKIE']], 'resources' => [], 'issues' => []]];
        }
        $raw = json_encode($body);
        return $handle->options[CURLOPT_WRITEFUNCTION]($handle, $raw) === strlen($raw);
    }
    function curl_getinfo($handle, $option) { return 200; }
    function curl_close($handle) {}
}
namespace {
    if (!function_exists('curl_init')) { function curl_init() {} }
    foreach (['CURLOPT_RETURNTRANSFER','CURLOPT_FOLLOWLOCATION','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT','CURLOPT_PROTOCOLS','CURLPROTO_HTTPS','CURLOPT_SSL_VERIFYPEER','CURLOPT_SSL_VERIFYHOST','CURLOPT_RESOLVE','CURLOPT_USERAGENT','CURLOPT_WRITEFUNCTION','CURLOPT_POST','CURLOPT_POSTFIELDS','CURLOPT_HTTPHEADER','CURLINFO_RESPONSE_CODE'] as $index => $constant) {
        if (!defined($constant)) define($constant, 10000 + $index);
    }
    function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
    function fails($callback, $code) { try { $callback(); } catch (Throwable $error) { check($error->getCode() === $code, 'Wrong error: ' . $error->getMessage()); return; } throw new RuntimeException('Expected failure did not occur'); }
    define('COMMANDER_ROOT', __DIR__ . '/.cookie-test-' . bin2hex(random_bytes(6)));
    mkdir(COMMANDER_ROOT . '/storage', 0700, true);
    require dirname(__DIR__) . '/app/Services/CookieBrowserlessService.php';
    $provider_calls = 0; $requests = [];
    $input = ['site_url' => 'https://public.example/', 'url' => 'https://public.example/page/', 'job' => str_repeat('a', 32), 'proof' => str_repeat('b', 64)];
    $_SERVER['REMOTE_ADDR'] = '203.0.113.20';
    try {
        foreach (['127.0.0.1','10.0.0.1','192.168.1.1','169.254.169.254','100.64.0.1','198.18.0.1','::1','fc00::1','fe80::1','::ffff:127.0.0.1'] as $ip) check(!\App\Services\CookieBrowserlessService::publicIp($ip), 'Private/reserved IP accepted: ' . $ip);
        check(\App\Services\CookieBrowserlessService::publicIp('93.184.216.34'), 'Public IP rejected');
        foreach (['http://public.example/','https://user:pass@public.example/','https://public.example:8443/','https://localhost/','https://private.example/'] as $url) fails(fn() => \App\Services\CookieBrowserlessService::publicUrl($url), 400);
        $bad_proof = true;
        fails(fn() => \App\Services\CookieBrowserlessService::scan($input), 403);
        check($provider_calls === 0, 'Unproven site spent provider quota');
        $bad_proof = false;
        $first = \App\Services\CookieBrowserlessService::scan($input);
        check($provider_calls === 1 && !isset($first['cookies'][0]['value']) && strlen($first['request_id']) === 64, 'Invalid provider result or value leak');
        $repeat = \App\Services\CookieBrowserlessService::scan($input);
        check($provider_calls === 1 && $repeat === $first, 'Retried request billed twice');
        $input['url'] = 'https://public.example/second/';
        \App\Services\CookieBrowserlessService::scan($input);
        check($provider_calls === 2, 'Second page did not run');
        $input['url'] = 'https://public.example/third/';
        fails(fn() => \App\Services\CookieBrowserlessService::scan($input), 429);
        check($provider_calls === 2, 'Exceeded monthly hard reservation cap');
        $input['url'] = 'https://another.example/page/';
        fails(fn() => \App\Services\CookieBrowserlessService::scan($input), 400);
        foreach ($requests as $request) {
            check($request->options[CURLOPT_FOLLOWLOCATION] === false && $request->options[CURLOPT_SSL_VERIFYPEER] === true && isset($request->options[CURLOPT_RESOLVE]), 'HTTP redirect/TLS/DNS protections missing');
        }
        foreach (glob(COMMANDER_ROOT . '/storage/cookie-scanner/*.php') as $path) {
            $raw = file_get_contents($path);
            check(strpos($raw, '<?php exit; ?>') === 0 && strpos($raw, 'PRIVATE_COOKIE') === false && strpos($raw, 'private-fixture-token') === false, 'Stored state exposed cookie/token');
        }
        \App\Models\Settings::$values['cookie_browserless_enabled'] = '0';
        fails(fn() => \App\Services\CookieBrowserlessService::scan($input), 503);
        echo "PASS Commander scanner: private-IP and URL guards, ownership proof, DNS-pinned TLS, no redirects, idempotent retry, quota reservation, encrypted-token boundary, no cookie values, disabled service.\n";
    } finally {
        foreach (glob(COMMANDER_ROOT . '/storage/cookie-scanner/*') ?: [] as $path) unlink($path);
        if (is_dir(COMMANDER_ROOT . '/storage/cookie-scanner')) rmdir(COMMANDER_ROOT . '/storage/cookie-scanner');
        rmdir(COMMANDER_ROOT . '/storage'); rmdir(COMMANDER_ROOT);
    }
}
