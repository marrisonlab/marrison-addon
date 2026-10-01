<?php
// Read-only runtime probes. The required harness opens a DB transaction and rolls it back.
$module = $argv[1] ?? 'product_discount';
$argv = ['wp-runtime-probe.php', 'isolation', $module];
require dirname(__DIR__) . '/wp-runtime-probe.php';

$out = ['module' => $module, 'checks' => [], 'errors' => $GLOBALS['audit_errors'] ?? []];
$check = static function ($name, $ok, $detail = null) use (&$out) {
    $out['checks'][] = ['name' => $name, 'ok' => (bool) $ok, 'detail' => $detail];
};

if ('product_discount' === $module) {
    $simple = new WC_Product_Simple();
    $simple->set_regular_price('100');
    $simple->set_sale_price('75');
    $check('simple 25 percent', 25.0 === round(Marrison_Addon_Product_Discount::get_discount_percentage($simple), 6), Marrison_Addon_Product_Discount::get_discount_percentage($simple));
    $free = new WC_Product_Simple();
    $free->set_regular_price('100');
    $free->set_sale_price('0');
    $check('simple free sale 100 percent', 100.0 === round(Marrison_Addon_Product_Discount::get_discount_percentage($free), 6), Marrison_Addon_Product_Discount::get_discount_percentage($free));
    $variable = new WC_Product_Variable();
    $v1 = new WC_Product_Variation();
    $v1->set_regular_price('100'); $v1->set_sale_price('80');
    $v2 = new WC_Product_Variation();
    $v2->set_regular_price('200'); $v2->set_sale_price('100');
    $variable->set_children([]);
    $check('variable object path', 0.0 === (float) Marrison_Addon_Product_Discount::get_discount_percentage($variable), Marrison_Addon_Product_Discount::get_discount_percentage($variable));
    $parent = new WC_Product_Variable();
    $parent->set_name('Audit temporary variable');
    $parent->set_status('draft');
    $parent_id = $parent->save();
    $rv1 = new WC_Product_Variation(); $rv1->set_parent_id($parent_id); $rv1->set_regular_price('100'); $rv1->set_sale_price('80'); $rv1->set_status('publish'); $rv1->save();
    $rv2 = new WC_Product_Variation(); $rv2->set_parent_id($parent_id); $rv2->set_regular_price('200'); $rv2->set_sale_price('100'); $rv2->set_status('publish'); $rv2->save();
    $loaded_parent = wc_get_product($parent_id);
    $check('persisted variable max discount', 50.0 === round(Marrison_Addon_Product_Discount::get_discount_percentage($loaded_parent), 6), $loaded_parent ? Marrison_Addon_Product_Discount::get_discount_percentage($loaded_parent) : null);
    $check('discount boundary invalid regular', 0 == Marrison_Addon_Product_Discount::calculate_discount_percentage(0, 10));
    $check('discount boundary sale above regular', 0 == Marrison_Addon_Product_Discount::calculate_discount_percentage(100, 120));
    $check('module hooks loaded', has_action('woocommerce_after_product_object_save') !== false && has_action('shutdown') !== false);
    $discount_module = null;
    foreach (($GLOBALS['wp_filter']['init']->callbacks[20] ?? []) as $callback) {
        if (is_array($callback['function'] ?? null) && is_object($callback['function'][0]) && $callback['function'][1] === 'maybe_schedule_backfill') {
            $discount_module = $callback['function'][0]; break;
        }
    }
    if ($discount_module) {
        $batch_method = (new ReflectionClass($discount_module))->getMethod('process_product_batch');
        $batch_method->setAccessible(true);
        $batch_result = $batch_method->invoke($discount_module, 1);
        $check('backfill batch returns pagination result', isset($batch_result['total'], $batch_result['processed'], $batch_result['done']), $batch_result);
    }
}

if ('recently_viewed_products' === $module) {
    $product_ids = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 3, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids']);
    $product_ids = array_values(array_map('absint', is_array($product_ids) ? $product_ids : []));
    $ids = count($product_ids) >= 3 ? $product_ids : [12, 13, 14];
    $_COOKIE['woocommerce_recently_viewed'] = implode('|', $ids);
    $macro_class = '\\Marrison_Addon\\Modules\\Recently_Viewed_Products\\Macros\\Recently_Viewed_Products';
    $macro_list = [];
    if (function_exists('jet_engine') && isset(jet_engine()->listings->macros)) {
        $macro_list = jet_engine()->listings->macros->get_all(false, true);
    }
    $check('macro lazy init registered', isset($macro_list['marrison_recently_viewed_products']), array_keys($macro_list));
    $check('macro class loaded after lazy init', class_exists($macro_class));
    if (class_exists($macro_class)) {
        $macro = new $macro_class();
        $GLOBALS['product'] = wc_get_product($ids[0]);
        $raw_ids_method = (new ReflectionClass($macro_class))->getMethod('get_recently_viewed_product_ids');
        $raw_ids_method->setAccessible(true);
        $raw_ids = $raw_ids_method->invoke($macro);
        $value = $macro->macros_callback(['marrison_rvp_limit' => 2, 'marrison_rvp_exclude_current' => 'no']);
        $expected = implode(',', array_slice(array_values(array_unique(array_merge([$ids[0]], array_reverse(array_filter($ids))))), 0, 2));
        $check('macro returns newest-first limit 2', $expected === $value, ['expected' => $expected, 'actual' => $value, 'ids' => $ids, 'raw' => $raw_ids, 'types' => array_map(static function ($id) { return [get_post_type($id), get_post_status($id)]; }, $ids)]);
        $excluded = $macro->macros_callback(['marrison_rvp_limit' => 3, 'marrison_rvp_exclude_current' => 'yes']);
        $check('macro excludes current product', false === strpos(',' . $excluded . ',', ',' . $ids[0] . ','), $excluded);
        $GLOBALS['product'] = null;

        $tracker = null;
        foreach (($GLOBALS['wp_filter']['template_redirect']->callbacks[20] ?? []) as $callback) {
            if (is_array($callback['function'] ?? null) && is_object($callback['function'][0]) && $callback['function'][1] === 'track_product_view') {
                $tracker = $callback['function'][0]; break;
            }
        }
        $check('tracker object found from hook', is_object($tracker));
        if ($tracker) {
            $check('tracker hook is registered at priority 20', has_action('template_redirect', [$tracker, 'track_product_view']) === 20);
            global $wp_query;
            $saved_query = $wp_query;
            $wp_query = new WP_Query();
            $wp_query->is_singular = true;
            $wp_query->is_single = true;
            $wp_query->queried_object_id = $ids[2];
            $wp_query->queried_object = get_post($ids[2]);
            $wp_query->query_vars['post_type'] = 'product';
            $_COOKIE['woocommerce_recently_viewed'] = $ids[0] . '|' . $ids[1];
            $tracker->track_product_view();
            $after_view = isset($_COOKIE['woocommerce_recently_viewed']) ? $_COOKIE['woocommerce_recently_viewed'] : '';
            $check('real tracker appends current viewed product', false !== strpos('|' . $after_view . '|', '|' . $ids[2] . '|'), $after_view);
            $_COOKIE['woocommerce_recently_viewed'] = implode('|', array_fill(0, 50, $ids[1])) . '|' . $ids[2];
            $tracker->track_product_view();
            $cap_ids = wp_parse_id_list(explode('|', $_COOKIE['woocommerce_recently_viewed']));
            $check('tracker caps cookie at 50 unique positions', count($cap_ids) <= 50 && end($cap_ids) === $ids[2], ['count' => count($cap_ids), 'tail' => array_slice($cap_ids, -3)]);
            $wp_query = $saved_query;
        }
        $empty = (new ReflectionClass($macro_class))->getMethod('get_empty_query_value');
        $empty->setAccessible(true);
        $check('empty sentinel is positive', '2147483647' === $empty->invoke($macro));
    }
}

if ('calendar_sync' === $module) {
    $module_object = new Marrison_Addon_Calendar_Sync();
    $ref = new ReflectionClass($module_object);
    $method = $ref->getMethod('format_ics_datetime'); $method->setAccessible(true);
    $formatted = $method->invoke($module_object, 0, 'UTC');
    $check('UTC format shape', 'UTC' === $formatted['timezone'] && false !== strpos($formatted['datetime'], 'Z'), $formatted);
    $links = $module_object->calendar_link_shortcode(['post_id' => 999999999, 'type' => 'google']);
    $check('invalid post returns empty', '' === $links, $links);
    $check('ICS route hook loaded', has_action('template_redirect') !== false);
    $settings = $module_object->sanitize_settings(['start_meta' => '', 'end_meta' => '', 'ics_timezone' => 'invalid/timezone']);
    $check('blank meta keys retained by sanitizer', '' === $settings['start_meta'] && '' === $settings['end_meta'], $settings);
    try {
        $method->invoke($module_object, time(), 'invalid/timezone');
        $check('invalid timezone rejected without exception', false);
    } catch (Throwable $e) {
        $check('invalid timezone rejected without exception', false, get_class($e) . ': ' . $e->getMessage());
    }
}

if ('cookie_manager' === $module) {
    $consent = Marrison_Cookie_Consent::get_instance();
    $ref = new ReflectionClass($consent);
    $detect = $ref->getMethod('detect_tracking_category'); $detect->setAccessible(true);
    $filter = $ref->getMethod('filter_script_tag'); $filter->setAccessible(true);
    $analytics = $detect->invoke($consent, '<script src="https://www.google-analytics.com/ga.js"></script>');
    $blocked = $filter->invoke($consent, ['', ' src="https://www.google-analytics.com/ga.js"', 'ga("send")']);
    $check('analytics classified', 'analytics' === $analytics, $analytics);
    $check('analytics script neutralized', false !== strpos($blocked, 'data-marrison-cookie-blocked="script"') && false !== strpos($blocked, 'data-marrison-blocked-src'), $blocked);
    $check('public scanner AJAX is auth protected', has_action('wp_ajax_nopriv_marrison_scan_cookies') === false);
    global $wpdb;
    $table = $wpdb->prefix . 'marrison_cookies';
    $check('cookie table exists', $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table, $wpdb->last_error);
}

if ('cursor' === $module || 'preloader' === $module) {
    do_action('wp_enqueue_scripts');
    $handles = array_keys($GLOBALS['wp_scripts']->registered ?? []);
    $styles = array_keys($GLOBALS['wp_styles']->registered ?? []);
    $handle = 'cursor' === $module ? 'marrison-cursor' : 'marrison-preloader';
    $check('frontend asset registered', in_array($handle, $handles, true) || in_array($handle, $styles, true), ['scripts' => $handles, 'styles' => $styles]);
}

if ('fast_logout' === $module) {
    $logout = new Marrison_Addon_Fast_Logout();
    $redirect = $logout->custom_logout_redirect('https://example.test/custom', 'https://example.test/custom', null);
    $check('logout returns home URL', home_url() === $redirect, $redirect);
}

echo "SERVICE_PROBE_JSON\n" . json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
