<?php
/** Uses real JetEngine Posts_Query/Base_Query and WP hooks; database/rendering are fixtures.
 * php tests/query-fallback-php-test.php /path/to/jet-engine /path/to/wordpress
 */
namespace Jet_Engine\Query_Builder {
    class Manager {
        public $queries = [], $contexts = [], $denied = [], $listings;
        public static $instance;
        public static function instance() { return self::$instance; }
        public function get_query_by_id( $id ) { return $this->queries[$id] ?? false; }
        public function get_query_by_id_for_context( $id, $context ) {
            $this->contexts[] = $context;
            return in_array( $id, $this->denied, true ) ? false : $this->get_query_by_id( $id );
        }
        public function get_queries_for_options( $blocks = false, $type = null ) {
            $result = ['' => 'Select query...'];
            foreach ( $this->queries as $id => $query ) {
                if ( $type && ( $query->query_type ?? '' ) !== $type ) continue;
                $result[$id] = $query->name ?? "Query $id";
            }
            return $result;
        }
    }
}
namespace Elementor {
    class Plugin { public static $instance; }
    class Controls_Manager {
        const SWITCHER = 'switcher', SELECT2 = 'select2', CHOOSE = 'choose', RAW_HTML = 'raw_html';
    }
}
namespace {
error_reporting( E_ALL );
define( 'ABSPATH', rtrim( $argv[2] ?? '', '/\\' ) . '/' );
$engine = rtrim( $argv[1] ?? '', '/\\' );
if ( ! is_file( ABSPATH . 'wp-includes/plugin.php' ) || ! is_file( $engine . '/jet-engine.php' ) ) {
    fwrite( STDERR, "Provide JetEngine and WordPress roots.\n" ); exit(1);
}
require ABSPATH . 'wp-includes/plugin.php';
function __( $text, $domain = '' ) { return $text; }
function absint( $value ) { return abs((int) $value); }
function admin_url( $path ) { return 'https://fixture.test/wp-admin/' . $path; }
function wp_doing_ajax() { return ! empty( $GLOBALS['ajax'] ); }
function wp_is_json_request() { return ! empty( $GLOBALS['json'] ); }
function wp_cache_get( $key, $group ) { return $GLOBALS['cache'][$group][$key] ?? false; }
function wp_cache_set( $key, $value, $group, $expiry ) { $GLOBALS['cache'][$group][$key] = $value; return true; }
class WP_Post { public $ID; public function __construct( $id ) { $this->ID = $id; } }
/** Strict section contract; the Listing Grid registration method below is real JetEngine. */
class Jet_Listing_Dynamic_Widget {
    public $controls = [], $section = null;
    public function start_controls_section($id, $args = []) {
        if ($this->section !== null) throw new \RuntimeException('Nested control section');
        $this->section = $id;
    }
    public function end_controls_section() {
        if ($this->section === null) throw new \RuntimeException('Section already closed');
        do_action('elementor/element/' . $this->get_name() . '/' . $this->section . '/before_section_end', $this, []);
        $this->section = null;
    }
    public function add_control($id, $args) {
        if ($this->section === null) throw new \RuntimeException('Cannot add a control outside of a section');
        if (isset($this->controls[$id])) throw new \RuntimeException('Duplicate control');
        $this->controls[$id] = $args + ['section' => $this->section];
    }
}
class WP_Query {
    public $posts = [], $query_vars, $post_count = 0, $found_posts = 0, $max_num_pages = 1;
    public function __construct( $args = [] ) {
        $this->query_vars = $args + ['posts_per_page' => 4];
        if ( ! $args ) return;
        $GLOBALS['sql'][] = $args;
        $ids = $args['fixture_ids'] ?? [];
        if ( ! empty($args['post__in']) ) $ids = array_values(array_intersect($ids, $args['post__in']));
        else $ids = array_values(array_diff($ids, $args['post__not_in'] ?? []));
        $this->found_posts = count($ids);
        $ids = array_slice($ids, (int)($args['offset'] ?? 0), max(0, $this->query_vars['posts_per_page']));
        $this->posts = array_map(function($id) { return new WP_Post($id); }, $ids);
        $this->post_count = count($ids);
        $this->max_num_pages = (int)ceil($this->found_posts / max(1, $this->query_vars['posts_per_page']));
    }
}
$current_id = 99;
$jet = (object)[
    'listings' => (object)[
        'data' => new class { public function get_current_object_id() { return $GLOBALS['current_id']; } },
        'macros' => new class {
            public function do_macros( $value ) { return str_replace('%current_id%', (string)$GLOBALS['current_id'], (string)$value); }
        },
    ],
    'admin_bar' => new class { public function register_item( $id, $args ) {} },
];
function jet_engine() { return $GLOBALS['jet']; }
require $engine . '/includes/components/query-builder/queries/base.php';
foreach (['meta-query', 'tax-query', 'date-query'] as $trait) require $engine . '/includes/components/query-builder/queries/traits/' . $trait . '.php';
require $engine . '/includes/components/query-builder/queries/posts.php';
require dirname(__DIR__) . '/marrison-addon/includes/modules/class-marrison-addon-query-fallback.php';
$manager = new \Jet_Engine\Query_Builder\Manager();
\Jet_Engine\Query_Builder\Manager::$instance = $manager;
$module = new Marrison_Addon_Query_Fallback();
$checks = 0;
function check( $value, $message ) { $GLOBALS['checks']++; if (!$value) throw new \RuntimeException($message); }
function ids( $items ) { return array_map(function($item) { return $item->ID; }, $items); }
function query( $id, $ids, $extra = [] ) {
    $dynamic = [];
    foreach ($extra as $key => $value) { if (is_string($value) && strpos($value, '%current_id%') !== false) $dynamic[$key] = $value; }
    return new \Jet_Engine\Query_Builder\Queries\Posts_Query([
        'id' => $id, 'name' => "Saved $id", 'type' => 'posts',
        'dynamic_query' => $dynamic,
        'query' => $extra + ['fixture_ids' => $ids, 'posts_per_page' => 4],
    ]);
}
function render( $main, $fallback, $mode = 'fill', $extra = [] ) {
    global $manager, $module;
    $manager->queries = [1 => $main, 2 => $fallback];
    $main->setup_query();
    $items = $main->get_items();
    $widget = (object)['listing_query_id' => 1, 'query_vars' => ['page' => 1, 'pages' => 2], 'posts_query' => $main->get_current_wp_query()];
    $settings = $extra + ['custom_query' => 'yes', 'custom_query_id' => '1', 'marrison_query_fallback_enable' => 'yes', 'marrison_query_fallback_id' => '2', 'marrison_query_fallback_mode' => $mode, 'lisitng_id' => 71, 'posts_num' => 12];
    $before_main = serialize($main); $before_fallback = serialize($fallback);
    $GLOBALS['sql'] = [];
    $result = apply_filters('jet-engine/listing/grid/query/query', $items, $settings, $widget);
    check(serialize($main) === $before_main && serialize($fallback) === $before_fallback, 'A singleton query was mutated');
    return [$result, $widget, $GLOBALS['sql']];
}
foreach ([0, 1, 2, 3, 4] as $number) {
    $main_ids = $number ? range(1, $number) : [];
    [$items, $widget, $sql] = render(query(1, $main_ids), query(2, range(1, 60)));
    check(ids($items) === [1,2,3,4], "Fill $number failed");
    check($number < 4 || !$sql, 'Unneeded fallback query');
}
[$items, , $sql] = render(query(1, []), query(2, [9,8,7,6]), 'empty');
check(ids($items) === [9,8,7,6] && count($sql) === 1, 'Empty fallback/order failed');
[$items, , $sql] = render(query(1, [7]), query(2, [9,8]), 'empty');
check(ids($items) === [7] && !$sql, 'Only empty executed despite results');
[$items] = render(query(1, [7]), query(2, []));
check(ids($items) === [7], 'Empty fallback lost primary');
[$items] = render(query(1, []), query(2, [8,7]));
check(ids($items) === [8,7], 'Short fallback failed');
[$items, , $sql] = render(query(1, [1,2,3]), query(2, array_merge(array_fill(0, 45, 1), [3,2,99,5]), ['post__not_in' => []]));
check(ids($items) === [1,2,3,99] && count($sql) === 3, 'Progressive overlap retrieval failed');
[$items] = render(query(1, [1,2], ['post__not_in' => '%current_id%']), query(2, [99,1,2,4,5], ['post__in' => [99,1,2,4,5]]));
check(ids($items) === [1,2,4,5], 'Current product exclusion or post__in intersection lost');
check($current_id === 99, 'Current Object context changed');
$current_id = 4;
[$items] = render(query(1, [1], ['post__not_in' => '%current_id%']), query(2, [4,3,2,8], ['post__not_in' => '%current_id%']));
check(ids($items) === [1,3,2,8], 'Different dynamic object reused stale context/cache');
foreach ([['marrison_query_fallback_id' => '1'], ['marrison_query_fallback_id' => '123'], ['marrison_query_fallback_id' => ['2']], ['custom_query' => ''], ['marrison_query_fallback_enable' => ''], ['marrison_query_fallback_mode' => 'invalid'], ['use_load_more' => 'yes'], ['lazy_load' => 'yes'], ['is_archive_template' => 'yes'], ['current_page' => 2]] as $extra) {
    [$items, , $sql] = render(query(1, [7]), query(2, [9,8]), 'fill', $extra);
    check(ids($items) === [7] && !$sql, 'Invalid/unsupported setting executed fallback: ' . json_encode($extra));
}
$manager->denied = [2];
[$items, , $sql] = render(query(1, []), query(2, [1,2]));
check(!$items && !$sql, 'Context authorization bypassed');
$manager->denied = [];
check(end($manager->contexts)['listing_id'] === 71, 'Listing context missing');
foreach (['ajax', 'json'] as $flag) {
    $GLOBALS[$flag] = true;
    [$items, , $sql] = render(query(1, []), query(2, [1,2]));
    check(!$items && !$sql, 'Public AJAX/JSON must not execute fallback');
    $GLOBALS[$flag] = false;
}
\Elementor\Plugin::$instance = (object)['editor' => new class { public function is_edit_mode() { return true; } }];
$ajax = true;
[$items] = render(query(1, []), query(2, [1,2]));
check(ids($items) === [1,2], 'Editor AJAX rendering failed');
$ajax = false;
$main = query(1, [1]); $backup = query(2, [2,3,4]);
[$items] = render($main, $backup, 'empty'); check(ids($items) === [1], 'Grid A changed');
[$items, $widget] = render($main, $backup); check(ids($items) === [1,2,3,4], 'Grid B inherited Grid A state');
check($widget->posts_query !== $main->get_current_wp_query() && $widget->posts_query->post_count === 4 && $widget->query_vars['pages'] === 1, 'Local render metadata wrong');
$manager->queries[2] = (object)['query_type' => 'terms'];
$settings = ['custom_query' => 'yes', 'custom_query_id' => 1, 'marrison_query_fallback_enable' => 'yes', 'marrison_query_fallback_id' => 2];
check($module->filter_items([], $settings, $widget) === [], 'Terms Query accepted');
$controls = new class { public $controls = []; public function add_control($id, $args) { $this->controls[$id] = $args; } };
$module->register_controls($controls);
check(count($controls->controls) === 5, 'Native control registration failed');
check(!isset($controls->controls['marrison_query_fallback_id']['options'][2]), 'Terms Query selectable');
check($controls->controls['marrison_query_fallback_mode']['type'] === 'choose' && $controls->controls['marrison_query_fallback_mode']['default'] === 'empty', 'Radio/default contract failed');
$jet->elementor_views = new class { public function include_base_widget() {} };
$jet->listings->legacy = new class { public function is_disabled() { return true; } };
require $engine . '/includes/components/elementor-views/static-widgets/grid.php';
$grid = new \Elementor\Jet_Listing_Grid_Widget();
$grid->register_query_settings();
check(count($grid->controls) === 7, 'Native Custom Query registration did not include all fallback controls');
check(array_keys($grid->controls) === ['custom_query', 'custom_query_id', 'marrison_query_fallback_enable', 'marrison_query_fallback_id', 'marrison_query_fallback_mode', 'marrison_query_fallback_notice', 'marrison_query_fallback_warning'], 'Fallback control order changed');
foreach (array_slice($grid->controls, 2) as $control) check($control['section'] === 'section_custom_query', 'Fallback control outside native section');
check($grid->section === null, 'Fallback left the native section open');
check(false === has_action('jet-engine/listing/custom-query-settings', [$module, 'register_controls']), 'Unsafe legacy-dependent hook still registered');
do_action('jet-engine/listing/custom-query-settings', $grid);
check(count($grid->controls) === 7, 'Legacy hook duplicated controls outside section');
eval('function jet_smart_filters() { return $GLOBALS["smart_filters"]; }');
$smart_filters = (object)['query' => new class {
    public function is_ajax_filter() { return false; }
    public function decode_provider_data($value) { $parts = explode('/', $value); return ['provider' => $parts[0], 'query_id' => $parts[1] ?? 'default']; }
}];
require $engine . '/includes/components/query-builder/listings/filters.php';
$manager->listings = (object)['filters' => (new \ReflectionClass('Jet_Engine\\Query_Builder\\Listings\\Filters'))->newInstanceWithoutConstructor()];
foreach (['empty', 'fill'] as $mode) {
    $main = query(1, []); $main->query_id = 'catalog-a';
    $backup = query(2, range(1, 3000)); $backup->query_id = 'catalog-b';
    [$items, , $sql] = render($main, $backup, $mode);
    check(ids($items) === [1,2,3,4] && count($sql) === 1, 'Unfiltered empty grid with installed JetSmartFilters failed: ' . $mode);
    $_REQUEST['jsf'] = 'jet-engine/catalog-a';
    [$items, , $sql] = render($main, $backup, $mode);
    check(!$items && !$sql, 'Applied main-query filter was bypassed');
    $_REQUEST['jsf'] = 'jet-engine/catalog-b';
    [$items, , $sql] = render($main, $backup, $mode);
    check(!$items && !$sql, 'Applied fallback-query filter was bypassed');
    $_REQUEST['jsf'] = 'jet-engine/other-grid';
    [$items, , $sql] = render($main, $backup, $mode);
    check(ids($items) === [1,2,3,4], 'A filter for another grid suspended this fallback');
    unset($_REQUEST['jsf']);
}
echo "PASS $checks Query Fallback checks using real JetEngine query classes; SQL/renderer are fixtures.\n";
}
