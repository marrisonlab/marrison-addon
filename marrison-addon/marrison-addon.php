<?php
/**
 * Plugin Name: Marrison Addon
 * Plugin URI:  https://github.com/marrisonlab/marrison-addon
 * Description: A comprehensive addon for Elementor and WordPress sites. Includes Wrapped Link, Read More, Horizontal Scroll, Liquid Background, Steps, Product Discount, Listing Grid Title, Dynamic SVG, Recently Viewed Products, Content Ticker, Header Animations, Anchor Offset, Custom Image Sizes, Local Google Fonts, Browser Cache, Custom Cursor, Preloader, Fast Logout, Calendar Sync, Cookie Manager, and Video Thumbnail.
 * Version: 1.3.46
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

	const VERSION = '1.3.46';

	private $elementor_modules_initialized = false;
	private $header_animations_initialized = false;
	private static $module_definitions_cache = [];
	private static $enabled_modules_cache = null;

	public function __construct() {
		self::clear_runtime_caches();
		$this->includes();
		$this->init_hooks();
	}

	private function includes() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-marrison-addon-context.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/admin/class-marrison-addon-admin.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-marrison-addon-updater.php';

		foreach ( self::get_module_definitions( false ) as $module_id => $module ) {
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
		if ( is_admin() || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
			new Marrison_Addon_Updater( __FILE__, 'marrisonlab', 'marrison-addon' );
		}

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

		foreach ( self::get_module_definitions( false ) as $module_id => $module ) {
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
		foreach ( self::get_module_definitions( false ) as $module_id => $module ) {
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

	public static function get_module_definitions( $translate = true ) {
		$cache_key = $translate ? 'translated' : 'raw';
		if ( isset( self::$module_definitions_cache[ $cache_key ] ) ) {
			return self::$module_definitions_cache[ $cache_key ];
		}

		self::$module_definitions_cache[ $cache_key ] = [
			'wrapped_link' => [
				'icon' => 'dashicons-admin-links',
				'title' => self::module_text( 'Wrapped Link', $translate ),
				'desc' => self::module_text( 'Rende cliccabili container e widget Elementor senza modificare il layout o aggiungere widget extra.', $translate ),
				'reload' => false,
				'requires_elementor' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Wrapped_Link',
				'file' => 'includes/modules/class-marrison-addon-wrapped-link.php',
			],
			'read_more' => [
				'icon' => 'dashicons-editor-expand',
				'title' => self::module_text( 'Leggi di più', $translate ),
				'desc' => self::module_text( 'Aggiunge righe visibili, ellissi e toggle Leggi di più/Leggi di meno ai widget Text Editor e Dynamic Field di JetEngine.', $translate ),
				'reload' => false,
				'requires_elementor' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Read_More',
				'file' => 'includes/modules/class-marrison-addon-read-more.php',
			],
			'horizontal_scroll' => [
				'icon' => 'dashicons-align-wide',
				'title' => self::module_text( 'Scroll Orizzontale', $translate ),
				'desc' => self::module_text( 'Trasforma un Container Elementor in una sezione a scorrimento orizzontale, con pin e controlli responsive.', $translate ),
				'reload' => true,
				'requires_elementor' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Horizontal_Scroll',
				'file' => 'includes/modules/class-marrison-addon-horizontal-scroll.php',
			],
			'liquid_background' => [
				'icon' => 'dashicons-art',
				'title' => self::module_text( 'Liquid Background', $translate ),
				'desc' => self::module_text( 'Aggiunge uno sfondo organico animato ai Container Elementor selezionati, con controlli e preset dedicati.', $translate ),
				'reload' => true,
				'requires_elementor' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Liquid_Background',
				'file' => 'includes/modules/class-marrison-addon-liquid-background.php',
			],
			'ticker' => [
				'icon' => 'dashicons-controls-forward',
				'title' => self::module_text( 'Ticker', $translate ),
				'desc' => self::module_text( 'Mostra testi o notizie in scorrimento continuo, anche partendo da contenuti dinamici.', $translate ),
				'reload' => false,
				'requires_elementor' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Ticker',
				'file' => 'includes/modules/class-marrison-addon-ticker.php',
			],
			'steps' => [
				'icon' => 'dashicons-editor-ol',
				'title' => self::module_text( 'Steps', $translate ),
				'desc' => self::module_text( 'Aggiunge un widget Elementor per creare step responsive con numeri, icone, immagini e connettori personalizzabili.', $translate ),
				'reload' => false,
				'requires_elementor' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Steps',
				'file' => 'includes/modules/class-marrison-addon-steps.php',
			],
			'product_discount' => [
				'icon' => 'dashicons-tag',
				'title' => self::module_text( 'Sconto Prodotto', $translate ),
				'desc' => self::module_text( 'Aggiunge un widget Elementor che mostra la percentuale di sconto del prodotto WooCommerce corrente.', $translate ),
				'reload' => false,
				'requires_elementor' => true,
				'requires_woocommerce' => true,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Product_Discount',
				'file' => 'includes/modules/class-marrison-addon-product-discount.php',
			],
			'recently_viewed_products' => [
				'icon' => 'dashicons-visibility',
				'title' => self::module_text( 'Visualizzati di recente', $translate ),
				'desc' => self::module_text( 'Aggiunge una macro JetEngine con gli ID dei prodotti WooCommerce visualizzati di recente.', $translate ),
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
				'title' => self::module_text( 'Titolo Listing', $translate ),
				'desc' => self::module_text( 'Aggiunge un campo titolo con controlli di stile direttamente nel widget Listing Grid di JetEngine.', $translate ),
				'reload' => false,
				'requires_elementor' => true,
				'requires_jet_engine' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Listing_Title',
				'file' => 'includes/modules/class-marrison-addon-listing-title.php',
			],
			'dynamic_svg' => [
				'icon' => 'dashicons-format-image',
				'title' => self::module_text( 'Dynamic SVG', $translate ),
				'desc' => self::module_text( 'Consente di visualizzare SVG dinamici JetEngine inline e controllarne il colore tramite CSS.', $translate ),
				'reload' => false,
				'requires_elementor' => false,
				'requires_jet_engine' => true,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Dynamic_SVG',
				'file' => 'includes/modules/class-marrison-addon-dynamic-svg.php',
			],
			'header_animations' => [
				'icon' => 'dashicons-format-status',
				'title' => self::module_text( 'Animazioni Header', $translate ),
				'desc' => self::module_text( 'Aggiunge animazioni in ingresso extra al widget Heading di Elementor, mantenendo i controlli nativi.', $translate ),
				'reload' => false,
				'requires_elementor' => true,
				'boot' => 'header',
				'class' => 'Marrison_Addon_Header_Animations',
				'file' => 'includes/modules/class-marrison-addon-header-animations.php',
			],
			'anchor_offset' => [
				'icon' => 'dashicons-editor-unlink',
				'title' => self::module_text( 'Anchor Offset', $translate ),
				'desc' => self::module_text( 'Corregge lo scroll degli anchor link usando l\'altezza dell\'header con ID hdr, evitando sezioni coperte.', $translate ),
				'reload' => false,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Anchor_Offset',
				'file' => 'includes/modules/class-marrison-addon-anchor-offset.php',
			],
			'image_sizes' => [
				'icon' => 'dashicons-format-image',
				'title' => self::module_text( 'Dimensioni Immagini', $translate ),
				'desc' => self::module_text( 'Aggiunge dimensioni immagine personalizzate al tema e le rende disponibili nel selettore media.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Image_Sizes',
				'file' => 'includes/modules/class-marrison-addon-image-sizes.php',
			],
			'local_google_fonts' => [
				'icon' => 'dashicons-editor-textcolor',
				'title' => self::module_text( 'Local Google Fonts', $translate ),
				'desc' => self::module_text( 'Scansiona i font Google usati dal sito, scarica i WOFF2 localmente e blocca le stylesheet remote solo quando la copia locale e valida.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Local_Google_Fonts',
				'file' => 'includes/modules/class-marrison-addon-local-google-fonts.php',
			],
			'browser_cache' => [
				'icon' => 'dashicons-performance',
				'title' => self::module_text( 'Browser Cache', $translate ),
				'desc' => self::module_text( 'Configura header HTTP per la cache browser degli asset statici senza rimuovere il versioning WordPress.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Browser_Cache',
				'file' => 'includes/modules/class-marrison-addon-browser-cache.php',
				'settings_page' => 'marrison_addon_browser_cache',
			],
			'cursor' => [
				'icon' => 'dashicons-arrow-right-alt2',
				'title' => self::module_text( 'Cursore Animato', $translate ),
				'desc' => self::module_text( 'Sostituisce il cursore standard con un effetto animato personalizzabile, visibile solo sul frontend.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Cursor',
				'file' => 'includes/modules/class-marrison-addon-cursor.php',
			],
			'preloader' => [
				'icon' => 'dashicons-update',
				'title' => self::module_text( 'Preloader', $translate ),
				'desc' => self::module_text( 'Mostra una schermata di caricamento con logo, stile e animazione personalizzati durante il caricamento della pagina.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Preloader',
				'file' => 'includes/modules/class-marrison-addon-preloader.php',
			],
			'fast_logout' => [
				'icon' => 'dashicons-migrate',
				'title' => self::module_text( 'Fast Logout', $translate ),
				'desc' => self::module_text( 'Reindirizza subito alla home page dopo il logout, saltando la schermata standard di WordPress.', $translate ),
				'reload' => false,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Fast_Logout',
				'file' => 'includes/modules/class-marrison-addon-fast-logout.php',
			],
			'calendar_sync' => [
				'icon' => 'dashicons-calendar-alt',
				'title' => self::module_text( 'Calendar Sync', $translate ),
				'desc' => self::module_text( 'Genera link Google Calendar e file ICS dai contenuti del sito partendo dai meta campi.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Calendar_Sync',
				'file' => 'includes/modules/class-marrison-addon-calendar-sync.php',
			],
			'cookie_manager' => [
				'icon' => 'dashicons-shield-alt',
				'title' => self::module_text( 'Cookie Manager', $translate ),
				'desc' => self::module_text( 'Gestisce banner, preferenze, scansione cookie e wizard iniziale per configurare il consenso.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Cookie_Manager_Module',
				'file' => 'includes/modules/class-marrison-addon-cookie-manager.php',
			],
			'video_thumbnail' => [
				'icon' => 'dashicons-video-alt3',
				'title' => self::module_text( 'Video Thumbnail', $translate ),
				'desc' => self::module_text( 'Importa miniature YouTube e genera cover automatiche dai video locali caricati nella libreria media.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Video_Thumbnail',
				'file' => 'includes/modules/class-marrison-addon-video-thumbnail.php',
			],
		];

		return self::$module_definitions_cache[ $cache_key ];
	}

	private static function module_text( $text, $translate ) {
		if ( ! $translate ) {
			return $text;
		}

		$translations = self::get_module_text_translations();
		if ( isset( $translations[ $text ] ) ) {
			return $translations[ $text ];
		}

		return function_exists( 'esc_html' ) ? esc_html( $text ) : $text;
	}

	public static function is_module_enabled( $module_id ) {
		if ( null === self::$enabled_modules_cache ) {
			$modules = get_option( 'marrison_addon_modules', [] );
			self::$enabled_modules_cache = is_array( $modules ) ? $modules : [];
		}

		return ! empty( self::$enabled_modules_cache[ $module_id ] );
	}

	public static function clear_runtime_caches() {
		self::$enabled_modules_cache = null;
	}

	public static function plugin_file() {
		return __FILE__;
	}

	public static function plugin_path( $relative_path = '' ) {
		return plugin_dir_path( __FILE__ ) . ltrim( (string) $relative_path, '/\\' );
	}

	public static function asset_version( $relative_path = '' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && '' !== (string) $relative_path ) {
			$path = self::plugin_path( $relative_path );
			if ( file_exists( $path ) ) {
				return self::VERSION . '.' . filemtime( $path );
			}
		}

		return self::VERSION;
	}

	private static function get_module_text_translations() {
		static $translations = null;

		if ( null !== $translations ) {
			return $translations;
		}

		$translations = [
			'Wrapped Link' => esc_html__( 'Wrapped Link', 'marrison-addon' ),
			'Rende cliccabili container e widget Elementor senza modificare il layout o aggiungere widget extra.' => esc_html__( 'Rende cliccabili container e widget Elementor senza modificare il layout o aggiungere widget extra.', 'marrison-addon' ),
			'Leggi di più' => esc_html__( 'Leggi di più', 'marrison-addon' ),
			'Aggiunge righe visibili, ellissi e toggle Leggi di più/Leggi di meno ai widget Text Editor e Dynamic Field di JetEngine.' => esc_html__( 'Aggiunge righe visibili, ellissi e toggle Leggi di più/Leggi di meno ai widget Text Editor e Dynamic Field di JetEngine.', 'marrison-addon' ),
			'Scroll Orizzontale' => esc_html__( 'Scroll Orizzontale', 'marrison-addon' ),
			'Trasforma un Container Elementor in una sezione a scorrimento orizzontale, con pin e controlli responsive.' => esc_html__( 'Trasforma un Container Elementor in una sezione a scorrimento orizzontale, con pin e controlli responsive.', 'marrison-addon' ),
			'Liquid Background' => esc_html__( 'Liquid Background', 'marrison-addon' ),
			'Aggiunge uno sfondo organico animato ai Container Elementor selezionati, con controlli e preset dedicati.' => esc_html__( 'Aggiunge uno sfondo organico animato ai Container Elementor selezionati, con controlli e preset dedicati.', 'marrison-addon' ),
			'Ticker' => esc_html__( 'Ticker', 'marrison-addon' ),
			'Mostra testi o notizie in scorrimento continuo, anche partendo da contenuti dinamici.' => esc_html__( 'Mostra testi o notizie in scorrimento continuo, anche partendo da contenuti dinamici.', 'marrison-addon' ),
			'Steps' => esc_html__( 'Steps', 'marrison-addon' ),
			'Aggiunge un widget Elementor per creare step responsive con numeri, icone, immagini e connettori personalizzabili.' => esc_html__( 'Aggiunge un widget Elementor per creare step responsive con numeri, icone, immagini e connettori personalizzabili.', 'marrison-addon' ),
			'Sconto Prodotto' => esc_html__( 'Sconto Prodotto', 'marrison-addon' ),
			'Aggiunge un widget Elementor che mostra la percentuale di sconto del prodotto WooCommerce corrente.' => esc_html__( 'Aggiunge un widget Elementor che mostra la percentuale di sconto del prodotto WooCommerce corrente.', 'marrison-addon' ),
			'Visualizzati di recente' => esc_html__( 'Visualizzati di recente', 'marrison-addon' ),
			'Aggiunge una macro JetEngine con gli ID dei prodotti WooCommerce visualizzati di recente.' => esc_html__( 'Aggiunge una macro JetEngine con gli ID dei prodotti WooCommerce visualizzati di recente.', 'marrison-addon' ),
			'Titolo Listing' => esc_html__( 'Titolo Listing', 'marrison-addon' ),
			'Aggiunge un campo titolo con controlli di stile direttamente nel widget Listing Grid di JetEngine.' => esc_html__( 'Aggiunge un campo titolo con controlli di stile direttamente nel widget Listing Grid di JetEngine.', 'marrison-addon' ),
			'Dynamic SVG' => esc_html__( 'Dynamic SVG', 'marrison-addon' ),
			'Consente di visualizzare SVG dinamici JetEngine inline e controllarne il colore tramite CSS.' => esc_html__( 'Consente di visualizzare SVG dinamici JetEngine inline e controllarne il colore tramite CSS.', 'marrison-addon' ),
			'Animazioni Header' => esc_html__( 'Animazioni Header', 'marrison-addon' ),
			'Aggiunge animazioni in ingresso extra al widget Heading di Elementor, mantenendo i controlli nativi.' => esc_html__( 'Aggiunge animazioni in ingresso extra al widget Heading di Elementor, mantenendo i controlli nativi.', 'marrison-addon' ),
			'Anchor Offset' => esc_html__( 'Anchor Offset', 'marrison-addon' ),
			'Corregge lo scroll degli anchor link usando l\'altezza dell\'header con ID hdr, evitando sezioni coperte.' => esc_html__( 'Corregge lo scroll degli anchor link usando l\'altezza dell\'header con ID hdr, evitando sezioni coperte.', 'marrison-addon' ),
			'Dimensioni Immagini' => esc_html__( 'Dimensioni Immagini', 'marrison-addon' ),
			'Aggiunge dimensioni immagine personalizzate al tema e le rende disponibili nel selettore media.' => esc_html__( 'Aggiunge dimensioni immagine personalizzate al tema e le rende disponibili nel selettore media.', 'marrison-addon' ),
			'Local Google Fonts' => esc_html__( 'Local Google Fonts', 'marrison-addon' ),
			'Scansiona i font Google usati dal sito, scarica i WOFF2 localmente e blocca le stylesheet remote solo quando la copia locale e valida.' => esc_html__( 'Scansiona i font Google usati dal sito, scarica i WOFF2 localmente e blocca le stylesheet remote solo quando la copia locale e valida.', 'marrison-addon' ),
			'Browser Cache' => esc_html__( 'Browser Cache', 'marrison-addon' ),
			'Configura header HTTP per la cache browser degli asset statici senza rimuovere il versioning WordPress.' => esc_html__( 'Configura header HTTP per la cache browser degli asset statici senza rimuovere il versioning WordPress.', 'marrison-addon' ),
			'Cursore Animato' => esc_html__( 'Cursore Animato', 'marrison-addon' ),
			'Sostituisce il cursore standard con un effetto animato personalizzabile, visibile solo sul frontend.' => esc_html__( 'Sostituisce il cursore standard con un effetto animato personalizzabile, visibile solo sul frontend.', 'marrison-addon' ),
			'Preloader' => esc_html__( 'Preloader', 'marrison-addon' ),
			'Mostra una schermata di caricamento con logo, stile e animazione personalizzati durante il caricamento della pagina.' => esc_html__( 'Mostra una schermata di caricamento con logo, stile e animazione personalizzati durante il caricamento della pagina.', 'marrison-addon' ),
			'Fast Logout' => esc_html__( 'Fast Logout', 'marrison-addon' ),
			'Reindirizza subito alla home page dopo il logout, saltando la schermata standard di WordPress.' => esc_html__( 'Reindirizza subito alla home page dopo il logout, saltando la schermata standard di WordPress.', 'marrison-addon' ),
			'Calendar Sync' => esc_html__( 'Calendar Sync', 'marrison-addon' ),
			'Genera link Google Calendar e file ICS dai contenuti del sito partendo dai meta campi.' => esc_html__( 'Genera link Google Calendar e file ICS dai contenuti del sito partendo dai meta campi.', 'marrison-addon' ),
			'Cookie Manager' => esc_html__( 'Cookie Manager', 'marrison-addon' ),
			'Gestisce banner, preferenze, scansione cookie e wizard iniziale per configurare il consenso.' => esc_html__( 'Gestisce banner, preferenze, scansione cookie e wizard iniziale per configurare il consenso.', 'marrison-addon' ),
			'Video Thumbnail' => esc_html__( 'Video Thumbnail', 'marrison-addon' ),
			'Importa miniature YouTube e genera cover automatiche dai video locali caricati nella libreria media.' => esc_html__( 'Importa miniature YouTube e genera cover automatiche dai video locali caricati nella libreria media.', 'marrison-addon' ),
		];

		return $translations;
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
