<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Header_Animations {

	private const ANIMATION_GROUP = 'Marrison';
	private const LETTER_ANIMATIONS = [
		'marrisonLettersRise',
		'marrisonLettersFocus',
		'marrisonLettersElastic',
	];

	public function __construct() {
		add_filter( 'elementor/controls/animations/additional_animations', [ $this, 'add_additional_animations' ] );
		add_action( 'elementor/element/heading/_section_effects/before_section_end', [ $this, 'register_animations' ] );
		add_action( 'elementor/element/heading/_section_effects/before_section_end', [ $this, 'register_fallback_control' ], 20 );
		add_action( 'elementor/element/common/_section_effects/before_section_end', [ $this, 'register_animations' ] );
		add_action( 'elementor/element/after_section_end', [ $this, 'limit_animations_to_heading' ], 10, 2 );
		add_action( 'elementor/frontend/widget/before_render', [ $this, 'before_render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'enqueue_styles' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function add_additional_animations( $animations ) {
		if ( ! is_array( $animations ) ) {
			$animations = [];
		}

		return $this->add_custom_animations( $animations );
	}

	public function register_fallback_control( $element ) {
		if ( ! method_exists( $element, 'add_control' ) ) {
			return;
		}

		if ( method_exists( $element, 'get_controls' ) ) {
			$controls = $element->get_controls();

			if ( isset( $controls['marrison_header_animation'] ) ) {
				return;
			}
		}

		$element->add_control(
			'marrison_header_animation',
			[
				'label' => esc_html__( 'Marrison Animation', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'separator' => 'before',
				'description' => esc_html__( 'Fallback control used if Elementor does not expose the Marrison animations in the native entrance animation list.', 'marrison-addon' ),
				'options' => array_merge(
					[ '' => esc_html__( 'Default', 'marrison-addon' ) ],
					$this->get_custom_animations()
				),
			]
		);
	}

	public function register_animations( $element ) {
		if ( ! method_exists( $element, 'get_controls' ) || ! method_exists( $element, 'update_control' ) ) {
			return;
		}

		if ( method_exists( $element, 'get_name' ) && 'heading' !== $element->get_name() ) {
			return;
		}

		$controls = $element->get_controls();
		$control_ids = [
			'_animation',
			'_animation_tablet',
			'_animation_mobile',
		];

		foreach ( $control_ids as $control_id ) {
			if ( empty( $controls[ $control_id ]['options'] ) ) {
				continue;
			}

			$element->update_control(
				$control_id,
				[
					'options' => $this->add_custom_animations( $controls[ $control_id ]['options'] ),
				]
			);
		}
	}

	public function limit_animations_to_heading( $element, $section_id ) {
		if ( ! method_exists( $element, 'get_name' ) || 'heading' === $element->get_name() ) {
			return;
		}

		if ( '_section_effects' !== $section_id || ! method_exists( $element, 'get_controls' ) || ! method_exists( $element, 'update_control' ) ) {
			return;
		}

		$controls = $element->get_controls();
		$control_ids = [
			'_animation',
			'_animation_tablet',
			'_animation_mobile',
		];

		foreach ( $control_ids as $control_id ) {
			if ( empty( $controls[ $control_id ]['options'] ) || ! isset( $controls[ $control_id ]['options'][ self::ANIMATION_GROUP ] ) ) {
				continue;
			}

			$options = $controls[ $control_id ]['options'];
			unset( $options[ self::ANIMATION_GROUP ] );

			$element->update_control(
				$control_id,
				[
					'options' => $options,
				]
			);
		}
	}

	public function before_render( $widget ) {
		if ( ! method_exists( $widget, 'get_name' ) || 'heading' !== $widget->get_name() || ! method_exists( $widget, 'get_settings_for_display' ) ) {
			return;
		}

		$settings = $widget->get_settings_for_display();

		if ( empty( $settings['marrison_header_animation'] ) ) {
			return;
		}

		$animation = sanitize_html_class( $settings['marrison_header_animation'] );

		if ( ! array_key_exists( $animation, $this->get_custom_animations() ) || ! method_exists( $widget, 'add_render_attribute' ) ) {
			return;
		}

		$widget->add_render_attribute( '_wrapper', 'class', [ 'marrison-heading-animated', $animation ] );

		if ( $this->is_letter_animation( $animation ) ) {
			$widget->add_render_attribute( '_wrapper', 'class', 'marrison-heading-letter-animation' );
			$widget->add_render_attribute( '_wrapper', 'data-marrison-letter-animation', $animation );
		}
	}

	public function enqueue_styles() {
		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		$style_path = plugin_dir_path( $plugin_root_file ) . 'assets/css/marrison-header-animations.css';
		$version = Marrison_Addon::VERSION;

		if ( file_exists( $style_path ) ) {
			$version .= '.' . filemtime( $style_path );
		}

		wp_enqueue_style(
			'marrison-header-animations',
			plugins_url( 'assets/css/marrison-header-animations.css', $plugin_root_file ),
			[],
			$version
		);
	}

	public function enqueue_scripts() {
		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		$script_path = plugin_dir_path( $plugin_root_file ) . 'assets/js/marrison-header-animations.js';
		$version = Marrison_Addon::VERSION;

		if ( file_exists( $script_path ) ) {
			$version .= '.' . filemtime( $script_path );
		}

		wp_enqueue_script(
			'marrison-header-animations',
			plugins_url( 'assets/js/marrison-header-animations.js', $plugin_root_file ),
			[],
			$version,
			true
		);
	}

	private function add_custom_animations( $options ) {
		$custom_animations = $this->get_custom_animations();

		if ( isset( $options[ self::ANIMATION_GROUP ] ) && is_array( $options[ self::ANIMATION_GROUP ] ) ) {
			$options[ self::ANIMATION_GROUP ] = array_merge( $options[ self::ANIMATION_GROUP ], $custom_animations );
			return $options;
		}

		$options[ self::ANIMATION_GROUP ] = $custom_animations;

		return $options;
	}

	private function get_custom_animations() {
		return [
			'marrisonLiftSoft'      => esc_html__( 'Lift Soft', 'marrison-addon' ),
			'marrisonDropSoft'      => esc_html__( 'Drop Soft', 'marrison-addon' ),
			'marrisonSlideReveal'   => esc_html__( 'Slide Reveal', 'marrison-addon' ),
			'marrisonCurtainRight'  => esc_html__( 'Curtain Right', 'marrison-addon' ),
			'marrisonFocusIn'       => esc_html__( 'Focus In', 'marrison-addon' ),
			'marrisonZoomSettle'    => esc_html__( 'Zoom Settle', 'marrison-addon' ),
			'marrisonTiltRise'      => esc_html__( 'Tilt Rise', 'marrison-addon' ),
			'marrisonSkewSweep'     => esc_html__( 'Skew Sweep', 'marrison-addon' ),
			'marrisonDriftLeft'     => esc_html__( 'Drift Left', 'marrison-addon' ),
			'marrisonElasticPop'    => esc_html__( 'Elastic Pop', 'marrison-addon' ),
			'marrisonLettersRise'   => esc_html__( 'Letters Rise', 'marrison-addon' ),
			'marrisonLettersFocus'  => esc_html__( 'Letters Focus', 'marrison-addon' ),
			'marrisonLettersElastic'=> esc_html__( 'Letters Elastic', 'marrison-addon' ),
		];
	}

	private function is_letter_animation( $animation ) {
		return in_array( $animation, self::LETTER_ANIMATIONS, true );
	}
}
