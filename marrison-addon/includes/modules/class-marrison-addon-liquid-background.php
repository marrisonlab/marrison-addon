<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A procedural background for explicitly enabled Elementor Containers.
 */
class Marrison_Addon_Liquid_Background {

	private $assets_enqueued = false;

	public function __construct() {
		add_action( 'elementor/element/container/section_layout/after_section_end', [ $this, 'register_controls' ] );
		add_action( 'elementor/frontend/container/before_render', [ $this, 'before_render' ] );
		add_filter( 'elementor/frontend/builder_content_data', [ $this, 'maybe_enqueue_assets_from_data' ] );
		// The preview needs the script before an initially disabled Container is switched on.
		add_action( 'elementor/preview/enqueue_scripts', [ $this, 'enqueue_preview_assets' ] );
	}

	public function register_controls( $container ) {
		$condition = [ 'marrison_liquid_enabled' => 'yes' ];
		$custom_condition = [
			'marrison_liquid_enabled' => 'yes',
			'marrison_liquid_preset'  => 'custom',
		];

		$container->start_controls_section(
			'marrison_liquid_section',
			[
				'label' => esc_html__( 'Marrison — Liquid Background', 'marrison-addon' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			]
		);

		$container->add_control( 'marrison_liquid_enabled', [
			'label'              => esc_html__( 'Enable Liquid Background', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::SWITCHER,
			'return_value'       => 'yes',
			'default'            => '',
			'frontend_available' => true,
		] );

		$container->add_control( 'marrison_liquid_preset', [
			'label'              => esc_html__( 'Preset', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::SELECT,
			'options'            => [
				'deep-purple' => esc_html__( 'Deep Purple', 'marrison-addon' ),
				'blue-ink'    => esc_html__( 'Blue Ink', 'marrison-addon' ),
				'monochrome'  => esc_html__( 'Monochrome', 'marrison-addon' ),
				'custom'      => esc_html__( 'Custom', 'marrison-addon' ),
			],
			'default'            => 'deep-purple',
			'condition'          => $condition,
			'frontend_available' => true,
		] );

		$container->add_control( 'marrison_liquid_background', [
			'label'              => esc_html__( 'Background Color', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::COLOR,
			'default'            => '#000000',
			'condition'          => $custom_condition,
			'frontend_available' => true,
		] );
		$container->add_control( 'marrison_liquid_primary', [
			'label'              => esc_html__( 'Primary Liquid Color', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::COLOR,
			'default'            => '#6C3BFF',
			'condition'          => $custom_condition,
			'frontend_available' => true,
		] );
		$container->add_control( 'marrison_liquid_secondary', [
			'label'              => esc_html__( 'Secondary Liquid Color', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::COLOR,
			'default'            => '#321875',
			'condition'          => $custom_condition,
			'description'        => esc_html__( 'Lascia vuoto per usare solo il colore principale.', 'marrison-addon' ),
			'frontend_available' => true,
		] );

		$this->add_slider( $container, 'marrison_liquid_speed', 'Animation Speed', 0.1, 3, 0.05, 0.35, true );
		$this->add_slider( $container, 'marrison_liquid_scale', 'Scale / Blob Size', 0.65, 2, 0.05, 1, true );
		$this->add_slider( $container, 'marrison_liquid_complexity', 'Complexity', 1, 4, 1, 3 );
		$this->add_slider( $container, 'marrison_liquid_distortion', 'Distortion', 0, 1.5, 0.05, 0.5 );
		$this->add_slider( $container, 'marrison_liquid_softness', 'Softness', 0.1, 1, 0.05, 0.45 );
		$this->add_slider( $container, 'marrison_liquid_contrast', 'Contrast', 0.5, 2, 0.05, 1 );
		$this->add_slider( $container, 'marrison_liquid_opacity', 'Opacity', 0, 1, 0.05, 1, true );

		$container->add_control( 'marrison_liquid_blend_gradient', [
			'label'              => esc_html__( 'Bottom Blend Gradient', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::SWITCHER,
			'return_value'       => 'yes',
			'default'            => '',
			'condition'          => $condition,
			'description'        => esc_html__( 'Aggiunge un layer trasparente in alto e pieno in basso per fondere il Container successivo.', 'marrison-addon' ),
			'frontend_available' => true,
		] );
		$container->add_control( 'marrison_liquid_blend_color', [
			'label'              => esc_html__( 'Blend Color', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::COLOR,
			'default'            => '#000000',
			'condition'          => [
				'marrison_liquid_enabled'        => 'yes',
				'marrison_liquid_blend_gradient' => 'yes',
			],
			'frontend_available' => true,
		] );
		$container->add_responsive_control( 'marrison_liquid_blend_height', [
			'label'              => esc_html__( 'Blend Height', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::SLIDER,
			'size_units'         => [ '%' ],
			'range'              => [ '%' => [ 'min' => 10, 'max' => 100, 'step' => 1 ] ],
			'default'            => [ 'size' => 45, 'unit' => '%' ],
			'devices'            => [ 'desktop', 'tablet', 'mobile' ],
			'condition'          => [
				'marrison_liquid_enabled'        => 'yes',
				'marrison_liquid_blend_gradient' => 'yes',
			],
			'frontend_available' => true,
		] );

		$container->add_control( 'marrison_liquid_mouse', [
			'label'              => esc_html__( 'Mouse Interaction', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::SWITCHER,
			'return_value'       => 'yes',
			'default'            => '',
			'condition'          => $condition,
			'frontend_available' => true,
		] );
		$this->add_slider( $container, 'marrison_liquid_mouse_influence', 'Mouse Influence', 0, 0.5, 0.025, 0.2, false, [
			'marrison_liquid_mouse' => 'yes',
		] );

		$container->add_control( 'marrison_liquid_direction', [
			'label'              => esc_html__( 'Animation Direction', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::SELECT,
			'options'            => [
				'natural'    => esc_html__( 'Natural', 'marrison-addon' ),
				'horizontal' => esc_html__( 'Horizontal', 'marrison-addon' ),
				'vertical'   => esc_html__( 'Vertical', 'marrison-addon' ),
			],
			'default'            => 'natural',
			'condition'          => $condition,
			'frontend_available' => true,
		] );
		$container->add_control( 'marrison_liquid_disable_mobile', [
			'label'              => esc_html__( 'Disable on Mobile', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::SWITCHER,
			'return_value'       => 'yes',
			'default'            => '',
			'condition'          => $condition,
			'frontend_available' => true,
		] );
		$container->add_control( 'marrison_liquid_reduced_motion', [
			'label'              => esc_html__( 'Reduced Motion Behavior', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::SELECT,
			'options'            => [
				'static'  => esc_html__( 'Static', 'marrison-addon' ),
				'disable' => esc_html__( 'Disable', 'marrison-addon' ),
			],
			'default'            => 'static',
			'condition'          => $condition,
			'frontend_available' => true,
		] );
		$container->add_control( 'marrison_liquid_seed', [
			'label'              => esc_html__( 'Seed', 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::NUMBER,
			'min'                => 0,
			'max'                => 99999,
			'step'               => 1,
			'default'            => '',
			'condition'          => $condition,
			'description'        => esc_html__( 'Vuoto: forma stabile generata dall’ID del Container.', 'marrison-addon' ),
			'frontend_available' => true,
		] );

		$container->end_controls_section();
	}

	private function add_slider( $container, $id, $label, $min, $max, $step, $default, $responsive = false, $extra_condition = [] ) {
		$definition = [
			'label'              => esc_html__( $label, 'marrison-addon' ),
			'type'               => \Elementor\Controls_Manager::SLIDER,
			'size_units'         => [ '' ],
			'range'              => [ '' => [ 'min' => $min, 'max' => $max, 'step' => $step ] ],
			'default'            => [ 'size' => $default, 'unit' => '' ],
			'condition'          => array_merge( [ 'marrison_liquid_enabled' => 'yes' ], $extra_condition ),
			'frontend_available' => true,
		];

		if ( $responsive ) {
			$definition['devices'] = [ 'desktop', 'tablet', 'mobile' ];
			$container->add_responsive_control( $id, $definition );
		} else {
			$container->add_control( $id, $definition );
		}
	}

	public function before_render( $container ) {
		if ( ! is_object( $container ) || ! method_exists( $container, 'get_data' ) || ! method_exists( $container, 'add_render_attribute' ) ) {
			return;
		}

		$settings = $container->get_data( 'settings' );
		if ( ! is_array( $settings ) || 'yes' !== ( $settings['marrison_liquid_enabled'] ?? '' ) ) {
			return;
		}

		$config = $this->build_config( $settings, method_exists( $container, 'get_id' ) ? $container->get_id() : '' );
		$container->add_render_attribute( '_wrapper', 'data-marrison-liquid-background', wp_json_encode( $config ) );

		if ( Marrison_Addon_Context::is_public_frontend_request() ) {
			$this->enqueue_assets();
		}
	}

	private function build_config( array $settings, $element_id ) {
		$presets = [
			'deep-purple' => [ '#000000', '#6C3BFF', '#321875' ],
			'blue-ink'    => [ '#020712', '#2869DA', '#102C77' ],
			'monochrome'  => [ '#000000', '#D9DCE5', '#555B69' ],
		];
		$preset = isset( $settings['marrison_liquid_preset'] ) && in_array( $settings['marrison_liquid_preset'], [ 'deep-purple', 'blue-ink', 'monochrome', 'custom' ], true )
			? $settings['marrison_liquid_preset'] : 'deep-purple';
		$colors = 'custom' === $preset
			? [
				$this->color( $settings['marrison_liquid_background'] ?? '', '#000000' ),
				$this->color( $settings['marrison_liquid_primary'] ?? '', '#6C3BFF' ),
				$this->color( array_key_exists( 'marrison_liquid_secondary', $settings ) ? $settings['marrison_liquid_secondary'] : '#321875', '' ),
			]
			: $presets[ $preset ];

		$seed = $settings['marrison_liquid_seed'] ?? '';
		if ( '' === $seed || null === $seed ) {
			$seed = hexdec( substr( md5( (string) $element_id ), 0, 8 ) ) % 100000;
		}

		$direction = $settings['marrison_liquid_direction'] ?? 'natural';
		if ( ! in_array( $direction, [ 'natural', 'horizontal', 'vertical' ], true ) ) {
			$direction = 'natural';
		}

		return [
			'preset'           => $preset,
			'background'       => $colors[0],
			'primary'          => $colors[1],
			'secondary'        => $colors[2],
			'speed'            => $this->responsive_slider( $settings, 'marrison_liquid_speed', 0.1, 3, 0.35 ),
			'scale'            => $this->responsive_slider( $settings, 'marrison_liquid_scale', 0.65, 2, 1 ),
			'complexity'       => (int) round( $this->slider( $settings['marrison_liquid_complexity'] ?? null, 1, 4, 3 ) ),
			'distortion'       => $this->slider( $settings['marrison_liquid_distortion'] ?? null, 0, 1.5, 0.5 ),
			'softness'         => $this->slider( $settings['marrison_liquid_softness'] ?? null, 0.1, 1, 0.45 ),
			'contrast'         => $this->slider( $settings['marrison_liquid_contrast'] ?? null, 0.5, 2, 1 ),
			'opacity'          => $this->responsive_slider( $settings, 'marrison_liquid_opacity', 0, 1, 1 ),
			'blendGradient'    => [
				'enabled' => 'yes' === ( $settings['marrison_liquid_blend_gradient'] ?? '' ),
				'color'   => $this->color( $settings['marrison_liquid_blend_color'] ?? '', '#000000' ),
				'height'  => $this->responsive_slider( $settings, 'marrison_liquid_blend_height', 10, 100, 45 ),
			],
			'mouse'            => 'yes' === ( $settings['marrison_liquid_mouse'] ?? '' ),
			'mouseInfluence'   => $this->slider( $settings['marrison_liquid_mouse_influence'] ?? null, 0, 0.5, 0.2 ),
			'direction'        => $direction,
			'disableMobile'    => 'yes' === ( $settings['marrison_liquid_disable_mobile'] ?? '' ),
			'reducedMotion'    => 'disable' === ( $settings['marrison_liquid_reduced_motion'] ?? '' ) ? 'disable' : 'static',
			'seed'             => (int) $this->number( $seed, 0, 99999, 0 ),
			'breakpoints'      => $this->get_breakpoints(),
		];
	}

	private function color( $value, $fallback ) {
		if ( '' === $value || null === $value ) {
			return $fallback;
		}

		$color = is_string( $value ) ? sanitize_hex_color( $value ) : null;
		return $color ?: $fallback;
	}

	private function number( $value, $min, $max, $fallback ) {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		$value = (float) $value;
		return is_finite( $value ) ? max( $min, min( $max, $value ) ) : $fallback;
	}

	private function slider( $value, $min, $max, $fallback ) {
		return $this->number( is_array( $value ) ? ( $value['size'] ?? null ) : $value, $min, $max, $fallback );
	}

	private function responsive_slider( array $settings, $id, $min, $max, $fallback ) {
		$desktop = $this->slider( $settings[ $id ] ?? null, $min, $max, $fallback );
		$tablet = $this->slider( $settings[ $id . '_tablet' ] ?? null, $min, $max, $desktop );
		$mobile = $this->slider( $settings[ $id . '_mobile' ] ?? null, $min, $max, $tablet );

		return [ 'desktop' => $desktop, 'tablet' => $tablet, 'mobile' => $mobile ];
	}

	private function get_breakpoints() {
		$values = [ 'mobile' => 767, 'tablet' => 1024 ];
		$plugin = \Elementor\Plugin::$instance;
		if ( ! isset( $plugin->breakpoints ) || ! method_exists( $plugin->breakpoints, 'get_active_breakpoints' ) ) {
			return $values;
		}

		foreach ( $plugin->breakpoints->get_active_breakpoints() as $name => $breakpoint ) {
			if ( isset( $values[ $name ] ) && method_exists( $breakpoint, 'get_value' ) ) {
				$values[ $name ] = (int) $breakpoint->get_value();
			}
		}

		return $values;
	}

	public function maybe_enqueue_assets_from_data( $elements ) {
		if ( Marrison_Addon_Context::is_public_frontend_request() && is_array( $elements ) && $this->has_enabled_container( $elements ) ) {
			$this->enqueue_assets();
		}

		return $elements;
	}

	private function has_enabled_container( array $elements ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( 'container' === ( $element['elType'] ?? '' ) && 'yes' === ( $element['settings']['marrison_liquid_enabled'] ?? '' ) ) {
				return true;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) && $this->has_enabled_container( $element['elements'] ) ) {
				return true;
			}
		}

		return false;
	}

	public function enqueue_preview_assets() {
		$this->enqueue_assets();
	}

	private function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}

		$this->assets_enqueued = true;
		$plugin_root_file = dirname( __DIR__, 2 ) . '/marrison-addon.php';
		$css_path = dirname( __DIR__, 2 ) . '/assets/css/marrison-liquid-background.css';
		$js_path = dirname( __DIR__, 2 ) . '/assets/js/marrison-liquid-background.js';

		wp_enqueue_style(
			'marrison-addon-liquid-background',
			plugins_url( 'assets/css/marrison-liquid-background.css', $plugin_root_file ),
			[],
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : Marrison_Addon::VERSION
		);
		wp_enqueue_script(
			'marrison-addon-liquid-background',
			plugins_url( 'assets/js/marrison-liquid-background.js', $plugin_root_file ),
			[],
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : Marrison_Addon::VERSION,
			true
		);
	}
}
