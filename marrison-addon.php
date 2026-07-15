<?php
/**
 * Plugin Name: Marrison Addon
 * Plugin URI:  https://github.com/marrisonlab/marrison-addon
 * Description: A comprehensive addon for Elementor and WordPress sites. Includes Wrapped Link, Content Ticker, Header Animations, Custom Image Sizes, Custom Cursor, Preloader, Fast Logout, Calendar Sync, Cookie Manager, and Video Thumbnail.
 * Version: 1.3.1
 * Author: Marrisonlab
 * Author URI:  https://marrisonlab.com
 * Text Domain: marrison-addon
 * Update URI:  https://github.com/marrisonlab/marrison-addon
 * GitHub Plugin URI: marrisonlab/marrison-addon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Marrison_Addon {

	const VERSION = '1.3.1';

	private $elementor_modules_initialized = false;
	private $header_animations_initialized = false;

	public function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	private function includes() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-marrison-addon-context.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/admin/class-marrison-addon-admin.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-marrison-addon-updater.php';

		foreach ( self::get_module_definitions() as $module_id => $module ) {
			if ( ! self::is_module_enabled( $module_id ) || empty( $module['file'] ) ) {
				continue;
			}

			require_once plugin_dir_path( __FILE__ ) . $module['file'];
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

		if ( self::is_module_enabled( 'header_animations' ) ) {
			$this->init_header_animations_module();
		}

		if ( did_action( 'elementor/loaded' ) ) {
			$this->init_elementor_modules();
		}

		$this->init_independent_modules();
	}

	public function init_elementor_modules() {
		if ( $this->elementor_modules_initialized ) {
			return;
		}

		$this->elementor_modules_initialized = true;

		foreach ( self::get_module_definitions() as $module_id => $module ) {
			if ( empty( $module['boot'] ) || 'elementor' !== $module['boot'] ) {
				continue;
			}

			if ( self::is_module_enabled( $module_id ) && ! empty( $module['class'] ) && class_exists( $module['class'] ) ) {
				new $module['class']();
			}
		}

		$this->init_header_animations_module();
	}

	private function init_header_animations_module() {
		if ( $this->header_animations_initialized || ! class_exists( 'Marrison_Addon_Header_Animations' ) ) {
			return;
		}

		if ( self::is_module_enabled( 'header_animations' ) ) {
			$this->header_animations_initialized = true;
			new Marrison_Addon_Header_Animations();
		}
	}

	private function init_independent_modules() {
		foreach ( self::get_module_definitions() as $module_id => $module ) {
			if ( empty( $module['boot'] ) || 'independent' !== $module['boot'] ) {
				continue;
			}

			if ( self::is_module_enabled( $module_id ) && ! empty( $module['class'] ) && class_exists( $module['class'] ) ) {
				new $module['class']();
			}
		}
	}

	public static function get_module_definitions() {
		return [
			'wrapped_link' => [
				'title' => esc_html__( 'Wrapped Link', 'marrison-addon' ),
				'desc' => esc_html__( 'Aggiungi link a qualsiasi contenitore Elementor.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Wrapped_Link',
				'file' => 'includes/modules/class-marrison-addon-wrapped-link.php',
			],
			'ticker' => [
				'title' => esc_html__( 'Ticker', 'marrison-addon' ),
				'desc' => esc_html__( 'Widget ticker notizie con supporto JetEngine.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Ticker',
				'file' => 'includes/modules/class-marrison-addon-ticker.php',
			],
			'header_animations' => [
				'title' => esc_html__( 'Animazioni Header', 'marrison-addon' ),
				'desc' => esc_html__( 'Aggiunge animazioni in ingresso extra solo al widget Heading di Elementor.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => true,
				'boot' => 'header',
				'class' => 'Marrison_Addon_Header_Animations',
				'file' => 'includes/modules/class-marrison-addon-header-animations.php',
			],
			'image_sizes' => [
				'title' => esc_html__( 'Dimensioni Immagini', 'marrison-addon' ),
				'desc' => esc_html__( 'Registra dimensioni immagine personalizzate e aggiungile al selettore media.', 'marrison-addon' ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Image_Sizes',
				'file' => 'includes/modules/class-marrison-addon-image-sizes.php',
			],
			'cursor' => [
				'title' => esc_html__( 'Cursore Animato', 'marrison-addon' ),
				'desc' => esc_html__( 'Sostituisce il cursore predefinito con un puntatore animato personalizzabile.', 'marrison-addon' ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Cursor',
				'file' => 'includes/modules/class-marrison-addon-cursor.php',
			],
			'preloader' => [
				'title' => esc_html__( 'Preloader', 'marrison-addon' ),
				'desc' => esc_html__( 'Aggiungi una schermata di caricamento con logo personalizzato e spinner.', 'marrison-addon' ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Preloader',
				'file' => 'includes/modules/class-marrison-addon-preloader.php',
			],
			'fast_logout' => [
				'title' => esc_html__( 'Fast Logout', 'marrison-addon' ),
				'desc' => esc_html__( 'Reindirizza automaticamente alla home page dopo il logout.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Fast_Logout',
				'file' => 'includes/modules/class-marrison-addon-fast-logout.php',
			],
			'calendar_sync' => [
				'title' => esc_html__( 'Calendar Sync', 'marrison-addon' ),
				'desc' => esc_html__( 'Genera link Google Calendar e file ICS da post e CPT.', 'marrison-addon' ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Calendar_Sync',
				'file' => 'includes/modules/class-marrison-addon-calendar-sync.php',
			],
			'cookie_manager' => [
				'title' => esc_html__( 'Cookie Manager', 'marrison-addon' ),
				'desc' => esc_html__( 'Gestisce banner consenso, preferenze, scansione cookie e wizard di setup.', 'marrison-addon' ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Cookie_Manager_Module',
				'file' => 'includes/modules/class-marrison-addon-cookie-manager.php',
			],
			'video_thumbnail' => [
				'title' => esc_html__( 'Video Thumbnail', 'marrison-addon' ),
				'desc' => esc_html__( 'Importa miniature YouTube nella libreria media di WordPress.', 'marrison-addon' ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Video_Thumbnail',
				'file' => 'includes/modules/class-marrison-addon-video-thumbnail.php',
			],
		];
	}

	public static function is_module_enabled( $module_id ) {
		$modules = get_option( 'marrison_addon_modules', [] );

		return ! empty( $modules[ $module_id ] );
	}
}

function marrison_addon_init() {
	new Marrison_Addon();
}
add_action( 'plugins_loaded', 'marrison_addon_init' );
