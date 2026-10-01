<?php
$auditOperation = $argv[1] ?? 'snapshot';
$argv = ['wp-runtime-probe.php','inventory'];
ob_start(); require dirname(__DIR__) . '/wp-runtime-probe.php'; ob_end_clean();
$user = get_user_by('login','123');
$file = __DIR__.'/cookie-state-before.json';
$names = [];
foreach(['127.0.0.1','::1',''] as $ip) {
    $names[] = '_transient_marrison_consent_'.md5($ip);
    $names[] = '_transient_timeout_marrison_consent_'.md5($ip);
}
if ($auditOperation === 'snapshot') {
    $state = ['user_id'=>$user->ID, 'user_meta'=>get_user_meta($user->ID,'marrison_cookie_consent',false),'options'=>[]];
    foreach($names as $name) $state['options'][$name]=get_option($name, null);
    file_put_contents($file,wp_json_encode($state,JSON_PRETTY_PRINT));
    echo "Consent state snapshot saved for the test account and localhost only.\n";
} else {
    $state=json_decode(file_get_contents($file),true);
    delete_user_meta($state['user_id'],'marrison_cookie_consent');
    foreach($state['user_meta'] as $value) add_user_meta($state['user_id'],'marrison_cookie_consent',$value);
    foreach($state['options'] as $name=>$value) {
        if (!in_array($name,$names,true)) throw new RuntimeException('Unexpected option');
        if ($value === null) delete_option($name); else update_option($name,$value);
    }
    $wpdb->query('COMMIT'); // Restore the pre-test consent state; remaining harness writes still rollback.
    file_put_contents(__DIR__.'/cookie-state-restored.json',wp_json_encode(['restored'=>true,'user_meta_matches'=>get_user_meta($state['user_id'],'marrison_cookie_consent',false)===$state['user_meta']],JSON_PRETTY_PRINT));
    echo "Pre-test consent state restored.\n";
}
