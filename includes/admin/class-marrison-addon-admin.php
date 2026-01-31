<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	public function enqueue_styles( $hook ) {
		if ( 'toplevel_page_marrison_addon_panel' !== $hook ) {
			return;
		}
		// Point to the root plugin file to get the correct base URL
		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		wp_enqueue_style( 'marrison-admin-css', plugins_url( 'assets/css/admin.css', $plugin_root_file ), [], '1.0.0' );
	}

	public function add_admin_menu() {
		// Main Menu - Dashboard
		add_menu_page(
			esc_html__( 'Marrison Addon', 'marrison-addon' ),
			esc_html__( 'Marrison Addon', 'marrison-addon' ),
			'manage_options',
			'marrison_addon_panel',
			[ $this, 'dashboard_page_html' ],
			'dashicons-plus',
			30
		);
	}

	public function register_settings() {
		register_setting( 'marrison_addon_settings', 'marrison_addon_modules' );
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
				'desc' => esc_html__( 'Add links to any Elementor container.', 'marrison-addon' ),
			],
			[
				'id' => 'ticker',
				'title' => esc_html__( 'Ticker', 'marrison-addon' ),
				'desc' => esc_html__( 'News ticker widget with JetEngine support.', 'marrison-addon' ),
			],
			[
				'id' => 'image_sizes',
				'title' => esc_html__( 'Image Sizes', 'marrison-addon' ),
				'desc' => esc_html__( 'Register custom image sizes and add them to media selector.', 'marrison-addon' ),
			],
		];
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Marrison Addon', 'marrison-addon' ); ?></h1>
			<p><?php echo esc_html__( 'Welcome to Marrison Addon. Manage your modules below.', 'marrison-addon' ); ?></p>
			
			<form action="options.php" method="post">
				<?php
				settings_fields( 'marrison_addon_settings' );
				?>
				<div class="marrison-modules-grid">
					<?php foreach ( $available_modules as $module ) : 
						$id = $module['id'];
						$checked = isset( $modules[ $id ] ) && $modules[ $id ] ? 'checked' : '';
					?>
					<div class="marrison-module-card">
						<div class="marrison-card-header">
							<h3 class="marrison-card-title"><?php echo $module['title']; ?></h3>
							<label class="marrison-switch">
								<input type="checkbox" name="marrison_addon_modules[<?php echo esc_attr( $id ); ?>]" value="1" <?php echo $checked; ?>>
								<span class="marrison-slider"></span>
							</label>
						</div>
						<p class="marrison-card-desc"><?php echo $module['desc']; ?></p>
					</div>
					<?php endforeach; ?>
				</div>
				<?php
				submit_button( esc_html__( 'Save Settings', 'marrison-addon' ) );
				?>
			</form>
		</div>
		<?php
	}
}
