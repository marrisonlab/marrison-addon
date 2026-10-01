<?php
/** Endpoint cases: real WordPress + rollback, no persistent events or settings. */
ob_start(); require dirname(__DIR__) . '/wp-runtime-probe.php'; ob_end_clean();
$case = $argv[1] ?? 'publish';
$status = in_array($case, ['draft','private','future'], true) ? $case : 'publish';
$id = wp_insert_post(['post_title'=>'Remediation calendar fixture', 'post_type'=>'post', 'post_status'=>$status, 'post_date'=>$status==='future'?'2030-01-01 00:00:00':current_time('mysql'), 'post_password'=>$case==='password'?'fixture-password':'', 'post_excerpt'=>'Disposable test event']);
wp_set_current_user(0);
foreach (['data_ora_inizio'=>1790841600, 'data_ora_fine'=>1790845200, 'custom_start'=>1790928000, 'custom_end'=>1790931600] as $key=>$value) { update_post_meta($id,$key,$value); }
$calendar = null;
foreach ($GLOBALS['wp_filter']['template_redirect']->callbacks as $callbacks) {
    foreach ($callbacks as $callback) {
        if (is_array($callback['function']) && $callback['function'][0] instanceof Marrison_Addon_Calendar_Sync) { $calendar=$callback['function'][0]; break 2; }
    }
}
if (!$calendar) { throw new RuntimeException('Calendar not booted'); }
$_GET = ['marrison_event_ics'=>$id];
if (in_array($case,['custom','tampered','legacy_timezone'],true)) {
    $url = html_entity_decode($calendar->calendar_link_shortcode(['post_id'=>$id,'type'=>'ics','start_meta'=>'custom_start','end_meta'=>'custom_end','location'=>'Roma, Sala; 1']), ENT_QUOTES);
    parse_str(parse_url($url,PHP_URL_QUERY),$_GET);
    if ($case==='tampered') { $_GET['marrison_ics_start']='data_ora_inizio'; }
    if ($case==='legacy_timezone') { add_filter('pre_option_marrison_calendar_sync_settings',function(){ return ['ics_timezone'=>'Invalid/Legacy']; }); }
}
add_filter('wp_die_handler',function(){ return function($message,$title,$args){ throw new RuntimeException('DENIED '.($args['response']??500).': '.wp_strip_all_tags($message)); }; });
file_put_contents(__DIR__.'/calendar-'.$case.'.txt','');
ob_start(function($output) use($case){ file_put_contents(__DIR__.'/calendar-'.$case.'.txt',$output,FILE_APPEND); return $output; });
try { $calendar->download_ics(); } catch (RuntimeException $error) { echo $error->getMessage(); }
