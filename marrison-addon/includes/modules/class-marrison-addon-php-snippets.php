<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Marrison_Addon_PHP_Snippets {
	private static $initialized = false;

	public function __construct() {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		require_once __DIR__ . '/php-snippets/class-php-snippets-store.php';
		require_once __DIR__ . '/php-snippets/class-php-snippets-runtime.php';
		$store = new Marrison_Addon_PHP_Snippets_Store();
		if ( is_admin() ) {
			require_once __DIR__ . '/php-snippets/class-php-snippets-admin.php';
			new Marrison_Addon_PHP_Snippets_Admin( $store );
		}
		$runtime = new Marrison_Addon_PHP_Snippets_Runtime( $store );
		$runtime->run();
	}
}
