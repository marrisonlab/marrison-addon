<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Read-only, local in-memory PHP sources. No eval, disk files or URL includes. */
final class Marrison_Addon_PHP_Snippets_Stream {
	const SCHEME = 'marrison-php-snippet';
	public $context;
	private static $sources = [];
	private static $registered = false;
	private $source = '';
	private $position = 0;

	public static function available() {
		if ( self::$registered ) {
			return true;
		}
		if ( ! function_exists( 'stream_wrapper_register' ) || in_array( self::SCHEME, stream_get_wrappers(), true ) ) {
			return false;
		}
		self::$registered = stream_wrapper_register( self::SCHEME, __CLASS__ );
		return self::$registered;
	}

	public static function put( $id, $revision, $code ) {
		$uri = self::SCHEME . '://' . (int) $id . '/' . $revision . '.php';
		self::$sources[ $uri ] = '<?php ' . $code;
		return $uri;
	}

	public static function forget( $uri ) {
		unset( self::$sources[ $uri ] );
	}

	public function stream_open( $path, $mode, $options, &$opened_path ) {
		if ( 'rb' !== $mode && 'r' !== $mode || ! isset( self::$sources[ $path ] ) ) {
			return false;
		}
		$this->source = self::$sources[ $path ];
		$this->position = 0;
		$opened_path = $path;
		return true;
	}

	public function stream_read( $count ) {
		$result = substr( $this->source, $this->position, $count );
		$this->position += strlen( $result );
		return $result;
	}

	public function stream_eof() {
		return $this->position >= strlen( $this->source );
	}

	public function stream_tell() {
		return $this->position;
	}

	public function stream_seek( $offset, $whence = SEEK_SET ) {
		$position = SEEK_SET === $whence ? $offset : ( SEEK_CUR === $whence ? $this->position + $offset : strlen( $this->source ) + $offset );
		if ( $position < 0 || $position > strlen( $this->source ) ) {
			return false;
		}
		$this->position = $position;
		return true;
	}

	public function stream_stat() {
		return [ 'mode' => 0100444, 'size' => strlen( $this->source ) ];
	}

	public function url_stat( $path, $flags ) {
		return isset( self::$sources[ $path ] ) ? [ 'mode' => 0100444, 'size' => strlen( self::$sources[ $path ] ) ] : false;
	}

	public function stream_set_option( $option, $arg1, $arg2 ) {
		return false;
	}
}

final class Marrison_Addon_PHP_Snippets_Runtime {
	private static $started = false;
	private $store;
	private $loaded = [];
	private $executing = null;
	private $reserve;

	public function __construct( Marrison_Addon_PHP_Snippets_Store $store ) {
		$this->store = $store;
	}

	public static function bypass_request() {
        if ( Marrison_Addon_PHP_Snippets_Security::safe_mode() ) {
			return true;
		}
		if ( function_exists( 'wp_is_recovery_mode' ) && wp_is_recovery_mode() ) {
			return true;
		}
        $script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? ''));
		if ( 'wp-login.php' === $script ) {
			return true;
		}
		if ( ! is_admin() ) {
			return false;
		}
        // A query string cannot turn admin-ajax.php/admin-post.php into admin.php.
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? $_GET['page'] : '';
		$action = isset( $_POST['action'] ) && is_string( $_POST['action'] ) ? $_POST['action'] : '';
        if ($script === 'admin.php') { return in_array($page, array('marrison_addon_panel', 'marrison_addon_php_snippets'), true); }
        if (!in_array($script, array('admin-ajax.php', 'admin-post.php'), true) || !did_action('init')
            || !function_exists('is_user_logged_in') || !is_user_logged_in() || !Marrison_Addon_PHP_Snippets_Store::can_manage()) { return false; }
        if ($action === 'marrison_save_option') {
            return isset($_POST['nonce']) && is_string($_POST['nonce']) && wp_verify_nonce(wp_unslash($_POST['nonce']), 'marrison_save_option_nonce');
        }
        $operation = isset($_POST['operation']) && is_string($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
        $id = isset($_POST['id']) && is_scalar($_POST['id']) ? absint($_POST['id']) : 0;
        return $action === 'marrison_php_snippets' && isset($_POST['_wpnonce']) && is_string($_POST['_wpnonce'])
            && wp_verify_nonce(wp_unslash($_POST['_wpnonce']), 'marrison_php_snippets_' . $operation . '_' . $id);
	}

	public function run() {
        $script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? ''));
        if (!self::$started && is_admin() && in_array($script, array('admin-ajax.php', 'admin-post.php'), true) && !did_action('init')) {
            add_action('init', array($this, 'run'), -9999); return;
        }
		if ( self::$started || self::bypass_request() ) {
			return;
		}
		self::$started = true;
		$rows = $this->store->active();
		if ( ! $rows || ! Marrison_Addon_PHP_Snippets_Stream::available() ) {
			return;
		}
		$this->reserve = str_repeat( 'x', 524288 );
		register_shutdown_function( [ $this, 'shutdown' ] );
		foreach ( $rows as $row ) {
            if (!Marrison_Addon_PHP_Snippets_Security::verify($row)) {
                $this->store->quarantine($row['id'], $row['revision'], Marrison_Addon_PHP_Snippets_Store::INTEGRITY_SUSPENSION);
                continue;
            }
			if ( ! in_array( $row['scope'], [ 'everywhere', 'frontend', 'backend' ], true ) ) {
				$this->store->quarantine( $row['id'], $row['revision'], 'Ambito di esecuzione non valido.' );
				continue;
			}
			if ( ( 'frontend' === $row['scope'] && is_admin() ) || ( 'backend' === $row['scope'] && ! is_admin() ) ) {
				continue;
			}
			$uri = Marrison_Addon_PHP_Snippets_Stream::put( $row['id'], $row['revision'], $row['code'] );
			$this->loaded[ $uri ] = [ 'id' => $row['id'], 'revision' => $row['revision'] ];
			$this->executing = $uri;
			try {
				self::include_source( $uri );
			} catch ( Throwable $error ) {
				$this->quarantine( $uri, $error->getMessage() . ' (riga ' . $error->getLine() . ')' );
				// Partially registered callbacks cannot safely be rolled back.
				throw $error;
			} finally {
				$this->executing = null;
				Marrison_Addon_PHP_Snippets_Stream::forget( $uri );
			}
		}
	}

	private static function include_source( $uri ) {
		require $uri;
	}

	private function quarantine( $uri, $message ) {
		if ( isset( $this->loaded[ $uri ] ) ) {
			$row = $this->loaded[ $uri ];
			$this->store->quarantine( $row['id'], $row['revision'], $message );
			unset( $this->loaded[ $uri ] );
		}
	}

	public function shutdown() {
		$this->reserve = null;
		$error = error_get_last();
		if ( ! $error || ! in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ], true ) ) {
			return;
		}
		$message = $error['message'] . ' (riga ' . $error['line'] . ')';
		if ( isset( $this->loaded[ $error['file'] ] ) ) {
			$this->quarantine( $error['file'], $message );
			return;
		}
		// An uncaught error in a dependency carries the calling snippet in its trace.
		if ( preg_match_all( '~marrison-php-snippet://[0-9]+/[a-zA-Z0-9-]+\.php~', $error['file'] . "\n" . $error['message'], $matches ) ) {
			foreach ( array_unique( $matches[0] ) as $uri ) {
				$this->quarantine( $uri, $message );
			}
			return;
		}
		if ( $this->executing ) {
			$this->quarantine( $this->executing, $message );
			return;
		}
		// PHP often omits traces for memory/time exhaustion in dependencies. Fail
		// closed for this request's snippets, without attributing an unknown culprit.
		if ( false !== strpos( $error['message'], 'Allowed memory size' ) || false !== strpos( $error['message'], 'Maximum execution time' ) ) {
			foreach ( array_keys( $this->loaded ) as $uri ) {
				$this->quarantine( $uri, 'Sospeso per esaurimento risorse della richiesta; origine non determinabile. ' . $message );
			}
		}
	}
}
