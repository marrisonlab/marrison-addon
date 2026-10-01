<?php
namespace Marrison_Addon\Modules\Ticker\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor Ticker Widget.
 *
 * Elementor widget that displays a scrolling text ticker.
 *
 * @since 1.0.0
 */
class Ticker_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name.
	 *
	 * Retrieve ticker widget name.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'ticker';
	}

	/**
	 * Get widget title.
	 *
	 * Retrieve ticker widget title.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'Ticker', 'marrison-addon' );
	}

	/**
	 * Get widget icon.
	 *
	 * Retrieve ticker widget icon.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget icon.
	 */
	public function get_icon() {
		return 'eicon-code';
	}

	/**
	 * Get widget categories.
	 *
	 * Retrieve the list of categories the ticker widget belongs to.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Widget categories.
	 */
	public function get_categories() {
		return [ 'general' ];
	}

	/**
	 * Get JetEngine Queries for options.
	 *
	 * @return array
	 */
	private function get_jet_engine_queries_options() {
		if ( ! class_exists( '\Jet_Engine\Query_Builder\Manager' ) ) {
			return [];
		}

		return \Jet_Engine\Query_Builder\Manager::instance()->get_queries_for_options();
	}

	/**
	 * Get JetEngine Macros for options.
	 *
	 * @return array
	 */
	private function get_jet_engine_macros_options() {
		$options = [
			'' => esc_html__( 'Select...', 'marrison-addon' ),
			'post_title' => esc_html__( 'Post Title', 'marrison-addon' ),
			'ID' => esc_html__( 'Post ID', 'marrison-addon' ),
			'post_excerpt' => esc_html__( 'Post Excerpt', 'marrison-addon' ),
			'post_content' => esc_html__( 'Post Content', 'marrison-addon' ),
			'permalink' => esc_html__( 'Permalink (URL)', 'marrison-addon' ),
			'thumbnail_url' => esc_html__( 'Thumbnail URL', 'marrison-addon' ),
		];

		if ( function_exists( 'jet_engine' ) && isset( jet_engine()->listings->macros ) ) {
			$macros = jet_engine()->listings->macros->get_macros_list_for_options();
			if ( ! empty( $macros ) ) {
				foreach ( $macros as $key => $label ) {
					// Prefix macros to distinguish from properties
					$options[ 'macro::' . $key ] = $label . ' (Macro)';
				}
			}
		}

		return $options;
	}

	/**
	 * Register ticker widget controls.
	 *
	 * Adds different input fields to allow the user to change and customize the widget settings.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function register_controls() {

		// Content Section
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Content', 'marrison-addon' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'source',
			[
				'label' => esc_html__( 'Source', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'repeater',
				'options' => [
					'repeater' => esc_html__( 'Manual (Repeater)', 'marrison-addon' ),
					'jet_engine' => esc_html__( 'JetEngine Query', 'marrison-addon' ),
				],
			]
		);

		// --- Repeater Controls ---

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'item_text',
			[
				'label' => esc_html__( 'Text', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__( 'Ticker Item', 'marrison-addon' ),
				'label_block' => true,
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$repeater->add_control(
			'item_link',
			[
				'label' => esc_html__( 'Link', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::URL,
				'placeholder' => esc_html__( 'https://your-link.com', 'marrison-addon' ),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'ticker_items',
			[
				'label' => esc_html__( 'Ticker Items', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'item_text' => esc_html__( 'Item #1', 'marrison-addon' ),
					],
					[
						'item_text' => esc_html__( 'Item #2', 'marrison-addon' ),
					],
				],
				'title_field' => '{{{ item_text }}}',
				'condition' => [
					'source' => 'repeater',
				],
			]
		);

		// --- JetEngine Query Controls ---

		$this->add_control(
			'query_id',
			[
				'label' => esc_html__( 'Query', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $this->get_jet_engine_queries_options(),
				'description' => esc_html__( 'Select the JetEngine Query.', 'marrison-addon' ),
				'condition' => [
					'source' => 'jet_engine',
				],
			]
		);

		$this->add_control(
			'query_text_type',
			[
				'label' => esc_html__( 'Text Source', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'preset',
				'options' => [
					'preset' => esc_html__( 'Select Field/Macro', 'marrison-addon' ),
					'custom' => esc_html__( 'Custom Key', 'marrison-addon' ),
				],
				'condition' => [
					'source' => 'jet_engine',
				],
			]
		);

		$this->add_control(
			'query_text_preset',
			[
				'label' => esc_html__( 'Text Field', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $this->get_jet_engine_macros_options(),
				'default' => 'post_title',
				'condition' => [
					'source' => 'jet_engine',
					'query_text_type' => 'preset',
				],
			]
		);

		$this->add_control(
			'query_text_custom',
			[
				'label' => esc_html__( 'Custom Text Key', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'description' => esc_html__( 'Field key (e.g., post_title) or macro.', 'marrison-addon' ),
				'default' => 'post_title',
				'condition' => [
					'source' => 'jet_engine',
					'query_text_type' => 'custom',
				],
			]
		);

		$this->add_control(
			'query_link_type',
			[
				'label' => esc_html__( 'Link Source', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'preset',
				'options' => [
					'preset' => esc_html__( 'Select Field/Macro', 'marrison-addon' ),
					'custom' => esc_html__( 'Custom Key', 'marrison-addon' ),
				],
				'condition' => [
					'source' => 'jet_engine',
				],
			]
		);

		$this->add_control(
			'query_link_preset',
			[
				'label' => esc_html__( 'Link Field', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $this->get_jet_engine_macros_options(),
				'default' => 'permalink',
				'condition' => [
					'source' => 'jet_engine',
					'query_link_type' => 'preset',
				],
			]
		);

		$this->add_control(
			'query_link_custom',
			[
				'label' => esc_html__( 'Custom Link Key', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'description' => esc_html__( 'Field key (e.g., permalink) or macro.', 'marrison-addon' ),
				'default' => 'permalink',
				'condition' => [
					'source' => 'jet_engine',
					'query_link_type' => 'custom',
				],
			]
		);

		$this->add_control(
			'separator_icon',
			[
				'label' => esc_html__( 'Separator Icon', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-star',
					'library' => 'fa-solid',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'direction',
			[
				'label' => esc_html__( 'Direction', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'left',
				'options' => [
					'left' => esc_html__( 'Left', 'marrison-addon' ),
					'right' => esc_html__( 'Right', 'marrison-addon' ),
				],
			]
		);

		$this->add_control(
			'speed',
			[
				'label' => esc_html__( 'Speed (seconds)', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 100,
				'step' => 1,
				'default' => 10,
				'description' => esc_html__( 'Time in seconds for the text to cross the container.', 'marrison-addon' ),
			]
		);

		$this->add_control(
			'pause_on_hover',
			[
				'label' => esc_html__( 'Pause on Hover', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Yes', 'marrison-addon' ),
				'label_off' => esc_html__( 'No', 'marrison-addon' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->end_controls_section();

		// Style Section
		$this->start_controls_section(
			'style_section',
			[
				'label' => esc_html__( 'Style', 'marrison-addon' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'vertical_align',
			[
				'label' => esc_html__( 'Vertical Alignment', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'middle',
				'options' => [
					'top' => esc_html__( 'Top', 'marrison-addon' ),
					'middle' => esc_html__( 'Middle', 'marrison-addon' ),
					'bottom' => esc_html__( 'Bottom', 'marrison-addon' ),
					'baseline' => esc_html__( 'Baseline', 'marrison-addon' ),
				],
				'selectors' => [
					'{{WRAPPER}} .ticker-item-text, {{WRAPPER}} .ticker-item-separator' => 'vertical-align: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'content_typography',
				'selector' => '{{WRAPPER}} .ticker-item-text, {{WRAPPER}} .ticker-item-text a',
			]
		);

		$this->add_control(
			'text_color',
			[
				'label' => esc_html__( 'Text Color', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .ticker-item-text' => 'color: {{VALUE}};',
					'{{WRAPPER}} .ticker-item-text a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'text_hover_color',
			[
				'label' => esc_html__( 'Link Hover Color', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .ticker-item-text a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'heading_icon_style',
			[
				'label' => esc_html__( 'Icon', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'icon_color',
			[
				'label' => esc_html__( 'Icon Color', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .ticker-item-separator i' => 'color: {{VALUE}};',
					'{{WRAPPER}} .ticker-item-separator svg' => 'fill: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'icon_size',
			[
				'label' => esc_html__( 'Icon Size', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 15,
				],
				'selectors' => [
					'{{WRAPPER}} .ticker-item-separator i' => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .ticker-item-separator svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'icon_padding',
			[
				'label' => esc_html__( 'Icon Padding', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .ticker-item-separator' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			[
				'name' => 'background',
				'types' => [ 'classic', 'gradient' ],
				'selector' => '{{WRAPPER}} .ticker-wrapper',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'border',
				'selector' => '{{WRAPPER}} .ticker-wrapper',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'box_shadow',
				'selector' => '{{WRAPPER}} .ticker-wrapper',
			]
		);

		$this->add_responsive_control(
			'padding',
			[
				'label' => esc_html__( 'Padding', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .ticker-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

	}

	/**
	 * Resolve dynamic value from object/array/macro.
	 *
	 * @param string $key
	 * @param object|array $obj
	 * @return string
	 */
	private function resolve_dynamic_value( $key, $obj ) {
		if ( empty( $key ) ) {
			return '';
		}

		// 0. Handle Macros (prefixed with macro::)
		if ( strpos( $key, 'macro::' ) === 0 ) {
			$macro = substr( $key, 7 ); // Remove 'macro::'
			if ( function_exists( 'jet_engine' ) && isset( jet_engine()->listings->macros ) ) {
				jet_engine()->listings->macros->set_macros_context( $obj );
				return jet_engine()->listings->macros->do_macros( '%' . $macro . '%' );
			}
			return '';
		}

		// 1. Handle special helpers
		if ( 'permalink' === $key ) {
			if ( $obj instanceof \WP_Post ) {
				return get_permalink( $obj->ID );
			}
		}
		if ( 'thumbnail_url' === $key ) {
			if ( $obj instanceof \WP_Post ) {
				return get_the_post_thumbnail_url( $obj->ID, 'full' );
			}
		}

		// 2. Try property/index access
		if ( is_object( $obj ) ) {
			if ( isset( $obj->$key ) ) {
				return $obj->$key;
			}
		} elseif ( is_array( $obj ) ) {
			if ( isset( $obj[ $key ] ) ) {
				return $obj[ $key ];
			}
		}

		// 3. Fallback: Try as Macro if it looks like one (contains %) or just try do_macros
		if ( strpos( $key, '%' ) !== false ) {
			if ( function_exists( 'jet_engine' ) && isset( jet_engine()->listings->macros ) ) {
				jet_engine()->listings->macros->set_macros_context( $obj );
				return jet_engine()->listings->macros->do_macros( $key );
			}
		}

		return '';
	}

	/**
	 * Render ticker widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$direction = $settings['direction'];
		$speed = $settings['speed'];
		$pause_on_hover = $settings['pause_on_hover'];
		$source = isset( $settings['source'] ) ? $settings['source'] : 'repeater';

		$ticker_items = [];

		if ( 'jet_engine' === $source && ! empty( $settings['query_id'] ) ) {
			// Fetch from JetEngine Query
			if ( class_exists( '\Jet_Engine\Query_Builder\Manager' ) ) {
				$query = \Jet_Engine\Query_Builder\Manager::instance()->get_query_by_id( $settings['query_id'] );
				if ( $query ) {
					$query_items = $query->get_items();
					if ( ! empty( $query_items ) ) {
						
						$text_type = isset( $settings['query_text_type'] ) ? $settings['query_text_type'] : 'preset';
						$text_key = ( 'custom' === $text_type ) ? $settings['query_text_custom'] : $settings['query_text_preset'];

						$link_type = isset( $settings['query_link_type'] ) ? $settings['query_link_type'] : 'preset';
						$link_key = ( 'custom' === $link_type ) ? $settings['query_link_custom'] : $settings['query_link_preset'];

						foreach ( $query_items as $obj ) {
							$text = $this->resolve_dynamic_value( $text_key, $obj );
							$url = $this->resolve_dynamic_value( $link_key, $obj );
							
							if ( ! empty( $text ) ) {
								$ticker_items[] = [
									'item_text' => $text,
									'item_link' => [ 'url' => $url, 'is_external' => '', 'nofollow' => '' ],
								];
							}
						}
					}
				}
			}
		} else {
			// Default Repeater
			$ticker_items = isset( $settings['ticker_items'] ) ? $settings['ticker_items'] : [];
		}

		// Allow external modification of items
		$ticker_items = apply_filters( 'marrison_addon/ticker/items', $ticker_items, 'ticker_items', $this );

		// Build items content
		$items_html = '';
		$base_items_html = '';
		$total_length = 0;

		if ( ! empty( $ticker_items ) ) {
			foreach ( $ticker_items as $item ) {
				$item_text = isset( $item['item_text'] ) ? $item['item_text'] : '';
				$item_link = isset( $item['item_link'] ) ? $item['item_link'] : [];
				
				$item_content = esc_html( $item_text );

				if ( ! empty( $item_link['url'] ) ) {
					$target = $item_link['is_external'] ? ' target="_blank"' : '';
					$nofollow = $item_link['nofollow'] ? ' rel="nofollow"' : '';
					$item_content = '<a href="' . esc_url( $item_link['url'] ) . '"' . $target . $nofollow . '>' . $item_content . '</a>';
				}

				$item_html = '<span class="ticker-item-text">' . $item_content . '</span>';
				$total_length += mb_strlen( $item_text );
				
				// Separator
				if ( ! empty( $settings['separator_icon']['value'] ) ) {
					$item_html .= '<span class="ticker-item-separator">';
					ob_start();
					\Elementor\Icons_Manager::render_icon( $settings['separator_icon'], [ 'aria-hidden' => 'true' ] );
					$item_html .= ob_get_clean();
					$item_html .= '</span>';
					$total_length += 5; 
				} else {
					$item_html .= '<span class="ticker-item-separator" style="display:inline-block; width: 20px;"></span>';
					$total_length += 5;
				}
				$base_items_html .= $item_html;
			}
		}

		// Calculate how many times to repeat to fill screen (heuristic)
		// Aim for ~1000 characters to cover most screens
		$target_length = 1000;
		$multiplier = 1;
		
		if ( $total_length > 0 && $total_length < $target_length ) {
			$multiplier = ceil( $target_length / $total_length );
		}
		
		// Cap multiplier to avoid performance issues
		$multiplier = min( $multiplier, 50 );

		for ( $i = 0; $i < $multiplier; $i++ ) {
			$items_html .= $base_items_html;
		}

		// Adjust speed based on multiplier to maintain consistent velocity
		if ( $multiplier > 1 ) {
			$speed = $speed * $multiplier;
		}

		$hover_class = ( 'yes' === $pause_on_hover ) ? 'ticker-pause-hover' : '';

		// Infinite Loop Implementation
		$animation_name = 'ticker-infinite-' . $direction;
		
		?>
		<div class="ticker-wrapper" style="overflow: hidden; white-space: nowrap;">
			<div class="ticker-move <?php echo esc_attr( $hover_class ); ?>" style="display: inline-block; white-space: nowrap; animation: <?php echo esc_attr( $animation_name ); ?> <?php echo esc_attr( $speed ); ?>s linear infinite;">
				<div class="ticker-loop-inner" style="display: inline-block;"><?php echo $items_html; ?></div>
				<div class="ticker-loop-inner" style="display: inline-block;"><?php echo $items_html; ?></div>
			</div>
		</div>
		<style>
			@keyframes ticker-infinite-left {
				0% { transform: translate3d(0, 0, 0); }
				100% { transform: translate3d(-50%, 0, 0); }
			}
			@keyframes ticker-infinite-right {
				0% { transform: translate3d(-50%, 0, 0); }
				100% { transform: translate3d(0, 0, 0); }
			}
			.ticker-pause-hover:hover {
				animation-play-state: paused !important;
			}
		</style>
		<?php
	}
}
