<?php
$argv = ['wp-runtime-probe.php', 'isolation', 'calendar_sync'];
ob_start(); require __DIR__ . '/wp-runtime-probe.php'; ob_end_clean();
$id = wp_insert_post(['post_title'=>'Audit alternative keys calendar', 'post_status'=>'draft', 'post_type'=>'post']);
update_post_meta($id, 'audit_start', '2030-01-01 10:00:00');
update_post_meta($id, 'audit_end', '2030-01-01 11:00:00');
$module = (new ReflectionClass('Marrison_Addon_Calendar_Sync'))->newInstanceWithoutConstructor();
$attributes = ['post_id'=>$id, 'start_meta'=>'audit_start', 'end_meta'=>'audit_end', 'location'=>'Roma'];
$google = $module->calendar_link_shortcode($attributes + ['type'=>'google']);
$ics = $module->calendar_link_shortcode($attributes + ['type'=>'ics']);
add_filter('wp_die_handler', function () { return function($message) { throw new RuntimeException(strip_tags($message)); }; });
$_GET['marrison_event_ics'] = (string)$id;
$error = null;
try { $module->download_ics(); } catch (Throwable $e) { $error = $e->getMessage(); }
$data = ['google_url_generated'=>$google !== '', 'google_location_preserved'=>strpos($google, 'location=Roma') !== false,
    'ics_url_generated'=>$ics !== '', 'ics_url'=>$ics, 'ics_download_error'=>$error];
file_put_contents(__DIR__.'/calendar-shortcode-results.json', wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
