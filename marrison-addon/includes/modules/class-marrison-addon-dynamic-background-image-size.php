<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Restore the native media size lost when Elementor evaluates a dynamic background. */
class Marrison_Addon_Dynamic_Background_Image_Size {

	const CACHE_VERSION_OPTION = 'marrison_addon_dynamic_background_css_version';
	const ATTRIBUTE = 'data-marrison-dynamic-bg-size';
	private $contexts = [];
	private $attachment_ids = [];
	private $resolved_urls = [];

	public function __construct() {
		add_action( 'elementor/element/before_parse_css', [ $this, 'before_parse_css' ], 10, 2 );
		add_action( 'elementor/element/parse_css', [ $this, 'after_parse_css' ], 10, 2 );
		add_filter( 'elementor/files/css/property', [ $this, 'filter_css_value' ], 5, 4 );
		add_filter( 'marrison_addon/image_sizes/url_replacement', [ $this, 'preserve_resolved_size' ], 10, 3 );
		add_filter( 'marrison_addon/image_sizes/lcp_background_settings', [ $this, 'filter_lcp_settings' ], 10, 2 );
		add_action( 'elementor/frontend/container/before_render', [ $this, 'before_render' ], 5 );
		add_action( 'wp_loaded', [ $this, 'maybe_clear_css_cache' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor_scripts' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'enqueue_editor_styles' ] );
	}

	public function enqueue_editor_scripts() {
		$plugin_file = dirname( __DIR__, 2 ) . '/marrison-addon.php';
		$asset = 'assets/js/marrison-dynamic-background-size-editor.js';
		wp_enqueue_script( 'marrison-dynamic-background-size-editor', plugins_url( $asset, $plugin_file ), [ 'elementor-editor' ], Marrison_Addon::asset_version( $asset ), true );
		wp_localize_script( 'marrison-dynamic-background-size-editor', 'marrisonDynamicBackgroundSizeEditor', [
			'notice' => __( 'Marrison Addon applica questa risoluzione allo sfondo dinamico quando è disponibile un allegato WordPress. Se la variante manca, mantiene l’immagine originale.', 'marrison-addon' ),
		] );
	}

	public function enqueue_editor_styles() {
		$plugin_file = dirname( __DIR__, 2 ) . '/marrison-addon.php';
		$asset = 'assets/css/marrison-dynamic-background-size-editor.css';
		wp_enqueue_style( 'marrison-dynamic-background-size-editor', plugins_url( $asset, $plugin_file ), [], Marrison_Addon::asset_version( $asset ) );
	}

	private function is_background_image( $name ) {
		return is_string( $name ) && preg_match( '/^background_(?:(?:overlay|hover|overlay_hover)_)?image(?:_[a-z0-9_]+)?$/D', $name );
	}

	private function get_dynamic_backgrounds( $element ) {
		if ( 'container' !== $element->get_name() ) {
			return [];
		}
		$settings = $element->get_settings();
		$backgrounds = [];
		foreach ( (array) ( $settings['__dynamic__'] ?? [] ) as $name => $tag ) {
			if ( $tag && $this->is_background_image( $name ) ) {
				// Empty size is Elementor's native Full option, not an automatic choice.
				$size = $settings[ $name ]['size'] ?? '';
				$backgrounds[ $name ] = is_string( $size ) && '' !== $size ? $size : 'full';
			}
		}
		return $backgrounds;
	}

	public function before_parse_css( $css, $element ) {
		$this->contexts[] = $this->get_dynamic_backgrounds( $element );
	}

	public function after_parse_css( $css, $element ) {
		array_pop( $this->contexts );
	}

	public function filter_css_value( $value, $property, $matches, $control ) {
		$context = end( $this->contexts );
		$name = $control['name'] ?? '';
		if ( ! isset( $context[ $name ] ) || 'media' !== ( $control['type'] ?? '' ) || 'URL' !== ( $matches[2] ?? '' ) || ! preg_match( '/(?:^|;)\s*background-image\s*:/i', $property ) ) {
			return $value;
		}
		return $this->resolve_image( $value, $context[ $name ] );
	}

	private function resolve_image( $value, $size ) {
		$image = is_array( $value ) ? $value : [ 'url' => is_string( $value ) ? $value : '', 'id' => is_numeric( $value ) ? $value : 0 ];
		$id = absint( $image['id'] ?? $image['ID'] ?? 0 );
		$url = isset( $image['url'] ) && is_string( $image['url'] ) ? $image['url'] : '';
		if ( ! $id && $url ) {
			$clean_url = preg_replace( '/[?#].*$/', '', html_entity_decode( $url, ENT_QUOTES ) );
			if ( ! array_key_exists( $clean_url, $this->attachment_ids ) ) {
				$this->attachment_ids[ $clean_url ] = attachment_url_to_postid( $clean_url );
			}
			$id = $this->attachment_ids[ $clean_url ];
		}

		if ( $id && wp_attachment_is_image( $id ) ) {
			// Formato Immagini supplies existing variants through its own resolver.
			$resolved = apply_filters( 'marrison_addon/image_sizes/selected_background_url', null, $id, $size );
			if ( null === $resolved ) {
				$resolved = 'full' === $size ? wp_get_attachment_url( $id ) : wp_get_attachment_image_url( $id, $size );
			}
			$url = $resolved ? $resolved : ( wp_get_attachment_url( $id ) ?: $url );
			// WordPress metadata may still name a deleted local intermediate file.
			$metadata = wp_get_attachment_metadata( $id );
			$file = $metadata['sizes'][ $size ]['file'] ?? '';
			$original = get_attached_file( $id );
			if ( $file && basename( wp_parse_url( $url, PHP_URL_PATH ) ) === basename( $file ) && $original && is_file( $original ) && ! is_file( dirname( $original ) . '/' . $file ) ) {
				$url = wp_get_attachment_url( $id ) ?: $url;
			}
		}

		if ( ! $url ) {
			return $value;
		}
		$this->resolved_urls[ html_entity_decode( $url, ENT_QUOTES ) ] = true;
		$image['url'] = $url;
		$image['size'] = $size;
		// Only the temporary CSS value loses its ID; Elementor must not downsize it again.
		$image['id'] = 0;
		return $image;
	}

	public function preserve_resolved_size( $replacement, $url, $format ) {
		// The existing formatter uses explicit WebP for CSS. Leave ordinary <img> handling intact.
		return 'webp' === $format && isset( $this->resolved_urls[ html_entity_decode( $url, ENT_QUOTES ) ] ) ? false : $replacement;
	}

	public function filter_lcp_settings( $settings, $element ) {
		foreach ( $this->get_dynamic_backgrounds( $element ) as $name => $size ) {
			if ( isset( $settings[ $name ] ) ) {
				$settings[ $name ] = $this->resolve_image( $settings[ $name ], $size );
			}
		}
		return $settings;
	}

	public function before_render( $element ) {
		if ( $this->get_dynamic_backgrounds( $element ) ) {
			$element->add_render_attribute( '_wrapper', self::ATTRIBUTE, '1' );
		}
	}

	public function maybe_clear_css_cache() {
		$version = Marrison_Addon::VERSION . '|' . ( defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
		if ( get_option( self::CACHE_VERSION_OPTION ) !== $version && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
			update_option( self::CACHE_VERSION_OPTION, $version, false );
		}
	}
}
