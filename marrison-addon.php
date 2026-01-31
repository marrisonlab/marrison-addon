<?php
/**
 * Plugin Name: Marrison Addon
 * Plugin URI:  https://github.com/marrisonlab/marrison-addon
 * Description: Adds a wrapped link functionality to Elementor Containers.
 * Version: 1.0.0
 * Author: Angelo Marra
 * Author URI:  https://marrisonlab.com
 * Text Domain: marrison-addon
 * Update URI:  https://github.com/marrisonlab/marrison-addon
 * GitHub Plugin URI: marrisonlab/marrison-addon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Marrison_Addon {

	const VERSION = '1.0.0';

	public function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	private function includes() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/admin/class-marrison-addon-admin.php';
		
		// Load Modules conditionally
		$modules = get_option( 'marrison_addon_modules', [] );
		
		// Modules are disabled by default
		$wrapped_link_enabled = isset( $modules['wrapped_link'] ) && $modules['wrapped_link'];
		$ticker_enabled = isset( $modules['ticker'] ) && $modules['ticker'];
		$image_sizes_enabled = isset( $modules['image_sizes'] ) && $modules['image_sizes'];

		if ( $wrapped_link_enabled ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-marrison-addon-wrapped-link.php';
		}

		if ( $ticker_enabled ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-marrison-addon-ticker.php';
		}

		if ( $image_sizes_enabled ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-marrison-addon-image-sizes.php';
		}
	}

	private function init_hooks() {
		$this->on_plugins_loaded();
		add_action( 'init', [ $this, 'init' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_links' ] );
	}

	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=marrison_addon_panel' ) . '">' . esc_html__( 'Settings', 'marrison-addon' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function on_plugins_loaded() {
		// Admin Panel Init
		if ( is_admin() ) {
			new Marrison_Addon_Admin();
		}
	}

	public function init() {
		// Initialize enabled modules
		$modules = get_option( 'marrison_addon_modules', [] );

		$wrapped_link_enabled = isset( $modules['wrapped_link'] ) && $modules['wrapped_link'];
		$ticker_enabled = isset( $modules['ticker'] ) && $modules['ticker'];
		$image_sizes_enabled = isset( $modules['image_sizes'] ) && $modules['image_sizes'];

		if ( $wrapped_link_enabled && class_exists( 'Marrison_Addon_Wrapped_Link' ) ) {
			new Marrison_Addon_Wrapped_Link();
		}

		if ( $ticker_enabled && class_exists( 'Marrison_Addon_Ticker' ) ) {
			new Marrison_Addon_Ticker();
		}

		if ( $image_sizes_enabled && class_exists( 'Marrison_Addon_Image_Sizes' ) ) {
			new Marrison_Addon_Image_Sizes();
		}
	}
}

function marrison_addon_init() {
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', 'marrison_addon_fail_load' );
		return;
	}

	new Marrison_Addon();
}
add_action( 'plugins_loaded', 'marrison_addon_init' );

function marrison_addon_fail_load() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$message = sprintf(
		/* translators: %s: Plugin Name */
		esc_html__( '"%s" requires "Elementor" to be installed and activated.', 'marrison-addon' ),
		'<strong>' . esc_html__( 'Marrison Addon', 'marrison-addon' ) . '</strong>'
	);

	echo '<div class="notice notice-error is-dismissible"><p>' . $message . '</p></div>';
}
