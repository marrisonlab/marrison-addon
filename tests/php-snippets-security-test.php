<?php
/** Security contracts in isolated processes, using the real store and runtime. */
ob_start(); require __DIR__ . '/php-snippets-php-test.php'; ob_end_clean();
$row = snippet_row(1, '$GLOBALS["trace"][]="signed";');
check(Marrison_Addon_PHP_Snippets_Security::verify($row), 'Valid signature rejected');
foreach (array('code' => 'marrison_missing_function();', 'scope' => 'backend', 'revision' => 'changed', 'id' => 99, 'signature' => str_repeat('0', 64)) as $field => $value) {
    $altered = $row; $altered[$field] = $value;
    check(!Marrison_Addon_PHP_Snippets_Security::verify($altered), 'Tampering accepted: ' . $field);
}
$unsigned = $row; unset($unsigned['signature']);
foreach (array($unsigned, array_merge($row, array('code' => '$GLOBALS["trace"][]="injected";'))) as $unsafe) {
    $result = run_child('retry', array(1 => $unsafe));
    check($result['status'] === 0 && !$result['stored'][1]['active'] && $result['out']['trace'] === array(), 'Unsigned/tampered PHP executed or was not quarantined');
}
$anonymous = run_child('anonymous-page', array(1 => $row));
check($anonymous['status'] === 0 && $anonymous['out']['trace'] === array('signed'), 'Anonymous AJAX page parameter skipped protection snippets after init');
$wpdb->rows = array(1 => $unsigned); $GLOBALS['fixture_transients'] = array();
$signed = post_action($admin, 'sign_reactivate', 1, array('revision' => $unsigned['revision']));
check($signed === 1 && $wpdb->rows[1]['active'] && Marrison_Addon_PHP_Snippets_Security::verify($wpdb->rows[1]), 'Explicit reviewed migration failed');
check(is_wp_error(post_action($admin, 'save', 0, array('name' => 'Large', 'code' => str_repeat(' ', 262145), 'scope' => 'everywhere'))), 'Oversized code accepted');
check(Marrison_Addon_PHP_Snippets_Security::warnings('eval(base64_decode("x"));') === array('eval', 'base64_decode con esecuzione'), 'Danger warning missing');
check(is_wp_error(post_action($admin, 'activate', 1, array('revision' => $wpdb->rows[1]['revision'], '_wpnonce' => 'invalid'))), 'Migration/activation accepted invalid nonce');
$audit = Marrison_Addon_PHP_Snippets_Security::audit_log();
check($audit && !isset($audit[0]['code']) && strlen($audit[0]['code_hash']) === 64, 'Audit stores code or lacks hash');

// Simulate the real entry script rather than letting a page query masquerade as admin.php.
$GLOBALS['admin'] = true; $GLOBALS['did_hooks']['init'] = 1;
$_SERVER['SCRIPT_NAME'] = '/wp-admin/admin-ajax.php'; $_GET['page'] = 'marrison_addon_panel'; $_POST = array();
check(!Marrison_Addon_PHP_Snippets_Runtime::bypass_request(), 'Anonymous-shaped page parameter bypassed AJAX');
$_POST = array('action' => 'marrison_php_snippets');
check(!Marrison_Addon_PHP_Snippets_Runtime::bypass_request(), 'Unverified snippets action bypassed AJAX');
$_POST = array('action' => 'marrison_save_option', 'nonce' => 'invalid');
check(!Marrison_Addon_PHP_Snippets_Runtime::bypass_request(), 'Invalid admin AJAX nonce bypassed execution');
$_POST['nonce'] = 'valid-marrison_save_option_nonce';
$GLOBALS['fixture_logged_in'] = false;
check(!Marrison_Addon_PHP_Snippets_Runtime::bypass_request(), 'Unauthenticated AJAX bypassed even with a supplied valid nonce');
$GLOBALS['fixture_logged_in'] = true;
check(Marrison_Addon_PHP_Snippets_Runtime::bypass_request(), 'Authenticated management recovery lost its valid nonce path');
$_SERVER['SCRIPT_NAME'] = '/wp-admin/admin-post.php'; $_POST = array();
check(!Marrison_Addon_PHP_Snippets_Runtime::bypass_request(), 'Page parameter bypassed admin-post');
$_SERVER['SCRIPT_NAME'] = '/wp-admin/admin.php';
check(Marrison_Addon_PHP_Snippets_Runtime::bypass_request(), 'Real recovery page lost bypass');
echo "PASS snippet security: HMAC all bound fields, unsigned/tampered runtime quarantine, explicit sign/reactivate, size, warnings, audit metadata, real admin/AJAX/admin-post bypass boundaries\n";
