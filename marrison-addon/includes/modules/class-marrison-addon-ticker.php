<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Ticker {

	public function __construct() {
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
	}

	public function register_widgets( $widgets_manager ) {
		require_once plugin_dir_path( __FILE__ ) . 'ticker/widgets/ticker-widget.php';
		$widgets_manager->register( new \Marrison_Addon\Modules\Ticker\Widgets\Ticker_Widget() );
	}
}
