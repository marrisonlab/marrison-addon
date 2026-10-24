<?php
ob_start(); require __DIR__ . '/cookie-consent-store-php-test.php'; ob_end_clean();
function wp_safe_remote_get($url, $args) { verify(strpos($url, '/consent/probe-') !== false && $args['redirection'] === 0, 'Unsafe health probe'); return $GLOBALS['probe_code']; }
function wp_remote_retrieve_response_code($response) { return $response; }
function is_wp_error($value) { return $value === false; }
$test_dir = __DIR__ . '/.cookie-security-' . bin2hex(random_bytes(5));
try {
    $probe_code = 200; verify($store->health_check()['status'] === 'critical', 'Public archive falsely reported protected');
    $probe_code = 403; verify($store->health_check()['status'] === 'good', 'Denied archive not recognized');
    $probe_code = false; verify($store->health_check()['status'] === 'recommended', 'Network failure certified protection');
    $archive = $test_dir . '/marrison-cookie/consent/';
    verify(!glob($archive . 'probe-*') && is_file($archive . 'index.php'), 'Probe leaked or directory index unprotected');
    $name = new ReflectionMethod($store, 'archive_path'); if (PHP_VERSION_ID < 80100) { $name->setAccessible(true); }
    $legacy = $archive . 'receipts-2001-01-01.php'; file_put_contents($legacy, $store::PREFIX . 'legacy');
    $migrated = $name->invoke($store, 'receipts', '2001-01-01');
    verify(!is_file($legacy) && strpos(file_get_contents($migrated), 'legacy') !== false && preg_match('/-[a-f0-9]{24}\.php$/', $migrated), 'Legacy archive data lost or filename guessable');
    verify(Marrison_Addon_Cookie_Request_Limits::allow('fixture', 2, 600) && Marrison_Addon_Cookie_Request_Limits::allow('fixture', 2, 600) && !Marrison_Addon_Cookie_Request_Limits::allow('fixture', 2, 600), 'Rate limit failed');
    $log = $name->invoke($store, 'receipts', gmdate('Y-m-d')); $file = fopen($log, 'wb'); fwrite($file, $store::PREFIX); ftruncate($file, 4194304); fclose($file);
    try { $store->record('reject_all', array()); throw new LogicException('Oversized journal accepted'); }
    catch (RuntimeException $error) { verify(strpos($error->getMessage(), 'giornaliero') !== false, 'Wrong journal failure'); }
    $budget = new ReflectionMethod($store, 'check_budget'); if (PHP_VERSION_ID < 80100) { $budget->setAccessible(true); }
    foreach (array(array(67108864, 0), array(0, 2000)) as $extra) {
        try { $budget->invokeArgs($store, $extra); throw new LogicException('Projected archive cap accepted'); }
        catch (RuntimeException $error) { verify(strpos($error->getMessage(), 'pieno') !== false, 'Wrong projected archive failure'); }
    }
    echo "PASS cookie security: health 200/403/network failure, probe cleanup, private index, lossless legacy filename migration, rate limits and bounded daily journal\n";
} finally {
    foreach (glob($test_dir . '/marrison-cookie/consent/*') ?: array() as $file) { if (is_file($file)) { unlink($file); } }
    foreach (array('.htaccess', 'web.config') as $guard) { if (is_file($test_dir . '/marrison-cookie/consent/' . $guard)) { unlink($test_dir . '/marrison-cookie/consent/' . $guard); } }
    if (is_dir($test_dir . '/marrison-cookie/consent')) { rmdir($test_dir . '/marrison-cookie/consent'); }
    if (is_file($test_dir . '/marrison-cookie/request-limits.php')) { unlink($test_dir . '/marrison-cookie/request-limits.php'); }
    if (is_dir($test_dir . '/marrison-cookie')) { rmdir($test_dir . '/marrison-cookie'); }
    if (is_dir($test_dir)) { rmdir($test_dir); }
}
