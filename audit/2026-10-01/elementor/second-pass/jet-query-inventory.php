<?php
ob_start(); require dirname(__DIR__, 2) . '/wp-runtime-probe.php'; ob_end_clean();
global $wpdb;
$rows=$wpdb->get_results("SELECT ID,post_title,post_type FROM {$wpdb->posts} WHERE post_type LIKE '%query%' OR post_type='jet-engine'",ARRAY_A);
echo json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
if(function_exists('jet_engine')){ $je=jet_engine(); echo 'jet_engine='.get_class($je)."\n"; echo 'query_manager='.(isset($je->query_builder)?get_class($je->query_builder):'none')."\n"; }
