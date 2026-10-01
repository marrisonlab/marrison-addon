<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Anchor_Offset {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function enqueue_scripts() {
		if ( ! Marrison_Addon_Context::is_public_frontend_request() ) {
			return;
		}

		$plugin_root_file = Marrison_Addon::plugin_file();

		wp_enqueue_script(
			'marrison-anchor-offset',
			plugins_url( 'assets/js/marrison-anchor-offset.js', $plugin_root_file ),
			[],
			Marrison_Addon::asset_version( 'assets/js/marrison-anchor-offset.js' ),
			true
		);
	}
}
