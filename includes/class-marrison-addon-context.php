<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Marrison_Addon_Context {

	public static function is_admin_page( $page_slug = '' ) {
		if ( ! is_admin() ) {
			return false;
		}

		if ( '' === $page_slug ) {
			return true;
		}

		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return $current_page === $page_slug;
	}

	public static function is_login_request() {
		return isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'];
	}

	public static function is_elementor_editor_context() {
		if ( self::request_has_flag( 'elementor-preview' ) ) {
			return true;
		}

		$action = self::get_request_value( 'action' );
		if ( 'elementor' === $action ) {
			return true;
		}

		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return true;
		}

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$plugin = \Elementor\Plugin::$instance;

			if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
				return true;
			}

			if ( isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
				return true;
			}
		}

		return false;
	}

	public static function is_public_frontend_request() {
		if ( is_admin() || self::is_login_request() ) {
			return false;
		}

		if ( wp_doing_ajax() ) {
			return false;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return false;
		}

		return ! self::is_elementor_editor_context();
	}

	private static function request_has_flag( $key ) {
		return '' !== self::get_request_value( $key );
	}

	private static function get_request_value( $key ) {
		if ( ! isset( $_REQUEST[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );
	}
}
