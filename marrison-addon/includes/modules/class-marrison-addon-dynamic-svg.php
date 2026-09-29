<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Dynamic_SVG {

	const CALLBACK_INLINE        = 'marrison_addon_inline_svg';
	const CALLBACK_CURRENT_COLOR = 'marrison_addon_inline_svg_current_color';
	const CACHE_GROUP            = 'marrison_addon_dynamic_svg';

	public function __construct() {
		if ( ! Marrison_Addon::is_jet_engine_active() ) {
			return;
		}

		add_action( 'jet-engine/callbacks/register', [ $this, 'register_callbacks' ] );
		add_filter( 'jet-engine/listings/allowed-callbacks', [ $this, 'register_allowed_callbacks' ] );
	}

	public function register_callbacks( $callbacks_manager ) {
		if ( ! is_object( $callbacks_manager ) || ! method_exists( $callbacks_manager, 'register_callback' ) ) {
			return;
		}

		$callbacks_manager->register_callback(
			self::CALLBACK_INLINE,
			esc_html__( 'Marrison – Inline SVG', 'marrison-addon' )
		);

		$callbacks_manager->register_callback(
			self::CALLBACK_CURRENT_COLOR,
			esc_html__( 'Marrison – Inline SVG (Current Color)', 'marrison-addon' )
		);
	}

	public function register_allowed_callbacks( $callbacks ) {
		if ( ! is_array( $callbacks ) ) {
			$callbacks = [];
		}

		$callbacks[ self::CALLBACK_INLINE ]        = esc_html__( 'Marrison – Inline SVG', 'marrison-addon' );
		$callbacks[ self::CALLBACK_CURRENT_COLOR ] = esc_html__( 'Marrison – Inline SVG (Current Color)', 'marrison-addon' );

		return $callbacks;
	}

	public static function render_inline_svg( $value ) {
		return self::render_svg( $value, false );
	}

	public static function render_inline_svg_current_color( $value ) {
		return self::render_svg( $value, true );
	}

	private static function render_svg( $value, $current_color ) {
		$file_path = self::resolve_svg_file_path( $value );

		if ( '' === $file_path ) {
			return '';
		}

		$svg = self::get_sanitized_svg( $file_path, $current_color );

		if ( '' === $svg ) {
			return '';
		}

		return '<span class="marrison-inline-svg">' . $svg . '</span>';
	}

	private static function resolve_svg_file_path( $value ) {
		if ( is_array( $value ) ) {
			if ( ! empty( $value['id'] ) ) {
				$file_path = self::resolve_attachment_file_path( $value['id'] );

				if ( '' !== $file_path ) {
					return $file_path;
				}
			}

			if ( ! empty( $value['url'] ) ) {
				return self::resolve_url_file_path( $value['url'] );
			}

			return '';
		}

		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( trim( $value ) ) ) ) {
			$file_path = self::resolve_attachment_file_path( $value );

			if ( '' !== $file_path ) {
				return $file_path;
			}
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) );

		if ( '' === $value ) {
			return '';
		}

		return self::resolve_url_file_path( $value );
	}

	private static function resolve_attachment_file_path( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( $attachment_id <= 0 || ! function_exists( 'get_attached_file' ) ) {
			return '';
		}

		$file_path = get_attached_file( $attachment_id );

		if ( ! is_string( $file_path ) || '' === $file_path ) {
			return '';
		}

		return self::validate_svg_file_path( $file_path );
	}

	private static function resolve_url_file_path( $url ) {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return '';
		}

		$url = trim( $url );

		if ( 0 === stripos( $url, 'data:' ) || 0 === stripos( $url, 'javascript:' ) ) {
			return '';
		}

		$upload_dir = self::get_upload_dir();

		if ( empty( $upload_dir['baseurl'] ) || empty( $upload_dir['basedir'] ) ) {
			return '';
		}

		$url_parts        = self::parse_url( $url );
		$upload_url_parts = self::parse_url( $upload_dir['baseurl'] );

		if ( false === $url_parts || false === $upload_url_parts || empty( $upload_url_parts['path'] ) ) {
			return '';
		}

		if ( ! empty( $url_parts['scheme'] ) && ! in_array( strtolower( $url_parts['scheme'] ), [ 'http', 'https' ], true ) ) {
			return '';
		}

		if ( ! self::is_local_url_host( $url_parts ) ) {
			return '';
		}

		$url_path    = isset( $url_parts['path'] ) ? rawurldecode( $url_parts['path'] ) : '';
		$upload_path = rawurldecode( $upload_url_parts['path'] );

		$url_path    = '/' . ltrim( self::normalize_url_path( $url_path ), '/' );
		$upload_path = '/' . trim( self::normalize_url_path( $upload_path ), '/' );

		if ( ! self::path_has_prefix( $url_path, $upload_path ) ) {
			return '';
		}

		$relative_path = ltrim( substr( $url_path, strlen( $upload_path ) ), '/' );

		if ( '' === $relative_path || false !== strpos( $relative_path, "\0" ) ) {
			return '';
		}

		$file_path = self::trailingslashit( $upload_dir['basedir'] ) . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );

		return self::validate_svg_file_path( $file_path );
	}

	private static function validate_svg_file_path( $file_path ) {
		if ( ! is_string( $file_path ) || '' === trim( $file_path ) ) {
			return '';
		}

		$file_path = self::normalize_path( $file_path );

		if ( 'svg' !== strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ) ) {
			return '';
		}

		$real_path = realpath( $file_path );

		if ( ! is_string( $real_path ) || ! is_file( $real_path ) || ! is_readable( $real_path ) ) {
			return '';
		}

		$upload_dir = self::get_upload_dir();

		if ( empty( $upload_dir['basedir'] ) ) {
			return '';
		}

		$uploads_real_path = realpath( $upload_dir['basedir'] );

		if ( ! is_string( $uploads_real_path ) || ! self::is_path_inside( $real_path, $uploads_real_path ) ) {
			return '';
		}

		return self::normalize_path( $real_path );
	}

	private static function get_sanitized_svg( $file_path, $current_color ) {
		$file_size = filesize( $file_path );

		if ( false === $file_size ) {
			return '';
		}

		$max_file_size = (int) apply_filters( 'marrison_addon/dynamic_svg/max_file_size', 262144 );

		if ( $max_file_size <= 0 ) {
			$max_file_size = 262144;
		}

		if ( $file_size > $max_file_size ) {
			return '';
		}

		$mode      = $current_color ? 'current-color' : 'original';
		$file_time = filemtime( $file_path );
		$cache_key = 'svg_' . md5( $mode . '|' . self::normalize_path( $file_path ) . '|' . $file_size . '|' . (int) $file_time );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) && array_key_exists( 'svg', $cached ) ) {
			return (string) $cached['svg'];
		}

		$raw_svg = file_get_contents( $file_path );
		$svg     = is_string( $raw_svg ) ? self::sanitize_svg( $raw_svg, $current_color ) : '';

		wp_cache_set( $cache_key, [ 'svg' => $svg ], self::CACHE_GROUP );

		return $svg;
	}

	private static function sanitize_svg( $raw_svg, $current_color ) {
		if ( ! is_string( $raw_svg ) || '' === trim( $raw_svg ) || ! class_exists( 'DOMDocument' ) ) {
			return '';
		}

		if ( preg_match( '/<!DOCTYPE|<!ENTITY/i', $raw_svg ) ) {
			return '';
		}

		$previous_errors = libxml_use_internal_errors( true );
		$previous_loader = null;

		if ( PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
			$previous_loader = libxml_disable_entity_loader( true );
		}

		$document = new DOMDocument();
		$flags    = LIBXML_NONET;

		if ( defined( 'LIBXML_COMPACT' ) ) {
			$flags |= LIBXML_COMPACT;
		}

		$loaded = $document->loadXML( $raw_svg, $flags );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );

		if ( null !== $previous_loader ) {
			libxml_disable_entity_loader( $previous_loader );
		}

		if ( ! $loaded || ! $document->documentElement || 'svg' !== strtolower( $document->documentElement->localName ) ) {
			return '';
		}

		$root = $document->documentElement;

		self::sanitize_node( $root, $current_color );

		if ( $current_color && ! $root->hasAttribute( 'fill' ) && ! self::style_has_property( $root->getAttribute( 'style' ), 'fill' ) ) {
			$root->setAttribute( 'fill', 'currentColor' );
		}

		$svg = $document->saveXML( $root );

		return is_string( $svg ) ? trim( $svg ) : '';
	}

	private static function sanitize_node( DOMNode $node, $current_color ) {
		if ( XML_ELEMENT_NODE === $node->nodeType ) {
			$element = $node;

			if ( ! self::is_allowed_element( $element->localName ) ) {
				if ( $element->parentNode ) {
					$element->parentNode->removeChild( $element );
				}

				return;
			}

			self::sanitize_attributes( $element, $current_color );
		}

		for ( $child = $node->firstChild; $child; ) {
			$next = $child->nextSibling;

			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				self::sanitize_node( $child, $current_color );
			} elseif ( XML_TEXT_NODE !== $child->nodeType && XML_CDATA_SECTION_NODE !== $child->nodeType ) {
				$node->removeChild( $child );
			}

			$child = $next;
		}
	}

	private static function sanitize_attributes( DOMElement $element, $current_color ) {
		$attributes = [];

		foreach ( $element->attributes as $attribute ) {
			$attributes[] = $attribute;
		}

		foreach ( $attributes as $attribute ) {
			$name       = $attribute->name;
			$local_name = strtolower( $attribute->localName ? $attribute->localName : $name );
			$full_name  = strtolower( $name );
			$value      = trim( $attribute->value );

			if ( 0 === strpos( $full_name, 'on' ) || 0 === strpos( $local_name, 'on' ) ) {
				$element->removeAttributeNode( $attribute );
				continue;
			}

			if ( 'xmlns' === $full_name ) {
				if ( 'http://www.w3.org/2000/svg' !== $value ) {
					$element->removeAttributeNode( $attribute );
				}

				continue;
			}

			if ( 'xmlns:xlink' === $full_name ) {
				if ( 'http://www.w3.org/1999/xlink' !== $value ) {
					$element->removeAttributeNode( $attribute );
				}

				continue;
			}

			if ( ! self::is_allowed_attribute( $local_name, $full_name ) ) {
				$element->removeAttributeNode( $attribute );
				continue;
			}

			if ( 'style' === $local_name ) {
				$value = self::sanitize_style_attribute( $value, $current_color );

				if ( '' === $value ) {
					$element->removeAttributeNode( $attribute );
				} else {
					$attribute->value = $value;
				}

				continue;
			}

			if ( 'href' === $local_name && ! self::is_safe_fragment_reference( $value ) ) {
				$element->removeAttributeNode( $attribute );
				continue;
			}

			if ( $current_color && in_array( $local_name, [ 'fill', 'stroke' ], true ) ) {
				$value = self::convert_color_value_to_current_color( $value );
			}

			if ( ! self::is_safe_svg_attribute_value( $value ) ) {
				$element->removeAttributeNode( $attribute );
				continue;
			}

			$attribute->value = $value;
		}
	}

	private static function sanitize_style_attribute( $style, $current_color ) {
		if ( '' === trim( $style ) ) {
			return '';
		}

		$declarations = [];

		foreach ( explode( ';', $style ) as $declaration ) {
			$declaration = trim( $declaration );

			if ( '' === $declaration || false === strpos( $declaration, ':' ) ) {
				continue;
			}

			list( $property, $value ) = array_map( 'trim', explode( ':', $declaration, 2 ) );
			$property                 = strtolower( $property );

			if ( ! self::is_allowed_style_property( $property ) ) {
				continue;
			}

			if ( $current_color && in_array( $property, [ 'fill', 'stroke' ], true ) ) {
				$value = self::convert_color_value_to_current_color( $value );
			}

			if ( ! self::is_safe_css_value( $value ) ) {
				continue;
			}

			$declarations[] = $property . ': ' . $value;
		}

		return implode( '; ', $declarations );
	}

	private static function convert_color_value_to_current_color( $value ) {
		$value = trim( $value );

		if ( '' === $value ) {
			return $value;
		}

		$important = '';

		if ( preg_match( '/\s*!important\s*$/i', $value ) ) {
			$important = ' !important';
			$value     = trim( preg_replace( '/\s*!important\s*$/i', '', $value ) );
		}

		$normalized = strtolower( preg_replace( '/\s+/', '', $value ) );

		if (
			'' === $normalized ||
			in_array( $normalized, [ 'none', 'currentcolor', 'inherit', 'initial', 'unset', 'transparent', 'context-fill', 'context-stroke' ], true ) ||
			0 === strpos( $normalized, 'url(' ) ||
			0 === strpos( $normalized, 'var(' )
		) {
			return $value . $important;
		}

		return 'currentColor' . $important;
	}

	private static function is_allowed_element( $name ) {
		static $allowed = [
			'svg' => true,
			'g' => true,
			'path' => true,
			'circle' => true,
			'rect' => true,
			'polygon' => true,
			'polyline' => true,
			'line' => true,
			'ellipse' => true,
			'defs' => true,
			'clippath' => true,
			'mask' => true,
			'lineargradient' => true,
			'radialgradient' => true,
			'stop' => true,
			'pattern' => true,
			'symbol' => true,
			'use' => true,
			'title' => true,
			'desc' => true,
			'filter' => true,
			'feblend' => true,
			'fecolormatrix' => true,
			'fecomponenttransfer' => true,
			'fecomposite' => true,
			'feflood' => true,
			'fegaussianblur' => true,
			'femerge' => true,
			'femergenode' => true,
			'feoffset' => true,
			'fedropshadow' => true,
			'femorphology' => true,
			'feturbulence' => true,
			'fedisplacementmap' => true,
			'fefunca' => true,
			'fefuncb' => true,
			'fefuncg' => true,
			'fefuncr' => true,
			'text' => true,
			'tspan' => true,
			'marker' => true,
		];

		return isset( $allowed[ strtolower( $name ) ] );
	}

	private static function is_allowed_attribute( $local_name, $full_name ) {
		static $allowed = [
			'id' => true,
			'class' => true,
			'style' => true,
			'viewbox' => true,
			'version' => true,
			'width' => true,
			'height' => true,
			'x' => true,
			'y' => true,
			'x1' => true,
			'y1' => true,
			'x2' => true,
			'y2' => true,
			'cx' => true,
			'cy' => true,
			'r' => true,
			'rx' => true,
			'ry' => true,
			'd' => true,
			'points' => true,
			'transform' => true,
			'fill' => true,
			'stroke' => true,
			'stroke-width' => true,
			'stroke-linecap' => true,
			'stroke-linejoin' => true,
			'stroke-miterlimit' => true,
			'stroke-dasharray' => true,
			'stroke-dashoffset' => true,
			'stroke-opacity' => true,
			'fill-opacity' => true,
			'fill-rule' => true,
			'clip-rule' => true,
			'opacity' => true,
			'pathlength' => true,
			'vector-effect' => true,
			'preserveaspectratio' => true,
			'focusable' => true,
			'role' => true,
			'aria-hidden' => true,
			'aria-label' => true,
			'aria-labelledby' => true,
			'aria-describedby' => true,
			'tabindex' => true,
			'clip-path' => true,
			'mask' => true,
			'filter' => true,
			'clippathunits' => true,
			'maskunits' => true,
			'maskcontentunits' => true,
			'filterunits' => true,
			'primitiveunits' => true,
			'gradientunits' => true,
			'gradienttransform' => true,
			'spreadmethod' => true,
			'offset' => true,
			'stop-color' => true,
			'stop-opacity' => true,
			'href' => true,
			'xlink:href' => true,
			'patternunits' => true,
			'patterncontentunits' => true,
			'patterntransform' => true,
			'marker-start' => true,
			'marker-mid' => true,
			'marker-end' => true,
			'markerwidth' => true,
			'markerheight' => true,
			'refx' => true,
			'refy' => true,
			'orient' => true,
			'markerunits' => true,
			'font-family' => true,
			'font-size' => true,
			'font-weight' => true,
			'font-style' => true,
			'text-anchor' => true,
			'dominant-baseline' => true,
			'alignment-baseline' => true,
			'baseline-shift' => true,
			'dx' => true,
			'dy' => true,
			'rotate' => true,
			'textlength' => true,
			'lengthadjust' => true,
			'flood-color' => true,
			'flood-opacity' => true,
			'color-interpolation-filters' => true,
			'in' => true,
			'in2' => true,
			'result' => true,
			'type' => true,
			'values' => true,
			'operator' => true,
			'mode' => true,
			'stddeviation' => true,
			'edgemode' => true,
			'basefrequency' => true,
			'numoctaves' => true,
			'seed' => true,
			'stitchtiles' => true,
			'scale' => true,
			'xchannelselector' => true,
			'ychannelselector' => true,
			'k1' => true,
			'k2' => true,
			'k3' => true,
			'k4' => true,
			'tablevalues' => true,
			'slope' => true,
			'intercept' => true,
			'amplitude' => true,
			'exponent' => true,
			'azimuth' => true,
			'elevation' => true,
			'pointsatx' => true,
			'pointsaty' => true,
			'pointsatz' => true,
			'specularexponent' => true,
			'limitingconeangle' => true,
		];

		return isset( $allowed[ $local_name ] ) || isset( $allowed[ $full_name ] );
	}

	private static function is_allowed_style_property( $property ) {
		static $allowed = [
			'fill' => true,
			'stroke' => true,
			'stroke-width' => true,
			'stroke-linecap' => true,
			'stroke-linejoin' => true,
			'stroke-miterlimit' => true,
			'stroke-dasharray' => true,
			'stroke-dashoffset' => true,
			'stroke-opacity' => true,
			'fill-opacity' => true,
			'fill-rule' => true,
			'clip-rule' => true,
			'opacity' => true,
			'display' => true,
			'visibility' => true,
			'stop-color' => true,
			'stop-opacity' => true,
			'clip-path' => true,
			'mask' => true,
			'filter' => true,
			'color' => true,
			'vector-effect' => true,
		];

		return isset( $allowed[ $property ] );
	}

	private static function is_safe_css_value( $value ) {
		$normalized = strtolower( preg_replace( '/\s+/', '', $value ) );

		if (
			false !== strpos( $normalized, 'javascript:' ) ||
			false !== strpos( $normalized, 'data:' ) ||
			false !== strpos( $normalized, 'vbscript:' ) ||
			false !== strpos( $normalized, 'expression(' ) ||
			false !== strpos( $normalized, '@import' ) ||
			false !== strpos( $normalized, '<' ) ||
			false !== strpos( $normalized, '>' )
		) {
			return false;
		}

		return self::has_only_safe_url_references( $value );
	}

	private static function is_safe_svg_attribute_value( $value ) {
		$normalized = strtolower( preg_replace( '/[\x00-\x20]+/', '', html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) ) );

		if (
			0 === strpos( $normalized, 'javascript:' ) ||
			0 === strpos( $normalized, 'data:' ) ||
			0 === strpos( $normalized, 'vbscript:' ) ||
			false !== strpos( $normalized, '<script' ) ||
			false !== strpos( $normalized, '<' ) ||
			false !== strpos( $normalized, '>' )
		) {
			return false;
		}

		return self::has_only_safe_url_references( $value );
	}

	private static function has_only_safe_url_references( $value ) {
		if ( false === stripos( $value, 'url(' ) ) {
			return true;
		}

		if ( ! preg_match_all( '/url\(\s*([^)]+?)\s*\)/i', $value, $matches ) ) {
			return false;
		}

		foreach ( $matches[1] as $reference ) {
			$reference = trim( $reference, " \t\n\r\0\x0B'\"" );

			if ( ! self::is_safe_fragment_reference( $reference ) ) {
				return false;
			}
		}

		return true;
	}

	private static function is_safe_fragment_reference( $value ) {
		$value = trim( $value );

		return preg_match( '/^#[A-Za-z][A-Za-z0-9_.:-]*$/', $value );
	}

	private static function style_has_property( $style, $property ) {
		if ( '' === trim( (string) $style ) ) {
			return false;
		}

		return (bool) preg_match( '/(^|;)\s*' . preg_quote( $property, '/' ) . '\s*:/i', $style );
	}

	private static function is_local_url_host( $url_parts ) {
		if ( empty( $url_parts['host'] ) ) {
			return true;
		}

		$host          = strtolower( $url_parts['host'] );
		$allowed_urls  = [];
		$upload_dir    = self::get_upload_dir();
		$allowed_urls[] = isset( $upload_dir['baseurl'] ) ? $upload_dir['baseurl'] : '';

		if ( function_exists( 'home_url' ) ) {
			$allowed_urls[] = home_url( '/' );
		}

		if ( function_exists( 'site_url' ) ) {
			$allowed_urls[] = site_url( '/' );
		}

		foreach ( $allowed_urls as $allowed_url ) {
			$allowed_parts = self::parse_url( $allowed_url );

			if ( is_array( $allowed_parts ) && ! empty( $allowed_parts['host'] ) && strtolower( $allowed_parts['host'] ) === $host ) {
				return true;
			}
		}

		return false;
	}

	private static function is_path_inside( $path, $base_path ) {
		$path      = strtolower( self::normalize_path( $path ) );
		$base_path = strtolower( self::normalize_path( $base_path ) );

		return $path === $base_path || 0 === strpos( $path, self::trailingslashit( $base_path ) );
	}

	private static function path_has_prefix( $path, $prefix ) {
		$path   = self::normalize_url_path( $path );
		$prefix = self::normalize_url_path( $prefix );

		return $path === $prefix || 0 === strpos( $path, self::trailingslashit( $prefix ) );
	}

	private static function get_upload_dir() {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return [];
		}

		$upload_dir = wp_upload_dir();

		return is_array( $upload_dir ) ? $upload_dir : [];
	}

	private static function parse_url( $url ) {
		return function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
	}

	private static function normalize_path( $path ) {
		return function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $path ) : str_replace( '\\', '/', $path );
	}

	private static function normalize_url_path( $path ) {
		return str_replace( '\\', '/', (string) $path );
	}

	private static function trailingslashit( $value ) {
		return rtrim( (string) $value, "/\\" ) . '/';
	}
}

function marrison_addon_inline_svg( $value = '' ) {
	return Marrison_Addon_Dynamic_SVG::render_inline_svg( $value );
}

function marrison_addon_inline_svg_current_color( $value = '' ) {
	return Marrison_Addon_Dynamic_SVG::render_inline_svg_current_color( $value );
}
