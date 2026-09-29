<?php
namespace Marrison_Addon\Modules\Product_Discount\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Product_Discount_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'marrison_product_discount';
	}

	public function get_title() {
		return esc_html__( 'Sconto Prodotto', 'marrison-addon' );
	}

	public function get_icon() {
		return 'eicon-product-price';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	public function get_keywords() {
		return [ 'woocommerce', 'product', 'discount', 'sale', 'sconto', 'percentuale' ];
	}

	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Content', 'marrison-addon' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'prefix',
			[
				'label' => esc_html__( 'Prefix', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '-',
			]
		);

		$this->add_control(
			'suffix',
			[
				'label' => esc_html__( 'Suffix', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '%',
			]
		);

		$this->add_control(
			'decimals',
			[
				'label' => esc_html__( 'Decimals', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 0,
				'max' => 2,
				'step' => 1,
				'default' => 0,
			]
		);

		$this->add_control(
			'rounding',
			[
				'label' => esc_html__( 'Rounding', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'round',
				'options' => [
					'round' => esc_html__( 'Round', 'marrison-addon' ),
					'floor' => esc_html__( 'Round down', 'marrison-addon' ),
					'ceil' => esc_html__( 'Round up', 'marrison-addon' ),
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_section',
			[
				'label' => esc_html__( 'Style', 'marrison-addon' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'alignment',
			[
				'label' => esc_html__( 'Alignment', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'flex-start' => [
						'title' => esc_html__( 'Left', 'marrison-addon' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Center', 'marrison-addon' ),
						'icon' => 'eicon-text-align-center',
					],
					'flex-end' => [
						'title' => esc_html__( 'Right', 'marrison-addon' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'default' => 'flex-start',
				'selectors' => [
					'{{WRAPPER}} .marrison-product-discount-wrapper' => 'justify-content: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'typography',
				'selector' => '{{WRAPPER}} .marrison-product-discount-badge',
			]
		);

		$this->add_control(
			'text_color',
			[
				'label' => esc_html__( 'Text Color', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .marrison-product-discount-badge' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			[
				'name' => 'background',
				'types' => [ 'classic', 'gradient' ],
				'selector' => '{{WRAPPER}} .marrison-product-discount-badge',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'border',
				'selector' => '{{WRAPPER}} .marrison-product-discount-badge',
			]
		);

		$this->add_responsive_control(
			'border_radius',
			[
				'label' => esc_html__( 'Border Radius', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem' ],
				'selectors' => [
					'{{WRAPPER}} .marrison-product-discount-badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'box_shadow',
				'selector' => '{{WRAPPER}} .marrison-product-discount-badge',
			]
		);

		$this->add_responsive_control(
			'padding',
			[
				'label' => esc_html__( 'Padding', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'default' => [
					'top' => 4,
					'right' => 10,
					'bottom' => 4,
					'left' => 10,
					'unit' => 'px',
					'isLinked' => false,
				],
				'selectors' => [
					'{{WRAPPER}} .marrison-product-discount-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'minimum_width',
			[
				'label' => esc_html__( 'Minimum Width', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 300,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .marrison-product-discount-badge' => 'min-width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'margin',
			[
				'label' => esc_html__( 'Margin', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', 'rem' ],
				'selectors' => [
					'{{WRAPPER}} .marrison-product-discount-badge' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$product = $this->get_current_product();

		if ( ! $product ) {
			return;
		}

		$settings = $this->get_settings_for_display();
		$discount = \Marrison_Addon_Product_Discount::get_discount_percentage( $product );

		if ( $discount <= 0 ) {
			return;
		}

		$decimals = isset( $settings['decimals'] ) ? absint( $settings['decimals'] ) : 0;
		$discount = $this->round_discount( $discount, isset( $settings['rounding'] ) ? $settings['rounding'] : 'round', $decimals );
		$label = sprintf(
			'%s%s%s',
			isset( $settings['prefix'] ) ? $settings['prefix'] : '-',
			number_format_i18n( $discount, $decimals ),
			isset( $settings['suffix'] ) ? $settings['suffix'] : '%'
		);

		$this->add_render_attribute( 'wrapper', 'class', 'marrison-product-discount-wrapper' );
		$this->add_render_attribute( 'wrapper', 'style', 'display: flex;' );
		$this->add_render_attribute( 'badge', 'class', 'marrison-product-discount-badge' );
		$this->add_render_attribute( 'badge', 'style', 'display: inline-flex; align-items: center; justify-content: center;' );
		?>
		<div <?php $this->print_render_attribute_string( 'wrapper' ); ?>>
			<span <?php $this->print_render_attribute_string( 'badge' ); ?>><?php echo esc_html( $label ); ?></span>
		</div>
		<?php
	}

	private function get_current_product() {
		global $product;

		if ( $product instanceof \WC_Product ) {
			return $product;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$post_id = get_the_ID();

		if ( ! $post_id ) {
			return false;
		}

		return wc_get_product( $post_id );
	}

	private function round_discount( $discount, $method, $decimals ) {
		$precision = pow( 10, $decimals );

		if ( 'floor' === $method ) {
			return floor( $discount * $precision ) / $precision;
		}

		if ( 'ceil' === $method ) {
			return ceil( $discount * $precision ) / $precision;
		}

		return round( $discount, $decimals );
	}
}
