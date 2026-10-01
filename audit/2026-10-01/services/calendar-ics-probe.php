<?php
// The harness starts a DB transaction and rolls it back in the shutdown handler.
$argv = ['wp-runtime-probe.php', 'isolation', 'calendar_sync'];
require dirname(__DIR__) . '/wp-runtime-probe.php';

$post_id = wp_insert_post([
    'post_title' => 'Audit draft calendar event',
    'post_content' => 'private audit fixture',
    'post_status' => 'draft',
    'post_type' => 'post',
]);
update_post_meta($post_id, 'data_ora_inizio', '2030-01-01 10:00:00');
update_post_meta($post_id, 'data_ora_fine', '2030-01-01 11:00:00');
$_GET['marrison_event_ics'] = (string) $post_id;
ob_start();
(new Marrison_Addon_Calendar_Sync())->download_ics();
