<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Browser_Cache_Diagnostics {

	private $server;

	public function __construct( Marrison_Addon_Browser_Cache_Server $server ) {
		$this->server = $server;
	}

	public function run( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : $this->server->get_settings();
		$assets   = $this->collect_assets( $settings );
		$samples  = array();

		foreach ( $assets as $asset ) {
			$samples[] = $this->inspect_asset( $asset, $settings );
		}

		$summary = array(
			'ok'       => 0,
			'warning'  => 0,
			'error'    => 0,
			'all_ok'   => false,
			'message'  => '',
		);

		foreach ( $samples as $sample ) {
			if ( isset( $summary[ $sample['result'] ] ) ) {
				$summary[ $sample['result'] ]++;
			}
		}

		$summary['all_ok'] = ! empty( $samples ) && 0 === $summary['warning'] && 0 === $summary['error'];

		if ( empty( $samples ) ) {
			$summary['message'] = __( 'Nessun asset statico same-host trovato nella home page.', 'marrison-addon' );
		} elseif ( $summary['all_ok'] ) {
			$summary['message'] = __( 'Gli asset verificati hanno header cache coerenti.', 'marrison-addon' );
		} elseif ( $summary['error'] > 0 ) {
			$summary['message'] = __( 'Uno o piu asset non hanno header cache sufficienti.', 'marrison-addon' );
		} else {
			$summary['message'] = __( 'Gli header sono presenti, ma ci sono avvisi da controllare.', 'marrison-addon' );
		}

		return array(
			'checked_at' => current_time( 'mysql' ),
			'home_url'   => home_url( '/' ),
			'samples'    => $samples,
			'summary'    => $summary,
		);
	}

	private function collect_assets( $settings ) {
		$response = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'     => 12,
				'redirection' => 3,
				'user-agent'  => 'Marrison Browser Cache Diagnostics/' . Marrison_Addon::VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$html = wp_remote_retrieve_body( $response );

		if ( '' === $html ) {
			return array();
		}

		$candidates = array(
			'css'    => array(),
			'js'     => array(),
			'images' => array(),
			'fonts'  => array(),
		);

		$this->collect_html_urls( $html, '/<link[^>]+href=["\']([^"\']+\.css(?:\?[^"\']*)?)["\'][^>]*>/i', 'css', $candidates, $settings );
		$this->collect_html_urls( $html, '/<script[^>]+src=["\']([^"\']+\.js(?:\?[^"\']*)?)["\'][^>]*>/i', 'js', $candidates, $settings );
		$this->collect_html_urls( $html, '/<(?:img|source)[^>]+(?:src|data-src|poster)=["\']([^"\']+)["\'][^>]*>/i', 'images', $candidates, $settings );
		$this->collect_srcset_urls( $html, $candidates, $settings );
		$this->collect_font_urls_from_css( $candidates['css'], $candidates, $settings );

		$assets = array();

		foreach ( array( 'css', 'js', 'images', 'fonts' ) as $type ) {
			if ( empty( $settings['asset_types'][ $type ] ) || empty( $candidates[ $type ] ) ) {
				continue;
			}

			$url      = reset( $candidates[ $type ] );
			$assets[] = array(
				'type'  => $type,
				'label' => $this->build_asset_label( $url, $type ),
				'url'   => $url,
			);
		}

		return $assets;
	}

	private function collect_html_urls( $html, $pattern, $type, &$candidates, $settings ) {
		if ( empty( $settings['asset_types'][ $type ] ) ) {
			return;
		}

		if ( ! preg_match_all( $pattern, $html, $matches ) ) {
			return;
		}

		foreach ( $matches[1] as $url ) {
			$this->add_candidate_url( $url, $type, $candidates, $settings );
		}
	}

	private function collect_srcset_urls( $html, &$candidates, $settings ) {
		if ( empty( $settings['asset_types']['images'] ) ) {
			return;
		}

		if ( ! preg_match_all( '/\ssrcset=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			return;
		}

		foreach ( $matches[1] as $srcset ) {
			$parts = array_map( 'trim', explode( ',', html_entity_decode( $srcset, ENT_QUOTES ) ) );

			foreach ( $parts as $part ) {
				$url = trim( preg_split( '/\s+/', $part )[0] );
				$this->add_candidate_url( $url, 'images', $candidates, $settings );
			}
		}
	}

	private function collect_font_urls_from_css( $css_urls, &$candidates, $settings ) {
		if ( empty( $settings['asset_types']['fonts'] ) || empty( $css_urls ) ) {
			return;
		}

		foreach ( array_slice( array_values( $css_urls ), 0, 4 ) as $css_url ) {
			$response = wp_remote_get(
				$css_url,
				array(
					'timeout'             => 10,
					'redirection'         => 3,
					'limit_response_size' => 300000,
					'user-agent'          => 'Marrison Browser Cache Diagnostics/' . Marrison_Addon::VERSION,
				)
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$css = wp_remote_retrieve_body( $response );

			if ( '' === $css || ! preg_match_all( '/url\((["\']?)([^"\')]+?\.(?:woff2?|ttf|otf)(?:\?[^"\')]+)?)\1\)/i', $css, $matches ) ) {
				continue;
			}

			foreach ( $matches[2] as $font_url ) {
				$font_url = $this->normalize_url( $font_url, $css_url );
				$this->add_candidate_url( $font_url, 'fonts', $candidates, $settings );
			}

			if ( ! empty( $candidates['fonts'] ) ) {
				return;
			}
		}
	}

	private function add_candidate_url( $url, $type, &$candidates, $settings ) {
		$url = $this->normalize_url( $url );

		if ( ! $url || isset( $candidates[ $type ][ $url ] ) ) {
			return;
		}

		if ( ! $this->is_same_host_url( $url ) || ! $this->server->is_static_asset_url( $url, $settings ) ) {
			return;
		}

		$candidates[ $type ][ $url ] = $url;
	}

	private function inspect_asset( $asset, $settings ) {
		$headers_response = $this->request_headers( $asset['url'] );

		if ( is_wp_error( $headers_response ) ) {
			return array_merge(
				$asset,
				array(
					'status_code' => 0,
					'headers'     => array(),
					'versioned'   => $this->server->is_versioned_url( $asset['url'] ),
					'result'      => 'error',
					'message'     => $headers_response->get_error_message(),
				)
			);
		}

		$headers     = $headers_response['headers'];
		$cache       = isset( $headers['cache-control'] ) ? $headers['cache-control'] : '';
		$max_age     = $this->extract_max_age( $cache );
		$versioned   = $this->server->is_versioned_url( $asset['url'] );
		$expected    = $versioned ? absint( $settings['versioned_ttl'] ) : absint( $settings['unversioned_ttl'] );
		$has_immutable = false !== stripos( $cache, 'immutable' );
		$result      = 'ok';
		$message     = __( 'OK', 'marrison-addon' );

		if ( '' === $cache ) {
			$result  = 'error';
			$message = __( 'Header Cache-Control assente.', 'marrison-addon' );
		} elseif ( null === $max_age || $max_age < $expected ) {
			$result = 'error';
			$message = sprintf(
				/* translators: 1: expected seconds, 2: current header. */
				__( 'max-age inferiore al previsto (%1$d). Header ricevuto: %2$s', 'marrison-addon' ),
				$expected,
				$cache
			);
		} elseif ( $versioned && ! empty( $settings['immutable_versioned'] ) && ! $has_immutable ) {
			$result  = 'warning';
			$message = __( 'Asset versionato con TTL corretto, ma senza immutable.', 'marrison-addon' );
		} elseif ( ! $versioned && $has_immutable ) {
			$result  = 'warning';
			$message = __( 'Asset non versionato con immutable: verifica che esista un cache busting affidabile.', 'marrison-addon' );
		}

		return array_merge(
			$asset,
			array(
				'status_code' => $headers_response['status_code'],
				'headers'     => $headers,
				'versioned'   => $versioned,
				'result'      => $result,
				'message'     => $message,
			)
		);
	}

	private function request_headers( $url ) {
		$args = array(
			'timeout'     => 10,
			'redirection' => 3,
			'user-agent'  => 'Marrison Browser Cache Diagnostics/' . Marrison_Addon::VERSION,
		);

		$response = wp_remote_head( $url, $args );

		if ( is_wp_error( $response ) || in_array( (int) wp_remote_retrieve_response_code( $response ), array( 0, 403, 405 ), true ) ) {
			$response = wp_remote_get(
				$url,
				array_merge(
					$args,
					array(
						'limit_response_size' => 2048,
					)
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'status_code' => (int) wp_remote_retrieve_response_code( $response ),
			'headers'     => $this->normalize_headers( wp_remote_retrieve_headers( $response ) ),
		);
	}

	private function normalize_headers( $headers ) {
		$normalized = array();

		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$headers = $headers->getAll();
		}

		foreach ( (array) $headers as $key => $value ) {
			$key = strtolower( (string) $key );

			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			$normalized[ $key ] = (string) $value;
		}

		return $normalized;
	}

	private function extract_max_age( $cache_control ) {
		if ( ! preg_match( '/(?:^|,\s*)max-age=(\d+)/i', (string) $cache_control, $matches ) ) {
			return null;
		}

		return absint( $matches[1] );
	}

	private function normalize_url( $url, $base_url = '' ) {
		$url = trim( html_entity_decode( (string) $url, ENT_QUOTES ) );

		if ( '' === $url || 0 === strpos( $url, 'data:' ) || 0 === strpos( $url, 'blob:' ) ) {
			return '';
		}

		if ( 0 === strpos( $url, '//' ) ) {
			$scheme = wp_parse_url( home_url(), PHP_URL_SCHEME );

			return $scheme . ':' . $url;
		}

		if ( preg_match( '#^https?://#i', $url ) ) {
			return $url;
		}

		if ( 0 === strpos( $url, '/' ) ) {
			return home_url( $url );
		}

		if ( $base_url ) {
			$base_path = wp_parse_url( $base_url, PHP_URL_PATH );
			$base_dir  = $base_path ? trailingslashit( dirname( $base_path ) ) : '/';

			return home_url( $base_dir . ltrim( $url, '/' ) );
		}

		return home_url( '/' . ltrim( $url, '/' ) );
	}

	private function is_same_host_url( $url ) {
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );

		return $home_host && $url_host && strtolower( $home_host ) === strtolower( $url_host );
	}

	private function build_asset_label( $url, $type ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$name = $path ? basename( $path ) : $url;

		return strtoupper( $type ) . ': ' . $name;
	}
}
