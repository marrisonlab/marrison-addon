<?php
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/mobile') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Barra mobile 375 px</title></head><body style="background:#eee;font:14px Arial"><p>Fixture locale — viewport mobile 375 px</p><iframe title="Barra mobile" src="/page" style="width:375px;height:700px;border:1px solid #999"></iframe></body></html>';
    return;
}
require dirname(__DIR__, 3) . '/tests/cookie-consent-fixture.php';
