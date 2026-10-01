<?php
// Cookie Manager second-pass probe. The shared harness blocks network and rolls DB back.
$argv = ['wp-runtime-probe.php', 'isolation', 'cookie_manager'];
require dirname(dirname(__DIR__)) . '/wp-runtime-probe.php';

$out = ['checks' => [], 'errors' => $GLOBALS['audit_errors'] ?? []];
$check = static function ($name, $ok, $detail = null) use (&$out) {
    $out['checks'][] = ['name' => $name, 'ok' => (bool) $ok, 'detail' => $detail];
};

$scanner = Marrison_Cookie_Scanner::get_instance();
$count = $scanner->perform_scan();
$cookies = $scanner->get_cookies('all');
$check('scanner completes with blocked HTTP and table storage', is_int($count) && $scanner->get_storage_info()['type'] === 'table', ['saved' => $count, 'storage' => $scanner->get_storage_info(), 'error' => $scanner->get_last_scan_error(), 'count' => count($cookies)]);
$check('scanner retains current WordPress cookie patterns', count(array_filter($cookies, static function ($c) { return isset($c->source) && 'wordpress' === $c->source; })) > 0, count($cookies));
$check('scanner reports blocked homepage request', false !== strpos($scanner->get_last_scan_error(), 'Richiesta home fallita') || '' === $scanner->get_last_scan_error(), $scanner->get_last_scan_error());

$consent = Marrison_Cookie_Consent::get_instance();
$blocked_input = '<script src="https://www.google-analytics.com/ga.js" nonce="abc">gtag("x")</script><iframe src="https://www.youtube.com/embed/x"></iframe>';
unset($_COOKIE['marrison_cookie_consent'], $_COOKIE['marrison_cookie_categories']);
$blocked = $consent->filter_output($blocked_input);
$check('no consent blocks analytics and marketing', substr_count($blocked, 'data-marrison-cookie-blocked=') === 2 && false !== strpos($blocked, 'data-marrison-blocked-src'), $blocked);
$_COOKIE['marrison_cookie_consent'] = 'accept_all';
$accepted = $consent->filter_output($blocked_input);
$check('accept all preserves original tags', $accepted === $blocked_input, $accepted);
$_COOKIE['marrison_cookie_consent'] = 'custom';
$_COOKIE['marrison_cookie_categories'] = 'necessary|analytics';
$custom = $consent->filter_output($blocked_input);
$check('custom analytics allows script but blocks marketing iframe', false === strpos($custom, 'data-marrison-cookie-blocked="script"') && false !== strpos($custom, 'data-marrison-cookie-blocked="iframe"'), $custom);
$ref = new ReflectionClass($consent);
$iframe_filter = $ref->getMethod('filter_iframe_tag');
$iframe_filter->setAccessible(true);
$unquoted = $iframe_filter->invoke($consent, ['', ' src=https://www.youtube.com/embed/test']);
$quoted = $iframe_filter->invoke($consent, ['', ' src="https://www.youtube.com/embed/test"']);
$check('unquoted marketing iframe has src removed', false === strpos($unquoted, ' src=https://www.youtube.com/embed/test'), $unquoted);
$check('quoted marketing iframe has src removed', false === strpos($quoted, ' src="https://www.youtube.com/embed/test"'), $quoted);

$check('frontend nonce refresh is public action', has_action('wp_ajax_nopriv_marrison_cookie_refresh_nonce') !== false);
$check('frontend save consent is public action', has_action('wp_ajax_nopriv_marrison_save_consent') !== false);
$check('scanner write actions are admin-only', has_action('wp_ajax_nopriv_marrison_scan_cookies') === false && has_action('wp_ajax_nopriv_marrison_delete_cookie') === false);

echo "COOKIE_SECOND_PASS_JSON\n" . json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
