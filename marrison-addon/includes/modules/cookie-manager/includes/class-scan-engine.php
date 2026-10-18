<?php
/** Progressive same-site HTTP crawler and isolated browser scan coordinator. */
class Marrison_Addon_Cookie_Scan_Engine {
    const PROTOCOL = 2;
    const REVISION = '20261018.1';
    private static $instance;
    private $frame = null;

    public static function get_instance() {
        if (!self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        foreach (array('start', 'step', 'state', 'browser', 'inventory', 'cancel', 'export') as $action) {
            add_action('wp_ajax_marrison_addon_cookie_engine_' . $action, array($this, 'ajax_' . $action));
        }
        add_action('marrison_addon_cookie_engine_tick', array($this, 'scheduled_step'));
        $this->frame = $this->frame_context();
        if ($this->frame) {
            if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
            add_action('template_redirect', array($this, 'frame_headers'), -9999);
            add_action('wp_head', array($this, 'frame_agent'), -9999);
        }
    }

    public function diagnostics() {
        $class = new ReflectionClass('Marrison_Cookie_Scanner');
        $loaded = realpath($class->getFileName());
        $expected = realpath(__DIR__ . '/class-cookie-scanner.php');
        return array(
            'protocol' => self::PROTOCOL, 'revision' => self::REVISION,
            'plugin_version' => class_exists('Marrison_Addon') ? Marrison_Addon::VERSION : '',
            'scanner_file' => wp_normalize_path($class->getFileName()),
            'scanner_hash' => hash_file('sha256', $class->getFileName()),
            'engine_hash' => hash_file('sha256', __FILE__),
            'compatible' => $loaded === $expected && $class->hasMethod('engine_prepare'),
        );
    }

    private function authorize() {
        if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'Permessi insufficienti'), 403); }
        check_ajax_referer('marrison_cookie_engine', 'nonce');
        $diagnostics = $this->diagnostics();
        if (!$diagnostics['compatible']) {
            wp_send_json_error(array('message' => 'È attivo uno scanner diverso da quello di Marrison Addon. Verifica copie del plugin e cache PHP.', 'runtime' => $diagnostics), 409);
        }
    }

    private function file_path($name) {
        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error']) || empty($uploads['basedir'])) { throw new RuntimeException('Archivio scansioni non disponibile.'); }
        $dir = rtrim($uploads['basedir'], '/\\') . '/marrison-cookie';
        if (!wp_mkdir_p($dir)) { throw new RuntimeException('Impossibile creare l’archivio scansioni.'); }
        return $dir . '/' . $name;
    }

    private function read_job() {
        $path = $this->file_path('scan-engine.php');
        if (!is_file($path)) { return null; }
        $data = file_get_contents($path);
        $prefix = "<?php exit; ?>\n";
        if ($data === false || strpos($data, $prefix) !== 0) { throw new RuntimeException('Archivio scansioni danneggiato.'); }
        $job = json_decode(substr($data, strlen($prefix)), true);
        if (!is_array($job) || !isset($job['id'], $job['status'])) { throw new RuntimeException('Stato della scansione non leggibile.'); }
        return $job;
    }

    private function write_job($job) {
        $path = $this->file_path('scan-engine.php');
        $temporary = $this->file_path('scan-' . bin2hex(random_bytes(8)) . '.tmp.php');
        $json = wp_json_encode($job, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false || file_put_contents($temporary, "<?php exit; ?>\n" . $json, LOCK_EX) === false) {
            throw new RuntimeException('Salvataggio del rapporto di scansione fallito.');
        }
        if (!rename($temporary, $path)) { @unlink($temporary); throw new RuntimeException('Aggiornamento del rapporto di scansione fallito.'); }
    }

    private function locked($callback) {
        $lock = fopen($this->file_path('scan-engine.lock'), 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) { fclose($lock); }
            throw new RuntimeException('engine_busy');
        }
        try { return call_user_func($callback); }
        finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function respond($callback) {
        $this->authorize();
        try { $data = call_user_func($callback); }
        catch (Throwable $error) {
            wp_send_json_error(array('message' => $error->getMessage(), 'code' => $error->getMessage() === 'engine_busy' ? 'engine_busy' : 'scan_failed', 'runtime' => $this->diagnostics()));
        }
        wp_send_json_success(array_merge(array('runtime' => $this->diagnostics()), $data));
    }

    public function ajax_start() {
        $this->respond(function() {
            return $this->locked(function() {
                $limit = isset($_POST['limit']) ? max(1, min(5000, (int) $_POST['limit'])) : 500;
                $job = $this->new_job($limit);
                $this->write_job($job);
                return array('job' => $this->summary($job));
            });
        });
    }

    public function ajax_state() {
        $this->respond(function() { return array('job' => $this->summary($this->read_job())); });
    }

    public function ajax_step() {
        $this->respond(function() {
            return $this->locked(function() {
                $job = $this->require_job();
                $this->http_step($job);
                $this->write_job($job);
                return array('job' => $this->summary($job));
            });
        });
    }

    public function ajax_cancel() {
        $this->respond(function() {
            return $this->locked(function() {
                $job = $this->require_job();
                $job['status'] = 'cancelled';
                $job['updated'] = time();
                $this->write_job($job);
                return array('job' => $this->summary($job));
            });
        });
    }

    public function ajax_inventory() {
        $this->respond(function() {
            $category = isset($_POST['category']) && is_string($_POST['category']) ? sanitize_key($_POST['category']) : 'all';
            $scanner = Marrison_Cookie_Scanner::get_instance();
            $cookies = $scanner->get_cookies($category);
            $job = $this->read_job();
            $evidence = $job ? $job['cookies'] : array();
            foreach ($cookies as $cookie) {
                $key = $this->cookie_key((array) $cookie);
                $cookie->evidence = isset($evidence[$key]) ? $evidence[$key] : null;
            }
            return array('cookies' => $cookies, 'count' => count($cookies), 'job' => $this->summary($job));
        });
    }

    public function ajax_export() {
        $this->respond(function() {
            $job = $this->read_job();
            if ($job) { unset($job['saved_categories']); }
            return array('report' => $job);
        });
    }

    private function require_job() {
        $job = $this->read_job();
        $id = isset($_POST['job']) && is_string($_POST['job']) ? wp_unslash($_POST['job']) : '';
        if (!$job || !hash_equals($job['id'], $id)) { throw new RuntimeException('La scansione è cambiata. Ricarica lo stato.'); }
        if ($job['expires'] < time()) { throw new RuntimeException('Scansione scaduta. Avvia una nuova scansione.'); }
        return $job;
    }

    private function new_job($limit) {
        $scanner = Marrison_Cookie_Scanner::get_instance();
        $categories = $scanner->engine_prepare();
        if ($categories === false) { throw new RuntimeException($scanner->get_last_scan_error()); }
        $previous = $this->read_job();
        if ($previous && isset($previous['saved_categories'])) { $categories = array_replace($previous['saved_categories'], $categories); }
        $home = $this->normalize_url(home_url('/'), home_url('/'));
        if (!$home) { throw new RuntimeException('URL del sito non valido.'); }
        $job = array('id' => bin2hex(random_bytes(16)), 'revision' => self::REVISION, 'status' => 'http', 'created' => time(), 'updated' => time(), 'expires' => time() + 24 * 3600,
            'limit' => $limit, 'limit_reached' => false, 'queue' => array(), 'seen' => array(), 'pages' => array(), 'http_done' => 0, 'http_failed' => 0,
            'browser_cursor' => 0, 'browser_failed' => 0, 'cookies' => array(), 'issues' => array(), 'issues_total' => 0, 'saved_categories' => $categories);
        $this->enqueue($job, $home, 'page');
        $this->enqueue($job, home_url('/wp-sitemap.xml'), 'sitemap');
        $this->enqueue($job, home_url('/robots.txt'), 'robots');
        return $job;
    }

    /** Same-origin URL normalization also excludes administrative/action endpoints. */
    public function normalize_url($url, $base, $kind = 'page') {
        if (!is_string($url) || preg_match('/[\x00-\x20\\\\]/', $url)) { return false; }
        $url = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');
        $home = wp_parse_url(home_url('/'));
        $base_parts = wp_parse_url($base);
        if (strpos($url, '//') === 0) { $url = $home['scheme'] . ':' . $url; }
        if (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            if ($url === '' || $url[0] === '#') { return false; }
            $origin = $home['scheme'] . '://' . $home['host'] . (isset($home['port']) ? ':' . $home['port'] : '');
            $base_path = isset($base_parts['path']) ? $base_parts['path'] : '/';
            $url = $origin . ($url[0] === '/' ? $url : ($url[0] === '?' ? $base_path . $url : substr($base_path, 0, strrpos($base_path, '/') + 1) . $url));
        }
        $parts = wp_parse_url($url);
        if (!$parts || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || strtolower($parts['host']) !== strtolower($home['host']) || strtolower($parts['scheme']) !== strtolower($home['scheme'])
            || (isset($parts['port']) ? (int) $parts['port'] : 0) !== (isset($home['port']) ? (int) $home['port'] : 0)) { return false; }
        $segments = array();
        foreach (explode('/', isset($parts['path']) ? $parts['path'] : '/') as $segment) {
            $decoded = rawurldecode($segment);
            if ($decoded === '..') { array_pop($segments); }
            elseif ($decoded !== '.' && $segment !== '') { $segments[] = $segment; }
        }
        $path = '/' . implode('/', $segments);
        if (substr(isset($parts['path']) ? $parts['path'] : '/', -1) === '/' && $path !== '/') { $path .= '/'; }
        $decoded_path = rawurldecode($path);
        if (preg_match('#/(?:wp-admin|wp-json)(?:/|$)|/(?:wp-login|wp-cron|xmlrpc)\.php(?:/|$)#i', $decoded_path)) { return false; }
        $home_path = isset($home['path']) ? rtrim($home['path'], '/') : '';
        if ($home_path && strpos($decoded_path . '/', $home_path . '/') !== 0) { return false; }
        if ($kind === 'page' && preg_match('/\.(?:xml|txt|pdf|zip|jpe?g|png|gif|svg|webp|avif|mp[34]|webm|css|js|woff2?|ttf|ico)$/i', $path)) { return false; }
        $query = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach ($query as $key => $value) {
                if (strpos($key, 'utm_') === 0 || in_array($key, array('gclid', 'fbclid', 'marrison_scan', 'marrison_scan_phase', 'marrison_scan_token'), true)) { unset($query[$key]); continue; }
                if (!in_array($key, array('p', 'page_id', 'paged', 'page', 'pagename', 'lang', 'product', 'product_cat'), true) || !is_scalar($value) || strlen((string) $value) > 200) { return false; }
            }
            ksort($query);
        }
        return $home['scheme'] . '://' . strtolower($home['host']) . (isset($home['port']) ? ':' . $home['port'] : '') . $path . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }

    private function enqueue(&$job, $url, $kind, $base = '') {
        $url = $this->normalize_url($url, $base ?: home_url('/'), $kind);
        if (!$url || isset($job['seen'][$url])) { return; }
        if (count($job['seen']) > $job['limit'] * 4 + 100) { $job['limit_reached'] = true; return; }
        $job['seen'][$url] = true;
        $job['queue'][] = array('url' => $url, 'kind' => $kind);
    }

    private function issue(&$job, $url, $message) {
        $job['issues_total']++;
        if (count($job['issues']) < 100) { $job['issues'][] = array('url' => $url, 'message' => $message); }
    }

    private function http_step(&$job) {
        if ($job['status'] !== 'http') { return; }
        while ($job['queue'] && $job['http_done'] + $job['http_failed'] >= $job['limit'] && $job['queue'][0]['kind'] === 'page') {
            array_shift($job['queue']);
            $job['limit_reached'] = true;
        }
        if (!$job['queue']) {
            $job['status'] = $job['pages'] ? 'browser' : 'partial';
            $job['updated'] = time();
            $this->commit($job);
            return;
        }
        $item = array_shift($job['queue']);
        $url = $item['url'];
        $response = null;
        for ($redirect = 0; $redirect < 4; $redirect++) {
            $response = wp_remote_get($url, array('timeout' => 8, 'redirection' => 0, 'cookies' => array(), 'limit_response_size' => 2097152,
                'reject_unsafe_urls' => true, 'user-agent' => 'Marrison Cookie Engine/' . self::REVISION,
                'headers' => array('Accept' => 'text/html,application/xhtml+xml,application/xml,text/plain;q=0.8', 'Cache-Control' => 'no-cache')));
            if (is_wp_error($response)) { break; }
            foreach ($this->http_cookies($response, $url) as $cookie) { $this->observe($job, $cookie, $url, 'before', 'http'); }
            $code = wp_remote_retrieve_response_code($response);
            if ($code < 300 || $code >= 400) { break; }
            $next = $this->normalize_url(wp_remote_retrieve_header($response, 'location'), $url, $item['kind']);
            if (!$next || $next === $url) { break; }
            $url = $next;
        }
        $code = !is_wp_error($response) ? wp_remote_retrieve_response_code($response) : 0;
        if (is_wp_error($response) || $code < 200 || $code >= 300) {
            if ($item['kind'] === 'page') { $job['http_failed']++; $this->issue($job, $url, is_wp_error($response) ? $response->get_error_message() : 'Risposta HTTP ' . $code); }
        } else {
            $body = wp_remote_retrieve_body($response);
            $type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
            if (strlen($body) >= 2097152) { $this->issue($job, $url, 'Risposta oltre 2 MiB: analisi della pagina parziale.'); }
            if ($item['kind'] === 'robots') {
                if (preg_match_all('/^Sitemap:\s*(\S+)/mi', $body, $matches)) {
                    foreach ($matches[1] as $link) { $this->enqueue($job, $link, 'sitemap', $url); }
                }
            } elseif ($item['kind'] === 'sitemap') {
                if (preg_match_all('#<(?:[a-z0-9_-]+:)?loc\b[^>]*>([^<]+)</#i', $body, $matches)) {
                    $kind = stripos($body, '<sitemapindex') !== false ? 'sitemap' : 'page';
                    foreach ($matches[1] as $link) { $this->enqueue($job, trim($link), $kind, $url); }
                }
            } elseif (strpos($type, 'text/html') !== false || strpos($type, 'application/xhtml+xml') !== false) {
                if (!in_array($url, $job['pages'], true)) { $job['pages'][] = $url; $job['http_done']++; }
                if (preg_match_all('/<a\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $body, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) { $this->enqueue($job, !empty($match[1]) ? $match[1] : (!empty($match[2]) ? $match[2] : (isset($match[3]) ? $match[3] : '')), 'page', $url); }
                }
            }
        }
        $job['updated'] = time();
        if (!$job['queue']) {
            $job['status'] = $job['pages'] ? 'browser' : 'partial';
            $this->commit($job);
        }
    }

    /** Set-Cookie values are deliberately discarded before any persistence. */
    public function http_cookies($response, $url) {
        $headers = wp_remote_retrieve_headers($response);
        $values = is_object($headers) && is_callable(array($headers, 'getValues')) ? $headers->getValues('set-cookie') : wp_remote_retrieve_header($response, 'set-cookie');
        if (is_string($values)) { $values = preg_split('/,(?=\s*[^=;,\s]+=)/', $values); }
        $result = array();
        foreach (is_array($values) ? $values : array() as $header) {
            $parts = explode(';', $header);
            $pair = explode('=', array_shift($parts), 2);
            $name = trim($pair[0]);
            if (count($pair) !== 2 || !preg_match('/^[!#$%&\'*+.^_`|~0-9a-z-]+$/i', $name)) { continue; }
            $attrs = array();
            foreach ($parts as $part) { $attribute = explode('=', trim($part), 2); $attrs[strtolower($attribute[0])] = isset($attribute[1]) ? trim($attribute[1]) : true; }
            $host = strtolower(wp_parse_url($url, PHP_URL_HOST));
            $domain = isset($attrs['domain']) && is_string($attrs['domain']) ? strtolower(ltrim($attrs['domain'], '.')) : $host;
            if ($domain !== $host && (strpos($domain, '.') === false || substr($host, -strlen('.' . $domain)) !== '.' . $domain)) { continue; }
            $url_path = wp_parse_url($url, PHP_URL_PATH) ?: '/';
            $path = isset($attrs['path']) && is_string($attrs['path']) && strpos($attrs['path'], '/') === 0 ? $attrs['path'] : (strrpos($url_path, '/') > 0 ? substr($url_path, 0, strrpos($url_path, '/')) : '/');
            $expires = null;
            $status = 'session';
            if (isset($attrs['max-age']) && is_string($attrs['max-age']) && preg_match('/^-?\d{1,10}$/', $attrs['max-age'])) {
                $expires = time() + (int) $attrs['max-age']; $status = 'persistent';
            } elseif (isset($attrs['expires']) && is_string($attrs['expires'])) {
                $expires = strtotime($attrs['expires']); $status = $expires === false ? 'unknown' : 'persistent';
            }
            if ($expires !== null && $expires !== false && $expires <= time()) { continue; }
            $result[] = array('cookie_name' => $name, 'cookie_domain' => $domain, 'cookie_path' => $path, 'cookie_expiration' => $expires ? gmdate('Y-m-d H:i:s', $expires) : null,
                'cookie_expiration_status' => $status, 'secure' => isset($attrs['secure']), 'http_only' => isset($attrs['httponly']), 'same_site' => isset($attrs['samesite']) && is_string($attrs['samesite']) ? strtolower($attrs['samesite']) : 'unknown', 'scope_known' => true);
        }
        return $result;
    }

    private function cookie_key($cookie) {
        return hash('sha256', $cookie['cookie_name'] . "\0" . strtolower(ltrim($cookie['cookie_domain'], '.')) . "\0" . $cookie['cookie_path']);
    }

    private function observe(&$job, $cookie, $url, $phase, $method) {
        $key = $this->cookie_key($cookie);
        if (!isset($job['cookies'][$key]) && count($job['cookies']) >= 5000) {
            if (empty($job['cookie_limit_reached'])) { $this->issue($job, $url, 'Limite di 5000 cookie distinti raggiunto: inventario parziale.'); $job['cookie_limit_reached'] = true; }
            return;
        }
        if (!isset($job['cookies'][$key])) { $job['cookies'][$key] = $cookie + array('observations' => array(), 'observation_count' => 0, 'resources' => array()); }
        $entry = &$job['cookies'][$key];
        if (isset($cookie['cookie_expiration_status']) && $cookie['cookie_expiration_status'] !== 'unknown') {
            foreach (array('cookie_expiration', 'cookie_expiration_status', 'secure', 'http_only', 'same_site', 'scope_known') as $field) {
                if (array_key_exists($field, $cookie)) { $entry[$field] = $cookie[$field]; }
            }
        }
        $observation = array('url' => $url, 'phase' => $phase, 'method' => $method);
        if (!in_array($observation, $entry['observations'], true)) {
            $entry['observation_count']++;
            if (count($entry['observations']) < 50) { $entry['observations'][] = $observation; }
        }
    }

    private function commit(&$job) {
        $scanner = Marrison_Cookie_Scanner::get_instance();
        if (!$scanner->engine_store(array_values($job['cookies']), $job['saved_categories'], $job['status'] === 'completed')) {
            $job['status'] = 'failed';
            $this->issue($job, '', $scanner->get_last_scan_error());
            try { $this->write_job($job); } catch (Throwable $error) { /* Preserve the original storage failure. */ }
            throw new RuntimeException($scanner->get_last_scan_error());
        }
        update_option('marrison_cookie_last_scan', time());
    }

    private function signature($job, $url, $phase) {
        return hash_hmac('sha256', $job['id'] . "\n" . $job['expires'] . "\n" . $url . "\n" . $phase, wp_salt('nonce'));
    }

    private function browser_next($job) {
        $count = count($job['pages']);
        if (!$count || $job['status'] !== 'browser' || $job['browser_cursor'] >= $count * 2) { return null; }
        $phase = $job['browser_cursor'] < $count ? 'before' : 'after';
        $url = $job['pages'][$job['browser_cursor'] % $count];
        return array('url' => $url, 'phase' => $phase, 'token' => $this->signature($job, $url, $phase), 'frame_url' => add_query_arg(array('marrison_scan' => $job['id'], 'marrison_scan_phase' => $phase, 'marrison_scan_token' => $this->signature($job, $url, $phase)), $url));
    }

    private function summary($job) {
        if (!$job) { return null; }
        return array('id' => $job['id'], 'status' => $job['status'], 'created' => $job['created'], 'updated' => $job['updated'], 'limit' => $job['limit'],
            'limit_reached' => $job['limit_reached'], 'queued' => count($job['queue']), 'http_done' => $job['http_done'], 'http_failed' => $job['http_failed'],
            'browser_done' => $job['browser_cursor'], 'browser_total' => count($job['pages']) * 2, 'browser_failed' => $job['browser_failed'],
            'cookie_count' => count($job['cookies']), 'issues' => $job['issues'], 'issues_total' => $job['issues_total'], 'next' => $this->browser_next($job),
            'coverage' => 'Cookie HTTP del sito e cookie JavaScript leggibili dal browser. I cookie HttpOnly di domini terzi non sono accessibili dal plugin e non vengono inventati.');
    }

    public function ajax_browser() {
        $this->respond(function() {
            return $this->locked(function() {
                $job = $this->require_job();
                $next = $this->browser_next($job);
                $raw = isset($_POST['result']) && is_string($_POST['result']) ? wp_unslash($_POST['result']) : '';
                if (!$next || strlen($raw) > 262144) { throw new RuntimeException('Risultato browser non previsto.'); }
                $data = json_decode($raw, true);
                if (!is_array($data) || !isset($data['url'], $data['phase'], $data['token']) || $data['url'] !== $next['url'] || $data['phase'] !== $next['phase'] || !hash_equals($next['token'], $data['token'])) { throw new RuntimeException('Risultato browser non corrispondente alla pagina attesa.'); }
                if (!empty($data['error'])) {
                    $job['browser_failed']++;
                    $this->issue($job, $next['url'], sanitize_text_field($data['error']));
                } else {
                    if (isset($data['cookies']) && is_array($data['cookies']) && count($data['cookies']) > 200) { $this->issue($job, $next['url'], 'Oltre 200 cookie leggibili nella pagina: risultato browser parziale.'); }
                    foreach (array_slice(isset($data['cookies']) && is_array($data['cookies']) ? $data['cookies'] : array(), 0, 200) as $cookie) {
                        $cookie = $this->validate_browser_cookie($cookie, $next['url']);
                        if ($cookie) { $this->observe($job, $cookie, $next['url'], $next['phase'], 'browser'); }
                    }
                }
                $job['browser_cursor']++;
                $job['updated'] = time();
                if ($job['browser_cursor'] >= count($job['pages']) * 2) {
                    $job['status'] = $job['http_failed'] || $job['browser_failed'] || $job['limit_reached'] || $job['issues_total'] ? 'partial' : 'completed';
                }
                $this->commit($job);
                $this->write_job($job);
                return array('job' => $this->summary($job));
            });
        });
    }

    private function validate_browser_cookie($cookie, $url) {
        if (!is_array($cookie) || !isset($cookie['cookie_name'], $cookie['cookie_domain'], $cookie['cookie_path']) || !is_string($cookie['cookie_name']) || !preg_match('/^[!#$%&\'*+.^_`|~0-9a-z-]{1,255}$/i', $cookie['cookie_name'])) { return false; }
        $host = strtolower(wp_parse_url($url, PHP_URL_HOST));
        $domain = is_string($cookie['cookie_domain']) ? strtolower(ltrim($cookie['cookie_domain'], '.')) : '';
        if ($domain !== $host && (strpos($domain, '.') === false || substr($host, -strlen('.' . $domain)) !== '.' . $domain)) { return false; }
        if (!is_string($cookie['cookie_path']) || strlen($cookie['cookie_path']) > 255 || strpos($cookie['cookie_path'], '/') !== 0) { return false; }
        if (preg_match('/[\x00-\x20;\\\\]/', $cookie['cookie_path'])) { return false; }
        $expires = isset($cookie['expires']) && is_numeric($cookie['expires']) ? (float) $cookie['expires'] : null;
        if ($expires !== null && (!is_finite($expires) || $expires <= time() * 1000 || $expires > 253402300799000)) { return false; }
        return array('cookie_name' => $cookie['cookie_name'], 'cookie_domain' => $domain, 'cookie_path' => $cookie['cookie_path'],
            'cookie_expiration' => $expires !== null ? gmdate('Y-m-d H:i:s', (int) ($expires / 1000)) : null,
            'cookie_expiration_status' => !empty($cookie['scope_known']) ? ($expires !== null ? 'persistent' : 'session') : 'unknown',
            'secure' => !empty($cookie['secure']), 'http_only' => false, 'same_site' => isset($cookie['same_site']) && in_array($cookie['same_site'], array('strict', 'lax', 'none'), true) ? $cookie['same_site'] : 'unknown', 'scope_known' => !empty($cookie['scope_known']));
    }

    private function frame_context() {
        if (empty($_GET['marrison_scan']) || !is_string($_GET['marrison_scan'])) { return null; }
        try { $job = $this->read_job(); } catch (Throwable $error) { return null; }
        $next = $job && $job['expires'] >= time() ? $this->browser_next($job) : null;
        $token = isset($_GET['marrison_scan_token']) && is_string($_GET['marrison_scan_token']) ? wp_unslash($_GET['marrison_scan_token']) : '';
        $phase = isset($_GET['marrison_scan_phase']) && is_string($_GET['marrison_scan_phase']) ? wp_unslash($_GET['marrison_scan_phase']) : '';
        $home = wp_parse_url(home_url('/'));
        $origin = $home['scheme'] . '://' . $home['host'] . (isset($home['port']) ? ':' . $home['port'] : '');
        $actual = $this->normalize_url($origin . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/'), home_url('/'));
        if (!$next || $_GET['marrison_scan'] !== $job['id'] || $phase !== $next['phase'] || $actual !== $next['url'] || !hash_equals($next['token'], $token)) { return null; }
        return $next + array('job' => $job['id']);
    }

    public function frame_headers() {
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
    }

    public function frame_agent() {
        if (!$this->frame) { return; }
        $origin = wp_parse_url(home_url('/'), PHP_URL_SCHEME) . '://' . wp_parse_url(home_url('/'), PHP_URL_HOST) . (wp_parse_url(home_url('/'), PHP_URL_PORT) ? ':' . wp_parse_url(home_url('/'), PHP_URL_PORT) : '');
        $config = $this->frame + array('origin' => $origin, 'revision' => self::REVISION);
        echo '<script id="marrison-scan-config">window.marrisonScanFrame=' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
        $plugin = dirname(__DIR__, 4) . '/marrison-addon.php';
        echo '<script src="' . esc_url(plugins_url('includes/modules/cookie-manager/assets/js/scan-agent.js', $plugin)) . '?ver=' . esc_attr(self::REVISION) . '"></script>';
    }

    public function frame_phase() { return $this->frame ? $this->frame['phase'] : null; }

    public function start_scheduled_scan() {
        try {
            $this->locked(function() {
                $job = $this->read_job();
                if (!$job || !in_array($job['status'], array('http', 'browser'), true) || $job['expires'] < time()) { $this->write_job($this->new_job(500)); }
            });
            $this->scheduled_step();
        } catch (Throwable $error) { /* The next admin status request reports storage failures. */ }
    }

    public function scheduled_step() {
        try {
            $job = $this->locked(function() {
                $job = $this->read_job();
                if ($job && $job['status'] === 'http') { $this->http_step($job); $this->write_job($job); }
                return $job;
            });
            if ($job && $job['status'] === 'http' && !wp_next_scheduled('marrison_addon_cookie_engine_tick')) { wp_schedule_single_event(time() + 10, 'marrison_addon_cookie_engine_tick'); }
        } catch (Throwable $error) { /* No green completion is stored on failure. */ }
    }
}
