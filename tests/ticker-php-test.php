<?php
/** Local output/asset fixtures: php tests/ticker-php-test.php */
namespace Elementor {
    class Widget_Base {
        public $settings = [];
        public function get_settings_for_display() { return $this->settings; }
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
    echo "Ticker: shared conditional CSS, multiple instances, direction/pause and escaped output PASS.\n";
}
