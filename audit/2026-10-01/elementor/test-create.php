<?php
ob_start(); require dirname(__DIR__) . '/wp-runtime-probe.php'; ob_end_clean();
$raw=['id'=>'abc123','elType'=>'container','isInner'=>false,'settings'=>[],'elements'=>[]];
$e=\Elementor\Plugin::$instance->elements_manager->create_element_instance($raw);
var_dump(is_object($e), is_object($e)?get_class($e):null, method_exists($e,'print_element'));
if($e){ob_start();$e->print_element();$o=ob_get_clean(); echo strlen($o),"\n",substr($o,0,300),"\n";}
