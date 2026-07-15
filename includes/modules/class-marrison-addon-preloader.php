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
		register_setting(
			'marrison_addon_preloader_group',
			'marrison_addon_preloader_settings',
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default' => $this->get_default_settings(),
			]
		);
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
		wp_enqueue_script( 'marrison-preloader', plugins_url( 'assets/js/marrison-preloader.js', $plugin_root_file ), [], Marrison_Addon::VERSION, true );
		
		$settings = wp_parse_args( get_option( 'marrison_addon_preloader_settings', [] ), $this->get_default_settings() );
		wp_localize_script( 'marrison-preloader', 'marrison_preloader_settings', [
			'transition_duration' => $settings['transition_duration'],
		] );
	}

	public function render_preloader() {
		if ( ! $this->should_display_preloader() ) {
			return;
		}

		$settings = wp_parse_args( get_option( 'marrison_addon_preloader_settings', [] ), $this->get_default_settings() );
		$bg_color = $settings['bg_color'];
		$logo_url = $settings['logo_url'];
		$logo_width = $settings['logo_width'];
		$spinner_type = $settings['spinner_type'];
		$spinner_color = $settings['spinner_color'];
		$animation_type = $settings['animation_type'];
		$show_progress = ! empty( $settings['show_progress'] );
		$progress_color = $settings['progress_color'];
		$progress_bar_width = $settings['progress_bar_width'];
		$progress_bar_height = $settings['progress_bar_height'];
		$transition_duration = $settings['transition_duration'];
		$inline_vars = sprintf(
			'--marrison-preloader-bg:%1$s;--marrison-preloader-logo-width:%2$spx;--marrison-preloader-spinner-color:%3$s;--marrison-preloader-progress-color:%4$s;--marrison-preloader-progress-width:%5$spx;--marrison-preloader-progress-height:%6$spx;--marrison-preloader-transition-duration:%7$sms;',
			esc_attr( $bg_color ),
			(int) $logo_width,
			esc_attr( $spinner_color ),
			esc_attr( $progress_color ),
			(int) $progress_bar_width,
			(int) $progress_bar_height,
			(int) $transition_duration
		);
		?>
		<div id="marrison-preloader" class="marrison-anim-<?php echo esc_attr( $animation_type ); ?>" style="<?php echo esc_attr( $inline_vars ); ?>" aria-hidden="true">
			<?php if ( 'split' === $animation_type ) : ?>
				<div class="marrison-preloader-curtain-top"></div>
				<div class="marrison-preloader-curtain-bottom"></div>
			<?php elseif ( 'shutter-vert' === $animation_type ) : ?>
				<div class="marrison-preloader-shutter">
					<div class="marrison-preloader-shutter-item"></div>
					<div class="marrison-preloader-shutter-item"></div>
					<div class="marrison-preloader-shutter-item"></div>
					<div class="marrison-preloader-shutter-item"></div>
				</div>
			<?php endif; ?>
			
			<div class="marrison-preloader-content" role="status">
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
		return Marrison_Addon_Context::is_public_frontend_request();
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = wp_parse_args( get_option( 'marrison_addon_preloader_settings', [] ), $this->get_default_settings() );
		$bg_color = $settings['bg_color'];
		$logo_url = $settings['logo_url'];
		$logo_width = $settings['logo_width'];
		$spinner_type = $settings['spinner_type'];
		$spinner_color = $settings['spinner_color'];
		$transition_duration = $settings['transition_duration'];
		$animation_type = $settings['animation_type'];
		$show_progress = ! empty( $settings['show_progress'] );
		$progress_color = $settings['progress_color'];
		$progress_bar_width = $settings['progress_bar_width'];
		$progress_bar_height = $settings['progress_bar_height'];
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
							<th scope="row"><?php echo esc_html__( 'Larghezza Barra (px)', 'marrison-addon' ); ?></th>
							<td>
								<input type="number" name="marrison_addon_preloader_settings[progress_bar_width]" value="<?php echo esc_attr( $progress_bar_width ); ?>" class="small-text"> px
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Spessore Barra (px)', 'marrison-addon' ); ?></th>
							<td>
								<input type="number" name="marrison_addon_preloader_settings[progress_bar_height]" value="<?php echo esc_attr( $progress_bar_height ); ?>" class="small-text"> px
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

	public function sanitize_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : [];
		$defaults = $this->get_default_settings();
		$spinner_types = [ 'none', 'circle', 'double-ring', 'dots', 'wave', 'pulse' ];
		$animation_types = [ 'fade', 'slide-up', 'slide-left', 'split', 'shutter-vert' ];

		return [
			'bg_color' => sanitize_hex_color( $settings['bg_color'] ?? $defaults['bg_color'] ) ?: $defaults['bg_color'],
			'logo_url' => esc_url_raw( $settings['logo_url'] ?? $defaults['logo_url'] ),
			'logo_width' => max( 40, min( 600, absint( $settings['logo_width'] ?? $defaults['logo_width'] ) ) ),
			'spinner_type' => in_array( $settings['spinner_type'] ?? '', $spinner_types, true ) ? $settings['spinner_type'] : $defaults['spinner_type'],
			'spinner_color' => sanitize_hex_color( $settings['spinner_color'] ?? $defaults['spinner_color'] ) ?: $defaults['spinner_color'],
			'transition_duration' => max( 100, min( 5000, absint( $settings['transition_duration'] ?? $defaults['transition_duration'] ) ) ),
			'animation_type' => in_array( $settings['animation_type'] ?? '', $animation_types, true ) ? $settings['animation_type'] : $defaults['animation_type'],
			'show_progress' => ! empty( $settings['show_progress'] ),
			'progress_color' => sanitize_hex_color( $settings['progress_color'] ?? $defaults['progress_color'] ) ?: $defaults['progress_color'],
			'progress_bar_width' => max( 80, min( 600, absint( $settings['progress_bar_width'] ?? $defaults['progress_bar_width'] ) ) ),
			'progress_bar_height' => max( 1, min( 24, absint( $settings['progress_bar_height'] ?? $defaults['progress_bar_height'] ) ) ),
		];
	}

	private function get_default_settings() {
		return [
			'bg_color' => '#ffffff',
			'logo_url' => '',
			'logo_width' => 150,
			'spinner_type' => 'circle',
			'spinner_color' => '#111111',
			'transition_duration' => 500,
			'animation_type' => 'fade',
			'show_progress' => false,
			'progress_color' => '#111111',
			'progress_bar_width' => 200,
			'progress_bar_height' => 2,
		];
	}
}
