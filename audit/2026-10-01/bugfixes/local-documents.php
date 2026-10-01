<?php
ob_start(); require dirname(__DIR__).'/wp-runtime-probe.php'; ob_end_clean();
$docs=$wpdb->get_results("SELECT p.ID,p.post_title,p.post_status,m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE m.meta_key='_elementor_data' AND m.meta_value<>'' AND m.meta_value<>'[]'",ARRAY_A);
$result=[];
foreach($docs as $doc){
 $data=json_decode($doc['meta_value'],true);$found=[];
 $walk=function($nodes,$parents=[])use(&$walk,&$found){foreach((array)$nodes as $node){$s=$node['settings']??[];if(($s['marrison_horizontal_scroll_enabled']??'')==='yes'){$found[]=['id'=>$node['id'],'parents'=>$parents,'settings'=>array_filter($s,function($k){return preg_match('/^(marrison_horizontal|content_width|width|height|min_height|flex_|overflow|position)/',$k);},ARRAY_FILTER_USE_KEY),'children'=>array_map(function($child){return ['id'=>$child['id']??null,'elType'=>$child['elType']??null,'settings'=>array_filter($child['settings']??[],function($k){return preg_match('/^(content_width|width|height|min_height|flex_|overflow|position|_animation)/',$k);},ARRAY_FILTER_USE_KEY)];},$node['elements']??[])];}if(!empty($node['elements']))$walk($node['elements'],array_merge($parents,[$node['id']]));}};
 $walk($data);
 $result[]=['id'=>$doc['ID'],'title'=>$doc['post_title'],'status'=>$doc['post_status'],'url'=>get_permalink($doc['ID']),'horizontal'=>$found];
}
file_put_contents(__DIR__.'/local-documents.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
