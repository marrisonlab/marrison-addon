<?php
/** Actual Elementor markup, generated CSS and native frontend runtime; DB rollback. */
ob_start(); require dirname(__DIR__).'/wp-runtime-probe.php'; ob_end_clean();
$id=wp_insert_post(['post_title'=>'Remediation Elementor fixture','post_type'=>'page','post_status'=>'draft']);
$title='Prima <strong>evidenza</strong><br>Seconda <em>riga</em> <a href="#target">link</a> 🙂';
$elements=[
 ['id'=>'fix-responsive','elType'=>'widget','widgetType'=>'heading','settings'=>['title'=>$title,'_animation'=>'','_animation_tablet'=>'marrisonDropSoft','_animation_mobile'=>'marrisonLettersFocus'],'elements'=>[]],
 ['id'=>'fix-letters','elType'=>'widget','widgetType'=>'heading','settings'=>['title'=>$title,'_animation'=>'marrisonLettersRise','_animation_tablet'=>'marrisonFocusIn','_animation_mobile'=>'none'],'elements'=>[]],
 ['id'=>'fix-delay','elType'=>'widget','widgetType'=>'heading','settings'=>['title'=>'Native delayed animation','_animation'=>'marrisonLiftSoft','_animation_delay'=>1500],'elements'=>[]],
 ['id'=>'fix-fallback','elType'=>'widget','widgetType'=>'heading','settings'=>['title'=>'Fallback letters','marrison_header_animation'=>'marrisonLettersElastic'],'elements'=>[]],
 ['id'=>'fix-wrapped','elType'=>'container','settings'=>['marrison_addon_url'=>['url'=>'#target','is_external'=>'','custom_attributes'=>'data-audit|yes,aria-label|Go to target,onclick|alert(1),data-marrison-addon|bad'],'min_height'=>['unit'=>'px','size'=>80]],'elements'=>[['id'=>'fix-wrapped-text','elType'=>'widget','widgetType'=>'text-editor','settings'=>['editor'=>'<p>Wrapped container: click this area</p>'],'elements'=>[]]]],
];
foreach(['_elementor_edit_mode'=>'builder','_elementor_template_type'=>'wp-page','_elementor_version'=>ELEMENTOR_VERSION,'_elementor_data'=>wp_slash(wp_json_encode($elements))] as $key=>$value){update_post_meta($id,$key,$value);}
$css=\Elementor\Core\Files\CSS\Post::create($id)->get_content();
$doc=\Elementor\Plugin::$instance->documents->get($id);
ob_start();$doc->print_elements_with_wrapper($doc->get_elements_data());$markup=ob_get_clean();
do_action('wp_enqueue_scripts');
\Elementor\Plugin::$instance->frontend->enqueue_styles();
\Elementor\Plugin::$instance->frontend->enqueue_scripts();
ob_start();wp_head();$head=ob_get_clean();
ob_start();wp_print_footer_scripts();$footer=ob_get_clean();
$html='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Audit corrections Elementor</title>'.$head.'<style>body{margin:0;font:16px Arial;color:#111;background:#fff}.elementor-heading-title{font:22px Arial!important}.elementor-widget{padding:8px}#target{min-height:120px;background:#ddd}</style><style>'.$css.'</style></head><body>'.$markup.'<section id="target">Wrapped link destination</section>'.$footer.'</body></html>';
file_put_contents(__DIR__.'/elementor.html',$html);
file_put_contents(__DIR__.'/elementor.css',$css);
file_put_contents(__DIR__.'/elementor-results.json',wp_json_encode(['document_id'=>$id,'custom_attributes'=>strpos($markup,'data-audit="yes"')!==false,'responsive_mapping'=>strpos($markup,'data-marrison-heading-animations')!==false,'no_forced_desktop_animation'=>strpos($markup,'class="elementor-element elementor-element-fix-responsive marrison-heading-animated marrisonDropSoft')===false,'errors'=>$GLOBALS['audit_errors']],JSON_PRETTY_PRINT));
file_put_contents(__DIR__.'/frames.html','<!doctype html><html><head><title>Elementor correction proofs</title><style>body{font:14px Arial;margin:10px}iframe{border:1px solid #bbb;transform-origin:top left}.frame{display:inline-block;vertical-align:top;height:480px;overflow:hidden}h2{font-size:16px}</style></head><body><h1>Actual Elementor responsive viewports</h1><div class="frame" style="width:450px"><h2>Desktop 1280</h2><iframe id="desktop" title="desktop" src="/marrison-remediation-elementor.html" style="width:1280px;height:1280px;transform:scale(.35)"></iframe></div><div class="frame" style="width:370px"><h2>Tablet 900</h2><iframe id="tablet" title="tablet" src="/marrison-remediation-elementor.html" style="width:900px;height:1080px;transform:scale(.4)"></iframe></div><div class="frame" style="width:280px"><h2>Mobile 390</h2><iframe id="mobile" title="mobile" src="/marrison-remediation-elementor.html" style="width:390px;height:650px;transform:scale(.7)"></iframe></div></body></html>');
echo "Generated real Elementor fixture, document rolls back\n";
