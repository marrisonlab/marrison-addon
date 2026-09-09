<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Listing_Title {

	public function __construct() {
		if ( ! Marrison_Addon::is_jet_engine_active() ) {
			return;
		}

		add_action( 'elementor/element/jet-listing-grid/section_general/after_section_end', [ $this, 'register_controls' ] );
		add_filter( 'elementor/widget/render_content', [ $this, 'prepend_listing_title' ], 10, 2 );
	}

	public function register_controls( $widget ) {
		$widget->start_controls_section(
			'marrison_listing_title_section',
			[
				'label' => esc_html__( 'Titolo Listing', 'marrison-addon' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$widget->add_control(
			'marrison_listing_title_enable',
			[
				'label' => esc_html__( 'Mostra titolo', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Si', 'marrison-addon' ),
				'label_off' => esc_html__( 'No', 'marrison-addon' ),
				'return_value' => 'yes',
				'default' => '',
				'render_type' => 'template',
			]
		);

		$widget->add_control(
			'marrison_listing_title_text',
			[
				'label' => esc_html__( 'Titolo', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'render_type' => 'template',
				'dynamic' => [
					'active' => true,
				],
				'condition' => [
					'marrison_listing_title_enable' => 'yes',
				],
			]
		);

		$widget->add_control(
			'marrison_listing_title_html_tag',
			[
				'label' => esc_html__( 'HTML Tag', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'h2',
				'render_type' => 'template',
				'options' => [
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
					'div' => 'div',
					'p' => 'p',
					'span' => 'span',
				],
				'condition' => [
					'marrison_listing_title_enable' => 'yes',
				],
			]
		);

		$widget->add_control(
			'marrison_listing_title_link',
			[
				'label' => esc_html__( 'Link', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::URL,
				'placeholder' => esc_html__( 'https://your-link.com', 'marrison-addon' ),
				'render_type' => 'template',
				'dynamic' => [
					'active' => true,
				],
				'condition' => [
					'marrison_listing_title_enable' => 'yes',
				],
			]
		);

		$widget->end_controls_section();

		$widget->start_controls_section(
			'marrison_listing_title_style_section',
			[
				'label' => esc_html__( 'Titolo Listing', 'marrison-addon' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [
					'marrison_listing_title_enable' => 'yes',
				],
			]
		);

		$widget->add_responsive_control(
			'marrison_listing_title_alignment',
			[
				'label' => esc_html__( 'Alignment', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => esc_html__( 'Left', 'marrison-addon' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Center', 'marrison-addon' ),
						'icon' => 'eicon-text-align-center',
					],
					'right' => [
						'title' => esc_html__( 'Right', 'marrison-addon' ),
						'icon' => 'eicon-text-align-right',
					],
					'justify' => [
						'title' => esc_html__( 'Justified', 'marrison-addon' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'default' => 'left',
				'selectors' => [
					'{{WRAPPER}} .marrison-listing-grid-title' => 'text-align: {{VALUE}};',
				],
			]
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'marrison_listing_title_typography',
				'selector' => '{{WRAPPER}} .marrison-listing-grid-title, {{WRAPPER}} .marrison-listing-grid-title a',
			]
		);

		$widget->add_control(
			'marrison_listing_title_color',
			[
				'label' => esc_html__( 'Text Color', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .marrison-listing-grid-title' => 'color: {{VALUE}};',
					'{{WRAPPER}} .marrison-listing-grid-title a' => 'color: {{VALUE}};',
				],
			]
		);

		$widget->add_control(
			'marrison_listing_title_hover_color',
			[
				'label' => esc_html__( 'Hover Color', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .marrison-listing-grid-title a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Text_Shadow::get_type(),
			[
				'name' => 'marrison_listing_title_text_shadow',
				'selector' => '{{WRAPPER}} .marrison-listing-grid-title',
			]
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			[
				'name' => 'marrison_listing_title_background',
				'types' => [ 'classic', 'gradient' ],
				'selector' => '{{WRAPPER}} .marrison-listing-grid-title',
			]
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'marrison_listing_title_border',
				'selector' => '{{WRAPPER}} .marrison-listing-grid-title',
			]
		);

		$widget->add_responsive_control(
			'marrison_listing_title_border_radius',
			[
				'label' => esc_html__( 'Border Radius', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem' ],
				'selectors' => [
					'{{WRAPPER}} .marrison-listing-grid-title' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'marrison_listing_title_box_shadow',
				'selector' => '{{WRAPPER}} .marrison-listing-grid-title',
			]
		);

		$widget->add_responsive_control(
			'marrison_listing_title_padding',
			[
				'label' => esc_html__( 'Padding', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'selectors' => [
					'{{WRAPPER}} .marrison-listing-grid-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$widget->add_responsive_control(
			'marrison_listing_title_margin',
			[
				'label' => esc_html__( 'Margin', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'default' => [
					'top' => 0,
					'right' => 0,
					'bottom' => 20,
					'left' => 0,
					'unit' => 'px',
					'isLinked' => false,
				],
				'selectors' => [
					'{{WRAPPER}} .marrison-listing-grid-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$widget->end_controls_section();
	}

	public function prepend_listing_title( $widget_content, $widget ) {
		if ( ! method_exists( $widget, 'get_name' ) || 'jet-listing-grid' !== $widget->get_name() ) {
			return $widget_content;
		}

		if ( ! method_exists( $widget, 'get_settings_for_display' ) ) {
			return $widget_content;
		}

		$title = $this->get_listing_title_html( $widget );

		return $title . $widget_content;
	}

	private function get_listing_title_html( $widget ) {
		$settings = $widget->get_settings_for_display();

		if ( empty( $settings['marrison_listing_title_enable'] ) || 'yes' !== $settings['marrison_listing_title_enable'] ) {
			return '';
		}

		$title = isset( $settings['marrison_listing_title_text'] ) ? trim( (string) $settings['marrison_listing_title_text'] ) : '';

		if ( '' === $title ) {
			return '';
		}

		$tag = isset( $settings['marrison_listing_title_html_tag'] ) ? $settings['marrison_listing_title_html_tag'] : 'h2';
		$tag = $this->sanitize_html_tag( $tag );
		$link = isset( $settings['marrison_listing_title_link'] ) && is_array( $settings['marrison_listing_title_link'] ) ? $settings['marrison_listing_title_link'] : [];

		$widget->add_render_attribute( 'marrison_listing_title', 'class', 'marrison-listing-grid-title' );

		if ( 'span' === $tag ) {
			$widget->add_render_attribute( 'marrison_listing_title', 'style', 'display: block;' );
		}

		$output = sprintf( '<%1$s %2$s>', esc_attr( $tag ), $widget->get_render_attribute_string( 'marrison_listing_title' ) );

		if ( ! empty( $link['url'] ) ) {
			$link_attributes = [
				'href' => esc_url( $link['url'] ),
			];

			if ( ! empty( $link['is_external'] ) ) {
				$link_attributes['target'] = '_blank';
				$link_attributes['rel'] = 'noopener';
			}

			if ( ! empty( $link['nofollow'] ) ) {
				$link_attributes['rel'] = empty( $link_attributes['rel'] ) ? 'nofollow' : $link_attributes['rel'] . ' nofollow';
			}

			$output .= '<a ' . $this->render_attributes( $link_attributes ) . '>' . esc_html( $title ) . '</a>';
		} else {
			$output .= esc_html( $title );
		}

		$output .= sprintf( '</%s>', esc_attr( $tag ) );

		return $output;
	}

	private function sanitize_html_tag( $tag ) {
		$allowed_tags = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span' ];

		return in_array( $tag, $allowed_tags, true ) ? $tag : 'h2';
	}

	private function render_attributes( $attributes ) {
		$output = [];

		foreach ( $attributes as $name => $value ) {
			if ( '' === $value ) {
				continue;
			}

			$output[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}

		return implode( ' ', $output );
	}
}
