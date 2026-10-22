<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Ticker {

	public function __construct() {
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
		add_action( 'elementor/frontend/after_register_styles', [ $this, 'register_styles' ] );
	}

	public function register_styles() {
		wp_register_style(
			'marrison-addon-ticker',
			plugins_url( 'assets/css/marrison-ticker.css', Marrison_Addon::plugin_file() ),
			[],
			Marrison_Addon::asset_version( 'assets/css/marrison-ticker.css' )
		);
	}

	public function register_widgets( $widgets_manager ) {
		require_once plugin_dir_path( __FILE__ ) . 'ticker/widgets/ticker-widget.php';
		$widgets_manager->register( new \Marrison_Addon\Modules\Ticker\Widgets\Ticker_Widget() );
	}
}
