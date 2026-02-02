<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Updater {

	private $slug; // plugin slug
	private $plugin_data; // plugin data
	private $username; // GitHub username
	private $repo; // GitHub repo name
	private $plugin_file; // __FILE__ of our plugin
	private $github_response; // contains the JSON response from GitHub

	public function __construct( $plugin_file, $github_username, $github_repo ) {
		$this->plugin_file = $plugin_file;
		$this->username = $github_username;
		$this->repo = $github_repo;
		$this->slug = plugin_basename( $this->plugin_file );

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'check_info' ], 10, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );
	}

	private function get_repository_info() {
		if ( ! empty( $this->github_response ) ) {
			return;
		}

		// Query the GitHub API
		$url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";

		$args = [
			'headers' => [
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
			],
		];

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return;
		}

		$this->github_response = json_decode( wp_remote_retrieve_body( $response ), true );
	}

	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$this->get_repository_info();

		if ( ! $this->github_response ) {
			return $transient;
		}

		$do_update = version_compare( $this->github_response['tag_name'], $transient->checked[ $this->slug ], '>' );

		if ( $do_update ) {
			$package = $this->github_response['zipball_url'];

			// If private, append token
			if ( ! empty( $this->access_token ) ) {
				$package = add_query_arg( [ 'access_token' => $this->access_token ], $package );
			}

			$obj = new stdClass();
			$obj->slug = $this->slug;
			$obj->new_version = $this->github_response['tag_name'];
			$obj->url = $this->github_response['html_url'];
			$obj->package = $package;
			$obj->icons = [
				'default' => 'https://raw.githubusercontent.com/' . $this->username . '/' . $this->repo . '/main/assets/icon-256x256.png'
			];
			$obj->banners = [
				'default' => 'https://raw.githubusercontent.com/' . $this->username . '/' . $this->repo . '/main/assets/banner-772x250.png'
			];

			$transient->response[ $this->slug ] = $obj;
		}

		return $transient;
	}

	public function check_info( $false, $action, $arg ) {
		if ( ! isset( $arg->slug ) || $arg->slug !== $this->slug ) {
			return $false;
		}

		$this->get_repository_info();

		if ( ! $this->github_response ) {
			return $false;
		}

		$obj = new stdClass();
		$obj->slug = $this->slug;
		$obj->name = $this->github_response['name'];
		$obj->plugin_name = $this->github_response['name'];
		$obj->sections = [
			'description' => $this->github_response['name'], // Using release name
			'changelog' => $this->github_response['body'],
		];
		
		$remote_version = $this->github_response['tag_name'];
		if ( substr( $remote_version, 0, 1 ) === 'v' ) {
			$remote_version = substr( $remote_version, 1 );
		}
		
		$obj->version = $remote_version;
		$obj->author = '<a href="' . $this->github_response['author']['html_url'] . '">' . $this->github_response['author']['login'] . '</a>';
		$obj->homepage = $this->github_response['html_url'];
		
		// Download link
		$package = $this->github_response['zipball_url'];
		if ( ! empty( $this->access_token ) ) {
			$package = add_query_arg( [ 'access_token' => $this->access_token ], $package );
		}
		$obj->download_link = $package;

		return $obj;
	}

	public function post_install( $true, $hook_extra, $result ) {
		// GitHub zipballs are extracted into a folder like 'user-repo-hash'
		// We need to move files back to the correct plugin folder
		
		global $wp_filesystem;
		$plugin_folder = WP_PLUGIN_DIR . '/' . dirname( $this->slug );
		$wp_filesystem->move( $result['destination'], $plugin_folder );
		$result['destination'] = $plugin_folder;

		return $result;
	}
}
