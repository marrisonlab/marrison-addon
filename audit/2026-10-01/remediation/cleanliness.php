<?php
ob_start();require dirname(__DIR__).'/wp-runtime-probe.php';ob_end_clean();
$remaining=$wpdb->get_results($wpdb->prepare("SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_title IN (%s,%s,%s)",'Remediation calendar fixture','Remediation Elementor fixture','Audit phase2 image metadata'),ARRAY_A);
file_put_contents(__DIR__.'/record-cleanliness.json',wp_json_encode(['remaining_test_records'=>$remaining,'errors'=>$GLOBALS['audit_errors']],JSON_PRETTY_PRINT));
if($remaining){throw new RuntimeException('Temporary records persisted');}
echo "PASS no temporary WordPress records remain\n";
