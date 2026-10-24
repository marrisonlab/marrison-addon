<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Updater {

	private const VERSION_TRANSIENT = 'marrison_addon_github_version';
	private const VERSION_FAILURE_TRANSIENT = 'marrison_addon_github_version_failure';
	private const INFO_TRANSIENT = 'marrison_addon_github_info_v3';
	private const CHECKSUM_TRANSIENT = 'marrison_addon_github_checksum';
	private const PACKAGE_TRANSIENT = 'marrison_addon_github_verified_package';

	private $slug; // plugin slug (e.g., marrison-addon/marrison-addon.php)
	private $plugin_file; // __FILE__ of our plugin
	private $username;
	private $repo;

	public function __construct( $plugin_file, $github_username, $github_repo ) {
		$this->plugin_file = $plugin_file;
		$this->username = $github_username;
		$this->repo = $github_repo;
		$this->slug = plugin_basename( $this->plugin_file );

		// Use site_transient_update_plugins to inject updates in real-time
		add_filter( 'site_transient_update_plugins', [ $this, 'check_update' ], 999 );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		
		// Cache cleaning hooks
		add_action( 'upgrader_process_complete', [ $this, 'clean_cache' ], 10, 2 );
		add_action( 'delete_site_transient_update_plugins', [ $this, 'clean_cache' ] );

		// Fix GitHub folder name issue
		add_filter( 'upgrader_source_selection', [ $this, 'fix_folder_name' ], 10, 4 );
		add_filter('upgrader_pre_download', array($this, 'verify_download'), 20, 4);
		add_action('admin_notices', array($this, 'checksum_notice'));
	}

	public function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = null ) {
		// Check if we are updating this plugin
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->slug ) {
			return $source;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			if ( ! WP_Filesystem() || ! $wp_filesystem ) {
				return new WP_Error( 'marrison_updater_filesystem', __( 'Impossibile inizializzare il filesystem per aggiornare Marrison Addon.', 'marrison-addon' ) );
			}
		}

		$correct_slug = dirname( $this->slug );
		$new_source   = trailingslashit( $remote_source ) . $correct_slug . '/';
		$plugin_entry = basename( $this->slug );
		$source = trailingslashit( $source );

		// GitHub tag archives contain the checkout; release ZIPs may contain only the plugin.
		if ( ! $wp_filesystem->is_file( $source . $plugin_entry ) ) {
			$nested_source = $source . $correct_slug . '/';
			if ( ! $wp_filesystem->is_file( $nested_source . $plugin_entry ) ) {
				return new WP_Error( 'marrison_updater_layout', __( 'Il pacchetto non contiene il file principale di Marrison Addon.', 'marrison-addon' ) );
			}
			$source = $nested_source;
		}

		if ( $source === $new_source ) {
			return $source;
		}

		if ( $wp_filesystem->exists( $new_source ) || ! $wp_filesystem->move( $source, $new_source ) ) {
			return new WP_Error( 'marrison_updater_move', __( 'Impossibile preparare la cartella di aggiornamento di Marrison Addon.', 'marrison-addon' ) );
		}

		if ( ! $wp_filesystem->is_file( $new_source . $plugin_entry ) ) {
			return new WP_Error( 'marrison_updater_entry', __( 'Il file principale manca nella cartella di aggiornamento.', 'marrison-addon' ) );
		}

		return $new_source;
	}

	public function clean_cache() {
		delete_transient( self::VERSION_TRANSIENT );
		delete_transient( self::VERSION_FAILURE_TRANSIENT );
		delete_transient( self::INFO_TRANSIENT );
		delete_transient(self::CHECKSUM_TRANSIENT);
		delete_transient(self::PACKAGE_TRANSIENT);
		delete_transient('marrison_addon_checksum_notice');
		delete_transient( 'marrison_addon_github_info' );
	}

	private function get_github_version() {
		$cached = get_transient( self::VERSION_TRANSIENT );
		if ( $cached !== false ) {
			return is_string($cached) && preg_match('/^\d+\.\d+\.\d+(?:\.\d+)?$/D', $cached) ? $cached : false;
		}

		if ( false !== get_transient( self::VERSION_FAILURE_TRANSIENT ) ) {
			return false;
		}

		$url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";

		$response = wp_remote_get( $url, [
			'timeout' => 10,
			'headers' => [
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/MarrisonAddon'
			]
		] );

		if ( is_wp_error( $response ) ) {
			$this->cache_github_version_failure();
			return false;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$this->cache_github_version_failure();
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['tag_name'] ) ) {
			$this->cache_github_version_failure();
			return false;
		}

		$version = ltrim( (string) $body['tag_name'], 'vV' );
		if (!preg_match('/^\d+\.\d+\.\d+(?:\.\d+)?$/D', $version)) { $this->cache_github_version_failure(); return false; }
		$checksum = self::release_checksum(isset($body['body']) && is_string($body['body']) ? $body['body'] : '');
		set_transient(self::CHECKSUM_TRANSIENT, array('version' => $version, 'sha256' => $checksum), 6 * HOUR_IN_SECONDS);
		set_transient( self::VERSION_TRANSIENT, $version, 6 * HOUR_IN_SECONDS );
		delete_transient( self::VERSION_FAILURE_TRANSIENT );

		return $version;
	}

	private function cache_github_version_failure() {
		set_transient( self::VERSION_FAILURE_TRANSIENT, 1, 30 * MINUTE_IN_SECONDS );
	}

	public static function release_checksum($body) {
		return is_string($body) && preg_match('/^Marrison-Archive-SHA256:\s*([a-f0-9]{64})\s*$/mi', $body, $match) ? strtolower($match[1]) : '';
	}

	private function expected_checksum($version) {
		$metadata = get_transient(self::CHECKSUM_TRANSIENT);
		return is_array($metadata) && ($metadata['version'] ?? '') === $version && preg_match('/^[a-f0-9]{64}$/D', $metadata['sha256'] ?? '') ? $metadata['sha256'] : '';
	}

	private function checksum_failure($message) {
		set_transient('marrison_addon_checksum_notice', $message, 6 * HOUR_IN_SECONDS);
		return new WP_Error('marrison_checksum', $message);
	}

	private function archive_url($version) {
		return "https://github.com/{$this->username}/{$this->repo}/archive/refs/tags/v{$version}.zip";
	}

	private function download_checked($url, $expected, $existing = false) {
		if (!function_exists('download_url')) { require_once ABSPATH . 'wp-admin/includes/file.php'; }
		$file = is_string($existing) ? $existing : download_url($url, 30);
		if (is_wp_error($file)) { return $this->checksum_failure('Pacchetto Marrison Addon non verificabile: download non riuscito.'); }
		$actual = is_string($file) && is_file($file) ? hash_file('sha256', $file) : false;
		if (!$actual || !hash_equals($expected, $actual)) {
			if ($existing === false && is_string($file) && is_file($file)) { wp_delete_file($file); }
			return $this->checksum_failure('Aggiornamento Marrison Addon bloccato: checksum SHA-256 non corrispondente.');
		}
		return $file;
	}

	private function verified_release($version) {
		$hash = $this->expected_checksum($version);
		if (!$hash) { $this->checksum_failure('Aggiornamento Marrison Addon non offerto: checksum SHA-256 della release assente.'); return false; }
		$verified = get_transient(self::PACKAGE_TRANSIENT);
		if (is_array($verified) && ($verified['version'] ?? '') === $version && ($verified['sha256'] ?? '') === $hash) { return !empty($verified['valid']); }
		$file = $this->download_checked($this->archive_url($version), $hash);
		$valid = !is_wp_error($file);
		if ($valid) { wp_delete_file($file); delete_transient('marrison_addon_checksum_notice'); }
		set_transient(self::PACKAGE_TRANSIENT, array('version' => $version, 'sha256' => $hash, 'valid' => $valid), 6 * HOUR_IN_SECONDS);
		return $valid;
	}

	public function verify_download($reply, $package, $upgrader, $extra = array()) {
		$prefix = "https://github.com/{$this->username}/{$this->repo}/archive/refs/tags/v";
		$ours = ($extra['plugin'] ?? '') === $this->slug || (is_string($package) && strpos($package, $prefix) === 0);
		if (!$ours || is_wp_error($reply)) { return $reply; }
		if (!is_string($package) || !preg_match('~^' . preg_quote($prefix, '~') . '(\d+\.\d+\.\d+(?:\.\d+)?)\.zip$~D', $package, $match)) {
			return $this->checksum_failure('Pacchetto Marrison Addon non riconosciuto: aggiornamento bloccato.');
		}
		$this->get_github_version();
		$hash = $this->expected_checksum($match[1]);
		if (!$hash) { return $this->checksum_failure('Checksum SHA-256 della release assente: aggiornamento bloccato.'); }
		return $this->download_checked($package, $hash, $reply);
	}

	public function checksum_notice() {
		if (!current_user_can('update_plugins')) { return; }
		$message = get_transient('marrison_addon_checksum_notice');
		if (is_string($message) && $message !== '') { echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>'; }
	}

	public function check_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		if ( ! isset( $transient->checked ) ) {
			$transient->checked = [];
		}

		// Ensure arrays exist
		if ( ! isset( $transient->response ) ) {
			$transient->response = [];
		}
		if ( ! isset( $transient->no_update ) ) {
			$transient->no_update = [];
		}

		// Get current version securely
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();

		if ( ! isset( $plugins[ $this->slug ] ) ) {
			return $transient;
		}

		$current_version = $plugins[ $this->slug ]['Version'];
		$remote_version  = $this->get_github_version();

		if ( ! $remote_version ) {
			unset($transient->response[$this->slug]);
			return $transient;
		}
		if (version_compare($current_version, $remote_version, '<') && !$this->verified_release($remote_version)) {
			unset($transient->response[$this->slug], $transient->no_update[$this->slug]);
			return $transient;
		}

		// Build update item
		$download_url = "https://github.com/{$this->username}/{$this->repo}/archive/refs/tags/v{$remote_version}.zip";
		
		$item = (object) [
			'id'           => 'marrison-addon',
			'slug'         => dirname( $this->slug ),
			'plugin'       => $this->slug,
			'new_version'  => $remote_version,
			'url'          => "https://github.com/{$this->username}/{$this->repo}",
			'package'      => $download_url,
			'tested'       => '7.1.3',
			'requires_php' => '7.4',
			'icons'        => [
				'default' => "https://raw.githubusercontent.com/{$this->username}/{$this->repo}/main/assets/icon-256x256.png"
			],
			'banners'      => [
				'default' => "https://raw.githubusercontent.com/{$this->username}/{$this->repo}/main/assets/banner-772x250.png"
			],
			'compatibility' => new stdClass(),
		];

		if ( version_compare( $current_version, $remote_version, '<' ) ) {
			$transient->response[ $this->slug ] = $item;
			unset( $transient->no_update[ $this->slug ] );
		} else {
			$transient->no_update[ $this->slug ] = $item;
			unset( $transient->response[ $this->slug ] );
		}

		$transient->checked[ $this->slug ] = $current_version;

		return $transient;
	}

	public function plugin_info( $res, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $res;
		}

		// Check if it's our plugin (slug can be folder name or full path)
		if ( $args->slug !== dirname( $this->slug ) && $args->slug !== $this->slug ) {
			return $res;
		}

		$remote_version = $this->get_github_version();
		if ( ! $remote_version ) {
			return $res;
		}
		if (!$this->verified_release($remote_version)) { return $res; }

		// Fetch full release info for description/changelog
		// We use a separate transient for this to avoid heavy API calls
		$cached_info = get_transient( self::INFO_TRANSIENT );

		if ( $cached_info !== false ) {
			return $cached_info;
		}

		$url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";
		$response = wp_remote_get( $url, [
			'timeout' => 10,
			'headers' => [
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/MarrisonAddon'
			]
		] );

		if ( is_wp_error( $response ) ) {
			return $res;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body ) ) {
			return $res;
		}

		$res = new stdClass();
		$res->name = 'Marrison Addon';
		$res->slug = dirname( $this->slug );
		$res->version = $remote_version;
		$res->tested = '7.1.3';
		$res->requires = '6.0';
		$res->author = '<a href="https://marrisonlab.com">Angelo Marra</a>';
		$res->author_profile = 'https://github.com/marrisonlab';
		$res->download_link = "https://github.com/{$this->username}/{$this->repo}/archive/refs/tags/v{$remote_version}.zip";
		$res->trunk = $res->download_link;
		$res->requires_php = '7.4';
		$res->last_updated = $body['published_at'];
		$res->homepage = "https://github.com/{$this->username}/{$this->repo}";
		
		// Parse README for sections
		$readme_content = $this->get_github_readme();
		$sections = $this->parse_readme_sections( $readme_content );

		// Fallback for description if empty
		if ( empty( $sections['description'] ) ) {
			$sections['description'] = "A comprehensive addon for Elementor and WordPress sites.";
		}

		// Fallback for changelog if empty
		if ( empty( $sections['changelog'] ) && isset( $body['body'] ) && ! empty( $body['body'] ) ) {
			$sections['changelog'] = $this->parse_markdown( $body['body'] );
		}

		$res->sections = $sections;

		$res->banners = [
			'low' => "https://raw.githubusercontent.com/{$this->username}/{$this->repo}/main/assets/banner-772x250.png",
			'high' => "https://raw.githubusercontent.com/{$this->username}/{$this->repo}/main/assets/banner-772x250.png"
		];

		set_transient( self::INFO_TRANSIENT, $res, 12 * HOUR_IN_SECONDS );

		return $res;
	}

	private function get_github_readme() {
		// Smart check: If local version is newer than remote, use local README
		// This helps during development or when changes haven't been pushed yet
		$local_version = class_exists( 'Marrison_Addon' ) ? Marrison_Addon::VERSION : '';
		$remote_version = $this->get_github_version();

		// If we can't get remote version or local is newer/equal, try local file first
		if ( ! $remote_version || ( $local_version && version_compare( $local_version, $remote_version, '>=' ) ) ) {
			$local_readme = plugin_dir_path( dirname( __FILE__ ) ) . 'README.md';
			if ( file_exists( $local_readme ) ) {
				return file_get_contents( $local_readme );
			}
		}

		$url = "https://raw.githubusercontent.com/{$this->username}/{$this->repo}/main/README.md";
		$response = wp_remote_get( $url );
		
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Try master branch if main fails
			$url = "https://raw.githubusercontent.com/{$this->username}/{$this->repo}/master/README.md";
			$response = wp_remote_get( $url );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				// Final fallback to local if remote fails completely
				$local_readme = plugin_dir_path( dirname( __FILE__ ) ) . 'README.md';
				if ( file_exists( $local_readme ) ) {
					return file_get_contents( $local_readme );
				}
				return '';
			}
		}
		
		return wp_remote_retrieve_body( $response );
	}

	private function parse_readme_sections( $content ) {
		$sections = [];
		if ( empty( $content ) ) {
			return $sections;
		}

		// Normalize newlines
		$content = str_replace( ["\r\n", "\r"], "\n", $content );
		
		// Split by H2 headers (## Title)
		// We capture the delimiter to know the section title
		$parts = preg_split( '/^##\s+(.*?)$/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		
		// If the file starts with text before any H2, it's usually the short description
		// However, in our README, the first H1 is the title, then text, then ## Description
		// We will treat the part before the first ## as 'description' if 'description' isn't explicitly found later,
		// but usually '## Description' is present.
		
		// parts[0] is text before first match
		// parts[1] is first title
		// parts[2] is first body
		// parts[3] is second title...
		
		// Initial chunk (often contains H1 and short desc)
		$initial = trim( $parts[0] );
		if ( ! empty( $initial ) ) {
			// Remove H1 title if present
			$initial = preg_replace( '/^#\s+.*?$/m', '', $initial );
			// $sections['description'] = $this->parse_markdown( $initial ); // Optional: Prepend this?
		}

		for ( $i = 1; $i < count( $parts ); $i += 2 ) {
			$title = strtolower( trim( $parts[$i] ) );
			$body  = isset( $parts[$i+1] ) ? $parts[$i+1] : '';
			
			// Map common titles to WP section keys
			if ( $title === 'description' ) {
				$key = 'description';
			} elseif ( $title === 'changelog' ) {
				$key = 'changelog';
			} elseif ( $title === 'installation' ) {
				$key = 'installation';
			} elseif ( $title === 'frequently asked questions' || $title === 'faq' ) {
				$key = 'faq';
			} elseif ( $title === 'screenshots' ) {
				$key = 'screenshots';
			} else {
				$key = sanitize_title( $title );
			}
			
			$sections[ $key ] = $this->parse_markdown( $body );
		}
		
		return $sections;
	}

	private function parse_markdown( $text ) {
		// Headers
		$text = preg_replace( '/^# (.*?)$/m', '<h1>$1</h1>', $text );
		$text = preg_replace( '/^## (.*?)$/m', '<h2>$1</h2>', $text );
		$text = preg_replace( '/^### (.*?)$/m', '<h3>$1</h3>', $text );
		
		// Bold
		$text = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text );
		
		// Italic
		$text = preg_replace( '/\*(.*?)\*/', '<em>$1</em>', $text );
		
		// Unordered Lists ( * item )
		$text = preg_replace_callback( '/((?:^\s*[*]\s+.*$\n?)+)/m', function($matches) {
			$list = $matches[1];
			$list = preg_replace( '/^\s*[*]\s+(.*)$/m', '<li>$1</li>', $list );
			return "<ul>\n$list\n</ul>\n";
		}, $text );

		// Links
		$text = preg_replace( '/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $text );
		
		// Paragraphs
		return wpautop( $text );
	}
}
