<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Marrison_Addon_Wrapped_Link {

	public function __construct() {
		add_action( 'elementor/element/container/section_layout/after_section_end', [ $this, 'register_controls' ] );
		add_action( 'elementor/frontend/container/before_render', [ $this, 'before_render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function register_controls( $element ) {
		$element->start_controls_section(
			'section_marrison_addon',
			[
				'label' => esc_html__( 'Wrapped Link', 'marrison-addon' ),
				'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
			]
		);

		$element->add_control(
			'marrison_addon_url',
			[
				'label' => esc_html__( 'Link', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::URL,
				'placeholder' => esc_html__( 'https://your-link.com', 'marrison-addon' ),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$element->end_controls_section();
	}

	public function before_render( $element ) {
		$settings = $element->get_settings_for_display();

		if ( ! empty( $settings['marrison_addon_url']['url'] ) ) {
			$element->add_render_attribute( '_wrapper', 'data-marrison-addon', json_encode( $settings['marrison_addon_url'] ) );
			$element->add_render_attribute( '_wrapper', 'style', 'cursor: pointer;' );
		}
	}

	public function enqueue_scripts() {
		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		wp_enqueue_script( 'marrison-addon', plugins_url( 'assets/js/marrison-addon.js', $plugin_root_file ), [ 'jquery' ], Marrison_Addon::VERSION, true );
	}
}
