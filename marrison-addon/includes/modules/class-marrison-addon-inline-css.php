<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Opt eligible local styles into WordPress's bounded, ordered CSS inliner. */
class Marrison_Addon_Inline_CSS {

	private const MAX_FILE_BYTES = 16384;
	private const MAX_TOTAL_BYTES = 32768;
	private $prepared = false;

	public function __construct() {
		add_action( 'wp_head', [ $this, 'prepare_styles' ], 7 );
	}

	public function prepare_styles() {
		if ( $this->prepared || ! Marrison_Addon_Context::is_public_frontend_request() || ! function_exists( 'wp_maybe_inline_styles' ) || ! function_exists( '_wp_normalize_relative_css_links' ) ) {
			return;
		}

		$this->prepared = true;
		$styles = wp_styles();
		$candidates = [];

		foreach ( $styles->queue as $handle ) {
			if ( ! isset( $styles->registered[ $handle ] ) || in_array( $handle, $styles->done, true ) ) {
				continue;
			}

			$style = $styles->registered[ $handle ];
			if ( ! is_string( $style->src ) || '' === $style->src || ! in_array( $style->args, [ '', 'all' ], true ) ) {
				continue;
			}

			// Existing delivery policies, RTL replacements and conditional styles stay with their owner.
			if ( $styles->get_data( $handle, 'path' ) || $styles->get_data( $handle, 'rtl' ) || $styles->get_data( $handle, 'conditional' ) || $styles->get_data( $handle, 'alt' ) || $styles->get_data( $handle, 'title' ) ) {
				continue;
			}

			$path = $this->local_css_path( $style->src );
			if ( ! $path || ! is_readable( $path ) ) {
				continue;
			}

			$css = file_get_contents( $path, false, null, 0, self::MAX_FILE_BYTES + 1 );
			if ( false === $css || '' === $css || strlen( $css ) > self::MAX_FILE_BYTES || preg_match( '/@import\b|<\/style\b/i', $css ) ) {
				continue;
			}

			$candidates[] = [ 'handle' => $handle, 'path' => $path, 'bytes' => strlen( $css ) ];
		}

		usort( $candidates, static function ( $a, $b ) { return $a['bytes'] <=> $b['bytes']; } );
		$total = 0;
		foreach ( $candidates as $candidate ) {
			if ( $total + $candidate['bytes'] > self::MAX_TOTAL_BYTES ) {
				continue;
			}
			wp_style_add_data( $candidate['handle'], 'path', $candidate['path'] );
			$total += $candidate['bytes'];
		}

		if ( $total > 0 ) {
			// Core rebases relative URLs and prepends each sheet to its own inline CSS.
			// Its configured size budget can further reduce this module's selection.
			wp_maybe_inline_styles();
		}
	}

	private function local_css_path( $src ) {
		$url = wp_parse_url( $src );
		if ( ! is_array( $url ) || empty( $url['path'] ) || ! preg_match( '/\.css$/i', $url['path'] ) || isset( $url['user'] ) || isset( $url['pass'] ) ) {
			return '';
		}

		$query = [];
		if ( isset( $url['query'] ) ) {
			parse_str( $url['query'], $query );
			if ( array_diff( array_keys( $query ), [ 'ver' ] ) ) {
				return '';
			}
		}

		$site = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $site ) || empty( $site['scheme'] ) || empty( $site['host'] ) ) {
			return '';
		}
		$site_port = $site['port'] ?? ( 'https' === $site['scheme'] ? 443 : 80 );
		$bases = [ [ content_url( '/' ), WP_CONTENT_DIR ], [ includes_url( '/' ), ABSPATH . WPINC ] ];
		foreach ( $bases as $base ) {
			$base_url = wp_parse_url( $base[0] );
			if ( ! is_array( $base_url ) || empty( $base_url['path'] ) || empty( $base_url['scheme'] ) || empty( $base_url['host'] ) ) {
				continue;
			}
			$base_port = $base_url['port'] ?? ( 'https' === $base_url['scheme'] ? 443 : 80 );
			$url_port = $url['port'] ?? ( isset( $url['host'] ) ? ( 'https' === ( $url['scheme'] ?? $base_url['scheme'] ) ? 443 : 80 ) : $base_port );
			if ( $base_url['scheme'] !== $site['scheme'] || strcasecmp( $base_url['host'], $site['host'] ) !== 0 || $base_port !== $site_port || ( isset( $url['scheme'] ) && $url['scheme'] !== $base_url['scheme'] ) || ( isset( $url['host'] ) && strcasecmp( $url['host'], $base_url['host'] ) !== 0 ) || $url_port !== $base_port ) {
				continue;
			}

			$prefix = rtrim( $base_url['path'], '/' ) . '/';
			if ( strpos( $url['path'], $prefix ) !== 0 ) {
				continue;
			}

			$relative = rawurldecode( substr( $url['path'], strlen( $prefix ) ) );
			if ( false !== strpos( $relative, "\0" ) || false !== strpos( $relative, '\\' ) ) {
				return '';
			}

			$root = realpath( $base[1] );
			$path = false === $root ? false : realpath( $root . '/' . $relative );
			if ( false === $path || ! is_file( $path ) || 'css' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
				return '';
			}

			$root_prefix = rtrim( str_replace( '\\', '/', $root ), '/' ) . '/';
			$normalized = str_replace( '\\', '/', $path );
			$inside = '\\' === DIRECTORY_SEPARATOR ? 0 === strncasecmp( $normalized, $root_prefix, strlen( $root_prefix ) ) : 0 === strncmp( $normalized, $root_prefix, strlen( $root_prefix ) );
			return $inside ? $path : '';
		}

		return '';
	}
}
