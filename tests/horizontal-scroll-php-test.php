<?php
/**
 * Run with: php tests/horizontal-scroll-php-test.php
 * Small WordPress/Elementor stubs for the module loading contract.
 */
namespace Elementor {
	class Plugin {
		public static $instance;
	}
	class Controls_Manager {
		const TAB_ADVANCED = 'advanced';
		const SWITCHER = 'switcher';
		const SELECT = 'select';
		const NUMBER = 'number';
	}
}

namespace {
	error_reporting( E_ALL );
	ini_set( 'display_errors', '1' );
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['marrison_test_modules'] = [];
	$GLOBALS['marrison_test_elementor_loaded'] = false;
	$GLOBALS['marrison_test_hooks'] = [];
	$GLOBALS['marrison_test_assets'] = [];

	function add_action( $name, $callback ) {
		$GLOBALS['marrison_test_hooks'][ $name ][] = $callback;
	}
	function add_filter( $name, $callback ) {
		$GLOBALS['marrison_test_hooks'][ $name ][] = $callback;
	}
	function esc_html__( $text ) { return $text; }
	function get_option( $name, $default = [] ) {
		return 'marrison_addon_modules' === $name ? $GLOBALS['marrison_test_modules'] : $default;
	}
	function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
	function plugin_basename( $file ) { return basename( $file ); }
	function is_admin() { return false; }
	function did_action( $name ) {
		return 'elementor/loaded' === $name && $GLOBALS['marrison_test_elementor_loaded'] ? 1 : 0;
	}
	function wp_doing_ajax() { return false; }
	function wp_is_json_request() { return false; }
	function is_customize_preview() { return false; }
	function wp_unslash( $value ) { return $value; }
	function sanitize_text_field( $value ) { return $value; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function plugins_url( $path ) { return $path; }
	function wp_enqueue_style( $handle ) { $GLOBALS['marrison_test_assets'][] = $handle; }
	function wp_enqueue_script( $handle ) { $GLOBALS['marrison_test_assets'][] = $handle; }

	function check( $condition, $message ) {
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
	}

	class Fake_Horizontal_Container {
		private $settings;
		public $attributes = [];
		public $controls = [];
		public function __construct( $settings ) { $this->settings = $settings; }
		public function get_data( $name ) { return 'settings' === $name ? $this->settings : null; }
		public function add_render_attribute( $wrapper, $key, $value ) { $this->attributes[ $key ] = $value; }
		public function start_controls_section( $name, $options ) { $this->controls[ $name ] = $options; }
		public function add_control( $name, $options ) { $this->controls[ $name ] = $options; }
		public function add_responsive_control( $name, $options ) { $this->controls[ $name ] = $options; }
		public function end_controls_section() {}
	}

	$plugin_file = dirname( __DIR__ ) . '/marrison-addon.php';
	if ( ! file_exists( $plugin_file ) ) {
		$plugin_file = dirname( __DIR__ ) . '/marrison-addon/marrison-addon.php';
	}
	require $plugin_file;
	new \Marrison_Addon();
	check( ! class_exists( 'Marrison_Addon_Horizontal_Scroll', false ), 'Off module loaded its PHP file.' );
	check( empty( $GLOBALS['marrison_test_hooks']['elementor/frontend/container/before_render'] ), 'Off module registered an Elementor render hook.' );

	$GLOBALS['marrison_test_modules'] = [ 'horizontal_scroll' => 1 ];
	new \Marrison_Addon();
	check( class_exists( 'Marrison_Addon_Horizontal_Scroll', false ), 'Enabled module PHP was not loaded.' );
	check( empty( $GLOBALS['marrison_test_hooks']['elementor/frontend/container/before_render'] ), 'Elementor-inactive module registered a render hook.' );

	$GLOBALS['marrison_test_elementor_loaded'] = true;
	\Elementor\Plugin::$instance = (object) [
		'breakpoints' => new class {
			public function get_active_breakpoints() {
				return [
					'mobile' => new class { public function get_value() { return 760; } },
					'tablet' => new class { public function get_value() { return 1030; } },
				];
			}
		},
	];
	new \Marrison_Addon();
	$render_hooks = $GLOBALS['marrison_test_hooks']['elementor/frontend/container/before_render'] ?? [];
	check( 1 === count( $render_hooks ), 'Module render hook was not registered exactly once.' );
	$before_render = $render_hooks[0];
	$control_hooks = $GLOBALS['marrison_test_hooks']['elementor/element/container/section_layout/after_section_end'] ?? [];
	check( 1 === count( $control_hooks ), 'Container controls were not registered exactly once.' );
	$data_hooks = $GLOBALS['marrison_test_hooks']['elementor/frontend/builder_content_data'] ?? [];
	check( 1 === count( $data_hooks ), 'Elementor document data hook was not registered exactly once.' );
	$control_container = new Fake_Horizontal_Container( [] );
	$control_hooks[0]( $control_container );
	check( '' === $control_container->controls['marrison_horizontal_scroll_enabled']['default'], 'The per-container control should default off.' );
	check( 'yes' === $control_container->controls['marrison_horizontal_scroll_pin']['default'], 'Pin should default on.' );
	check( '' === $control_container->controls['marrison_horizontal_scroll_snap']['default'], 'Snap should default off.' );
	check( '' === $control_container->controls['marrison_horizontal_scroll_background_scale']['default'], 'Background scale should default off.' );
	check( 0 === $control_container->controls['marrison_horizontal_scroll_background_scale_from']['default'], 'Background scale should start from 0 by default.' );
	check( 100 === $control_container->controls['marrison_horizontal_scroll_background_scale_to']['default'], 'Background scale should end at 100 by default.' );
	check( '' === $control_container->controls['marrison_horizontal_scroll_device']['mobile_default'], 'Mobile should default off.' );

	$before_render( new Fake_Horizontal_Container( [] ) );
	check( ! $GLOBALS['marrison_test_assets'], 'Container without scroll loaded assets.' );

	$_REQUEST['elementor-preview'] = '1';
	$editor_container = new Fake_Horizontal_Container( [ 'marrison_horizontal_scroll_enabled' => 'yes' ] );
	$before_render( $editor_container );
	check( ! $editor_container->attributes && ! $GLOBALS['marrison_test_assets'], 'Editor preview received frontend behavior.' );
	unset( $_REQUEST['elementor-preview'] );

	$container = new Fake_Horizontal_Container( [
		'marrison_horizontal_scroll_enabled' => 'yes',
		'marrison_horizontal_scroll_direction' => 'ltr',
		'marrison_horizontal_scroll_speed' => 2,
		'marrison_horizontal_scroll_snap' => 'yes',
		'marrison_horizontal_scroll_background_scale' => 'yes',
		'marrison_horizontal_scroll_background_scale_from' => 0,
		'marrison_horizontal_scroll_background_scale_to' => 100,
	] );
	$before_render( $container );
	$config = json_decode( $container->attributes['data-marrison-horizontal-scroll'] ?? '', true );
	check( is_array( $config ) && 'ltr' === $config['direction'], 'Enabled container has no usable configuration.' );
	check( true === $config['snap'], 'Snap config differs.' );
	check( 2.0 === (float) $config['speed'] && true === $config['devices']['desktop'] && true === $config['devices']['tablet'] && false === $config['devices']['mobile'], 'Speed or responsive defaults differ.' );
	check( true === $config['backgroundScale']['enabled'] && 0.0 === (float) $config['backgroundScale']['from'] && 1.0 === (float) $config['backgroundScale']['to'], 'Background scale config differs.' );
	check( 760 === $config['breakpoints']['mobile'] && 1030 === $config['breakpoints']['tablet'], 'Elementor breakpoints were not used.' );
	check( 2 === count( $GLOBALS['marrison_test_assets'] ), 'CSS and JS were not enqueued once.' );
	$before_render( new Fake_Horizontal_Container( [ 'marrison_horizontal_scroll_enabled' => 'yes' ] ) );
	check( 2 === count( $GLOBALS['marrison_test_assets'] ), 'Multiple instances enqueued assets twice.' );
	$invalid_container = new Fake_Horizontal_Container( [
		'marrison_horizontal_scroll_enabled' => 'yes',
		'marrison_horizontal_scroll_direction' => '<invalid>',
		'marrison_horizontal_scroll_speed' => 100,
		'marrison_horizontal_scroll_pin' => '',
		'marrison_horizontal_scroll_snap' => '',
		'marrison_horizontal_scroll_device' => '',
		'marrison_horizontal_scroll_background_scale' => '',
		'marrison_horizontal_scroll_background_scale_from' => -50,
		'marrison_horizontal_scroll_background_scale_to' => 400,
	] );
	$before_render( $invalid_container );
	$invalid_config = json_decode( $invalid_container->attributes['data-marrison-horizontal-scroll'], true );
	check( 'rtl' === $invalid_config['direction'] && 4 === $invalid_config['speed'] && false === $invalid_config['pin'] && false === $invalid_config['devices']['desktop'], 'Invalid settings were not normalized.' );
	check( false === $invalid_config['snap'], 'Snap should normalize to false when disabled.' );
	check( false === $invalid_config['backgroundScale']['enabled'] && 0.0 === (float) $invalid_config['backgroundScale']['from'] && 2.0 === (float) $invalid_config['backgroundScale']['to'], 'Background scale bounds were not normalized.' );

	$cached_module = new \Marrison_Addon_Horizontal_Scroll();
	$cached_data = [
		[ 'elType' => 'container', 'settings' => [], 'elements' => [
			[ 'elType' => 'container', 'settings' => [ 'marrison_horizontal_scroll_enabled' => 'yes' ], 'elements' => [] ],
		] ],
	];
	$_REQUEST['elementor-preview'] = '1';
	check( $cached_data === $cached_module->maybe_enqueue_assets_from_data( $cached_data ), 'Document data was changed in editor preview.' );
	check( 2 === count( $GLOBALS['marrison_test_assets'] ), 'Editor preview loaded cached Container assets.' );
	unset( $_REQUEST['elementor-preview'] );
	check( $cached_data === $cached_module->maybe_enqueue_assets_from_data( $cached_data ), 'Document data was changed on frontend.' );
	check( 4 === count( $GLOBALS['marrison_test_assets'] ), 'Cached nested Container did not enqueue frontend assets.' );
	$cached_module->maybe_enqueue_assets_from_data( $cached_data );
	check( 4 === count( $GLOBALS['marrison_test_assets'] ), 'Cached document enqueued assets twice.' );

	$plain_data = [
		[ 'elType' => 'container', 'settings' => [], 'elements' => [] ],
	];
	$plain_module = new \Marrison_Addon_Horizontal_Scroll();
	$plain_module->maybe_enqueue_assets_from_data( $plain_data );
	check( 4 === count( $GLOBALS['marrison_test_assets'] ), 'Document without Scroll Orizzontale enqueued assets.' );

	echo "Horizontal scroll PHP contract: PASS\n";
}
