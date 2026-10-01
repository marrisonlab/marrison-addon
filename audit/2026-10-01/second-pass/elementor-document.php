<?php
/** Generate markup AND Elementor CSS from a temporary saved document. DB rolls back. */
ob_start(); require dirname(__DIR__) . '/wp-runtime-probe.php'; ob_end_clean();
$id = wp_insert_post(['post_title'=>'Audit phase2 Elementor document','post_type'=>'page','post_status'=>'draft']);
$elements = [
 ['id'=>'p2-liquid','elType'=>'container','settings'=>['content_width'=>'full','min_height'=>['unit'=>'px','size'=>360],'marrison_liquid_enabled'=>'yes','marrison_liquid_preset'=>'custom','marrison_liquid_background'=>'#050509','marrison_liquid_primary'=>'#7c3aed','marrison_liquid_secondary'=>'#d8b4fe','marrison_liquid_blend_gradient'=>'yes','marrison_liquid_blend_color'=>'#111827','marrison_liquid_blend_height'=>45], 'elements'=>[['id'=>'p2-liquid-heading','elType'=>'widget','widgetType'=>'heading','settings'=>['title'=>'Liquid: actual Elementor CSS'],'elements'=>[]]]],
 ['id'=>'p2-wrapped','elType'=>'container','settings'=>['marrison_addon_url'=>['url'=>'#target','is_external'=>'','custom_attributes'=>'data-audit|yes,aria-label|Go to target'],'min_height'=>['unit'=>'px','size'=>120]],'elements'=>[['id'=>'p2-wrapped-text','elType'=>'widget','widgetType'=>'text-editor','settings'=>['editor'=>'<p>Wrapped link with custom attributes</p>'],'elements'=>[]]]],
 ['id'=>'p2-readmore','elType'=>'widget','widgetType'=>'text-editor','settings'=>['editor'=>'<p>Start <strong>bold</strong> <a href="#target">link</a></p><ul><li>List first</li><li>List second</li></ul>'.str_repeat('<p>Paragraph with <em>inline text</em> to expand and collapse.</p>',5),'marrison_read_more_enabled'=>'yes','marrison_read_more_lines'=>3],'elements'=>[]],
 ['id'=>'p2-steps','elType'=>'widget','widgetType'=>'marrison_steps','settings'=>['steps'=>[['media_type'=>'number','number'=>'1','title'=>'First','text'=>'First step'],['media_type'=>'number','number'=>'2','title'=>'Second','text'=>'Second step']],'layout_direction'=>'horizontal','layout_direction_tablet'=>'vertical','layout_direction_mobile'=>'vertical'],'elements'=>[]],
];
update_post_meta($id, '_elementor_edit_mode', 'builder');
update_post_meta($id, '_elementor_template_type', 'wp-page');
update_post_meta($id, '_elementor_version', ELEMENTOR_VERSION);
update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode($elements)));
$cssObject = \Elementor\Core\Files\CSS\Post::create($id);
$css = $cssObject->get_content(); // Parses real saved settings without writing uploads CSS.
$document = \Elementor\Plugin::$instance->documents->get($id);
ob_start(); $document->print_elements_with_wrapper($document->get_elements_data()); $markup = ob_get_clean();
$plugin = '/wp-content/plugins/marrison-addon/';
$cssAssets = ['assets/css/marrison-liquid-background.css','assets/css/marrison-read-more.css','includes/modules/steps/assets/css/marrison-steps.css'];
$jsAssets = ['assets/js/marrison-liquid-background.js','assets/js/marrison-read-more.js','assets/js/marrison-addon.js'];
$html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Elementor saved settings audit</title><link rel="stylesheet" href="/wp-content/plugins/elementor/assets/css/frontend.min.css">';
foreach($cssAssets as $asset) $html .= '<link rel="stylesheet" href="'.$plugin.$asset.'">';
$html .= '<style>body{margin:0;font:18px Arial}.audit-label{padding:10px;background:#fff;color:#000}</style><style>'.$css.'</style></head><body><h1 class="audit-label">Real Elementor document CSS</h1>'.$markup.'<section id="target" style="min-height:200px">Link target</section>';
foreach($jsAssets as $asset) $html .= '<script src="'.$plugin.$asset.'"></script>';
$html .= '</body></html>';
file_put_contents(__DIR__.'/elementor-document.html',$html);
file_put_contents(__DIR__.'/elementor-document.css',$css);
file_put_contents(__DIR__.'/elementor-document-results.json',json_encode(['temporary_document_id'=>$id,'css_bytes'=>strlen($css),'markup_bytes'=>strlen($markup),'has_custom_attributes_markup'=>strpos($markup,'data-audit="yes"')!==false,'has_custom_attributes_json'=>strpos($markup,'data-audit')!==false,'errors'=>$GLOBALS['audit_errors']],JSON_PRETTY_PRINT));
file_put_contents(__DIR__.'/responsive-parent.html','<!doctype html><html><head><title>Responsive audit frames</title></head><body><h1>Actual frame viewports</h1><h2>Desktop 1280</h2><iframe title="desktop" id="desktop" src="/marrison-audit-p2-elementor.html" style="width:1280px;height:900px"></iframe><h2>Mobile 390</h2><iframe title="mobile" id="mobile" src="/marrison-audit-p2-elementor.html" style="width:390px;height:900px"></iframe></body></html>');
echo 'Rendered document '.$id.'; CSS bytes '.strlen($css).'; markup bytes '.strlen($markup)."\n";
