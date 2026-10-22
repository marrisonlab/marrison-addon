<?php
/** Select representative public routes locally, before making HTTP/browser requests. */
final class Marrison_Addon_Cookie_Scan_Sampler {
    private $archives = array();
    private $taxonomies = array();
    private $forced = array();
    private $templates = array();
    private $rules = array();
    private $feature_nodes = 0;

    public function __construct() {
        foreach (array('privacy', 'cookie') as $kind) {
            $url = get_option('marrison_cookie_' . $kind . '_policy_url', '');
            if ($url && $this->published($url)) { $this->forced[$this->route($url)] = array('key' => 'policy:' . $kind, 'label' => ucfirst($kind) . ' Policy', 'kind' => 'policy'); }
        }
        $blog = (int) get_option('page_for_posts');
        if ($blog && function_exists('get_permalink')) {
            $this->forced[$this->route(get_permalink($blog))] = array('key' => 'posts_index', 'label' => 'Indice articoli', 'kind' => 'archive');
        }
        if (function_exists('get_post_types')) {
            foreach (get_post_types(array('public' => true, 'publicly_queryable' => true), 'objects') as $type => $object) {
                if (in_array($type, array('attachment', 'elementor_library'), true)) { continue; }
                $url = function_exists('get_post_type_archive_link') ? get_post_type_archive_link($type) : false;
                if ($url) { $this->archives[$this->route($url)] = array('key' => 'archive:' . $type, 'label' => 'Archivio ' . $object->label, 'kind' => 'archive', 'template' => $this->locate(array('archive-' . $type . '.php', 'archive.php', 'index.php'))); }
            }
        }
        if (function_exists('get_taxonomies')) { $this->taxonomies = get_taxonomies(array('public' => true, 'publicly_queryable' => true), 'objects'); }
        foreach (array('functional', 'analytics', 'marketing') as $category) {
            foreach (preg_split('/\R/', (string) get_option('marrison_cookie_' . $category . '_sources', '')) as $rule) {
                if (trim($rule) !== '') { $this->rules[] = trim($rule); }
            }
        }
    }

    private function route($url) {
        $parts = wp_parse_url((string) $url);
        $path = preg_replace('#/page/\d+/?$#', '/', $parts['path'] ?? '/');
        $query = array();
        if (!empty($parts['query'])) { parse_str($parts['query'], $query); }
        unset($query['paged'], $query['page'], $query['lang']);
        ksort($query);
        return rtrim($path, '/') . ($query ? '?' . http_build_query($query) : '');
    }

    private function published($url) {
        $id = function_exists('url_to_postid') ? url_to_postid($url) : 0;
        $post = $id && function_exists('get_post') ? get_post($id) : null;
        return !$post || (($post->post_status ?? '') === 'publish' && empty($post->post_password));
    }

    public function seeds() {
        $urls = array(home_url('/'));
        foreach (array('privacy', 'cookie') as $kind) {
            $url = get_option('marrison_cookie_' . $kind . '_policy_url', '');
            if ($url && $this->published($url)) { $urls[] = $url; }
        }
        if (function_exists('get_permalink')) {
            $blog = (int) get_option('page_for_posts');
            if ($blog) { $urls[] = get_permalink($blog); }
        }
        if (function_exists('get_post_types') && function_exists('get_posts')) {
            foreach (get_post_types(array('public' => true, 'publicly_queryable' => true), 'objects') as $type => $object) {
                if (in_array($type, array('attachment', 'elementor_library'), true)) { continue; }
                $posts = get_posts(array('post_type' => $type, 'post_status' => 'publish', 'has_password' => false, 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'DESC', 'fields' => 'ids'));
                foreach ($posts as $id) { $urls[] = get_permalink($id); }
                $archive = get_post_type_archive_link($type);
                if ($archive) { $urls[] = $archive; }
            }
        }
        if (function_exists('get_terms') && function_exists('get_term_link')) {
            foreach ($this->taxonomies as $taxonomy => $object) {
                $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 1, 'orderby' => 'count', 'order' => 'DESC'));
                if (is_wp_error($terms)) { continue; }
                foreach ($terms as $term) { $url = get_term_link($term); if (!is_wp_error($url)) { $urls[] = $url; } }
            }
        }
        return array_values(array_unique(array_filter($urls, 'is_string')));
    }

    public function describe($url) {
        $id = function_exists('url_to_postid') ? url_to_postid($url) : 0;
        $post = $id && function_exists('get_post') ? get_post($id) : null;
        if ($post && (($post->post_status ?? '') !== 'publish' || !empty($post->post_password))) { return null; }
        $route = $this->route($url);
        $home = $this->route(home_url('/'));
        $query = array(); parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
        $locale = isset($query['lang']) && is_scalar($query['lang']) ? ':' . $query['lang'] : '';
        if ($route === $home) { return array('key' => 'home' . $locale, 'kind' => 'home', 'label' => 'Home'); }
        if (isset($this->forced[$route])) { $sample = $this->forced[$route]; $sample['key'] .= $locale; return $sample; }
        if (isset($this->archives[$route])) { $sample = $this->archives[$route]; $sample['key'] .= $locale; return $sample; }
        if (isset($query['s'])) { return array('key' => 'search' . $locale, 'kind' => 'search', 'label' => 'Ricerca'); }
        if ($post) {
            $type = $post->post_type;
            if (in_array($type, array('attachment', 'elementor_library'), true)) { return null; }
            $this->feature_nodes = 0;
            $template = $this->post_template($post);
            $features = $this->features((string) $post->post_content);
            if (function_exists('get_post_meta')) {
                $data = get_post_meta($id, '_elementor_data', true);
                if (is_string($data)) { $data = json_decode($data, true); }
                if (is_array($data)) { $this->elementor_features($data, $features); }
                foreach ((array) get_post_meta($id) as $key => $values) {
                    if (is_string($key) && $key !== '_elementor_data' && preg_match('/video|map|embed|iframe|script|tracking|analytics|pixel|form|shortcode|template/i', $key)) {
                        $value = get_post_meta($id, $key, true);
                        if (is_scalar($value) || is_array($value)) { $features = array_merge($features, $this->features(is_array($value) ? wp_json_encode($value) : (string) $value, true)); }
                    }
                }
            }
            sort($features, SORT_STRING); $features = array_values(array_unique($features));
            $uncertain = in_array('content-truncated', $features, true) || in_array('nested-content', $features, true);
            $variant = substr(hash('sha256', wp_json_encode($features)), 0, 16) . ($uncertain ? ':dedicated-' . $id : '');
            $sample = array('key' => 'single:' . $type . ':' . $template . ':' . $variant . $locale, 'kind' => 'single',
                'label' => 'Singolo ' . $type, 'post_type' => $type, 'template' => $template, 'features' => array_slice($features, 0, 30), 'features_total' => count($features));
            if ($uncertain) { $sample['uncertain'] = true; $sample['uncertainty'] = 'Contenuto oltre i limiti di raggruppamento: campione dedicato'; }
            // Builders with custom assignment rules may supply their resolved template key.
            return function_exists('apply_filters') ? apply_filters('marrison_cookie_scan_sample', $sample, $url, $post) : $sample;
        }
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        if (preg_match('#/\d{4}/(?:\d{1,2}/)?(?:\d{1,2}/)?$#', $path)) {
            return array('key' => 'date:' . $this->locate(array('date.php', 'archive.php', 'index.php')) . $locale, 'kind' => 'archive', 'label' => 'Archivio per data');
        }
        $slug = rawurldecode(basename(rtrim(preg_replace('#/page/\d+/?$#', '/', $path), '/')));
        if (function_exists('get_term_by') && function_exists('get_term_link')) {
            foreach ($this->taxonomies as $taxonomy => $object) {
                $term_slug = $slug;
                if (($query['taxonomy'] ?? '') === $taxonomy && isset($query['term'])) { $term_slug = $query['term']; }
                elseif (!empty($object->query_var) && isset($query[$object->query_var])) { $term_slug = basename($query[$object->query_var]); }
                $term = $taxonomy === 'category' && !empty($query['cat']) ? get_term_by('id', (int) $query['cat'], $taxonomy) : get_term_by('slug', $term_slug, $taxonomy);
                if (!$term) { continue; }
                $link = get_term_link($term);
                if (is_wp_error($link) || $this->route($link) !== $route) { continue; }
                $names = array('taxonomy-' . $taxonomy . '-' . $term->slug . '.php', 'taxonomy-' . $taxonomy . '.php', 'taxonomy.php', 'archive.php', 'index.php');
                if (in_array($taxonomy, array('category', 'post_tag'), true)) {
                    $prefix = $taxonomy === 'category' ? 'category' : 'tag';
                    $names = array($prefix . '-' . $term->slug . '.php', $prefix . '-' . ($term->term_id ?? 0) . '.php', $prefix . '.php', 'archive.php', 'index.php');
                }
                $template = $this->locate($names);
                $features = $this->features($term->description ?? ''); sort($features, SORT_STRING);
                return array('key' => 'taxonomy:' . $taxonomy . ':' . $template . ':' . substr(hash('sha256', wp_json_encode($features)), 0, 16) . $locale,
                    'kind' => 'taxonomy', 'label' => 'Archivio ' . $object->label, 'template' => $template, 'features' => array_slice($features, 0, 30), 'features_total' => count($features));
            }
        }
        // Non-WordPress routes are sampled by route family, not by every pagination URL.
        $relative = trim(substr($path, strlen(rtrim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/'))), '/');
        $family = explode('/', $relative)[0];
        return array('key' => 'route:' . $family . $locale, 'kind' => 'route', 'label' => 'Percorso ' . ($family ?: '/'), 'uncertain' => true);
    }

    private function post_template($post) {
        $assigned = function_exists('get_page_template_slug') ? get_page_template_slug($post->ID) : '';
        if ($assigned && $assigned !== 'default') { return $assigned; }
        $type = $post->post_type;
        $names = $type === 'page' ? array('page-' . $post->post_name . '.php', 'page-' . $post->ID . '.php', 'page.php', 'singular.php', 'index.php')
            : array('single-' . $type . '-' . $post->post_name . '.php', 'single-' . $type . '.php', 'single.php', 'singular.php', 'index.php');
        return $this->locate($names) ?: 'default-' . $type;
    }

    private function locate($names) {
        if (!function_exists('locate_template')) { return ''; }
        $key = implode('|', $names);
        if (!isset($this->templates[$key])) { $this->templates[$key] = basename((string) locate_template($names, false, false)); }
        return $this->templates[$key];
    }

    private function hosts($value, &$features) {
        $value = str_replace('\\/', '/', html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
        if (!preg_match_all('#(?:https?:)?//([a-z0-9.-]+)(?::\d+)?#i', $value, $matches)) { return; }
        $home = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        foreach ($matches[1] as $host) { $host = strtolower($host); if ($host !== $home) { $features[] = 'service:' . $host; } }
    }

    private function features($content, $url_value = false) {
        $truncated = strlen($content) > 1048576;
        $content = substr($content, 0, 1048576);
        $features = $truncated ? array('content-truncated') : array();
        if ($url_value) { $this->hosts($content, $features); }
        if (preg_match_all('/(?:src|srcset|data-src|action)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>\]]+))/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) { $this->hosts($match[1] ?: ($match[2] ?: ($match[3] ?? '')), $features); }
        }
        if (preg_match_all('#(?:^|[\r\n>])\s*(https?://[^\s<>]+)\s*(?:$|[\r\n<])#m', $content, $matches)) {
            foreach ($matches[1] as $value) { $this->hosts($value, $features); }
        }
        if (preg_match_all('/\[(?!\/)([a-z_][\w-]*)\b([^\]]*)\]/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tag = strtolower($match[1]); $features[] = 'shortcode:' . $tag;
                $this->hosts($match[2], $features);
                if (preg_match('/form|elementor-template/', $tag) && preg_match_all('/(?:^|\s)(id|form_id|template_id)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s]+))/i', $match[2], $ids, PREG_SET_ORDER)) {
                    foreach ($ids as $component) { $features[] = 'shortcode-component:' . $tag . ':' . strtolower($component[1]) . ':' . substr(hash('sha256', $component[2] ?: ($component[3] ?: ($component[4] ?? ''))), 0, 16); }
                }
            }
        }
        if (preg_match_all('#\[(embed|video|audio|playlist)\b[^\]]*\](?:([^\[]*)\[/\1\])?#is', $content, $matches)) {
            foreach ($matches[0] as $value) { $this->hosts($value, $features); }
        }
        if (preg_match_all('#<script\b[^>]*>(.*?)</script>#is', $content, $matches)) {
            foreach ($matches[1] as $script) { if (trim($script) !== '') { $features[] = 'inline:' . substr(hash('sha256', preg_replace('/\s+/', ' ', trim($script))), 0, 16); } }
        }
        if (stripos($content, '<form') !== false) { $features[] = 'form'; }
        foreach ($this->rules as $rule) { if (stripos($content, $rule) !== false) { $features[] = 'configured:' . substr(hash('sha256', $rule), 0, 16); } }
        return array_values(array_unique($features));
    }

    private function elementor_features($nodes, &$features, $depth = 0) {
        if ($depth > 30) { $features[] = 'nested-content'; return; }
        foreach ($nodes as $key => $value) {
            if (++$this->feature_nodes > 5000) { $features[] = 'content-truncated'; return; }
            if (is_array($value)) { $this->elementor_features($value, $features, $depth + 1); }
            elseif (is_string($value)) {
                if ($key === 'widgetType') {
                    // Cosmetic differences do not change cookie behavior; custom/integration widgets still get distinct samples.
                    if (!in_array($value, array('heading', 'text-editor', 'image', 'image-box', 'icon', 'icon-box', 'icon-list', 'button', 'spacer', 'divider'), true)) { $features[] = 'widget:' . $value; }
                }
                elseif (in_array($key, array('template_id', 'listing_id', 'form_id', 'popup_id'), true)) { $features[] = 'component:' . $key . ':' . $value; }
                elseif (preg_match('/url|src|video|map|html|shortcode|editor/i', (string) $key)) { $features = array_merge($features, $this->features($value, $key === 'url' || $key === 'src')); }
            } elseif (is_int($value) && in_array($key, array('template_id', 'listing_id', 'form_id', 'popup_id'), true)) { $features[] = 'component:' . $key . ':' . $value; }
        }
    }
}
