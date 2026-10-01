<?php
ob_start(); require dirname(__DIR__).'/wp-runtime-probe.php'; ob_end_clean();
$id=wp_insert_post(['post_title'=>'Audit horizontal regression','post_type'=>'page','post_status'=>'draft']);
$nodes=[];
foreach(['full','boxed'] as $layout){
 foreach([false,true] as $snap){
  $case=$layout.($snap?'-snap':'-continuous');$slides=[];
  for($n=1;$n<=3;$n++)$slides[]=['id'=>'slide-'.$case.'-'.$n,'elType'=>'container','settings'=>['content_width'=>'full','width'=>['unit'=>'%','size'=>100],'min_height'=>['unit'=>'px','size'=>340],'background_background'=>'classic','background_color'=>['#481d6f','#145468','#a13827'][$n-1]],'elements'=>[['id'=>'heading-'.$case.'-'.$n,'elType'=>'widget','widgetType'=>'heading','settings'=>['title'=>$case.' slide '.$n],'elements'=>[]]]];
  $nodes[]=['id'=>'before-'.$case,'elType'=>'container','settings'=>['min_height'=>['unit'=>'px','size'=>120]],'elements'=>[['id'=>'label-'.$case,'elType'=>'widget','widgetType'=>'heading','settings'=>['title'=>'Case '.$case],'elements'=>[]]]];
  $nodes[]=['id'=>'host-'.$case,'elType'=>'container','settings'=>['content_width'=>$layout,'min_height'=>['unit'=>'px','size'=>360],'marrison_horizontal_scroll_enabled'=>'yes','marrison_horizontal_scroll_pin'=>'yes','marrison_horizontal_scroll_snap'=>$snap?'yes':'','marrison_horizontal_scroll_speed'=>1],'elements'=>$slides];
  $nodes[]=['id'=>'after-'.$case,'elType'=>'container','settings'=>['min_height'=>['unit'=>'px','size'=>300],'background_background'=>'classic','background_color'=>'#dddddd'],'elements'=>[['id'=>'following-'.$case,'elType'=>'widget','widgetType'=>'heading','settings'=>['title'=>'Following '.$case],'elements'=>[]]]];
 }
}
update_post_meta($id,'_elementor_edit_mode','builder');update_post_meta($id,'_elementor_template_type','wp-page');update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($nodes)));
$css=\Elementor\Core\Files\CSS\Post::create($id)->get_content();
$doc=\Elementor\Plugin::$instance->documents->get($id);
ob_start();$doc->print_elements_with_wrapper($doc->get_elements_data());$html=ob_get_clean();
$html='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Horizontal real CSS regression</title><link rel="stylesheet" href="/wp-content/plugins/elementor/assets/css/frontend.min.css"><link rel="stylesheet" href="/wp-content/plugins/marrison-addon/assets/css/horizontal-scroll.css"><style>body{margin:0;font-family:Arial}</style><style>'.$css.'</style></head><body>'.$html.'<script src="/wp-content/plugins/marrison-addon/assets/js/horizontal-scroll.js"></script></body></html>';
file_put_contents(__DIR__.'/horizontal-real.html',$html);file_put_contents(__DIR__.'/horizontal-real.css',$css);echo 'Generated document '.$id."\n";
