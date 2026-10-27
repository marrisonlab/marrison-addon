<?php
/** Existing WordPress authentication must suffice for database snippets. */
ob_start(); require __DIR__ . '/php-snippets-php-test.php'; ob_end_clean();
$mode = $argv[1] ?? '';
if ($mode === 'file_edit') { define('DISALLOW_FILE_EDIT', true); }
if ($mode === 'file_mods') { define('DISALLOW_FILE_MODS', true); }
$GLOBALS['fixture_login'] = time() - 86400;
$GLOBALS['fixture_transients'] = [];
$blocked = $mode === 'file_mods';
check($blocked !== Marrison_Addon_PHP_Snippets_Security::can_edit(), 'Incorrect interpretation of the WordPress constants.');
$saved = post_action($admin, 'save', 0, ['name' => 'Existing WordPress login', 'code' => 'return;', 'scope' => 'frontend']);
check($blocked ? is_wp_error($saved) : is_int($saved), 'Valid WordPress login could not manage snippets with file editing disabled.');
check(is_wp_error(post_action($admin, 'save', 0, ['_wpnonce' => 'invalid'])), 'Invalid nonce permitted a write.');
$GLOBALS['caps']['manage_options'] = false;
check(is_wp_error(post_action($admin, 'save', 0)), 'Missing management capability permitted a write.');
$GLOBALS['caps']['manage_options'] = true;
$GLOBALS['caps']['unfiltered_html'] = false;
check(is_wp_error(post_action($admin, 'save', 0)), 'Missing unfiltered_html permitted a write.');
$GLOBALS['caps']['unfiltered_html'] = true;
$GLOBALS['fixture_transients'] = [];
for ($i = 0; $i < 20; $i++) { check(Marrison_Addon_PHP_Snippets_Security::rate_allowed(), 'Rate limit too early.'); }
check(!Marrison_Addon_PHP_Snippets_Security::rate_allowed(), 'Rate limit missing.');
echo 'PASS snippet authentication: ' . ($mode ?: 'normal') . "; existing login, no repeated password, capabilities, nonce and rate limit\n";
