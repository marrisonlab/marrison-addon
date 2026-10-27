<?php
/** Resumable local inventory. No HTTP, cookie values or visitor data. */
if (!defined('ABSPATH')) { exit; }
final class Marrison_Addon_Cookie_Static_Scanner {
    private $resource_overflow = false;

    public function step(&$state, &$services, $on_post = null) {
        $state += array('page' => 1, 'posts' => 0, 'done' => false, 'options_done' => false, 'skipped' => 0);
        if ($state['done']) { return; }
        $deadline = microtime(true) + 2;
        $budget = 2000;
        unset($state['memory_limited']);
        if (!$state['options_done'] && !$this->configuration($state, $services, $deadline, $budget)) { return; }
        $query = new WP_Query(array('post_type' => 'any', 'post_status' => 'publish', 'has_password' => false,
            'fields' => 'ids', 'posts_per_page' => 20, 'paged' => max(1, (int) $state['page']), 'orderby' => 'ID', 'order' => 'ASC',
            'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false));
        $ids = $query->posts;
        $offset = (int) ($state['offset'] ?? 0);
        foreach (array_slice($ids, $offset) as $id) {
            if (memory_get_usage(true) > $this->memory_budget()) { $state['memory_limited'] = true; return; }
            $post = get_post($id);
            if ($post && $post->post_status === 'publish' && empty($post->post_password)) {
                $state += array('field' => 0);
                while ($state['field'] < 2) {
                    $field = $state['field'] === 0 ? 'post_content' : '_elementor_data';
                    $value = $state['field'] === 0 ? $post->post_content : get_post_meta($id, '_elementor_data', true);
                    if (!$this->source($value, $services, $state, 'post:' . $id . ':' . $field, $deadline, $budget)) { return; }
                    $state['field']++;
                }
            }
            if ($post && is_callable($on_post)) { call_user_func($on_post, $post, $id); }
            unset($state['field']);
            $state['posts']++; $state['offset'] = ++$offset;
            if ($budget <= 0 || microtime(true) >= $deadline) { return; }
        }
        $state['page']++; $state['offset'] = 0;
        $state['done'] = count($ids) < 20;
    }

    private function memory_budget() {
        $limit = function_exists('wp_convert_hr_to_bytes') ? wp_convert_hr_to_bytes(ini_get('memory_limit')) : 0;
        return $limit > 0 ? max(1, (int) ($limit * .8)) : 134217728;
    }

    public function content($value, &$services) {
        $cursor = array();
        do {
            $budget = 2000;
            $done = $this->advance_value($value, $services, $cursor, PHP_FLOAT_MAX, $budget);
            if (memory_get_usage(true) > $this->memory_budget()) { return false; }
        } while (!$done);
        return empty($cursor['errors']);
    }

    /** Persist positions only, never option values, cookie values or copied post bodies. */
    private function source($value, &$services, &$state, $label, $deadline, &$budget) {
        if (!isset($state['value_cursor'])) { $state['value_cursor'] = array(); }
        $state['current_source'] = $label;
        if (!$this->advance_value($value, $services, $state['value_cursor'], $deadline, $budget)) {
            if (memory_get_usage(true) > $this->memory_budget()) { $state['memory_limited'] = true; }
            return false;
        }
        foreach ($state['value_cursor']['errors'] ?? array() as $reason) {
            $state['skipped']++;
            if (count($state['details'] ?? array()) < 20) { $state['details'][] = array('source' => $label, 'reason' => $reason); }
        }
        unset($state['value_cursor'], $state['current_source']);
        return true;
    }

    /** Large arrays and strings yield to the next request instead of dropping their tail. */
    private function advance_value($value, &$services, &$cursor, $deadline, &$budget) {
        if (!isset($cursor['stack'])) { $cursor['stack'] = array(array('path' => array(), 'next' => 0, 'byte' => 0)); }
        $key_cache = array();
        while ($cursor['stack']) {
            if ($budget-- <= 0 || microtime(true) >= $deadline || memory_get_usage(true) > $this->memory_budget()) { return false; }
            $index = count($cursor['stack']) - 1;
            $frame = $cursor['stack'][$index];
            $item = $value;
            foreach ($frame['path'] as $key) {
                if (!is_array($item) || !array_key_exists($key, $item)) { $item = null; break; }
                $item = $item[$key];
            }
            if (is_array($item)) {
                $cache_key = serialize($frame['path']);
                if (!isset($key_cache[$cache_key])) { $key_cache[$cache_key] = array_keys($item); }
                $keys = $key_cache[$cache_key];
                if ($frame['next'] >= count($keys)) { array_pop($cursor['stack']); unset($key_cache[$cache_key]); continue; }
                $key = $keys[$frame['next']];
                $cursor['stack'][$index]['next']++;
                if (count($frame['path']) >= 128) {
                    $this->cursor_error($cursor, 'Struttura annidata oltre 128 livelli: ' . substr(implode('/', $frame['path']), 0, 180));
                    continue;
                }
                $path = $frame['path']; $path[] = $key;
                $cursor['stack'][] = array('path' => $path, 'next' => 0, 'byte' => 0);
                continue;
            }
            if (!is_string($item) || $frame['byte'] >= strlen($item)) { array_pop($cursor['stack']); continue; }
            // Overlap preserves escaped URLs, HTML entities and service markers at chunk edges.
            $start = max(0, $frame['byte'] - 4096);
            $text = substr($item, $start, 65536 + $frame['byte'] - $start + 4096);
            $text = html_entity_decode(str_replace(array('\\/', '\\u0026'), array('/', '&'), $text), ENT_QUOTES, 'UTF-8');
            $this->resource_overflow = false;
            if (preg_match_all('~(?:https?:)?//[^\s"\'<>\\\\()]+~i', $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    if ($match[1] < $frame['byte'] - $start + 65536) { $this->resource($match[0], $services); }
                }
            }
            if (preg_match('/(?:google_maps|google-maps|google_maps_api_key)/i', $text)) { $this->resource('https://maps.googleapis.com/maps/api/js', $services); }
            if (preg_match_all('/\b(G-[A-Z0-9]{4,}|GTM-[A-Z0-9]{3,})\b/', $text, $ids)) {
                foreach (array_unique($ids[1]) as $id) { $this->resource('https://www.googletagmanager.com/' . (strpos($id, 'GTM-') === 0 ? 'gtm.js' : 'gtag/js') . '?id=' . $id, $services); }
            }
            if ($this->resource_overflow) { $this->cursor_error($cursor, 'Oltre 100 host esterni distinti: alcuni servizi richiedono una verifica dedicata.'); }
            $cursor['stack'][$index]['byte'] += 65536;
        }
        return true;
    }

    private function cursor_error(&$cursor, $reason) {
        if (!in_array($reason, $cursor['errors'] ?? array(), true) && count($cursor['errors'] ?? array()) < 10) { $cursor['errors'][] = $reason; }
    }

    public function resource($url, &$services) {
        if (strpos($url, '//') === 0) { $url = 'https:' . $url; }
        $parts = wp_parse_url($url); $host = strtolower($parts['host'] ?? '');
        if (!$host || !in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true)) { return; }
        if ($host === 'youtu.be' || (in_array($host, array('youtube.com', 'www.youtube.com'), true) && preg_match('~^/(?:watch|shorts/)~', $parts['path'] ?? ''))) { $url = 'https://www.youtube-nocookie.com/embed/catalog-detection'; }
        $profiles = Marrison_Addon_Cookie_Scan_Services::identify($url);
        if (!$profiles && $host === strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST))) { return; }
        if (!isset($services[$host]) && count($services) >= 100) { $this->resource_overflow = true; return; }
        if (!isset($services[$host])) { $services[$host] = array('host' => $host, 'kinds' => array('static'), 'pages' => array(), 'cookies_verified' => false, 'profiles' => array()); }
        foreach ($profiles as $profile) { $services[$host]['profiles'][$profile['id']] = $profile; }
    }

    private function configuration(&$state, &$services, $deadline, &$budget) {
        global $wp_scripts, $wp_styles;
        $sources = array();
        foreach (array('registered_scripts' => $wp_scripts, 'registered_styles' => $wp_styles) as $label => $registry) {
            $sources[$label] = array();
            foreach (isset($registry->registered) ? $registry->registered : array() as $dependency) {
                $src = $dependency->src ?? '';
                if (is_string($src) && $src !== '') { $sources[$label][] = $src[0] === '/' && substr($src, 0, 2) !== '//' ? home_url($src) : $src; }
            }
        }
        $keys = array('widget_text', 'widget_custom_html', 'monsterinsights_settings', 'googlesitekit_analytics-4_settings',
            'googlesitekit_tagmanager_settings', 'gtm4wp-options', 'pixelyoursite', 'woocommerce_google_analytics_settings');
        if (function_exists('get_stylesheet')) { $keys[] = 'theme_mods_' . get_stylesheet(); }
        $labels = array_merge(array_keys($sources), $keys);
        $state += array('option_cursor' => 0);
        while ($state['option_cursor'] < count($labels)) {
            $key = $labels[$state['option_cursor']];
            $value = isset($sources[$key]) ? $sources[$key] : get_option($key, array());
            $before = $state['skipped'];
            if (!$this->source($value, $services, $state, 'configuration:' . $key, $deadline, $budget)) { return false; }
            if ($state['skipped'] > $before) { $state['config_incomplete'] = true; }
            $state['option_cursor']++;
        }
        foreach (array('woocommerce_stripe_settings' => 'stripe', 'woocommerce_woocommerce_payments_settings' => 'woopayments',
            'woocommerce_ppcp-gateway_settings' => 'paypal', 'woocommerce_klarna_payments_settings' => 'klarna') as $key => $id) {
            $option = get_option($key, array());
            if (is_array($option) && ($option['enabled'] ?? '') === 'yes') { $this->declare_service($id, $services); }
        }
        if (class_exists('WooCommerce', false)) { $this->declare_service('woocommerce', $services); }
        $pixel = get_option('facebook_config', array());
        if (is_array($pixel) && !empty($pixel['pixel_id'])) { $this->resource('https://connect.facebook.net/en_US/fbevents.js', $services); }
        $state['options_done'] = true;
        return true;
    }

    private function declare_service($id, &$services) {
        $catalog = Marrison_Addon_Cookie_Scan_Services::catalog();
        if (!isset($catalog[$id])) { return; }
        $profile = $catalog[$id]; $profile['category'] = Marrison_Addon_Cookie_Scan_Services::category($profile);
        foreach ($profile['cookies'] as &$cookie) { $cookie['observed'] = false; $cookie['evidence'] = 'provider_documentation'; } unset($cookie);
        $key = 'plugin:' . $id;
        $services[$key] = array('host' => (string) wp_parse_url(home_url('/'), PHP_URL_HOST), 'kinds' => array('configuration'), 'pages' => array(), 'cookies_verified' => false, 'profiles' => array($id => $profile));
    }

    public static function declared_cookies($services) {
        $records = array(); $site = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        foreach ($services as $service) {
            foreach ($service['profiles'] ?? array() as $profile) {
                foreach ($profile['cookies'] as $cookie) {
                    $domain = $cookie['domain'] === 'Dominio del sito' ? $site : ltrim($cookie['domain'], '.');
                    $key = $cookie['name'] . '|' . $domain;
                    $records[$key] = array('cookie_name' => $cookie['name'], 'cookie_domain' => $domain, 'cookie_path' => '/',
                        'cookie_expiration' => null, 'cookie_expiration_status' => 'unknown', 'source' => 'catalogo', 'observed' => false,
                        'category' => $profile['category'], 'description' => $cookie['purpose'] . ' Durata dichiarata: ' . $cookie['duration'], 'observations' => array());
                }
            }
        }
        return array_values($records);
    }
}
