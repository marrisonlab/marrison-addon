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
