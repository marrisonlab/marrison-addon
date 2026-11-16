<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reverse lookup for uploads paths, including arbitrary names in legacy image metadata.
 * WordPress indexes postmeta by key and post ID, but not by file path.
 */
class Marrison_Addon_Image_Path_Index {
	const READY_OPTION = 'marrison_addon_image_path_index_v1';
	const CURSOR_OPTION = 'marrison_addon_image_path_index_cursor';
	const CRON_HOOK = 'marrison_addon_build_image_path_index';
	const BATCH_SIZE = 100;

	public function register_hooks() {
		add_action( 'init', [ $this, 'schedule_build' ], 10, 0 );
		add_action( self::CRON_HOOK, [ $this, 'build_batch' ] );
		add_action( 'update_option_marrison_addon_modules', [ $this, 'reset_cursor_on_disable' ], 10, 2 );
	}

	public function schedule_build( $delay = 5 ) {
		if ( -1 !== (int) get_option( self::CURSOR_OPTION, 0 ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + $delay, self::CRON_HOOK );
		}
	}

	public function reset_cursor_on_disable( $old, $new ) {
		if ( ! empty( $old['image_sizes'] ) && empty( $new['image_sizes'] ) ) {
			delete_option( self::CURSOR_OPTION );
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	public function build_batch() {
		global $wpdb;
		$table = $wpdb->prefix . 'marrison_image_paths';
		$lock = md5( DB_NAME . '/' . $table . '/build' );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return;
		}

		try {
			// Schedule the retry before doing any work so an interrupted batch resumes.
			$this->schedule_build( 15 );
			if ( ! get_option( self::READY_OPTION ) ) {
				$created = $wpdb->query(
					"CREATE TABLE IF NOT EXISTS `{$table}` (
						path_hash binary(32) NOT NULL,
						attachment_id bigint unsigned NOT NULL,
						priority tinyint unsigned NOT NULL,
						PRIMARY KEY (path_hash, attachment_id),
						KEY attachment_id (attachment_id)
					) " . $wpdb->get_charset_collate()
				);
				if ( false === $created ) {
					return;
				}
				update_option( self::READY_OPTION, 1, false );
			}

			$cursor = (int) get_option( self::CURSOR_OPTION, 0 );
			if ( -1 === $cursor ) {
				return;
			}
			// Limit posts examined as well as attachments processed, including sparse ID ranges.
			$posts = $wpdb->get_results( $wpdb->prepare(
				"SELECT ID, post_type, post_mime_type FROM {$wpdb->posts} WHERE ID > %d ORDER BY ID LIMIT %d",
				$cursor,
				self::BATCH_SIZE
			) );
			if ( $wpdb->last_error ) {
				return;
			}
			foreach ( $posts as $post ) {
				if ( 'attachment' === $post->post_type && 0 === strpos( $post->post_mime_type, 'image/' ) && ! $this->sync_attachment( (int) $post->ID ) ) {
					update_option( self::CURSOR_OPTION, $cursor, false );
					return;
				}
				$cursor = (int) $post->ID;
			}

			update_option( self::CURSOR_OPTION, count( $posts ) < self::BATCH_SIZE ? -1 : $cursor, false );
			if ( count( $posts ) < self::BATCH_SIZE ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
				do_action( 'marrison_addon/image_sizes/path_index_built' );
			}
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	public function refresh_attachment( $attachment_id, $deleted = false ) {
		if ( $this->sync_attachment( $attachment_id, $deleted ) ) {
			return;
		}

		$cursor = (int) get_option( self::CURSOR_OPTION, 0 );
		if ( ! $deleted && ( -1 === $cursor || $cursor >= $attachment_id ) ) {
			update_option( self::CURSOR_OPTION, $attachment_id - 1, false );
			$this->schedule_build();
		}
	}

	public function sync_attachment( $attachment_id, $deleted = false ) {
		if ( ! get_option( self::READY_OPTION ) ) {
			return true;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'marrison_image_paths';
		$lock = md5( DB_NAME . '/' . $table . '/' . $attachment_id );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 2)', $lock ) ) ) {
			return false;
		}
		try {
			// Read the current rows after taking the lock so concurrent writes converge on disk state.
			wp_cache_delete( $attachment_id, 'post_meta' );
			$paths = ! $deleted && wp_attachment_is_image( $attachment_id ) ? $this->attachment_paths( $attachment_id ) : [];
			$rows = [];
			$hashes = [];
			foreach ( $paths as $path => $priority ) {
				$hash = hash( 'sha256', $path );
				$rows[] = $wpdb->prepare( '(UNHEX(%s), %d, %d)', $hash, $attachment_id, $priority );
				$hashes[] = $wpdb->prepare( 'UNHEX(%s)', $hash );
			}
			if ( $rows && false === $wpdb->query(
				"INSERT INTO `{$table}` (path_hash, attachment_id, priority) VALUES " . implode( ',', $rows ) .
				' ON DUPLICATE KEY UPDATE priority = VALUES(priority)'
			) ) {
				return false;
			}
			$obsolete = $hashes ? ' AND path_hash NOT IN (' . implode( ',', $hashes ) . ')' : '';
			return false !== $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE attachment_id = %d", $attachment_id ) . $obsolete );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/** Relative paths as stored by WordPress, with the primary file preferred over intermediates. */
	public function attachment_paths( $id, $metadata = null ) {
		if ( null === $metadata ) {
			$metadata = wp_get_attachment_metadata( $id );
		}
		$paths = [];
		$attached = get_post_meta( $id, '_wp_attached_file', true );
		$uploads = wp_get_upload_dir();
		$basedir = trailingslashit( str_replace( '\\', '/', $uploads['basedir'] ) );
		foreach ( [ $attached, $metadata['file'] ?? '' ] as $file ) {
			$file = str_replace( '\\', '/', (string) $file );
			if ( 0 === strpos( $file, $basedir ) ) {
				$file = substr( $file, strlen( $basedir ) );
			}
			if ( self::valid_relative_path( $file ) ) {
				$paths[ $file ] = 2;
			}
		}
		$full = $metadata['file'] ?? '';
		if ( ! self::valid_relative_path( $full ) ) {
			return $paths;
		}

		$directory = dirname( $full );
		$prefix = '.' === $directory ? '' : $directory . '/';
		$variants = [
			$metadata['original_image'] ?? '',
			$metadata['marrison_webp_full']['file'] ?? '',
			$metadata['marrison_avif_full']['file'] ?? '',
		];
		foreach ( (array) ( $metadata['sizes'] ?? [] ) as $size ) {
			$variants[] = $size['file'] ?? '';
			foreach ( (array) ( $size['sources'] ?? [] ) as $source ) {
				$variants[] = $source['file'] ?? '';
			}
		}
		foreach ( $variants as $file ) {
			if ( self::valid_relative_path( $file ) && ! isset( $paths[ $prefix . $file ] ) ) {
				$paths[ $prefix . $file ] = 1;
			}
		}
		return $paths;
	}

	public static function valid_relative_path( $path ) {
		return is_string( $path ) && '' !== $path && '/' !== $path[0]
			&& ! preg_match( '~(?:^|/)\.{1,2}(?:/|$)|[\x00-\x1f\\\\:]~', $path );
	}

	public function find_attachment( $path ) {
		$candidates = [ $path ];
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( in_array( $extension, [ 'webp', 'avif' ], true ) ) {
			$stem = substr( $path, 0, -strlen( $extension ) );
			foreach ( [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' ] as $alternate ) {
				if ( $alternate !== $extension ) {
					$candidates[] = $stem . $alternate;
				}
			}
		}

		global $wpdb;
		if ( get_option( self::READY_OPTION ) ) {
			$table = $wpdb->prefix . 'marrison_image_paths';
			$hashes = array_map( static function ( $file ) use ( $wpdb ) {
				return $wpdb->prepare( 'UNHEX(%s)', hash( 'sha256', $file ) );
			}, $candidates );
			$ids = $wpdb->get_col(
				"SELECT attachment_id FROM `{$table}` WHERE path_hash IN (" . implode( ',', $hashes ) .
				") ORDER BY path_hash = {$hashes[0]} DESC, priority DESC, attachment_id DESC LIMIT 16"
			);
			if ( $ids ) {
				$id = $this->match_candidates( $ids, $candidates );
				if ( $id ) {
					return $id;
				}
			}
		}

		if ( -1 === (int) get_option( self::CURSOR_OPTION, 0 ) ) {
			return 0;
		}

		// Before the background build finishes, try normal WordPress names via the indexed post_name.
		$stem = pathinfo( $path, PATHINFO_FILENAME );
		$plain_stem = preg_replace( '~(?:-\d+x\d+|-scaled|-rotated|-e\d{13})+$~i', '', $stem );
		$without_counter = preg_replace( '/-\d+$/', '', $stem );
		$names = [ $stem, $plain_stem ];
		foreach ( wp_get_registered_image_subsizes() as $slug => $size ) {
			$suffix = '-' . $slug;
			foreach ( [ $stem, $without_counter ] as $name ) {
				if ( substr( $name, -strlen( $suffix ) ) === $suffix ) {
					$names[] = substr( $name, 0, -strlen( $suffix ) );
				}
			}
		}
		$slugs = array_values( array_unique( array_filter( array_map( 'sanitize_title', $names ) ) ) );
		if ( ! $slugs ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} FORCE INDEX (post_name)
			WHERE post_type = 'attachment' AND post_name IN ({$placeholders}) LIMIT 24",
			$slugs
		) );
		return $this->match_candidates( $ids, $candidates );
	}

	private function match_candidates( array $ids, array $paths ) {
		$best_id = 0;
		$best_priority = 0;
		foreach ( array_unique( $ids ) as $id ) {
			if ( ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			$known = $this->attachment_paths( $id );
			foreach ( $paths as $offset => $path ) {
				if ( isset( $known[ $path ] ) ) {
					$priority = ( 0 === $offset ? 2 : 0 ) + $known[ $path ];
					if ( $priority > $best_priority ) {
						$best_id = (int) $id;
						$best_priority = $priority;
					}
					break;
				}
			}
			if ( 4 === $best_priority ) {
				break;
			}
		}
		return $best_id;
	}
}
