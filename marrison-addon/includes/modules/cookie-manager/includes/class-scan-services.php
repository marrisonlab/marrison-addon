<?php
/** Local provider declarations. A match never proves that a cookie was installed. */
if (!defined('ABSPATH')) { exit; }

class Marrison_Addon_Cookie_Scan_Services {
    private static $catalog;
    private static $invalid = false;
    private static $inventory;

    public static function catalog() {
        if (self::$catalog === null) {
            $path = dirname(__DIR__) . '/data/services.json';
            $json = is_file($path) && filesize($path) <= 262144 ? @file_get_contents($path) : false;
            self::$catalog = self::validate_catalog($json);
            self::$invalid = !self::$catalog;
        }
        return self::$catalog;
    }

    public static function catalog_error() {
        self::catalog();
        return self::$invalid;
    }

    /** Reject the whole document on invalid data; never partially trust a damaged catalog. */
    public static function validate_catalog($json) {
        if (!is_string($json) || strlen($json) > 262144) { return array(); }
        $rows = json_decode($json, true, 32);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($rows) || !$rows || count($rows) > 100) { return array(); }
        $catalog = array();
        foreach ($rows as $row) {
            if (!is_array($row)) { return array(); }
            foreach (array('id', 'name', 'category', 'source_url', 'last_reviewed') as $key) {
                if (!isset($row[$key]) || !is_string($row[$key]) || $row[$key] === '' || strlen($row[$key]) > 2048) { return array(); }
            }
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $row['id']) || isset($catalog[$row['id']])
                || !in_array($row['category'], array('necessary', 'functional', 'analytics', 'marketing'), true)
                || !self::https_url($row['source_url']) || !isset($row['verified']) || !is_bool($row['verified'])
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $row['last_reviewed'])) { return array(); }
            $date = explode('-', $row['last_reviewed']);
            if (!checkdate((int) $date[1], (int) $date[2], (int) $date[0])) { return array(); }
            if (!isset($row['provider']['name'], $row['provider']['privacy_url']) || !is_string($row['provider']['name'])
                || $row['provider']['name'] === '' || !self::https_url($row['provider']['privacy_url'])
                || !isset($row['patterns'], $row['cookies']) || !is_array($row['patterns']) || !$row['patterns']
                || count($row['patterns']) > 40 || !is_array($row['cookies']) || count($row['cookies']) > 50
                || (isset($row['notes']) && !is_string($row['notes']))) { return array(); }
            foreach ($row['patterns'] as $pattern) {
                if (!is_array($pattern) || !isset($pattern['host']) || !is_string($pattern['host'])
                    || ($pattern['host'] !== '' && !self::valid_host($pattern['host']))
                    || (isset($pattern['subdomains']) && !is_bool($pattern['subdomains']))
                    || ($pattern['host'] === '' && empty($pattern['path']))) { return array(); }
                if (isset($pattern['path']) && (!is_string($pattern['path']) || substr($pattern['path'], 0, 1) !== '/' || strlen($pattern['path']) > 255)) { return array(); }
                foreach (array('path_regex', 'query_regex') as $key) {
                    if (isset($pattern[$key]) && (!is_string($pattern[$key]) || strlen($pattern[$key]) > 255 || @preg_match('~' . $pattern[$key] . '~i', '') === false)) { return array(); }
                }
            }
            foreach ($row['cookies'] as $cookie) {
                if (!is_array($cookie)) { return array(); }
                foreach (array('name', 'domain', 'duration', 'purpose') as $key) {
                    if (!isset($cookie[$key]) || !is_string($cookie[$key]) || $cookie[$key] === '' || strlen($cookie[$key]) > 2048) { return array(); }
                }
            }
            $catalog[$row['id']] = $row;
        }
        return $catalog;
    }

    private static function https_url($value) {
        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) { return false; }
        $parts = wp_parse_url($value);
        return ($parts['scheme'] ?? '') === 'https' && empty($parts['user']) && empty($parts['pass']);
    }

    private static function valid_host($host) {
        return strlen($host) <= 253 && (bool) filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }

    public static function sanitize_categories($value) {
        $clean = array();
        foreach (is_array($value) ? $value : array() as $id => $category) {
            if (is_string($id) && preg_match('/^[a-z0-9-]{1,80}$/D', $id) && is_string($category)
                && in_array($category, array('necessary', 'functional', 'analytics', 'marketing'), true)) { $clean[$id] = $category; }
            if (count($clean) >= 200) { break; }
        }
        return $clean;
    }

    public static function host_id($host) { return 'host-' . hash('sha256', strtolower($host)); }

    public static function merge_categories($value) {
        $categories = self::sanitize_categories(get_option('marrison_cookie_service_categories', array()));
        foreach (is_array($value) ? $value : array() as $id => $category) {
            if (is_string($id) && $category === '') { unset($categories[$id]); }
        }
        return self::sanitize_categories(array_replace($categories, self::sanitize_categories($value)));
    }

    public static function category($profile) {
        $overrides = self::sanitize_categories(get_option('marrison_cookie_service_categories', array()));
        return $overrides[$profile['id']] ?? $profile['category'];
    }

    /** Read the existing scan inventory once per request, without starting a scan. */
    public static function discovered_hosts() {
        if (self::$inventory === null) {
            self::$inventory = class_exists('Marrison_Addon_Cookie_Scan_Engine', false)
                ? Marrison_Addon_Cookie_Scan_Engine::get_instance()->service_inventory() : array();
        }
        $hosts = array();
        foreach ((array) get_option('marrison_cookie_passive_hosts', array()) as $host => $present) {
            if (is_string($host) && self::valid_host($host)) { $hosts[$host] = self::category(array('id' => self::host_id($host), 'category' => 'unclassified')); }
        }
        foreach (self::$inventory as $service) {
            $host = strtolower($service['host'] ?? '');
            if (!$host || !self::valid_host($host) || !empty($service['profiles'])) { continue; }
            $hosts[$host] = self::category(array('id' => self::host_id($host), 'category' => 'unclassified'));
        }
        return $hosts;
    }

    public static function runtime_config() {
        $services = array();
        $custom = array();
        foreach (array('functional', 'analytics', 'marketing') as $category) {
            $value = get_option('marrison_cookie_' . $category . '_sources', '');
            $custom[$category] = is_string($value) ? array_values(array_filter(array_map('trim', array_slice(preg_split('/\R/', $value), 0, 100)))) : array();
        }
        foreach (self::catalog() as $profile) {
            $services[] = array('category' => self::category($profile), 'patterns' => $profile['patterns']);
        }
        return array('catalog' => $services, 'hosts' => self::discovered_hosts(), 'custom_sources' => $custom, 'unknown_external' => true);
    }

    public static function resource_category($resource) {
        if (is_string($resource) && substr($resource, 0, 1) === '/' && substr($resource, 0, 2) !== '//') { $resource = home_url($resource); }
        $profiles = self::identify($resource);
        if ($profiles) { return $profiles[0]['category']; }
        $host = strtolower((string) wp_parse_url($resource, PHP_URL_HOST));
        $hosts = self::discovered_hosts();
        if (isset($hosts[$host])) { return $hosts[$host]; }
        $scheme = strtolower((string) wp_parse_url($resource, PHP_URL_SCHEME));
        return $host && in_array($scheme, array('http', 'https'), true) && $host !== strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST)) ? 'unclassified' : null;
    }

    private static function pattern_matches($parts, $pattern) {
        $host = strtolower($parts['host']);
        if ($pattern['host'] !== '') {
            $expected = strtolower($pattern['host']);
            if ($host !== $expected && (empty($pattern['subdomains']) || substr($host, -strlen('.' . $expected)) !== '.' . $expected)) { return false; }
        } elseif ($host !== strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST))) { return false; }
        $path = $parts['path'] ?? '/';
        if (isset($pattern['path']) && strpos($path, $pattern['path']) !== 0) { return false; }
        foreach (array('path_regex' => $path, 'query_regex' => ($parts['query'] ?? '')) as $key => $value) {
            if (isset($pattern[$key]) && !preg_match('~' . $pattern[$key] . '~i', $value)) { return false; }
        }
        return true;
    }

    /** Existing domain/path source rules extend the catalog without editing plugin files. */
    public static function custom_category($resource) {
        $parts = wp_parse_url($resource);
        if (!isset($parts['host'])) { return null; }
        foreach (array('functional', 'analytics', 'marketing') as $category) {
            $rules = get_option('marrison_cookie_' . $category . '_sources', '');
            foreach (is_string($rules) ? array_slice(preg_split('/\R/', $rules), 0, 100) : array() as $rule) {
                $rule = trim($rule);
                if ($rule === '' || strlen($rule) > 255) { continue; }
                if ($rule[0] === '/') {
                    if (strpos($parts['path'] ?? '/', $rule) === 0) { return $category; }
                } else {
                    $rule_parts = wp_parse_url(strpos($rule, '://') === false ? 'https://' . $rule : $rule);
                    if (isset($rule_parts['host']) && self::pattern_matches($parts, array('host' => $rule_parts['host'], 'subdomains' => true, 'path' => $rule_parts['path'] ?? '/'))) { return $category; }
                }
            }
        }
        return null;
    }

    public static function identify($resource) {
        if (!is_string($resource) || strlen($resource) > 8192) { return array(); }
        $resource = html_entity_decode($resource, ENT_QUOTES, 'UTF-8');
        if (strpos($resource, '//') === 0) { $resource = 'https:' . $resource; }
        $parts = wp_parse_url($resource);
        if (!isset($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true) || !empty($parts['user']) || !empty($parts['pass'])) { return array(); }
        $profiles = array();
        $custom = self::custom_category($resource);
        foreach (self::catalog() as $profile) {
            foreach ($profile['patterns'] as $pattern) {
                if (!self::pattern_matches($parts, $pattern)) { continue; }
                $profile['category'] = $custom ?? self::category($profile);
                foreach ($profile['cookies'] as &$cookie) { $cookie['evidence'] = 'provider_documentation'; $cookie['observed'] = false; }
                unset($cookie);
                $profiles[] = $profile;
                break;
            }
        }
        if (!$profiles && $custom !== null) {
            $host = strtolower($parts['host']);
            $profiles[] = array('id' => self::host_id($host), 'name' => $host, 'category' => $custom,
                'source_url' => '', 'verified' => false, 'cookies' => array(), 'notes' => 'Servizio classificato tramite una regola amministrativa; cookie da verificare.');
        }
        return $profiles;
    }
}
