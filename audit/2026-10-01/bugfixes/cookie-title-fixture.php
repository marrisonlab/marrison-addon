<?php
ob_start(); require dirname(__DIR__).'/wp-runtime-probe.php'; ob_end_clean();
$_COOKIE=[];
$banner=Marrison_Cookie_Banner::get_instance();
ob_start();$banner->render_banner();$banner->render_floating_widget();$ui=ob_get_clean();
$plugin='/wp-content/plugins/marrison-addon/includes/modules/cookie-manager/';
$config=['ajaxUrl'=>'http://localhost:10004/wp-admin/admin-ajax.php','nonce'=>'audit-stale-nonce','consentDuration'=>30,'loadingText'=>'Caricamento cookie...','customizeTitle'=>'Personalizza Cookie','acceptAll'=>'Accetta tutti','rejectAll'=>'Rifiuta tutti','savePreferences'=>'Salva Preferenze','hasConsent'=>false,'closedTriggerModes'=>['desktop'=>'floating','tablet'=>'floating','mobile'=>'floating']];
$html='<!doctype html><html><head><meta charset="utf-8"><title>Cookie title regression</title><link rel="stylesheet" href="'.$plugin.'assets/css/frontend.css?regression=1"><style>body{font:18px Arial;background:#171322;color:#fff}.elementor-kit-audit h3{color:#fff}</style></head><body class="elementor-kit-audit"><h1>Cookie title regression</h1><h3>Theme heading remains white</h3>'.$ui.'<script src="/wp-includes/js/jquery/jquery.min.js"></script><script>var marrisonCookie='.wp_json_encode($config).';</script><script src="'.$plugin.'assets/js/frontend.js"></script></body></html>';
file_put_contents(__DIR__.'/cookie-title.html',$html);
echo "Cookie title fixture generated\n";
