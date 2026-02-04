<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Preloader {

	public function __construct() {
		// Admin
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		// Frontend
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_body_open', [ $this, 'render_preloader' ] );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'marrison_addon_panel',
			esc_html__( 'Preloader', 'marrison-addon' ),
			esc_html__( 'Preloader', 'marrison-addon' ),
			'manage_options',
			'marrison_addon_preloader',
			[ $this, 'render_admin_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'marrison_addon_preloader_group', 'marrison_addon_preloader_settings' );
	}

	public function enqueue_admin_scripts( $hook ) {
		// Only load on our settings page
		if ( ! isset( $_GET['page'] ) || 'marrison_addon_preloader' !== $_GET['page'] ) {
			return;
		}
		
		wp_enqueue_media(); // Core media uploader
		wp_enqueue_style( 'wp-color-picker' );
		
		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		wp_enqueue_script( 'marrison-admin-preloader', plugins_url( 'assets/js/admin-preloader.js', $plugin_root_file ), [ 'jquery', 'wp-color-picker' ], Marrison_Addon::VERSION, true );
	}

	public function enqueue_scripts() {
		if ( ! $this->should_display_preloader() ) {
			return;
		}

		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		wp_enqueue_style( 'marrison-preloader', plugins_url( 'assets/css/marrison-preloader.css', $plugin_root_file ), [], Marrison_Addon::VERSION );
		wp_enqueue_script( 'marrison-preloader', plugins_url( 'assets/js/marrison-preloader.js', $plugin_root_file ), [ 'jquery' ], Marrison_Addon::VERSION, true );
		
		$settings = get_option( 'marrison_addon_preloader_settings', [] );
		wp_localize_script( 'marrison-preloader', 'marrison_preloader_settings', [
			'transition_duration' => isset( $settings['transition_duration'] ) ? $settings['transition_duration'] : '500',
		] );
	}

	public function render_preloader() {
		if ( ! $this->should_display_preloader() ) {
			return;
		}

		$settings = get_option( 'marrison_addon_preloader_settings', [] );
		
		$bg_color = isset( $settings['bg_color'] ) ? $settings['bg_color'] : '#ffffff';
		$logo_url = isset( $settings['logo_url'] ) ? $settings['logo_url'] : '';
		$logo_width = isset( $settings['logo_width'] ) ? $settings['logo_width'] : '150';
		$spinner_type = isset( $settings['spinner_type'] ) ? $settings['spinner_type'] : 'circle';
		$spinner_color = isset( $settings['spinner_color'] ) ? $settings['spinner_color'] : '#000000';
		$animation_type = isset( $settings['animation_type'] ) ? $settings['animation_type'] : 'fade';
		$show_progress = isset( $settings['show_progress'] ) ? $settings['show_progress'] : false;
		$progress_color = isset( $settings['progress_color'] ) ? $settings['progress_color'] : '#000000';
		
		// Inline styles for critical CSS
		echo '<style>
			#marrison-preloader {
				background-color: ' . esc_attr( $bg_color ) . ';
			}
			#marrison-preloader .marrison-preloader-logo {
				width: ' . esc_attr( $logo_width ) . 'px;
			}
			#marrison-preloader .marrison-spinner-circle {
				border-top-color: ' . esc_attr( $spinner_color ) . ';
			}
			#marrison-preloader .marrison-spinner-dots div {
				background-color: ' . esc_attr( $spinner_color ) . ';
			}
			#marrison-preloader .marrison-spinner-double-ring {
				border-color: ' . esc_attr( $spinner_color ) . ' transparent ' . esc_attr( $spinner_color ) . ' transparent;
			}
			#marrison-preloader .marrison-spinner-wave div {
				background-color: ' . esc_attr( $spinner_color ) . ';
			}
			.marrison-preloader-progress-bar {
				background-color: ' . esc_attr( $progress_color ) . ';
			}
			.marrison-preloader-percentage {
				color: ' . esc_attr( $progress_color ) . ';
			}
		</style>';
		?>
		<div id="marrison-preloader" class="marrison-anim-<?php echo esc_attr( $animation_type ); ?>">
			<?php if ( 'split' === $animation_type ) : ?>
				<div class="marrison-preloader-curtain-top" style="background-color: <?php echo esc_attr( $bg_color ); ?>"></div>
				<div class="marrison-preloader-curtain-bottom" style="background-color: <?php echo esc_attr( $bg_color ); ?>"></div>
			<?php elseif ( 'shutter-vert' === $animation_type ) : ?>
				<div class="marrison-preloader-shutter">
					<div class="marrison-preloader-shutter-item" style="background-color: <?php echo esc_attr( $bg_color ); ?>"></div>
					<div class="marrison-preloader-shutter-item" style="background-color: <?php echo esc_attr( $bg_color ); ?>"></div>
					<div class="marrison-preloader-shutter-item" style="background-color: <?php echo esc_attr( $bg_color ); ?>"></div>
					<div class="marrison-preloader-shutter-item" style="background-color: <?php echo esc_attr( $bg_color ); ?>"></div>
				</div>
			<?php endif; ?>
			
			<div class="marrison-preloader-content">
				<?php if ( ! empty( $logo_url ) ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" class="marrison-preloader-logo <?php echo ( 'pulse' === $spinner_type ) ? 'marrison-pulse' : ''; ?>" alt="Loading...">
				<?php endif; ?>
				
				<?php if ( 'circle' === $spinner_type ) : ?>
					<div class="marrison-spinner-circle"></div>
				<?php elseif ( 'dots' === $spinner_type ) : ?>
					<div class="marrison-spinner-dots"><div></div><div></div><div></div></div>
				<?php elseif ( 'double-ring' === $spinner_type ) : ?>
					<div class="marrison-spinner-double-ring"></div>
				<?php elseif ( 'wave' === $spinner_type ) : ?>
					<div class="marrison-spinner-wave"><div></div><div></div><div></div><div></div><div></div></div>
				<?php endif; ?>

				<?php if ( $show_progress ) : ?>
					<div class="marrison-preloader-progress-container">
						<div class="marrison-preloader-progress-bar"></div>
						<span class="marrison-preloader-percentage">0%</span>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function should_display_preloader() {
		// 1. Check if Elementor Editor is active (Edit Mode or Preview Mode)
		if ( class_exists( '\Elementor\Plugin' ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || 
				 \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
				return false;
			}
		}

		// 2. Check for Elementor GET parameters (Preview/Editor)
		if ( isset( $_GET['elementor-preview'] ) || ( isset( $_GET['action'] ) && 'elementor' === $_GET['action'] ) ) {
			return false;
		}

		// 3. Restrict to Front Page ONLY (User Request: "SOLO frontpage")
		if ( ! is_front_page() ) {
			return false;
		}

		return true;
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option( 'marrison_addon_preloader_settings', [] );
		$bg_color = isset( $settings['bg_color'] ) ? $settings['bg_color'] : '#ffffff';
		$logo_url = isset( $settings['logo_url'] ) ? $settings['logo_url'] : '';
		$logo_width = isset( $settings['logo_width'] ) ? $settings['logo_width'] : '150';
		$spinner_type = isset( $settings['spinner_type'] ) ? $settings['spinner_type'] : 'circle';
		$spinner_color = isset( $settings['spinner_color'] ) ? $settings['spinner_color'] : '#000000';
		$transition_duration = isset( $settings['transition_duration'] ) ? $settings['transition_duration'] : '500';
		$animation_type = isset( $settings['animation_type'] ) ? $settings['animation_type'] : 'fade';
		$show_progress = isset( $settings['show_progress'] ) ? $settings['show_progress'] : false;
		$progress_color = isset( $settings['progress_color'] ) ? $settings['progress_color'] : '#000000';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Impostazioni Preloader', 'marrison-addon' ); ?></h1>
			<p><?php echo esc_html__( 'Configura la schermata di caricamento del sito.', 'marrison-addon' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'marrison_addon_preloader_group' ); ?>
				<?php do_settings_sections( 'marrison_addon_preloader_group' ); ?>

				<div class="marrison-module-card" style="max-width: 600px; margin-top: 20px;">
					<div class="marrison-card-header">
						<h2 class="marrison-card-title"><?php echo esc_html__( 'Aspetto', 'marrison-addon' ); ?></h2>
					</div>
					
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Colore di Sfondo', 'marrison-addon' ); ?></th>
							<td>
								<input type="text" name="marrison_addon_preloader_settings[bg_color]" value="<?php echo esc_attr( $bg_color ); ?>" class="marrison-color-field" data-default-color="#ffffff">
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Logo / Immagine', 'marrison-addon' ); ?></th>
							<td>
								<div class="marrison-media-uploader">
									<input type="text" name="marrison_addon_preloader_settings[logo_url]" value="<?php echo esc_attr( $logo_url ); ?>" class="regular-text marrison-media-url">
									<button type="button" class="button marrison-media-upload-btn"><?php echo esc_html__( 'Carica Immagine', 'marrison-addon' ); ?></button>
									<?php if ( ! empty( $logo_url ) ) : ?>
										<div class="marrison-media-preview" style="margin-top: 10px; max-width: 200px;">
											<img src="<?php echo esc_url( $logo_url ); ?>" style="max-width: 100%; height: auto;">
										</div>
									<?php endif; ?>
								</div>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Larghezza Logo (px)', 'marrison-addon' ); ?></th>
							<td>
								<input type="number" name="marrison_addon_preloader_settings[logo_width]" value="<?php echo esc_attr( $logo_width ); ?>" class="small-text"> px
							</td>
						</tr>
					</table>
				</div>

				<div class="marrison-module-card" style="max-width: 600px; margin-top: 20px;">
					<div class="marrison-card-header">
						<h2 class="marrison-card-title"><?php echo esc_html__( 'Spinner & Animazione', 'marrison-addon' ); ?></h2>
					</div>
					
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Animazione Uscita', 'marrison-addon' ); ?></th>
							<td>
								<select name="marrison_addon_preloader_settings[animation_type]">
									<option value="fade" <?php selected( $animation_type, 'fade' ); ?>><?php echo esc_html__( 'Dissolvenza (Default)', 'marrison-addon' ); ?></option>
									<option value="slide-up" <?php selected( $animation_type, 'slide-up' ); ?>><?php echo esc_html__( 'Scorrimento Su', 'marrison-addon' ); ?></option>
									<option value="slide-left" <?php selected( $animation_type, 'slide-left' ); ?>><?php echo esc_html__( 'Scorrimento Sinistra', 'marrison-addon' ); ?></option>
									<option value="split" <?php selected( $animation_type, 'split' ); ?>><?php echo esc_html__( 'Sipario (Split)', 'marrison-addon' ); ?></option>
									<option value="shutter-vert" <?php selected( $animation_type, 'shutter-vert' ); ?>><?php echo esc_html__( 'Tapparelle (Verticale)', 'marrison-addon' ); ?></option>
								</select>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Tipo Spinner', 'marrison-addon' ); ?></th>
							<td>
								<select name="marrison_addon_preloader_settings[spinner_type]">
									<option value="none" <?php selected( $spinner_type, 'none' ); ?>><?php echo esc_html__( 'Nessuno', 'marrison-addon' ); ?></option>
									<option value="circle" <?php selected( $spinner_type, 'circle' ); ?>><?php echo esc_html__( 'Cerchio Rotante', 'marrison-addon' ); ?></option>
									<option value="double-ring" <?php selected( $spinner_type, 'double-ring' ); ?>><?php echo esc_html__( 'Doppio Anello', 'marrison-addon' ); ?></option>
									<option value="dots" <?php selected( $spinner_type, 'dots' ); ?>><?php echo esc_html__( 'Puntini Pulsanti', 'marrison-addon' ); ?></option>
									<option value="wave" <?php selected( $spinner_type, 'wave' ); ?>><?php echo esc_html__( 'Onda', 'marrison-addon' ); ?></option>
									<option value="pulse" <?php selected( $spinner_type, 'pulse' ); ?>><?php echo esc_html__( 'Logo Pulsante', 'marrison-addon' ); ?></option>
								</select>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Colore Spinner', 'marrison-addon' ); ?></th>
							<td>
								<input type="text" name="marrison_addon_preloader_settings[spinner_color]" value="<?php echo esc_attr( $spinner_color ); ?>" class="marrison-color-field" data-default-color="#000000">
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Mostra Barra Progresso', 'marrison-addon' ); ?></th>
							<td>
								<label class="marrison-switch">
									<input type="checkbox" name="marrison_addon_preloader_settings[show_progress]" value="1" <?php checked( $show_progress, true ); ?>>
									<span class="marrison-slider"></span>
								</label>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Colore Progresso', 'marrison-addon' ); ?></th>
							<td>
								<input type="text" name="marrison_addon_preloader_settings[progress_color]" value="<?php echo esc_attr( $progress_color ); ?>" class="marrison-color-field" data-default-color="#000000">
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Durata Transizione (ms)', 'marrison-addon' ); ?></th>
							<td>
								<input type="number" name="marrison_addon_preloader_settings[transition_duration]" value="<?php echo esc_attr( $transition_duration ); ?>" class="small-text" step="100"> ms
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
