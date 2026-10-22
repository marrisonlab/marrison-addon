<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Listing_Columns {
	public function __construct() {
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
		add_action( 'elementor/frontend/after_register_styles', [ $this, 'register_styles' ] );
		add_action( 'elementor/frontend/after_register_scripts', [ $this, 'register_scripts' ] );
	}

	public function register_styles() {
		wp_register_style( 'marrison-listing-columns', plugins_url( 'assets/css/marrison-listing-columns.css', Marrison_Addon::plugin_file() ), [], Marrison_Addon::asset_version( 'assets/css/marrison-listing-columns.css' ) );
	}

	public function register_scripts() {
		wp_register_script( 'marrison-listing-columns', plugins_url( 'assets/js/marrison-listing-columns.js', Marrison_Addon::plugin_file() ), [ 'jquery', 'elementor-frontend' ], Marrison_Addon::asset_version( 'assets/js/marrison-listing-columns.js' ), true );
	}

	public function register_widgets( $widgets_manager ) {
		require_once __DIR__ . '/listing-columns/widgets/listing-columns-widget.php';
		$widgets_manager->register( new \Marrison_Addon\Modules\Listing_Columns\Widgets\Listing_Columns_Widget() );
	}
}
