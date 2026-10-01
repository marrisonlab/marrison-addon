<?php
ob_start(); require dirname(__DIR__) . '/wp-runtime-probe.php'; ob_end_clean();
$w = \Elementor\Plugin::$instance->widgets_manager->get_widget_types()['text-editor'];
echo get_class($w), "\n";
foreach (get_class_methods($w) as $m) if (stripos($m, 'setting') !== false || stripos($m, 'render') !== false) echo $m, "\n";
