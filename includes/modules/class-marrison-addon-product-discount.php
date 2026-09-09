<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Product_Discount {

	public function __construct() {
		if ( ! Marrison_Addon::is_woocommerce_active() ) {
			return;
		}

		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
	}

	public function register_widgets( $widgets_manager ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'product-discount/widgets/product-discount-widget.php';

		$widgets_manager->register( new \Marrison_Addon\Modules\Product_Discount\Widgets\Product_Discount_Widget() );
	}
}
