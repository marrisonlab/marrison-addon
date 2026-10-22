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
		add_action( 'admin_head', [ $this, 'add_menu_badge_styles' ] );
	}

	public function enqueue_styles( $hook ) {
		if ( ! $this->is_marrison_admin_page() ) {
			return;
		}
		$plugin_root_file = Marrison_Addon::plugin_file();
		wp_enqueue_style( 'marrison-admin-css', plugins_url( 'assets/css/admin.css', $plugin_root_file ), [], Marrison_Addon::asset_version( 'assets/css/admin.css' ) );
	}

	public function enqueue_scripts( $hook ) {
		if ( ! $this->is_marrison_admin_page() ) {
			return;
		}
		$plugin_root_file = Marrison_Addon::plugin_file();
		wp_enqueue_script( 'marrison-admin-global', plugins_url( 'assets/js/admin-global.js', $plugin_root_file ), [ 'jquery' ], Marrison_Addon::asset_version( 'assets/js/admin-global.js' ), true );
		
		wp_localize_script( 'marrison-admin-global', 'marrison_global', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'marrison_save_option_nonce' ),
			'error_saving' => __( 'Errore durante il salvataggio delle impostazioni', 'marrison-addon' ),
			'connection_error' => __( 'Errore di connessione', 'marrison-addon' ),
		] );
	}

	private function is_marrison_admin_page() {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) );

		if ( 'marrison_addon_panel' === $page || 0 === strpos( $page, 'marrison_addon_' ) ) {
			return true;
		}

		return in_array(
			$page,
			[
				'marrison-cookie',
				'marrison-video-thumb',
			],
			true
		);
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

		$allowed_options = apply_filters(
			'marrison_addon/ajax_save_allowed_options',
			[
				'marrison_addon_modules',
				'marrison_addon_disabled_sizes',
			]
		);

		if ( ! in_array( $option_name, $allowed_options, true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid option name' ] );
		}

		// Handle array updates if key is provided
		$key = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : null;
		if ( 'marrison_addon_modules' === $option_name && null === $key && ! is_array( $value ) ) {
			wp_send_json_error( [ 'message' => __( 'Impostazioni dei moduli non valide.', 'marrison-addon' ) ] );
		}
		if ( 'marrison_addon_modules' === $option_name && ! current_user_can( 'unfiltered_html' ) ) {
			$old_modules = get_option( 'marrison_addon_modules', [] );
			if ( 'php_snippets' === $key || ( null === $key && ( ! empty( $value['php_snippets'] ) !== ! empty( $old_modules['php_snippets'] ) ) ) ) {
				wp_send_json_error( [ 'message' => __( 'PHP Snippets richiede anche la capability unfiltered_html.', 'marrison-addon' ) ] );
			}
		}
		
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
			
			$expected = $current_value;
		} else {
			$expected = $value;
		}
		if ( 'marrison_addon_modules' === $option_name ) {
			$expected = marrison_addon_protect_php_snippets_toggle( $expected );
		}
		update_option( $option_name, $expected );
		if ( 'marrison_addon_modules' === $option_name && is_wp_error( Marrison_Addon::get_module_update_error() ) ) {
			wp_send_json_error( [ 'message' => Marrison_Addon::get_module_update_error()->get_error_message() ] );
		}
		if ( get_option( $option_name ) !== $expected ) {
			wp_send_json_error( [ 'message' => __( 'Impostazioni non salvate. Verifica il database e riprova.', 'marrison-addon' ) ] );
		}

		wp_send_json_success( [ 'message' => 'Settings saved' ] );
	}

	public function add_admin_menu() {
		// Main Menu - Dashboard
		add_menu_page(
			esc_html__( 'Marrison Addon', 'marrison-addon' ),
			esc_html__( 'AM Addon', 'marrison-addon' ),
			'manage_options',
			'marrison_addon_panel',
			[ $this, 'dashboard_page_html' ],
			'dashicons-admin-generic',
			31
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
		$is_elementor_active = did_action( 'elementor/loaded' );
		$is_woocommerce_active = Marrison_Addon::is_woocommerce_active();
		$is_jet_engine_active = Marrison_Addon::is_jet_engine_active();

		$available_modules = Marrison_Addon::get_module_definitions();
		uasort(
			$available_modules,
			static function ( $a, $b ) {
				$title_a = isset( $a['title'] ) ? remove_accents( wp_strip_all_tags( (string) $a['title'] ) ) : '';
				$title_b = isset( $b['title'] ) ? remove_accents( wp_strip_all_tags( (string) $b['title'] ) ) : '';

				return strnatcasecmp( $title_a, $title_b );
			}
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Marrison Addon', 'marrison-addon' ); ?></h1>
			<p><?php echo esc_html__( 'Benvenuto in Marrison Addon. Gestisci i tuoi moduli qui sotto.', 'marrison-addon' ); ?></p>
			
			<div class="marrison-modules-grid">
				<?php foreach ( $available_modules as $id => $module ) :
					$checked = isset( $modules[ $id ] ) && $modules[ $id ] ? 'checked' : '';
					$reload = isset( $module['reload'] ) && $module['reload'] ? 'true' : 'false';
					$icon = isset( $module['icon'] ) ? $module['icon'] : 'dashicons-admin-generic';
					
					$is_disabled = false;
					$disabled_attr = '';
					$card_style = '';
					$badge = '';

					$missing_requirements = [];
					$required_plugins = Marrison_Addon::module_required_plugins( $module );
					if ( 'php_snippets' === $id && ! current_user_can( 'unfiltered_html' ) ) {
						$missing_requirements[] = 'unfiltered_html';
					}

					if ( isset( $module['requires_elementor'] ) && $module['requires_elementor'] && ! $is_elementor_active ) {
						$missing_requirements[] = esc_html__( 'Elementor', 'marrison-addon' );
					}

					if ( isset( $module['requires_woocommerce'] ) && $module['requires_woocommerce'] && ! $is_woocommerce_active ) {
						$missing_requirements[] = esc_html__( 'WooCommerce', 'marrison-addon' );
					}

					if ( isset( $module['requires_jet_engine'] ) && $module['requires_jet_engine'] && ! $is_jet_engine_active ) {
						$missing_requirements[] = esc_html__( 'JetEngine', 'marrison-addon' );
					}

					if ( ! empty( $missing_requirements ) ) {
						$checked = '';
						$is_disabled = true;
						$disabled_attr = 'disabled';
						$card_style = '';
						$badge = 'php_snippets' === $id ? '<span class="marrison-badge-error">' . sprintf(
							/* translators: %s: required capability. */
							esc_html__( 'Disabilitato: richiede il permesso %s.', 'marrison-addon' ),
							esc_html( implode( ' + ', $missing_requirements ) )
						) . '</span>' : '';
					}
				?>
				<div class="marrison-module-card" data-module="<?php echo esc_attr( $id ); ?>" <?php echo $card_style; ?>>
					<div class="marrison-card-header">
						<div class="marrison-card-heading">
							<span class="dashicons <?php echo esc_attr( $icon ); ?> marrison-card-icon" aria-hidden="true"></span>
							<h3 class="marrison-card-title"><?php echo esc_html( $module['title'] ); ?><?php if ( ! empty( $module['beta'] ) ) : ?> <span class="marrison-module-beta"><?php echo esc_html__( 'Beta', 'marrison-addon' ); ?></span><?php endif; ?></h3>
						</div>
						<div class="marrison-module-actions">
						<?php if ( ! empty( $module['settings_page'] ) ) : ?>
							<?php if ( ! $is_disabled && $checked ) : ?>
							<a class="marrison-module-settings" href="<?php echo esc_url( admin_url( 'admin.php?page=' . sanitize_key( $module['settings_page'] ) ) ); ?>" title="<?php echo esc_attr__( 'Impostazioni', 'marrison-addon' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Impostazioni di %s', 'marrison-addon' ), $module['title'] ) ); ?>">
								<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
							</a>
							<?php else : ?>
							<span class="marrison-module-settings is-disabled" aria-disabled="true" title="<?php echo esc_attr__( 'Attiva il modulo per aprire le impostazioni', 'marrison-addon' ); ?>"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span></span>
							<?php endif; ?>
						<?php endif; ?>
						<label class="marrison-switch">
							<input type="checkbox" 
								   class="marrison-ajax-toggle" 
								   data-option="marrison_addon_modules" 
								   data-key="<?php echo esc_attr( $id ); ?>" 
								   data-reload="<?php echo esc_attr( $reload ); ?>"
								   aria-label="<?php echo esc_attr( sprintf( __( 'Abilita %s', 'marrison-addon' ), $module['title'] ) ); ?>"
								   <?php if ( $required_plugins ) : ?>aria-describedby="marrison-module-requirements-<?php echo esc_attr( $id ); ?>"<?php endif; ?>
								   <?php echo $checked; ?>
								   <?php echo $disabled_attr; ?>>
							<span class="marrison-slider"></span>
						</label>
						</div>
					</div>
					<p class="marrison-card-desc"><?php echo esc_html( $module['desc'] ); ?></p>
					<?php if ( $required_plugins ) : ?>
						<p class="marrison-module-requirements" id="marrison-module-requirements-<?php echo esc_attr( $id ); ?>">
							<?php foreach ( $required_plugins as $required_plugin ) :
								$dependency_active = [ 'Elementor' => $is_elementor_active, 'WooCommerce' => $is_woocommerce_active, 'JetEngine' => $is_jet_engine_active ][ $required_plugin ];
								$dependency_status = $dependency_active ? __( 'Attivo', 'marrison-addon' ) : __( 'Assente o non attivo', 'marrison-addon' );
							?>
								<span class="marrison-module-dependency <?php echo $dependency_active ? 'is-active' : 'is-missing'; ?>" title="<?php echo esc_attr( $dependency_status ); ?>">
									<span class="marrison-dependency-flag" aria-hidden="true"><?php echo $dependency_active ? '&#10003;' : '&#10005;'; ?></span>
									<?php echo esc_html( $required_plugin ); ?><span class="screen-reader-text">: <?php echo esc_html( $dependency_status ); ?></span>
								</span>
							<?php endforeach; ?>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $badge ) ) : ?>
						<div style="margin-top: 10px;"><?php echo $badge; ?></div>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>

		</div>
		<?php
	}
}
