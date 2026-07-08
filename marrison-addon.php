<?php
/**
 * Plugin Name: Marrison Addon
 * Plugin URI:  https://github.com/marrisonlab/marrison-addon
 * Description: A comprehensive addon for Elementor and WordPress sites. Includes Wrapped Link, Content Ticker, Header Animations, Custom Image Sizes, Custom Cursor, Preloader, and Fast Logout.
 * Version: 1.2.5
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

	const VERSION = '1.2.5';

	private $elementor_modules_initialized = false;
	private $header_animations_initialized = false;

	public function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	private function includes() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/admin/class-marrison-addon-admin.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-marrison-addon-updater.php';
		
		// Load Modules conditionally
		$modules = get_option( 'marrison_addon_modules', [] );
		
		// Modules are disabled by default
		$wrapped_link_enabled = isset( $modules['wrapped_link'] ) && $modules['wrapped_link'];
		$ticker_enabled = isset( $modules['ticker'] ) && $modules['ticker'];
		$header_animations_enabled = isset( $modules['header_animations'] ) && $modules['header_animations'];
		$image_sizes_enabled = isset( $modules['image_sizes'] ) && $modules['image_sizes'];
		$cursor_enabled = isset( $modules['cursor'] ) && $modules['cursor'];
		$preloader_enabled = isset( $modules['preloader'] ) && $modules['preloader'];
		$fast_logout_enabled = isset( $modules['fast_logout'] ) && $modules['fast_logout'];

		if ( $wrapped_link_enabled ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-marrison-addon-wrapped-link.php';
		}

		if ( $ticker_enabled ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-marrison-addon-ticker.php';
		}

		if ( $header_animations_enabled ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-marrison-addon-header-animations.php';
		}

		if ( $image_sizes_enabled ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-marrison-addon-image-sizes.php';
		}

		if ( $cursor_enabled ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-marrison-addon-cursor.php';
		}

		if ( $preloader_enabled ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-marrison-addon-preloader.php';
		}

		if ( $fast_logout_enabled ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/modules/class-marrison-addon-fast-logout.php';
		}
	}

	private function init_hooks() {
		$this->on_plugins_loaded();
		add_action( 'elementor/loaded', [ $this, 'init_elementor_modules' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_links' ] );
	}

	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=marrison_addon_panel' ) . '">' . esc_html__( 'Settings', 'marrison-addon' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function on_plugins_loaded() {
		// Init Updater (Global for Cron/WP-CLI support)
		new Marrison_Addon_Updater( __FILE__, 'marrisonlab', 'marrison-addon' );

		// Admin Panel Init
		if ( is_admin() ) {
			new Marrison_Addon_Admin();
		}

		// Initialize enabled modules
		$modules = get_option( 'marrison_addon_modules', [] );
		$header_animations_enabled = isset( $modules['header_animations'] ) && $modules['header_animations'];
		$image_sizes_enabled = isset( $modules['image_sizes'] ) && $modules['image_sizes'];
		$cursor_enabled = isset( $modules['cursor'] ) && $modules['cursor'];
		$preloader_enabled = isset( $modules['preloader'] ) && $modules['preloader'];
		$fast_logout_enabled = isset( $modules['fast_logout'] ) && $modules['fast_logout'];

		if ( $header_animations_enabled ) {
			$this->init_header_animations_module();
		}

		if ( did_action( 'elementor/loaded' ) ) {
			$this->init_elementor_modules();
		}

		// Independent Modules
		if ( $image_sizes_enabled && class_exists( 'Marrison_Addon_Image_Sizes' ) ) {
			new Marrison_Addon_Image_Sizes();
		}

		if ( $cursor_enabled && class_exists( 'Marrison_Addon_Cursor' ) ) {
			new Marrison_Addon_Cursor();
		}

		if ( $preloader_enabled && class_exists( 'Marrison_Addon_Preloader' ) ) {
			new Marrison_Addon_Preloader();
		}

		if ( $fast_logout_enabled && class_exists( 'Marrison_Addon_Fast_Logout' ) ) {
			new Marrison_Addon_Fast_Logout();
		}
	}

	public function init_elementor_modules() {
		if ( $this->elementor_modules_initialized ) {
			return;
		}

		$this->elementor_modules_initialized = true;

		$modules = get_option( 'marrison_addon_modules', [] );

		$wrapped_link_enabled = isset( $modules['wrapped_link'] ) && $modules['wrapped_link'];
		$ticker_enabled = isset( $modules['ticker'] ) && $modules['ticker'];

		if ( $wrapped_link_enabled && class_exists( 'Marrison_Addon_Wrapped_Link' ) ) {
			new Marrison_Addon_Wrapped_Link();
		}

		if ( $ticker_enabled && class_exists( 'Marrison_Addon_Ticker' ) ) {
			new Marrison_Addon_Ticker();
		}

		$this->init_header_animations_module();
	}

	private function init_header_animations_module() {
		if ( $this->header_animations_initialized || ! class_exists( 'Marrison_Addon_Header_Animations' ) ) {
			return;
		}

		$modules = get_option( 'marrison_addon_modules', [] );
		$header_animations_enabled = isset( $modules['header_animations'] ) && $modules['header_animations'];

		if ( $header_animations_enabled ) {
			$this->header_animations_initialized = true;
			new Marrison_Addon_Header_Animations();
		}
	}
}

function marrison_addon_init() {
	new Marrison_Addon();
}
add_action( 'plugins_loaded', 'marrison_addon_init' );
