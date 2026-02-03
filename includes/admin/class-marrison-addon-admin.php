<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_marrison_save_option', [ $this, 'ajax_save_option' ] );
		add_action( 'wp_ajax_marrison_force_update_check', [ $this, 'ajax_force_update_check' ] );
		add_action( 'admin_head', [ $this, 'add_menu_badge_styles' ] );
	}

	public function enqueue_styles( $hook ) {
		// Enqueue styles on all plugin pages
		if ( strpos( $hook, 'marrison_addon' ) === false ) {
			return;
		}
		// Point to the root plugin file to get the correct base URL
		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		wp_enqueue_style( 'marrison-admin-css', plugins_url( 'assets/css/admin.css', $plugin_root_file ), [], Marrison_Addon::VERSION );
	}

	public function enqueue_scripts( $hook ) {
		// Enqueue scripts on all plugin pages
		if ( strpos( $hook, 'marrison_addon' ) === false ) {
			return;
		}
		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		wp_enqueue_script( 'marrison-admin-global', plugins_url( 'assets/js/admin-global.js', $plugin_root_file ), [ 'jquery' ], Marrison_Addon::VERSION, true );
		
		wp_localize_script( 'marrison-admin-global', 'marrison_global', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'marrison_save_option_nonce' ),
			'error_saving' => __( 'Errore durante il salvataggio delle impostazioni', 'marrison-addon' ),
			'connection_error' => __( 'Errore di connessione', 'marrison-addon' ),
		] );
	}

	public function ajax_save_option() {
		check_ajax_referer( 'marrison_save_option_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$option_name = isset( $_POST['option_name'] ) ? sanitize_text_field( $_POST['option_name'] ) : '';
		$value       = isset( $_POST['value'] ) ? $_POST['value'] : null;

		if ( empty( $option_name ) ) {
			wp_send_json_error( [ 'message' => 'Missing option name' ] );
		}

		// Handle array updates if key is provided
		$key = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : null;
		
		if ( $key !== null ) {
			$current_value = get_option( $option_name, [] );
			if ( ! is_array( $current_value ) ) {
				$current_value = [];
			}
			
			// Boolean handling for checkboxes/switches
			if ( $value === 'true' || $value === '1' ) {
				$current_value[ $key ] = 1;
			} else {
				$current_value[ $key ] = 0; // Or unset if preferred, but 0 is safer for checkboxes
			}
			
			update_option( $option_name, $current_value );
		} else {
			update_option( $option_name, $value );
		}

		wp_send_json_success( [ 'message' => 'Settings saved' ] );
	}

	public function ajax_force_update_check() {
		check_ajax_referer( 'marrison_save_option_nonce', 'nonce' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		// Force check
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		
		// Get results
		$update_plugins = get_site_transient( 'update_plugins' );
		$plugin_slug = plugin_basename( dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php' );
		
		if ( isset( $update_plugins->response[ $plugin_slug ] ) ) {
			$update = $update_plugins->response[ $plugin_slug ];
			wp_send_json_success( [ 
				'message' => sprintf( __( 'Trovata nuova versione: %s', 'marrison-addon' ), $update->new_version ),
				'found' => true
			] );
		} else {
			wp_send_json_success( [ 
				'message' => __( 'Nessun aggiornamento trovato. Il plugin è aggiornato.', 'marrison-addon' ),
				'found' => false
			] );
		}
	}

	public function add_admin_menu() {
		// Main Menu - Dashboard
		add_menu_page(
			esc_html__( 'Marrison Addon', 'marrison-addon' ),
			esc_html__( 'Marrison Addon', 'marrison-addon' ),
			'manage_options',
			'marrison_addon_panel',
			[ $this, 'dashboard_page_html' ],
			'dashicons-admin-generic',
			61
		);
	}

	public function register_settings() {
		register_setting( 'marrison_addon_settings', 'marrison_addon_modules' );
	}

	public function add_menu_badge_styles() {
        $icon_url = plugin_dir_url(__FILE__) . '../../assets/icon.svg';
        ?>
        <style>
        #toplevel_page_marrison_addon_panel .wp-menu-image:before { display: none; }
        #toplevel_page_marrison_addon_panel .wp-menu-image {
            background-color: currentColor;
            -webkit-mask-image: url('<?php echo esc_url($icon_url); ?>');
            mask-image: url('<?php echo esc_url($icon_url); ?>');
            -webkit-mask-repeat: no-repeat;
            mask-repeat: no-repeat;
            -webkit-mask-position: center;
            mask-position: center;
            -webkit-mask-size: 20px;
            mask-size: 20px;
        }
        </style>
        <?php
    }

	public function dashboard_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$modules = get_option( 'marrison_addon_modules', [] );
		$available_modules = [
			[
				'id' => 'wrapped_link',
				'title' => esc_html__( 'Wrapped Link', 'marrison-addon' ),
				'desc' => esc_html__( 'Aggiungi link a qualsiasi contenitore Elementor.', 'marrison-addon' ),
				'reload' => false,
			],
			[
				'id' => 'ticker',
				'title' => esc_html__( 'Ticker', 'marrison-addon' ),
				'desc' => esc_html__( 'Widget ticker notizie con supporto JetEngine.', 'marrison-addon' ),
				'reload' => false,
			],
			[
				'id' => 'image_sizes',
				'title' => esc_html__( 'Dimensioni Immagini', 'marrison-addon' ),
				'desc' => esc_html__( 'Registra dimensioni immagine personalizzate e aggiungile al selettore media.', 'marrison-addon' ),
				'reload' => true,
			],
			[
				'id' => 'cursor',
				'title' => esc_html__( 'Cursore Animato', 'marrison-addon' ),
				'desc' => esc_html__( 'Sostituisce il cursore predefinito con un puntatore animato personalizzabile.', 'marrison-addon' ),
				'reload' => true,
			],
			[
				'id' => 'preloader',
				'title' => esc_html__( 'Preloader', 'marrison-addon' ),
				'desc' => esc_html__( 'Aggiungi una schermata di caricamento con logo personalizzato e spinner.', 'marrison-addon' ),
				'reload' => true,
			],
			[
				'id' => 'fast_logout',
				'title' => esc_html__( 'Fast Logout', 'marrison-addon' ),
				'desc' => esc_html__( 'Reindirizza automaticamente alla home page dopo il logout.', 'marrison-addon' ),
				'reload' => false,
			],
		];
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Marrison Addon', 'marrison-addon' ); ?></h1>
			<p><?php echo esc_html__( 'Benvenuto in Marrison Addon. Gestisci i tuoi moduli qui sotto.', 'marrison-addon' ); ?></p>
			
			<div class="marrison-modules-grid">
				<?php foreach ( $available_modules as $module ) : 
					$id = $module['id'];
					$checked = isset( $modules[ $id ] ) && $modules[ $id ] ? 'checked' : '';
					$reload = isset( $module['reload'] ) && $module['reload'] ? 'true' : 'false';
				?>
				<div class="marrison-module-card">
					<div class="marrison-card-header">
						<h3 class="marrison-card-title"><?php echo $module['title']; ?></h3>
						<label class="marrison-switch">
							<input type="checkbox" 
								   class="marrison-ajax-toggle" 
								   data-option="marrison_addon_modules" 
								   data-key="<?php echo esc_attr( $id ); ?>" 
								   data-reload="<?php echo esc_attr( $reload ); ?>"
								   <?php echo $checked; ?>>
							<span class="marrison-slider"></span>
						</label>
					</div>
					<p class="marrison-card-desc"><?php echo $module['desc']; ?></p>
				</div>
				<?php endforeach; ?>
			</div>

			<div class="marrison-update-section">
				<div class="marrison-update-info">
					<h2><?php esc_html_e( 'Aggiornamenti', 'marrison-addon' ); ?></h2>
					<p><?php esc_html_e( 'Cerca manualmente nuove versioni del plugin su GitHub.', 'marrison-addon' ); ?></p>
				</div>
				<div class="marrison-update-actions">
					<span class="marrison-update-status"></span>
					<button type="button" class="button button-primary marrison-force-update">
						<?php esc_html_e( 'Cerca Aggiornamenti', 'marrison-addon' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}
}
