<?php
if (!defined('ABSPATH')) { exit; }
/** Representative same-site HTTP samples and isolated browser measurements. */
require_once __DIR__ . '/class-scan-sampler.php';
require_once __DIR__ . '/class-scan-services.php';
require_once __DIR__ . '/class-static-scanner.php';
class Marrison_Addon_Cookie_Scan_Engine {
    const PROTOCOL = 2;
    const REVISION = '20261027.3';
    private static $instance;
    private $frame = null;
    private $sampler = null;

    public static function get_instance() {
        if (!self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        foreach (array('start', 'step', 'state', 'resume', 'browser', 'inventory', 'cancel', 'mode', 'export') as $action) {
            add_action('wp_ajax_marrison_addon_cookie_engine_' . $action, array($this, 'ajax_' . $action));
        }
        add_action('marrison_addon_cookie_engine_tick', array($this, 'scheduled_step'));
        add_action('admin_notices', array($this, 'background_notice'));
        add_action('admin_notices', array($this, 'retired_notice'));
        $this->frame = $this->frame_context();
        if ($this->frame) {
            if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
            add_action('template_redirect', array($this, 'frame_headers'), -9999);
            add_action('wp_head', array($this, 'frame_agent'), -9999);
            add_action('shutdown', array($this, 'capture_frame_cookies'), PHP_INT_MAX);
            add_filter('wp_redirect', array($this, 'scan_redirect'), PHP_INT_MAX, 2);
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

    public function retired_notice() {
        if (!current_user_can('manage_options') || get_option('marrison_cookie_scan_mode', 'local') !== 'commander') { return; }
        update_option('marrison_cookie_scan_mode', 'local');
        echo '<div class="notice notice-info"><p>' . esc_html('La scansione remota è stata dismessa. È attiva la scansione locale dei servizi con catalogo; nessun lavoro remoto viene avviato.') . '</p></div>';
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
            // Deprecated remote implementation below is intentionally unreachable.
            if (false && isset($_POST['mode']) && $_POST['mode'] === 'commander') {
                $check = wp_safe_remote_get($this->commander_endpoint(), array('timeout' => 8, 'redirection' => 0, 'limit_response_size' => 4096));
                $configuration = is_wp_error($check) ? null : json_decode(wp_remote_retrieve_body($check), true);
                if (is_wp_error($check) || wp_remote_retrieve_response_code($check) !== 200 || empty($configuration['enabled']) || ($configuration['protocol'] ?? null) !== 1) {
                    throw new RuntimeException('Il browser remoto non è ancora attivo su Commander. Installa il pacchetto scanner e abilita Browserless nelle sue impostazioni.');
                }
            }
            $result = $this->locked(function() {
                $limit = isset($_POST['limit']) && is_scalar($_POST['limit']) ? max(0, min(10, (int) $_POST['limit'])) : 0;
                if (isset($_POST['additional_urls']) && is_string($_POST['additional_urls']) && strlen($_POST['additional_urls']) > 20000) { throw new RuntimeException('Elenco delle pagine aggiuntive troppo lungo.'); }
                $additional = isset($_POST['additional_urls']) && is_string($_POST['additional_urls']) ? preg_split('/\R/', wp_unslash($_POST['additional_urls'])) : array();
                $mode = 'local';
                $browser = in_array($_POST['browser'] ?? null, array('1', 1), true);
                $seconds = isset($_POST['max_seconds']) && is_scalar($_POST['max_seconds']) ? (int) $_POST['max_seconds'] : 0;
                $job = $this->new_job($limit, $additional, $mode, $browser, $seconds);
                if (isset($_POST['mode'])) { update_option('marrison_cookie_scan_mode', $mode); }
                $this->write_job($job);
                return array('job' => $this->summary($job));
            });
            $this->wake_background();
            if (!wp_next_scheduled('marrison_addon_cookie_engine_tick')) { wp_schedule_single_event(time() + 10, 'marrison_addon_cookie_engine_tick'); }
            return $result;
        });
    }

    public function ajax_state() {
        $this->respond(function() {
            return array('job' => $this->summary($this->read_job()));
        });
    }

    public function ajax_mode() {
        $this->respond(function() {
            $mode = isset($_POST['mode']) && is_string($_POST['mode']) ? $_POST['mode'] : '';
            if ($mode === 'commander') { $mode = 'local'; }
            if (!in_array($mode, array('local', 'commander'), true)) { throw new RuntimeException('Metodo di scansione non valido.'); }
            return $this->locked(function() use ($mode) {
                $job = $this->read_job();
                $interrupted = $job && in_array($job['status'], array('http', 'browser'), true);
                if ($interrupted) {
                    $job['status'] = 'cancelled';
                    $job['updated'] = time();
                    $this->write_job($job);
                    wp_clear_scheduled_hook('marrison_addon_cookie_engine_tick');
                }
                update_option('marrison_cookie_scan_mode', $mode);
                return array('job' => $this->summary($job), 'interrupted' => (bool) $interrupted);
            });
        });
    }

    public function ajax_resume() {
        $this->respond(function() {
            $this->locked(function() {
                $job = $this->require_job();
                if (isset($job['static_scan']) && empty($job['static_scan']['done'])) {
                    $job['status'] = 'http'; unset($job['static_scan']['memory_limited']); $this->write_job($job);
                }
                if (!$this->background_active($job)) { return; }
                $job['worker']['state'] = 'queued';
                $job['worker']['error'] = '';
                $job['worker']['dispatch_at'] = 0;
                $job['worker']['dispatch_failures'] = 0;
                unset($job['remote_error']);
                $this->write_job($job);
            });
            $this->wake_background();
            return array('job' => $this->summary($this->read_job()));
        });
    }

    public function ajax_step() {
        $this->respond(function() {
            return $this->locked(function() {
                $job = $this->require_job();
                $deadline = microtime(true) + 4;
                for ($step = 0; $step < 3 && $job['status'] === 'http'; $step++) {
                    $this->http_step($job);
                    if (microtime(true) >= $deadline) { break; }
                }
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
                wp_clear_scheduled_hook('marrison_addon_cookie_engine_tick');
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
            if ($job) { unset($job['saved_categories'], $job['remote_proof'], $job['remote_claim']); }
            return array('report' => $job);
        });
    }

    private function require_job() {
        $job = $this->read_job();
        $id = isset($_POST['job']) && is_string($_POST['job']) ? wp_unslash($_POST['job']) : '';
        if (!$job || !hash_equals($job['id'], $id)) { throw new RuntimeException('La scansione è cambiata. Ricarica lo stato.'); }
        if ($this->legacy_job($job)) { throw new RuntimeException('Il rapporto appartiene al metodo precedente. Avvia una nuova scansione locale per sostituire il vecchio inventario.'); }
        if ($job['expires'] < time()) { throw new RuntimeException('Scansione scaduta. Avvia una nuova scansione.'); }
        return $job;
    }

    private function new_job($limit, $additional = array(), $mode = 'local', $browser = null, $seconds = null) {
        $additional = array_values(array_filter(array_map('trim', $additional), 'strlen'));
        if (count($additional) > 50) { throw new RuntimeException('Puoi indicare al massimo 50 pagine aggiuntive.'); }
        $extra = array();
        foreach ($additional as $url) {
            if (trim($url) === '') { continue; }
            $url = $this->normalize_url(trim($url), home_url('/'));
            if (!$url) { throw new RuntimeException('Una delle pagine aggiuntive non è un URL pubblico valido dello stesso sito.'); }
            $extra[$url] = true;
        }
        $scanner = Marrison_Cookie_Scanner::get_instance();
        $categories = $scanner->engine_prepare();
        if ($categories === false) { throw new RuntimeException($scanner->get_last_scan_error()); }
        $previous = $this->read_job();
        if ($previous && isset($previous['saved_categories'])) { $categories = array_replace($previous['saved_categories'], $categories); }
        $home = $this->normalize_url(home_url('/'), home_url('/'));
        if (!$home) { throw new RuntimeException('URL del sito non valido.'); }
        $job = array('id' => bin2hex(random_bytes(16)), 'revision' => self::REVISION, 'plugin_version' => class_exists('Marrison_Addon') ? Marrison_Addon::VERSION : '', 'status' => 'http', 'created' => time(), 'updated' => time(), 'expires' => time() + 24 * 3600,
            'mode' => $mode, 'remote_proof' => bin2hex(random_bytes(32)), 'remote_requests' => array(), 'storage' => array(), 'storage_supported' => true,
            'worker' => array('state' => 'queued', 'lease_until' => 0, 'dispatch_at' => 0, 'error' => '', 'initiator' => function_exists('get_current_user_id') ? get_current_user_id() : 0),
            'limit' => $limit, 'limit_reached' => false, 'queue' => array(), 'seen' => array(), 'pages' => array(), 'http_done' => 0, 'http_failed' => 0,
            'browser_cursor' => 0, 'browser_failed' => 0, 'cookies' => array(), 'services' => array(), 'issues' => array(), 'issues_total' => 0, 'saved_categories' => $categories,
            'sampling' => array('strategy' => 'templates', 'candidates' => 0, 'equivalents' => 0, 'excluded' => 0, 'groups' => array(), 'unselected' => array()), 'additional_urls' => $extra);
        $job['mode'] = 'local';
        $job['static_scan'] = array('page' => 1, 'posts' => 0, 'done' => false);
        $job['local_iframe'] = $browser === null ? (bool) get_option('marrison_cookie_local_iframe', false) : (bool) $browser;
        $seconds = (int) ($seconds === null ? get_option('marrison_cookie_local_max_seconds', 30) : $seconds);
        $job['local_max_seconds'] = $seconds === 0 ? 0 : max(10, min(120, $seconds));
        if ($job['local_iframe']) {
            $this->sampler = new Marrison_Addon_Cookie_Scan_Sampler();
            $this->enqueue($job, $home, 'page');
            foreach (array_keys($job['additional_urls']) as $url) { $this->enqueue($job, $url, 'page'); }
            foreach ($this->sampler->seeds() as $url) { $this->enqueue($job, $url, 'page'); }
            $job['pages'] = array_column($job['queue'], 'url');
        }
        $job['queue'] = array();
        // A new run replaces the cookie inventory; consent receipts and settings are separate.
        if (!$scanner->engine_store(array(), $categories, true)) { throw new RuntimeException($scanner->get_last_scan_error()); }
        return $job;
    }

    public function start_local_scan() {
        return $this->locked(function() {
            $job = $this->new_job(0);
            $this->http_step($job); $this->write_job($job);
            if ($job['status'] === 'http' && !wp_next_scheduled('marrison_addon_cookie_engine_tick')) { wp_schedule_single_event(time() + 10, 'marrison_addon_cookie_engine_tick'); }
            return count(Marrison_Addon_Cookie_Static_Scanner::declared_cookies($job['services']));
        });
    }

    /** Same-origin URL normalization also excludes administrative/action endpoints. */
    public function normalize_url($url, $base, $kind = 'page') {
        if (!is_string($url) || preg_match('/[\x00-\x20\\\\]/', $url)) { return false; }
        $url = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');
        if (preg_match('/\{\{|\}\}|\$\{|<%|%>|(?:^|\/)itemDataObject\./i', rawurldecode($url))) { return false; }
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
        $allowed_query = array('p', 'page_id', 'paged', 'page', 'pagename', 'lang', 'product', 'product_cat', 'post_type', 'taxonomy', 'term', 'cat', 'tag', 'category_name', 's');
        if (function_exists('get_taxonomies')) {
            foreach (get_taxonomies(array('public' => true, 'publicly_queryable' => true), 'objects') as $taxonomy) {
                if (!empty($taxonomy->query_var) && is_string($taxonomy->query_var)) { $allowed_query[] = $taxonomy->query_var; }
            }
        }
        $query = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach ($query as $key => $value) {
                if (strpos($key, 'utm_') === 0 || in_array($key, array('gclid', 'fbclid', 'marrison_scan', 'marrison_scan_phase', 'marrison_scan_token'), true)) { unset($query[$key]); continue; }
                if (!in_array($key, $allowed_query, true) || !is_scalar($value) || strlen((string) $value) > 200) { return false; }
            }
            ksort($query);
        }
        return $home['scheme'] . '://' . strtolower($home['host']) . (isset($home['port']) ? ':' . $home['port'] : '') . $path . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }

    private function enqueue(&$job, $url, $kind, $base = '') {
        $url = $this->normalize_url($url, $base ?: home_url('/'), $kind);
        if (!$url || isset($job['seen'][$url])) { return; }
        if (count($job['seen']) >= 10000) {
            if (empty($job['discovery_limit'])) { $this->issue($job, '', 'Limite di 10000 URL da raggruppare raggiunto: copertura parziale.'); $job['discovery_limit'] = true; }
            $job['limit_reached'] = true; return;
        }
        $job['seen'][$url] = true;
        $item = array('url' => $url, 'kind' => $kind);
        if ($kind === 'page' && isset($job['sampling'])) {
            $job['sampling']['candidates']++;
            if (!$this->sampler) { $this->sampler = new Marrison_Addon_Cookie_Scan_Sampler(); }
            $sample = !empty($job['additional_urls'][$url]) ? array('key' => 'additional:' . $url, 'kind' => 'additional', 'label' => 'Pagina aggiuntiva') : $this->sampler->describe($url);
            if (!is_array($sample) || !isset($sample['key'], $sample['kind'], $sample['label'])) { $job['sampling']['excluded']++; return; }
            $key = hash('sha256', $sample['key']); unset($sample['key']);
            if (isset($job['sampling']['groups'][$key])) {
                $group = &$job['sampling']['groups'][$key]; $group['represented_urls']++;
                if (count($group['examples']) < 3) { $group['examples'][] = $url; }
                $job['sampling']['equivalents']++; return;
            }
            if ($job['limit'] > 0 && count($job['sampling']['groups']) >= $job['limit']) {
                if (!$job['sampling']['unselected']) { $this->issue($job, '', 'Limite di ' . $job['limit'] . ' campioni raggiunto: altri gruppi non sono stati visitati.'); }
                $job['sampling']['unselected'][$key] = true; $job['limit_reached'] = true; return;
            }
            $job['sampling']['groups'][$key] = $sample + array('url' => $url, 'represented_urls' => 1, 'examples' => array($url), 'status' => 'queued');
            $item['sample_key'] = $key;
        }
        $job['queue'][] = $item;
    }

    private function issue(&$job, $url, $message) {
        $job['issues_total']++;
        if (count($job['issues']) < 100) { $job['issues'][] = array('url' => $url, 'message' => $message); }
    }

    private function http_step(&$job) {
        if ($job['status'] !== 'http') { return; }
        if (isset($job['static_scan'])) {
            (new Marrison_Addon_Cookie_Static_Scanner())->step($job['static_scan'], $job['services'], function($post, $id) use (&$job) {
                if (empty($job['local_iframe']) || empty($post->post_type) || !function_exists('get_permalink')
                    || in_array($post->post_type, array('attachment', 'elementor_library'), true)
                    || (function_exists('is_post_type_viewable') && !is_post_type_viewable($post->post_type))) { return; }
                // Every published route participates in grouping, not just the newest post of each type.
                $this->enqueue($job, get_permalink($id), 'page');
            });
            foreach ($job['queue'] as $item) { if (!in_array($item['url'], $job['pages'], true)) { $job['pages'][] = $item['url']; } }
            $job['queue'] = array();
            $job['http_done'] = $job['static_scan']['posts']; $job['updated'] = time();
            if (!empty($job['static_scan']['memory_limited'])) {
                $job['status'] = 'partial'; $this->issue($job, '', 'Limite di memoria: scansione statica sospesa e riprendibile.');
            } elseif (!empty($job['static_scan']['done'])) {
                $incomplete = !empty($job['static_scan']['skipped']) || !empty($job['static_scan']['config_incomplete']);
                if ($incomplete) {
                    $details = $job['static_scan']['details'] ?? array();
                    if (!$details) { $this->issue($job, '', 'Inventario statico parziale: alcuni dati non sono stati analizzati.'); }
                    foreach ($details as $detail) {
                        $source = (string) ($detail['source'] ?? '');
                        $url = '';
                        if (preg_match('/^post:(\d+):/', $source, $match) && function_exists('get_permalink')) { $url = (string) get_permalink((int) $match[1]); }
                        $this->issue($job, $url, 'Analisi statica parziale — ' . $source . ': ' . ($detail['reason'] ?? 'Dato non analizzabile.'));
                    }
                }
                $job['status'] = !empty($job['local_iframe']) && $job['pages'] ? 'browser' : ($incomplete ? 'partial' : 'completed');
                $job['local_started'] = time();
            }
            $this->commit($job);
            if ($job['status'] === 'completed') { do_action('marrison_addon/cookie_scan_finished', $job); }
            return;
        }
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
        $missing = $item['kind'] === 'page' && in_array($code, array(404, 410), true) && $item['url'] !== $this->normalize_url(home_url('/'), home_url('/'));
        if (is_wp_error($response) || $code < 200 || $code >= 300) {
            if ($missing) {
                if (!isset($job['skipped_missing'])) { $job['skipped_missing'] = array(); }
                if (count($job['skipped_missing']) < 100) { $job['skipped_missing'][] = array('url' => $url, 'http_status' => $code); }
            } elseif ($item['kind'] === 'page') { $job['http_failed']++; $this->issue($job, $url, is_wp_error($response) ? $response->get_error_message() : 'Risposta HTTP ' . $code); }
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
                foreach ($this->html_elements($body) as $element) {
                    if ($element['tag'] === 'a') { $this->enqueue($job, $element['url'], 'page', $url); }
                    else { $this->observe_service($job, $element['url'], $element['tag'], $url); }
                }
            }
        }
        if (isset($item['sample_key'], $job['sampling']['groups'][$item['sample_key']])) {
            $group = &$job['sampling']['groups'][$item['sample_key']];
            $group['status'] = $missing ? 'skipped_missing' : (in_array($url, $job['pages'], true) ? 'visited' : 'failed');
            $group['final_url'] = $url;
        }
        $job['updated'] = time();
        if (!$job['queue']) {
            $job['status'] = $job['pages'] ? 'browser' : 'partial';
            $this->commit($job);
        }
    }

    /** Ignore inert templates and script text; :href/data-href are not navigable links. */
    private function html_elements($body) {
        $body = preg_replace('#<!--.*?-->|<(template|textarea)\b[^>]*>.*?</\1\s*>#is', '', $body);
        $body = preg_replace('#(<(script|style)\b[^>]*>).*?</\2\s*>#is', '$1', $body);
        $elements = array();
        if (preg_match_all('/<(a|script|iframe|img)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/is', $body, $tags, PREG_SET_ORDER)) {
            foreach ($tags as $tag) {
                $name = strtolower($tag[1]);
                $attribute = $name === 'a' ? 'href' : '(?:src|data-src|data-marrison-blocked-src)';
                if (preg_match('/\s' . $attribute . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag[0], $value)) {
                    $resource = $value[1] ?? '';
                    if ($resource === '') { $resource = !empty($value[2]) ? $value[2] : ($value[3] ?? ''); }
                    $elements[] = array('tag' => $name, 'url' => html_entity_decode($resource, ENT_QUOTES, 'UTF-8'));
                }
            }
        }
        return $elements;
    }

    private function observe_service(&$job, $resource, $kind, $page) {
        if (!is_string($resource) || !in_array($kind, array('script', 'iframe', 'img', 'style', 'font', 'request', 'resource'), true)) { return; }
        if (strpos($resource, '//') === 0) { $resource = 'https:' . $resource; }
        $parts = wp_parse_url($resource);
        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
        if (!$host || !in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true) || $host === strtolower(wp_parse_url(home_url('/'), PHP_URL_HOST))) { return; }
        if (!isset($job['services'][$host]) && count($job['services']) >= 100) { return; }
        if (!isset($job['services'][$host])) { $job['services'][$host] = array('host' => $host, 'kinds' => array(), 'pages' => array(), 'cookies_verified' => false); }
        $entry = &$job['services'][$host];
        foreach (Marrison_Addon_Cookie_Scan_Services::identify($resource) as $profile) { $entry['profiles'][$profile['id']] = $profile; }
        if (!in_array($kind, $entry['kinds'], true)) { $entry['kinds'][] = $kind; }
        if (count($entry['pages']) < 10 && !in_array($page, $entry['pages'], true)) { $entry['pages'][] = $page; }
    }

    /** Set-Cookie values are deliberately discarded before any persistence. */
    public function http_cookies($response, $url) {
        $headers = wp_remote_retrieve_headers($response);
        $values = is_object($headers) && is_callable(array($headers, 'getValues')) ? $headers->getValues('set-cookie') : wp_remote_retrieve_header($response, 'set-cookie');
        if (is_string($values)) { $values = preg_split('/,(?=\s*[^=;,\s]+=)/', $values); }
        return $this->parse_cookie_headers($values, $url);
    }

    private function parse_cookie_headers($values, $url) {
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
        $records = array_merge(Marrison_Addon_Cookie_Static_Scanner::declared_cookies($job['services']), array_values($job['cookies']));
        // Every batch replaces the inventory with this job's cumulative results, including partial runs.
        if (!$scanner->engine_store($records, $job['saved_categories'], true)) {
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
        if (isset($job['local_started']) && ($job['local_max_seconds'] ?? 30) > 0 && time() - $job['local_started'] >= ($job['local_max_seconds'] ?? 30)) { return null; }
        if (($job['mode'] ?? 'local') === 'commander') { return array('url' => $job['pages'][(int) floor($job['browser_cursor'] / 2)], 'phase' => 'both'); }
        $phase = $job['browser_cursor'] < $count ? 'before' : 'after';
        $url = $job['pages'][$job['browser_cursor'] % $count];
        return array('url' => $url, 'phase' => $phase, 'token' => $this->signature($job, $url, $phase), 'frame_url' => add_query_arg(array('marrison_scan' => $job['id'], 'marrison_scan_phase' => $phase, 'marrison_scan_token' => $this->signature($job, $url, $phase)), $url));
    }

    private function legacy_job($job) {
        return ($job['revision'] ?? '') !== self::REVISION || ($job['mode'] ?? 'local') !== 'local'
            || !is_array($job['static_scan'] ?? null);
    }

    private function summary($job) {
        if (!$job) { return null; }
        $stale = $this->legacy_job($job);
        if ($stale && in_array($job['status'], array('http', 'browser'), true)) { $job['status'] = 'cancelled'; }
        $browser_enabled = !$stale && !empty($job['local_iframe']);
        $issues = $job['issues'];
        // Older static-only jobs selected samples even though no browser visits were requested.
        if (!$stale && !$browser_enabled) {
            $issues = array_values(array_filter($issues, function($issue) {
                return !preg_match('/^Limite di [0-9]+ campioni raggiunto:/', $issue['message'] ?? '');
            }));
        }

        return array('revision' => $job['revision'] ?? '', 'mode' => $job['mode'] ?? 'local', 'remote_requests' => count($job['remote_requests'] ?? array()), 'stale' => $stale, 'id' => $job['id'], 'status' => $job['status'], 'created' => $job['created'], 'updated' => $job['updated'], 'limit' => $job['limit'],
            'limit_reached' => $browser_enabled && $job['limit_reached'], 'queued' => count($job['queue']), 'http_done' => $job['http_done'], 'http_failed' => $job['http_failed'],
            'static_posts' => $stale ? null : (int) ($job['static_scan']['posts'] ?? 0), 'local_iframe' => $browser_enabled,
            'browser_done' => $browser_enabled ? $job['browser_cursor'] : 0, 'browser_total' => $browser_enabled ? count($job['pages']) * 2 : 0, 'browser_failed' => $job['browser_failed'],
            'response_pages' => count($job['response_pages'] ?? array()), 'skipped_missing' => $job['skipped_missing'] ?? array(),
            'remote_error' => $job['remote_error'] ?? '',
            'background' => false, 'worker' => $job['worker'] ?? array(),
            'static_pending' => !$stale && empty($job['static_scan']['done']),
            'static_source' => $stale ? '' : ($job['static_scan']['current_source'] ?? ''),
            'static_details' => $stale ? array() : ($job['static_scan']['details'] ?? array()),
            'storage' => array_values($job['storage'] ?? array()), 'storage_count' => count($job['storage'] ?? array()), 'storage_supported' => $job['storage_supported'] ?? false,
            'sampling' => $browser_enabled && isset($job['sampling']) ? array('strategy' => 'templates', 'candidates' => $job['sampling']['candidates'], 'equivalents' => $job['sampling']['equivalents'],
                'selected' => count($job['sampling']['groups']), 'unselected' => count($job['sampling']['unselected']), 'groups' => array_values($job['sampling']['groups'])) : null,
            'cookie_count' => count($job['cookies']), 'documented_cookie_count' => $this->documented_cookie_count($job), 'services' => $this->display_services($job['services'] ?? array()), 'issues' => $issues, 'issues_total' => max(0, $job['issues_total'] - count($job['issues']) + count($issues)), 'next' => $stale || !$browser_enabled ? null : $this->browser_next($job),
            'coverage' => $stale
                ? 'Rapporto del metodo precedente. Avvia una nuova scansione locale per sostituire il vecchio inventario.'
                : 'Scansione statica dei servizi e cookie dichiarati dal catalogo.' . (!empty($job['local_iframe']) ? ' Verifica iframe opzionale: non vede i cookie di terze parti.' : ' Nessuna navigazione del browser richiesta.'));
    }

    private function documented_cookie_count($job) {
        $names = array();
        foreach ($job['services'] ?? array() as $service) {
            foreach ($service['profiles'] ?? array() as $profile) {
                foreach ($profile['cookies'] as $cookie) { $names[$profile['id'] . ':' . $cookie['name']] = true; }
            }
        }
        return count($names);
    }

    private function display_services($services) {
        $catalog = Marrison_Addon_Cookie_Scan_Services::catalog();
        foreach ($services as &$service) {
            $service['category'] = Marrison_Addon_Cookie_Scan_Services::category(array('id' => Marrison_Addon_Cookie_Scan_Services::host_id($service['host']), 'category' => 'unclassified'));
            foreach ($service['profiles'] ?? array() as $id => $profile) {
                if (isset($catalog[$id])) {
                    // Refresh review metadata for old reports without rewriting their observed records.
                    foreach (array('verified', 'last_reviewed', 'notes', 'provider') as $field) {
                        if (isset($catalog[$id][$field])) { $service['profiles'][$id][$field] = $catalog[$id][$field]; }
                    }
                }
            }
        }
        unset($service);
        return array_values($services);
    }

    public function service_inventory() {
        try { $job = $this->read_job(); return array_values($job['services'] ?? array()); }
        catch (Throwable $error) { return array(); }
    }

    public function storage_inventory() {
        try { $job = $this->read_job(); return array_values($job['storage'] ?? array()); }
        catch (Throwable $error) { return array(); }
    }

    public function technical_only() {
        try { $job = $this->read_job(); } catch (Throwable $error) { return false; }
        if (!$job || $this->legacy_job($job) || $job['status'] !== 'completed' || empty($job['static_scan']['done'])) { return false; }
        foreach (Marrison_Addon_Cookie_Scan_Services::discovered_hosts() as $category) { if ($category !== 'necessary') { return false; } }
        foreach (array('functional', 'analytics', 'marketing') as $category) {
            if (trim((string) get_option('marrison_cookie_' . $category . '_sources', '')) !== '') { return false; }
        }
        foreach ($job['services'] as $service) {
            if (empty($service['profiles'])) {
                if (Marrison_Addon_Cookie_Scan_Services::category(array('id' => Marrison_Addon_Cookie_Scan_Services::host_id($service['host']), 'category' => 'unclassified')) !== 'necessary') { return false; }
            }
            foreach ($service['profiles'] ?? array() as $profile) { if (Marrison_Addon_Cookie_Scan_Services::category($profile) !== 'necessary') { return false; } }
        }
        foreach (Marrison_Cookie_Scanner::get_instance()->get_cookies('all') as $cookie) { if ($cookie->cookie_category !== 'necessary') { return false; } }
        return true;
    }

    public function register_scan_proof() {
        return; // Deprecated: remote proof route is no longer exposed.
        register_rest_route('marrison-cookie/v1', '/scan-proof', array('methods' => 'GET', 'permission_callback' => '__return_false',
            'callback' => array($this, 'scan_proof')));
    }

    /** Expiring proof of an administrator-started scan, without a Commander license. */
    public function scan_proof($request) {
        return new WP_Error('scan_proof_retired', 'Scansione remota dismessa.', array('status' => 410)); // Deprecated below.
        try {
            $job = $this->read_job();
            $id = $request->get_param('job'); $proof = $request->get_param('proof'); $url = $request->get_param('url');
            $next = $job ? $this->browser_next($job) : null;
            if (!$job || ($job['mode'] ?? '') !== 'commander' || ($job['revision'] ?? '') !== self::REVISION || $job['expires'] < time()
                || !is_string($id) || !is_string($proof) || !is_string($url) || !hash_equals($job['id'], $id)
                || !hash_equals($job['remote_proof'] ?? '', $proof) || !$next || $next['url'] !== $url) {
                return new WP_Error('scan_proof_invalid', 'Scansione non confermata.', array('status' => 403));
            }
            $response = new WP_REST_Response(array('proof_hash' => hash('sha256', $proof . "\n" . $url)));
            $response->header('Cache-Control', 'no-store');
            return $response;
        } catch (Throwable $error) { return new WP_Error('scan_proof_unavailable', 'Verifica della scansione non disponibile.', array('status' => 503)); }
    }

    public function ajax_remote() {
        throw new RuntimeException('Scansione remota dismessa. Avvia una scansione locale.'); // Deprecated implementation below.
        $this->respond(function() { return array('job' => $this->summary($this->remote_step())); });
    }

    private function commander_endpoint() {
        return apply_filters('marrison_addon/cookie_scan_commander_url', 'https://www.marrisonlab.com/commander/public/cookie-scan.php');
    }

    private function remote_step($scheduled = false, $expected_id = '') {
        throw new RuntimeException('Scansione remota dismessa.'); // Deprecated, unreachable.
        $claim = bin2hex(random_bytes(16));
        $work = $this->locked(function() use ($claim, $scheduled, $expected_id) {
            $job = $scheduled ? $this->read_job() : $this->require_job();
            if (!$job || ($job['mode'] ?? '') !== 'commander' || ($job['revision'] ?? '') !== self::REVISION || $job['expires'] < time()) { throw new RuntimeException('Scansione remota non disponibile.'); }
            if ($expected_id !== '' && $job['id'] !== $expected_id) { throw new RuntimeException('La scansione è cambiata.'); }
            $next = $this->browser_next($job);
            if (!$next) { throw new RuntimeException('Nessuna pagina remota da verificare.'); }
            if (!empty($job['remote_claim']) && $job['remote_claim']['expires'] > time()) { throw new RuntimeException('engine_busy'); }
            $job['remote_claim'] = array('id' => $claim, 'expires' => time() + 90);
            $this->write_job($job);
            return array('job' => $job['id'], 'url' => $next['url'], 'site_url' => home_url('/'), 'proof' => $job['remote_proof']);
        });
        try {
            $endpoint = $this->commander_endpoint();
            // No visitor cookies, WP login, Browserless token or custom scripts are sent.
            $response = wp_safe_remote_post($endpoint, array('timeout' => 55, 'redirection' => 0, 'limit_response_size' => 1048576,
                'headers' => array('Content-Type' => 'application/json', 'Accept' => 'application/json'), 'body' => wp_json_encode($work)));
            if (is_wp_error($response)) { throw new RuntimeException('Commander non raggiungibile: la scansione remota non è completata. Premi Riprendi per ritentare.'); }
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (wp_remote_retrieve_response_code($response) !== 200 || empty($body['success']) || !is_array($body['result'] ?? null)) {
                $message = is_array($body) && is_string($body['error'] ?? null) ? sanitize_text_field($body['error']) : 'Endpoint scanner Commander non disponibile. Verifica l’installazione e l’attivazione del servizio.';
                throw new RuntimeException($message);
            }
            $data = $body['result'];
            if (($data['protocol'] ?? null) !== 1 || ($data['engine'] ?? '') !== 'commander_browserless' || ($data['url'] ?? '') !== $work['url']
                || !is_array($data['cookies'] ?? null) || !is_array($data['resources'] ?? null) || !is_array($data['issues'] ?? null)
                || count($data['cookies']) > 1000 || count($data['resources']) > 300) { throw new RuntimeException('Risultato del browser remoto non valido.'); }
            return $this->locked(function() use ($work, $claim, $data) {
                $job = $this->read_job();
                if (!$job || $job['id'] !== $work['job'] || ($job['remote_claim']['id'] ?? '') !== $claim || $job['status'] !== 'browser') { throw new RuntimeException('La scansione è stata interrotta o sostituita.'); }
                $hosts = array(strtolower(wp_parse_url($work['url'], PHP_URL_HOST)));
                foreach ($data['resources'] as $resource) {
                    if (!is_array($resource) || !is_string($resource['url'] ?? null)) { continue; }
                    $host = wp_parse_url($resource['url'], PHP_URL_HOST);
                    if ($host) { $hosts[] = strtolower($host); }
                    $this->observe_service($job, $resource['url'], $resource['kind'] ?? 'resource', $work['url']);
                }
                foreach ($data['cookies'] as $record) {
                    if (!is_array($record) || !in_array($record['phase'] ?? '', array('before', 'after'), true) || !is_string($record['cookie_domain'] ?? null)) { continue; }
                    $domain = strtolower(ltrim($record['cookie_domain'], '.'));
                    if (!preg_match('/^[a-z0-9.-]+\.[a-z0-9-]+$/', $domain)) { continue; }
                    $associated = false;
                    foreach ($hosts as $host) { if ($host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain) { $associated = true; break; } }
                    if (!$associated) { continue; }
                    $candidate = $record;
                    $candidate['expires'] = isset($record['expires']) && is_numeric($record['expires']) ? (float) $record['expires'] * 1000 : null;
                    $candidate['scope_known'] = true;
                    $cookie = $this->validate_browser_cookie($candidate, 'https://' . $domain . '/');
                    if (!$cookie) { continue; }
                    $cookie['http_only'] = !empty($record['http_only']);
                    $cookie['partitioned'] = !empty($record['partitioned']);
                    $this->observe($job, $cookie, $work['url'], $record['phase'], 'remote_browser');
                }
                $job['storage_supported'] = !empty($job['storage_supported']) && isset($data['storage']) && is_array($data['storage']);
                foreach (array_slice(is_array($data['storage'] ?? null) ? $data['storage'] : array(), 0, 500) as $record) {
                    if (!is_array($record) || !is_string($record['key'] ?? null) || $record['key'] === '' || strlen($record['key']) > 255 || preg_match('/[\x00-\x1f\x7f]/', $record['key'])
                        || !in_array($record['type'] ?? '', array('localStorage', 'sessionStorage'), true) || !in_array($record['phase'] ?? '', array('before', 'after'), true) || !is_string($record['origin'] ?? null)) { continue; }
                    $parts = wp_parse_url($record['origin']);
                    if (!$parts || !in_array($parts['scheme'] ?? '', array('http', 'https'), true) || empty($parts['host']) || isset($parts['user']) || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment'])
                        || !in_array(strtolower($parts['host']), $hosts, true) || !empty($parts['path']) && $parts['path'] !== '/') { continue; }
                    $origin = $parts['scheme'] . '://' . strtolower($parts['host']);
                    $key = hash('sha256', $origin . "\n" . $record['type'] . "\n" . $record['key']);
                    if (!isset($job['storage'][$key])) {
                        if (count($job['storage']) >= 1000) { $this->issue($job, $work['url'], 'Limite di 1000 voci di archiviazione browser raggiunto.'); break; }
                        $job['storage'][$key] = array('key' => $record['key'], 'type' => $record['type'], 'origin' => $origin, 'domain' => strtolower($parts['host']), 'observations' => array());
                    }
                    $evidence = array('url' => $work['url'], 'phase' => $record['phase'], 'method' => 'remote_browser');
                    if (count($job['storage'][$key]['observations']) < 50 && !in_array($evidence, $job['storage'][$key]['observations'], true)) { $job['storage'][$key]['observations'][] = $evidence; }
                }
                $issues = array_slice($data['issues'], 0, 30);
                if (empty($data['consent_verified']) && !$issues) { $issues[] = 'Consenso nel browser remoto non confermato.'; }
                foreach ($issues as $issue) { if (is_string($issue)) { $this->issue($job, $work['url'], sanitize_text_field($issue)); } }
                if ($issues) { $job['browser_failed']++; }
                $job['remote_requests'][] = array('url' => $work['url'], 'id' => sanitize_text_field($data['request_id'] ?? ''), 'consent_verified' => !empty($data['consent_verified']));
                unset($job['remote_claim'], $job['remote_error']);
                $job['browser_cursor'] += 2; $job['updated'] = time();
                if ($job['browser_cursor'] >= count($job['pages']) * 2) { $job['status'] = $job['http_failed'] || $job['browser_failed'] || $job['limit_reached'] || $job['issues_total'] ? 'partial' : 'completed'; }
                $this->commit($job); $this->write_job($job);
                if (in_array($job['status'], array('completed', 'partial'), true)) { do_action('marrison_addon/cookie_scan_finished', $job); }
                return $job;
            });
        } catch (Throwable $error) {
            $this->locked(function() use ($work, $claim, $error) {
                $job = $this->read_job();
                if ($job && $job['id'] === $work['job'] && ($job['remote_claim']['id'] ?? '') === $claim) {
                    unset($job['remote_claim']); $job['remote_error'] = sanitize_text_field($error->getMessage()); $this->write_job($job);
                }
            });
            throw $error;
        }
    }

    public function ajax_browser() {
        $this->respond(function() {
            return $this->locked(function() {
                $job = $this->require_job();
                if (($job['mode'] ?? 'local') === 'commander') { throw new RuntimeException('Questa scansione richiede il browser remoto di Commander.'); }
                $next = $this->browser_next($job);
                if (!$next && $job['status'] === 'browser' && isset($job['static_scan'])) {
                    $job['status'] = 'partial'; $this->issue($job, '', 'Tempo massimo della verifica locale raggiunto; catalogo e risultati disponibili sono salvati.');
                    $this->commit($job); $this->write_job($job); return array('job' => $this->summary($job));
                }
                $raw = isset($_POST['result']) && is_string($_POST['result']) ? wp_unslash($_POST['result']) : '';
                if (!$next || strlen($raw) > 262144) { throw new RuntimeException('Risultato browser non previsto.'); }
                $data = json_decode($raw, true);
                if (!is_array($data) || !isset($data['url'], $data['phase'], $data['token']) || $data['url'] !== $next['url'] || $data['phase'] !== $next['phase'] || !hash_equals($next['token'], $data['token'])) { throw new RuntimeException('Risultato browser non corrispondente alla pagina attesa.'); }
                if (empty($data['error']) && ($data['storage_access'] ?? '') !== 'available') { $data['error'] = 'Il browser non permette di verificare i cookie nella sessione di scansione. Il risultato vuoto non è attendibile.'; }
                if (!empty($data['error'])) {
                    $job['browser_failed']++;
                    $this->issue($job, $next['url'], sanitize_text_field($data['error']));
                } else {
                    foreach (array_slice(isset($data['resources']) && is_array($data['resources']) ? $data['resources'] : array(), 0, 100) as $resource) {
                        if (is_array($resource)) { $this->observe_service($job, $resource['url'] ?? '', $resource['kind'] ?? '', $next['url']); }
                    }
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
                if (in_array($job['status'], array('completed', 'partial'), true)) { do_action('marrison_addon/cookie_scan_finished', $job); }
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
        if (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') !== 'iframe' || is_user_logged_in()) { return null; }
        try { $job = $this->read_job(); } catch (Throwable $error) { return null; }
        $next = $job && ($job['mode'] ?? 'local') === 'local' && ($job['revision'] ?? '') === self::REVISION && $job['expires'] >= time() ? $this->browser_next($job) : null;
        $token = isset($_GET['marrison_scan_token']) && is_string($_GET['marrison_scan_token']) ? wp_unslash($_GET['marrison_scan_token']) : '';
        $phase = isset($_GET['marrison_scan_phase']) && is_string($_GET['marrison_scan_phase']) ? wp_unslash($_GET['marrison_scan_phase']) : '';
        $home = wp_parse_url(home_url('/'));
        $origin = $home['scheme'] . '://' . $home['host'] . (isset($home['port']) ? ':' . $home['port'] : '');
        $actual = $this->normalize_url($origin . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/'), home_url('/'));
        if (!$next || $_GET['marrison_scan'] !== $job['id'] || $phase !== $next['phase'] || !hash_equals($next['token'], $token)) { return null; }
        if ($actual !== $next['url'] && !in_array($actual, $job['frame_redirects'][$token] ?? array(), true)) { return null; }
        return $next + array('job' => $job['id'], 'actual_url' => $actual);
    }

    /** Keep a verified anonymous scan attached to legitimate same-site redirects (e.g. an empty checkout). */
    public function scan_redirect($location, $status = 302) {
        if (!$this->frame || is_user_logged_in() || ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') !== 'iframe') { return $location; }
        $target = $this->normalize_url($location, $this->frame['actual_url'] ?? $this->frame['url']);
        if (!$target) { return $location; }
        try {
            return $this->locked(function() use ($location, $target) {
                $job = $this->read_job();
                $next = $job ? $this->browser_next($job) : null;
                if (!$next || !hash_equals($next['token'], $this->frame['token'])) { return $location; }
                $targets = $job['frame_redirects'][$next['token']] ?? array();
                if (count($targets) >= 8 || $target === ($this->frame['actual_url'] ?? $next['url'])) { return $location; }
                if (!in_array($target, $targets, true)) { $targets[] = $target; }
                $job['frame_redirects'][$next['token']] = $targets;
                // Preserve the effective destination in the exported report, rather than claiming checkout rendered.
                $key = $next['phase'] . ' ' . $next['url'];
                if (count($job['redirected_pages'] ?? array()) < 100 || isset($job['redirected_pages'][$key])) { $job['redirected_pages'][$key] = $targets; }
                $this->write_job($job);
                return add_query_arg(array('marrison_scan' => $job['id'], 'marrison_scan_phase' => $next['phase'], 'marrison_scan_token' => $next['token']), $target);
            });
        } catch (Throwable $error) { return $location; }
    }

    public function frame_headers() {
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        if (!$this->frame || $this->frame['phase'] !== 'after') { return; }
        try {
            if (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') !== 'iframe' || is_user_logged_in()) { throw new RuntimeException('Contesto di scansione non isolato.'); }
            $this->locked(function() {
                $job = $this->read_job();
                $next = $job ? $this->browser_next($job) : null;
                if (!$next || !hash_equals($next['token'], $this->frame['token'])) { throw new RuntimeException('La scansione è cambiata durante la verifica del consenso.'); }
                $store = Marrison_Addon_Cookie_Consent_Store::get_instance();
                $store->record('accept_all', array(), 'scan');
            });
        } catch (Throwable $error) { $this->frame['error'] = 'Salvataggio del consenso nella scansione non riuscito: ' . $error->getMessage(); }
    }

    /** Capture real response headers, including HttpOnly and late plugin cookies. */
    public function capture_frame_cookies($headers = null) {
        if (!$this->frame || !empty($this->frame['error']) || ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') !== 'iframe' || is_user_logged_in()) { return; }
        $values = array();
        foreach (is_array($headers) ? $headers : headers_list() as $header) {
            if (stripos($header, 'Set-Cookie:') === 0) { $values[] = trim(substr($header, 11)); }
        }
        $cookies = $this->parse_cookie_headers($values, $this->frame['url']);
        try {
            $this->locked(function() use ($cookies) {
                $job = $this->read_job();
                $next = $job ? $this->browser_next($job) : null;
                if (!$next || !hash_equals($next['token'], $this->frame['token'])) { return; }
                $job['response_pages'][$next['phase'] . ' ' . $next['url']] = true;
                foreach ($cookies as $cookie) { $this->observe($job, $cookie, $next['url'], $next['phase'], 'http_browser'); }
                $this->write_job($job);
            });
        } catch (Throwable $error) { error_log('Marrison cookie scan: response cookie capture failed: ' . $error->getMessage()); }
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

    private function background_active($job) {
        // Deprecated: persisted remote jobs must never restart a retired worker.
        return false;
    }

    private function worker_token($job) {
        return hash_hmac('sha256', "background-worker\n" . $job['id'] . "\n" . $job['expires'], wp_salt('nonce'));
    }

    private function watchdog() {
        if (!wp_next_scheduled('marrison_addon_cookie_engine_tick')) { wp_schedule_single_event(time() + 95, 'marrison_addon_cookie_engine_tick'); }
    }

    /** A signed nonblocking loopback chains bounded batches independently of admin tabs. */
    private function wake_background() {
        $work = $this->locked(function() {
            $job = $this->read_job();
            if (!$this->background_active($job) || ($job['worker']['state'] ?? '') === 'paused') { return null; }
            $this->watchdog();
            if (($job['worker']['lease_until'] ?? 0) > time() || ($job['remote_claim']['expires'] ?? 0) > time()
                || ($job['worker']['dispatch_at'] ?? 0) > time() - 5) { return null; }
            $job['worker']['state'] = 'queued';
            $job['worker']['dispatch_at'] = time();
            $this->write_job($job);
            return array('job' => $job['id'], 'token' => $this->worker_token($job));
        });
        if (!$work) { return; }
        $response = wp_remote_post(admin_url('admin-ajax.php'), array('blocking' => false, 'timeout' => 2, 'redirection' => 0,
            'cookies' => array(), 'body' => array('action' => 'marrison_addon_cookie_engine_worker') + $work));
        if (is_wp_error($response)) {
            $this->locked(function() use ($work) {
                $job = $this->read_job();
                if (!$job || $job['id'] !== $work['job'] || !$this->background_active($job)) { return; }
                $job['worker']['dispatch_failures'] = ($job['worker']['dispatch_failures'] ?? 0) + 1;
                $job['worker']['error'] = 'Avvio in background non riuscito: il server non permette la richiesta interna. Verifica loopback e WP-Cron, poi premi Riprendi.';
                if ($job['worker']['dispatch_failures'] >= 3) { $job['worker']['state'] = 'paused'; }
                $this->write_job($job);
            });
        }
    }

    public function ajax_worker() {
        wp_send_json_error(array('message' => 'Worker remoto dismesso.'), 410); return; // Deprecated below.
        try {
            $job = $this->read_job();
        } catch (Throwable $error) {
            wp_send_json_error(array('message' => 'Archivio del lavoro non disponibile.'), 503);
            return;
        }
        $id = isset($_POST['job']) && is_string($_POST['job']) ? wp_unslash($_POST['job']) : '';
        $token = isset($_POST['token']) && is_string($_POST['token']) ? wp_unslash($_POST['token']) : '';
        if (!$this->background_active($job) || !hash_equals($job['id'], $id) || !hash_equals($this->worker_token($job), $token)) {
            wp_send_json_error(array('message' => 'Richiesta di lavoro non valida.'), 403);
            return;
        }
        ignore_user_abort(true);
        if (function_exists('set_time_limit')) { @set_time_limit(70); }
        $this->background_batch($id);
        wp_send_json_success(array('accepted' => true));
    }

    private function background_batch($expected_id = '') {
        $owner = bin2hex(random_bytes(12));
        $work = $this->locked(function() use ($expected_id, $owner) {
            $job = $this->read_job();
            if (!$this->background_active($job) || ($expected_id !== '' && $job['id'] !== $expected_id)
                || ($job['worker']['state'] ?? '') === 'paused' || ($job['worker']['lease_until'] ?? 0) > time() || ($job['remote_claim']['expires'] ?? 0) > time()) { return null; }
            $job['worker']['state'] = 'running';
            $job['worker']['owner'] = $owner;
            $job['worker']['lease_until'] = time() + 90;
            $job['worker']['heartbeat'] = time();
            $job['worker']['error'] = '';
            $job['worker']['dispatch_failures'] = 0;
            $this->write_job($job);
            $this->watchdog();
            return array('id' => $job['id'], 'status' => $job['status']);
        });
        if (!$work) { $this->wake_background(); return; }
        try {
            if ($work['status'] === 'http') {
                $this->locked(function() use ($work) {
                    $job = $this->read_job();
                    if (!$job || $job['id'] !== $work['id'] || $job['status'] !== 'http') { return; }
                    $deadline = microtime(true) + 4;
                    for ($step = 0; $step < 3 && $job['status'] === 'http'; $step++) {
                        $this->http_step($job);
                        if (microtime(true) >= $deadline) { break; }
                    }
                    $this->write_job($job);
                });
            } else { $this->remote_step(true, $work['id']); }
            $this->locked(function() use ($work, $owner) {
                $job = $this->read_job();
                if (!$job || $job['id'] !== $work['id'] || ($job['worker']['owner'] ?? '') !== $owner) { return; }
                $job['worker']['lease_until'] = 0;
                $job['worker']['dispatch_at'] = 0;
                $job['worker']['heartbeat'] = time();
                $job['worker']['state'] = $this->background_active($job) ? 'queued' : 'finished';
                $this->write_job($job);
                if (!$this->background_active($job)) { wp_clear_scheduled_hook('marrison_addon_cookie_engine_tick'); }
            });
            $this->wake_background();
        } catch (Throwable $error) {
            $this->locked(function() use ($work, $owner, $error) {
                $job = $this->read_job();
                if (!$job || $job['id'] !== $work['id'] || ($job['worker']['owner'] ?? '') !== $owner) { return; }
                $job['worker']['lease_until'] = 0;
                $job['worker']['state'] = 'paused';
                $job['worker']['error'] = sanitize_text_field($error->getMessage());
                $this->write_job($job);
            });
        }
    }

    public function background_notice() {
        if (!current_user_can('manage_options') || (isset($_GET['page']) && $_GET['page'] === 'marrison-cookie')) { return; }
        try { $job = $this->read_job(); } catch (Throwable $error) { return; }
        if (!$this->background_active($job) || !empty($job['worker']['initiator']) && $job['worker']['initiator'] !== get_current_user_id()) { return; }
        $message = ($job['worker']['state'] ?? '') === 'paused' ? 'Scansione cookie in pausa: apri lo scanner per consultare l’errore e riprendere.'
            : 'Scansione cookie in background. Puoi continuare a lavorare: avanzamento e risultati vengono salvati sul server.';
        echo '<div class="notice notice-info"><p>' . esc_html($message) . ' <a href="' . esc_url(admin_url('admin.php?page=marrison-cookie&tab=scanner')) . '">' . esc_html('Apri scanner') . '</a></p></div>';
    }

    public function start_scheduled_scan() {
        if (class_exists('Marrison_Addon') && method_exists('Marrison_Addon', 'is_module_enabled') && !Marrison_Addon::is_module_enabled('cookie_manager')) { return; }
        if (!Marrison_Cookie_Scanner::auto_scan_enabled()) { return; }
        try {
            $this->locked(function() {
                $job = $this->read_job();
                if (!$job || ($job['revision'] ?? '') !== self::REVISION || !in_array($job['status'], array('http', 'browser'), true) || $job['expires'] < time()) { $this->write_job($this->new_job(0, array(), 'local')); }
            });
            $job = $this->read_job();
            if ($this->background_active($job)) { $this->wake_background(); }
            else { $this->scheduled_step(); }
        } catch (Throwable $error) { /* The next admin status request reports storage failures. */ }
    }

    public function scheduled_step() {
        if (class_exists('Marrison_Addon') && method_exists('Marrison_Addon', 'is_module_enabled') && !Marrison_Addon::is_module_enabled('cookie_manager')) { return; }
        try {
            $current = $this->read_job();
            if ($this->background_active($current)) { $this->background_batch($current['id']); return; }
            $job = $this->locked(function() {
                $job = $this->read_job();
                if ($job && $this->legacy_job($job) && in_array($job['status'], array('http', 'browser'), true)) { $job['status'] = 'cancelled'; $this->write_job($job); }
                if ($job && $job['status'] === 'http') { $this->http_step($job); $this->write_job($job); }
                return $job;
            });
            if ($job && $job['status'] === 'http' && !wp_next_scheduled('marrison_addon_cookie_engine_tick')) { wp_schedule_single_event(time() + 10, 'marrison_addon_cookie_engine_tick'); }
        } catch (Throwable $error) { /* No green completion is stored on failure. */ }
    }
}
