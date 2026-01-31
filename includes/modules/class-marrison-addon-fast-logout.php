<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Fast_Logout {

	public function __construct() {
		add_filter( 'logout_redirect', [ $this, 'custom_logout_redirect' ], 10, 3 );
	}

	/**
	 * Redirect user to home page after logout.
	 */
	public function custom_logout_redirect( $redirect_to, $requested_redirect_to, $user ) {
		return home_url();
	}
}
