<?php
ob_start(); require dirname(__DIR__, 2) . '/wp-runtime-probe.php'; ob_end_clean();
$prototype=\Elementor\Plugin::$instance->widgets_manager->get_widget_types()['ticker'];$class=get_class($prototype);
$w=new $class(['id'=>'ticker-array-probe','settings'=>['source'=>'repeater','direction'=>'left','speed'=>10,'pause_on_hover'=>'','ticker_items'=>[['item_text'=>['unexpected'=>'array'],'item_link'=>[]]],'separator_icon'=>[]]],[]);
$m=new ReflectionMethod($w,'render');$m->setAccessible(true);ob_start();try{$m->invoke($w);echo "render=ok\n";}catch(Throwable $e){echo "render_error=".get_class($e).':'.$e->getMessage()."\n";}ob_end_flush();
