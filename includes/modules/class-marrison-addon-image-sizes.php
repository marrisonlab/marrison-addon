<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Image_Sizes {

	public function __construct() {
		// Hooks for functionality
		add_action( 'after_setup_theme', [ $this, 'register_image_sizes' ] );
		add_filter( 'image_size_names_choose', [ $this, 'add_to_media_selector' ] );
		add_filter( 'image_size_names_choose', [ $this, 'remove_disabled_from_media_selector' ], 20 );
		add_filter( 'intermediate_image_sizes_advanced', [ $this, 'filter_intermediate_image_sizes' ] );
		add_filter( 'intermediate_image_sizes', [ $this, 'filter_intermediate_image_sizes_list' ] );

		// Hooks for Admin UI
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		// AJAX Hooks
		add_action( 'wp_ajax_marrison_get_image_ids', [ $this, 'ajax_get_image_ids' ] );
		add_action( 'wp_ajax_marrison_regenerate_single_image', [ $this, 'ajax_regenerate_single_image' ] );
	}

	/**
	 * Enqueue Admin Scripts.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Check if we are on the correct page. 
		if ( ! isset( $_GET['page'] ) || 'marrison_addon_image_sizes' !== $_GET['page'] ) {
			return;
		}

		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';
		wp_enqueue_script( 'marrison-admin-image-sizes', plugins_url( 'assets/js/admin-image-sizes.js', $plugin_root_file ), [ 'jquery' ], Marrison_Addon::VERSION, true );

		wp_localize_script( 'marrison-admin-image-sizes', 'marrison_vars', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'marrison_regenerate_nonce' ),
			'confirm_message' => __( 'Sei sicuro di voler rigenerare tutte le miniature? Questa operazione potrebbe richiedere del tempo.', 'marrison-addon' ),
			'no_images' => __( 'Nessuna immagine trovata da rigenerare.', 'marrison-addon' ),
			'found_images' => __( 'Trovate %d immagini. Avvio rigenerazione...', 'marrison-addon' ),
			'done_message' => __( 'Finito!', 'marrison-addon' ),
			'process_stopped' => __( 'Processo interrotto dall\'utente.', 'marrison-addon' ),
		] );
		
		// Add some basic CSS for the progress bar
		wp_add_inline_style( 'wp-admin', '
			#marrison-progress-bar {
				width: 100%;
				background-color: #f0f0f1;
				border: 1px solid #c3c4c7;
				height: 25px;
				margin: 10px 0;
				position: relative;
				display: none;
			}
			#marrison-progress-fill {
				width: 0%;
				height: 100%;
				background-color: #2271b1;
				transition: width 0.2s;
			}
			#marrison-progress-text {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				text-align: center;
				line-height: 25px;
				color: #000;
				font-weight: bold;
				mix-blend-mode: difference;
				color: white;
			}
			#marrison-log-container {
				max-height: 300px;
				overflow-y: auto;
				background: #fff;
				border: 1px solid #c3c4c7;
				padding: 10px;
				margin-top: 10px;
				display: none;
			}
			#marrison-log-list {
				list-style: none;
				margin: 0;
				padding: 0;
			}
			#marrison-log-list li {
				border-bottom: 1px solid #f0f0f1;
				padding: 5px 0;
			}
			#marrison-log-list li.success { color: green; }
			#marrison-log-list li.error { color: red; }
		' );
	}

	/**
	 * AJAX: Get all image IDs.
	 */
	public function ajax_get_image_ids() {
		check_ajax_referer( 'marrison_regenerate_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$query_images = new WP_Query( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		wp_send_json_success( [ 'ids' => $query_images->posts ] );
	}

	/**
	 * AJAX: Regenerate single image.
	 */
	public function ajax_regenerate_single_image() {
		check_ajax_referer( 'marrison_regenerate_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( [ 'message' => 'Invalid ID' ] );
		}

		$fullsizepath = get_attached_file( $id );

		if ( false === $fullsizepath || ! file_exists( $fullsizepath ) ) {
			wp_send_json_error( [ 'message' => sprintf( __( 'File non trovato per ID %d', 'marrison-addon' ), $id ) ] );
		}

		// Optional: Cleanup disabled sizes
		$cleanup = isset( $_POST['cleanup'] ) && $_POST['cleanup'] === 'true';
		if ( $cleanup ) {
			$disabled_sizes = get_option( 'marrison_addon_disabled_sizes', [] );
			if ( ! empty( $disabled_sizes ) ) {
				$old_metadata = wp_get_attachment_metadata( $id );
				if ( ! empty( $old_metadata['sizes'] ) ) {
					$base_dir = dirname( $fullsizepath );
					
					foreach ( $old_metadata['sizes'] as $size_slug => $size_data ) {
						if ( isset( $disabled_sizes[ $size_slug ] ) && $disabled_sizes[ $size_slug ] ) {
							if ( isset( $size_data['file'] ) ) {
								$file_path = $base_dir . '/' . $size_data['file'];
								if ( file_exists( $file_path ) ) {
									@unlink( $file_path );
								}
							}
						}
					}
				}
			}
		}

		// @see wp_generate_attachment_metadata() in wp-admin/includes/image.php
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$metadata = wp_generate_attachment_metadata( $id, $fullsizepath );

		if ( is_wp_error( $metadata ) ) {
			wp_send_json_error( [ 'message' => sprintf( __( 'Errore generazione metadata per ID %d: %s', 'marrison-addon' ), $id, $metadata->get_error_message() ) ] );
		}

		if ( empty( $metadata ) ) {
			wp_send_json_error( [ 'message' => sprintf( __( 'Errore sconosciuto generazione metadata per ID %d', 'marrison-addon' ), $id ) ] );
		}

		// Update metadata in DB
		wp_update_attachment_metadata( $id, $metadata );

		$filename = basename( $fullsizepath );
		wp_send_json_success( [ 'message' => sprintf( __( 'Rigenerato: %s (ID: %d)', 'marrison-addon' ), $filename, $id ) ] );
	}


	/**
	 * Register custom image sizes.
	 */
	public function register_image_sizes() {
		$sizes = get_option( 'marrison_addon_image_sizes', [] );
		
		if ( ! empty( $sizes ) && is_array( $sizes ) ) {
			foreach ( $sizes as $size ) {
				if ( ! empty( $size['slug'] ) && ! empty( $size['width'] ) && ! empty( $size['height'] ) ) {
					// Use strict boolean for compatibility. true = center-center crop in WP.
					$crop = isset( $size['crop'] ) && $size['crop'] ? true : false;
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
			esc_html__( 'Dimensioni Immagini', 'marrison-addon' ),
			esc_html__( 'Dimensioni Immagini', 'marrison-addon' ),
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
		register_setting( 'marrison_addon_image_sizes_group', 'marrison_addon_disabled_sizes' );
	}

	/**
	 * Filter intermediate image sizes to remove disabled ones.
	 */
	public function filter_intermediate_image_sizes( $sizes ) {
		// Do NOT filter if we are on our own settings page, so we can list all sizes to toggle them.
		if ( isset( $_GET['page'] ) && 'marrison_addon_image_sizes' === $_GET['page'] ) {
			return $sizes;
		}

		$disabled_sizes = get_option( 'marrison_addon_disabled_sizes', [] );

		if ( ! empty( $disabled_sizes ) && is_array( $disabled_sizes ) ) {
			foreach ( $disabled_sizes as $slug => $is_disabled ) {
				if ( $is_disabled && isset( $sizes[ $slug ] ) ) {
					unset( $sizes[ $slug ] );
				}
			}
		}

		return $sizes;
	}

	/**
	 * Filter intermediate_image_sizes (list of slugs) to remove disabled ones.
	 * This affects get_intermediate_image_sizes() used by Elementor and others.
	 */
	public function filter_intermediate_image_sizes_list( $sizes ) {
		// Do NOT filter if we are on our own settings page.
		if ( isset( $_GET['page'] ) && 'marrison_addon_image_sizes' === $_GET['page'] ) {
			return $sizes;
		}

		$disabled_sizes = get_option( 'marrison_addon_disabled_sizes', [] );

		if ( ! empty( $disabled_sizes ) && is_array( $disabled_sizes ) ) {
			foreach ( $sizes as $key => $slug ) {
				if ( isset( $disabled_sizes[ $slug ] ) && $disabled_sizes[ $slug ] ) {
					unset( $sizes[ $key ] );
				}
			}
		}

		return $sizes;
	}

	/**
	 * Remove disabled sizes from Media Selector dropdown.
	 */
	public function remove_disabled_from_media_selector( $sizes ) {
		// Do NOT filter if we are on our own settings page (though this hook is mostly for media selector).
		if ( isset( $_GET['page'] ) && 'marrison_addon_image_sizes' === $_GET['page'] ) {
			return $sizes;
		}
		
		$disabled_sizes = get_option( 'marrison_addon_disabled_sizes', [] );

		if ( ! empty( $disabled_sizes ) && is_array( $disabled_sizes ) ) {
			foreach ( $disabled_sizes as $slug => $is_disabled ) {
				if ( $is_disabled && isset( $sizes[ $slug ] ) ) {
					unset( $sizes[ $slug ] );
				}
			}
		}

		return $sizes;
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
		$disabled_sizes = get_option( 'marrison_addon_disabled_sizes', [] );
		$all_sizes = wp_get_registered_image_subsizes();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Gestore Dimensioni Immagini', 'marrison-addon' ); ?></h1>
			<p><?php echo esc_html__( 'Registra dimensioni immagine personalizzate per il tuo tema.', 'marrison-addon' ); ?></p>

			<!-- Disable Sizes Section -->
			<div class="marrison-module-card" style="margin-bottom: 20px;">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title" style="font-size: 1.3em; margin: 0;"><?php echo esc_html__( 'Gestisci Dimensioni Generate', 'marrison-addon' ); ?></h2>
				</div>
				<p class="marrison-card-desc" style="margin-bottom: 15px;"><?php echo esc_html__( 'Usa gli switch qui sotto per controllare quali dimensioni immagine vengono generate da WordPress.', 'marrison-addon' ); ?></p>
				
				<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px;">
					<?php foreach ( $all_sizes as $slug => $data ) : 
						// Logic inverted: Checked means ENABLED (so NOT in disabled list)
						$is_disabled = isset( $disabled_sizes[ $slug ] ) && $disabled_sizes[ $slug ];
						$checked = ! $is_disabled ? 'checked' : '';
						$label = isset( $data['width'], $data['height'] ) ? "{$data['width']}x{$data['height']}" : '';
						$crop = isset( $data['crop'] ) && $data['crop'] ? ' (Cropped)' : '';
					?>
						<div style="background: #f0f0f1; padding: 10px 15px; border-radius: 4px; display: flex; align-items: center; gap: 10px;">
							<label class="marrison-switch" style="transform: scale(0.8); margin: 0;">
								<input type="checkbox" 
									   class="marrison-ajax-toggle" 
									   data-option="marrison_addon_disabled_sizes" 
									   data-key="<?php echo esc_attr( $slug ); ?>" 
									   data-inverse="true"
									   <?php echo $checked; ?>>
								<span class="marrison-slider"></span>
							</label>
							<div style="line-height: 1.2;">
								<div style="font-weight: 600; font-size: 13px; color: #1d2327;"><?php echo esc_html( $slug ); ?></div>
								<div style="color: #646970; font-size: 11px; margin-top: 2px;"><?php echo esc_html( "{$label}{$crop}" ); ?></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
				
				<!-- Add New Form -->
				<div class="marrison-module-card" style="width: 300px; flex-shrink: 0;">
					<div class="marrison-card-header">
						<h2 class="marrison-card-title" style="font-size: 1.3em; margin: 0;"><?php echo esc_html__( 'Aggiungi Nuova', 'marrison-addon' ); ?></h2>
					</div>
					<form method="post" style="margin-top: 15px;">
						<?php wp_nonce_field( 'marrison_image_sizes_action', 'marrison_nonce' ); ?>
						<input type="hidden" name="marrison_action" value="add_size">
						
						<p>
							<label for="size_name" style="font-weight: 600; display: block; margin-bottom: 5px;"><?php echo esc_html__( 'Nome Visualizzato', 'marrison-addon' ); ?></label>
							<input type="text" name="size_name" id="size_name" class="widefat" required>
						</p>
						<div style="display: flex; gap: 10px;">
							<p style="flex: 1;">
								<label for="size_width" style="font-weight: 600; display: block; margin-bottom: 5px;"><?php echo esc_html__( 'Larghezza', 'marrison-addon' ); ?></label>
								<input type="number" name="size_width" id="size_width" class="widefat" required min="1">
							</p>
							<p style="flex: 1;">
								<label for="size_height" style="font-weight: 600; display: block; margin-bottom: 5px;"><?php echo esc_html__( 'Altezza', 'marrison-addon' ); ?></label>
								<input type="number" name="size_height" id="size_height" class="widefat" required min="1">
							</p>
						</div>
						<p>
							<label>
								<input type="checkbox" name="size_crop" value="1" checked> 
								<?php echo esc_html__( 'Ritaglia (Hard Crop)', 'marrison-addon' ); ?>
							</label>
						</p>
						<p>
							<label>
								<input type="checkbox" name="size_show_in_media" value="1" checked> 
								<?php echo esc_html__( 'Mostra nel Selettore Media', 'marrison-addon' ); ?>
							</label>
						</p>
						<p style="margin-bottom: 0;">
							<input type="submit" class="button button-primary" value="<?php echo esc_attr__( 'Aggiungi Dimensione', 'marrison-addon' ); ?>" style="width: 100%;">
						</p>
					</form>
				</div>

				<!-- List Existing -->
				<div class="marrison-module-card" style="flex: 1; min-width: 300px;">
					<div class="marrison-card-header">
						<h2 class="marrison-card-title" style="font-size: 1.3em; margin: 0;"><?php echo esc_html__( 'Dimensioni Registrate', 'marrison-addon' ); ?></h2>
					</div>
					<div style="margin-top: 15px;">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Slug', 'marrison-addon' ); ?></th>
									<th><?php echo esc_html__( 'Nome', 'marrison-addon' ); ?></th>
									<th><?php echo esc_html__( 'Dimensioni', 'marrison-addon' ); ?></th>
									<th><?php echo esc_html__( 'Ritaglia', 'marrison-addon' ); ?></th>
									<th><?php echo esc_html__( 'Nei Media', 'marrison-addon' ); ?></th>
									<th><?php echo esc_html__( 'Azioni', 'marrison-addon' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $sizes ) ) : ?>
									<tr>
										<td colspan="6"><?php echo esc_html__( 'Nessuna dimensione personalizzata registrata.', 'marrison-addon' ); ?></td>
									</tr>
								<?php else : ?>
									<?php foreach ( $sizes as $index => $size ) : ?>
										<tr>
											<td><strong><?php echo esc_html( $size['slug'] ); ?></strong></td>
											<td><?php echo esc_html( $size['name'] ); ?></td>
											<td><?php echo esc_html( $size['width'] . ' x ' . $size['height'] ); ?> px</td>
											<td><?php echo $size['crop'] ? esc_html__( 'Sì', 'marrison-addon' ) : esc_html__( 'No', 'marrison-addon' ); ?></td>
											<td><?php echo isset( $size['show_in_media'] ) && $size['show_in_media'] ? esc_html__( 'Sì', 'marrison-addon' ) : esc_html__( 'No', 'marrison-addon' ); ?></td>
											<td>
												<form method="post" style="display:inline;">
													<?php wp_nonce_field( 'marrison_image_sizes_action', 'marrison_nonce' ); ?>
													<input type="hidden" name="marrison_action" value="delete_size">
													<input type="hidden" name="size_index" value="<?php echo esc_attr( $index ); ?>">
													<button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Sei sicuro?', 'marrison-addon' ) ); ?>');">
														<?php echo esc_html__( 'Elimina', 'marrison-addon' ); ?>
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

			<!-- Regenerate Thumbnails Section -->
			<div class="marrison-module-card" style="margin-top: 20px;">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title" style="font-size: 1.3em; margin: 0;"><?php echo esc_html__( 'Rigenera Miniature', 'marrison-addon' ); ?></h2>
				</div>
				<p class="marrison-card-desc" style="margin-bottom: 15px;"><?php echo esc_html__( 'Usa questo strumento per rigenerare le miniature di tutte le immagini utilizzando le dimensioni attualmente registrate.', 'marrison-addon' ); ?></p>
				
				<p style="margin-bottom: 15px; background: #f0f0f1; padding: 10px; border-radius: 4px;">
					<label style="display: flex; align-items: center; gap: 8px;">
						<input type="checkbox" id="marrison-cleanup-disabled" value="1"> 
						<span style="font-weight: 600;"><?php echo esc_html__( 'Elimina immagini nei tagli non abilitati', 'marrison-addon' ); ?></span>
					</label>
					<span class="description" style="display: block; margin-left: 24px; margin-top: 5px;">
						<?php echo esc_html__( 'Se selezionato, i file fisici per le dimensioni disabilitate verranno eliminati dal server durante la rigenerazione.', 'marrison-addon' ); ?>
					</span>
				</p>

				<button id="marrison-regenerate-btn" class="button button-primary button-large">
					<?php echo esc_html__( 'Rigenera Tutte le Miniature', 'marrison-addon' ); ?>
				</button>
				<button id="marrison-stop-btn" class="button button-secondary button-large" style="display: none; color: #d63638; border-color: #d63638;">
					<?php echo esc_html__( 'Interrompi Processo', 'marrison-addon' ); ?>
				</button>

				<div id="marrison-progress-bar">
					<div id="marrison-progress-fill"></div>
					<div id="marrison-progress-text">0%</div>
				</div>

				<div id="marrison-log-container">
					<ul id="marrison-log-list"></ul>
				</div>
			</div>
		</div>
		<?php
	}

	private function handle_form_submission() {
		$sizes = get_option( 'marrison_addon_image_sizes', [] );
		$action = $_POST['marrison_action'];

		if ( 'add_size' === $action ) {
			$name = sanitize_text_field( $_POST['size_name'] );
			$new_size = [
				'slug' => sanitize_title( $name ),
				'name' => $name,
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
		} elseif ( 'save_disabled_sizes' === $action ) {
			$disabled = isset( $_POST['disabled_sizes'] ) ? $_POST['disabled_sizes'] : [];
			// Sanitize array
			$clean_disabled = [];
			foreach ( $disabled as $slug => $val ) {
				$clean_disabled[ sanitize_key( $slug ) ] = 1;
			}
			update_option( 'marrison_addon_disabled_sizes', $clean_disabled );
			add_settings_error( 'marrison_messages', 'marrison_sizes_updated', __( 'Disabled sizes updated.', 'marrison-addon' ), 'updated' );
		}
	}
}
