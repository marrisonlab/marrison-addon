<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Cursor {

	public function __construct() {
		// Admin
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		// Frontend
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'marrison_addon_panel',
			esc_html__( 'Cursore Animato', 'marrison-addon' ),
			esc_html__( 'Cursore Animato', 'marrison-addon' ),
			'manage_options',
			'marrison_addon_cursor',
			[ $this, 'render_admin_page' ]
		);
	}

	public function register_settings() {
		register_setting(
			'marrison_addon_cursor_group',
			'marrison_addon_cursor_settings',
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default' => $this->get_default_settings(),
			]
		);
	}

	public function enqueue_admin_scripts( $hook ) {
		// Only load on our settings page
		if ( ! isset( $_GET['page'] ) || 'marrison_addon_cursor' !== $_GET['page'] ) {
			return;
		}
		
		// Load wp-color-picker
		wp_enqueue_style( 'wp-color-picker' );
		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		wp_enqueue_script( 'marrison-admin-cursor', plugins_url( 'assets/js/admin-cursor.js', $plugin_root_file ), [ 'wp-color-picker' ], Marrison_Addon::VERSION, true );
	}

	public function enqueue_scripts() {
		if ( ! $this->should_load_cursor() ) {
			return;
		}

		$settings = wp_parse_args( get_option( 'marrison_addon_cursor_settings', [] ), $this->get_default_settings() );
		
		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		$cursor_css_path = plugin_dir_path( $plugin_root_file ) . 'assets/css/marrison-cursor.css';
		$cursor_js_path = plugin_dir_path( $plugin_root_file ) . 'assets/js/marrison-cursor.js';
		$asset_version = Marrison_Addon::VERSION;

		if ( file_exists( $cursor_css_path ) && file_exists( $cursor_js_path ) ) {
			$asset_version .= '.' . max( filemtime( $cursor_css_path ), filemtime( $cursor_js_path ) );
		}

		wp_enqueue_style( 'marrison-cursor', plugins_url( 'assets/css/marrison-cursor.css', $plugin_root_file ), [], $asset_version );
		wp_enqueue_script( 'marrison-cursor', plugins_url( 'assets/js/marrison-cursor.js', $plugin_root_file ), [], $asset_version, true );

		// Pass settings to JS
		wp_localize_script( 'marrison-cursor', 'marrison_cursor_settings', [
			'dot_color' => $settings['dot_color'],
			'circle_color' => $settings['circle_color'],
			'hover_color' => $settings['hover_color'],
			'shape' => $settings['shape'],
			'animation' => $settings['animation'],
		] );
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = wp_parse_args( get_option( 'marrison_addon_cursor_settings', [] ), $this->get_default_settings() );
		$dot_color = $settings['dot_color'];
		$circle_color = $settings['circle_color'];
		$hover_color = $settings['hover_color'];
		$shape = $settings['shape'];
		$animation = $settings['animation'];

		// Save logic is handled by WordPress options.php if we use form action="options.php"
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Impostazioni Cursore Animato', 'marrison-addon' ); ?></h1>
			<p><?php echo esc_html__( 'Personalizza l\'aspetto del puntatore animato sul tuo sito.', 'marrison-addon' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'marrison_addon_cursor_group' ); ?>
				<?php do_settings_sections( 'marrison_addon_cursor_group' ); ?>

				<div class="marrison-module-card" style="max-width: 600px; margin-top: 20px;">
					<div class="marrison-card-header">
						<h2 class="marrison-card-title"><?php echo esc_html__( 'Stile e Animazione', 'marrison-addon' ); ?></h2>
					</div>
					
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Forma Cursore', 'marrison-addon' ); ?></th>
							<td>
								<select name="marrison_addon_cursor_settings[shape]">
									<option value="circle" <?php selected( $shape, 'circle' ); ?>><?php echo esc_html__( 'Cerchio (Default)', 'marrison-addon' ); ?></option>
									<option value="square" <?php selected( $shape, 'square' ); ?>><?php echo esc_html__( 'Quadrato', 'marrison-addon' ); ?></option>
									<option value="diamond" <?php selected( $shape, 'diamond' ); ?>><?php echo esc_html__( 'Rombo', 'marrison-addon' ); ?></option>
								</select>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Tipo Animazione', 'marrison-addon' ); ?></th>
							<td>
								<select name="marrison_addon_cursor_settings[animation]">
									<option value="lag" <?php selected( $animation, 'lag' ); ?>><?php echo esc_html__( 'Standard (Lag)', 'marrison-addon' ); ?></option>
									<option value="elastic" <?php selected( $animation, 'elastic' ); ?>><?php echo esc_html__( 'Elastico', 'marrison-addon' ); ?></option>
									<option value="fast" <?php selected( $animation, 'fast' ); ?>><?php echo esc_html__( 'Veloce', 'marrison-addon' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<div class="marrison-module-card" style="max-width: 600px; margin-top: 20px;">
					<div class="marrison-card-header">
						<h2 class="marrison-card-title"><?php echo esc_html__( 'Colori', 'marrison-addon' ); ?></h2>
					</div>
					
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Colore Punto (Dot)', 'marrison-addon' ); ?></th>
							<td>
								<input type="text" name="marrison_addon_cursor_settings[dot_color]" value="<?php echo esc_attr( $dot_color ); ?>" class="marrison-color-field" data-default-color="#000000">
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Colore Follower', 'marrison-addon' ); ?></th>
							<td>
								<input type="text" name="marrison_addon_cursor_settings[circle_color]" value="<?php echo esc_attr( $circle_color ); ?>" class="marrison-color-field" data-default-color="#000000">
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo esc_html__( 'Colore Hover (Follower)', 'marrison-addon' ); ?></th>
							<td>
								<input type="text" name="marrison_addon_cursor_settings[hover_color]" value="<?php echo esc_attr( $hover_color ); ?>" class="marrison-color-field" data-default-color="rgba(0,0,0,0.1)">
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function should_load_cursor() {
		return Marrison_Addon_Context::is_public_frontend_request();
	}

	public function sanitize_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : [];
		$defaults = $this->get_default_settings();
		$shapes = [ 'circle', 'square', 'diamond' ];
		$animations = [ 'lag', 'elastic', 'fast' ];
		$hover_color = isset( $settings['hover_color'] ) ? sanitize_text_field( wp_unslash( $settings['hover_color'] ) ) : $defaults['hover_color'];

		return [
			'dot_color' => sanitize_hex_color( $settings['dot_color'] ?? $defaults['dot_color'] ) ?: $defaults['dot_color'],
			'circle_color' => sanitize_hex_color( $settings['circle_color'] ?? $defaults['circle_color'] ) ?: $defaults['circle_color'],
			'hover_color' => preg_match( '/^rgba?\(([^)]+)\)$/', $hover_color ) ? $hover_color : $defaults['hover_color'],
			'shape' => in_array( $settings['shape'] ?? '', $shapes, true ) ? $settings['shape'] : $defaults['shape'],
			'animation' => in_array( $settings['animation'] ?? '', $animations, true ) ? $settings['animation'] : $defaults['animation'],
		];
	}

	private function get_default_settings() {
		return [
			'dot_color' => '#111111',
			'circle_color' => '#111111',
			'hover_color' => 'rgba(17,17,17,0.12)',
			'shape' => 'circle',
			'animation' => 'lag',
		];
	}
}
