<?php
/** Small bounded counters shared across PHP workers; no raw IP or user agent. */
if (!defined('ABSPATH')) { exit; }
final class Marrison_Addon_Cookie_Request_Limits {
    public static function allow($bucket, $limit, $seconds) {
        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error']) || empty($uploads['basedir'])) { return false; }
        $directory = rtrim($uploads['basedir'], '/\\') . '/marrison-cookie';
        if (!wp_mkdir_p($directory)) { return false; }
        $file = fopen($directory . '/request-limits.php', 'c+b');
        if (!$file) { return false; }
        try {
            if (!flock($file, LOCK_EX)) { return false; }
            $raw = stream_get_contents($file, 131072); $prefix = "<?php exit; ?>\n";
            $data = strpos($raw, $prefix) === 0 ? json_decode(substr($raw, strlen($prefix)), true) : array();
            if (!is_array($data)) { return false; }
            foreach ($data as $key => $entry) { if (($entry['expires'] ?? 0) <= time()) { unset($data[$key]); } }
            $key = hash('sha256', $bucket);
            if (!isset($data[$key]) && count($data) >= 1000) { return false; }
            $entry = $data[$key] ?? array('count' => 0, 'expires' => time() + $seconds);
            if ($entry['count'] >= $limit) { return false; }
            $entry['count']++; $data[$key] = $entry;
            $body = $prefix . wp_json_encode($data);
            rewind($file);
            if (fwrite($file, $body) !== strlen($body) || !ftruncate($file, strlen($body)) || !fflush($file)) { return false; }
            return true;
        } finally { flock($file, LOCK_UN); fclose($file); }
    }
}
