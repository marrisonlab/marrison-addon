<?php
/**
 * Bootstrap del plugin Marrison Steps.
 *
 * @package MarrisonSteps
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Marrison_Steps_Plugin {
	/**
	 * Istanza singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Restituisce l'istanza del plugin.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_styles' ) );
	}

	/**
	 * Registra gli stili frontend del widget.
	 */
	public function register_styles(): void {
		wp_register_style(
			'marrison-steps',
			MARRISON_STEPS_URL . 'assets/css/marrison-steps.css',
			array(),
			MARRISON_STEPS_VERSION
		);
	}

	/**
	 * Registra i widget Elementor.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Manager dei widget.
	 */
	public function register_widgets( $widgets_manager ): void {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		require_once MARRISON_STEPS_PATH . 'includes/widgets/class-marrison-steps-widget.php';

		$widgets_manager->register( new \Marrison_Steps_Widget() );
	}
}
