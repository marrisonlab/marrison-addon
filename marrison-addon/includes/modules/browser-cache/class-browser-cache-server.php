<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Browser_Cache_Server {

	const OPTION_SETTINGS = 'marrison_browser_cache_settings';
	const OPTION_STATE    = 'marrison_browser_cache_state';
	const BEGIN_MARKER    = '# BEGIN Marrison Optimizer Browser Cache';
	const END_MARKER      = '# END Marrison Optimizer Browser Cache';
	const RULES_VERSION   = '2';

	public function get_default_settings() {
		return array(
			'versioned_ttl'       => WEEK_IN_SECONDS,
			'unversioned_ttl'     => WEEK_IN_SECONDS,
			'immutable_versioned' => 0,
			'asset_types'         => array(
				'css'    => 1,
				'js'     => 1,
				'fonts'  => 1,
				'images' => 1,
			),
		);
	}

	public function get_settings() {
		$settings = get_option( self::OPTION_SETTINGS, null );

		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return $this->get_default_settings();
		}

		return $this->sanitize_settings( $settings );
	}

	public function sanitize_settings( $input ) {
		$defaults = $this->get_default_settings();
		$input    = is_array( $input ) ? $input : array();

		$versioned_ttl = isset( $input['versioned_ttl'] ) ? absint( $input['versioned_ttl'] ) : $defaults['versioned_ttl'];
		$versioned_ttl = max( HOUR_IN_SECONDS, min( $versioned_ttl, 5 * YEAR_IN_SECONDS ) );

		$unversioned_ttl = isset( $input['unversioned_ttl'] ) ? absint( $input['unversioned_ttl'] ) : $defaults['unversioned_ttl'];
		$unversioned_ttl = max( HOUR_IN_SECONDS, min( $unversioned_ttl, YEAR_IN_SECONDS ) );

		$asset_input = isset( $input['asset_types'] ) && is_array( $input['asset_types'] ) ? $input['asset_types'] : array();
		$asset_types = array();

		foreach ( $defaults['asset_types'] as $type => $enabled ) {
			$asset_types[ $type ] = ! empty( $asset_input[ $type ] ) ? 1 : 0;
		}

		return array(
			'versioned_ttl'       => $versioned_ttl,
			'unversioned_ttl'     => $unversioned_ttl,
			'immutable_versioned' => ! empty( $input['immutable_versioned'] ) ? 1 : 0,
			'asset_types'         => $asset_types,
		);
	}

	public function get_state() {
		$state = get_option( self::OPTION_STATE, array() );

		return is_array( $state ) ? $state : array();
	}

	public function update_state( $state ) {
		$current = $this->get_state();
		$state   = array_merge(
			$current,
			$state,
			array(
				'updated_at' => current_time( 'mysql' ),
			)
		);

		update_option( self::OPTION_STATE, $state, false );

		return $state;
	}

	public function get_settings_hash( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : $this->get_settings();

		return md5( wp_json_encode( array( self::RULES_VERSION, $this->get_method()['slug'], $settings ) ) );
	}

	public function detect_server() {
		$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
		$lower    = strtolower( $software );

		if ( false !== strpos( $lower, 'openlitespeed' ) ) {
			return array(
				'slug'  => 'openlitespeed',
				'label' => 'OpenLiteSpeed',
				'raw'   => $software,
			);
		}

		if ( false !== strpos( $lower, 'litespeed' ) ) {
			return array(
				'slug'  => 'litespeed',
				'label' => 'LiteSpeed',
				'raw'   => $software,
			);
		}

		if ( false !== strpos( $lower, 'apache' ) ) {
			return array(
				'slug'  => 'apache',
				'label' => 'Apache',
				'raw'   => $software,
			);
		}

		if ( false !== strpos( $lower, 'nginx' ) ) {
			return array(
				'slug'  => 'nginx',
				'label' => 'Nginx',
				'raw'   => $software,
			);
		}

		return array(
			'slug'  => 'unknown',
			'label' => __( 'Sconosciuto', 'marrison-addon' ),
			'raw'   => $software,
		);
	}

	public function get_method() {
		$server = $this->detect_server();

		if ( in_array( $server['slug'], array( 'apache', 'litespeed' ), true ) ) {
			return array(
				'slug'  => 'htaccess',
				'label' => '.htaccess',
			);
		}

		if ( 'nginx' === $server['slug'] ) {
			return array(
				'slug'  => 'nginx_manual',
				'label' => __( 'Configurazione manuale Nginx', 'marrison-addon' ),
			);
		}

		return array(
			'slug'  => 'manual',
			'label' => __( 'Configurazione server manuale', 'marrison-addon' ),
		);
	}

	public function get_asset_extensions( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : $this->get_settings();
		$types    = isset( $settings['asset_types'] ) && is_array( $settings['asset_types'] ) ? $settings['asset_types'] : array();
		$map      = array(
			'css'    => array( 'css' ),
			'js'     => array( 'js' ),
			'fonts'  => array( 'woff', 'woff2', 'ttf', 'otf' ),
			'images' => array( 'svg', 'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'ico' ),
		);
		$exts     = array();

		foreach ( $map as $type => $extensions ) {
			if ( empty( $types[ $type ] ) ) {
				continue;
			}

			$exts = array_merge( $exts, $extensions );
		}

		return array_values( array_unique( $exts ) );
	}

	public function get_asset_type_from_url( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$ext  = $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';

		if ( 'css' === $ext ) {
			return 'css';
		}

		if ( 'js' === $ext ) {
			return 'js';
		}

		if ( in_array( $ext, array( 'woff', 'woff2', 'ttf', 'otf' ), true ) ) {
			return 'fonts';
		}

		if ( in_array( $ext, array( 'svg', 'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'ico' ), true ) ) {
			return 'images';
		}

		return '';
	}

	public function is_static_asset_url( $url, $settings = null ) {
		$type = $this->get_asset_type_from_url( $url );

		if ( '' === $type ) {
			return false;
		}

		$settings = is_array( $settings ) ? $settings : $this->get_settings();
		$types    = isset( $settings['asset_types'] ) && is_array( $settings['asset_types'] ) ? $settings['asset_types'] : array();

		return ! empty( $types[ $type ] );
	}

	public function is_versioned_url( $url ) {
		$query = wp_parse_url( $url, PHP_URL_QUERY );

		if ( $query ) {
			wp_parse_str( $query, $query_args );

			foreach ( array( 'ver', 'v', 'version' ) as $key ) {
				if ( isset( $query_args[ $key ] ) && is_scalar( $query_args[ $key ] ) && '' !== (string) $query_args[ $key ] ) {
					return true;
				}
			}
		}

		$path     = wp_parse_url( $url, PHP_URL_PATH );
		$filename = $path ? basename( $path ) : '';

		return 1 === preg_match( '/[._-](?:v?[0-9]+(?:[.][0-9]+){1,}|[a-f0-9]{8,})[.][a-z0-9]+$/i', $filename );
	}

	public function get_expected_cache_control( $url, $settings = null ) {
		$settings   = is_array( $settings ) ? $settings : $this->get_settings();
		$versioned  = $this->is_versioned_url( $url );
		$ttl        = $versioned ? absint( $settings['versioned_ttl'] ) : absint( $settings['unversioned_ttl'] );
		$directives = array(
			'public',
			'max-age=' . $ttl,
		);

		if ( $versioned && ! empty( $settings['immutable_versioned'] ) ) {
			$directives[] = 'immutable';
		}

		return implode( ', ', $directives );
	}

	public function get_htaccess_path() {
		if ( ! function_exists( 'get_home_path' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$home_path = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
		$path      = trailingslashit( $home_path ) . '.htaccess';

		return function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $path ) : $path;
	}

	public function htaccess_has_rules() {
		$path = $this->get_htaccess_path();

		if ( ! is_readable( $path ) ) {
			return false;
		}

		$contents = file_get_contents( $path );

		return is_string( $contents ) && false !== strpos( $contents, self::BEGIN_MARKER ) && false !== strpos( $contents, self::END_MARKER );
	}

	public function cleanup_htaccess_backups( $keep = '' ) {
		$dir     = dirname( $this->get_htaccess_path() );
		$entries = scandir( $dir );

		if ( false === $entries ) {
			return new WP_Error( 'htaccess_backup_scan_failed', __( 'Impossibile leggere la directory dei backup Browser Cache.', 'marrison-addon' ) );
		}

		$backups = array();
		foreach ( $entries as $entry ) {
			if ( ! preg_match( '/^\.htaccess\.marrison-browser-cache-[0-9]{8}-[0-9]{6}\.bak$/D', $entry ) ) {
				continue;
			}
			$file = trailingslashit( $dir ) . $entry;
			if ( is_file( $file ) && ! is_link( $file ) ) {
				$backups[] = $file;
			}
		}

		// Timestamp names sort chronologically; retain the newest existing backup on upgrade.
		rsort( $backups, SORT_STRING );
		if ( '' === $keep && ! empty( $backups ) ) {
			$keep = $backups[0];
		}
		if ( '' !== $keep && ! in_array( $keep, $backups, true ) ) {
			return new WP_Error( 'htaccess_backup_missing', __( 'Backup Browser Cache da conservare non trovato. Nessun vecchio backup eliminato.', 'marrison-addon' ) );
		}
		foreach ( $backups as $backup ) {
			if ( $backup !== $keep && ! unlink( $backup ) ) {
				return new WP_Error( 'htaccess_backup_cleanup_failed', __( 'Impossibile eliminare un vecchio backup Browser Cache. Controlla i permessi della directory principale.', 'marrison-addon' ) );
			}
		}

		return true;
	}

	public function apply_htaccess_rules( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : $this->get_settings();
		$rules    = $this->build_htaccess_rules( $settings );

		if ( is_wp_error( $rules ) ) {
			return $rules;
		}

		$path = $this->get_htaccess_path();
		$dir  = dirname( $path );

		if ( file_exists( $path ) && ! is_writable( $path ) ) {
			return new WP_Error(
				'htaccess_not_writable',
				__( '.htaccess non e scrivibile. Copia manualmente le regole mostrate nella pagina del modulo.', 'marrison-addon' )
			);
		}

		if ( ! file_exists( $path ) && ! is_writable( $dir ) ) {
			return new WP_Error(
				'htaccess_dir_not_writable',
				__( 'La directory principale non e scrivibile. Copia manualmente le regole nel file .htaccess.', 'marrison-addon' )
			);
		}

		$existing = file_exists( $path ) ? file_get_contents( $path ) : '';

		if ( false === $existing ) {
			return new WP_Error(
				'htaccess_read_failed',
				__( 'Impossibile leggere il file .htaccess esistente.', 'marrison-addon' )
			);
		}

		$updated = $this->replace_marker_block( (string) $existing, $rules );
		if ( 1 !== substr_count( $updated, self::BEGIN_MARKER ) || 1 !== substr_count( $updated, self::END_MARKER ) ) {
			return new WP_Error( 'htaccess_incomplete_block', __( 'Marker Marrison incompleti o duplicati: controlla .htaccess prima di applicare le regole.', 'marrison-addon' ) );
		}

		if ( $updated === (string) $existing ) {
			$cleanup = $this->cleanup_htaccess_backups();
			if ( is_wp_error( $cleanup ) ) {
				return $cleanup;
			}
			$this->update_state(
				array(
					'status'        => 'pending_verification',
					'message'       => __( 'Regole .htaccess gia aggiornate. Verifica gli header reali per confermarne l effetto.', 'marrison-addon' ),
					'settings_hash' => $this->get_settings_hash( $settings ),
				)
			);

			return true;
		}

		$backup = '';
		if ( file_exists( $path ) ) {
			$backup = trailingslashit( $dir ) . '.htaccess.marrison-browser-cache-' . gmdate( 'Ymd-His' ) . '.bak';

			if ( ! copy( $path, $backup ) ) {
				return new WP_Error(
					'htaccess_backup_failed',
					__( 'Backup di sicurezza .htaccess non riuscito. Nessuna modifica applicata.', 'marrison-addon' )
				);
			}
		}

		if ( strlen( $updated ) !== file_put_contents( $path, $updated, LOCK_EX ) || file_get_contents( $path ) !== $updated ) {
			return new WP_Error(
				'htaccess_write_failed',
				__( 'Scrittura .htaccess non riuscita. Copia manualmente le regole mostrate nella pagina del modulo.', 'marrison-addon' )
			);
		}

		$cleanup = $this->cleanup_htaccess_backups( $backup );
		if ( is_wp_error( $cleanup ) ) {
			return $cleanup;
		}

		$this->update_state(
			array(
				'status'        => 'pending_verification',
				'message'       => __( 'Regole .htaccess Marrison scritte. Esegui Verifica cache browser per confermare gli header effettivi.', 'marrison-addon' ),
				'settings_hash' => $this->get_settings_hash( $settings ),
			)
		);

		return true;
	}

	public function remove_htaccess_rules() {
		$path = $this->get_htaccess_path();

		if ( ! file_exists( $path ) ) {
			$this->update_state(
				array(
					'status'  => 'inactive',
					'message' => __( 'Modulo disattivato. Nessun file .htaccess trovato.', 'marrison-addon' ),
				)
			);

			return true;
		}

		if ( ! is_readable( $path ) ) {
			return new WP_Error(
				'htaccess_not_readable',
				__( 'Impossibile leggere .htaccess per rimuovere le regole Marrison.', 'marrison-addon' )
			);
		}

		$existing = file_get_contents( $path );

		if ( false === $existing ) {
			return new WP_Error( 'htaccess_read_failed', __( 'Impossibile leggere .htaccess: rimozione non verificata.', 'marrison-addon' ) );
		}
		if ( ( false !== strpos( $existing, self::BEGIN_MARKER ) ) !== ( false !== strpos( $existing, self::END_MARKER ) ) ) {
			return new WP_Error( 'htaccess_incomplete_block', __( 'Blocco Marrison incompleto: controlla entrambi i marker prima della rimozione.', 'marrison-addon' ) );
		}
		if ( false === strpos( $existing, self::BEGIN_MARKER ) ) {
			$this->update_state(
				array(
					'status'  => 'inactive',
					'message' => __( 'Modulo disattivato. Nessun blocco Marrison da rimuovere.', 'marrison-addon' ),
				)
			);

			return true;
		}

		if ( ! is_writable( $path ) ) {
			return new WP_Error( 'htaccess_not_writable', __( '.htaccess non e scrivibile. Rimuovi manualmente solo il blocco Marrison Browser Cache.', 'marrison-addon' ) );
		}
		$updated = $this->remove_marker_block( (string) $existing );
		if ( false !== strpos( $updated, self::BEGIN_MARKER ) || false !== strpos( $updated, self::END_MARKER ) ) {
			return new WP_Error( 'htaccess_incomplete_block', __( 'Blocco Marrison incompleto: rimozione automatica interrotta. Controlla i marker in .htaccess.', 'marrison-addon' ) );
		}

		if ( strlen( $updated ) !== file_put_contents( $path, $updated, LOCK_EX ) || file_get_contents( $path ) !== $updated ) {
			return new WP_Error(
				'htaccess_write_failed',
				__( 'Impossibile aggiornare .htaccess durante la rimozione del blocco Marrison.', 'marrison-addon' )
			);
		}

		$this->update_state(
			array(
				'status'  => 'inactive',
				'message' => __( 'Blocco .htaccess Marrison rimosso.', 'marrison-addon' ),
			)
		);

		return true;
	}

	public function build_htaccess_rules( $settings = null ) {
		$settings   = is_array( $settings ) ? $settings : $this->get_settings();
		$extensions = $this->get_asset_extensions( $settings );

		if ( empty( $extensions ) ) {
			return new WP_Error(
				'no_asset_types',
				__( 'Seleziona almeno una tipologia di asset.', 'marrison-addon' )
			);
		}

		$extension_regex = $this->get_extension_regex( $extensions );
		$static_regex    = '[.](?:' . $extension_regex . ')$';
		$filename_regex  = '[._-](?:v?[0-9]+(?:[.][0-9]+){1,}|[a-f0-9]{8,})[.](?:' . $extension_regex . ')$';
		$version_expr    = "(%{QUERY_STRING} =~ m#(?:^|&)(?:ver|v|version)=[^&]+#i || %{REQUEST_URI} =~ m#" . $filename_regex . '#i)';
		$static_expr     = "(%{REQUEST_METHOD} == 'GET' || %{REQUEST_METHOD} == 'HEAD') && (%{REQUEST_STATUS} == 200 || %{REQUEST_STATUS} == 206 || %{REQUEST_STATUS} == 304) && -f %{REQUEST_FILENAME} && %{REQUEST_FILENAME} =~ m#" . $static_regex . "#i && %{REQUEST_URI} !~ m#(?:^|/)(?:wp-admin|wp-json|wp-login[.]php)(?:/|$)#i && (%{HANDLER} == '' || %{HANDLER} == 'default-handler') && -z resp('Cache-Control')";
		$versioned_ttl   = absint( $settings['versioned_ttl'] );
		$unversioned_ttl = absint( $settings['unversioned_ttl'] );
		$versioned_cc    = 'public, max-age=' . $versioned_ttl . ( ! empty( $settings['immutable_versioned'] ) ? ', immutable' : '' );
		$unversioned_cc  = 'public, max-age=' . $unversioned_ttl;
		$lines           = array(
			self::BEGIN_MARKER,
			'# Apache 2.4: solo file statici esistenti, GET/HEAD e risposte 200/206/304.',
			'# QUERY_STRING viene verificata separatamente: Request_URI non contiene ?ver=.',
			'# Mantiene le politiche Cache-Control esistenti, incluse private/no-store.',
			'<IfModule mod_headers.c>',
			'    Header set Cache-Control "' . $unversioned_cc . '" "expr=' . $static_expr . ' && !' . $version_expr . '"',
			'    Header set Cache-Control "' . $versioned_cc . '" "expr=' . $static_expr . ' && ' . $version_expr . '"',
			'</IfModule>',
			self::END_MARKER,
		);

		return implode( "\n", $lines );
	}

	public function build_nginx_config( $settings = null ) {
		$settings   = is_array( $settings ) ? $settings : $this->get_settings();
		$extensions = $this->get_asset_extensions( $settings );

		if ( empty( $extensions ) ) {
			return __( 'Seleziona almeno una tipologia di asset per generare la configurazione.', 'marrison-addon' );
		}

		$extension_regex = $this->get_extension_regex( $extensions );
		$versioned_cc    = 'public, max-age=' . absint( $settings['versioned_ttl'] ) . ( ! empty( $settings['immutable_versioned'] ) ? ', immutable' : '' );
		$unversioned_cc  = 'public, max-age=' . absint( $settings['unversioned_ttl'] );
		$version_regex   = '(?:[?&](?:ver|v|version)=[^&#]+|[._-](?:v?[0-9]+(?:[.][0-9]+){1,}|[a-f0-9]{8,})[.](?:' . $extension_regex . ')(?:[?]|$))';
		$mime_regex      = implode( '|', array_map( function ( $mime ) { return preg_quote( $mime, '~' ); }, $this->get_mime_types_for_settings( $settings ) ) );
		$response_regex  = '(?:(?:200|206):(?:' . $mime_regex . ')(?:;[^:]*)?|304:(?:(?:' . $mime_regex . ')(?:;[^:]*)?)?)';
		$eligible_regex  = '^(?:GET|HEAD):' . $response_regex . '::/(?!wp-admin(?:/|$)|wp-json(?:/|$)|wp-login[.]php(?:/|$))(?!.*[.]php(?:/|$)).*[.](?:' . $extension_regex . ')$';

		return implode(
			"\n",
			array(
				'# Marrison Browser Cache - Nginx',
				'# PARTE 1: solo contesto http, una volta. Non incollare map nelle direttive server Plesk.',
				'# La presenza di ?ver= non garantisce che la versione cambi con ogni modifica.',
				'map $request_uri $marrison_browser_cache_control {',
				'    default "' . $unversioned_cc . '";',
				'    "~*' . $version_regex . '" "' . $versioned_cc . '";',
				'}',
				'',
				'# Non aggiunge policy a errori, HTML/dinamici o risposte gia dotate di Cache-Control.',
				'map "$request_method:$status:$sent_http_content_type:$sent_http_cache_control:$uri" $marrison_browser_cache_header {',
				'    default "";',
				'    "~*' . $eligible_regex . '" $marrison_browser_cache_control;',
				'}',
				'',
				'# PARTE 2: integra nella location GIA ESISTENTE che serve i file statici pubblici.',
				'# Mantieni try_files, root/alias, protezioni e gli altri header di quella location.',
				'# add_header richiede di preservare gli header ereditati (security, CORS, Vary).',
				'# Non creare una location concorrente alle regole generate da Plesk.',
				'expires off;',
				'add_header Cache-Control $marrison_browser_cache_header;',
				'# Nessun always e un solo max-age: Cache-Control basta senza Expires.',
				'# Dopo nginx -t e reload, eseguire Verifica cache browser nel modulo.',
			)
		);
	}

	public function seconds_to_human_label( $seconds ) {
		$seconds = absint( $seconds );

		if ( $seconds >= YEAR_IN_SECONDS && 0 === $seconds % YEAR_IN_SECONDS ) {
			return sprintf(
				/* translators: %d: years. */
				_n( '%d anno', '%d anni', $seconds / YEAR_IN_SECONDS, 'marrison-addon' ),
				$seconds / YEAR_IN_SECONDS
			);
		}

		if ( $seconds >= DAY_IN_SECONDS && 0 === $seconds % DAY_IN_SECONDS ) {
			return sprintf(
				/* translators: %d: days. */
				_n( '%d giorno', '%d giorni', $seconds / DAY_IN_SECONDS, 'marrison-addon' ),
				$seconds / DAY_IN_SECONDS
			);
		}

		if ( $seconds >= HOUR_IN_SECONDS && 0 === $seconds % HOUR_IN_SECONDS ) {
			return sprintf(
				/* translators: %d: hours. */
				_n( '%d ora', '%d ore', $seconds / HOUR_IN_SECONDS, 'marrison-addon' ),
				$seconds / HOUR_IN_SECONDS
			);
		}

		return sprintf(
			/* translators: %d: seconds. */
			_n( '%d secondo', '%d secondi', $seconds, 'marrison-addon' ),
			$seconds
		);
	}

	private function get_extension_regex( $extensions ) {
		$quoted = array_map(
			function ( $extension ) {
				return preg_quote( $extension, '/' );
			},
			$extensions
		);

		return implode( '|', $quoted );
	}

	private function get_mime_types_for_settings( $settings ) {
		$types = isset( $settings['asset_types'] ) && is_array( $settings['asset_types'] ) ? $settings['asset_types'] : array();
		$mimes = array();

		if ( ! empty( $types['css'] ) ) {
			$mimes = array_merge( $mimes, array( 'text/css' ) );
		}

		if ( ! empty( $types['js'] ) ) {
			$mimes = array_merge( $mimes, array( 'application/javascript', 'text/javascript', 'application/x-javascript' ) );
		}

		if ( ! empty( $types['fonts'] ) ) {
			$mimes = array_merge(
				$mimes,
				array(
					'font/woff2',
					'font/woff',
					'application/font-woff2',
					'application/font-woff',
					'font/ttf',
					'application/x-font-ttf',
					'font/otf',
					'application/x-font-opentype',
				)
			);
		}

		if ( ! empty( $types['images'] ) ) {
			$mimes = array_merge(
				$mimes,
				array(
					'image/svg+xml',
					'image/png',
					'image/jpeg',
					'image/webp',
					'image/avif',
					'image/gif',
					'image/x-icon',
					'image/vnd.microsoft.icon',
				)
			);
		}

		return array_values( array_unique( $mimes ) );
	}

	private function replace_marker_block( $contents, $block ) {
		$without_block = $this->remove_marker_block( $contents );
		$without_block = rtrim( $without_block );

		if ( '' !== $without_block ) {
			$without_block .= "\n\n";
		}

		return $without_block . $block . "\n";
	}

	private function remove_marker_block( $contents ) {
		$pattern = '/' . preg_quote( self::BEGIN_MARKER, '/' ) . '.*?' . preg_quote( self::END_MARKER, '/' ) . '\s*/s';

		return preg_replace( $pattern, '', $contents );
	}

	private function seconds_to_nginx_duration( $seconds ) {
		$seconds = max( 1, absint( $seconds ) );

		if ( 0 === $seconds % YEAR_IN_SECONDS ) {
			return ( $seconds / YEAR_IN_SECONDS ) . 'y';
		}

		if ( 0 === $seconds % DAY_IN_SECONDS ) {
			return ( $seconds / DAY_IN_SECONDS ) . 'd';
		}

		if ( 0 === $seconds % HOUR_IN_SECONDS ) {
			return ( $seconds / HOUR_IN_SECONDS ) . 'h';
		}

		if ( 0 === $seconds % MINUTE_IN_SECONDS ) {
			return ( $seconds / MINUTE_IN_SECONDS ) . 'm';
		}

		return $seconds . 's';
	}
}
