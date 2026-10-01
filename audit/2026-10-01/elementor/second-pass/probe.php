<?php
ob_start(); require dirname(__DIR__, 2) . '/wp-runtime-probe.php'; ob_end_clean();
$em = \Elementor\Plugin::$instance->elements_manager;
$raw = ['id'=>'probe-wrap','elType'=>'container','isInner'=>false,'settings'=>['marrison_addon_url'=>['url'=>'#target','is_external'=>'1','nofollow'=>'1','custom_attributes'=>'data-test|wrapped']], 'elements'=>[]];
$element = $em->create_element_instance($raw);
$settings = $element->get_settings_for_display();
$control = $element->get_controls()['marrison_addon_url'] ?? null;
echo "wrapped_settings=" . json_encode($settings['marrison_addon_url'] ?? null, JSON_UNESCAPED_SLASHES) . "\n";
echo "wrapped_control=" . json_encode($control, JSON_UNESCAPED_SLASHES) . "\n";
ob_start(); $element->print_element(); $html = ob_get_clean();
echo "wrapped_html=" . substr(preg_replace('/\\s+/', ' ', $html), 0, 900) . "\n";
$css = file_get_contents(dirname(__DIR__, 4) . '/marrison-addon/includes/modules/steps/assets/css/marrison-steps.css');
preg_match_all('/@media \\(max-width: ([0-9]+)px\\)/', $css, $matches);
echo "steps_media_breakpoints=" . json_encode($matches[1]) . "\n";
$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types()['marrison_steps'];
$layout = $widget->get_controls()['layout_direction'] ?? null;
echo "steps_layout_control=" . json_encode($layout, JSON_UNESCAPED_SLASHES) . "\n";
