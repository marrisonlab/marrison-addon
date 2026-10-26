<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/business-info/class-business-info-store.php';
require_once __DIR__ . '/business-info/class-business-hours.php';

final class Marrison_Addon_Business_Info {
	public function __construct() {
		require_once __DIR__ . '/business-info/class-business-info-display.php';
		if ( is_admin() ) { require_once __DIR__ . '/business-info/class-business-info-admin.php'; new Marrison_Addon_Business_Info_Admin(); }
		add_action( 'init', [ $this, 'register' ] );
		add_action( 'elementor/widgets/register', [ $this, 'widgets' ] );
		add_action( 'elementor/dynamic_tags/register', [ $this, 'tags' ] );
		add_action( 'wp_ajax_marrison_business_hours', [ 'Marrison_Addon_Business_Info_Display', 'ajax' ] );
		add_action( 'wp_ajax_nopriv_marrison_business_hours', [ 'Marrison_Addon_Business_Info_Display', 'ajax' ] );
	}
	public function register() {
		foreach ( [ 'css', 'js' ] as $ext ) {
			$path = 'includes/modules/business-info/assets/frontend.' . $ext;
			if ( 'css' === $ext ) { wp_register_style( 'marrison-business-hours', plugins_url( $path, Marrison_Addon::plugin_file() ), [], Marrison_Addon::asset_version( $path ) ); }
			else { wp_register_script( 'marrison-business-hours', plugins_url( $path, Marrison_Addon::plugin_file() ), [], Marrison_Addon::asset_version( $path ), true ); }
		}
		add_shortcode( 'marrison_info', [ 'Marrison_Addon_Business_Info_Display', 'info_shortcode' ] );
		add_shortcode( 'marrison_business_hours', [ 'Marrison_Addon_Business_Info_Display', 'hours_shortcode' ] );
		add_shortcode( 'marrison_business_status', [ 'Marrison_Addon_Business_Info_Display', 'status_shortcode' ] );
		if ( Marrison_Addon::is_jet_engine_active() ) { require_once __DIR__ . '/business-info/class-business-info-jet-engine.php'; new Marrison_Addon_Business_Info_Jet_Engine(); }
	}
	public function widgets( $manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) { return; }
		require_once __DIR__ . '/business-info/widgets/business-hours-widget.php';
		$manager->register( new \Marrison_Addon\Modules\Business_Info\Widgets\Business_Hours_Widget() );
	}
	public function tags( $manager ) {
		if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) || ! class_exists( '\Elementor\Core\DynamicTags\Data_Tag' ) ) { return; }
		require_once __DIR__ . '/business-info/class-business-info-tags.php';
		$manager->register_group( 'marrison-business-info', [ 'title' => __( 'Marrison Business Info', 'marrison-addon' ) ] );
		foreach ( [ 'Marrison_Business_Info_Text_Tag', 'Marrison_Business_Info_URL_Tag', 'Marrison_Business_Info_Image_Tag', 'Marrison_Business_Hours_Tag' ] as $class ) { $manager->register( new $class() ); }
	}
}
