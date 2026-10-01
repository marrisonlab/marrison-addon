<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/browser-fixture.html') { http_response_code(404); exit; }
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/elementor/browser-fixture.html');
