<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Cookie_Manager_Module {

	private static $booted = false;

	public function __construct() {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		$this->define_constants();
		$this->load_support_functions();
		$this->load_dependencies();
		$this->ensure_installation();
		$this->init_hooks();
		$this->init_components();
	}

	private function define_constants() {
		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		$module_dir = trailingslashit( plugin_dir_path( __FILE__ ) . 'cookie-manager' );
		$module_url = trailingslashit( plugins_url( 'includes/modules/cookie-manager/', $plugin_root_file ) );

		if ( ! defined( 'MARRISON_COOKIE_VERSION' ) ) {
			define( 'MARRISON_COOKIE_VERSION', Marrison_Addon::VERSION );
		}

		if ( ! defined( 'MARRISON_COOKIE_PLUGIN_FILE' ) ) {
			define( 'MARRISON_COOKIE_PLUGIN_FILE', __FILE__ );
		}

		if ( ! defined( 'MARRISON_COOKIE_PLUGIN_DIR' ) ) {
			define( 'MARRISON_COOKIE_PLUGIN_DIR', $module_dir );
		}

		if ( ! defined( 'MARRISON_COOKIE_PLUGIN_URL' ) ) {
			define( 'MARRISON_COOKIE_PLUGIN_URL', $module_url );
		}

		if ( ! defined( 'MARRISON_COOKIE_PLUGIN_BASENAME' ) ) {
			define( 'MARRISON_COOKIE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		}

		if ( ! defined( 'MARRISON_COOKIE_GITHUB_REPO' ) ) {
			define( 'MARRISON_COOKIE_GITHUB_REPO', 'marrisonlab/marrison-cookie' );
		}
	}

	private function load_support_functions() {
		if ( ! function_exists( 'marrison_cookie_is_english_site' ) ) {
			function marrison_cookie_is_english_site() {
				$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
				return is_string( $locale ) && stripos( $locale, 'en_' ) === 0;
			}
		}

		if ( ! function_exists( 'marrison_cookie_site_text' ) ) {
			function marrison_cookie_site_text( $it, $en ) {
				return marrison_cookie_is_english_site() ? $en : $it;
			}
		}

		if ( ! function_exists( 'marrison_cookie_category_labels' ) ) {
			function marrison_cookie_category_labels() {
				return array(
					'necessary'  => marrison_cookie_site_text( 'Necessari', 'Necessary' ),
					'functional' => marrison_cookie_site_text( 'Funzionali', 'Functional' ),
					'analytics'  => marrison_cookie_site_text( 'Analitici', 'Analytics' ),
					'marketing'  => marrison_cookie_site_text( 'Marketing', 'Marketing' ),
				);
			}
		}
	}

	private function load_dependencies() {
		$dependency_map = array(
			'Marrison_Cookie_Scanner'        => MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-cookie-scanner.php',
			'Marrison_Cookie_Banner'         => MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-cookie-banner.php',
			'Marrison_Cookie_Admin_Settings' => MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-admin-settings.php',
			'Marrison_Cookie_Consent'        => MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-cookie-consent.php',
			'Marrison_Setup_Wizard'          => MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-setup-wizard.php',
		);

		foreach ( $dependency_map as $class_name => $file_path ) {
			if ( ! class_exists( $class_name, false ) ) {
				require_once $file_path;
			}
		}
	}

	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'marrison_addon/module_status_changed', array( $this, 'handle_module_status_change' ), 10, 2 );
	}

	private function ensure_installation() {
		$installed_version = get_option( 'marrison_cookie_module_version' );
		$is_first_install = empty( $installed_version );

		if ( MARRISON_COOKIE_VERSION === $installed_version ) {
			return;
		}

		$this->create_default_options();
		$this->create_tables();
		update_option( 'marrison_cookie_module_version', MARRISON_COOKIE_VERSION );

		if ( $is_first_install ) {
			set_transient( 'marrison_cookie_just_activated', true, 30 );
		}
	}

	private function init_components() {
		Marrison_Cookie_Scanner::get_instance();
		Marrison_Cookie_Banner::get_instance();
		Marrison_Cookie_Admin_Settings::get_instance();
		Marrison_Cookie_Consent::get_instance();
		Marrison_Setup_Wizard::get_instance();
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'marrison-cookie', false, dirname( MARRISON_COOKIE_PLUGIN_BASENAME ) . '/languages' );
	}

	public function enqueue_admin_assets( $hook ) {
		if ( ! $this->is_cookie_manager_admin_page() ) {
			return;
		}

		wp_enqueue_style(
			'marrison-cookie-admin',
			MARRISON_COOKIE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			MARRISON_COOKIE_VERSION
		);

		wp_enqueue_script(
			'marrison-cookie-admin',
			MARRISON_COOKIE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			MARRISON_COOKIE_VERSION,
			true
		);

		wp_localize_script(
			'marrison-cookie-admin',
			'marrisonCookieAdmin',
			array(
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'marrison_cookie_nonce' ),
				'confirmDeleteText'   => function_exists( 'marrison_cookie_site_text' ) ? marrison_cookie_site_text( 'Sei sicuro di voler eliminare questo cookie?', 'Are you sure you want to delete this cookie?' ) : 'Sei sicuro di voler eliminare questo cookie?',
				'scanningText'        => function_exists( 'marrison_cookie_site_text' ) ? marrison_cookie_site_text( 'Scansione in corso...', 'Scanning...' ) : 'Scansione in corso...',
				'scanSuccessText'     => function_exists( 'marrison_cookie_site_text' ) ? marrison_cookie_site_text( 'Scansione completata!', 'Scan completed!' ) : 'Scansione completata!',
				'connectionErrorText' => function_exists( 'marrison_cookie_site_text' ) ? marrison_cookie_site_text( 'Errore di connessione', 'Connection error' ) : 'Errore di connessione',
				'noCookiesFoundText'  => function_exists( 'marrison_cookie_site_text' ) ? marrison_cookie_site_text( 'Nessun cookie trovato', 'No cookies found' ) : 'Nessun cookie trovato',
				'errorText'           => function_exists( 'marrison_cookie_site_text' ) ? marrison_cookie_site_text( 'Errore: ', 'Error: ' ) : 'Errore: ',
				'categoryNecessary'   => function_exists( 'marrison_cookie_site_text' ) ? marrison_cookie_site_text( 'Necessari', 'Necessary' ) : 'Necessari',
				'categoryFunctional'  => function_exists( 'marrison_cookie_site_text' ) ? marrison_cookie_site_text( 'Funzionali', 'Functional' ) : 'Funzionali',
				'categoryAnalytics'   => function_exists( 'marrison_cookie_site_text' ) ? marrison_cookie_site_text( 'Analitici', 'Analytics' ) : 'Analitici',
				'categoryMarketing'   => function_exists( 'marrison_cookie_site_text' ) ? marrison_cookie_site_text( 'Marketing', 'Marketing' ) : 'Marketing',
			)
		);
	}

	private function is_cookie_manager_admin_page() {
		if ( isset( $_GET['page'] ) && 'marrison-cookie' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return true;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( $screen && false !== strpos( (string) $screen->id, 'marrison-cookie' ) ) {
				return true;
			}
		}

		return false;
	}

	public function handle_module_status_change( $module_id, $enabled ) {
		if ( 'cookie_manager' !== $module_id || $enabled ) {
			return;
		}

		$timestamp = wp_next_scheduled( 'marrison_cookie_daily_scan' );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'marrison_cookie_daily_scan' );
			$timestamp = wp_next_scheduled( 'marrison_cookie_daily_scan' );
		}
	}

	private function create_default_options() {
		$default_options = array(
			'banner_title'             => marrison_cookie_site_text( 'Gestione Cookie', 'Cookie Management' ),
			'banner_description'       => marrison_cookie_site_text( 'Utilizziamo i cookie per migliorare la tua esperienza. Per maggiori informazioni, leggi la nostra privacy policy.', 'We use cookies to improve your experience. For more information, read our privacy policy.' ),
			'accept_button_text'       => marrison_cookie_site_text( 'Accetta tutti', 'Accept all' ),
			'reject_button_text'       => marrison_cookie_site_text( 'Rifiuta tutti', 'Reject all' ),
			'customize_button_text'    => marrison_cookie_site_text( 'Personalizza', 'Customize' ),
			'privacy_policy_url'       => '',
			'cookie_policy_url'        => '',
			'banner_layout'            => 'bar',
			'banner_position'          => 'bottom',
			'box_position'             => 'bottom-right',
			'banner_background_color'  => '#ffffff',
			'banner_text_color'        => '#333333',
			'button_background_color'  => '#0073aa',
			'button_text_color'        => '#ffffff',
			'consent_duration'         => 30,
			'show_banner'              => true,
			'auto_scan'                => true,
			'scan_interval'            => 7,
		);

		foreach ( $default_options as $key => $value ) {
			if ( false === get_option( 'marrison_cookie_' . $key ) ) {
				add_option( 'marrison_cookie_' . $key, $value );
			}
		}
	}

	private function create_tables() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'marrison_cookies';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			cookie_name varchar(255) NOT NULL,
			cookie_domain varchar(255) DEFAULT '',
			cookie_path varchar(255) DEFAULT '/',
			cookie_expiration datetime DEFAULT NULL,
			cookie_category varchar(50) DEFAULT 'functional',
			cookie_description text DEFAULT '',
			source varchar(255) DEFAULT '',
			scan_date datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY cookie_name (cookie_name),
			KEY cookie_category (cookie_category)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
