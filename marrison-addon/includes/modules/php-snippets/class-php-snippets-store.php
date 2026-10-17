<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Marrison_Addon_PHP_Snippets_Store {
	const SCHEMA_OPTION = 'marrison_addon_php_snippets_schema';
	const SCHEMA_VERSION = '1';

	public function table() {
		global $wpdb;
		return $wpdb->prefix . 'marrison_php_snippets';
	}

	public function ready() {
		return self::SCHEMA_VERSION === get_option( self::SCHEMA_OPTION );
	}

	public function install() {
		if ( $this->ready() ) {
			return true;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = $this->table();
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			code longtext NOT NULL,
			scope varchar(20) NOT NULL DEFAULT 'everywhere',
			active tinyint(1) NOT NULL DEFAULT 0,
			revision varchar(64) NOT NULL,
			last_error text NOT NULL,
			PRIMARY KEY  (id),
			KEY active (active)
		) $charset;" );
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return new WP_Error( 'storage', __( 'Impossibile creare la tabella PHP Snippets.', 'marrison-addon' ) );
		}
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, true );
		return true;
	}

	public static function can_manage() {
		// Do not replace these checks with role names or an is_super_admin shortcut.
		return current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
	}

	private static function new_revision() {
		// Quarantine also runs before WordPress loads pluggable.php/wp_rand().
		return bin2hex( random_bytes( 16 ) );
	}

	public static function normalize_code( $code ) {
		return preg_replace( '/\A\s*<\?php(?:\s|$)/i', '', $code, 1 );
	}

	public static function validate( $code ) {
		if ( ! is_string( $code ) || '' === trim( $code ) ) {
			return new WP_Error( 'code', __( 'Inserisci il codice PHP.', 'marrison-addon' ) );
		}
		if ( ! function_exists( 'token_get_all' ) || ! defined( 'TOKEN_PARSE' ) ) {
			return new WP_Error( 'tokenizer', __( 'Il tokenizer PHP non è disponibile: salvataggio e attivazione bloccati.', 'marrison-addon' ) );
		}
		try {
			$tokens = token_get_all( '<?php ' . $code, TOKEN_PARSE );
			foreach ( $tokens as $token ) {
				if ( is_array( $token ) && in_array( $token[0], [ T_CLOSE_TAG, T_INLINE_HTML, T_HALT_COMPILER ], true ) ) {
					return new WP_Error( 'tags', __( 'Usa solo codice PHP, senza tag di chiusura, HTML esterno o __halt_compiler().', 'marrison-addon' ) );
				}
			}
		} catch ( Throwable $error ) {
			return new WP_Error( 'syntax', sprintf( __( 'Errore PHP alla riga %1$d: %2$s', 'marrison-addon' ), $error->getLine(), $error->getMessage() ) );
		}
		return true;
	}

	public function active() {
		global $wpdb;
		if ( ! $this->ready() ) {
			return [];
		}
		// No persistent cache: a quarantined revision must disappear on the next request.
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, code, scope, revision FROM {$this->table()} WHERE active = 1 AND scope IN ('everywhere', %s) ORDER BY id", is_admin() ? 'backend' : 'frontend' ), ARRAY_A );
	}

	public function listing() {
		global $wpdb;
		return $wpdb->get_results( "SELECT id, name, scope, active, revision, last_error FROM {$this->table()} ORDER BY id", ARRAY_A );
	}

	public function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ), ARRAY_A );
	}

	public function save( $id, $name, $code, $scope, $expected_revision = '' ) {
		$valid = self::validate( $code );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( '' === $name || ! in_array( $scope, [ 'everywhere', 'frontend', 'backend' ], true ) ) {
			return new WP_Error( 'fields', __( 'Inserisci un nome e un ambito valido.', 'marrison-addon' ) );
		}
		global $wpdb;
		$data = [ 'name' => $name, 'code' => $code, 'scope' => $scope, 'active' => 0, 'last_error' => '', 'revision' => self::new_revision() ];
		if ( $id ) {
			$result = $wpdb->update( $this->table(), $data, [ 'id' => $id, 'revision' => $expected_revision ] );
		} else {
			$result = $wpdb->insert( $this->table(), $data );
			$id = (int) $wpdb->insert_id;
		}
		return 1 === $result ? $id : $this->write_error();
	}

	public function change_state( $id, $revision, $operation ) {
		global $wpdb;
		$row = $this->get( $id );
		if ( ! $row || ! hash_equals( $row['revision'], $revision ) ) {
			return $this->write_error();
		}
		if ( 'duplicate' === $operation ) {
			return $this->save( 0, wp_html_excerpt( $row['name'], 170, '' ) . ' (copia)', $row['code'], $row['scope'] );
		}
		$where = [ 'id' => $id, 'revision' => $revision ];
		if ( 'delete' === $operation ) {
			$result = $wpdb->delete( $this->table(), $where );
		} else {
			if ( 'activate' === $operation ) {
				$valid = self::validate( $row['code'] );
				if ( is_wp_error( $valid ) ) {
					$this->quarantine( $id, $revision, $valid->get_error_message() );
					return $valid;
				}
			} elseif ( 'deactivate' !== $operation ) {
				return new WP_Error( 'operation', __( 'Operazione non valida.', 'marrison-addon' ) );
			}
			$result = $wpdb->update( $this->table(), [ 'active' => 'activate' === $operation ? 1 : 0, 'last_error' => '', 'revision' => self::new_revision() ], $where );
		}
		return 1 === $result ? $id : $this->write_error();
	}

	public function quarantine( $id, $revision, $message ) {
		global $wpdb;
		// A failing concurrent request must never disable a newer edit/reactivation.
		$message = wp_check_invalid_utf8( substr( $message, 0, 4000 ), true );
		$data = [ 'active' => 0, 'last_error' => $message ?: 'Errore PHP: diagnostica non disponibile.', 'revision' => self::new_revision() ];
		$where = [ 'id' => $id, 'revision' => $revision ];
		$result = $wpdb->update( $this->table(), $data, $where );
		if ( false === $result ) {
			// A diagnostic rejected by the database charset must not prevent recovery.
			$data['last_error'] = 'Errore PHP: diagnostica non salvabile nel database.';
			$result = $wpdb->update( $this->table(), $data, $where );
		}
		return $result;
	}

	public function deactivate_all() {
		global $wpdb;
		return false !== $wpdb->query( $wpdb->prepare( "UPDATE {$this->table()} SET active = 0, revision = %s WHERE active = 1", self::new_revision() ) );
	}

	private function write_error() {
		return new WP_Error( 'storage', __( 'Operazione non completata: errore database oppure snippet modificato da un’altra richiesta. Ricarica la pagina.', 'marrison-addon' ) );
	}
}
