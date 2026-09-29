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

		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		$script_path      = plugin_dir_path( $plugin_root_file ) . 'assets/js/marrison-anchor-offset.js';
		$asset_version    = Marrison_Addon::VERSION;

		if ( file_exists( $script_path ) ) {
			$asset_version .= '.' . filemtime( $script_path );
		}

		wp_enqueue_script(
			'marrison-anchor-offset',
			plugins_url( 'assets/js/marrison-anchor-offset.js', $plugin_root_file ),
			[],
			$asset_version,
			true
		);
	}
}
