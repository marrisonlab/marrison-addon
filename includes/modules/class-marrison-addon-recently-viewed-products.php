<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Recently_Viewed_Products {

	public function __construct() {
		if ( ! Marrison_Addon::is_woocommerce_active() || ! Marrison_Addon::is_jet_engine_active() ) {
			return;
		}

		add_action( 'template_redirect', [ $this, 'track_product_view' ], 20 );
		add_action( 'jet-engine/register-macros', [ $this, 'register_macros' ] );
	}

	public function track_product_view() {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'wc_setcookie' ) ) {
			return;
		}

		$product_id = get_queried_object_id();

		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		$viewed_products = [];

		if ( ! empty( $_COOKIE['woocommerce_recently_viewed'] ) ) {
			$viewed_products = wp_parse_id_list( explode( '|', wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) );
		}

		$viewed_products = array_diff( $viewed_products, [ $product_id ] );
		$viewed_products[] = $product_id;

		$max_tracked_products = (int) apply_filters( 'marrison_addon/recently_viewed_products/max_tracked', 50 );
		$max_tracked_products = $max_tracked_products > 0 ? $max_tracked_products : 50;

		if ( count( $viewed_products ) > $max_tracked_products ) {
			$viewed_products = array_slice( $viewed_products, -1 * $max_tracked_products );
		}

		$cookie_value = implode( '|', $viewed_products );

		wc_setcookie( 'woocommerce_recently_viewed', $cookie_value );
		$_COOKIE['woocommerce_recently_viewed'] = $cookie_value;
	}

	public function register_macros() {
		if ( ! class_exists( 'Jet_Engine_Base_Macros' ) ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'recently-viewed-products/macros/recently-viewed-products.php';

		new \Marrison_Addon\Modules\Recently_Viewed_Products\Macros\Recently_Viewed_Products();
	}
}
