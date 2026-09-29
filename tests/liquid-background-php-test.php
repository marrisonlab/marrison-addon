<?php
/**
 * Run with: php tests/liquid-background-php-test.php
 * Small WordPress/Elementor stubs for the Liquid Background module contract.
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
		const COLOR = 'color';
		const SLIDER = 'slider';
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
	function sanitize_hex_color( $value ) {
		return is_string( $value ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $value ) ? $value : null;
	}
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function plugins_url( $path ) { return $path; }
	function wp_enqueue_style( $handle ) { $GLOBALS['marrison_test_assets'][] = $handle; }
	function wp_enqueue_script( $handle ) { $GLOBALS['marrison_test_assets'][] = $handle; }
	function admin_url( $path = '' ) { return '/wp-admin/' . $path; }

	function check( $condition, $message ) {
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
	}

	class Fake_Liquid_Container {
		private $settings;
		private $id;
		public $attributes = [];
		public $controls = [];
		public function __construct( $settings, $id = 'liquid-a' ) {
			$this->settings = $settings;
			$this->id = $id;
		}
		public function get_data( $name ) { return 'settings' === $name ? $this->settings : null; }
		public function get_id() { return $this->id; }
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
	check( ! class_exists( 'Marrison_Addon_Liquid_Background', false ), 'Off module loaded its PHP file.' );
	check( empty( $GLOBALS['marrison_test_hooks']['elementor/frontend/container/before_render'] ), 'Off module registered an Elementor render hook.' );

	$GLOBALS['marrison_test_modules'] = [ 'liquid_background' => 1 ];
	new \Marrison_Addon();
	check( class_exists( 'Marrison_Addon_Liquid_Background', false ), 'Enabled module PHP was not loaded.' );
	check( empty( $GLOBALS['marrison_test_hooks']['elementor/frontend/container/before_render'] ), 'Elementor-inactive module registered a render hook.' );
	check( empty( $GLOBALS['marrison_test_assets'] ), 'Enabled module loaded assets without an enabled Container.' );

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
	$preview_hooks = $GLOBALS['marrison_test_hooks']['elementor/preview/enqueue_scripts'] ?? [];
	check( 1 === count( $preview_hooks ), 'Elementor preview enqueue hook was not registered exactly once.' );

	$control_container = new Fake_Liquid_Container( [] );
	$control_hooks[0]( $control_container );
	check( '' === $control_container->controls['marrison_liquid_enabled']['default'], 'The per-container control should default off.' );
	check( 'deep-purple' === $control_container->controls['marrison_liquid_preset']['default'], 'Deep Purple should be the default preset.' );
	check( '#000000' === $control_container->controls['marrison_liquid_background']['default'], 'Background color default differs.' );
	check( '#6C3BFF' === $control_container->controls['marrison_liquid_primary']['default'], 'Primary liquid color default differs.' );
	check( '#321875' === $control_container->controls['marrison_liquid_secondary']['default'], 'Secondary liquid color default differs.' );
	check( '' === $control_container->controls['marrison_liquid_blend_gradient']['default'], 'Bottom blend gradient should default off.' );
	check( '#000000' === $control_container->controls['marrison_liquid_blend_color']['default'], 'Blend color default differs.' );
	check( [ 'desktop', 'tablet', 'mobile' ] === $control_container->controls['marrison_liquid_blend_height']['devices'], 'Blend height should be responsive.' );
	check( '' === $control_container->controls['marrison_liquid_disable_mobile']['default'], 'Disable on Mobile should default off.' );
	check( 'static' === $control_container->controls['marrison_liquid_reduced_motion']['default'], 'Reduced motion should default to Static.' );
	check( true === $control_container->controls['marrison_liquid_speed']['frontend_available'], 'Speed should be available to the editor frontend.' );
	check( [ 'desktop', 'tablet', 'mobile' ] === $control_container->controls['marrison_liquid_scale']['devices'], 'Scale should be responsive.' );
	check( [ 'desktop', 'tablet', 'mobile' ] === $control_container->controls['marrison_liquid_opacity']['devices'], 'Opacity should be responsive.' );

	$before_render( new Fake_Liquid_Container( [] ) );
	check( ! $GLOBALS['marrison_test_assets'], 'Container without Liquid Background loaded assets.' );

	$container = new Fake_Liquid_Container( [
		'marrison_liquid_enabled' => 'yes',
		'marrison_liquid_preset' => 'custom',
		'marrison_liquid_background' => '#111111',
		'marrison_liquid_primary' => '#ABCDEF',
		'marrison_liquid_secondary' => '',
		'marrison_liquid_speed' => [ 'size' => 9 ],
		'marrison_liquid_speed_tablet' => [ 'size' => 0.01 ],
		'marrison_liquid_scale' => [ 'size' => 1.4 ],
		'marrison_liquid_scale_mobile' => [ 'size' => 1.8 ],
		'marrison_liquid_complexity' => [ 'size' => 9 ],
		'marrison_liquid_distortion' => [ 'size' => -1 ],
		'marrison_liquid_softness' => [ 'size' => 0 ],
		'marrison_liquid_contrast' => [ 'size' => 8 ],
		'marrison_liquid_opacity' => [ 'size' => 0.7 ],
		'marrison_liquid_blend_gradient' => 'yes',
		'marrison_liquid_blend_color' => '#222222',
		'marrison_liquid_blend_height' => [ 'size' => 150 ],
		'marrison_liquid_blend_height_tablet' => [ 'size' => 5 ],
		'marrison_liquid_blend_height_mobile' => [ 'size' => 66 ],
		'marrison_liquid_mouse' => 'yes',
		'marrison_liquid_mouse_influence' => [ 'size' => 2 ],
		'marrison_liquid_direction' => 'diagonal',
		'marrison_liquid_disable_mobile' => 'yes',
		'marrison_liquid_reduced_motion' => 'disable',
		'marrison_liquid_seed' => '',
	], 'liquid-main' );
	$before_render( $container );
	$config = json_decode( $container->attributes['data-marrison-liquid-background'] ?? '', true );
	check( is_array( $config ), 'Enabled Container has no usable configuration.' );
	check( '#111111' === $config['background'] && '#ABCDEF' === $config['primary'] && '' === $config['secondary'], 'Custom colors were not preserved.' );
	check( 3.0 === (float) $config['speed']['desktop'] && 0.1 === (float) $config['speed']['tablet'] && 0.1 === (float) $config['speed']['mobile'], 'Responsive speed was not bounded.' );
	check( 1.4 === (float) $config['scale']['desktop'] && 1.4 === (float) $config['scale']['tablet'] && 1.8 === (float) $config['scale']['mobile'], 'Responsive scale fallback differs.' );
	check( 4 === $config['complexity'] && 0.0 === (float) $config['distortion'] && 0.1 === (float) $config['softness'], 'Numeric bounds were not normalized.' );
	check( 2.0 === (float) $config['contrast'] && 0.5 === (float) $config['mouseInfluence'], 'Contrast or mouse influence bounds differ.' );
	check( true === $config['blendGradient']['enabled'] && '#222222' === $config['blendGradient']['color'], 'Blend gradient config differs.' );
	check( 100.0 === (float) $config['blendGradient']['height']['desktop'] && 10.0 === (float) $config['blendGradient']['height']['tablet'] && 66.0 === (float) $config['blendGradient']['height']['mobile'], 'Blend gradient height was not bounded.' );
	check( 'natural' === $config['direction'] && true === $config['disableMobile'] && 'disable' === $config['reducedMotion'], 'Enum settings were not normalized.' );
	check( 760 === $config['breakpoints']['mobile'] && 1030 === $config['breakpoints']['tablet'], 'Elementor breakpoints were not used.' );
	check( is_int( $config['seed'] ) && $config['seed'] >= 0 && $config['seed'] <= 99999, 'Stable generated seed is out of bounds.' );
	check( 2 === count( $GLOBALS['marrison_test_assets'] ), 'CSS and JS were not enqueued once.' );
	$before_render( new Fake_Liquid_Container( [ 'marrison_liquid_enabled' => 'yes' ] ) );
	check( 2 === count( $GLOBALS['marrison_test_assets'] ), 'Multiple instances enqueued assets twice.' );

	$cached_module = new \Marrison_Addon_Liquid_Background();
	$cached_data = [
		[ 'elType' => 'container', 'settings' => [], 'elements' => [
			[ 'elType' => 'container', 'settings' => [ 'marrison_liquid_enabled' => 'yes' ], 'elements' => [] ],
		] ],
	];
	$cached_module->maybe_enqueue_assets_from_data( $cached_data );
	check( 4 === count( $GLOBALS['marrison_test_assets'] ), 'Cached nested Container did not enqueue frontend assets.' );
	$cached_module->maybe_enqueue_assets_from_data( $cached_data );
	check( 4 === count( $GLOBALS['marrison_test_assets'] ), 'Cached document enqueued assets twice.' );

	$GLOBALS['marrison_test_assets'] = [];
	$_REQUEST['elementor-preview'] = '1';
	$editor_container = new Fake_Liquid_Container( [ 'marrison_liquid_enabled' => 'yes' ] );
	$before_render( $editor_container );
	check( isset( $editor_container->attributes['data-marrison-liquid-background'] ), 'Editor preview did not receive the Liquid config attribute.' );
	check( ! $GLOBALS['marrison_test_assets'], 'Editor before_render should not enqueue public frontend assets.' );
	$preview_module = new \Marrison_Addon_Liquid_Background();
	$preview_module->enqueue_preview_assets();
	check( 2 === count( $GLOBALS['marrison_test_assets'] ), 'Editor preview hook did not enqueue CSS and JS.' );
	$preview_module->enqueue_preview_assets();
	check( 2 === count( $GLOBALS['marrison_test_assets'] ), 'Editor preview assets enqueued twice.' );
	unset( $_REQUEST['elementor-preview'] );

	echo "Liquid background PHP contract: PASS\n";
}
