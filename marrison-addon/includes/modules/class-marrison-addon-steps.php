<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Steps {

	public function __construct() {
		$this->define_constants();
		$this->load_dependencies();
		$this->boot_steps();
	}

	private function define_constants() {
		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		$module_dir       = trailingslashit( plugin_dir_path( __FILE__ ) . 'steps' );
		$module_url       = trailingslashit( plugins_url( 'includes/modules/steps/', $plugin_root_file ) );

		if ( ! defined( 'MARRISON_STEPS_VERSION' ) ) {
			define( 'MARRISON_STEPS_VERSION', Marrison_Addon::VERSION );
		}

		if ( ! defined( 'MARRISON_STEPS_FILE' ) ) {
			define( 'MARRISON_STEPS_FILE', __FILE__ );
		}

		if ( ! defined( 'MARRISON_STEPS_PATH' ) ) {
			define( 'MARRISON_STEPS_PATH', $module_dir );
		}

		if ( ! defined( 'MARRISON_STEPS_URL' ) ) {
			define( 'MARRISON_STEPS_URL', $module_url );
		}
	}

	private function load_dependencies() {
		if ( ! class_exists( 'Marrison_Steps_Plugin', false ) ) {
			require_once MARRISON_STEPS_PATH . 'includes/class-marrison-steps-plugin.php';
		}
	}

	private function boot_steps() {
		if ( class_exists( 'Marrison_Steps_Plugin', false ) ) {
			Marrison_Steps_Plugin::instance();
		}
	}
}
