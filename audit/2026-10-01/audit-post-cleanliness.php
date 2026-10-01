<?php
ob_start(); require __DIR__ . '/wp-runtime-probe.php'; ob_end_clean();
global $wpdb;
$posts = $wpdb->get_results("SELECT ID,post_title,post_type,post_status,post_parent FROM {$wpdb->posts} WHERE post_title IN ('Audit temporary variable','Audit draft calendar event','Audit alternative keys calendar','Audit phase2 image metadata','Audit phase2 Elementor document','Audit horizontal regression') OR post_title LIKE 'Audit horizontal variant %'", ARRAY_A);
echo wp_json_encode(['remaining_test_records'=>$posts], JSON_PRETTY_PRINT)."\n";
