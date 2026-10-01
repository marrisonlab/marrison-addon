<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Read_More {

	private const SUPPORTED_WIDGETS = [
		'text-editor',
		'jet-listing-dynamic-field',
	];

	private $registered_elements = [];
	private $assets_enqueued = false;

	public function __construct() {
		add_action( 'elementor/element/after_section_end', [ $this, 'maybe_register_controls' ], 10, 2 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'elementor/widget/render_content', [ $this, 'render_read_more' ], 10, 2 );
		add_filter( 'elementor/frontend/builder_content_data', [ $this, 'maybe_enqueue_assets_from_data' ] );
	}

	public function maybe_register_controls( $element, $section_id = '' ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) ) {
			return;
		}

		if ( ! $this->is_supported_widget_name( $element->get_name() ) ) {
			return;
		}

		$element_hash = spl_object_hash( $element );
		if ( isset( $this->registered_elements[ $element_hash ] ) ) {
			return;
		}

		$this->registered_elements[ $element_hash ] = true;
		$this->register_controls( $element );
	}

	public function register_controls( $widget ) {
		if ( ! method_exists( $widget, 'start_controls_section' ) || ! method_exists( $widget, 'add_control' ) ) {
			return;
		}

		$widget->start_controls_section(
			'marrison_read_more_section',
			[
				'label' => esc_html__( 'Marrison — Leggi di più', 'marrison-addon' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$widget->add_control(
			'marrison_read_more_enabled',
			[
				'label'        => esc_html__( 'Abilita Leggi di più', 'marrison-addon' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Si', 'marrison-addon' ),
				'label_off'    => esc_html__( 'No', 'marrison-addon' ),
				'return_value' => 'yes',
				'default'      => '',
				'render_type'  => 'template',
			]
		);

		$widget->add_control(
			'marrison_read_more_lines',
			[
				'label'       => esc_html__( 'Righe da mostrare', 'marrison-addon' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => 30,
				'step'        => 1,
				'default'     => 3,
				'render_type' => 'template',
				'condition'   => [
					'marrison_read_more_enabled' => 'yes',
				],
			]
		);

		$widget->add_control(
			'marrison_read_more_label_more',
			[
				'label'       => esc_html__( 'Testo chiuso', 'marrison-addon' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => esc_html__( 'Leggi di più', 'marrison-addon' ),
				'label_block' => true,
				'render_type' => 'template',
				'condition'   => [
					'marrison_read_more_enabled' => 'yes',
				],
			]
		);

		$widget->add_control(
			'marrison_read_more_label_less',
			[
				'label'       => esc_html__( 'Testo aperto', 'marrison-addon' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => esc_html__( 'Leggi di meno', 'marrison-addon' ),
				'label_block' => true,
				'render_type' => 'template',
				'condition'   => [
					'marrison_read_more_enabled' => 'yes',
				],
			]
		);

		$widget->end_controls_section();

		$widget->start_controls_section(
			'marrison_read_more_style_section',
			[
				'label'     => esc_html__( 'Leggi di più', 'marrison-addon' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [
					'marrison_read_more_enabled' => 'yes',
				],
			]
		);

		$widget->add_responsive_control(
			'marrison_read_more_alignment',
			[
				'label'     => esc_html__( 'Allineamento', 'marrison-addon' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => [
					'left' => [
						'title' => esc_html__( 'Sinistra', 'marrison-addon' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'marrison-addon' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right' => [
						'title' => esc_html__( 'Destra', 'marrison-addon' ),
						'icon'  => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Giustificato', 'marrison-addon' ),
						'icon'  => 'eicon-text-align-justify',
					],
				],
				'default'   => 'left',
				'selectors' => [
					'{{WRAPPER}} .marrison-read-more-toggle-wrap' => 'text-align: {{VALUE}};',
				],
			]
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'marrison_read_more_typography',
				'selector' => '{{WRAPPER}} .marrison-read-more-toggle',
			]
		);

		$widget->add_control(
			'marrison_read_more_color',
			[
				'label'     => esc_html__( 'Colore testo', 'marrison-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .marrison-read-more-toggle' => 'color: {{VALUE}};',
				],
			]
		);

		$widget->add_control(
			'marrison_read_more_hover_color',
			[
				'label'     => esc_html__( 'Colore hover', 'marrison-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .marrison-read-more-toggle:hover, {{WRAPPER}} .marrison-read-more-toggle:focus' => 'color: {{VALUE}};',
				],
			]
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			[
				'name'     => 'marrison_read_more_background',
				'types'    => [ 'classic', 'gradient' ],
				'selector' => '{{WRAPPER}} .marrison-read-more-toggle',
			]
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'marrison_read_more_border',
				'selector' => '{{WRAPPER}} .marrison-read-more-toggle',
			]
		);

		$widget->add_responsive_control(
			'marrison_read_more_border_radius',
			[
				'label'      => esc_html__( 'Raggio bordo', 'marrison-addon' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem' ],
				'selectors'  => [
					'{{WRAPPER}} .marrison-read-more-toggle' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$widget->add_responsive_control(
			'marrison_read_more_padding',
			[
				'label'      => esc_html__( 'Padding', 'marrison-addon' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'selectors'  => [
					'{{WRAPPER}} .marrison-read-more-toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$widget->add_responsive_control(
			'marrison_read_more_margin',
			[
				'label'      => esc_html__( 'Margine', 'marrison-addon' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'default'    => [
					'top'      => 10,
					'right'    => 0,
					'bottom'   => 0,
					'left'     => 0,
					'unit'     => 'px',
					'isLinked' => false,
				],
				'selectors'  => [
					'{{WRAPPER}} .marrison-read-more-toggle-wrap' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$widget->end_controls_section();
	}

	public function render_read_more( $widget_content, $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || ! method_exists( $widget, 'get_settings_for_display' ) ) {
			return $widget_content;
		}

		if ( ! $this->is_supported_widget_name( $widget->get_name() ) ) {
			return $widget_content;
		}

		$settings = $widget->get_settings_for_display();
		if ( empty( $settings['marrison_read_more_enabled'] ) || 'yes' !== $settings['marrison_read_more_enabled'] ) {
			return $widget_content;
		}

		if ( '' === trim( wp_strip_all_tags( $widget_content ) ) ) {
			return $widget_content;
		}

		$this->enqueue_assets();

		$lines = isset( $settings['marrison_read_more_lines'] ) ? (int) $settings['marrison_read_more_lines'] : 3;
		$lines = max( 1, min( 30, $lines ) );

		$label_more = isset( $settings['marrison_read_more_label_more'] ) ? trim( (string) $settings['marrison_read_more_label_more'] ) : '';
		$label_less = isset( $settings['marrison_read_more_label_less'] ) ? trim( (string) $settings['marrison_read_more_label_less'] ) : '';

		if ( '' === $label_more ) {
			$label_more = esc_html__( 'Leggi di più', 'marrison-addon' );
		}

		if ( '' === $label_less ) {
			$label_less = esc_html__( 'Leggi di meno', 'marrison-addon' );
		}

		$content_id = $this->get_content_id( $widget );
		$config     = [
			'lines'     => $lines,
			'labelMore' => $label_more,
			'labelLess' => $label_less,
		];

		return $this->wrap_widget_content( $widget_content, $config, $content_id, $label_more );
	}

	public function maybe_enqueue_assets_from_data( $elements ) {
		if ( is_array( $elements ) && $this->has_enabled_widget( $elements ) ) {
			$this->enqueue_assets();
		}

		return $elements;
	}

	private function is_supported_widget_name( $name ) {
		return in_array( $name, self::SUPPORTED_WIDGETS, true );
	}

	private function get_content_id( $widget ) {
		$id = method_exists( $widget, 'get_id' ) ? (string) $widget->get_id() : uniqid( 'read-more-', false );
		$id = sanitize_html_class( $id );

		return 'marrison-read-more-content-' . $id;
	}

	private function build_read_more_html( $content, $config, $content_id, $label_more ) {
		return sprintf(
			'<div class="marrison-read-more" data-marrison-read-more="%1$s"><div id="%2$s" class="marrison-read-more-content">%3$s</div><div class="marrison-read-more-toggle-wrap"><button class="marrison-read-more-toggle" type="button" aria-expanded="false" aria-controls="%2$s">%4$s</button></div></div>',
			esc_attr( wp_json_encode( $config ) ),
			esc_attr( $content_id ),
			$content,
			esc_html( $label_more )
		);
	}

	private function wrap_widget_content( $widget_content, $config, $content_id, $label_more ) {
		if ( false === strpos( $widget_content, 'elementor-widget-container' ) ) {
			return $this->build_read_more_html( $widget_content, $config, $content_id, $label_more );
		}

		$open_tag_start = strpos( $widget_content, '<div' );
		$open_tag_end   = false === $open_tag_start ? false : strpos( $widget_content, '>', $open_tag_start );
		$close_tag_start = strripos( $widget_content, '</div>' );

		if ( false === $open_tag_start || false === $open_tag_end || false === $close_tag_start || $close_tag_start <= $open_tag_end ) {
			return $this->build_read_more_html( $widget_content, $config, $content_id, $label_more );
		}

		$open_tag = substr( $widget_content, $open_tag_start, $open_tag_end - $open_tag_start + 1 );
		if ( false === strpos( $open_tag, 'elementor-widget-container' ) ) {
			return $this->build_read_more_html( $widget_content, $config, $content_id, $label_more );
		}

		$inner_content = substr( $widget_content, $open_tag_end + 1, $close_tag_start - $open_tag_end - 1 );
		$wrapped_inner = $this->build_read_more_html( $inner_content, $config, $content_id, $label_more );

		return substr( $widget_content, 0, $open_tag_end + 1 ) . $wrapped_inner . substr( $widget_content, $close_tag_start );
	}

	private function has_enabled_widget( $elements ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$widget_type = isset( $element['widgetType'] ) ? $element['widgetType'] : '';
			$settings    = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : [];

			if ( $this->is_supported_widget_name( $widget_type ) && 'yes' === ( $settings['marrison_read_more_enabled'] ?? '' ) ) {
				return true;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) && $this->has_enabled_widget( $element['elements'] ) ) {
				return true;
			}
		}

		return false;
	}

	public function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}

		$this->assets_enqueued = true;

		$plugin_root_file = method_exists( 'Marrison_Addon', 'plugin_file' ) ? Marrison_Addon::plugin_file() : dirname( __DIR__, 2 ) . '/marrison-addon.php';
		$css_version = method_exists( 'Marrison_Addon', 'asset_version' ) ? Marrison_Addon::asset_version( 'assets/css/marrison-read-more.css' ) : Marrison_Addon::VERSION;
		$js_version = method_exists( 'Marrison_Addon', 'asset_version' ) ? Marrison_Addon::asset_version( 'assets/js/marrison-read-more.js' ) : Marrison_Addon::VERSION;

		wp_enqueue_style(
			'marrison-addon-read-more',
			plugins_url( 'assets/css/marrison-read-more.css', $plugin_root_file ),
			[],
			$css_version
		);

		wp_enqueue_script(
			'marrison-addon-read-more',
			plugins_url( 'assets/js/marrison-read-more.js', $plugin_root_file ),
			[],
			$js_version,
			true
		);
	}
}
