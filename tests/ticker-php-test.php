<?php
/** Local output/asset fixtures: php tests/ticker-php-test.php */
namespace Elementor {
    class Widget_Base {
        public $settings = [];
        public function get_settings_for_display() { return $this->settings; }
    }
}
namespace Jet_Engine\Query_Builder {
    class Manager {
        public static $items = [];
        public static function instance() { return new self(); }
        public function get_query_by_id( $id ) { return $this; }
        public function get_items() { return self::$items; }
    }
}
namespace {
    error_reporting( E_ALL );
    define( 'ABSPATH', __DIR__ . '/' );
    $GLOBALS['ticker_hooks'] = [];
    $GLOBALS['ticker_styles'] = [];
    function add_action( $hook, $callback ) { $GLOBALS['ticker_hooks'][ $hook ][] = $callback; }
    function wp_register_style( $handle, $url ) { $GLOBALS['ticker_styles'][ $handle ] = $url; }
    function plugins_url( $path ) { return $path; }
    function apply_filters( $hook, $value ) { return $value; }
    function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
    function esc_html( $value ) { return esc_attr( $value ); }
    function esc_html__( $value, $domain ) { return $value; }
    function esc_url( $value ) { return esc_attr( $value ); }
    class WP_Post { public $ID = 7; public $post_title = 'Post title'; }
    class WP_Term {
        public $term_id = 12;
        public $name = 'News & Events';
        public $slug = 'news';
        public $taxonomy = 'category';
    }
    class WP_Error {}
    function is_wp_error( $value ) { return $value instanceof WP_Error; }
    function get_term_link( $term ) {
        return 'invalid' === $term->taxonomy ? new WP_Error() : 'https://example.test/' . $term->taxonomy . '/' . $term->slug . '/';
    }
    function get_permalink( $id ) { return 'https://example.test/post/' . $id . '/'; }
    // WordPress supplies a compatibility fallback when mbstring is unavailable.
    if ( ! function_exists( 'mb_strlen' ) ) {
        function mb_strlen( $value ) { return preg_match_all( '/./us', $value ); }
    }
    class Marrison_Addon {
        public static function plugin_file() { return __FILE__; }
        public static function asset_version( $path ) { return 'test'; }
    }
    require dirname( __DIR__ ) . '/marrison-addon/includes/modules/class-marrison-addon-ticker.php';
    require dirname( __DIR__ ) . '/marrison-addon/includes/modules/ticker/widgets/ticker-widget.php';
    $module = new \Marrison_Addon_Ticker();
    if ( $GLOBALS['ticker_styles'] ) { throw new \RuntimeException( 'Unused module should not enqueue/register assets at construction.' ); }
    $GLOBALS['ticker_hooks']['elementor/frontend/after_register_styles'][0]();
    $widget = new \Marrison_Addon\Modules\Ticker\Widgets\Ticker_Widget();
    if ( [ 'marrison-addon-ticker' ] !== $widget->get_style_depends() || ! isset( $GLOBALS['ticker_styles']['marrison-addon-ticker'] ) ) {
        throw new \RuntimeException( 'The widget must declare its shared stylesheet.' );
    }
    $widget->settings = [
        'direction' => 'left', 'speed' => 20, 'pause_on_hover' => 'yes',
        'ticker_items' => [ [ 'item_text' => 'Test <safe>', 'item_link' => [] ] ],
    ];
    $render = new \ReflectionMethod( $widget, 'render' );
    if ( PHP_VERSION_ID < 80100 ) { $render->setAccessible( true ); }
    ob_start();
    $render->invoke( $widget );
    $render->invoke( $widget );
    $html = ob_get_clean();
    if ( false !== strpos( $html, '<style' ) || false !== strpos( $html, '@keyframes' ) ) { throw new \RuntimeException( 'Each instance must not duplicate shared CSS.' ); }
    if ( 4 !== substr_count( $html, 'class="ticker-loop-inner"' ) || false === strpos( $html, 'animation-name: ticker-infinite-left' ) || false === strpos( $html, 'ticker-pause-hover' ) ) {
        throw new \RuntimeException( 'Loop, direction or pause configuration changed.' );
    }
    if ( false !== strpos( $html, 'Test <safe>' ) || false === strpos( $html, 'Test &lt;safe&gt;' ) ) { throw new \RuntimeException( 'Text escaping changed.' ); }
    $options_method = new \ReflectionMethod( $widget, 'get_jet_engine_macros_options' );
    if ( PHP_VERSION_ID < 80100 ) { $options_method->setAccessible( true ); }
    $options = $options_method->invoke( $widget );
    foreach ( [ 'name', 'term_id', 'slug', 'description', 'taxonomy', 'term_link' ] as $key ) {
        if ( ! isset( $options[ $key ] ) ) { throw new \RuntimeException( 'Missing term field: ' . $key ); }
    }
    $widget->settings = [
        'source' => 'jet_engine', 'query_id' => 1,
        'direction' => 'left', 'speed' => 20, 'pause_on_hover' => 'yes',
        'query_text_type' => 'preset', 'query_text_preset' => 'name',
        'query_link_type' => 'preset', 'query_link_preset' => 'term_link',
    ];
    $term = new WP_Term();
    \Jet_Engine\Query_Builder\Manager::$items = [ $term ];
    foreach ( [ 'category', 'product_cat', 'custom_taxonomy' ] as $taxonomy ) {
        $term->taxonomy = $taxonomy;
        foreach ( [ 'term_link', 'permalink' ] as $link_key ) {
            $widget->settings['query_link_preset'] = $link_key;
            ob_start();
            $render->invoke( $widget );
            $html = ob_get_clean();
            if ( false === strpos( $html, '<a href="https://example.test/' . $taxonomy . '/news/">News &amp; Events</a>' ) ) {
                throw new \RuntimeException( 'Term title/archive link failed for ' . $taxonomy . ': ' . $link_key );
            }
        }
    }
    $term->taxonomy = 'invalid';
    ob_start();
    $render->invoke( $widget );
    $html = ob_get_clean();
    if ( false !== strpos( $html, '<a ' ) || false === strpos( $html, 'News &amp; Events' ) ) {
        throw new \RuntimeException( 'Invalid term URL must retain plain text without a broken link.' );
    }
    \Jet_Engine\Query_Builder\Manager::$items = [ new WP_Post() ];
    $widget->settings['query_text_preset'] = 'post_title';
    ob_start();
    $render->invoke( $widget );
    $html = ob_get_clean();
    if ( false === strpos( $html, '<a href="https://example.test/post/7/">Post title</a>' ) ) {
        throw new \RuntimeException( 'Existing post title/permalink behavior changed.' );
    }
    echo "Ticker: term selector fields, linked category/custom taxonomy query output, invalid term URL and post regression PASS.\n";
    echo "Ticker: shared conditional CSS, multiple instances, direction/pause and escaped output PASS.\n";
}
