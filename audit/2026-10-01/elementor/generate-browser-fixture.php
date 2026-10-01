<?php
// Generates a standalone browser fixture from real registered Elementor widget objects.
ob_start(); require dirname(__DIR__) . '/wp-runtime-probe.php'; ob_end_clean();

$manager = \Elementor\Plugin::$instance->widgets_manager;
$types = $manager->get_widget_types();

function audit_print_element(array $raw) {
    $element = \Elementor\Plugin::$instance->elements_manager->create_element_instance($raw);
    if (!is_object($element)) return '<div class="audit-error">Element creation failed</div>';
    ob_start();
    try { $element->print_element(); } catch (Throwable $e) { echo '<pre class="audit-error">' . esc_html($e->getMessage()) . '</pre>'; }
    return ob_get_clean();
}

$heading = audit_print_element(['id'=>'fixture-heading','elType'=>'widget','widgetType'=>'heading','isInner'=>false,'settings'=>[
    'title' => 'Prima <strong>evidenza</strong><br>Seconda <em>riga</em>',
    'header_size' => 'h2', '_animation' => '', '_animation_tablet' => 'marrisonDropSoft',
    '_animation_mobile' => 'marrisonLettersFocus', 'marrison_header_animation' => '',
]]);
$letter_heading = audit_print_element(['id'=>'fixture-letters','elType'=>'widget','widgetType'=>'heading','isInner'=>false,'settings'=>[
    'title'=>'Prima <strong>evidenza</strong><br>Seconda <em>riga</em>',
    'header_size'=>'h2', '_animation'=>'marrisonLettersFocus',
]]);
$text = audit_print_element(['id'=>'fixture-read-more','elType'=>'widget','widgetType'=>'text-editor','isInner'=>false,'settings'=>[
    'editor' => '<p>' . str_repeat('Testo Read More con contenuto lungo. ', 18) . '</p>',
    'marrison_read_more_enabled'=>'yes','marrison_read_more_lines'=>3,'marrison_read_more_label_more'=>'Leggi di più','marrison_read_more_label_less'=>'Leggi di meno',
]]);
$ticker = audit_print_element(['id'=>'fixture-ticker','elType'=>'widget','widgetType'=>'ticker','isInner'=>false,'settings'=>[
    'source'=>'repeater','direction'=>'left','speed'=>12,'pause_on_hover'=>'yes',
    'ticker_items'=>[['item_text'=>'Prima notizia'],['item_text'=>'Seconda notizia'],['item_text'=>'Terza notizia']],
    'separator_icon'=>['value'=>'fas fa-star','library'=>'fa-solid'],
]]);
$steps_data = [];
for ($i=1; $i<=8; $i++) $steps_data[] = ['media_type'=>'number','number'=>(string)$i,'title'=>'Step '.$i,'text'=>'Descrizione step '.$i];
$steps = audit_print_element(['id'=>'fixture-steps','elType'=>'widget','widgetType'=>'marrison_steps','isInner'=>false,'settings'=>['steps'=>$steps_data,'connector_type'=>'arrow','layout_direction'=>'horizontal','layout_direction_tablet'=>'horizontal','layout_direction_mobile'=>'vertical']]);

$wrapped = audit_print_element(['id'=>'fixture-wrapped','elType'=>'container','isInner'=>false,'settings'=>['marrison_addon_url'=>['url'=>'#wrapped-target','is_external'=>'','nofollow'=>'']],'elements'=>[['id'=>'fixture-wrapped-text','elType'=>'widget','widgetType'=>'text-editor','isInner'=>true,'settings'=>['editor'=>'<p>Container target #wrapped-target <a href="#nested">nested link</a></p>']]]]);
$slides = array_map(function($id,$title){ return ['id'=>$id,'elType'=>'container','isInner'=>true,'settings'=>[],'elements'=>[['id'=>$id.'-heading','elType'=>'widget','widgetType'=>'heading','isInner'=>true,'settings'=>['title'=>$title]]]]; }, ['fixture-slide-a','fixture-slide-b','fixture-slide-c'], ['Slide 1','Slide 2','Slide 3']);
$horizontal = audit_print_element(['id'=>'fixture-horizontal','elType'=>'container','isInner'=>false,'settings'=>['marrison_horizontal_scroll_enabled'=>'yes','marrison_horizontal_scroll_pin'=>'yes','marrison_horizontal_scroll_snap'=>'yes','marrison_horizontal_scroll_snap_threshold'=>120,'marrison_horizontal_scroll_speed'=>1],'elements'=>$slides]);
$liquid_element = audit_print_element(['id'=>'fixture-liquid','elType'=>'container','isInner'=>false,'settings'=>['marrison_liquid_enabled'=>'yes','marrison_liquid_preset'=>'custom','marrison_liquid_background'=>'#050509','marrison_liquid_primary'=>'#7c3aed','marrison_liquid_secondary'=>'#d8b4fe','marrison_liquid_blend_gradient'=>'yes','marrison_liquid_blend_color'=>'#111827','marrison_liquid_blend_height'=>45],'elements'=>[['id'=>'fixture-liquid-heading','elType'=>'widget','widgetType'=>'heading','isInner'=>true,'settings'=>['title'=>'Liquid custom/global colors']]]]);
$asset = function($path) { return plugins_url($path, Marrison_Addon::plugin_file()); };
$html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Marrison Elementor audit</title>';
$html .= '<link rel="stylesheet" href="'.$asset('assets/css/horizontal-scroll.css').'">';
$html .= '<link rel="stylesheet" href="'.$asset('assets/css/marrison-liquid-background.css').'">';
$html .= '<link rel="stylesheet" href="'.$asset('assets/css/marrison-read-more.css').'">';
$html .= '<link rel="stylesheet" href="'.$asset('includes/modules/steps/assets/css/marrison-steps.css').'">';
$html .= '<link rel="stylesheet" href="'.home_url('/wp-content/plugins/elementor/assets/css/frontend.min.css').'">';
$html .= '<link rel="stylesheet" href="'.$asset('assets/css/marrison-header-animations.css').'">';
$html .= '<style>body{margin:0;font-family:Arial,sans-serif;background:#111;color:#fff}.audit-section{padding:32px;min-height:180px}.fixture-controls{position:fixed;z-index:20;right:12px;top:12px;background:#fff;color:#111;padding:8px}.audit-error{color:#f88}#fixture-horizontal{min-height:420px}#fixture-horizontal .e-con-inner{width:100%}#fixture-horizontal-row{width:260vw;min-width:260vw}#fixture-horizontal-row>.elementor-element{flex:0 0 86vw;min-width:86vw;min-height:260px;display:grid;place-items:center;font-size:32px}#fixture-liquid{min-height:360px}.marrison-listing-grid-title{padding:10px;background:#fff;color:#111}</style></head><body>';
$html .= '<div class="fixture-controls">Resize viewport, test wheel/click/toggle</div>';
$html .= '<section class="audit-section"><h1>Header animation fixture</h1>'.$heading.'</section>';
$html .= '<section class="audit-section"><h2>Letter animation markup fixture</h2>'.$letter_heading.'</section>';
$html .= '<section class="audit-section"><h2>Read More</h2>'.$text.'</section>';
$html .= '<section class="audit-section"><h2>Wrapped Link</h2>'.$wrapped.'</section>';
$html .= '<section class="audit-section"><h2>Ticker</h2>'.$ticker.'</section>';
$html .= '<section class="audit-section"><h2>Steps 2-8 fixture (8)</h2>'.$steps.'</section>';
$html .= '<section class="audit-section"><h2>Horizontal Scroll real Container</h2>'.$horizontal.'</section>';
$html .= '<section class="audit-section"><h2>Liquid Background real Container</h2>'.$liquid_element.'<div class="elementor-shape elementor-shape-bottom">Divider preserved</div></section>';
$html .= '<section id="wrapped-target" class="audit-section"><h2>Wrapped target</h2><p>Anchor destination.</p></section>';
$html .= '<script src="'.$asset('assets/js/marrison-addon.js').'"></script><script src="'.$asset('assets/js/marrison-read-more.js').'"></script><script src="'.$asset('assets/js/horizontal-scroll.js').'"></script><script src="'.$asset('assets/js/marrison-liquid-background.js').'"></script><script src="'.$asset('assets/js/marrison-header-animations.js').'"></script></body></html>';
$html = str_replace(['#fixture-horizontal-row', '#fixture-horizontal', '#fixture-liquid'], ['.elementor-element-fixture-horizontal-row', '.elementor-element-fixture-horizontal', '.elementor-element-fixture-liquid'], $html);
$html = str_replace('</head>', '<style>.ticker-item-separator svg{width:24px;height:24px}.elementor-element-fixture-horizontal-row,.elementor-element-fixture-horizontal-row>.e-con-inner{display:flex;flex-direction:row;flex-wrap:nowrap}.elementor-element-fixture-horizontal-row .elementor-element-fixture-slide-a,.elementor-element-fixture-horizontal-row .elementor-element-fixture-slide-b,.elementor-element-fixture-horizontal-row .elementor-element-fixture-slide-c{flex:0 0 86vw;min-width:86vw;min-height:260px}.elementor-element-fixture-slide-a{background:#241044}.elementor-element-fixture-slide-b{background:#10325d}.elementor-element-fixture-slide-c{background:#3d1b31}#hdr{position:fixed;top:0;left:0;right:0;height:64px;background:#333;z-index:99;padding:12px}</style></head>', $html);
$html = str_replace('<body>', '<body><header id="hdr">Audit header 64px <a href="#wrapped-target">Anchor test</a></header>', $html);
$html = str_replace('</body>', '<script src="'.$asset('assets/js/marrison-anchor-offset.js').'"></script></body>', $html);
$html = str_replace('</head>', '<style>.elementor-element-fixture-horizontal{--min-height:360px;min-height:360px}.elementor-element-fixture-horizontal .marrison-horizontal-scroll-mover>.elementor-element{--width:1201px;--min-height:300px;width:1201px;min-width:1201px;flex:0 0 1201px;min-height:300px;box-sizing:border-box}.elementor-element-fixture-liquid{--min-height:360px;min-height:360px}</style></head>', $html);
$target = __DIR__ . '/browser-fixture.html';
file_put_contents($target, $html);
echo "Generated $target\n";
