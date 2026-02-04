<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Updater {

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
	}

	public function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = null ) {
		// Check if we are updating this plugin
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->slug ) {
			return $source;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// The source folder usually has the version number (e.g., marrison-addon-1.0.0)
		// We want to rename it to the correct slug (e.g., marrison-addon)
		$correct_slug = dirname( $this->slug );
		$new_source   = trailingslashit( $remote_source ) . $correct_slug . '/';

		if ( $source !== $new_source ) {
			$wp_filesystem->move( $source, $new_source );
			return $new_source;
		}

		return $source;
	}

	public function clean_cache() {
		delete_transient( 'marrison_addon_github_version' );
		delete_transient( 'marrison_addon_github_info' );
	}

	private function get_github_version() {
		$cached = get_transient( 'marrison_addon_github_version' );
		if ( $cached !== false ) {
			return $cached;
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
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['tag_name'] ) ) {
			return false;
		}

		$version = str_replace( 'v', '', $body['tag_name'] );
		set_transient( 'marrison_addon_github_version', $version, 6 * HOUR_IN_SECONDS );

		return $version;
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
			'tested'       => '6.9', // Safe hardcoded value
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

		// Fetch full release info for description/changelog
		// We use a separate transient for this to avoid heavy API calls
		$cache_key = 'marrison_addon_github_info';
		$cached_info = get_transient( $cache_key );

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
		$res->tested = '6.9';
		$res->requires = '6.0';
		$res->author = '<a href="https://marrisonlab.com">Angelo Marra</a>';
		$res->author_profile = 'https://github.com/marrisonlab';
		$res->download_link = "https://github.com/{$this->username}/{$this->repo}/archive/refs/tags/v{$remote_version}.zip";
		$res->trunk = $res->download_link;
		$res->requires_php = '7.4';
		$res->last_updated = $body['published_at'];
		$res->homepage = "https://github.com/{$this->username}/{$this->repo}";
		
		// Parse body for sections
		$description = "A comprehensive addon for Elementor and WordPress sites.";
		$changelog = isset( $body['body'] ) ? nl2br( $body['body'] ) : 'No changelog available';

		// Basic markdown parsing for changelog if needed
		// For now, nl2br is a simple start, but GitHub releases use Markdown.
		// We can improve this if needed, but nl2br is safe.

		$res->sections = [
			'description' => $description,
			'changelog'   => $changelog,
		];

		$res->banners = [
			'low' => "https://raw.githubusercontent.com/{$this->username}/{$this->repo}/main/assets/banner-772x250.png",
			'high' => "https://raw.githubusercontent.com/{$this->username}/{$this->repo}/main/assets/banner-772x250.png"
		];

		set_transient( $cache_key, $res, 12 * HOUR_IN_SECONDS );

		return $res;
	}
}
