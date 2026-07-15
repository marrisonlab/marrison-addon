<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Video_Thumbnail {

	private const VERSION = '1.0.3';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_mvt_get_thumbnails', array( $this, 'ajax_get_thumbnails' ) );
		add_action( 'wp_ajax_mvt_import_thumbnail', array( $this, 'ajax_import_thumbnail' ) );
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
		?>
		<div class="wrap mvt-wrap">
			<h1><?php esc_html_e( 'Marrison Video Thumbnail', 'marrison-addon' ); ?></h1>
			<p><?php esc_html_e( 'Paste a YouTube video URL to fetch the available thumbnails.', 'marrison-addon' ); ?></p>

			<div class="mvt-form">
				<input type="text" id="mvt-url" class="regular-text" placeholder="https://www.youtube.com/watch?v=..." />
				<button id="mvt-fetch" type="button" class="button button-primary"><?php esc_html_e( 'Fetch Thumbnails', 'marrison-addon' ); ?></button>
			</div>

			<div id="mvt-status" class="mvt-status"></div>
			<div id="mvt-results" class="mvt-results"></div>
		</div>
		<?php
	}

	public function ajax_get_thumbnails() {
		check_ajax_referer( 'mvt_nonce', 'nonce' );

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

		$image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		$video_id = isset( $_POST['video_id'] ) ? sanitize_text_field( wp_unslash( $_POST['video_id'] ) ) : '';
		$video_title = isset( $_POST['video_title'] ) ? sanitize_text_field( wp_unslash( $_POST['video_title'] ) ) : '';

		if ( empty( $image_url ) ) {
			wp_send_json_error( 'Missing image URL.' );
		}

		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

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
