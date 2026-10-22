<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CookieBrowserlessService;
use App\Services\RateLimiter;

// Release the admin session lock before waiting for the remote browser.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
header('Cache-Control: no-store');
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') json_response(['success' => true, 'protocol' => 1, 'enabled' => CookieBrowserlessService::enabled()]);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Metodo non consentito.', 405);
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) json_error('Richiesta troppo grande.', 413);
    if (RateLimiter::tooManyRequests('/api/cookie-scan')) json_error('Troppe richieste.', 429);
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if (strlen($raw) > 16384) json_error('Richiesta troppo grande.', 413);
    $input = json_decode($raw, true);
    if (!is_array($input)) json_error('JSON non valido.', 400);
    if (function_exists('set_time_limit')) @set_time_limit(65);
    $result = CookieBrowserlessService::scan($input);
    json_response(['success' => true, 'result' => $result]);
} catch (Throwable $error) {
    $code = in_array($error->getCode(), [400,403,429,502,503], true) ? $error->getCode() : 503;
    // No exception details, token, provider body or cookie values in logs or responses.
    json_error($error instanceof RuntimeException ? $error->getMessage() : 'Servizio di scansione non disponibile.', $code);
}
