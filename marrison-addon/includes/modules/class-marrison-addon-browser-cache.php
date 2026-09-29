<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Browser_Cache {

	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}

		$module_dir = plugin_dir_path( __FILE__ ) . 'browser-cache/';

		require_once $module_dir . 'class-browser-cache-server.php';
		require_once $module_dir . 'class-browser-cache-diagnostics.php';
		require_once $module_dir . 'class-browser-cache-admin.php';

		$server      = new Marrison_Addon_Browser_Cache_Server();
		$diagnostics = new Marrison_Addon_Browser_Cache_Diagnostics( $server );

		new Marrison_Addon_Browser_Cache_Admin( $server, $diagnostics );
	}
}
