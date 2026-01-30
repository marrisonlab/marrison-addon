<?php
/**
 * Plugin Name: Marrison Link
 * Description: Adds a wrapped link functionality to Elementor Containers.
 * Version: 1.0.0
 * Author: Marrison
 * Text Domain: marrison-link
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Marrison_Link {

	const VERSION = '1.0.0';

	public function __construct() {
		add_action( 'elementor/init', [ $this, 'init' ] );
	}

	public function init() {
		add_action( 'elementor/element/container/section_layout/after_section_end', [ $this, 'register_controls' ] );
		add_action( 'elementor/frontend/container/before_render', [ $this, 'before_render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function register_controls( $element ) {
		$element->start_controls_section(
			'section_marrison_link',
			[
				'label' => esc_html__( 'Wrapped Link', 'marrison-link' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$element->add_control(
			'marrison_link_url',
			[
				'label' => esc_html__( 'Link', 'marrison-link' ),
				'type' => \Elementor\Controls_Manager::URL,
				'placeholder' => esc_html__( 'https://your-link.com', 'marrison-link' ),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$element->end_controls_section();
	}

	public function before_render( $element ) {
		$settings = $element->get_settings_for_display();

		if ( ! empty( $settings['marrison_link_url']['url'] ) ) {
			$element->add_render_attribute( '_wrapper', 'data-marrison-link', json_encode( $settings['marrison_link_url'] ) );
			$element->add_render_attribute( '_wrapper', 'style', 'cursor: pointer;' );
		}
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'marrison-link', plugin_dir_url( __FILE__ ) . 'assets/js/marrison-link.js', [ 'jquery' ], self::VERSION, true );
	}
}

new Marrison_Link();
