<?php
/** Per-device signed choices and private append-only receipts. No IP address is collected. */
final class Marrison_Addon_Cookie_Consent_Store {
    const COOKIE = 'marrison_cookie_state';
    const PREFIX = "<?php exit; ?>\n";
    private static $instance;

    public static function get_instance() {
        if (!self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_marrison_cookie_consent_state', array($this, 'ajax_state'));
        add_action('wp_ajax_nopriv_marrison_cookie_consent_state', array($this, 'ajax_state'));
        add_action('wp_ajax_marrison_cookie_consent_receipts', array($this, 'ajax_receipts'));
        add_action('marrison_cookie_consent_cleanup', array($this, 'cleanup'));
        add_action('init', array($this, 'schedule_cleanup'));
    }

    public static function duration() { return max(184, min(365, (int) get_option('marrison_cookie_consent_duration', 184))); }
    public static function retention() { return max(365, min(1095, (int) get_option('marrison_cookie_receipt_retention', 365))); }

    public static function categories($type, $categories) {
        $valid = array('necessary', 'functional', 'analytics', 'marketing');
        if ($type === 'accept_all') { return $valid; }
        if ($type === 'reject_all') { return array('necessary'); }
        return array_values(array_unique(array_merge(array('necessary'), array_intersect($valid, is_array($categories) ? $categories : array()))));
    }

    private function sign($value, $purpose) {
        $key = wp_salt('auth');
        if ($purpose === 'receipt') {
            $key = get_option('marrison_cookie_receipt_key', '');
            if (!is_string($key) || strlen($key) !== 64) {
                add_option('marrison_cookie_receipt_key', bin2hex(random_bytes(32)), '', false);
                $key = get_option('marrison_cookie_receipt_key', '');
                if (!is_string($key) || strlen($key) !== 64) { throw new RuntimeException('Chiave di integrità delle ricevute non disponibile.'); }
            }
        }
        return hash_hmac('sha256', $purpose . "\n" . $value, $key);
    }

    public function encode($payload) {
        $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) { throw new RuntimeException('Ricevuta consenso non codificabile.'); }
        $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        return $body . '.' . $this->sign($body, 'choice');
    }

    public function decode($value, $check_revision = true) {
        if (!is_string($value) || strlen($value) > 4096 || substr_count($value, '.') !== 1) { return null; }
        list($body, $signature) = explode('.', $value, 2);
        if (!hash_equals($this->sign($body, 'choice'), $signature)) { return null; }
        $data = json_decode(base64_decode(strtr($body, '-_', '+/'), true), true);
        if (!is_array($data) || ($data['schema'] ?? 0) !== 1 || !isset($data['receipt_id'], $data['visitor_id'], $data['type'], $data['categories'], $data['expires'], $data['revision'], $data['consent_version'])
            || !is_string($data['receipt_id']) || !preg_match('/^[a-f0-9]{32}$/', $data['receipt_id'])
            || !is_string($data['visitor_id']) || !preg_match('/^[a-f0-9]{32}$/', $data['visitor_id'])
            || !is_string($data['revision']) || !preg_match('/^[a-f0-9]{64}$/', $data['revision'])
            || !is_string($data['consent_version']) || !preg_match('/^[a-f0-9]{64}$/', $data['consent_version'])
            || !in_array($data['type'], array('accept_all', 'reject_all', 'custom'), true) || !is_array($data['categories'])
            || !is_int($data['expires']) || $data['expires'] <= time() || $data['categories'] !== self::categories($data['type'], $data['categories'])) { return null; }
        if ($check_revision && !hash_equals(Marrison_Addon_Cookie_Policy_Content::consent_version(), $data['consent_version'])) { return null; }
        return $data;
    }

    public function current() {
        return $this->decode(isset($_COOKIE[self::COOKIE]) ? wp_unslash($_COOKIE[self::COOKIE]) : '');
    }

    public function public_state($choice = null) {
        $choice = $choice === null ? $this->current() : $choice;
        return array('valid' => (bool) $choice, 'type' => $choice ? $choice['type'] : null,
            'categories' => $choice ? $choice['categories'] : array('necessary'), 'receipt_id' => $choice ? $choice['receipt_id'] : null,
            'expires' => $choice ? $choice['expires'] : null, 'revision' => Marrison_Addon_Cookie_Policy_Content::revision());
    }

    public function authorize_public() {
        check_ajax_referer('marrison_cookie_nonce', 'nonce');
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : ($_SERVER['HTTP_REFERER'] ?? '');
        $parts = parse_url($origin);
        $home = parse_url(home_url('/'));
        if (!$parts || !isset($parts['scheme'], $parts['host']) || strtolower($parts['host']) !== strtolower($home['host']) || strtolower($parts['scheme']) !== strtolower($home['scheme'])
            || ($parts['port'] ?? null) !== ($home['port'] ?? null)) {
            wp_send_json_error(array('message' => 'Origine della richiesta consenso non valida.'), 403);
        }
        nocache_headers();
    }

    public function ajax_state() {
        $this->authorize_public();
        try {
            $current = $this->current();
            // Preserve a legacy refusal without inventing an affirmative consent event.
            if (!$current && !$this->decode($_COOKIE[self::COOKIE] ?? '', false) && ($_COOKIE['marrison_cookie_consent'] ?? '') === 'reject_all') {
                $current = $this->record('reject_all', array(), 'legacy_refusal');
            }
        } catch (RuntimeException $error) { wp_send_json_error(array('message' => 'Non è stato possibile verificare le preferenze.', 'code' => 'consent_storage_error')); }
        wp_send_json_success(array('state' => $this->public_state($current)));
    }

    public function save_request($type, $categories) {
        $this->authorize_public();
        if (!in_array($type, array('accept_all', 'reject_all', 'custom'), true)) { wp_send_json_error(array('message' => 'Tipo di consenso non valido.')); }
        $requested = self::categories($type, $categories);
        $revision = isset($_POST['revision']) && is_string($_POST['revision']) ? $_POST['revision'] : '';
        if (count($requested) > 1 && !hash_equals(Marrison_Addon_Cookie_Policy_Content::revision(), $revision)) {
            wp_send_json_error(array('message' => 'L’informativa è cambiata. Aggiorna la pagina e rileggila prima di accettare.'));
        }
        try {
            $choice = $this->record($type, $categories);
            Marrison_Cookie_Banner::get_instance()->handle_consent($type, $choice['categories']);
        } catch (RuntimeException $error) { wp_send_json_error(array('message' => 'Non è stato possibile salvare le preferenze. Riprova.', 'code' => 'consent_storage_error')); }
        wp_send_json_success(array('message' => 'Preferenze salvate.', 'state' => $this->public_state($choice)));
    }

    private function path($file) {
        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error']) || empty($uploads['basedir'])) { throw new RuntimeException('Archivio delle ricevute non disponibile.'); }
        $dir = rtrim($uploads['basedir'], '/\\') . '/marrison-cookie/consent';
        if (!wp_mkdir_p($dir)) { throw new RuntimeException('Impossibile creare l’archivio delle ricevute.'); }
        // Install the access rules for the actual server. Unix hosts may forbid
        // web.config even though private PHP receipt files are writable.
        $iis = stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'Microsoft-IIS') !== false;
        $guards = array('index.html' => '');
        if ($iis) { $guards['web.config'] = '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>'; }
        else { $guards['.htaccess'] = "Require all denied\nDeny from all\n"; }
        foreach ($guards as $name => $content) {
            if (!is_file($dir . '/' . $name) && @file_put_contents($dir . '/' . $name, $content, LOCK_EX) === false) { throw new RuntimeException('Protezione dell’archivio ricevute non disponibile (' . $name . ').'); }
        }
        return $dir . '/' . $file;
    }

    public function record($type, $categories, $source = 'user') {
        if (!in_array($type, array('accept_all', 'reject_all', 'custom'), true)) { throw new RuntimeException('Tipo di consenso non valido.'); }
        if (headers_sent()) { throw new RuntimeException('Impossibile salvare i cookie di consenso: header già inviati.'); }
        $previous = $this->decode($_COOKIE[self::COOKIE] ?? '', false);
        $snapshot = Marrison_Addon_Cookie_Policy_Content::snapshot();
        $revision = Marrison_Addon_Cookie_Policy_Content::revision($snapshot);
        $choice = array('schema' => 1, 'receipt_id' => bin2hex(random_bytes(16)), 'visitor_id' => $previous ? $previous['visitor_id'] : bin2hex(random_bytes(16)),
            'type' => $type, 'categories' => self::categories($type, $categories), 'expires' => time() + self::duration() * DAY_IN_SECONDS, 'revision' => $revision, 'consent_version' => Marrison_Addon_Cookie_Policy_Content::consent_version($snapshot));
        // Signed scan requests use the same real cookies, without creating visitor consent receipts.
        if ($source === 'scan') { $this->set_cookies($choice); return $choice; }
        $event = $previous && array_diff($previous['categories'], $choice['categories']) ? 'withdrawal' : 'choice';
        $receipt = array('schema' => 1, 'receipt_id' => $choice['receipt_id'], 'visitor_id' => $choice['visitor_id'], 'previous_receipt' => $previous ? $previous['receipt_id'] : null,
            'created_at' => gmdate('c'), 'expires_at' => gmdate('c', $choice['expires']), 'type' => $type, 'categories' => $choice['categories'],
            'event' => $source === 'legacy_refusal' ? 'legacy_refusal' : $event, 'policy_revision' => $revision, 'consent_version' => $choice['consent_version']);
        $receipt['proof'] = $this->sign(wp_json_encode($receipt, JSON_UNESCAPED_SLASHES), 'receipt');
        $lock = fopen($this->path('archive.lock'), 'c');
        if (!$lock || !flock($lock, LOCK_EX)) { if ($lock) { fclose($lock); } throw new RuntimeException('Archivio ricevute occupato o non scrivibile.'); }
        try {
            $policy_path = $this->path('policy-' . $revision . '.php');
            if (!is_file($policy_path)) {
                $body = self::PREFIX . wp_json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if (file_put_contents($policy_path, $body, LOCK_EX) !== strlen($body)) { @unlink($policy_path); throw new RuntimeException('Salvataggio versione informativa non riuscito.'); }
            }
            touch($policy_path);
            $stream = fopen($this->path('receipts-' . gmdate('Y-m-d') . '.php'), 'c+b');
            if (!$stream) { throw new RuntimeException('Salvataggio ricevuta non riuscito.'); }
            try {
                if (!flock($stream, LOCK_EX)) { throw new RuntimeException('Impossibile bloccare l’archivio delle ricevute.'); }
                fseek($stream, 0, SEEK_END); $length = ftell($stream);
                $line = ($length === 0 ? self::PREFIX : '') . wp_json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
                if (fwrite($stream, $line) !== strlen($line) || !fflush($stream)) { ftruncate($stream, $length); throw new RuntimeException('Scrittura ricevuta incompleta. Nessun consenso applicato.'); }
            } finally { flock($stream, LOCK_UN); fclose($stream); }
        } finally { flock($lock, LOCK_UN); fclose($lock); }
        $this->set_cookies($choice);
        return $choice;
    }

    private function set_cookies($choice) {
        $domain = defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
        $options = array('expires' => $choice['expires'], 'path' => '/', 'domain' => $domain, 'secure' => is_ssl(), 'samesite' => 'Lax');
        $encoded = $this->encode($choice);
        if (!setcookie(self::COOKIE, $encoded, $options + array('httponly' => true))
            || !setcookie('marrison_cookie_consent', $choice['type'], $options + array('httponly' => false))
            || !setcookie('marrison_cookie_categories', implode('|', $choice['categories']), $options + array('httponly' => false))) { throw new RuntimeException('Cookie di consenso non salvati.'); }
        $_COOKIE[self::COOKIE] = $encoded;
        $_COOKIE['marrison_cookie_consent'] = $choice['type'];
        $_COOKIE['marrison_cookie_categories'] = implode('|', $choice['categories']);
    }

    public function ajax_receipts() {
        check_ajax_referer('marrison_cookie_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'Permessi insufficienti.'), 403); }
        nocache_headers();
        $date = isset($_POST['date']) && is_string($_POST['date']) ? $_POST['date'] : gmdate('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) { wp_send_json_error(array('message' => 'Data non valida.')); }
        $offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;
        $archive_lock = null;
        try {
            $archive_lock = fopen($this->path('archive.lock'), 'c');
            if (!$archive_lock || !flock($archive_lock, LOCK_SH)) { throw new RuntimeException('Archivio ricevute non leggibile.'); }
            $path = $this->path('receipts-' . $date . '.php'); $rows = array(); $more = false; $index = 0;
            if (is_file($path)) {
                $stream = fopen($path, 'rb');
                if (!$stream || !flock($stream, LOCK_SH)) { throw new RuntimeException('Archivio ricevute non leggibile.'); }
                try {
                    if (fgets($stream) !== self::PREFIX) { throw new RuntimeException('Archivio ricevute non valido.'); }
                    while (($line = fgets($stream)) !== false) {
                        if ($index++ < $offset) { continue; }
                        if (count($rows) >= 100) { $more = true; break; }
                        $row = json_decode($line, true);
                        if (!is_array($row) || !isset($row['proof'], $row['policy_revision']) || !is_string($row['proof']) || !is_string($row['policy_revision'])) { throw new RuntimeException('Ricevuta non leggibile.'); }
                        $proof = $row['proof']; unset($row['proof']);
                        $row['integrity_valid'] = hash_equals($this->sign(wp_json_encode($row, JSON_UNESCAPED_SLASHES), 'receipt'), $proof);
                        $row['proof'] = $proof;
                        $rows[] = $row;
                    }
                } finally { flock($stream, LOCK_UN); fclose($stream); }
            }
            $snapshots = array(); $snapshot_integrity = array();
            foreach ($rows as $row) {
                $revision = $row['policy_revision'];
                if (preg_match('/^[a-f0-9]{64}$/', $revision) && !isset($snapshots[$revision])) {
                    $policy_path = $this->path('policy-' . $revision . '.php');
                    $data = is_file($policy_path) ? file_get_contents($policy_path) : false;
                    $snapshots[$revision] = $data !== false && strpos($data, self::PREFIX) === 0 ? json_decode(substr($data, strlen(self::PREFIX)), true) : null;
                    $snapshot_integrity[$revision] = is_array($snapshots[$revision]) && hash_equals($revision, Marrison_Addon_Cookie_Policy_Content::revision($snapshots[$revision]));
                }
            }
        } catch (RuntimeException $error) { wp_send_json_error(array('message' => $error->getMessage())); }
        finally { if ($archive_lock) { flock($archive_lock, LOCK_UN); fclose($archive_lock); } }
        wp_send_json_success(array('receipts' => $rows, 'snapshots' => $snapshots, 'snapshot_integrity' => $snapshot_integrity, 'next_offset' => $more ? $offset + count($rows) : null, 'date' => $date));
    }

    public function schedule_cleanup() {
        if (class_exists('Marrison_Addon') && method_exists('Marrison_Addon', 'is_module_enabled') && !Marrison_Addon::is_module_enabled('cookie_manager')) { return; }
        if (!wp_next_scheduled('marrison_cookie_consent_cleanup')) { wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'marrison_cookie_consent_cleanup'); }
    }

    public function cleanup() {
        $lock = null;
        try {
            $lock = fopen($this->path('archive.lock'), 'c');
            if (!$lock || !flock($lock, LOCK_EX)) { throw new RuntimeException('Pulizia archivio ricevute non disponibile.'); }
            $cutoff = time() - self::retention() * DAY_IN_SECONDS;
            foreach (glob($this->path('receipts-????-??-??.php')) ?: array() as $file) {
                if (preg_match('/receipts-(\d{4}-\d{2}-\d{2})\.php$/', $file, $date) && strtotime($date[1] . ' 23:59:59 UTC') < $cutoff) { unlink($file); }
            }
            foreach (glob($this->path('policy-*.php')) ?: array() as $file) { if (filemtime($file) < $cutoff && basename($file) !== 'policy-' . Marrison_Addon_Cookie_Policy_Content::revision() . '.php') { unlink($file); } }
        } catch (RuntimeException $error) { error_log('Marrison Cookie Manager: ' . $error->getMessage()); }
        finally { if ($lock) { flock($lock, LOCK_UN); fclose($lock); } }
    }
}
