<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Video_Thumbnail {

	private const VERSION = '1.1.0';
	private const OPTION_NAME = 'marrison_video_thumbnail_settings';
	private const META_LOCAL_COVER_ID = '_mvt_local_cover_id';
	private const META_LOCAL_COVER_ERROR = '_mvt_local_cover_error';
	private const META_SOURCE_VIDEO_ID = '_mvt_source_video_id';
	private const META_CAPTURE_SECOND = '_mvt_capture_second';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_mvt_get_thumbnails', array( $this, 'ajax_get_thumbnails' ) );
		add_action( 'wp_ajax_mvt_import_thumbnail', array( $this, 'ajax_import_thumbnail' ) );
		add_action( 'add_attachment', array( $this, 'maybe_generate_local_video_cover' ), 20 );
	}

	public function add_admin_page() {
		add_submenu_page(
			'marrison_addon_panel',
			esc_html__( 'Video Thumbnail', 'marrison-addon' ),
			esc_html__( 'Video Thumbnail', 'marrison-addon' ),
			'upload_files',
			'marrison-video-thumb',
			array( $this, 'render_admin_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'marrison_addon_video_thumbnail_group',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_default_settings(),
			)
		);
	}

	public function enqueue_assets( $hook ) {
		if ( ! $this->is_video_thumbnail_admin_page() ) {
			return;
		}

		$base_path = plugin_dir_path( __FILE__ ) . 'video-thumbnail/assets/';
		$base_url = plugins_url( 'includes/modules/video-thumbnail/assets/', dirname( dirname( dirname( __FILE__ ) ) ) . '/marrison-addon.php' );

		wp_enqueue_style( 'mvt-style', $base_url . 'style.css', array(), filemtime( $base_path . 'style.css' ) );
		wp_enqueue_script( 'mvt-script', $base_url . 'script.js', array( 'jquery' ), filemtime( $base_path . 'script.js' ), true );
		wp_localize_script(
			'mvt-script',
			'mvtData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mvt_nonce' ),
			)
		);
	}

	private function is_video_thumbnail_admin_page() {
		if ( isset( $_GET['page'] ) && 'marrison-video-thumb' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return true;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( $screen && false !== strpos( (string) $screen->id, 'marrison-video-thumb' ) ) {
				return true;
			}
		}

		return false;
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$settings = $this->get_settings();
		$ffmpeg_status = $this->get_ffmpeg_status();
		$destination_options = $this->get_destination_options();
		?>
		<div class="wrap mvt-wrap marrison-admin-page marrison-admin-page-video-thumbnail">
			<h1><?php esc_html_e( 'Marrison Video Thumbnail', 'marrison-addon' ); ?></h1>
			<p><?php esc_html_e( 'Importa miniature YouTube e genera cover automatiche per video locali caricati nella Libreria media.', 'marrison-addon' ); ?></p>

			<div class="mvt-section marrison-module-card">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title"><?php esc_html_e( 'YouTube', 'marrison-addon' ); ?></h2>
				</div>
				<p class="marrison-card-desc"><?php esc_html_e( 'Incolla un URL YouTube per recuperare le miniature disponibili.', 'marrison-addon' ); ?></p>

				<div class="mvt-form">
					<input type="text" id="mvt-url" class="regular-text" placeholder="https://www.youtube.com/watch?v=..." />
					<button id="mvt-fetch" type="button" class="button button-primary"><?php esc_html_e( 'Fetch Thumbnails', 'marrison-addon' ); ?></button>
				</div>

				<div id="mvt-status" class="mvt-status"></div>
				<div id="mvt-results" class="mvt-results"></div>
			</div>

			<div class="mvt-section marrison-module-card">
				<div class="marrison-card-header">
					<h2 class="marrison-card-title"><?php esc_html_e( 'Video locali', 'marrison-addon' ); ?></h2>
				</div>
				<div class="mvt-ffmpeg-status <?php echo $ffmpeg_status['available'] ? 'success' : 'error'; ?>">
					<strong><?php esc_html_e( 'Stato FFmpeg:', 'marrison-addon' ); ?></strong>
					<?php echo esc_html( $ffmpeg_status['message'] ); ?>
				</div>

				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<form method="post" action="options.php" class="mvt-settings-form">
						<?php settings_fields( 'marrison_addon_video_thumbnail_group' ); ?>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Generazione automatica', 'marrison-addon' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[local_video_enabled]" value="1" <?php checked( $settings['local_video_enabled'], true ); ?> />
										<?php esc_html_e( 'Genera una cover quando viene caricato un video MP4 o WebM.', 'marrison-addon' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="mvt-capture-second"><?php esc_html_e( 'Secondo screenshot', 'marrison-addon' ); ?></label>
								</th>
								<td>
									<input type="number" id="mvt-capture-second" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[capture_second]" value="<?php echo esc_attr( $this->format_seconds_label( $settings['capture_second'] ) ); ?>" min="0" max="3600" step="0.1" class="small-text" />
									<p class="description"><?php esc_html_e( 'Default: 1. Puoi usare anche decimali, ad esempio 1.5.', 'marrison-addon' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="mvt-cover-destination"><?php esc_html_e( 'Destinazione cover', 'marrison-addon' ); ?></label>
								</th>
								<td>
									<select id="mvt-cover-destination" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[destination]">
										<?php foreach ( $destination_options as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['destination'], $value ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="mvt-ffmpeg-path"><?php esc_html_e( 'Percorso FFmpeg', 'marrison-addon' ); ?></label>
								</th>
								<td>
									<input type="text" id="mvt-ffmpeg-path" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[ffmpeg_path]" value="<?php echo esc_attr( $settings['ffmpeg_path'] ); ?>" class="regular-text" placeholder="ffmpeg" />
									<p class="description"><?php esc_html_e( 'Lascia vuoto se FFmpeg e disponibile nel PATH del server.', 'marrison-addon' ); ?></p>
								</td>
							</tr>
						</table>

						<?php submit_button( esc_html__( 'Salva impostazioni Video Thumbnail', 'marrison-addon' ) ); ?>
					</form>
				<?php else : ?>
					<p><?php esc_html_e( 'Le impostazioni dei video locali sono disponibili agli amministratori.', 'marrison-addon' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function ajax_get_thumbnails() {
		check_ajax_referer( 'mvt_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';
		$video_id = $this->extract_video_id( $url );

		if ( ! $video_id ) {
			wp_send_json_error( 'Invalid YouTube URL. Check the format.' );
		}

		$video_title = $this->extract_video_title( $url );
		$candidates = array(
			array(
				'url'   => "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg",
				'label' => 'Maximum Resolution (1280 x 720)',
				'w'     => 1280,
				'h'     => 720,
			),
			array(
				'url'   => "https://img.youtube.com/vi/{$video_id}/sddefault.jpg",
				'label' => 'Standard (640 x 480)',
				'w'     => 640,
				'h'     => 480,
			),
		);

		$thumbnails = array();

		foreach ( $candidates as $thumb ) {
			$response = wp_remote_head( $thumb['url'], array( 'timeout' => 10 ) );
			$code = wp_remote_retrieve_response_code( $response );

			if ( 200 === $code && $thumb['w'] >= 640 ) {
				$thumb['video_id'] = $video_id;
				$thumb['video_title'] = $video_title;
				$thumbnails[] = $thumb;
			}
		}

		if ( empty( $thumbnails ) ) {
			wp_send_json_error( 'No thumbnails were found for this video.' );
		}

		wp_send_json_success( array( 'thumbnails' => $thumbnails ) );
	}

	public function ajax_import_thumbnail() {
		check_ajax_referer( 'mvt_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		$video_id = isset( $_POST['video_id'] ) ? sanitize_text_field( wp_unslash( $_POST['video_id'] ) ) : '';
		$video_title = isset( $_POST['video_title'] ) ? sanitize_text_field( wp_unslash( $_POST['video_title'] ) ) : '';

		if ( empty( $image_url ) ) {
			wp_send_json_error( 'Missing image URL.' );
		}

		$this->load_media_dependencies();

		$filename = $this->build_filename_from_title( $video_title, $video_id );
		$tmp = download_url( $image_url );

		if ( is_wp_error( $tmp ) ) {
			wp_send_json_error( 'Unable to download the image: ' . $tmp->get_error_message() );
		}

		$ext = 'jpg';
		$content_type = '';
		$head_response = wp_remote_head( $image_url, array( 'timeout' => 10 ) );

		if ( ! is_wp_error( $head_response ) ) {
			$content_type = wp_remote_retrieve_header( $head_response, 'content-type' );
		}

		if ( false !== strpos( $content_type, 'png' ) ) {
			$ext = 'png';
		} elseif ( false !== strpos( $content_type, 'webp' ) ) {
			$ext = 'webp';
		}

		$file_array = array(
			'name'     => $filename . '.' . $ext,
			'tmp_name' => $tmp,
		);

		if ( ! file_is_valid_image( $tmp ) ) {
			@unlink( $tmp );
			wp_send_json_error( 'The downloaded file is not a valid image.' );
		}

		$attachment_desc = ! empty( $video_title ) ? $video_title : $video_id;
		$attachment_id = media_handle_sideload( $file_array, 0, $attachment_desc );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			wp_send_json_error( 'Import error: ' . $attachment_id->get_error_message() );
		}

		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'edit_url'      => admin_url( 'post.php?post=' . $attachment_id . '&action=edit' ),
				'media_url'     => admin_url( 'upload.php?item=' . $attachment_id ),
			)
		);
	}

	public function maybe_generate_local_video_cover( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id || ! $this->is_supported_local_video( $attachment_id ) ) {
			return;
		}

		$settings = $this->get_settings();

		if ( empty( $settings['local_video_enabled'] ) ) {
			return;
		}

		if ( get_post_meta( $attachment_id, self::META_LOCAL_COVER_ID, true ) ) {
			return;
		}

		$video_path = get_attached_file( $attachment_id );

		if ( empty( $video_path ) || ! file_exists( $video_path ) || ! is_readable( $video_path ) ) {
			$this->store_generation_error( $attachment_id, __( 'File video non leggibile.', 'marrison-addon' ) );
			return;
		}

		$result = $this->generate_local_video_cover( $attachment_id, $video_path, $settings );

		if ( is_wp_error( $result ) ) {
			$this->store_generation_error( $attachment_id, $result->get_error_message() );
			return;
		}

		delete_post_meta( $attachment_id, self::META_LOCAL_COVER_ERROR );
	}

	public function sanitize_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$defaults = $this->get_default_settings();
		$destination = isset( $settings['destination'] ) && is_scalar( $settings['destination'] ) ? sanitize_key( wp_unslash( (string) $settings['destination'] ) ) : $defaults['destination'];
		$destination_options = $this->get_destination_options();
		$ffmpeg_path = isset( $settings['ffmpeg_path'] ) && is_scalar( $settings['ffmpeg_path'] ) ? sanitize_text_field( wp_unslash( (string) $settings['ffmpeg_path'] ) ) : $defaults['ffmpeg_path'];

		return array(
			'local_video_enabled' => ! empty( $settings['local_video_enabled'] ),
			'capture_second'      => $this->normalize_capture_second( isset( $settings['capture_second'] ) ? $settings['capture_second'] : $defaults['capture_second'] ),
			'destination'         => array_key_exists( $destination, $destination_options ) ? $destination : $defaults['destination'],
			'ffmpeg_path'         => trim( $ffmpeg_path, " \t\n\r\0\x0B\"'" ),
		);
	}

	private function generate_local_video_cover( $video_id, $video_path, $settings ) {
		$this->load_media_dependencies();

		$target = $this->get_local_cover_target( $video_id, $video_path, $settings['capture_second'] );

		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$capture_result = $this->capture_video_frame( $video_path, $target['path'], $settings['capture_second'] );

		if ( is_wp_error( $capture_result ) ) {
			@unlink( $target['path'] );
			return $capture_result;
		}

		$image_id = $this->create_cover_attachment( $video_id, $target['path'], $target['url'], $settings['capture_second'] );

		if ( is_wp_error( $image_id ) ) {
			@unlink( $target['path'] );
			return $image_id;
		}

		update_post_meta( $video_id, self::META_LOCAL_COVER_ID, $image_id );
		$this->apply_cover_destination( $video_id, $image_id, $settings['destination'] );

		return $image_id;
	}

	private function capture_video_frame( $video_path, $output_path, $capture_second ) {
		if ( ! function_exists( 'exec' ) ) {
			return new WP_Error( 'mvt_exec_disabled', __( 'La funzione PHP exec non e disponibile sul server.', 'marrison-addon' ) );
		}

		$attempts = array( $this->normalize_capture_second( $capture_second ) );

		if ( $attempts[0] > 0 ) {
			$attempts[] = 0;
		}

		$last_error = '';

		foreach ( $attempts as $second ) {
			if ( file_exists( $output_path ) ) {
				@unlink( $output_path );
			}

			$output = array();
			$exit_code = 0;
			$command = $this->build_ffmpeg_command( $video_path, $output_path, $second );

			@exec( $command, $output, $exit_code );

			if ( 0 === (int) $exit_code && file_exists( $output_path ) && filesize( $output_path ) > 0 && file_is_valid_image( $output_path ) ) {
				return true;
			}

			$last_error = trim( implode( ' ', array_slice( $output, -3 ) ) );
		}

		if ( '' === $last_error ) {
			$last_error = __( 'Nessun dettaglio restituito da FFmpeg.', 'marrison-addon' );
		}

		return new WP_Error(
			'mvt_ffmpeg_failed',
			sprintf(
				/* translators: %s: FFmpeg error output. */
				__( 'FFmpeg non ha generato la cover. %s', 'marrison-addon' ),
				$last_error
			)
		);
	}

	private function build_ffmpeg_command( $video_path, $output_path, $capture_second ) {
		$binary = $this->get_ffmpeg_binary();

		return sprintf(
			'%1$s -hide_banner -loglevel error -y -i %2$s -ss %3$s -map 0:v:0 -frames:v 1 -q:v 2 %4$s 2>&1',
			escapeshellarg( $binary ),
			escapeshellarg( $video_path ),
			escapeshellarg( $this->format_seconds_for_ffmpeg( $capture_second ) ),
			escapeshellarg( $output_path )
		);
	}

	private function create_cover_attachment( $video_id, $image_path, $image_url, $capture_second ) {
		$video_title = get_the_title( $video_id );
		$title = sprintf(
			/* translators: %s: video title. */
			__( '%s - cover', 'marrison-addon' ),
			$video_title ? $video_title : __( 'Video', 'marrison-addon' )
		);

		$attachment = array(
			'guid'           => $image_url,
			'post_mime_type' => 'image/jpeg',
			'post_title'     => wp_strip_all_tags( $title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $video_id,
		);

		$image_id = wp_insert_attachment( $attachment, $image_path, $video_id, true );

		if ( is_wp_error( $image_id ) ) {
			return $image_id;
		}

		$metadata = wp_generate_attachment_metadata( $image_id, $image_path );

		if ( ! is_wp_error( $metadata ) && ! empty( $metadata ) ) {
			wp_update_attachment_metadata( $image_id, $metadata );
		}

		update_post_meta( $image_id, self::META_SOURCE_VIDEO_ID, $video_id );
		update_post_meta( $image_id, self::META_CAPTURE_SECOND, $this->format_seconds_label( $capture_second ) );
		update_post_meta(
			$image_id,
			'_wp_attachment_image_alt',
			sprintf(
				/* translators: %s: video title. */
				__( 'Cover video: %s', 'marrison-addon' ),
				$video_title ? $video_title : __( 'Video', 'marrison-addon' )
			)
		);

		return $image_id;
	}

	private function apply_cover_destination( $video_id, $image_id, $destination ) {
		if ( 'attachment' === $destination ) {
			return;
		}

		if ( 'video_featured' === $destination || 'both_featured' === $destination ) {
			set_post_thumbnail( $video_id, $image_id );
		}

		if ( 'parent_featured' !== $destination && 'both_featured' !== $destination ) {
			return;
		}

		$parent_id = wp_get_post_parent_id( $video_id );

		if ( ! $parent_id || has_post_thumbnail( $parent_id ) ) {
			return;
		}

		$post_type = get_post_type( $parent_id );

		if ( $post_type && post_type_supports( $post_type, 'thumbnail' ) ) {
			set_post_thumbnail( $parent_id, $image_id );
		}
	}

	private function get_local_cover_target( $video_id, $video_path, $capture_second ) {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'mvt_upload_dir_error', $upload_dir['error'] );
		}

		$target_dir = dirname( $video_path );

		if ( ! wp_mkdir_p( $target_dir ) || ! is_writable( $target_dir ) ) {
			return new WP_Error( 'mvt_upload_dir_unwritable', __( 'La cartella upload del video non e scrivibile.', 'marrison-addon' ) );
		}

		$filename = $this->build_local_cover_filename( $video_id, $video_path, $capture_second );
		$filename = wp_unique_filename( $target_dir, $filename );
		$path = trailingslashit( $target_dir ) . $filename;
		$url = $this->get_upload_url_from_path( $path, $upload_dir );

		if ( is_wp_error( $url ) ) {
			return $url;
		}

		return array(
			'path' => $path,
			'url'  => $url,
		);
	}

	private function get_upload_url_from_path( $path, $upload_dir ) {
		$base_dir = trailingslashit( wp_normalize_path( $upload_dir['basedir'] ) );
		$normalized_path = wp_normalize_path( $path );

		if ( 0 !== strpos( strtolower( $normalized_path ), strtolower( $base_dir ) ) ) {
			return new WP_Error( 'mvt_path_outside_uploads', __( 'La cover non puo essere salvata fuori dalla cartella uploads.', 'marrison-addon' ) );
		}

		$relative_path = ltrim( substr( $normalized_path, strlen( $base_dir ) ), '/' );
		$relative_url = implode( '/', array_map( 'rawurlencode', explode( '/', $relative_path ) ) );

		return trailingslashit( $upload_dir['baseurl'] ) . $relative_url;
	}

	private function build_local_cover_filename( $video_id, $video_path, $capture_second ) {
		$base = sanitize_file_name( wp_strip_all_tags( get_the_title( $video_id ) ) );

		if ( '' === $base ) {
			$base = sanitize_file_name( pathinfo( $video_path, PATHINFO_FILENAME ) );
		}

		if ( '' === $base ) {
			$base = 'video-' . absint( $video_id );
		}

		$base = substr( $base, 0, 150 );

		return $base . '-cover-' . $this->format_seconds_for_filename( $capture_second ) . '.jpg';
	}

	private function is_supported_local_video( $attachment_id ) {
		$mime = get_post_mime_type( $attachment_id );
		$supported_mimes = apply_filters(
			'marrison_addon/video_thumbnail/local_video_mimes',
			array(
				'video/mp4',
				'video/webm',
			)
		);

		return in_array( $mime, $supported_mimes, true );
	}

	private function get_ffmpeg_status() {
		if ( ! function_exists( 'exec' ) ) {
			return array(
				'available' => false,
				'message'   => __( 'non disponibile: la funzione PHP exec e disabilitata.', 'marrison-addon' ),
			);
		}

		$output = array();
		$exit_code = 0;
		$command = escapeshellarg( $this->get_ffmpeg_binary() ) . ' -version 2>&1';

		@exec( $command, $output, $exit_code );

		if ( 0 === (int) $exit_code ) {
			return array(
				'available' => true,
				'message'   => __( 'disponibile.', 'marrison-addon' ),
			);
		}

		return array(
			'available' => false,
			'message'   => __( 'non trovato. Inserisci il percorso assoluto di FFmpeg oppure installalo nel PATH del server.', 'marrison-addon' ),
		);
	}

	private function get_ffmpeg_binary() {
		$settings = $this->get_settings();
		$binary = ! empty( $settings['ffmpeg_path'] ) ? $settings['ffmpeg_path'] : 'ffmpeg';

		return apply_filters( 'marrison_addon/video_thumbnail/ffmpeg_path', $binary );
	}

	private function get_settings() {
		$settings = get_option( self::OPTION_NAME, null );

		if ( ! is_array( $settings ) ) {
			return $this->get_default_settings();
		}

		return wp_parse_args( $this->sanitize_settings( $settings ), $this->get_default_settings() );
	}

	private function get_default_settings() {
		return array(
			'local_video_enabled' => true,
			'capture_second'      => 1,
			'destination'         => 'both_featured',
			'ffmpeg_path'         => '',
		);
	}

	private function get_destination_options() {
		return array(
			'video_featured'  => __( 'Allegato + immagine in evidenza del video', 'marrison-addon' ),
			'parent_featured' => __( 'Allegato + immagine in evidenza del contenuto collegato', 'marrison-addon' ),
			'both_featured'   => __( 'Allegato + immagine in evidenza di video e contenuto', 'marrison-addon' ),
			'attachment'      => __( 'Solo allegato immagine in Libreria media', 'marrison-addon' ),
		);
	}

	private function normalize_capture_second( $value ) {
		$value = is_numeric( $value ) ? (float) $value : 1;

		return max( 0, min( 3600, $value ) );
	}

	private function format_seconds_for_ffmpeg( $seconds ) {
		return number_format( $this->normalize_capture_second( $seconds ), 3, '.', '' );
	}

	private function format_seconds_label( $seconds ) {
		$label = rtrim( rtrim( $this->format_seconds_for_ffmpeg( $seconds ), '0' ), '.' );

		return '' === $label ? '0' : $label;
	}

	private function format_seconds_for_filename( $seconds ) {
		return str_replace( '.', '-', $this->format_seconds_label( $seconds ) ) . 's';
	}

	private function store_generation_error( $attachment_id, $message ) {
		update_post_meta( $attachment_id, self::META_LOCAL_COVER_ERROR, sanitize_text_field( wp_strip_all_tags( $message ) ) );
	}

	private function load_media_dependencies() {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		if ( ! function_exists( 'download_url' ) || ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) || ! function_exists( 'file_is_valid_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	private function extract_video_id( $url ) {
		$patterns = array(
			'/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
			'/youtu\.be\/([a-zA-Z0-9_-]{11})/',
			'/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
			'/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $url, $matches ) ) {
				return $matches[1];
			}
		}

		return false;
	}

	private function extract_video_title( $url ) {
		$endpoint = add_query_arg(
			array(
				'url'    => $url,
				'format' => 'json',
			),
			'https://www.youtube.com/oembed'
		);

		$response = wp_remote_get( $endpoint, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return false;
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['title'] ) ) {
			return false;
		}

		return sanitize_text_field( wp_strip_all_tags( $data['title'] ) );
	}

	private function build_filename_from_title( $video_title, $video_id ) {
		$base = sanitize_file_name( wp_strip_all_tags( (string) $video_title ) );

		if ( '' === $base ) {
			$base = 'youtube_' . $video_id;
		}

		return substr( $base, 0, 180 );
	}
}
