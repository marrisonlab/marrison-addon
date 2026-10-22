<?php
/** Isolated controls/render fixtures; --fixture prints standalone browser HTML. */
namespace Elementor {
	class Widget_Base {
		public $settings = [];
		public $controls = [];
		public $groups = [];
		public $responsive = [];
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $id, $args = [] ) {}
		public function end_controls_section() {}
		public function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
		public function add_responsive_control( $id, $args ) { $this->responsive[] = $id; $this->add_control( $id, $args ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = $args; }
		public function start_controls_tabs( $id ) {}
		public function end_controls_tabs() {}
		public function start_controls_tab( $id, $args ) {}
		public function end_controls_tab() {}
	}
	class Controls_Manager {
		const TEXT = 'text', NUMBER = 'number', SWITCHER = 'switcher', SELECT = 'select', ICONS = 'icons', REPEATER = 'repeater', TAB_STYLE = 'style', CHOOSE = 'choose', COLOR = 'color', SLIDER = 'slider', DIMENSIONS = 'dimensions';
	}
	class Repeater extends Widget_Base { public function get_controls() { return $this->controls; } }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Icons_Manager { public static function render_icon( $icon, $args ) { echo '<svg class="custom-icon" aria-hidden="true"><path d="M1 1h8v8H1z" /></svg>'; } }
	class Plugin { public static $instance; }
	class Listing_Test_Breakpoint {
		private $width;
		private $direction;
		public function __construct( $width, $direction = 'max' ) { $this->width = $width; $this->direction = $direction; }
		public function get_value() { return $this->width; }
		public function get_direction() { return $this->direction; }
	}
}
namespace {
	error_reporting( E_ALL );
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['listing_hooks'] = [];
	$GLOBALS['listing_styles'] = [];
	$GLOBALS['listing_scripts'] = [];
	$GLOBALS['listing_breakpoints'] = [ 'mobile' => new \Elementor\Listing_Test_Breakpoint( 767 ), 'tablet' => new \Elementor\Listing_Test_Breakpoint( 1024 ) ];
	\Elementor\Plugin::$instance = (object) [ 'breakpoints' => new class {
		public function get_active_breakpoints() { return $GLOBALS['listing_breakpoints']; }
	} ];
	function add_action( $hook, $callback ) { $GLOBALS['listing_hooks'][ $hook ][] = $callback; }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $value ) { return esc_attr( $value ); }
	function esc_html__( $value, $domain = '' ) { return esc_html( $value ); }
	function __( $value, $domain = '' ) { return $value; }
	function _n( $singular, $plural, $number, $domain ) { return 1 === $number ? $singular : $plural; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function plugins_url( $path, $file ) { return $path; }
	function wp_register_style( $handle, $path, $deps, $version ) { $GLOBALS['listing_styles'][ $handle ] = $path; }
	function wp_register_script( $handle, $path, $deps, $version, $footer ) { $GLOBALS['listing_scripts'][ $handle ] = compact( 'path', 'deps', 'footer' ); }
	class Marrison_Addon {
		public static function plugin_file() { return __FILE__; }
		public static function asset_version( $path ) { return 'test'; }
	}
	$base = dirname( __DIR__ ) . '/marrison-addon/';
	require $base . 'includes/modules/class-marrison-addon-listing-columns.php';
	require $base . 'includes/modules/listing-columns/widgets/listing-columns-widget.php';
	function check( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	function render_listing_toolbar( $settings = [] ) {
		$widget = new \Marrison_Addon\Modules\Listing_Columns\Widgets\Listing_Columns_Widget();
		$widget->settings = $settings;
		$render = new \ReflectionMethod( $widget, 'render' );
		if ( PHP_VERSION_ID < 80100 ) { $render->setAccessible( true ); }
		ob_start(); $render->invoke( $widget ); return ob_get_clean();
	}
	if ( in_array( $argv[1] ?? '', [ '--fixture', '--responsive-fixture' ], true ) ) {
		$css = file_get_contents( $base . 'assets/css/marrison-listing-columns.css' );
		$js = file_get_contents( $base . 'assets/js/marrison-listing-columns.js' );
		$settings = [ 'target_selector' => '#catalogo' ];
		if ( '--responsive-fixture' === $argv[1] ) {
			$GLOBALS['listing_breakpoints'] = [ 'mobile' => new \Elementor\Listing_Test_Breakpoint( 680 ), 'tablet' => new \Elementor\Listing_Test_Breakpoint( 1100 ), 'mobile_extra' => new \Elementor\Listing_Test_Breakpoint( 900 ), 'laptop' => new \Elementor\Listing_Test_Breakpoint( 1400 ), 'widescreen' => new \Elementor\Listing_Test_Breakpoint( 1800, 'min' ) ];
			$settings += [ 'min_viewport' => 0, 'min_columns' => 2, 'max_columns' => 6, 'default_columns' => 4, 'max_columns_laptop' => 5, 'min_columns_tablet' => 1, 'max_columns_tablet' => 3, 'default_columns_tablet' => 2, 'max_columns_mobile' => 2, 'default_columns_mobile' => 1, 'min_columns_widescreen' => 4, 'max_columns_widescreen' => 8, 'default_columns_widescreen' => 6 ];
		}
		$toolbar = render_listing_toolbar( $settings );
		$cards = '';
		for ( $i = 1; $i <= 8; $i++ ) {
			$cards .= '<article class="jet-listing-grid__item"><div class="photo"></div><strong>Prodotto ' . $i . '</strong><p>Descrizione del prodotto</p></article>';
		}
		echo '<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Colonne Listing — fixture locale</title><style>body{font:16px system-ui;margin:40px auto;padding:0 24px;max-width:1100px;color:#222}h1{font-size:24px}.jet-listing-grid__items{display:flex;flex-wrap:wrap;gap:16px}.jet-listing-grid__item{box-sizing:border-box;width:calc(50% - 8px);border:1px solid #ddd;border-radius:10px;padding:14px}.photo{height:90px;background:#e5e9ec;border-radius:5px;margin-bottom:14px}article p{font-size:13px;color:#666}#toolbar{margin-bottom:20px}#second{margin-top:24px}@media(max-width:767px){.jet-listing-grid__item{width:100%}}' . $css . '</style><h1>Colonne Listing</h1><p>Fixture locale del widget: seleziona la disposizione del catalogo.</p><div id="toolbar">' . $toolbar . '</div><div id="catalogo"><div class="jet-listing-grid"><div class="jet-listing-grid__items">' . $cards . '</div></div></div><div id="second">' . $toolbar . '</div><script>' . $js . '</script></html>';
		exit;
	}
	$module = new \Marrison_Addon_Listing_Columns();
	check( ! $GLOBALS['listing_styles'] && ! $GLOBALS['listing_scripts'], 'Construction must not enqueue assets.' );
	$GLOBALS['listing_hooks']['elementor/frontend/after_register_styles'][0]();
	$GLOBALS['listing_hooks']['elementor/frontend/after_register_scripts'][0]();
	$widget = new \Marrison_Addon\Modules\Listing_Columns\Widgets\Listing_Columns_Widget();
	check( $widget->get_script_depends() === [ 'marrison-listing-columns' ] && $widget->get_style_depends() === [ 'marrison-listing-columns' ], 'Missing widget-only dependencies.' );
	check( $GLOBALS['listing_scripts']['marrison-listing-columns']['deps'] === [ 'jquery', 'elementor-frontend' ], 'Editor/frontend initialization dependency missing.' );
	$controls = new \ReflectionMethod( $widget, 'register_controls' );
	if ( PHP_VERSION_ID < 80100 ) { $controls->setAccessible( true ); }
	$controls->invoke( $widget );
	check( $widget->controls['min_columns']['min'] === 1 && $widget->controls['max_columns']['max'] === 12, 'Column range controls missing.' );
	foreach ( [ 'min_columns', 'max_columns', 'default_columns' ] as $id ) {
		check( in_array( $id, $widget->responsive, true ), 'Column control must use native responsive settings: ' . $id );
	}
	foreach ( [ 'normal', 'hover', 'active', 'focus' ] as $state ) {
		check( isset( $widget->controls[ $state . '_color' ], $widget->controls[ $state . '_icon_color' ], $widget->groups[ $state . '_background' ], $widget->groups[ $state . '_border' ], $widget->groups[ $state . '_shadow' ] ), 'Missing style state: ' . $state );
	}
	$html = render_listing_toolbar();
	check( substr_count( $html, '<button ' ) === 3 && substr_count( $html, 'aria-pressed="true"' ) === 1 && substr_count( $html, '<rect ' ) === 18, 'Default 2/3/4 buttons or distinct grid icons incorrect.' );
	check( false === strpos( $html, '<script' ) && false === strpos( $html, '<style' ), 'Render must use shared assets.' );
	$html = render_listing_toolbar( [ 'min_columns' => -7, 'max_columns' => 999, 'default_columns' => 100 ] );
	check( substr_count( $html, '<button ' ) === 12 && preg_match( '/data-cols="12"[^>]*aria-pressed="true"/', $html ), 'Range/default clamps failed.' );
	$html = render_listing_toolbar( [ 'min_columns' => 5, 'max_columns' => 2, 'default_columns' => 1 ] );
	check( substr_count( $html, '<button ' ) === 1 && false !== strpos( $html, 'data-cols="5"' ), 'Reversed range must produce a valid button.' );
	$html = render_listing_toolbar( [ 'display_mode' => 'number', 'target_selector' => '#x" onfocus="bad', 'group_label' => '"><script>bad</script>', 'custom_buttons' => [ [ 'columns' => 2, 'label' => '<img src=x onerror=bad>' ] ] ] );
	check( false === strpos( $html, '<svg' ) && false === strpos( $html, '<script>' ) && false === strpos( $html, '<img' ) && false !== strpos( $html, '&lt;img' ), 'Output escaping or number mode failed.' );
	$html = render_listing_toolbar( [ 'display_mode' => 'icon', 'custom_buttons' => [ [ 'columns' => 3, 'icon' => [ 'value' => 'fas fa-star', 'library' => 'fa-solid' ] ] ] ] );
	check( false === strpos( $html, 'class="marrison-listing-columns__label"' ) && false !== strpos( $html, 'class="custom-icon"' ) && substr_count( $html, 'aria-label="' ) === 4, 'Native icon override or icon-only accessibility failed.' );
	$html = render_listing_toolbar( [ 'min_columns' => 2, 'max_columns' => 6, 'default_columns' => 5, 'min_columns_tablet' => 1, 'max_columns_tablet' => 3, 'default_columns_tablet' => 2, 'max_columns_mobile' => 2, 'default_columns_mobile' => '' ] );
	preg_match( '/data-marrison-listing-columns="([^"]+)"/', $html, $match );
	$config = json_decode( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ), true );
	$profiles = array_column( $config['profiles'], null, 'name' );
	check( 6 === substr_count( $html, '<button ' ) && 1 === substr_count( $html, ' hidden' ), 'All responsive options must render with desktop-only visibility initially.' );
	check( 1024 === $profiles['tablet']['width'] && 767 === $profiles['mobile']['width'] && 1 === $profiles['mobile']['minColumns'] && 2 === $profiles['mobile']['defaultColumns'], 'Active breakpoint widths or empty-value inheritance failed.' );
	$html = render_listing_toolbar( [ 'min_columns' => 2, 'max_columns' => 6, 'default_columns' => 5, 'min_columns_tablet' => 4, 'max_columns_tablet' => 1, 'default_columns_tablet' => 1 ] );
	preg_match( '/data-marrison-listing-columns="([^"]+)"/', $html, $match );
	$config = json_decode( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ), true );
	$profiles = array_column( $config['profiles'], null, 'name' );
	check( 4 === $profiles['tablet']['maxColumns'] && 4 === $profiles['mobile']['defaultColumns'], 'Each inherited responsive profile must normalize its own range/default.' );
	echo "Listing Columns PHP: conditional assets, style states, 1–12 range, clamps, SVG icons, native icons, labels and escaping PASS.\n";
}
