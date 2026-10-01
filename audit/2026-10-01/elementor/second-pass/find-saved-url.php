<?php
ob_start(); require dirname(__DIR__, 2) . '/wp-runtime-probe.php'; ob_end_clean();
$rows = $GLOBALS['wpdb']->get_col("SELECT meta_value FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key = '_elementor_data'");
$found=[];
$walk=function($items) use (&$walk,&$found){foreach((array)$items as $item){$s=$item['settings']??[];if(isset($s['marrison_addon_url']))$found[]=$s['marrison_addon_url'];if(!empty($item['elements']))$walk($item['elements']);}};
foreach($rows as $raw){$data=json_decode($raw,true);if(is_array($data))$walk($data);}
echo json_encode($found,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
