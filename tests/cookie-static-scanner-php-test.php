<?php
define('ABSPATH', __DIR__ . '/');
function wp_parse_url($v, $part = -1) { return parse_url($v, $part); }
function home_url($path = '/') { return 'https://example.test' . $path; }
function get_option($name, $default = false) { return $default; }
function get_post($id) { return (object) array('post_status' => 'publish', 'post_password' => '', 'post_content' => $id === 1 ? '<iframe src="https://www.youtube-nocookie.com/embed/x"></iframe>' : ''); }
function get_post_meta($id, $key, $single) { return $id === 22 ? '[{"widgetType":"google_maps","settings":{"address":"Roma"}}]' : ''; }
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
check_static(!$scan->content(str_repeat('x', 2097153), $services), 'Oversized content silently certified complete');
check_static(empty($services['unclassified.example']['profiles']), 'Unknown host was assigned invented cookies');
$cookies = Marrison_Addon_Cookie_Static_Scanner::declared_cookies($services);
check_static(count($cookies) === 3, 'Unexpected declaration count');
foreach ($cookies as $cookie) { check_static($cookie['source'] === 'catalogo' && $cookie['observed'] === false && $cookie['observations'] === array(), 'Catalog declaration mislabeled observed'); }
echo "PASS static scanner: bounded published WP_Query, resumable cursor, post/Elementor resources, unknown host, declarations only and no HTTP\n";

check_static(!$scan->content(array_fill(0, 201, ''), $services), 'Truncated configuration must be partial');
check_static(!$scan->content(str_repeat(' https://example.test/a ', 201), $services), 'Truncated URL list must be partial');
