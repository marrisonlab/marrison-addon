<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Image_Sizes {

	const WEBP_FULL_METADATA_KEY = 'marrison_webp_full';
	const AVIF_FULL_METADATA_KEY = 'marrison_avif_full';
	const ELEMENTOR_CSS_CACHE_VERSION_OPTION = 'marrison_addon_webp_css_cache_version';
	const REGISTERED_WEBP_OPTION = 'marrison_addon_registered_size_webp';
	const FULL_REPLACEMENT_SIZE_OPTION = 'marrison_addon_full_replacement_size';
	const LCP_HERO_CONTROL = 'marrison_image_lcp_hero';

	private $webp_enabled_sizes = null;
	private $url_replacement_cache = [];
	private $lcp_preloaded_urls = [];
	private $lcp_hero_container_stack = [];

	public function __construct() {
		// Hooks for functionality
		add_action( 'after_setup_theme', [ $this, 'register_image_sizes' ] );
		add_filter( 'image_size_names_choose', [ $this, 'add_to_media_selector' ] );
		add_filter( 'image_size_names_choose', [ $this, 'remove_disabled_from_media_selector' ], 20 );
		add_filter( 'intermediate_image_sizes_advanced', [ $this, 'filter_intermediate_image_sizes' ] );
		add_filter( 'intermediate_image_sizes', [ $this, 'filter_intermediate_image_sizes_list' ] );
		add_filter( 'image_resize_dimensions', [ $this, 'enable_upscaling' ], 10, 6 );
		add_filter( 'intermediate_image_sizes_advanced', [ $this, 'force_upscale_sizes' ], 999 );
		add_filter( 'image_downsize', [ $this, 'force_downsize_upscale' ], 10, 3 );
		add_filter( 'image_downsize', [ $this, 'serve_webp_downsize' ], 20, 3 );
		add_filter( 'wp_calculate_image_srcset', [ $this, 'serve_webp_srcset' ], 20, 5 );
		add_filter( 'elementor/files/css/property', [ $this, 'filter_elementor_css_property_value' ], 20, 4 );
		add_action( 'template_redirect', [ $this, 'start_frontend_webp_rewrite' ], 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
		add_action( 'elementor/element/container/section_layout/after_section_end', [ $this, 'register_lcp_hero_controls' ] );
		add_action( 'elementor/frontend/container/before_render', [ $this, 'before_render_lcp_hero_container' ] );
		add_action( 'elementor/frontend/container/after_render', [ $this, 'after_render_lcp_hero_container' ] );
		add_action( 'elementor/loaded', [ $this, 'maybe_clear_elementor_css_cache' ] );

		// Hooks for Admin UI
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		// AJAX Hooks
		add_action( 'wp_ajax_marrison_get_image_ids', [ $this, 'ajax_get_image_ids' ] );
		add_action( 'wp_ajax_marrison_regenerate_single_image', [ $this, 'ajax_regenerate_single_image' ] );
		add_action( 'wp_ajax_marrison_clear_elementor_css_cache', [ $this, 'ajax_clear_elementor_css_cache' ] );
		add_action( 'wp_ajax_marrison_test_avif_support', [ $this, 'ajax_test_avif_support' ] );
		add_action( 'wp_ajax_marrison_resolve_background_webp', [ $this, 'ajax_resolve_background_webp' ] );
		add_action( 'wp_ajax_nopriv_marrison_resolve_background_webp', [ $this, 'ajax_resolve_background_webp' ] );

		if ( did_action( 'elementor/loaded' ) ) {
			$this->maybe_clear_elementor_css_cache();
		}
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
			'cache_cleared' => __( 'Cache CSS Elementor aggiornata.', 'marrison-addon' ),
			'avif_testing' => __( 'Test supporto AVIF in corso...', 'marrison-addon' ),
		] );
		
		// Add JavaScript for WebP/AVIF quality toggles
		wp_add_inline_script( 'marrison-admin-image-sizes', '
			jQuery(document).ready(function($) {
				$("input[name=\'size_webp\']").on("change", function() {
					if ($(this).is(":checked")) {
						$("#webp-quality-container").show();
					} else {
						$("#webp-quality-container").hide();
					}
				}).trigger("change");

				$("input[name=\'size_avif\']").on("change", function() {
					if ($(this).is(":checked")) {
						$("#avif-quality-container").show();
					} else {
						$("#avif-quality-container").hide();
					}
				}).trigger("change");
			});
		', 'after' );
		
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
	 * Enqueue frontend helper for responsive dynamic background images.
	 */
	public function enqueue_frontend_scripts() {
		if ( ! $this->should_serve_webp_on_frontend() ) {
			return;
		}

		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return;
		}

		$plugin_root_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php';

		wp_enqueue_script(
			'marrison-background-webp',
			plugins_url( 'assets/js/marrison-bg-webp.js', $plugin_root_file ),
			[],
			Marrison_Addon::VERSION,
			true
		);

		wp_localize_script(
			'marrison-background-webp',
			'marrisonBgWebp',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'uploadsBaseUrl' => $uploads['baseurl'],
				'preferAvif' => $this->client_prefers_avif(),
			]
		);
	}

	/**
	 * Add a small LCP section to Elementor Containers.
	 *
	 * The control is intentionally scoped to Containers because it describes a
	 * page-level hero region, not a generic image optimization rule.
	 */
	public function register_lcp_hero_controls( $element ) {
		if ( ! class_exists( '\Elementor\Controls_Manager' ) || ! method_exists( $element, 'start_controls_section' ) ) {
			return;
		}

		$element->start_controls_section(
			'section_marrison_image_lcp',
			[
				'label' => esc_html__( 'Ottimizzazione LCP', 'marrison-addon' ),
				'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
			]
		);

		$element->add_control(
			self::LCP_HERO_CONTROL,
			[
				'label' => esc_html__( 'Imposta come Hero LCP', 'marrison-addon' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sì', 'marrison-addon' ),
				'label_off' => esc_html__( 'No', 'marrison-addon' ),
				'return_value' => 'yes',
				'default' => '',
				'description' => esc_html__( 'Precarica la prima immagine di sfondo e dà priorità alle immagini interne del container.', 'marrison-addon' ),
			]
		);

		$element->end_controls_section();
	}

	/**
	 * Mark LCP containers and emit an early preload tag before the container HTML.
	 */
	public function before_render_lcp_hero_container( $container ) {
		if ( ! method_exists( $container, 'get_settings_for_display' ) || Marrison_Addon_Context::is_elementor_editor_context() ) {
			return;
		}

		$settings = $container->get_settings_for_display();
		$is_lcp_hero = ! empty( $settings[ self::LCP_HERO_CONTROL ] ) && 'yes' === $settings[ self::LCP_HERO_CONTROL ];
		$is_inside_lcp_hero = ! empty( $this->lcp_hero_container_stack );

		if ( ! $is_lcp_hero && ! $is_inside_lcp_hero ) {
			return;
		}

		$this->lcp_hero_container_stack[] = true;

		if ( $is_lcp_hero && method_exists( $container, 'add_render_attribute' ) ) {
			$container->add_render_attribute( '_wrapper', 'data-marrison-lcp-hero', '1' );
		}

		$preload_url = $this->get_lcp_preload_url_from_container_settings( $settings );
		if ( ! $preload_url || isset( $this->lcp_preloaded_urls[ $preload_url ] ) ) {
			return;
		}

		$this->lcp_preloaded_urls[ $preload_url ] = true;

		echo '<link rel="preload" as="image" href="' . esc_url( $preload_url ) . '" fetchpriority="high" />' . "\n";
	}

	/**
	 * Keep track of nested containers while Elementor renders a marked LCP region.
	 */
	public function after_render_lcp_hero_container() {
		if ( ! empty( $this->lcp_hero_container_stack ) ) {
			array_pop( $this->lcp_hero_container_stack );
		}
	}

	/**
	 * Resolve the image Elementor should preload for a marked hero container.
	 */
	private function get_lcp_preload_url_from_container_settings( array $settings ) {
		if ( ! empty( $settings['background_slideshow_gallery'] ) && is_array( $settings['background_slideshow_gallery'] ) ) {
			$first_slide = reset( $settings['background_slideshow_gallery'] );
			if ( is_array( $first_slide ) && ! empty( $first_slide['url'] ) ) {
				return $this->get_preload_webp_url( $first_slide['url'], isset( $first_slide['id'] ) ? (int) $first_slide['id'] : 0 );
			}
		}

		if ( ! empty( $settings['background_image'] ) && is_array( $settings['background_image'] ) && ! empty( $settings['background_image']['url'] ) ) {
			return $this->get_preload_webp_url( $settings['background_image']['url'], isset( $settings['background_image']['id'] ) ? (int) $settings['background_image']['id'] : 0 );
		}

		return '';
	}

	/**
	 * Prefer a generated WebP URL, then fall back to a cheap extension rewrite.
	 */
	private function get_preload_webp_url( $url, $attachment_id = 0 ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		$replacement = $this->get_webp_replacement_for_url( $url, $attachment_id );
		if ( $replacement ) {
			return $replacement;
		}

		return $this->replace_image_extension_with_webp( $url );
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

		// Generate metadata with upscaling support
		$metadata = $this->generate_metadata_with_upscaling( $id, $fullsizepath );

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
	 * AJAX: Clear Elementor generated CSS cache after thumbnail regeneration.
	 */
	public function ajax_clear_elementor_css_cache() {
		check_ajax_referer( 'marrison_regenerate_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$this->clear_elementor_css_cache();

		wp_send_json_success( [ 'message' => __( 'Cache CSS Elementor aggiornata.', 'marrison-addon' ) ] );
	}

	/**
	 * AJAX: Test whether the current WordPress image stack can write AVIF files.
	 */
	public function ajax_test_avif_support() {
		check_ajax_referer( 'marrison_regenerate_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$wp_editor_supports_avif = $this->image_editor_supports_mime_type( 'image/avif' );
		$gd_supports_avif = function_exists( 'imageavif' );
		$supported = $wp_editor_supports_avif || $gd_supports_avif;

		$details = [];
		$details[] = $wp_editor_supports_avif ? __( 'WP_Image_Editor: supportato', 'marrison-addon' ) : __( 'WP_Image_Editor: non supportato', 'marrison-addon' );
		$details[] = $gd_supports_avif ? __( 'GD imageavif(): disponibile', 'marrison-addon' ) : __( 'GD imageavif(): non disponibile', 'marrison-addon' );

		if ( $supported ) {
			wp_send_json_success( [
				'message' => __( 'AVIF supportato dal server. Puoi abilitarlo e rigenerare le miniature.', 'marrison-addon' ),
				'details' => $details,
			] );
		}

		wp_send_json_error( [
			'message' => __( 'AVIF non supportato dal server. Abilitalo su Imagick/GD prima di rigenerare.', 'marrison-addon' ),
			'details' => $details,
		] );
	}

	/**
	 * AJAX: Resolve the best WebP size for measured CSS background containers.
	 */
	public function ajax_resolve_background_webp() {
		$items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : [];
		if ( empty( $items ) ) {
			wp_send_json_success( [ 'items' => [] ] );
		}

		$items = array_slice( $items, 0, 50 );
		$resolved = [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$key = isset( $item['key'] ) ? sanitize_key( $item['key'] ) : '';
			$url = isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '';
			$target_width = isset( $item['width'] ) ? absint( $item['width'] ) : 0;
			$target_height = isset( $item['height'] ) ? absint( $item['height'] ) : 0;
			$fit = isset( $item['fit'] ) ? sanitize_key( $item['fit'] ) : 'width';
			$preferred_format = isset( $item['format'] ) ? sanitize_key( $item['format'] ) : '';

			if ( '' === $key || '' === $url || $target_width < 1 || ! $this->is_uploads_url( $url ) ) {
				continue;
			}

			$attachment_id = $this->resolve_attachment_id_from_upload_url( $url );
			if ( ! $attachment_id ) {
				continue;
			}

			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( empty( $metadata ) || ! is_array( $metadata ) ) {
				continue;
			}

			$source = $this->get_best_webp_source_for_container( $metadata, $target_width, $target_height, $fit, $preferred_format );
			if ( ! $source || empty( $source['file'] ) ) {
				continue;
			}

			$source_url = $this->build_metadata_file_url( $metadata, $source['file'] );
			if ( ! $source_url ) {
				continue;
			}

			$resolved[ $key ] = [
				'url' => $source_url,
				'width' => isset( $source['width'] ) ? (int) $source['width'] : 0,
				'height' => isset( $source['height'] ) ? (int) $source['height'] : 0,
			];
		}

		wp_send_json_success( [ 'items' => $resolved ] );
	}

	/**
	 * Generate attachment metadata with upscaling support.
	 */
	private function generate_metadata_with_upscaling( $attachment_id, $file ) {
		$custom_sizes = get_option( 'marrison_addon_image_sizes', [] );
		$upscale_sizes = [];
		$webp_sizes = [];

		// Collect sizes with upscaling and webp enabled
		if ( ! empty( $custom_sizes ) && is_array( $custom_sizes ) ) {
			foreach ( $custom_sizes as $size ) {
				if ( ! empty( $size['slug'] ) ) {
					$size_data = [
						'width' => (int) $size['width'],
						'height' => (int) $size['height'],
						'crop' => isset( $size['crop'] ) && $size['crop'],
						'webp' => isset( $size['webp'] ) && $size['webp'],
						'webp_quality' => isset( $size['webp_quality'] ) ? (int) $size['webp_quality'] : 85,
						'avif' => isset( $size['avif'] ) && $size['avif'],
						'avif_quality' => isset( $size['avif_quality'] ) ? (int) $size['avif_quality'] : 75,
					];

					if ( isset( $size['upscale'] ) && $size['upscale'] ) {
						$upscale_sizes[ $size['slug'] ] = $size_data;
					}

					if ( ! empty( $size_data['webp'] ) || ! empty( $size_data['avif'] ) ) {
						$webp_sizes[ $size['slug'] ] = $size_data;
					}
				}
			}
		}

		$webp_sizes = array_merge( $webp_sizes, $this->get_registered_webp_size_definitions() );

		// Generate standard metadata first
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );

		if ( is_wp_error( $metadata ) || empty( $metadata ) ) {
			return $metadata;
		}

		// Now manually generate upscale sizes if they don't exist
		if ( ! empty( $upscale_sizes ) ) {
			foreach ( $upscale_sizes as $slug => $size_data ) {
				// Check if this size doesn't exist or needs regeneration
				if ( isset( $metadata['sizes'][ $slug ] ) ) {
					continue;
				}

				$editor = wp_get_image_editor( $file );

				if ( is_wp_error( $editor ) ) {
					continue;
				}

				$orig_size = $editor->get_size();
				$orig_w = $orig_size['width'];
				$orig_h = $orig_size['height'];

				$dest_w = $size_data['width'];
				$dest_h = $size_data['height'];
				$crop = $size_data['crop'];

				// Use center-center crop position for hard crop
				$crop_position = $crop ? ['center', 'center'] : false;

				// Request the final dimensions directly. The image_resize_dimensions
				// filter handles proportional upscaling and calculates the source crop.
				$editor->resize( $dest_w, $dest_h, $crop_position );

				// Save the resized image
				$saved = $editor->save( $editor->generate_filename( $slug ) );

				if ( ! is_wp_error( $saved ) ) {
					// Add to metadata
					$metadata['sizes'][ $slug ] = [
						'file' => $saved['file'],
						'width' => $saved['width'],
						'height' => $saved['height'],
						'mime-type' => $saved['mime-type'],
					];
				}
			}
		}

		if ( ! empty( $webp_sizes ) && $this->size_definitions_include_format( $webp_sizes, 'webp' ) ) {
			$full_webp_quality = $this->get_full_webp_quality( $webp_sizes );
			$full_webp_path = $this->convert_to_format( $file, 'image/webp', $full_webp_quality );

			if ( ! is_wp_error( $full_webp_path ) && file_exists( $full_webp_path ) ) {
				$metadata[ self::WEBP_FULL_METADATA_KEY ] = [
					'file' => basename( $full_webp_path ),
					'mime_type' => 'image/webp',
					'width' => isset( $metadata['width'] ) ? (int) $metadata['width'] : 0,
					'height' => isset( $metadata['height'] ) ? (int) $metadata['height'] : 0,
				];
			}
		}

		if ( ! empty( $webp_sizes ) && $this->size_definitions_include_format( $webp_sizes, 'avif' ) ) {
			$full_avif_quality = $this->get_full_avif_quality( $webp_sizes );
			$full_avif_path = $this->convert_to_format( $file, 'image/avif', $full_avif_quality );

			if ( ! is_wp_error( $full_avif_path ) && file_exists( $full_avif_path ) ) {
				$metadata[ self::AVIF_FULL_METADATA_KEY ] = [
					'file' => basename( $full_avif_path ),
					'mime_type' => 'image/avif',
					'width' => isset( $metadata['width'] ) ? (int) $metadata['width'] : 0,
					'height' => isset( $metadata['height'] ) ? (int) $metadata['height'] : 0,
				];
			}
		}

		// Generate WebP versions for sizes with webp enabled
		if ( ! empty( $webp_sizes ) ) {
			foreach ( $webp_sizes as $slug => $size_data ) {
				// Get the original or generated image file
				if ( isset( $metadata['sizes'][ $slug ] ) ) {
					$size_file = pathinfo( $metadata['sizes'][ $slug ]['file'], PATHINFO_BASENAME );
					$size_path = dirname( $file ) . '/' . $size_file;
				} else {
					// If size doesn't exist, generate it first
					$size_path = $this->generate_single_size( $file, $slug, $size_data );
					if ( is_wp_error( $size_path ) ) {
						continue;
					}
				}

				if ( file_exists( $size_path ) ) {
					if ( ! empty( $size_data['webp'] ) ) {
						$webp_quality = isset( $size_data['webp_quality'] ) ? $size_data['webp_quality'] : 85;
						$webp_path = $this->convert_to_format( $size_path, 'image/webp', $webp_quality );
						if ( ! is_wp_error( $webp_path ) && file_exists( $webp_path ) ) {
							$metadata = $this->add_modern_source_to_metadata( $metadata, $slug, $webp_path, 'image/webp', $size_data );
						}
					}

					if ( ! empty( $size_data['avif'] ) ) {
						$avif_quality = isset( $size_data['avif_quality'] ) ? $size_data['avif_quality'] : 75;
						$avif_path = $this->convert_to_format( $size_path, 'image/avif', $avif_quality );
						if ( ! is_wp_error( $avif_path ) && file_exists( $avif_path ) ) {
							$metadata = $this->add_modern_source_to_metadata( $metadata, $slug, $avif_path, 'image/avif', $size_data );
						}
					}
				}
			}
		}

		return $metadata;
	}

	/**
	 * Generate a single image size.
	 */
	private function generate_single_size( $file, $slug, $size_data ) {
		$editor = wp_get_image_editor( $file );

		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$orig_size = $editor->get_size();
		$orig_w = $orig_size['width'];
		$orig_h = $orig_size['height'];

		$dest_w = $size_data['width'];
		$dest_h = $size_data['height'];
		$crop = $size_data['crop'];

		// Use center-center crop position for hard crop
		$crop_position = $crop ? ['center', 'center'] : false;
		$editor->resize( $dest_w, $dest_h, $crop_position );
		$saved = $editor->save( $editor->generate_filename( $slug ) );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return dirname( $file ) . '/' . $saved['file'];
	}

	/**
	 * Convert image to WebP format.
	 */
	private function convert_to_webp( $file_path, $quality = 85 ) {
		return $this->convert_to_format( $file_path, 'image/webp', $quality );
	}

	/**
	 * Convert an image to a modern output format supported by WP_Image_Editor or GD.
	 */
	private function convert_to_format( $file_path, $mime_type, $quality = 85 ) {
		$extension = $this->get_extension_for_mime_type( $mime_type );
		if ( '' === $extension ) {
			return new WP_Error( 'unsupported_format', __( 'Formato immagine non supportato.', 'marrison-addon' ) );
		}

		$wp_editor_supports_format = $this->image_editor_supports_mime_type( $mime_type );
		$gd_callback = 'image/webp' === $mime_type ? 'imagewebp' : ( 'image/avif' === $mime_type ? 'imageavif' : '' );

		if ( ! $wp_editor_supports_format && ( '' === $gd_callback || ! function_exists( $gd_callback ) ) ) {
			return new WP_Error( 'format_not_supported', sprintf( __( '%s non è supportato su questo server.', 'marrison-addon' ), strtoupper( $extension ) ) );
		}

		// Get image info
		$image_info = getimagesize( $file_path );
		if ( ! $image_info ) {
			return new WP_Error( 'invalid_image', __( 'Impossibile leggere l\'immagine.', 'marrison-addon' ) );
		}

		$source_mime_type = $image_info['mime'];
		$output_path = preg_replace( '/\.(jpe?g|png|gif|webp|avif)$/i', '.' . $extension, $file_path );
		if ( empty( $output_path ) || $output_path === $file_path ) {
			return new WP_Error( 'unsupported_format', __( 'Formato immagine non supportato.', 'marrison-addon' ) );
		}

		if ( $wp_editor_supports_format ) {
			$editor = wp_get_image_editor( $file_path );
			if ( ! is_wp_error( $editor ) ) {
				$editor->set_quality( $quality );
				$saved = $editor->save( $output_path, $mime_type );

				if ( ! is_wp_error( $saved ) && file_exists( $output_path ) ) {
					return $output_path;
				}
			}
		}

		if ( '' === $gd_callback || ! function_exists( $gd_callback ) ) {
			return new WP_Error( 'conversion_failed', __( 'Conversione immagine fallita.', 'marrison-addon' ) );
		}

		// Create image from source
		switch ( $source_mime_type ) {
			case 'image/jpeg':
				$image = imagecreatefromjpeg( $file_path );
				break;
			case 'image/png':
				$image = imagecreatefrompng( $file_path );
				break;
			case 'image/gif':
				$image = imagecreatefromgif( $file_path );
				break;
			default:
				return new WP_Error( 'unsupported_format', __( 'Formato immagine non supportato.', 'marrison-addon' ) );
		}

		if ( ! $image ) {
				return new WP_Error( 'image_creation_failed', __( 'Impossibile creare l\'immagine.', 'marrison-addon' ) );
		}

		// Preserve transparency where the GD encoder supports it.
		if ( in_array( $source_mime_type, [ 'image/png', 'image/gif' ], true ) ) {
			if ( function_exists( 'imagepalettetotruecolor' ) ) {
				imagepalettetotruecolor( $image );
			}
			if ( function_exists( 'imagealphablending' ) ) {
				imagealphablending( $image, true );
			}
			if ( function_exists( 'imagesavealpha' ) ) {
				imagesavealpha( $image, true );
			}
		}

		$result = $gd_callback( $image, $output_path, $quality );
		imagedestroy( $image );

		if ( ! $result ) {
			return new WP_Error( 'conversion_failed', __( 'Conversione immagine fallita.', 'marrison-addon' ) );
		}

		return $output_path;
	}

	private function image_editor_supports_mime_type( $mime_type ) {
		return function_exists( 'wp_image_editor_supports' ) && wp_image_editor_supports( [ 'mime_type' => $mime_type ] );
	}

	private function get_extension_for_mime_type( $mime_type ) {
		if ( 'image/webp' === $mime_type ) {
			return 'webp';
		}

		if ( 'image/avif' === $mime_type ) {
			return 'avif';
		}

		return '';
	}

	private function add_modern_source_to_metadata( $metadata, $slug, $file_path, $mime_type, $size_data ) {
		if ( empty( $metadata['sizes'][ $slug ] ) ) {
			return $metadata;
		}

		if ( ! isset( $metadata['sizes'][ $slug ]['sources'] ) || ! is_array( $metadata['sizes'][ $slug ]['sources'] ) ) {
			$metadata['sizes'][ $slug ]['sources'] = [];
		}

		$metadata['sizes'][ $slug ]['sources'][] = [
			'file' => basename( $file_path ),
			'mime_type' => $mime_type,
			'width' => isset( $metadata['sizes'][ $slug ]['width'] ) ? (int) $metadata['sizes'][ $slug ]['width'] : $size_data['width'],
			'height' => isset( $metadata['sizes'][ $slug ]['height'] ) ? (int) $metadata['sizes'][ $slug ]['height'] : $size_data['height'],
		];

		return $metadata;
	}

	/**
	 * Serve WebP for frontend calls to wp_get_attachment_image_src()/URL().
	 */
	public function serve_webp_downsize( $downsize, $id, $size ) {
		if ( $downsize || ! $this->should_serve_webp_on_frontend() ) {
			return $downsize;
		}

		$metadata = wp_get_attachment_metadata( $id );
		if ( empty( $metadata ) || ! is_array( $metadata ) ) {
			return $downsize;
		}

		if ( 'full' === $size ) {
			$source = $this->get_full_replacement_preferred_source( $metadata );
			if ( ! $source ) {
				return $downsize;
			}

			$url = $this->build_metadata_file_url( $metadata, $source['file'] );
			if ( ! $url || ! $this->metadata_file_exists( $metadata, $source['file'] ) ) {
				return $downsize;
			}

			return [
				$url,
				isset( $source['width'] ) ? (int) $source['width'] : 0,
				isset( $source['height'] ) ? (int) $source['height'] : 0,
				false,
			];
		}

		$slug = $this->resolve_webp_size_slug( $size, $metadata );
		if ( ! $slug ) {
			return $downsize;
		}

		$source = $this->get_preferred_source_for_size( $metadata, $slug );
		if ( ! $source ) {
			return $downsize;
		}

		$url = $this->build_metadata_file_url( $metadata, $source['file'] );
		if ( ! $url || ! $this->metadata_file_exists( $metadata, $source['file'] ) ) {
			return $downsize;
		}

		return [
			$url,
			isset( $source['width'] ) ? (int) $source['width'] : 0,
			isset( $source['height'] ) ? (int) $source['height'] : 0,
			true,
		];
	}

	/**
	 * Replace JPG/PNG srcset candidates with their WebP counterparts when present.
	 */
	public function serve_webp_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( empty( $sources ) || ! is_array( $sources ) || ! $this->should_serve_webp_on_frontend() ) {
			return $sources;
		}

		foreach ( $sources as $width => $source ) {
			if ( empty( $source['url'] ) ) {
				continue;
			}

			$replacement = $this->get_webp_replacement_for_url( $source['url'], $attachment_id, $image_meta );
			if ( $replacement ) {
				$sources[ $width ]['url'] = $replacement;
			}
		}

		return $sources;
	}

	/**
	 * Replace Elementor CSS image-control URLs before generated CSS files are written.
	 */
	public function filter_elementor_css_property_value( $value, $css_property, $matches, $control ) {
		if ( false === stripos( $css_property, 'url' ) || empty( $this->get_webp_enabled_sizes() ) ) {
			return $value;
		}

		return $this->replace_webp_urls_in_value( $value );
	}

	/**
	 * Start a frontend output buffer that catches inline styles and plugin-rendered HTML.
	 */
	public function start_frontend_webp_rewrite() {
		if ( ! $this->should_serve_webp_on_frontend() || is_feed() || is_robots() || is_trackback() ) {
			return;
		}

		ob_start( [ $this, 'rewrite_frontend_webp_urls' ] );
	}

	/**
	 * Rewrite known upload JPG/PNG/GIF URLs in frontend HTML/CSS output.
	 */
	public function rewrite_frontend_webp_urls( $html ) {
		if ( '' === $html ) {
			return $html;
		}

		$html = $this->rewrite_elementor_lightbox_action_hashes( $html );
		$html = $this->rewrite_elementor_data_settings( $html );
		$html = $this->optimize_lcp_hero_internal_images( $html );
		$html = $this->inject_lcp_hero_background_preloads( $html );
		$this->maybe_send_vary_accept_header();

		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return $html;
		}

		$base_path = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
		if ( false === strpos( $html, $uploads['baseurl'] ) && ( ! $base_path || false === strpos( $html, $base_path ) ) ) {
			return $html;
		}

		$patterns = [
			preg_quote( $uploads['baseurl'], '~' ),
			preg_quote( preg_replace( '~^https?:~i', '', $uploads['baseurl'] ), '~' ),
		];

		if ( $base_path ) {
			$patterns[] = preg_quote( $base_path, '~' );
		}

		$pattern = '~(?P<url>(?:' . implode( '|', array_unique( array_filter( $patterns ) ) ) . ')[^\\s\\\'"<>)]*\\.(?:jpe?g|png|gif|webp|avif)(?:\\?[^\\s\\\'"<>)]*)?)~i';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$replacement = $this->get_webp_replacement_for_url( $matches['url'] );
				return $replacement ? $replacement : $matches['url'];
			},
			$html
		);
	}

	/**
	 * Rewrite Elementor lightbox settings stored as URL-encoded Base64 JSON.
	 */
	private function rewrite_elementor_lightbox_action_hashes( $html ) {
		if ( false === strpos( $html, 'data-e-action-hash' ) || false === strpos( $html, 'settings%3D' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'~data-e-action-hash=(["\'])(?P<value>.*?)\1~i',
			function ( $matches ) {
				$value = $matches['value'];
				if ( false === strpos( $value, 'settings%3D' ) ) {
					return $matches[0];
				}

				$updated_value = preg_replace_callback(
					'~(settings%3D)(?P<payload>[^&"\']+)~i',
					function ( $payload_matches ) {
						$payload = rawurldecode( $payload_matches['payload'] );
						$json = base64_decode( $payload, true );

						if ( false === $json || ! $this->string_may_contain_known_image( $json ) ) {
							return $payload_matches[0];
						}

						$updated_json = $this->replace_json_image_urls_with_webp( $json );
						if ( $updated_json === $json ) {
							return $payload_matches[0];
						}

						return $payload_matches[1] . rawurlencode( base64_encode( $updated_json ) );
					},
					$value
				);

				if ( ! is_string( $updated_value ) || $updated_value === $value ) {
					return $matches[0];
				}

				return 'data-e-action-hash=' . $matches[1] . $updated_value . $matches[1];
			},
			$html
		);
	}

	/**
	 * Rewrite Elementor background slideshow JSON stored in data-settings.
	 */
	private function rewrite_elementor_data_settings( $html ) {
		if ( false === strpos( $html, 'background_slideshow_gallery' ) || false === strpos( $html, 'data-settings' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'~data-settings=(["\'])(?P<value>.*?)\1~is',
			function ( $matches ) {
				$decoded = html_entity_decode( $matches['value'], ENT_QUOTES, 'UTF-8' );

				if ( false === strpos( $decoded, 'background_slideshow_gallery' ) || ! $this->string_may_contain_known_image( $decoded ) ) {
					return $matches[0];
				}

				$updated = $this->replace_json_image_urls_with_webp( $decoded );
				if ( $updated === $decoded ) {
					return $matches[0];
				}

				return 'data-settings=' . $matches[1] . htmlspecialchars( $updated, ENT_QUOTES, 'UTF-8' ) . $matches[1];
			},
			$html
		);
	}

	/**
	 * Add high priority/eager loading to the first image inside marked LCP hero containers.
	 */
	private function optimize_lcp_hero_internal_images( $html ) {
		if ( false === strpos( $html, 'data-marrison-lcp-hero' ) || false === strpos( $html, '<img' ) ) {
			return $html;
		}

		$marker = 'data-marrison-lcp-hero';
		$offset = 0;

		while ( false !== ( $marker_pos = strpos( $html, $marker, $offset ) ) ) {
			$next_marker_pos = strpos( $html, $marker, $marker_pos + strlen( $marker ) );
			$img_pos = strpos( $html, '<img', $marker_pos );

			if ( false === $img_pos || ( false !== $next_marker_pos && $next_marker_pos < $img_pos ) ) {
				$offset = $marker_pos + strlen( $marker );
				continue;
			}

			$img_end = strpos( $html, '>', $img_pos );
			if ( false === $img_end ) {
				break;
			}

			$img_tag = substr( $html, $img_pos, $img_end - $img_pos + 1 );
			$updated_img_tag = $this->prioritize_lcp_image_tag( $img_tag );

			if ( $updated_img_tag !== $img_tag ) {
				$html = substr_replace( $html, $updated_img_tag, $img_pos, strlen( $img_tag ) );
				$img_end = $img_pos + strlen( $updated_img_tag ) - 1;
			}

			$offset = $img_end + 1;
		}

		return $html;
	}

	/**
	 * Force an image tag to load eagerly and receive high fetch priority.
	 */
	private function prioritize_lcp_image_tag( $img_tag ) {
		if ( false === stripos( $img_tag, '<img' ) ) {
			return $img_tag;
		}

		$img_tag = preg_replace( '~\sloading=(["\']).*?\1~i', '', $img_tag );

		if ( false !== stripos( $img_tag, 'fetchpriority=' ) ) {
			$img_tag = preg_replace( '~\sfetchpriority=(["\']).*?\1~i', ' fetchpriority="high"', $img_tag );
		} else {
			$img_tag = preg_replace( '~<img\b~i', '<img fetchpriority="high"', $img_tag, 1 );
		}

		if ( false !== stripos( $img_tag, 'loading=' ) ) {
			return $img_tag;
		}

		return preg_replace( '~<img\b~i', '<img loading="eager"', $img_tag, 1 );
	}

	/**
	 * Add head preloads for CSS/background images found inside marked LCP regions.
	 */
	private function inject_lcp_hero_background_preloads( $html ) {
		if ( false === strpos( $html, 'data-marrison-lcp-hero' ) || false === stripos( $html, '</head>' ) ) {
			return $html;
		}

		$preload_urls = array_unique( array_merge( array_keys( $this->lcp_preloaded_urls ), $this->find_lcp_hero_background_preload_urls( $html ) ) );
		if ( empty( $preload_urls ) ) {
			return $html;
		}

		$links = '';
		$head_html = substr( $html, 0, stripos( $html, '</head>' ) );
		foreach ( $preload_urls as $url ) {
			if ( false !== strpos( $head_html, 'href="' . esc_url( $url ) . '"' ) ) {
				continue;
			}

			$this->lcp_preloaded_urls[ $url ] = true;
			$links .= '<link rel="preload" as="image" href="' . esc_url( $url ) . '" fetchpriority="high" />' . "\n";
		}

		if ( '' === $links ) {
			return $html;
		}

		return preg_replace( '~</head>~i', $links . '</head>', $html, 1 );
	}

	/**
	 * Locate the first Elementor background image in each marked LCP region.
	 */
	private function find_lcp_hero_background_preload_urls( $html ) {
		$urls = [];
		$marker = 'data-marrison-lcp-hero';
		$offset = 0;

		while ( false !== ( $marker_pos = strpos( $html, $marker, $offset ) ) ) {
			$next_marker_pos = strpos( $html, $marker, $marker_pos + strlen( $marker ) );
			$segment_length = false === $next_marker_pos ? null : $next_marker_pos - $marker_pos;
			$segment = substr( $html, $marker_pos, $segment_length );
			$url = $this->extract_lcp_background_preload_url_from_html_segment( $segment );

			if ( $url && ! in_array( $url, $urls, true ) ) {
				$urls[] = $url;
			}

			$offset = $marker_pos + strlen( $marker );
		}

		return $urls;
	}

	/**
	 * Read Elementor data-settings/style background URLs from a small HTML slice.
	 */
	private function extract_lcp_background_preload_url_from_html_segment( $html ) {
		if ( false !== strpos( $html, 'data-settings' ) ) {
			if ( preg_match_all( '~data-settings=(["\'])(?P<value>.*?)\1~is', $html, $matches ) ) {
				foreach ( $matches['value'] as $value ) {
					$url = $this->extract_lcp_background_preload_url_from_settings_json( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) );
					if ( $url ) {
						return $url;
					}
				}
			}
		}

		if ( preg_match_all( '~background-image\s*:\s*url\((["\']?)(?P<url>.*?)\1\)~is', $html, $matches ) ) {
			foreach ( $matches['url'] as $url ) {
				if ( $this->is_uploads_url( $url ) ) {
					return $this->get_preload_webp_url( $url );
				}
			}
		}

		if ( preg_match( '~url\((["\']?)(?P<url>.*?)\1\)~is', $html, $match ) && $this->is_uploads_url( $match['url'] ) ) {
			return $this->get_preload_webp_url( $match['url'] );
		}

		return '';
	}

	/**
	 * Extract the first preloadable background from Elementor's JSON settings.
	 */
	private function extract_lcp_background_preload_url_from_settings_json( $json ) {
		if ( ! is_string( $json ) || ! $this->string_may_contain_known_image( $json ) ) {
			return '';
		}

		$data = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return '';
		}

		if ( ! empty( $data['background_slideshow_gallery'] ) && is_array( $data['background_slideshow_gallery'] ) ) {
			$first_slide = reset( $data['background_slideshow_gallery'] );
			if ( is_array( $first_slide ) && ! empty( $first_slide['url'] ) ) {
				return $this->get_preload_webp_url( $first_slide['url'], isset( $first_slide['id'] ) ? (int) $first_slide['id'] : 0 );
			}
		}

		if ( ! empty( $data['background_image'] ) && is_array( $data['background_image'] ) && ! empty( $data['background_image']['url'] ) ) {
			return $this->get_preload_webp_url( $data['background_image']['url'], isset( $data['background_image']['id'] ) ? (int) $data['background_image']['id'] : 0 );
		}

		return '';
	}

	private function replace_webp_urls_in_value( $value ) {
		if ( is_string( $value ) ) {
			$replacement = $this->get_webp_replacement_for_url( $value );
			return $replacement ? $replacement : $value;
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( ! empty( $value['id'] ) && ! empty( $value['url'] ) ) {
			$metadata = wp_get_attachment_metadata( (int) $value['id'] );
			$replacement = $this->get_webp_replacement_for_url( $value['url'], (int) $value['id'], $metadata );

			if ( $replacement ) {
				$value['url'] = $replacement;
			}

			return $value;
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = $this->replace_webp_urls_in_value( $item );
		}

		return $value;
	}

	/**
	 * Decode Elementor JSON payloads and replace image URLs via attachment metadata.
	 *
	 * This is intentionally metadata-aware: original JPG/PNG URLs become the
	 * configured generated WebP size (for example 1440 wide), not just full.webp.
	 */
	private function replace_json_image_urls_with_webp( $json ) {
		if ( ! $this->string_may_contain_known_image( $json ) ) {
			return $json;
		}

		$data = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return $this->replace_upload_image_urls_with_webp( $json );
		}

		$updated_data = $this->replace_image_urls_in_payload( $data );
		if ( $updated_data === $data ) {
			return $json;
		}

		$encoded = wp_json_encode( $updated_data );

		return is_string( $encoded ) ? $encoded : $json;
	}

	/**
	 * Recursively replace string URLs in Elementor decoded JSON settings.
	 */
	private function replace_image_urls_in_payload( $value ) {
		if ( is_string( $value ) ) {
			return $this->replace_single_payload_image_url( $value );
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( ! empty( $value['url'] ) && is_string( $value['url'] ) ) {
			$attachment_id = ! empty( $value['id'] ) ? (int) $value['id'] : 0;
			$value['url'] = $this->replace_single_payload_image_url( $value['url'], $attachment_id );
		}

		foreach ( $value as $key => $item ) {
			if ( 'url' === $key ) {
				continue;
			}

			$value[ $key ] = $this->replace_image_urls_in_payload( $item );
		}

		return $value;
	}

	/**
	 * Resolve one JSON payload URL to the best generated WebP URL.
	 */
	private function replace_single_payload_image_url( $url, $attachment_id = 0 ) {
		if ( ! $this->string_may_contain_known_image( $url ) ) {
			return $url;
		}

		$replacement = $this->get_webp_replacement_for_url( $url, $attachment_id );
		if ( $replacement ) {
			return $replacement;
		}

		return $this->replace_image_extension_with_webp( $url );
	}

	/**
	 * Fallback for malformed JSON: replace uploads URLs with metadata-aware WebP URLs.
	 */
	private function replace_upload_image_urls_with_webp( $value ) {
		if ( ! $this->string_may_contain_known_image( $value ) ) {
			return $value;
		}

		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return $this->replace_image_extensions_with_webp( $value );
		}

		$base_url = preg_quote( $uploads['baseurl'], '~' );
		$base_url_escaped = preg_quote( str_replace( '/', '\\/', $uploads['baseurl'] ), '~' );
		$base_path = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
		$patterns = [ $base_url, $base_url_escaped ];

		if ( $base_path ) {
			$patterns[] = preg_quote( $base_path, '~' );
			$patterns[] = preg_quote( str_replace( '/', '\\/', $base_path ), '~' );
		}

		$pattern = '~(?P<url>(?:' . implode( '|', array_unique( array_filter( $patterns ) ) ) . ')[^\\s\\\'"<>)]*\\.(?:jpe?g|png|webp|avif)(?:\\?[^\\s\\\'"<>)]*)?)~i';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$url = str_replace( '\\/', '/', $matches['url'] );
				$replacement = $this->get_webp_replacement_for_url( $url );

				if ( ! $replacement ) {
					$replacement = $this->replace_image_extension_with_webp( $url );
				}

				return false !== strpos( $matches['url'], '\\/' ) ? str_replace( '/', '\\/', $replacement ) : $replacement;
			},
			$value
		);
	}

	/**
	 * Cheap string guard used before regex-based extension replacements.
	 */
	private function string_may_contain_convertible_image( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$lower = strtolower( $value );

		return false !== strpos( $lower, '.jpg' ) || false !== strpos( $lower, '.jpeg' ) || false !== strpos( $lower, '.png' );
	}

	/**
	 * Cheap guard for payloads that may already contain WebP URLs.
	 */
	private function string_may_contain_known_image( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$lower = strtolower( $value );

		return $this->string_may_contain_convertible_image( $lower ) || false !== strpos( $lower, '.webp' ) || false !== strpos( $lower, '.avif' );
	}

	/**
	 * Rewrite every JPG/JPEG/PNG extension in a string to WebP.
	 */
	private function replace_image_extensions_with_webp( $value ) {
		if ( ! $this->string_may_contain_convertible_image( $value ) ) {
			return $value;
		}

		return preg_replace( '~\.(?:jpe?g|png)(?=([?#&\\\\/"\'\s<>)])|$)~i', '.webp', $value );
	}

	/**
	 * Rewrite a single image URL extension to WebP while preserving query/hash.
	 */
	private function replace_image_extension_with_webp( $url ) {
		if ( ! $this->string_may_contain_convertible_image( $url ) ) {
			return $url;
		}

		return preg_replace( '~\.(?:jpe?g|png)(?=([?#&\\\\/"\'\s<>)])|$)~i', '.webp', $url, 1 );
	}

	private function get_webp_replacement_for_url( $url, $attachment_id = 0, $metadata = null ) {
		if ( ! is_string( $url ) || '' === $url || ! $this->string_may_contain_known_image( $url ) ) {
			return false;
		}

		$cache_key = $attachment_id . '|' . $url;
		if ( array_key_exists( $cache_key, $this->url_replacement_cache ) ) {
			return $this->url_replacement_cache[ $cache_key ];
		}

		$query = '';
		$query_pos = strpos( $url, '?' );
		if ( false !== $query_pos ) {
			$query = substr( $url, $query_pos );
			$url = substr( $url, 0, $query_pos );
		}

		$clean_url = html_entity_decode( $url, ENT_QUOTES );

		if ( ! $attachment_id ) {
			$attachment_id = $this->resolve_attachment_id_from_upload_url( $clean_url );
		}

		if ( ! $attachment_id ) {
			$this->url_replacement_cache[ $cache_key ] = false;
			return false;
		}

		if ( null === $metadata ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
		}

		if ( empty( $metadata ) || ! is_array( $metadata ) ) {
			$this->url_replacement_cache[ $cache_key ] = false;
			return false;
		}

		$path = wp_parse_url( $clean_url, PHP_URL_PATH );
		$basename = $path ? basename( $path ) : basename( $clean_url );
		$source = false;

		if (
			( ! empty( $metadata['file'] ) && $this->metadata_file_matches_basename( $metadata['file'], $basename ) )
			|| ( ! empty( $metadata[ self::WEBP_FULL_METADATA_KEY ]['file'] ) && $this->metadata_file_matches_basename( $metadata[ self::WEBP_FULL_METADATA_KEY ]['file'], $basename ) )
			|| ( ! empty( $metadata[ self::AVIF_FULL_METADATA_KEY ]['file'] ) && $this->metadata_file_matches_basename( $metadata[ self::AVIF_FULL_METADATA_KEY ]['file'], $basename ) )
		) {
			$source = $this->get_full_replacement_preferred_source( $metadata );
		}

		if ( ! $source && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $slug => $size_data ) {
				if ( ! $this->is_webp_enabled_size( $slug ) ) {
					continue;
				}

				if ( ! empty( $size_data['file'] ) && $this->metadata_file_matches_basename( $size_data['file'], $basename ) ) {
					$source = $this->get_preferred_source_for_size( $metadata, $slug );
					break;
				}

				if ( ! empty( $size_data['sources'] ) && is_array( $size_data['sources'] ) ) {
					foreach ( $size_data['sources'] as $source_data ) {
						if ( ! empty( $source_data['file'] ) && $this->metadata_file_matches_basename( $source_data['file'], $basename ) ) {
							$source = $this->get_preferred_source_for_size( $metadata, $slug );
							break 2;
						}
					}
				}
			}
		}

		if ( ! $source || empty( $source['file'] ) || ! $this->metadata_file_exists( $metadata, $source['file'] ) ) {
			$this->url_replacement_cache[ $cache_key ] = false;
			return false;
		}

		$replacement = $this->build_metadata_file_url( $metadata, $source['file'] );
		if ( $replacement && $query ) {
			$replacement .= $query;
		}

		if ( $replacement && html_entity_decode( $replacement, ENT_QUOTES ) === $clean_url . $query ) {
			$replacement = false;
		}

		$this->url_replacement_cache[ $cache_key ] = $replacement ? $replacement : false;

		return $this->url_replacement_cache[ $cache_key ];
	}

	private function get_best_webp_source_for_container( $metadata, $target_width, $target_height = 0, $fit = 'width', $preferred_format = '' ) {
		if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return false;
		}

		$best_fit = false;
		$best_fit_area = PHP_INT_MAX;
		$largest = false;
		$largest_area = 0;
		$enabled_sizes = $this->get_webp_enabled_sizes();
		$fit = 'cover' === $fit ? 'cover' : 'width';

		foreach ( $enabled_sizes as $slug => $enabled ) {
			if ( empty( $enabled ) || empty( $metadata['sizes'][ $slug ] ) ) {
				continue;
			}

			$source = $this->get_preferred_source_for_size( $metadata, $slug, $preferred_format );
			if ( ! $source || empty( $source['file'] ) || ! $this->metadata_file_exists( $metadata, $source['file'] ) ) {
				continue;
			}

			$source_width = isset( $source['width'] ) ? (int) $source['width'] : 0;
			if ( $source_width < 1 ) {
				continue;
			}

			$source_height = isset( $source['height'] ) ? (int) $source['height'] : 0;
			$source_area = $source_width * max( 1, $source_height );
			$fits_container = $source_width >= $target_width;

			if ( 'cover' === $fit && $target_height > 0 && $source_height > 0 ) {
				$fits_container = $fits_container && $source_height >= $target_height;
			}

			if ( $fits_container && $source_area < $best_fit_area ) {
				$best_fit = $source;
				$best_fit_area = $source_area;
			}

			if ( $source_area > $largest_area ) {
				$largest = $source;
				$largest_area = $source_area;
			}
		}

		return $best_fit ? $best_fit : $largest;
	}

	private function is_uploads_url( $url ) {
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) || ! is_string( $url ) ) {
			return false;
		}

		$clean_url = html_entity_decode( $url, ENT_QUOTES );
		$base_url = $uploads['baseurl'];
		$base_path = wp_parse_url( $base_url, PHP_URL_PATH );

		if ( 0 === strpos( $clean_url, $base_url ) ) {
			return true;
		}

		$protocol_relative_base = preg_replace( '~^https?:~i', '', $base_url );
		if ( $protocol_relative_base && 0 === strpos( $clean_url, $protocol_relative_base ) ) {
			return true;
		}

		return $base_path && 0 === strpos( $clean_url, $base_path );
	}

	private function resolve_attachment_id_from_upload_url( $url ) {
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return 0;
		}

		$absolute_url = $url;
		if ( 0 === strpos( $url, '//' ) ) {
			$scheme = is_ssl() ? 'https:' : 'http:';
			$absolute_url = $scheme . $url;
		} elseif ( 0 === strpos( $url, '/' ) ) {
			$base_parts = wp_parse_url( $uploads['baseurl'] );
			if ( ! empty( $base_parts['scheme'] ) && ! empty( $base_parts['host'] ) ) {
				$absolute_url = $base_parts['scheme'] . '://' . $base_parts['host'] . $url;
			}
		}

		$attachment_id = attachment_url_to_postid( $absolute_url );
		if ( $attachment_id ) {
			return (int) $attachment_id;
		}

		$path = wp_parse_url( $absolute_url, PHP_URL_PATH );
		$basename = $path ? basename( $path ) : basename( $absolute_url );
		if ( '' === $basename ) {
			return 0;
		}

		$attachment_ids = $this->query_attachment_ids_by_metadata_value( $basename );
		if ( empty( $attachment_ids ) && in_array( strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) ), [ 'webp', 'avif' ], true ) ) {
			$attachment_ids = $this->query_attachment_ids_by_metadata_value( pathinfo( $basename, PATHINFO_FILENAME ) );
		}

		if ( empty( $attachment_ids ) ) {
			return 0;
		}

		foreach ( $attachment_ids as $maybe_attachment_id ) {
			$metadata = wp_get_attachment_metadata( $maybe_attachment_id );
			if ( empty( $metadata ) || ! is_array( $metadata ) ) {
				continue;
			}

			if ( $this->metadata_contains_image_basename( $metadata, $basename ) ) {
				return (int) $maybe_attachment_id;
			}
		}

		return 0;
	}

	private function query_attachment_ids_by_metadata_value( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return [];
		}

		$query = new WP_Query( [
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => 20,
			'fields' => 'ids',
			'no_found_rows' => true,
			'suppress_filters' => true,
			'meta_query' => [
				[
					'key' => '_wp_attachment_metadata',
					'value' => $value,
					'compare' => 'LIKE',
				],
			],
		] );

		return ! empty( $query->posts ) ? array_map( 'intval', $query->posts ) : [];
	}

	private function metadata_contains_image_basename( $metadata, $basename ) {
		if ( empty( $metadata ) || ! is_array( $metadata ) || '' === $basename ) {
			return false;
		}

		if ( ! empty( $metadata['file'] ) && $this->metadata_file_matches_basename( $metadata['file'], $basename ) ) {
			return true;
		}

		if ( ! empty( $metadata[ self::WEBP_FULL_METADATA_KEY ]['file'] ) && $this->metadata_file_matches_basename( $metadata[ self::WEBP_FULL_METADATA_KEY ]['file'], $basename ) ) {
			return true;
		}

		if ( ! empty( $metadata[ self::AVIF_FULL_METADATA_KEY ]['file'] ) && $this->metadata_file_matches_basename( $metadata[ self::AVIF_FULL_METADATA_KEY ]['file'], $basename ) ) {
			return true;
		}

		if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return false;
		}

		foreach ( $metadata['sizes'] as $size_data ) {
			if ( ! empty( $size_data['file'] ) && $this->metadata_file_matches_basename( $size_data['file'], $basename ) ) {
				return true;
			}

			if ( empty( $size_data['sources'] ) || ! is_array( $size_data['sources'] ) ) {
				continue;
			}

			foreach ( $size_data['sources'] as $source_data ) {
				if ( ! empty( $source_data['file'] ) && $this->metadata_file_matches_basename( $source_data['file'], $basename ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function metadata_file_matches_basename( $metadata_file, $requested_basename ) {
		if ( ! is_string( $metadata_file ) || '' === $metadata_file || '' === $requested_basename ) {
			return false;
		}

		$metadata_basename = basename( $metadata_file );
		if ( strtolower( $metadata_basename ) === strtolower( $requested_basename ) ) {
			return true;
		}

		if ( ! in_array( strtolower( pathinfo( $requested_basename, PATHINFO_EXTENSION ) ), [ 'webp', 'avif' ], true ) ) {
			return false;
		}

		return strtolower( pathinfo( $metadata_basename, PATHINFO_FILENAME ) ) === strtolower( pathinfo( $requested_basename, PATHINFO_FILENAME ) );
	}

	private function resolve_webp_size_slug( $size, $metadata ) {
		if ( is_string( $size ) ) {
			return $this->is_webp_enabled_size( $size ) ? $size : '';
		}

		if ( ! is_array( $size ) || count( $size ) < 2 || empty( $metadata['sizes'] ) ) {
			return '';
		}

		$requested_width = absint( $size[0] );
		$requested_height = absint( $size[1] );

		foreach ( $metadata['sizes'] as $slug => $size_data ) {
			if ( ! $this->is_webp_enabled_size( $slug ) ) {
				continue;
			}

			if ( isset( $size_data['width'], $size_data['height'] ) && (int) $size_data['width'] === $requested_width && (int) $size_data['height'] === $requested_height ) {
				return $slug;
			}
		}

		return '';
	}

	private function get_webp_enabled_sizes() {
		if ( null !== $this->webp_enabled_sizes ) {
			return $this->webp_enabled_sizes;
		}

		$this->webp_enabled_sizes = [];
		$custom_sizes = get_option( 'marrison_addon_image_sizes', [] );

		if ( ! empty( $custom_sizes ) && is_array( $custom_sizes ) ) {
			foreach ( $custom_sizes as $size ) {
				if ( ! empty( $size['slug'] ) && ( ! empty( $size['webp'] ) || ! empty( $size['avif'] ) ) ) {
					$this->webp_enabled_sizes[ $size['slug'] ] = true;
				}
			}
		}

		foreach ( $this->get_registered_webp_size_definitions() as $slug => $size_data ) {
			$this->webp_enabled_sizes[ $slug ] = true;
		}

		return $this->webp_enabled_sizes;
	}

	private function is_webp_enabled_size( $slug ) {
		$enabled_sizes = $this->get_webp_enabled_sizes();
		return is_string( $slug ) && isset( $enabled_sizes[ $slug ] );
	}

	private function get_webp_source_for_size( $metadata, $slug ) {
		return $this->get_source_for_size_by_mime_type( $metadata, $slug, 'image/webp' );
	}

	private function get_avif_source_for_size( $metadata, $slug ) {
		return $this->get_source_for_size_by_mime_type( $metadata, $slug, 'image/avif' );
	}

	private function get_preferred_source_for_size( $metadata, $slug, $preferred_format = '' ) {
		foreach ( $this->get_preferred_output_mime_types( $preferred_format ) as $mime_type ) {
			$source = $this->get_source_for_size_by_mime_type( $metadata, $slug, $mime_type );
			if ( $source ) {
				return $source;
			}
		}

		return false;
	}

	private function get_source_for_size_by_mime_type( $metadata, $slug, $mime_type ) {
		if ( empty( $metadata['sizes'][ $slug ]['sources'] ) ) {
			return false;
		}

		$sources = $metadata['sizes'][ $slug ]['sources'];
		if ( isset( $sources[ $mime_type ]['file'] ) ) {
			return $sources[ $mime_type ];
		}

		foreach ( $sources as $source ) {
			$source_mime_type = isset( $source['mime_type'] ) ? $source['mime_type'] : ( isset( $source['mime-type'] ) ? $source['mime-type'] : '' );
			if ( $mime_type === $source_mime_type && ! empty( $source['file'] ) ) {
				return $source;
			}
		}

		return false;
	}

	private function get_full_webp_source( $metadata ) {
		return ! empty( $metadata[ self::WEBP_FULL_METADATA_KEY ]['file'] ) ? $metadata[ self::WEBP_FULL_METADATA_KEY ] : false;
	}

	private function get_full_avif_source( $metadata ) {
		return ! empty( $metadata[ self::AVIF_FULL_METADATA_KEY ]['file'] ) ? $metadata[ self::AVIF_FULL_METADATA_KEY ] : false;
	}

	private function get_full_preferred_source( $metadata, $preferred_format = '' ) {
		foreach ( $this->get_preferred_output_mime_types( $preferred_format ) as $mime_type ) {
			if ( 'image/avif' === $mime_type ) {
				$source = $this->get_full_avif_source( $metadata );
			} else {
				$source = $this->get_full_webp_source( $metadata );
			}

			if ( $source ) {
				return $source;
			}
		}

		return false;
	}

	private function get_full_replacement_webp_source( $metadata ) {
		$fallback_slug = $this->get_full_replacement_size_slug();

		if ( $fallback_slug && ! empty( $metadata['sizes'][ $fallback_slug ] ) ) {
			$source = $this->get_webp_source_for_size( $metadata, $fallback_slug );
			if ( $source ) {
				return $source;
			}
		}

		return $this->get_full_webp_source( $metadata );
	}

	private function get_full_replacement_preferred_source( $metadata ) {
		$fallback_slug = $this->get_full_replacement_size_slug();

		if ( $fallback_slug && ! empty( $metadata['sizes'][ $fallback_slug ] ) ) {
			$source = $this->get_preferred_source_for_size( $metadata, $fallback_slug );
			if ( $source ) {
				return $source;
			}
		}

		return $this->get_full_preferred_source( $metadata );
	}

	private function get_preferred_output_mime_types( $preferred_format = '' ) {
		$mime_types = [];

		if ( 'avif' === $preferred_format || ( '' === $preferred_format && $this->client_prefers_avif() ) ) {
			$mime_types[] = 'image/avif';
		}

		$mime_types[] = 'image/webp';

		return $mime_types;
	}

	private function client_prefers_avif() {
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) ) : '';

		return false !== strpos( $accept, 'image/avif' );
	}

	private function maybe_send_vary_accept_header() {
		if ( headers_sent() || ! $this->client_prefers_avif() ) {
			return;
		}

		header( 'Vary: Accept', false );
	}

	private function get_full_replacement_size_slug() {
		$slug = get_option( self::FULL_REPLACEMENT_SIZE_OPTION, '' );
		$slug = is_string( $slug ) ? sanitize_key( $slug ) : '';

		if ( '' === $slug || ! $this->is_webp_enabled_size( $slug ) ) {
			return '';
		}

		return $slug;
	}

	private function get_full_replacement_size_choices( $all_sizes = null ) {
		$choices = [];

		if ( null === $all_sizes ) {
			$all_sizes = wp_get_registered_image_subsizes();
		}

		foreach ( $this->get_webp_enabled_sizes() as $slug => $enabled ) {
			if ( empty( $enabled ) || empty( $all_sizes[ $slug ] ) ) {
				continue;
			}

			$size_data = $all_sizes[ $slug ];
			$label = isset( $size_data['width'], $size_data['height'] ) ? $slug . ' - ' . $size_data['width'] . ' x ' . $size_data['height'] . ' px' : $slug;
			$choices[ $slug ] = $label;
		}

		return $choices;
	}

	private function build_metadata_file_url( $metadata, $filename ) {
		if ( empty( $metadata['file'] ) || empty( $filename ) ) {
			return '';
		}

		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return '';
		}

		$relative_dir = dirname( $metadata['file'] );
		$relative_file = '.' === $relative_dir ? $filename : trailingslashit( $relative_dir ) . $filename;

		return trailingslashit( $uploads['baseurl'] ) . str_replace( '\\', '/', $relative_file );
	}

	private function build_metadata_file_path( $metadata, $filename ) {
		if ( empty( $metadata['file'] ) || empty( $filename ) ) {
			return '';
		}

		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return '';
		}

		$relative_dir = dirname( $metadata['file'] );
		$relative_file = '.' === $relative_dir ? $filename : trailingslashit( $relative_dir ) . $filename;

		return trailingslashit( $uploads['basedir'] ) . str_replace( '\\', '/', $relative_file );
	}

	private function metadata_file_exists( $metadata, $filename ) {
		$path = $this->build_metadata_file_path( $metadata, $filename );
		return $path && file_exists( $path );
	}

	private function size_definitions_include_format( $size_definitions, $format ) {
		if ( empty( $size_definitions ) || ! is_array( $size_definitions ) ) {
			return false;
		}

		foreach ( $size_definitions as $size_data ) {
			if ( ! empty( $size_data[ $format ] ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_full_webp_quality( $webp_sizes ) {
		$quality = 85;

		foreach ( $webp_sizes as $size_data ) {
			if ( isset( $size_data['webp_quality'] ) ) {
				$quality = max( $quality, (int) $size_data['webp_quality'] );
			}
		}

		return max( 0, min( 100, $quality ) );
	}

	private function get_full_avif_quality( $webp_sizes ) {
		$quality = 75;

		foreach ( $webp_sizes as $size_data ) {
			if ( isset( $size_data['avif_quality'] ) ) {
				$quality = max( $quality, (int) $size_data['avif_quality'] );
			}
		}

		return max( 0, min( 100, $quality ) );
	}

	private function get_registered_webp_size_definitions() {
		$definitions = [];
		$settings = get_option( self::REGISTERED_WEBP_OPTION, [] );

		if ( empty( $settings ) || ! is_array( $settings ) ) {
			return $definitions;
		}

		$all_sizes = wp_get_registered_image_subsizes();
		$custom_slugs = $this->get_custom_size_slugs();
		$disabled_sizes = get_option( 'marrison_addon_disabled_sizes', [] );

		foreach ( $settings as $slug => $setting ) {
			$slug = sanitize_key( $slug );

			if ( isset( $custom_slugs[ $slug ] ) || ( empty( $setting['enabled'] ) && empty( $setting['avif_enabled'] ) ) || empty( $all_sizes[ $slug ] ) || ! empty( $disabled_sizes[ $slug ] ) ) {
				continue;
			}

			$size_data = $all_sizes[ $slug ];
			if ( empty( $size_data['width'] ) || empty( $size_data['height'] ) ) {
				continue;
			}

			$quality = isset( $setting['quality'] ) ? absint( $setting['quality'] ) : 85;
			$avif_quality = isset( $setting['avif_quality'] ) ? absint( $setting['avif_quality'] ) : 75;

			$definitions[ $slug ] = [
				'width' => (int) $size_data['width'],
				'height' => (int) $size_data['height'],
				'crop' => ! empty( $size_data['crop'] ),
				'webp' => ! empty( $setting['enabled'] ),
				'webp_quality' => max( 0, min( 100, $quality ) ),
				'avif' => ! empty( $setting['avif_enabled'] ),
				'avif_quality' => max( 0, min( 100, $avif_quality ) ),
			];
		}

		return $definitions;
	}

	private function get_custom_size_slugs( $sizes = null ) {
		$slugs = [];

		if ( null === $sizes ) {
			$sizes = get_option( 'marrison_addon_image_sizes', [] );
		}

		if ( ! empty( $sizes ) && is_array( $sizes ) ) {
			foreach ( $sizes as $size ) {
				if ( ! empty( $size['slug'] ) ) {
					$slugs[ $size['slug'] ] = true;
				}
			}
		}

		return $slugs;
	}

	private function should_serve_webp_on_frontend() {
		if ( empty( $this->get_webp_enabled_sizes() ) ) {
			return false;
		}

		if ( class_exists( 'Marrison_Addon_Context' ) ) {
			return Marrison_Addon_Context::is_public_frontend_request();
		}

		return ! is_admin() && ! wp_doing_ajax();
	}

	public function maybe_clear_elementor_css_cache() {
		if ( empty( $this->get_webp_enabled_sizes() ) ) {
			return;
		}

		$current_version = defined( 'Marrison_Addon::VERSION' ) ? Marrison_Addon::VERSION : '1';
		if ( get_option( self::ELEMENTOR_CSS_CACHE_VERSION_OPTION ) === $current_version ) {
			return;
		}

		$this->clear_elementor_css_cache();
	}

	private function clear_elementor_css_cache() {
		$cleared = false;

		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) && method_exists( \Elementor\Plugin::$instance->files_manager, 'clear_cache' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
			$cleared = true;
		}

		if ( $cleared ) {
			$current_version = defined( 'Marrison_Addon::VERSION' ) ? Marrison_Addon::VERSION : '1';
			update_option( self::ELEMENTOR_CSS_CACHE_VERSION_OPTION, $current_version, false );
		}
	}


	/**
	 * Force generation of sizes with upscaling enabled.
	 */
	public function force_upscale_sizes( $sizes ) {
		$custom_sizes = get_option( 'marrison_addon_image_sizes', [] );
		
		if ( ! empty( $custom_sizes ) && is_array( $custom_sizes ) ) {
			foreach ( $custom_sizes as $size ) {
				if ( isset( $size['upscale'] ) && $size['upscale'] && ! empty( $size['slug'] ) ) {
					// Ensure this size is in the list to be generated
					$sizes[ $size['slug'] ] = [
						'width' => (int) $size['width'],
						'height' => (int) $size['height'],
						'crop' => isset( $size['crop'] ) && $size['crop'],
					];
				}
			}
		}
		
		return $sizes;
	}

	/**
	 * Force image downsize to return false for sizes with upscaling, 
	 * allowing WordPress to generate the upscaled version.
	 */
	public function force_downsize_upscale( $downsize, $id, $size ) {
		// If size is a string (slug), check if it has upscaling enabled
		if ( is_string( $size ) ) {
			$custom_sizes = get_option( 'marrison_addon_image_sizes', [] );
			
			if ( ! empty( $custom_sizes ) && is_array( $custom_sizes ) ) {
				foreach ( $custom_sizes as $custom_size ) {
					if ( isset( $custom_size['slug'] ) && $custom_size['slug'] === $size && isset( $custom_size['upscale'] ) && $custom_size['upscale'] ) {
						// Return false to force WordPress to generate the image
						return false;
					}
				}
			}
		}
		
		return $downsize;
	}

	/**
	 * Enable upscaling for images smaller than target dimensions.
	 */
	public function enable_upscaling( $payload, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {
		// Check if any custom size has upscaling enabled
		$sizes = get_option( 'marrison_addon_image_sizes', [] );
		
		if ( ! empty( $sizes ) && is_array( $sizes ) ) {
			foreach ( $sizes as $size ) {
				if ( isset( $size['upscale'] ) && $size['upscale'] ) {
					// Check if this size matches the requested dimensions
					$size_w = (int) $size['width'];
					$size_h = (int) $size['height'];
					$size_crop = isset( $size['crop'] ) && $size['crop'];
					$is_crop = (bool) $crop;
					
					// Match by dimensions and crop setting
					if ( $size_w === $dest_w && $size_h === $dest_h && $size_crop === $is_crop ) {
						// Check if original image is smaller than target
						if ( $orig_w < $dest_w || $orig_h < $dest_h ) {
							// Calculate dimensions with upscaling while maintaining aspect ratio
							if ( $crop ) {
								// Hard crop: upscale to fill exactly
								$ratio = max( $dest_w / $orig_w, $dest_h / $orig_h );
								$crop_w = round( $dest_w / $ratio );
								$crop_h = round( $dest_h / $ratio );
								$s_x = floor( ( $orig_w - $crop_w ) / 2 );
								$s_y = floor( ( $orig_h - $crop_h ) / 2 );
								return [ 0, 0, $s_x, $s_y, $dest_w, $dest_h, $crop_w, $crop_h ];
							} else {
								// Soft crop: upscale proportionally to fit within bounds
								$ratio = min( $dest_w / $orig_w, $dest_h / $orig_h );
								$new_w = round( $orig_w * $ratio );
								$new_h = round( $orig_h * $ratio );
								return [ 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h ];
							}
						}
					}
				}
			}
		}
		
		return $payload;
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
		register_setting( 'marrison_addon_image_sizes_group', self::REGISTERED_WEBP_OPTION );
		register_setting( 'marrison_addon_image_sizes_group', self::FULL_REPLACEMENT_SIZE_OPTION );
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
		$registered_webp = get_option( self::REGISTERED_WEBP_OPTION, [] );
		$all_sizes = wp_get_registered_image_subsizes();
		$custom_size_slugs = $this->get_custom_size_slugs( $sizes );
		$full_replacement_size = $this->get_full_replacement_size_slug();
		$full_replacement_choices = $this->get_full_replacement_size_choices( $all_sizes );
		$edit_index = null;
		$editing_size = null;

		if ( isset( $_GET['edit_size'], $_GET['marrison_edit_nonce'] ) ) {
			$requested_edit_index = absint( $_GET['edit_size'] );
			if ( isset( $sizes[ $requested_edit_index ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['marrison_edit_nonce'] ) ), 'marrison_edit_image_size_' . $requested_edit_index ) ) {
				$edit_index = $requested_edit_index;
				$editing_size = $sizes[ $edit_index ];
			}
		}

		$is_editing = is_array( $editing_size );
		$form_values = $is_editing ? $editing_size : [
			'name' => '',
			'slug' => '',
			'width' => '',
			'height' => '',
			'crop' => true,
			'upscale' => false,
			'webp' => false,
			'webp_quality' => 85,
			'avif' => false,
			'avif_quality' => 75,
			'show_in_media' => true,
		];
		?>
		<div class="wrap marrison-admin-page marrison-admin-page-image-sizes">
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

			<!-- Registered Modern Formats Section -->
			<div class="marrison-module-card" style="margin-bottom: 20px;">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title" style="font-size: 1.3em; margin: 0;"><?php echo esc_html__( 'WebP e AVIF per Dimensioni WordPress', 'marrison-addon' ); ?></h2>
				</div>
				<p class="marrison-card-desc" style="margin-bottom: 15px;"><?php echo esc_html__( 'Abilita la conversione WebP e AVIF anche per le dimensioni registrate da WordPress, tema o plugin. AVIF viene servito solo ai browser che lo supportano, con WebP come fallback.', 'marrison-addon' ); ?></p>
				<p style="margin-bottom: 15px;">
					<button type="button" id="marrison-test-avif-btn" class="button button-secondary"><?php echo esc_html__( 'Test supporto server AVIF', 'marrison-addon' ); ?></button>
					<span id="marrison-avif-test-result" class="description" style="margin-left: 8px;"></span>
				</p>

				<form method="post">
					<?php wp_nonce_field( 'marrison_image_sizes_action', 'marrison_nonce' ); ?>
					<input type="hidden" name="marrison_action" value="save_registered_webp">
					<div style="background: #f0f0f1; padding: 12px 14px; border-radius: 4px; margin-bottom: 15px;">
						<label for="full_replacement_size" style="font-weight: 600; display: block; margin-bottom: 6px;"><?php echo esc_html__( 'Formato per URL originali e background dinamici', 'marrison-addon' ); ?></label>
						<select name="full_replacement_size" id="full_replacement_size" class="regular-text">
							<option value=""><?php echo esc_html__( 'Usa originale moderno full-size', 'marrison-addon' ); ?></option>
							<?php foreach ( $full_replacement_choices as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $full_replacement_size, $slug ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="description" style="display: block; margin-top: 6px;"><?php echo esc_html__( 'Quando Elementor stampa un background dinamico in dimensione originale, Marrison userà questo taglio moderno al suo posto. Le opzioni compaiono qui dopo aver abilitato WebP o AVIF per quella dimensione e salvato.', 'marrison-addon' ); ?></span>
					</div>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Slug', 'marrison-addon' ); ?></th>
								<th><?php echo esc_html__( 'Dimensioni', 'marrison-addon' ); ?></th>
								<th><?php echo esc_html__( 'Generata', 'marrison-addon' ); ?></th>
								<th><?php echo esc_html__( 'WebP', 'marrison-addon' ); ?></th>
								<th><?php echo esc_html__( 'Qualità WebP', 'marrison-addon' ); ?></th>
								<th><?php echo esc_html__( 'AVIF', 'marrison-addon' ); ?></th>
								<th><?php echo esc_html__( 'Qualità AVIF', 'marrison-addon' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $all_sizes as $slug => $data ) : ?>
								<?php
								if ( isset( $custom_size_slugs[ $slug ] ) ) {
									continue;
								}

								$is_disabled = isset( $disabled_sizes[ $slug ] ) && $disabled_sizes[ $slug ];
								$setting = isset( $registered_webp[ $slug ] ) && is_array( $registered_webp[ $slug ] ) ? $registered_webp[ $slug ] : [];
								$webp_enabled = ! empty( $setting['enabled'] );
								$quality = isset( $setting['quality'] ) ? absint( $setting['quality'] ) : 85;
								$quality = max( 0, min( 100, $quality ) );
								$avif_enabled = ! empty( $setting['avif_enabled'] );
								$avif_quality = isset( $setting['avif_quality'] ) ? absint( $setting['avif_quality'] ) : 75;
								$avif_quality = max( 0, min( 100, $avif_quality ) );
								$label = isset( $data['width'], $data['height'] ) ? "{$data['width']} x {$data['height']} px" : '-';
								$crop = isset( $data['crop'] ) && $data['crop'] ? ' - ' . __( 'Ritagliata', 'marrison-addon' ) : '';
								?>
								<tr>
									<td><strong><?php echo esc_html( $slug ); ?></strong></td>
									<td><?php echo esc_html( "{$label}{$crop}" ); ?></td>
									<td><?php echo $is_disabled ? esc_html__( 'No', 'marrison-addon' ) : esc_html__( 'Sì', 'marrison-addon' ); ?></td>
									<td>
										<label>
											<input type="checkbox" name="registered_webp[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( $webp_enabled ); ?>>
											<?php echo esc_html__( 'Converti', 'marrison-addon' ); ?>
										</label>
									</td>
									<td>
										<input type="number" name="registered_webp[<?php echo esc_attr( $slug ); ?>][quality]" class="small-text" min="0" max="100" value="<?php echo esc_attr( $quality ); ?>">
									</td>
									<td>
										<label>
											<input type="checkbox" name="registered_webp[<?php echo esc_attr( $slug ); ?>][avif_enabled]" value="1" <?php checked( $avif_enabled ); ?>>
											<?php echo esc_html__( 'Converti', 'marrison-addon' ); ?>
										</label>
									</td>
									<td>
										<input type="number" name="registered_webp[<?php echo esc_attr( $slug ); ?>][avif_quality]" class="small-text" min="0" max="100" value="<?php echo esc_attr( $avif_quality ); ?>">
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p style="margin-bottom: 0;">
						<input type="submit" class="button button-primary" value="<?php echo esc_attr__( 'Salva formati WordPress', 'marrison-addon' ); ?>">
					</p>
				</form>
			</div>

			<div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
				
				<!-- Add New Form -->
				<div class="marrison-module-card" style="width: 300px; flex-shrink: 0;">
					<div class="marrison-card-header">
						<h2 class="marrison-card-title" style="font-size: 1.3em; margin: 0;"><?php echo $is_editing ? esc_html__( 'Modifica Dimensione', 'marrison-addon' ) : esc_html__( 'Aggiungi Nuova', 'marrison-addon' ); ?></h2>
					</div>
					<form method="post" style="margin-top: 15px;">
						<?php wp_nonce_field( 'marrison_image_sizes_action', 'marrison_nonce' ); ?>
						<input type="hidden" name="marrison_action" value="<?php echo $is_editing ? 'update_size' : 'add_size'; ?>">
						<?php if ( $is_editing ) : ?>
							<input type="hidden" name="size_index" value="<?php echo esc_attr( $edit_index ); ?>">
							<p>
								<label style="font-weight: 600; display: block; margin-bottom: 5px;"><?php echo esc_html__( 'Slug', 'marrison-addon' ); ?></label>
								<code><?php echo esc_html( $form_values['slug'] ); ?></code>
								<span class="description" style="display: block; margin-top: 5px;"><?php echo esc_html__( 'Lo slug resta invariato per non rompere le assegnazioni già usate in Elementor, gallery e template.', 'marrison-addon' ); ?></span>
							</p>
						<?php endif; ?>
						
						<p>
							<label for="size_name" style="font-weight: 600; display: block; margin-bottom: 5px;"><?php echo esc_html__( 'Nome Visualizzato', 'marrison-addon' ); ?></label>
							<input type="text" name="size_name" id="size_name" class="widefat" value="<?php echo esc_attr( $form_values['name'] ); ?>" required>
						</p>
						<div style="display: flex; gap: 10px;">
							<p style="flex: 1;">
								<label for="size_width" style="font-weight: 600; display: block; margin-bottom: 5px;"><?php echo esc_html__( 'Larghezza', 'marrison-addon' ); ?></label>
								<input type="number" name="size_width" id="size_width" class="widefat" value="<?php echo esc_attr( $form_values['width'] ); ?>" required min="1">
							</p>
							<p style="flex: 1;">
								<label for="size_height" style="font-weight: 600; display: block; margin-bottom: 5px;"><?php echo esc_html__( 'Altezza', 'marrison-addon' ); ?></label>
								<input type="number" name="size_height" id="size_height" class="widefat" value="<?php echo esc_attr( $form_values['height'] ); ?>" required min="1">
							</p>
						</div>
						<p>
							<label>
								<input type="checkbox" name="size_crop" value="1" <?php checked( ! empty( $form_values['crop'] ) ); ?>>
								<?php echo esc_html__( 'Ritaglia (Hard Crop)', 'marrison-addon' ); ?>
							</label>
						</p>
						<p>
							<label>
								<input type="checkbox" name="size_upscale" value="1" <?php checked( ! empty( $form_values['upscale'] ) ); ?>>
								<?php echo esc_html__( 'Allarga se più piccolo (Upscaling)', 'marrison-addon' ); ?>
							</label>
						</p>
						<p>
							<label>
								<input type="checkbox" name="size_webp" value="1" <?php checked( ! empty( $form_values['webp'] ) ); ?>>
								<?php echo esc_html__( 'Converti in WebP', 'marrison-addon' ); ?>
							</label>
						</p>
						<p id="webp-quality-container" style="display: none;">
							<label for="size_webp_quality" style="font-weight: 600; display: block; margin-bottom: 5px;"><?php echo esc_html__( 'Qualità WebP (0-100)', 'marrison-addon' ); ?></label>
							<input type="number" name="size_webp_quality" id="size_webp_quality" class="widefat" min="0" max="100" value="<?php echo esc_attr( $form_values['webp_quality'] ? $form_values['webp_quality'] : 85 ); ?>">
							<span class="description"><?php echo esc_html__( '85 è un buon equilibrio tra qualità e dimensione file.', 'marrison-addon' ); ?></span>
						</p>
						<p>
							<label>
								<input type="checkbox" name="size_avif" value="1" <?php checked( ! empty( $form_values['avif'] ) ); ?>>
								<?php echo esc_html__( 'Converti in AVIF', 'marrison-addon' ); ?>
							</label>
						</p>
						<p id="avif-quality-container" style="display: none;">
							<label for="size_avif_quality" style="font-weight: 600; display: block; margin-bottom: 5px;"><?php echo esc_html__( 'Qualità AVIF (0-100)', 'marrison-addon' ); ?></label>
							<input type="number" name="size_avif_quality" id="size_avif_quality" class="widefat" min="0" max="100" value="<?php echo esc_attr( ! empty( $form_values['avif_quality'] ) ? $form_values['avif_quality'] : 75 ); ?>">
							<span class="description"><?php echo esc_html__( '75 è un buon punto di partenza per AVIF.', 'marrison-addon' ); ?></span>
						</p>
						<p>
							<label>
								<input type="checkbox" name="size_show_in_media" value="1" <?php checked( ! empty( $form_values['show_in_media'] ) ); ?>>
								<?php echo esc_html__( 'Mostra nel Selettore Media', 'marrison-addon' ); ?>
							</label>
						</p>
						<p style="margin-bottom: 0;">
							<input type="submit" class="button button-primary" value="<?php echo $is_editing ? esc_attr__( 'Salva Modifiche', 'marrison-addon' ) : esc_attr__( 'Aggiungi Dimensione', 'marrison-addon' ); ?>" style="width: 100%;">
							<?php if ( $is_editing ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=marrison_addon_image_sizes' ) ); ?>" class="button button-secondary" style="width: 100%; margin-top: 8px; text-align: center;"><?php echo esc_html__( 'Annulla modifica', 'marrison-addon' ); ?></a>
							<?php endif; ?>
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
									<th><?php echo esc_html__( 'Allarga', 'marrison-addon' ); ?></th>
									<th><?php echo esc_html__( 'WebP', 'marrison-addon' ); ?></th>
									<th><?php echo esc_html__( 'Qualità WebP', 'marrison-addon' ); ?></th>
									<th><?php echo esc_html__( 'AVIF', 'marrison-addon' ); ?></th>
									<th><?php echo esc_html__( 'Qualità AVIF', 'marrison-addon' ); ?></th>
									<th><?php echo esc_html__( 'Nei Media', 'marrison-addon' ); ?></th>
									<th><?php echo esc_html__( 'Azioni', 'marrison-addon' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $sizes ) ) : ?>
									<tr>
										<td colspan="11"><?php echo esc_html__( 'Nessuna dimensione personalizzata registrata.', 'marrison-addon' ); ?></td>
									</tr>
								<?php else : ?>
									<?php foreach ( $sizes as $index => $size ) : ?>
										<tr>
											<td><strong><?php echo esc_html( $size['slug'] ); ?></strong></td>
											<td><?php echo esc_html( $size['name'] ); ?></td>
											<td><?php echo esc_html( $size['width'] . ' x ' . $size['height'] ); ?> px</td>
											<td><?php echo $size['crop'] ? esc_html__( 'Sì', 'marrison-addon' ) : esc_html__( 'No', 'marrison-addon' ); ?></td>
											<td><?php echo isset( $size['upscale'] ) && $size['upscale'] ? esc_html__( 'Sì', 'marrison-addon' ) : esc_html__( 'No', 'marrison-addon' ); ?></td>
											<td><?php echo isset( $size['webp'] ) && $size['webp'] ? esc_html__( 'Sì', 'marrison-addon' ) : esc_html__( 'No', 'marrison-addon' ); ?></td>
											<td><?php echo isset( $size['webp_quality'] ) && $size['webp'] ? esc_html( $size['webp_quality'] ) : '-'; ?></td>
											<td><?php echo isset( $size['avif'] ) && $size['avif'] ? esc_html__( 'Sì', 'marrison-addon' ) : esc_html__( 'No', 'marrison-addon' ); ?></td>
											<td><?php echo isset( $size['avif_quality'] ) && $size['avif'] ? esc_html( $size['avif_quality'] ) : '-'; ?></td>
											<td><?php echo isset( $size['show_in_media'] ) && $size['show_in_media'] ? esc_html__( 'Sì', 'marrison-addon' ) : esc_html__( 'No', 'marrison-addon' ); ?></td>
											<td>
												<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=marrison_addon_image_sizes&edit_size=' . absint( $index ) ), 'marrison_edit_image_size_' . absint( $index ), 'marrison_edit_nonce' ) ); ?>" class="button button-small">
													<?php echo esc_html__( 'Modifica', 'marrison-addon' ); ?>
												</a>
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
			$webp_quality = isset( $_POST['size_webp_quality'] ) ? absint( $_POST['size_webp_quality'] ) : 85;
			$webp_quality = max( 0, min( 100, $webp_quality ) ); // Clamp between 0 and 100
			$avif_quality = isset( $_POST['size_avif_quality'] ) ? absint( $_POST['size_avif_quality'] ) : 75;
			$avif_quality = max( 0, min( 100, $avif_quality ) );
			
			$new_size = [
				'slug' => sanitize_title( $name ),
				'name' => $name,
				'width' => absint( $_POST['size_width'] ),
				'height' => absint( $_POST['size_height'] ),
				'crop' => isset( $_POST['size_crop'] ) ? true : false,
				'upscale' => isset( $_POST['size_upscale'] ) ? true : false,
				'webp' => isset( $_POST['size_webp'] ) ? true : false,
				'webp_quality' => isset( $_POST['size_webp'] ) ? $webp_quality : null,
				'avif' => isset( $_POST['size_avif'] ) ? true : false,
				'avif_quality' => isset( $_POST['size_avif'] ) ? $avif_quality : null,
				'show_in_media' => isset( $_POST['size_show_in_media'] ) ? true : false,
			];

			// Simple validation
			if ( ! empty( $new_size['slug'] ) && ! empty( $new_size['width'] ) ) {
				$sizes[] = $new_size;
				update_option( 'marrison_addon_image_sizes', $sizes );
				$this->webp_enabled_sizes = null;
				$this->clear_elementor_css_cache();
				add_settings_error( 'marrison_messages', 'marrison_size_added', __( 'Image size added.', 'marrison-addon' ), 'updated' );
			}
		} elseif ( 'delete_size' === $action ) {
			$index = absint( $_POST['size_index'] );
			if ( isset( $sizes[ $index ] ) ) {
				unset( $sizes[ $index ] );
				update_option( 'marrison_addon_image_sizes', array_values( $sizes ) ); // Re-index
				$this->webp_enabled_sizes = null;
				$this->clear_elementor_css_cache();
				add_settings_error( 'marrison_messages', 'marrison_size_deleted', __( 'Image size deleted.', 'marrison-addon' ), 'updated' );
			}
		} elseif ( 'update_size' === $action ) {
			$index = isset( $_POST['size_index'] ) ? absint( $_POST['size_index'] ) : -1;

			if ( isset( $sizes[ $index ] ) ) {
				$name = sanitize_text_field( $_POST['size_name'] );
				$webp_quality = isset( $_POST['size_webp_quality'] ) ? absint( $_POST['size_webp_quality'] ) : 85;
				$webp_quality = max( 0, min( 100, $webp_quality ) );
				$avif_quality = isset( $_POST['size_avif_quality'] ) ? absint( $_POST['size_avif_quality'] ) : 75;
				$avif_quality = max( 0, min( 100, $avif_quality ) );

				$updated_size = [
					'slug' => $sizes[ $index ]['slug'],
					'name' => $name,
					'width' => absint( $_POST['size_width'] ),
					'height' => absint( $_POST['size_height'] ),
					'crop' => isset( $_POST['size_crop'] ) ? true : false,
					'upscale' => isset( $_POST['size_upscale'] ) ? true : false,
					'webp' => isset( $_POST['size_webp'] ) ? true : false,
					'webp_quality' => isset( $_POST['size_webp'] ) ? $webp_quality : null,
					'avif' => isset( $_POST['size_avif'] ) ? true : false,
					'avif_quality' => isset( $_POST['size_avif'] ) ? $avif_quality : null,
					'show_in_media' => isset( $_POST['size_show_in_media'] ) ? true : false,
				];

				if ( ! empty( $updated_size['slug'] ) && ! empty( $updated_size['name'] ) && ! empty( $updated_size['width'] ) && ! empty( $updated_size['height'] ) ) {
					$sizes[ $index ] = $updated_size;
					update_option( 'marrison_addon_image_sizes', array_values( $sizes ) );
					$this->webp_enabled_sizes = null;
					$this->clear_elementor_css_cache();
					add_settings_error( 'marrison_messages', 'marrison_size_updated', __( 'Image size updated.', 'marrison-addon' ), 'updated' );
				}
			}
		} elseif ( 'save_registered_webp' === $action ) {
			$submitted_settings = isset( $_POST['registered_webp'] ) && is_array( $_POST['registered_webp'] ) ? wp_unslash( $_POST['registered_webp'] ) : [];
			$all_sizes = wp_get_registered_image_subsizes();
			$custom_slugs = $this->get_custom_size_slugs( $sizes );
			$clean_settings = [];

			foreach ( $all_sizes as $slug => $size_data ) {
				if ( isset( $custom_slugs[ $slug ] ) ) {
					continue;
				}

				$setting = isset( $submitted_settings[ $slug ] ) && is_array( $submitted_settings[ $slug ] ) ? $submitted_settings[ $slug ] : [];
				$enabled = ! empty( $setting['enabled'] ) ? 1 : 0;
				$quality = isset( $setting['quality'] ) ? absint( $setting['quality'] ) : 85;
				$quality = max( 0, min( 100, $quality ) );
				$avif_enabled = ! empty( $setting['avif_enabled'] ) ? 1 : 0;
				$avif_quality = isset( $setting['avif_quality'] ) ? absint( $setting['avif_quality'] ) : 75;
				$avif_quality = max( 0, min( 100, $avif_quality ) );

				if ( $enabled || $avif_enabled ) {
					$clean_settings[ sanitize_key( $slug ) ] = [
						'enabled' => $enabled,
						'quality' => $quality,
						'avif_enabled' => $avif_enabled,
						'avif_quality' => $avif_quality,
					];
				}
			}

			update_option( self::REGISTERED_WEBP_OPTION, $clean_settings );
			$this->webp_enabled_sizes = null;

			$full_replacement_size = isset( $_POST['full_replacement_size'] ) ? sanitize_key( wp_unslash( $_POST['full_replacement_size'] ) ) : '';
			if ( '' !== $full_replacement_size && ! $this->is_webp_enabled_size( $full_replacement_size ) ) {
				$full_replacement_size = '';
			}

			update_option( self::FULL_REPLACEMENT_SIZE_OPTION, $full_replacement_size );
			$this->clear_elementor_css_cache();
			add_settings_error( 'marrison_messages', 'marrison_registered_webp_updated', __( 'Impostazioni WebP WordPress aggiornate.', 'marrison-addon' ), 'updated' );
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
