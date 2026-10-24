<?php
/**
 * Plugin Name: Marrison Addon
 * Plugin URI:  https://github.com/marrisonlab/marrison-addon
 * Description: A comprehensive addon for Elementor and WordPress sites. Includes Wrapped Link, Read More, Horizontal Scroll, Liquid Background, Steps, Product Discount, Listing Grid Title, Listing Columns, Query Fallback, Dynamic SVG, Recently Viewed Products, Content Ticker, Header Animations, Anchor Offset, Custom Image Sizes, Dynamic Background Image Size, Local Google Fonts, Inline CSS, Browser Cache, Custom Cursor, Preloader, Fast Logout, Calendar Sync, Cookie Manager, PHP Snippets, and Video Thumbnail.
 * Version: 1.3.84
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Angelo Marra
 * Author URI:  https://marrisonlab.com
 * Text Domain: marrison-addon
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI:  https://github.com/marrisonlab/marrison-addon
 * GitHub Plugin URI: marrisonlab/marrison-addon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Marrison_Addon {

	const VERSION = '1.3.84';

	private $elementor_modules_initialized = false;
	private $header_animations_initialized = false;
	private static $module_definitions_cache = [];
	private static $enabled_modules_cache = null;
	private static $module_update_error = null;
	private static $module_effects_cleaned = [];

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
				'beta' => true,
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
				'settings_page' => 'marrison_addon_product_discount',
				'icon' => 'dashicons-tag',
				'title' => self::module_text( 'Sconto Prodotto', $translate ),
				'desc' => self::module_text( 'Aggiunge un widget Elementor che mostra la percentuale di sconto del prodotto WooCommerce corrente.', $translate ),
				'reload' => true,
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
			'listing_columns' => [
				'icon' => 'dashicons-grid-view',
				'title' => self::module_text( 'Colonne Listing', $translate ),
				'desc' => self::module_text( 'Aggiunge un widget Elementor per scegliere le colonne di un Listing Grid JetEngine, con icone e stili personalizzabili.', $translate ),
				'reload' => false,
				'requires_elementor' => true,
				'requires_jet_engine' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Listing_Columns',
				'file' => 'includes/modules/class-marrison-addon-listing-columns.php',
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
			'query_fallback' => [
				'icon' => 'dashicons-randomize',
				'title' => self::module_text( 'Query Fallback', $translate ),
				'desc' => self::module_text( 'Completa i Listing Grid con una seconda Posts Query JetEngine quando i risultati sono vuoti o insufficienti.', $translate ),
				'reload' => false,
				'requires_elementor' => true,
				'requires_jet_engine' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Query_Fallback',
				'file' => 'includes/modules/class-marrison-addon-query-fallback.php',
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
				'settings_page' => 'marrison_addon_image_sizes',
				'icon' => 'dashicons-format-image',
				'title' => self::module_text( 'Dimensioni Immagini', $translate ),
				'desc' => self::module_text( 'Aggiunge dimensioni immagine personalizzate al tema e le rende disponibili nel selettore media.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Image_Sizes',
				'file' => 'includes/modules/class-marrison-addon-image-sizes.php',
			],
			'dynamic_background_image_size' => [
				'icon' => 'dashicons-format-image',
				'title' => self::module_text( 'Dynamic Background Image Size', $translate ),
				'desc' => self::module_text( 'Applica la risoluzione immagine nativa di Elementor agli sfondi dinamici dei container.', $translate ),
				'reload' => true,
				'requires_elementor' => true,
				'boot' => 'elementor',
				'class' => 'Marrison_Addon_Dynamic_Background_Image_Size',
				'file' => 'includes/modules/class-marrison-addon-dynamic-background-image-size.php',
			],
			'local_google_fonts' => [
				'settings_page' => 'marrison_addon_local_google_fonts',
				'icon' => 'dashicons-editor-textcolor',
				'title' => self::module_text( 'Local Google Fonts', $translate ),
				'desc' => self::module_text( 'Scansiona i font Google usati dal sito, scarica i WOFF2 localmente e blocca le stylesheet remote solo quando la copia locale e valida.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Local_Google_Fonts',
				'file' => 'includes/modules/class-marrison-addon-local-google-fonts.php',
			],
			'inline_css' => [
				'icon' => 'dashicons-performance',
				'title' => self::module_text( 'CSS piccoli inline', $translate ),
				'desc' => self::module_text( 'Include i piccoli CSS locali nell\'HTML iniziale per ridurre le richieste separate, conservando ordine e stili. Limite: 16 KiB per file e 32 KiB totali.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Inline_CSS',
				'file' => 'includes/modules/class-marrison-addon-inline-css.php',
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
				'settings_page' => 'marrison_addon_cursor',
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
				'settings_page' => 'marrison_addon_preloader',
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
				'settings_page' => 'marrison_addon_calendar_sync',
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
				'settings_page' => 'marrison-cookie',
				'icon' => 'dashicons-shield-alt',
				'title' => self::module_text( 'Cookie Manager', $translate ),
				'desc' => self::module_text( 'Gestisce banner, preferenze, scansione cookie e wizard iniziale per configurare il consenso.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'independent',
				'class' => 'Marrison_Addon_Cookie_Manager_Module',
				'file' => 'includes/modules/class-marrison-addon-cookie-manager.php',
			],
			'php_snippets' => [
				'icon' => 'dashicons-editor-code',
				'title' => self::module_text( 'PHP Snippets', $translate ),
				'desc' => self::module_text( 'Gestisce snippet PHP personalizzati con ambiti di esecuzione e recupero dagli errori.', $translate ),
				'reload' => true,
				'requires_elementor' => false,
				'boot' => 'early',
				'class' => 'Marrison_Addon_PHP_Snippets',
				'file' => 'includes/modules/class-marrison-addon-php-snippets.php',
				'settings_page' => 'marrison_addon_php_snippets',
			],
			'video_thumbnail' => [
				'settings_page' => 'marrison-video-thumb',
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

		if ( empty( self::$enabled_modules_cache[ $module_id ] ) ) {
			return false;
		}
		$definitions = self::get_module_definitions( false );
		return isset( $definitions[ $module_id ] ) && self::module_dependencies_available( $definitions[ $module_id ] );
	}

	public static function clear_runtime_caches() {
		self::$enabled_modules_cache = null;
	}

	/** Run only when module settings change, before confirming an OFF state. */
	public static function prepare_module_update( $value, $old_value ) {
		self::$module_update_error = null;
		self::$module_effects_cleaned = [];
		if ( ! is_array( $value ) ) {
			self::$module_update_error = new WP_Error( 'invalid_modules', __( 'Impostazioni dei moduli non valide.', 'marrison-addon' ) );
			return $old_value;
		}
		$old_value = is_array( $old_value ) ? $old_value : [];
		foreach ( self::get_module_definitions( false ) as $id => $definition ) {
			if ( ! empty( $value[ $id ] ) && empty( $old_value[ $id ] ) && ! self::module_dependencies_available( $definition ) ) {
				self::$module_update_error = new WP_Error( 'module_dependencies_missing', sprintf(
					/* translators: 1: module name, 2: required plugins. */
					__( '%1$s necessita di %2$s presenti e attivi.', 'marrison-addon' ),
					$definition['title'], implode( ' + ', self::module_required_plugins( $definition ) )
				) );
				if ( function_exists( 'add_settings_error' ) ) {
					add_settings_error( 'marrison_addon_modules', 'module_dependencies_missing', self::$module_update_error->get_error_message() );
				}
				return $old_value;
			}
		}
		foreach ( self::get_module_definitions( false ) as $id => $definition ) {
			if ( ! empty( $old_value[ $id ] ) && empty( $value[ $id ] ) ) {
				$result = self::cleanup_module_effects( $id );
				if ( is_wp_error( $result ) ) {
					self::$module_update_error = $result;
					if ( function_exists( 'add_settings_error' ) ) {
						add_settings_error( 'marrison_addon_modules', $result->get_error_code(), $result->get_error_message() );
					}
					return $old_value;
				}
			}
		}
		return $value;
	}

	public static function get_module_update_error() {
		return self::$module_update_error;
	}

	public static function module_effects_were_cleaned( $module_id ) {
		return ! empty( self::$module_effects_cleaned[ $module_id ] );
	}

	/** Covers AJAX, Settings API, and direct option updates, including bulk writes. */
	public static function module_options_updated( $old_value, $value ) {
		self::clear_runtime_caches();
		$old_value = is_array( $old_value ) ? $old_value : [];
		$value = is_array( $value ) ? $value : [];
		$changed = [];
		$clear_css = false;
		foreach ( self::get_module_definitions( false ) as $id => $definition ) {
			if ( ! empty( $old_value[ $id ] ) === ! empty( $value[ $id ] ) ) {
				continue;
			}
			$changed[ $id ] = ! empty( $value[ $id ] );
			if ( ! empty( $definition['requires_elementor'] ) || 'image_sizes' === $id || 'dynamic_svg' === $id ) {
				$clear_css = true;
			}
			if ( 'dynamic_background_image_size' === $id ) {
				delete_option( 'marrison_addon_dynamic_background_css_version' );
			}
		}
		if ( $clear_css && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		foreach ( $changed as $id => $enabled ) {
			do_action( 'marrison_addon/module_status_changed', $id, $enabled );
		}
	}

	public static function module_option_added( $option, $value ) {
		self::module_options_updated( [], $value );
	}

	/** No module file is loaded here during ordinary requests; cleanup is transitional. */
	public static function cleanup_module_effects( $module_id ) {
		$hooks = [];
		if ( 'cookie_manager' === $module_id ) {
			$hooks = [ 'marrison_cookie_daily_scan', 'marrison_addon_cookie_engine_tick', 'marrison_cookie_consent_cleanup' ];
		} elseif ( 'product_discount' === $module_id ) {
			$hooks = [ 'marrison_product_discount_backfill_batch', 'marrison_product_discount_scheduled_sync', 'marrison_product_discount_scheduled_sync_batch', 'marrison_product_discount_queued_sync_batch' ];
		} elseif ( 'browser_cache' === $module_id ) {
			$file = __DIR__ . '/includes/modules/browser-cache/class-browser-cache-server.php';
			if ( ! is_file( $file ) ) {
				return new WP_Error( 'browser_cache_module_missing', __( 'File Browser Cache mancante: rimozione delle regole non verificata.', 'marrison-addon' ) );
			}
			require_once $file;
			$server = new Marrison_Addon_Browser_Cache_Server();
			$result = $server->remove_htaccess_rules();
			if ( is_wp_error( $result ) ) {
				$server->update_state( [ 'status' => 'error', 'message' => $result->get_error_message() ] );
				return $result;
			}
		}
		foreach ( $hooks as $hook ) {
			$result = wp_unschedule_hook( $hook, true );
			if ( is_wp_error( $result ) || false === $result ) {
				return is_wp_error( $result ) ? $result : new WP_Error( 'module_cron_cleanup', __( 'Impossibile rimuovere le attività programmate del modulo. Riprova.', 'marrison-addon' ) );
			}
			if ( 'product_discount' === $module_id && function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( $hook, null, 'marrison-addon' );
				if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, null, 'marrison-addon' ) ) {
					return new WP_Error( 'module_actions_cleanup', __( 'Sono ancora presenti attività del modulo in coda. Riprova la disattivazione.', 'marrison-addon' ) );
				}
			}
		}
		if ( 'product_discount' === $module_id ) {
			update_option( 'marrison_product_discount_scheduled_frequency', 'manual' );
			delete_option( 'marrison_product_discount_scheduled_sync_page' );
		}
		self::$module_effects_cleaned[ $module_id ] = true;
		return true;
	}

	public static function deactivate( $network_wide = false ) {
		$sites = $network_wide && is_multisite() ? get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) : [];
		if ( $sites ) {
			foreach ( $sites as $site_id ) {
				switch_to_blog( $site_id );
				$result = self::cleanup_deactivated_plugin();
				restore_current_blog();
				if ( is_wp_error( $result ) ) {
					wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => 500, 'back_link' => true ] );
				}
			}
			return;
		}
		$result = self::cleanup_deactivated_plugin();
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => 500, 'back_link' => true ] );
		}
	}

	private static function cleanup_deactivated_plugin() {
		foreach ( [ 'cookie_manager', 'product_discount', 'browser_cache' ] as $id ) {
			$result = self::cleanup_module_effects( $id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		if ( isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		return true;
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
			'Query Fallback' => esc_html__( 'Query Fallback', 'marrison-addon' ),
			'Completa i Listing Grid con una seconda Posts Query JetEngine quando i risultati sono vuoti o insufficienti.' => esc_html__( 'Completa i Listing Grid con una seconda Posts Query JetEngine quando i risultati sono vuoti o insufficienti.', 'marrison-addon' ),
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
			'Colonne Listing' => esc_html__( 'Colonne Listing', 'marrison-addon' ),
			'Aggiunge un widget Elementor per scegliere le colonne di un Listing Grid JetEngine, con icone e stili personalizzabili.' => esc_html__( 'Aggiunge un widget Elementor per scegliere le colonne di un Listing Grid JetEngine, con icone e stili personalizzabili.', 'marrison-addon' ),
			'Titolo Listing' => esc_html__( 'Titolo Listing', 'marrison-addon' ),
			'Aggiunge un campo titolo con controlli di stile direttamente nel widget Listing Grid di JetEngine.' => esc_html__( 'Aggiunge un campo titolo con controlli di stile direttamente nel widget Listing Grid di JetEngine.', 'marrison-addon' ),
			'Dynamic SVG' => esc_html__( 'Dynamic SVG', 'marrison-addon' ),
			'Consente di visualizzare SVG dinamici JetEngine inline e controllarne il colore tramite CSS.' => esc_html__( 'Consente di visualizzare SVG dinamici JetEngine inline e controllarne il colore tramite CSS.', 'marrison-addon' ),
			'Animazioni Header' => esc_html__( 'Animazioni Header', 'marrison-addon' ),
			'Aggiunge animazioni in ingresso extra al widget Heading di Elementor, mantenendo i controlli nativi.' => esc_html__( 'Aggiunge animazioni in ingresso extra al widget Heading di Elementor, mantenendo i controlli nativi.', 'marrison-addon' ),
			'Anchor Offset' => esc_html__( 'Anchor Offset', 'marrison-addon' ),
			'Corregge lo scroll degli anchor link usando l\'altezza dell\'header con ID hdr, evitando sezioni coperte.' => esc_html__( 'Corregge lo scroll degli anchor link usando l\'altezza dell\'header con ID hdr, evitando sezioni coperte.', 'marrison-addon' ),
			'Dimensioni Immagini' => esc_html__( 'Dimensioni Immagini', 'marrison-addon' ),
			'Dynamic Background Image Size' => esc_html__( 'Dynamic Background Image Size', 'marrison-addon' ),
			'Applica la risoluzione immagine nativa di Elementor agli sfondi dinamici dei container.' => esc_html__( 'Applica la risoluzione immagine nativa di Elementor agli sfondi dinamici dei container.', 'marrison-addon' ),
			'Aggiunge dimensioni immagine personalizzate al tema e le rende disponibili nel selettore media.' => esc_html__( 'Aggiunge dimensioni immagine personalizzate al tema e le rende disponibili nel selettore media.', 'marrison-addon' ),
			'Local Google Fonts' => esc_html__( 'Local Google Fonts', 'marrison-addon' ),
			'Scansiona i font Google usati dal sito, scarica i WOFF2 localmente e blocca le stylesheet remote solo quando la copia locale e valida.' => esc_html__( 'Scansiona i font Google usati dal sito, scarica i WOFF2 localmente e blocca le stylesheet remote solo quando la copia locale e valida.', 'marrison-addon' ),
			'CSS piccoli inline' => esc_html__( 'CSS piccoli inline', 'marrison-addon' ),
			'Include i piccoli CSS locali nell\'HTML iniziale per ridurre le richieste separate, conservando ordine e stili. Limite: 16 KiB per file e 32 KiB totali.' => esc_html__( 'Include i piccoli CSS locali nell\'HTML iniziale per ridurre le richieste separate, conservando ordine e stili. Limite: 16 KiB per file e 32 KiB totali.', 'marrison-addon' ),
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
			'PHP Snippets' => esc_html__( 'PHP Snippets', 'marrison-addon' ),
			'Gestisce snippet PHP personalizzati con ambiti di esecuzione e recupero dagli errori.' => esc_html__( 'Gestisce snippet PHP personalizzati con ambiti di esecuzione e recupero dagli errori.', 'marrison-addon' ),
			'Importa miniature YouTube e genera cover automatiche dai video locali caricati nella libreria media.' => esc_html__( 'Importa miniature YouTube e genera cover automatiche dai video locali caricati nella libreria media.', 'marrison-addon' ),
		];

		return $translations;
	}

	public static function module_dependencies_available( $module ) {
		if ( ! empty( $module['requires_elementor'] ) && ! did_action( 'elementor/loaded' ) && ! class_exists( '\\Elementor\\Plugin' ) ) {
			return false;
		}
		if ( ! empty( $module['requires_woocommerce'] ) && ! self::is_woocommerce_active() ) {
			return false;
		}

		if ( ! empty( $module['requires_jet_engine'] ) && ! self::is_jet_engine_active() ) {
			return false;
		}

		return true;
	}

	/** Shared by the dashboard and server-side activation validation. */
	public static function module_required_plugins( $module ) {
		$plugins = [];
		foreach ( [ 'requires_elementor' => 'Elementor', 'requires_woocommerce' => 'WooCommerce', 'requires_jet_engine' => 'JetEngine' ] as $flag => $name ) {
			if ( ! empty( $module[ $flag ] ) ) {
				$plugins[] = $name;
			}
		}
		return $plugins;
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
add_filter( 'pre_update_option_marrison_addon_modules', [ 'Marrison_Addon', 'prepare_module_update' ], 10, 2 );
add_action( 'update_option_marrison_addon_modules', [ 'Marrison_Addon', 'module_options_updated' ], 10, 2 );
add_action( 'add_option_marrison_addon_modules', [ 'Marrison_Addon', 'module_option_added' ], 10, 2 );
register_deactivation_hook( __FILE__, [ 'Marrison_Addon', 'deactivate' ] );

// Only this module needs to register callbacks before plugins_loaded starts.
// Keep the established bootstrap timing of all other modules unchanged.
if ( Marrison_Addon::is_module_enabled( 'php_snippets' ) ) {
	require_once __DIR__ . '/includes/modules/class-marrison-addon-php-snippets.php';
	new Marrison_Addon_PHP_Snippets();
}

// Apply to both the AJAX toggle and the Settings API, including bulk updates.
add_filter( 'sanitize_option_marrison_addon_modules', 'marrison_addon_protect_php_snippets_toggle' );
function marrison_addon_protect_php_snippets_toggle( $value ) {
	$old = get_option( 'marrison_addon_modules', [] );
	if ( ! is_array( $value ) ) {
		return $old;
	}
	foreach ( $value as $id => $enabled ) {
		$value[ $id ] = in_array( $enabled, [ true, 1, '1', 'true', 'on', 'yes' ], true ) ? 1 : 0;
	}
	if ( ( ! empty( $value['php_snippets'] ) !== ! empty( $old['php_snippets'] ) )
		&& ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'unfiltered_html' ) ) ) {
		if ( ! is_array( $value ) ) {
			return $old;
		}
		$value['php_snippets'] = empty( $old['php_snippets'] ) ? 0 : 1;
	}
	return $value;
}
