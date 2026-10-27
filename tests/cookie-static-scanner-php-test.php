<?php
define('ABSPATH', __DIR__ . '/');
function wp_parse_url($v, $part = -1) { return parse_url($v, $part); }
function home_url($path = '/') { return 'https://example.test' . $path; }
function get_option($name, $default = false) { return $GLOBALS['static_options'][$name] ?? $default; }
function get_post($id) { return (object) array('ID' => $id, 'post_type' => 'post', 'post_name' => 'post-' . $id, 'post_status' => 'publish', 'post_password' => '', 'post_content' => $GLOBALS['static_bodies'][$id] ?? ($id === 1 ? '<iframe src="https://www.youtube-nocookie.com/embed/x"></iframe>' : '')); }
function get_permalink($id) { return home_url('/post-' . $id . '/'); }
function url_to_postid($url) { return preg_match('~/post-(\d+)/~', $url, $match) ? (int) $match[1] : 0; }
function wp_convert_hr_to_bytes($value) { return $GLOBALS['static_memory_limit'] ?? 134217728; }
function get_post_meta($id, $key = '', $single = false) { return $key === '' ? array() : ($id === 22 && $key === '_elementor_data' ? '[{"widgetType":"google_maps","settings":{"address":"Roma"}}]' : ''); }
function wp_remote_get() { throw new RuntimeException('Static scan made an HTTP request'); }
function check_static($value, $message) { if (!$value) { throw new RuntimeException($message); } }
class WP_Query {
    public $posts;
    public function __construct($args) {
        check_static($args['fields'] === 'ids' && $args['post_status'] === 'publish' && $args['no_found_rows'] && $args['has_password'] === false, 'Unbounded/private query');
        $this->posts = array_slice(range(1, 41), ($args['paged'] - 1) * 20, 20);
    }
}
require __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-scan-services.php';
require __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-static-scanner.php';
$scan = new Marrison_Addon_Cookie_Static_Scanner(); $state = array(); $services = array();
$scan->step($state, $services);
check_static(!$state['done'] && $state['posts'] === 20 && $state['page'] === 2, 'Batch/cursor not resumable');
$copy = json_decode(json_encode($state), true); $scan->step($copy, $services); $scan->step($copy, $services);
check_static($copy['done'] && $copy['posts'] === 41, 'Resumed scan lost or duplicated posts');
check_static(isset($services['www.youtube-nocookie.com']['profiles']['youtube'], $services['maps.googleapis.com']['profiles']['google-maps']), 'Embed/Elementor detection missing');
$scan->content('https://unclassified.example/a.js', $services);
check_static($scan->content('[embed]https://youtu.be/example[/embed]', $services) && isset($services['youtu.be']['profiles']['youtube']), 'Shortcode short YouTube URL not recognized');
check_static($scan->content(str_repeat('x', 2097153), $services), 'Large plain content was skipped instead of read in chunks');
check_static(empty($services['unclassified.example']['profiles']), 'Unknown host was assigned invented cookies');
$cookies = Marrison_Addon_Cookie_Static_Scanner::declared_cookies($services);
check_static(count($cookies) === 3, 'Unexpected declaration count');
foreach ($cookies as $cookie) { check_static($cookie['source'] === 'catalogo' && $cookie['observed'] === false && $cookie['observations'] === array(), 'Catalog declaration mislabeled observed'); }
check_static($scan->content(array_fill(0, 201, ''), $services), 'Ordinary configuration array was truncated');
check_static($scan->content(str_repeat(' https://example.test/a ', 201), $services), 'Ordinary URL list was truncated');

$large_services = array();
$large = array_fill(0, 2300, array('settings' => array('text' => 'irrelevant text')));
$large[2299]['settings']['url'] = 'https://www.youtube-nocookie.com/embed/after-node-limit';
$GLOBALS['static_options'] = array('widget_custom_html' => $large);
$GLOBALS['static_bodies'] = array(2 => str_repeat('x', 2097153) . ' https://maps.googleapis.com/maps/api/js ');
$large_state = array(); $passes = 0; $resumed_source = false;
do {
    $scan->step($large_state, $large_services);
    $resumed_source = $resumed_source || isset($large_state['value_cursor']);
    // A real persisted job must resume using positions, not snapshots of the data.
    check_static(strpos(json_encode($large_state), 'irrelevant text') === false, 'Post/option values leaked into saved cursor');
    $large_state = json_decode(json_encode($large_state), true);
    check_static(++$passes < 100, 'Large content never resumed to completion');
} while (!$large_state['done']);
check_static($resumed_source && $large_state['posts'] === 41 && empty($large_state['skipped']) && empty($large_state['config_incomplete']), 'Large configuration falsely made scan partial or duplicated posts');
check_static(isset($large_services['www.youtube-nocookie.com']['profiles']['youtube'], $large_services['maps.googleapis.com']['profiles']['google-maps']), 'Resources beyond old node/byte limits were lost');
unset($GLOBALS['static_options'], $GLOBALS['static_bodies']);

$tail_services = array();
check_static($scan->content(str_repeat(' https://example.test/a ', 220) . ' https://www.youtube-nocookie.com/embed/after-url-limit ', $tail_services), 'Long URL list was not fully consumed');
check_static(isset($tail_services['www.youtube-nocookie.com']['profiles']['youtube']), 'URL beyond old 200 URL limit lost');
$boundary_services = array();
$scan->content(str_repeat(' ', 65530) . 'https://www.youtube-nocookie.com/embed/boundary', $boundary_services);
check_static(count($boundary_services) === 1 && isset($boundary_services['www.youtube-nocookie.com']['profiles']['youtube']), 'URL split at byte chunk boundary was corrupted');

$paused_state = array(); $paused_services = array();
$GLOBALS['static_memory_limit'] = 1;
$scan->step($paused_state, $paused_services);
check_static(!empty($paused_state['memory_limited']) && $paused_state['posts'] === 0 && !$paused_state['done'], 'Memory pressure did not retain an unfinished cursor');
unset($GLOBALS['static_memory_limit']);
do { $scan->step($paused_state, $paused_services); } while (!$paused_state['done']);
check_static($paused_state['posts'] === 41 && empty($paused_state['memory_limited']), 'Memory resume lost posts or stayed blocked');

$nested = 'https://www.youtube-nocookie.com/embed/deep';
for ($i = 0; $i < 132; $i++) { $nested = array('nested' => $nested); }
$GLOBALS['static_bodies'] = array(2 => $nested);
$detail_state = array(); $detail_services = array();
do { $scan->step($detail_state, $detail_services); } while (!$detail_state['done']);
check_static(!empty($detail_state['skipped']) && $detail_state['details'][0]['source'] === 'post:2:post_content' && strpos($detail_state['details'][0]['reason'], '128 livelli') !== false, 'Unsupported structure lacked precise source/reason');
unset($GLOBALS['static_bodies']);
echo "PASS static scanner: resumed large arrays, >2000 nodes, >2MiB strings and >200 URLs without truncation; chunk boundaries, memory resume, actionable source diagnostics, published content and catalog provenance\n";
