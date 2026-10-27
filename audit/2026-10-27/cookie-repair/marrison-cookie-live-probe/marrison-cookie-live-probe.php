<?php
/**
 * Plugin Name: Marrison Cookie Live Probe (test site only)
 * Description: Temporary observable consent/scanner fixture. Contains no remote administration API.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) { exit; }

register_activation_hook(__FILE__, function () {
    $saved = array();
    foreach (array('analytics', 'marketing') as $category) {
        $key = 'marrison_cookie_' . $category . '_sources';
        $saved[$key] = get_option($key, '');
        update_option($key, trim($saved[$key] . "\n/marrison-cookie-probe/" . $category), false);
    }
    update_option('marrison_cookie_probe_saved', $saved, false);
    $page = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Verifica Cookie Marrison — sito di test',
        'post_name' => 'verifica-cookie-marrison',
        // Both old truncation failures are represented by real saved WordPress data.
        'post_content' => '<!-- ' . str_repeat('fixture ', 270000) . ' https://www.youtube-nocookie.com/embed/catalog-probe -->[marrison_cookie_live_probe]'));
    if (is_wp_error($page) || !$page) { return; }
    $meta = array_fill(0, 2300, array('settings' => array('fixture' => 'text')));
    $meta[2299]['settings']['url'] = 'https://maps.googleapis.com/maps/api/js';
    update_post_meta($page, '_elementor_data', $meta);
    update_option('marrison_cookie_probe_page', $page, false);
});

register_deactivation_hook(__FILE__, function () {
    foreach (get_option('marrison_cookie_probe_saved', array()) as $key => $value) { update_option($key, $value, false); }
    $page = (int) get_option('marrison_cookie_probe_page');
    if ($page) { wp_trash_post($page); }
    delete_option('marrison_cookie_probe_saved');
    delete_option('marrison_cookie_probe_counts');
    delete_option('marrison_cookie_probe_page');
});

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) { return; }
    echo '<div class="notice notice-info"><p><strong>Probe temporaneo cookie:</strong> <a href="' . esc_url(get_permalink((int) get_option('marrison_cookie_probe_page'))) . '">Apri la pagina di verifica</a>. La disattivazione del probe ripristina le sorgenti e cestina la pagina.</p></div>';
});

add_action('template_redirect', function () {
    $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('~^/marrison-cookie-probe/(analytics|marketing)\.js$~', $path, $match)) {
        $category = $match[1];
        $counts = get_option('marrison_cookie_probe_counts', array());
        $counts[$category] = (int) ($counts[$category] ?? 0) + 1;
        update_option('marrison_cookie_probe_counts', $counts, false);
        status_header(200); nocache_headers(); header('Content-Type: application/javascript; charset=UTF-8');
        $name = $category === 'analytics' ? '_ga_marrison_probe' : '_fbp';
        echo 'window.marrisonProbeExecuted=window.marrisonProbeExecuted||{};window.marrisonProbeExecuted[' . wp_json_encode($category) . ']=true;';
        echo 'document.cookie=' . wp_json_encode($name . '=probe; Path=/; Max-Age=600; SameSite=Lax; Secure') . ';';
        exit;
    }
    if (is_page((int) get_option('marrison_cookie_probe_page')) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && isset($_POST['marrison_probe_reset']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['marrison_probe_reset'])), 'marrison_probe_reset')) {
        foreach (array('marrison_cookie_consent', 'marrison_cookie_categories', 'marrison_cookie_state', '_ga_marrison_probe', '_fbp') as $name) {
            setcookie($name, '', array('expires' => time() - 3600, 'path' => '/', 'secure' => is_ssl(), 'httponly' => $name === 'marrison_cookie_state', 'samesite' => 'Lax'));
        }
        wp_safe_redirect(get_permalink((int) get_option('marrison_cookie_probe_page'))); exit;
    }
}, -100);

add_shortcode('marrison_cookie_live_probe', function () {
    $counts = get_option('marrison_cookie_probe_counts', array());
    ob_start(); ?>
    <section id="marrison-live-probe" style="max-width:900px;margin:40px auto;padding:24px;background:#f4f6f8;color:#111;font:16px/1.5 sans-serif">
        <h2>Verifica tecnica del consenso — sito di test</h2>
        <p>Questa pagina usa due risorse controllate e un video esterno reale. Nessun valore di cookie o dato personale viene mostrato.</p>
        <p>Analytics eseguito: <strong id="probe-analytics">no</strong> — Cookie analytics presente: <strong id="probe-analytics-cookie">no</strong></p>
        <p>Marketing eseguito: <strong id="probe-marketing">no</strong> — Cookie marketing presente: <strong id="probe-marketing-cookie">no</strong></p>
        <p>Richieste risorse opzionali in questa pagina: <strong id="probe-requests">0</strong></p>
        <p>Richieste arrivate al server prima di questa pagina: analytics <strong><?php echo (int) ($counts['analytics'] ?? 0); ?></strong>, marketing <strong><?php echo (int) ($counts['marketing'] ?? 0); ?></strong>.</p>
        <form method="post"><button type="submit" name="marrison_probe_reset" value="<?php echo esc_attr(wp_create_nonce('marrison_probe_reset')); ?>">Azzera soltanto il consenso di prova</button></form>
        <h3>Video esterno soggetto al consenso marketing</h3>
        <iframe title="Video di prova consenso" data-marrison-cookie-category="marketing" src="https://www.youtube-nocookie.com/embed/M7lc1UVf-VE" width="560" height="315" loading="lazy"></iframe>
        <script src="<?php echo esc_url(home_url('/marrison-cookie-probe/analytics.js')); ?>" data-marrison-cookie-category="analytics"></script>
        <script src="<?php echo esc_url(home_url('/marrison-cookie-probe/marketing.js')); ?>" data-marrison-cookie-category="marketing"></script>
        <script>
        (function(){function show(){var executed=window.marrisonProbeExecuted||{},names=document.cookie.split(';').map(function(pair){return pair.split('=')[0].trim();});
            ['analytics','marketing'].forEach(function(category){document.getElementById('probe-'+category).textContent=executed[category]?'sì':'no';});
            document.getElementById('probe-analytics-cookie').textContent=names.indexOf('_ga_marrison_probe')>=0?'sì':'no';
            document.getElementById('probe-marketing-cookie').textContent=names.indexOf('_fbp')>=0?'sì':'no';
            document.getElementById('probe-requests').textContent=performance.getEntriesByType('resource').filter(function(entry){return entry.name.indexOf('/marrison-cookie-probe/')>=0;}).length;
        }show();setInterval(show,300);}());
        </script>
    </section>
    <?php return ob_get_clean();
});
