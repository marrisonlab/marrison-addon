<?php
ob_start(); require __DIR__ . '/cookie-observed-scan-php-test.php'; ob_end_clean();
$wpdb = new ObservedCookieDatabase();
$record = array('cookie_name' => '_ga', 'cookie_domain' => 'example.test', 'cookie_path' => '/', 'cookie_expiration' => null,
    'cookie_expiration_status' => 'unknown', 'observations' => array(array('method' => 'browser')));
verify($scanner->engine_store(array($record), array(), false), 'Observation failed');
$declared = $record + array('source' => 'catalogo', 'observed' => false, 'category' => 'analytics', 'description' => 'Dichiarato dal catalogo');
verify($scanner->engine_store(array($declared), array(), false), 'Catalog merge failed');
verify($scanner->get_cookies('all')[0]->source !== 'catalogo', 'Catalog downgraded observation');
$declared['cookie_name'] = '_ga_*'; $declared['observations'] = array();
verify($scanner->engine_store(array($declared), array(), false), 'Catalog insert failed');
$rows = $scanner->get_cookies('all');
verify(count($rows) === 2, 'Declared record missing from existing inventory');
foreach ($rows as $row) { verify($row->observed === ($row->source !== 'catalogo'), 'Incorrect observed flag'); }
echo "PASS catalog storage: SQL inventory, declared observed=false, true observations preserved\n";

$test_directory = sys_get_temp_dir() . '/marrison-cookie-replacement-' . uniqid();
$upgrade_path = ABSPATH . 'wp-admin/includes/upgrade.php'; wp_mkdir_p(dirname($upgrade_path)); file_put_contents($upgrade_path, '<?php');
try {
    foreach (array('table', 'json') as $mode) {
        $wpdb = new ObservedCookieDatabase(); $wpdb->fail_table = $mode === 'json'; storage($scanner, $mode);
        $old = $record; $old['cookie_name'] = 'previous_scan_only';
        verify($scanner->engine_store(array($old), array(), false), 'Could not seed previous inventory');
        verify($scanner->engine_store(array(), array(), true), 'New scan reset failed: ' . $mode);
        verify(!$scanner->get_cookies('all'), 'Old cookies survived new scan start: ' . $mode);
        $current = $declared; $current['observations'] = array();
        verify($scanner->engine_store(array($current), array(), true), 'Current catalog write failed');
        $rows = $scanner->get_cookies('all');
        verify(count($rows) === 1 && $rows[0]->source === 'catalogo' && !$rows[0]->observed, 'Current declarations inherited old observation: ' . $mode);
        $new = $record; $new['cookie_name'] = 'current_only';
        verify($scanner->engine_store(array($current, $new), array(), true), 'Cumulative partial write failed');
        verify(count($scanner->get_cookies('all')) === 2, 'Current results lost between batches');
        verify($scanner->engine_store(array(), array(), true) && !$scanner->get_cookies('all'), 'Empty scan reused old results');
    }
    echo "PASS fresh scan inventory: SQL/JSON reset at start, no old observations, cumulative partial results and empty scans\n";
} finally {
    foreach (array('scanned-cookies.json', 'index.html', '.htaccess') as $file) {
        $path = $test_directory . '/marrison-cookie/' . $file; if (is_file($path)) { unlink($path); }
    }
    if (is_dir($test_directory . '/marrison-cookie')) { rmdir($test_directory . '/marrison-cookie'); }
    if (is_dir($test_directory)) { rmdir($test_directory); }
    if (is_file($upgrade_path)) { unlink($upgrade_path); }
    foreach (array('wp-admin/includes', 'wp-admin', '') as $folder) { if (is_dir(ABSPATH . $folder)) { rmdir(ABSPATH . $folder); } }
}
