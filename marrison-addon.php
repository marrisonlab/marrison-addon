<?php
/**
 * Plugin Name: Marrison Addon
 * Plugin URI:  https://github.com/marrisonlab/marrison-addon
 * Description: A comprehensive addon for Elementor and WordPress sites. Includes Wrapped Link, Steps, Product Discount, Listing Grid Title, Recently Viewed Products, Content Ticker, Header Animations, Anchor Offset, Custom Image Sizes, Custom Cursor, Preloader, Fast Logout, Calendar Sync, Cookie Manager, and Video Thumbnail.
 * Version: 1.3.25
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

	const VERSION = '1.3.25';

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

			if ( ! self::module_dependencies_available( $module ) ) {
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

			if ( ! self::module_dependencies_available( $module ) ) {
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

			if ( ! self::module_dependencies_available( $module ) ) {
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
				'icon' => 'dashicons-admin-links',
				'title' => esc_html__( 'Wrapped Link', 'marrison-addon' ),
				'desc' => esc_html__( 'Rende cliccabili container e widget Elementor senza modificare il layout o aggiungere widget extra.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Wrapped_Link',
				'file' => 'includes/modules/class-marrison-addon-wrapped-link.php',
			],
			'ticker' => [
				'icon' => 'dashicons-controls-forward',
				'title' => esc_html__( 'Ticker', 'marrison-addon' ),
				'desc' => esc_html__( 'Mostra testi o notizie in scorrimento continuo, anche partendo da contenuti dinamici.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Ticker',
				'file' => 'includes/modules/class-marrison-addon-ticker.php',
			],
			'steps' => [
				'icon' => 'dashicons-editor-ol',
				'title' => esc_html__( 'Steps', 'marrison-addon' ),
				'desc' => esc_html__( 'Aggiunge un widget Elementor per creare step responsive con numeri, icone, immagini e connettori personalizzabili.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Steps',
				'file' => 'includes/modules/class-marrison-addon-steps.php',
			],
			'product_discount' => [
				'icon' => 'dashicons-tag',
				'title' => esc_html__( 'Sconto Prodotto', 'marrison-addon' ),
				'desc' => esc_html__( 'Aggiunge un widget Elementor che mostra la percentuale di sconto del prodotto WooCommerce corrente.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => true,
				'requires_woocommerce' => true,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Product_Discount',
				'file' => 'includes/modules/class-marrison-addon-product-discount.php',
			],
			'recently_viewed_products' => [
				'icon' => 'dashicons-visibility',
				'title' => esc_html__( 'Visualizzati di recente', 'marrison-addon' ),
				'desc' => esc_html__( 'Aggiunge una macro JetEngine con gli ID dei prodotti WooCommerce visualizzati di recente.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => false,
				'requires_woocommerce' => true,
				'requires_jet_engine' => true,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Recently_Viewed_Products',
				'file' => 'includes/modules/class-marrison-addon-recently-viewed-products.php',
			],
			'listing_title' => [
				'icon' => 'dashicons-heading',
				'title' => esc_html__( 'Titolo Listing', 'marrison-addon' ),
				'desc' => esc_html__( 'Aggiunge un campo titolo con controlli di stile direttamente nel widget Listing Grid di JetEngine.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => true,
				'requires_jet_engine' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Listing_Title',
				'file' => 'includes/modules/class-marrison-addon-listing-title.php',
			],
			'header_animations' => [
				'icon' => 'dashicons-format-status',
				'title' => esc_html__( 'Animazioni Header', 'marrison-addon' ),
				'desc' => esc_html__( 'Aggiunge animazioni in ingresso extra al widget Heading di Elementor, mantenendo i controlli nativi.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => true,
				'boot' => 'header',
				'class' => 'Marrison_Addon_Header_Animations',
				'file' => 'includes/modules/class-marrison-addon-header-animations.php',
			],
			'anchor_offset' => [
				'icon' => 'dashicons-editor-unlink',
				'title' => esc_html__( 'Anchor Offset', 'marrison-addon' ),
				'desc' => esc_html__( 'Corregge lo scroll degli anchor link usando l\'altezza dell\'header con ID hdr, evitando sezioni coperte.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Anchor_Offset',
				'file' => 'includes/modules/class-marrison-addon-anchor-offset.php',
			],
			'image_sizes' => [
				'icon' => 'dashicons-format-image',
				'title' => esc_html__( 'Dimensioni Immagini', 'marrison-addon' ),
				'desc' => esc_html__( 'Aggiunge dimensioni immagine personalizzate al tema e le rende disponibili nel selettore media.', 'marrison-addon' ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Image_Sizes',
				'file' => 'includes/modules/class-marrison-addon-image-sizes.php',
			],
			'cursor' => [
				'icon' => 'dashicons-arrow-right-alt2',
				'title' => esc_html__( 'Cursore Animato', 'marrison-addon' ),
				'desc' => esc_html__( 'Sostituisce il cursore standard con un effetto animato personalizzabile, visibile solo sul frontend.', 'marrison-addon' ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Cursor',
				'file' => 'includes/modules/class-marrison-addon-cursor.php',
			],
			'preloader' => [
				'icon' => 'dashicons-update',
				'title' => esc_html__( 'Preloader', 'marrison-addon' ),
				'desc' => esc_html__( 'Mostra una schermata di caricamento con logo, stile e animazione personalizzati durante il caricamento della pagina.', 'marrison-addon' ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Preloader',
				'file' => 'includes/modules/class-marrison-addon-preloader.php',
			],
			'fast_logout' => [
				'icon' => 'dashicons-migrate',
				'title' => esc_html__( 'Fast Logout', 'marrison-addon' ),
				'desc' => esc_html__( 'Reindirizza subito alla home page dopo il logout, saltando la schermata standard di WordPress.', 'marrison-addon' ),
				'reload' => false,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Fast_Logout',
				'file' => 'includes/modules/class-marrison-addon-fast-logout.php',
			],
			'calendar_sync' => [
				'icon' => 'dashicons-calendar-alt',
				'title' => esc_html__( 'Calendar Sync', 'marrison-addon' ),
				'desc' => esc_html__( 'Genera link Google Calendar e file ICS dai contenuti del sito partendo dai meta campi.', 'marrison-addon' ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Calendar_Sync',
				'file' => 'includes/modules/class-marrison-addon-calendar-sync.php',
			],
			'cookie_manager' => [
				'icon' => 'dashicons-shield-alt',
				'title' => esc_html__( 'Cookie Manager', 'marrison-addon' ),
				'desc' => esc_html__( 'Gestisce banner, preferenze, scansione cookie e wizard iniziale per configurare il consenso.', 'marrison-addon' ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Cookie_Manager_Module',
				'file' => 'includes/modules/class-marrison-addon-cookie-manager.php',
			],
			'video_thumbnail' => [
				'icon' => 'dashicons-video-alt3',
				'title' => esc_html__( 'Video Thumbnail', 'marrison-addon' ),
				'desc' => esc_html__( 'Importa miniature YouTube e genera cover automatiche dai video locali caricati nella libreria media.', 'marrison-addon' ),
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

	public static function module_dependencies_available( $module ) {
		if ( ! empty( $module['requires_woocommerce'] ) && ! self::is_woocommerce_active() ) {
			return false;
		}

		if ( ! empty( $module['requires_jet_engine'] ) && ! self::is_jet_engine_active() ) {
			return false;
		}

		return true;
	}

	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
	}

	public static function is_jet_engine_active() {
		return function_exists( 'jet_engine' ) || class_exists( 'Jet_Engine' );
	}
}

function marrison_addon_init() {
	new Marrison_Addon();
}
add_action( 'plugins_loaded', 'marrison_addon_init' );
