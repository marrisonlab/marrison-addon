<?php
namespace Marrison_Addon\Modules\Recently_Viewed_Products\Macros;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Recently_Viewed_Products extends \Jet_Engine_Base_Macros {

	public function macros_tag() {
		return 'marrison_recently_viewed_products';
	}

	public function macros_name() {
		return esc_html__( 'Visualizzati di recente (prodotti)', 'marrison-addon' );
	}

	public function macros_args() {
		return [
			'marrison_rvp_limit' => [
				'label'   => esc_html__( 'Limite', 'marrison-addon' ),
				'type'    => 'number',
				'default' => 12,
				'min'     => 1,
				'max'     => 100,
			],
			'marrison_rvp_exclude_current' => [
				'label'   => esc_html__( 'Escludi prodotto corrente', 'marrison-addon' ),
				'type'    => 'select',
				'default' => 'yes',
				'options' => [
					'yes' => esc_html__( 'Si', 'marrison-addon' ),
					'no'  => esc_html__( 'No', 'marrison-addon' ),
				],
			],
		];
	}

	public function macros_callback( $args = [] ) {
		$limit = isset( $args['marrison_rvp_limit'] ) ? absint( $args['marrison_rvp_limit'] ) : 12;
		$limit = $limit > 0 ? $limit : 12;
		$exclude_current = ! isset( $args['marrison_rvp_exclude_current'] ) || 'yes' === $args['marrison_rvp_exclude_current'];
		$product_ids = $this->get_recently_viewed_product_ids();
		$current_product_id = $this->get_current_product_id();

		if ( $current_product_id && ! $exclude_current ) {
			array_unshift( $product_ids, $current_product_id );
			$product_ids = array_values( array_unique( $product_ids ) );
		}

		if ( $exclude_current ) {
			if ( $current_product_id ) {
				$product_ids = array_diff( $product_ids, [ $current_product_id ] );
			}
		}

		$product_ids = $this->filter_existing_products( $product_ids );
		$product_ids = array_slice( $product_ids, 0, $limit );

		if ( empty( $product_ids ) ) {
			return $this->get_empty_query_value();
		}

		return implode( ',', $product_ids );
	}

	private function get_empty_query_value() {
		// JetEngine can discard empty or falsy Post In values, which would return all products.
		return '2147483647';
	}

	private function get_recently_viewed_product_ids() {
		if ( empty( $_COOKIE['woocommerce_recently_viewed'] ) ) {
			return [];
		}

		$raw_cookie = sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) );
		$product_ids = array_reverse( array_filter( array_map( 'absint', explode( '|', $raw_cookie ) ) ) );

		return array_values( array_unique( $product_ids ) );
	}

	private function get_current_product_id() {
		global $product;

		if ( $product instanceof \WC_Product ) {
			return $product->get_id();
		}

		$post_id = get_the_ID();

		if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) {
			return 0;
		}

		return absint( $post_id );
	}

	private function filter_existing_products( $product_ids ) {
		if ( empty( $product_ids ) ) {
			return [];
		}

		$filtered_ids = [];

		foreach ( $product_ids as $product_id ) {
			if ( 'publish' !== get_post_status( $product_id ) || 'product' !== get_post_type( $product_id ) ) {
				continue;
			}

			$filtered_ids[] = $product_id;
		}

		return $filtered_ids;
	}
}
