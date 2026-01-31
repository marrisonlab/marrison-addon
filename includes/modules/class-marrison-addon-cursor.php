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
		register_setting( 'marrison_addon_cursor_group', 'marrison_addon_cursor_settings' );
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
		$settings = get_option( 'marrison_addon_cursor_settings', [] );
		
		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		wp_enqueue_style( 'marrison-cursor', plugins_url( 'assets/css/marrison-cursor.css', $plugin_root_file ), [], Marrison_Addon::VERSION );
		wp_enqueue_script( 'marrison-cursor', plugins_url( 'assets/js/marrison-cursor.js', $plugin_root_file ), [ 'jquery' ], Marrison_Addon::VERSION, true );

		// Pass settings to JS
		wp_localize_script( 'marrison-cursor', 'marrison_cursor_settings', [
			'dot_color' => isset( $settings['dot_color'] ) ? $settings['dot_color'] : '#000000',
			'circle_color' => isset( $settings['circle_color'] ) ? $settings['circle_color'] : '#000000',
			'hover_color' => isset( $settings['hover_color'] ) ? $settings['hover_color'] : 'rgba(0,0,0,0.1)',
			'shape' => isset( $settings['shape'] ) ? $settings['shape'] : 'circle',
			'animation' => isset( $settings['animation'] ) ? $settings['animation'] : 'lag',
		] );
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option( 'marrison_addon_cursor_settings', [] );
		$dot_color = isset( $settings['dot_color'] ) ? $settings['dot_color'] : '#000000';
		$circle_color = isset( $settings['circle_color'] ) ? $settings['circle_color'] : '#000000';
		$hover_color = isset( $settings['hover_color'] ) ? $settings['hover_color'] : 'rgba(0,0,0,0.1)';
		$shape = isset( $settings['shape'] ) ? $settings['shape'] : 'circle';
		$animation = isset( $settings['animation'] ) ? $settings['animation'] : 'lag';

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
}
