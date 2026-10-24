<?php
/** Resumable local inventory. No HTTP, cookie values or visitor data. */
if (!defined('ABSPATH')) { exit; }
final class Marrison_Addon_Cookie_Static_Scanner {
    public function step(&$state, &$services) {
        $state += array('page' => 1, 'posts' => 0, 'done' => false, 'options_done' => false, 'skipped' => 0);
        if ($state['done']) { return; }
        $deadline = microtime(true) + 2;
        if (!$state['options_done']) { $state['config_incomplete'] = !$this->configuration($services); $state['options_done'] = true; }
        $query = new WP_Query(array('post_type' => 'any', 'post_status' => 'publish', 'has_password' => false,
            'fields' => 'ids', 'posts_per_page' => 20, 'paged' => max(1, (int) $state['page']), 'orderby' => 'ID', 'order' => 'ASC',
            'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false));
        $ids = $query->posts;
        $offset = (int) ($state['offset'] ?? 0);
        foreach (array_slice($ids, $offset) as $id) {
            if (memory_get_usage(true) > $this->memory_budget()) { $state['memory_limited'] = true; return; }
            $post = get_post($id);
            if ($post && $post->post_status === 'publish' && empty($post->post_password)) {
                if (!$this->content($post->post_content, $services)) { $state['skipped']++; }
                if (!$this->content(get_post_meta($id, '_elementor_data', true), $services)) { $state['skipped']++; }
            }
            $state['posts']++; $state['offset'] = ++$offset;
            if (microtime(true) >= $deadline) { return; }
        }
        $state['page']++; $state['offset'] = 0;
        $state['done'] = count($ids) < 20;
    }

    private function memory_budget() {
        $limit = function_exists('wp_convert_hr_to_bytes') ? wp_convert_hr_to_bytes(ini_get('memory_limit')) : 0;
        return $limit > 0 ? max(1, (int) ($limit * .8)) : 134217728;
    }

    public function content($value, &$services) {
        $nodes = 0; $stack = array($value); $complete = true;
        while ($stack && $nodes++ < 2000) {
            $item = array_pop($stack);
            if (is_array($item)) { if (count($item) > 200) { $complete = false; } foreach (array_slice($item, 0, 200) as $child) { $stack[] = $child; } continue; }
            if (!is_string($item)) { continue; }
            if (strlen($item) > 2097152) { $complete = false; continue; }
            $item = html_entity_decode(str_replace(array('\\/', '\\u0026'), array('/', '&'), $item), ENT_QUOTES, 'UTF-8');
            if (preg_match_all('~(?:https?:)?//[^\s"\'<>\\\\()]+~i', $item, $matches)) {
                if (count($matches[0]) > 200) { $complete = false; }
                foreach (array_slice($matches[0], 0, 200) as $url) { $this->resource($url, $services); }
            }
            // Elementor video/map widgets can store a short URL or an API key instead of an embed URL.
            if (preg_match('/(?:google_maps|google-maps|google_maps_api_key)/i', $item)) { $this->resource('https://maps.googleapis.com/maps/api/js', $services); }
            if (preg_match_all('/\b(G-[A-Z0-9]{4,}|GTM-[A-Z0-9]{3,})\b/', $item, $ids)) {
                foreach (array_unique($ids[1]) as $id) { $this->resource('https://www.googletagmanager.com/' . (strpos($id, 'GTM-') === 0 ? 'gtm.js' : 'gtag/js') . '?id=' . $id, $services); }
            }
        }
        return $complete && !$stack;
    }

    public function resource($url, &$services) {
        if (strpos($url, '//') === 0) { $url = 'https:' . $url; }
        $parts = wp_parse_url($url); $host = strtolower($parts['host'] ?? '');
        if (!$host || !in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true)) { return; }
        if ($host === 'youtu.be' || (in_array($host, array('youtube.com', 'www.youtube.com'), true) && preg_match('~^/(?:watch|shorts/)~', $parts['path'] ?? ''))) { $url = 'https://www.youtube-nocookie.com/embed/catalog-detection'; }
        $profiles = Marrison_Addon_Cookie_Scan_Services::identify($url);
        if (!$profiles && $host === strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST))) { return; }
        if (!isset($services[$host]) && count($services) >= 100) { return; }
        if (!isset($services[$host])) { $services[$host] = array('host' => $host, 'kinds' => array('static'), 'pages' => array(), 'cookies_verified' => false, 'profiles' => array()); }
        foreach ($profiles as $profile) { $services[$host]['profiles'][$profile['id']] = $profile; }
    }

    private function configuration(&$services) {
        global $wp_scripts, $wp_styles;
        $complete = true;
        foreach (array($wp_scripts, $wp_styles) as $registry) {
            foreach (isset($registry->registered) ? $registry->registered : array() as $dependency) {
                $src = $dependency->src ?? '';
                if (is_string($src) && $src !== '') { $this->resource($src[0] === '/' && substr($src, 0, 2) !== '//' ? home_url($src) : $src, $services); }
            }
        }
        $keys = array('widget_text', 'widget_custom_html', 'monsterinsights_settings', 'googlesitekit_analytics-4_settings',
            'googlesitekit_tagmanager_settings', 'gtm4wp-options', 'pixelyoursite', 'woocommerce_google_analytics_settings');
        if (function_exists('get_stylesheet')) { $keys[] = 'theme_mods_' . get_stylesheet(); }
        foreach ($keys as $key) { if (!$this->content(get_option($key, array()), $services)) { $complete = false; } }
        foreach (array('woocommerce_stripe_settings' => 'stripe', 'woocommerce_woocommerce_payments_settings' => 'woopayments',
            'woocommerce_ppcp-gateway_settings' => 'paypal', 'woocommerce_klarna_payments_settings' => 'klarna') as $key => $id) {
            $option = get_option($key, array());
            if (is_array($option) && ($option['enabled'] ?? '') === 'yes') { $this->declare_service($id, $services); }
        }
        if (class_exists('WooCommerce', false)) { $this->declare_service('woocommerce', $services); }
        $pixel = get_option('facebook_config', array());
        if (is_array($pixel) && !empty($pixel['pixel_id'])) { $this->resource('https://connect.facebook.net/en_US/fbevents.js', $services); }
        return $complete;
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
