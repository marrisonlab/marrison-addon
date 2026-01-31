<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Image_Sizes {

	public function __construct() {
		// Hooks for functionality
		add_action( 'after_setup_theme', [ $this, 'register_image_sizes' ] );
		add_filter( 'image_size_names_choose', [ $this, 'add_to_media_selector' ] );

		// Hooks for Admin UI
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register custom image sizes.
	 */
	public function register_image_sizes() {
		$sizes = get_option( 'marrison_addon_image_sizes', [] );
		
		if ( ! empty( $sizes ) && is_array( $sizes ) ) {
			foreach ( $sizes as $size ) {
				if ( ! empty( $size['slug'] ) && ! empty( $size['width'] ) && ! empty( $size['height'] ) ) {
					$crop = isset( $size['crop'] ) ? (bool) $size['crop'] : false;
					add_image_size( $size['slug'], (int) $size['width'], (int) $size['height'], $crop );
				}
			}
		}
	}

	/**
	 * Add sizes to Media Selector.
	 */
	public function add_to_media_selector( $sizes ) {
		$custom_sizes = get_option( 'marrison_addon_image_sizes', [] );
		$new_sizes = [];

		if ( ! empty( $custom_sizes ) && is_array( $custom_sizes ) ) {
			foreach ( $custom_sizes as $size ) {
				if ( ! empty( $size['slug'] ) && ! empty( $size['name'] ) && ! empty( $size['show_in_media'] ) ) {
					$new_sizes[ $size['slug'] ] = $size['name'];
				}
			}
		}

		return array_merge( $sizes, $new_sizes );
	}

	/**
	 * Add Submenu page.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'marrison_addon_panel',
			esc_html__( 'Image Sizes', 'marrison-addon' ),
			esc_html__( 'Image Sizes', 'marrison-addon' ),
			'manage_options',
			'marrison_addon_image_sizes',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'marrison_addon_image_sizes_group', 'marrison_addon_image_sizes' );
	}

	/**
	 * Render Admin Page.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle Form Submission for adding/deleting
		if ( isset( $_POST['marrison_action'] ) && check_admin_referer( 'marrison_image_sizes_action', 'marrison_nonce' ) ) {
			$this->handle_form_submission();
		}

		$sizes = get_option( 'marrison_addon_image_sizes', [] );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Image Sizes Manager', 'marrison-addon' ); ?></h1>
			<p><?php echo esc_html__( 'Register custom image sizes for your theme.', 'marrison-addon' ); ?></p>

			<div style="display: flex; gap: 20px; align-items: flex-start;">
				
				<!-- Add New Form -->
				<div class="marrison-module-card" style="width: 300px;">
					<h2 style="margin-top: 0;"><?php echo esc_html__( 'Add New Size', 'marrison-addon' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( 'marrison_image_sizes_action', 'marrison_nonce' ); ?>
						<input type="hidden" name="marrison_action" value="add_size">
						
						<p>
							<label for="size_slug"><?php echo esc_html__( 'Slug (e.g., custom-thumb)', 'marrison-addon' ); ?></label><br>
							<input type="text" name="size_slug" id="size_slug" class="widefat" required pattern="[a-z0-9-_]+">
						</p>
						<p>
							<label for="size_name"><?php echo esc_html__( 'Display Name', 'marrison-addon' ); ?></label><br>
							<input type="text" name="size_name" id="size_name" class="widefat" required>
						</p>
						<div style="display: flex; gap: 10px;">
							<p style="flex: 1;">
								<label for="size_width"><?php echo esc_html__( 'Width (px)', 'marrison-addon' ); ?></label><br>
								<input type="number" name="size_width" id="size_width" class="widefat" required min="1">
							</p>
							<p style="flex: 1;">
								<label for="size_height"><?php echo esc_html__( 'Height (px)', 'marrison-addon' ); ?></label><br>
								<input type="number" name="size_height" id="size_height" class="widefat" required min="1">
							</p>
						</div>
						<p>
							<label>
								<input type="checkbox" name="size_crop" value="1" checked> 
								<?php echo esc_html__( 'Hard Crop', 'marrison-addon' ); ?>
							</label>
						</p>
						<p>
							<label>
								<input type="checkbox" name="size_show_in_media" value="1" checked> 
								<?php echo esc_html__( 'Show in Media Selector', 'marrison-addon' ); ?>
							</label>
						</p>
						<p>
							<input type="submit" class="button button-primary" value="<?php echo esc_attr__( 'Add Image Size', 'marrison-addon' ); ?>">
						</p>
					</form>
				</div>

				<!-- List Existing -->
				<div style="flex: 1;">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Slug', 'marrison-addon' ); ?></th>
								<th><?php echo esc_html__( 'Name', 'marrison-addon' ); ?></th>
								<th><?php echo esc_html__( 'Dimensions', 'marrison-addon' ); ?></th>
								<th><?php echo esc_html__( 'Crop', 'marrison-addon' ); ?></th>
								<th><?php echo esc_html__( 'In Media', 'marrison-addon' ); ?></th>
								<th><?php echo esc_html__( 'Actions', 'marrison-addon' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $sizes ) ) : ?>
								<tr>
									<td colspan="6"><?php echo esc_html__( 'No custom sizes registered yet.', 'marrison-addon' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $sizes as $index => $size ) : ?>
									<tr>
										<td><?php echo esc_html( $size['slug'] ); ?></td>
										<td><?php echo esc_html( $size['name'] ); ?></td>
										<td><?php echo esc_html( $size['width'] . ' x ' . $size['height'] ); ?> px</td>
										<td><?php echo $size['crop'] ? esc_html__( 'Yes', 'marrison-addon' ) : esc_html__( 'No', 'marrison-addon' ); ?></td>
										<td><?php echo isset( $size['show_in_media'] ) && $size['show_in_media'] ? esc_html__( 'Yes', 'marrison-addon' ) : esc_html__( 'No', 'marrison-addon' ); ?></td>
										<td>
											<form method="post" style="display:inline;">
												<?php wp_nonce_field( 'marrison_image_sizes_action', 'marrison_nonce' ); ?>
												<input type="hidden" name="marrison_action" value="delete_size">
												<input type="hidden" name="size_index" value="<?php echo esc_attr( $index ); ?>">
												<button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure?', 'marrison-addon' ) ); ?>');">
													<?php echo esc_html__( 'Delete', 'marrison-addon' ); ?>
												</button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	private function handle_form_submission() {
		$sizes = get_option( 'marrison_addon_image_sizes', [] );
		$action = $_POST['marrison_action'];

		if ( 'add_size' === $action ) {
			$new_size = [
				'slug' => sanitize_title( $_POST['size_slug'] ),
				'name' => sanitize_text_field( $_POST['size_name'] ),
				'width' => absint( $_POST['size_width'] ),
				'height' => absint( $_POST['size_height'] ),
				'crop' => isset( $_POST['size_crop'] ) ? true : false,
				'show_in_media' => isset( $_POST['size_show_in_media'] ) ? true : false,
			];

			// Simple validation
			if ( ! empty( $new_size['slug'] ) && ! empty( $new_size['width'] ) ) {
				$sizes[] = $new_size;
				update_option( 'marrison_addon_image_sizes', $sizes );
				add_settings_error( 'marrison_messages', 'marrison_size_added', __( 'Image size added.', 'marrison-addon' ), 'updated' );
			}
		} elseif ( 'delete_size' === $action ) {
			$index = absint( $_POST['size_index'] );
			if ( isset( $sizes[ $index ] ) ) {
				unset( $sizes[ $index ] );
				update_option( 'marrison_addon_image_sizes', array_values( $sizes ) ); // Re-index
				add_settings_error( 'marrison_messages', 'marrison_size_deleted', __( 'Image size deleted.', 'marrison-addon' ), 'updated' );
			}
		}
	}
}
