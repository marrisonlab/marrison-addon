<?php
ob_start();require dirname(__DIR__).'/wp-runtime-probe.php';ob_end_clean();
require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-direct.php';
$GLOBALS['wp_filesystem']=new WP_Filesystem_Direct(null);
$updater=new Marrison_Addon_Updater(Marrison_Addon::plugin_file(),'marrisonlab','marrison-addon');
$root=__DIR__.'/updater-fixture-'.bin2hex(random_bytes(3));
mkdir($root);
$results=[];
foreach(['github','release','already_correct'] as $layout){
    $remote=$root.'/'.$layout.'/';mkdir($remote);
    $source=$remote.($layout==='already_correct'?'marrison-addon/':'extracted/');mkdir($source);
    $plugin=$layout==='github'?$source.'marrison-addon/':$source;
    if($plugin!==$source){mkdir($plugin);}
    file_put_contents($plugin.'marrison-addon.php',"<?php\n/*\nPlugin Name: Marrison Addon\nVersion: 1.3.43\n*/\n");
    $selected=$updater->fix_folder_name($source,$remote,null,['plugin'=>'marrison-addon/marrison-addon.php']);
    $ok=!is_wp_error($selected)&&file_exists($selected.'marrison-addon.php')&&$selected===$remote.'marrison-addon/';
    $results[$layout]=['ok'=>$ok,'main_at_root'=>$ok,'selected'=>is_wp_error($selected)?$selected->get_error_code():$selected];
    if(!$ok){throw new RuntimeException('Updater failed '.$layout);}
}
file_put_contents(__DIR__.'/updater-runtime-results.json',wp_json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "PASS updater real WP_Filesystem_Direct, Github/nested and release/direct layouts\n";
