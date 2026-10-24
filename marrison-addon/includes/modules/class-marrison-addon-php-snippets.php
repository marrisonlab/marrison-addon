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
		add_action('init', array('Marrison_Addon_PHP_Snippets_Security', 'schedule_cleanup'));
		add_action('marrison_snippets_audit_cleanup', array('Marrison_Addon_PHP_Snippets_Security', 'cleanup_audit'));
		add_action('marrison_addon/module_status_changed', function($id, $enabled) {
			if ($id === 'php_snippets' && !$enabled) { Marrison_Addon_PHP_Snippets_Security::clear_schedule(); }
		}, 10, 2);
		register_deactivation_hook(Marrison_Addon::plugin_file(), array('Marrison_Addon_PHP_Snippets_Security', 'clear_schedule'));
		if ( is_admin() ) {
			require_once __DIR__ . '/php-snippets/class-php-snippets-admin.php';
			new Marrison_Addon_PHP_Snippets_Admin( $store );
		}
		$runtime = new Marrison_Addon_PHP_Snippets_Runtime( $store );
		$runtime->run();
	}
}
