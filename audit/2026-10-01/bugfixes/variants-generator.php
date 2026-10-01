<?php
// Generates isolated Elementor documents for Horizontal Scroll layout variants.
// WP harness wraps writes in a transaction and rolls them back at shutdown.
ob_start(); require dirname(__DIR__) . '/wp-runtime-probe.php'; ob_end_clean();

function variant_container($id, $title, array $settings = [], array $children = []) {
    return ['id'=>$id,'elType'=>'container','isInner'=>false,'settings'=>array_merge([
        'content_width'=>'full','min_height'=>['unit'=>'px','size'=>360],
    ], $settings),'elements'=>$children];
}
function variant_slide($id, $title, $color, $width = null) {
    $settings=['content_width'=>'full','min_height'=>['unit'=>'px','size'=>340],'background_background'=>'classic','background_color'=>$color];
    if (null !== $width) $settings['width']=['unit'=>'%','size'=>$width];
    return variant_container($id,$title,$settings,[['id'=>$id.'-heading','elType'=>'widget','widgetType'=>'heading','isInner'=>true,'settings'=>['title'=>$title],'elements'=>[]]]);
}
function variant_nodes($name, array $host_settings, array $slides) {
    return [
        variant_container('before-'.$name,'Before '.$name,['min_height'=>['unit'=>'px','size'=>120]],[['id'=>'before-'.$name.'-heading','elType'=>'widget','widgetType'=>'heading','isInner'=>true,'settings'=>['title'=>'Before '.$name],'elements'=>[]]]),
        variant_container('host-'.$name,'Host '.$name,array_merge(['marrison_horizontal_scroll_enabled'=>'yes','marrison_horizontal_scroll_speed'=>1],$host_settings),$slides),
        variant_container('after-'.$name,'After '.$name,['min_height'=>['unit'=>'px','size'=>300],'background_background'=>'classic','background_color'=>'#dddddd'],[['id'=>'after-'.$name.'-heading','elType'=>'widget','widgetType'=>'heading','isInner'=>true,'settings'=>['title'=>'Following '.$name],'elements'=>[]]]),
    ];
}

$variants=[];
foreach (['snapfull'=>['content_width'=>'full','marrison_horizontal_scroll_pin'=>'yes','marrison_horizontal_scroll_snap'=>'yes'], 'snapboxed'=>['content_width'=>'boxed','marrison_horizontal_scroll_pin'=>'yes','marrison_horizontal_scroll_snap'=>'yes']] as $name=>$settings) {
    $slides=[]; foreach (['#481d6f','#145468','#a13827'] as $i=>$color) $slides[]=variant_slide($name.'-slide-'.($i+1),ucfirst($name).' slide '.($i+1),$color,100);
    $variants[$name]=variant_nodes($name,$settings,$slides);
}
$percent=[]; foreach ([33,50,80] as $i=>$width) $percent[]=variant_slide('percent-'.$width,'Percent '.$width,$i%2?'#145468':'#481d6f',$width);
$variants['percent33-50-80']=variant_nodes('percent33-50-80',['content_width'=>'full','marrison_horizontal_scroll_pin'=>'yes','marrison_horizontal_scroll_snap'=>''],$percent);
$row_slides=[]; foreach (['#481d6f','#145468','#a13827'] as $i=>$color) $row_slides[]=variant_slide('nested-'.$i,'Nested row '.($i+1),$color,100);
$row=[variant_container('nested-row-inner','Nested row inner',['content_width'=>'full','flex_direction'=>'row','flex_wrap'=>'nowrap'],$row_slides)];
$variants['nested-row']=variant_nodes('nested-row',['content_width'=>'full','marrison_horizontal_scroll_pin'=>'yes','marrison_horizontal_scroll_snap'=>''], $row);
$ltr=[]; foreach (['#481d6f','#145468','#a13827'] as $i=>$color) $ltr[]=variant_slide('ltr-'.$i,'LTR '.($i+1),$color,100);
$variants['direction-ltr']=variant_nodes('direction-ltr',['content_width'=>'full','marrison_horizontal_scroll_direction'=>'ltr','marrison_horizontal_scroll_pin'=>'yes','marrison_horizontal_scroll_snap'=>'yes'],$ltr);
$nopin=[]; foreach (['#481d6f','#145468','#a13827'] as $i=>$color) $nopin[]=variant_slide('nopin-'.$i,'No pin '.($i+1),$color,100);
$variants['pin-false']=variant_nodes('pin-false',['content_width'=>'full','marrison_horizontal_scroll_pin'=>'','marrison_horizontal_scroll_snap'=>''],$nopin);

$out=[];
foreach($variants as $name=>$nodes){
    $post_id=wp_insert_post(['post_title'=>'Audit horizontal variant '.$name,'post_type'=>'page','post_status'=>'draft']);
    update_post_meta($post_id,'_elementor_edit_mode','builder'); update_post_meta($post_id,'_elementor_template_type','wp-page');
    update_post_meta($post_id,'_elementor_data',wp_slash(wp_json_encode($nodes)));
    $css=\Elementor\Core\Files\CSS\Post::create($post_id)->get_content();
    $doc=\Elementor\Plugin::$instance->documents->get($post_id); ob_start(); $doc->print_elements_with_wrapper($doc->get_elements_data()); $html=ob_get_clean();
    $html='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Horizontal '.$name.'</title><link rel="stylesheet" href="/wp-content/plugins/elementor/assets/css/frontend.min.css"><link rel="stylesheet" href="/wp-content/plugins/marrison-addon/assets/css/horizontal-scroll.css"><style>body{margin:0;font-family:Arial,sans-serif}.elementor-element-host-'.$name.'{min-height:360px}.elementor-element-nested-row-inner{width:260vw;min-width:260vw}.elementor-element-nested-row-inner>.elementor-element{flex:0 0 100vw;min-width:100vw}</style><style>'.$css.'</style></head><body>'.$html.'<script src="/wp-content/plugins/marrison-addon/assets/js/horizontal-scroll.js"></script></body></html>';
    $path=__DIR__.'/horizontal-'.$name.'.html'; file_put_contents($path,$html); file_put_contents(__DIR__.'/horizontal-'.$name.'.css',$css);
    $out[]=['name'=>$name,'post_id'=>$post_id,'html'=>$path,'css'=>__DIR__.'/horizontal-'.$name.'.css','has_data'=>strpos($html,'data-marrison-horizontal-scroll')!==false,'child_count'=>substr_count($html,'data-element_type="container"')];
}
file_put_contents(__DIR__.'/variants-manifest.json',wp_json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); echo wp_json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
