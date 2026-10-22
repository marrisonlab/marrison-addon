<?php
namespace App\Services;

use App\Models\Settings;

/** Public scanner: no license, site registration or visitor credentials. */
class CookieBrowserlessService
{
    public static function token(): string
    {
        $environment = getenv('BROWSERLESS_TOKEN');
        return $environment ? trim($environment) : MaintenanceSecretVault::decrypt((string) Settings::get('cookie_browserless_token', ''));
    }

    public static function enabled(): bool
    {
        return Settings::get('cookie_browserless_enabled', '0') === '1' && self::token() !== '';
    }

    public static function publicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && !preg_match('/^(100\.(6[4-9]|[7-9][0-9]|1[01][0-9]|12[0-7])\.|198\.(18|19)\.|::ffff:)/i', $ip);
    }

    public static function publicUrl(string $url): array
    {
        $parts = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['port']) || isset($parts['fragment'])
            || strlen($url) > 2048 || preg_match('/[\x00-\x20\\\\]/', $url)) throw new \RuntimeException('URL HTTPS pubblico non valido.', 400);
        $host = strtolower($parts['host'] ?? '');
        if (!preg_match('/^[a-z0-9.-]+\.[a-z0-9-]+$/', $host) || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) throw new \RuntimeException('Dominio pubblico non valido.', 400);
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = [];
        foreach ($records ?: [] as $record) { if (isset($record['ip'])) $ips[] = $record['ip']; if (isset($record['ipv6'])) $ips[] = $record['ipv6']; }
        if (!$ips || count(array_filter($ips, [self::class, 'publicIp'])) !== count($ips)) throw new \RuntimeException('Il dominio non risolve esclusivamente a indirizzi pubblici.', 400);
        return ['host' => $host, 'ip' => $ips[0], 'path' => $parts['path'] ?? '/', 'origin' => 'https://' . $host];
    }

    /** DNS pinned, no redirects, bounded data. Never propagate provider error bodies. */
    private static function request(string $url, array $target, ?string $body = null, int $timeout = 8): array
    {
        if (!function_exists('curl_init')) throw new \RuntimeException('Estensione cURL non disponibile.', 503);
        $handle = curl_init($url);
        $output = ''; $tooLarge = false;
        $ip = strpos($target['ip'], ':') !== false ? '[' . $target['ip'] . ']' : $target['ip'];
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [$target['host'] . ':443:' . $ip], CURLOPT_USERAGENT => 'Marrison-Cookie-Scanner/1.0',
            CURLOPT_WRITEFUNCTION => static function ($curl, $chunk) use (&$output, &$tooLarge) {
                if (strlen($output) + strlen($chunk) > 1048576) { $tooLarge = true; return 0; }
                $output .= $chunk; return strlen($chunk);
            }]);
        if ($body !== null) curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
        $ok = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); curl_close($handle);
        if ($ok === false || $tooLarge) throw new \RuntimeException('Collegamento allo scanner non riuscito o risposta oltre il limite.', 502);
        return [$status, json_decode($output, true)];
    }

    public static function scan(array $input): array
    {
        if (!self::enabled()) throw new \RuntimeException('Scansione remota non attiva su Commander.', 503);
        foreach (['site_url', 'url', 'job', 'proof'] as $field) if (!isset($input[$field]) || !is_string($input[$field])) throw new \RuntimeException('Richiesta incompleta.', 400);
        if (!preg_match('/^[a-f0-9]{32}$/', $input['job']) || !preg_match('/^[a-f0-9]{64}$/', $input['proof'])) throw new \RuntimeException('Prova della scansione non valida.', 400);
        $site = self::publicUrl($input['site_url']); $page = self::publicUrl($input['url']);
        if ($site['origin'] !== $page['origin'] || parse_url($input['site_url'], PHP_URL_QUERY) !== null
            || preg_match('#/(wp-admin|wp-login\.php|wp-json)(/|$)#i', rawurldecode($page['path']))
            || !str_starts_with($page['path'], rtrim($site['path'], '/') . '/')) throw new \RuntimeException('La pagina deve appartenere al sito pubblico indicato.', 400);
        // Ownership is automatic and expiring, not a Commander account or license.
        $proofUrl = rtrim($input['site_url'], '/') . '/?rest_route=/marrison-cookie/v1/scan-proof&' . http_build_query([
            'job' => $input['job'], 'proof' => $input['proof'], 'url' => $input['url']]);
        [$code, $proof] = self::request($proofUrl, $site);
        $expected = hash('sha256', $input['proof'] . "\n" . $input['url']);
        if ($code !== 200 || !is_array($proof) || !hash_equals($expected, (string) ($proof['proof_hash'] ?? ''))) throw new \RuntimeException('Il sito non conferma questa scansione attiva.', 403);

        $dir = COMMANDER_ROOT . '/storage/cookie-scanner';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new \RuntimeException('Archivio scanner non disponibile.', 503);
        // All state includes PHP exit prefix, also when Apache rules are unavailable.
        $key = hash('sha256', $input['site_url'] . $input['job'] . $input['url']);
        $cache = $dir . '/' . $key . '.php';
        $lock = fopen($dir . '/worker.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { if ($lock) fclose($lock); throw new \RuntimeException('Scanner occupato. Riprova tra qualche secondo.', 429); }
        try {
            if (is_file($cache) && filemtime($cache) > time() - 3600) {
                $cached = self::readState($cache);
                if (is_array($cached) && ($cached['protocol'] ?? null) === 1) return $cached;
            }
            $budgetFile = $dir . '/budget.php';
            $budget = self::readState($budgetFile) ?: [];
            $month = gmdate('Y-m'); $day = gmdate('Y-m-d');
            if (($budget['month'] ?? '') !== $month) $budget = ['month' => $month, 'reserved_units' => 0];
            if (($budget['day'] ?? '') !== $day) { $budget['day'] = $day; $budget['hosts'] = []; $budget['ips'] = []; }
            $ipKey = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $limit = max(2, min(1000, (int) Settings::get('cookie_browserless_monthly_units', '900')));
            if ($budget['reserved_units'] + 2 > $limit) throw new \RuntimeException('Quota mensile gratuita riservata allo scanner esaurita.', 429);
            if (($budget['hosts'][$site['host']] ?? 0) >= 100 || ($budget['ips'][$ipKey] ?? 0) >= 200) throw new \RuntimeException('Limite giornaliero di scansione raggiunto.', 429);
            $budget['reserved_units'] += 2; // 45s hard Browserless timeout: at most two 30s units, no proxy/CAPTCHA.
            $budget['hosts'][$site['host']] = ($budget['hosts'][$site['host']] ?? 0) + 1;
            $budget['ips'][$ipKey] = ($budget['ips'][$ipKey] ?? 0) + 1;
            self::writeState($budgetFile, $budget); // Reserve BEFORE provider request; failures still consume allowance.
            $provider = self::publicUrl('https://production-sfo.browserless.io/');
            $body = json_encode(['code' => file_get_contents(__DIR__ . '/cookie-scan.js'), 'context' => ['url' => $input['url']]], JSON_THROW_ON_ERROR);
            [$status, $response] = self::request('https://production-sfo.browserless.io/function?timeout=45000&token=' . rawurlencode(self::token()), $provider, $body, 50);
            if ($status !== 200) throw new \RuntimeException($status === 429 ? 'Browserless ha esaurito la quota o la concorrenza disponibile.' : 'Browserless non ha completato la scansione (HTTP ' . $status . ').', $status === 429 ? 429 : 502);
            $result = is_array($response) && isset($response['data']) ? $response['data'] : $response;
            if (!is_array($result) || ($result['protocol'] ?? null) !== 1 || ($result['url'] ?? '') !== $input['url']
                || !is_array($result['cookies'] ?? null) || !is_array($result['resources'] ?? null) || !is_array($result['issues'] ?? null)) throw new \RuntimeException('Risultato Browserless non valido.', 502);
            // Defense in depth: strip any accidental cookie values before disk or API response.
            foreach ($result['cookies'] as &$cookie) $cookie = array_intersect_key($cookie, array_flip(['cookie_name','cookie_domain','cookie_path','expires','secure','http_only','same_site','phase','partitioned','observed_at']));
            unset($cookie);
            $result['request_id'] = $key;
            $result['reserved_units'] = 2;
            self::writeState($cache, $result);
            // Bounded cleanup of expired result files, never touch anything outside this directory.
            $removed = 0;
            foreach (new \DirectoryIterator($dir) as $file) if ($file->isFile() && preg_match('/^[a-f0-9]{64}\.php$/', $file->getFilename()) && $file->getMTime() < time() - 86400) { unlink($file->getPathname()); if (++$removed >= 100) break; }
            return $result;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private static function readState(string $path): ?array
    {
        if (!is_file($path)) return null;
        $raw = file_get_contents($path); $prefix = "<?php exit; ?>\n";
        if (!is_string($raw) || !str_starts_with($raw, $prefix)) throw new \RuntimeException('Archivio scanner non leggibile.', 503);
        $data = json_decode(substr($raw, strlen($prefix)), true);
        if (!is_array($data)) throw new \RuntimeException('Archivio scanner non valido.', 503);
        return $data;
    }

    private static function writeState(string $path, array $data): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp.php';
        if (file_put_contents($temporary, "<?php exit; ?>\n" . json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temporary, $path)) { @unlink($temporary); throw new \RuntimeException('Salvataggio scanner non riuscito.', 503); }
    }
}
