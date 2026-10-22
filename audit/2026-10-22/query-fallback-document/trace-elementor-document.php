<?php
/**
 * Temporary diagnostic: copy directly to wp-content/mu-plugins/, reproduce,
 * collect [MARRISON-DOCUMENT-TRACE] lines from debug.log, then remove this file.
 * No settings, content or request data are changed or recorded.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'plugins_loaded', static function () {
	$previous = null;
	$seen = [];
	$previous = set_error_handler( static function ( $severity, $message, $file, $line ) use ( &$previous, &$seen ) {
		$normalized = str_replace( '\\', '/', $file );
		if ( E_WARNING === $severity
			&& false !== strpos( $message, 'Attempt to read property "ID" on null' )
			&& preg_match( '~/elementor/core/base/document\.php$~', $normalized ) ) {
			$frames = [];
			$root = rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) . '/';
			foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 ) as $frame ) {
				$path = str_replace( '\\', '/', $frame['file'] ?? '' );
				if ( 0 === strpos( $path, $root ) ) {
					$path = substr( $path, strlen( $root ) );
				} else {
					$path = basename( $path );
				}
				$frames[] = [
					'call' => ( $frame['class'] ?? '' ) . ( $frame['type'] ?? '' ) . ( $frame['function'] ?? '' ),
					'file' => $path,
					'line' => $frame['line'] ?? 0,
				];
			}
			$signature = md5( json_encode( $frames ) );
			if ( ! isset( $seen[ $signature ] ) && count( $seen ) < 5 ) {
				$seen[ $signature ] = true;
				error_log( '[MARRISON-DOCUMENT-TRACE] ' . json_encode( [
					'addon_version' => class_exists( 'Marrison_Addon', false ) ? Marrison_Addon::VERSION : null,
					'frames' => $frames,
				] ) );
			}
		}
		// Preserve the installed handler and the original warning.
		return is_callable( $previous ) ? call_user_func( $previous, $severity, $message, $file, $line ) : false;
	} );
}, PHP_INT_MAX );
