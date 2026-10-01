<?php
/**
 * Run with: php tests/read-more-php-test.php
 * Small WordPress/Elementor stubs for the Read More module contract.
 */
namespace Elementor {
	class Controls_Manager {
		const TAB_CONTENT = 'content';
		const TAB_STYLE = 'style';
		const SWITCHER = 'switcher';
		const NUMBER = 'number';
		const TEXT = 'text';
		const CHOOSE = 'choose';
		const COLOR = 'color';
		const DIMENSIONS = 'dimensions';
	}

	class Group_Control_Typography {
		public static function get_type() { return 'typography'; }
	}

	class Group_Control_Background {
		public static function get_type() { return 'background'; }
	}

	class Group_Control_Border {
		public static function get_type() { return 'border'; }
	}
}

namespace {
	error_reporting( E_ALL );
	ini_set( 'display_errors', '1' );
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['marrison_test_hooks'] = [];
	$GLOBALS['marrison_test_filters'] = [];
	$GLOBALS['marrison_test_assets'] = [];

	function add_action( $name, $callback ) { $GLOBALS['marrison_test_hooks'][ $name ][] = $callback; }
	function add_filter( $name, $callback ) { $GLOBALS['marrison_test_filters'][ $name ][] = $callback; }
	function esc_html__( $text ) { return $text; }
	function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function sanitize_html_class( $value ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', $value ); }
	function plugins_url( $path ) { return $path; }
	function wp_enqueue_style( $handle ) { $GLOBALS['marrison_test_assets'][] = $handle; }
	function wp_enqueue_script( $handle ) { $GLOBALS['marrison_test_assets'][] = $handle; }

	function check( $condition, $message ) {
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
	}

	class Marrison_Addon {
		const VERSION = 'test';
	}

	class Fake_Read_More_Widget {
		private $name;
		private $settings;
		public $controls = [];
		public $sections = [];

		public function __construct( $name, $settings = [] ) {
			$this->name = $name;
			$this->settings = $settings;
		}

		public function get_name() { return $this->name; }
		public function get_id() { return 'abc123'; }
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $options ) { $this->sections[ $name ] = $options; }
		public function add_control( $name, $options ) { $this->controls[ $name ] = $options; }
		public function add_responsive_control( $name, $options ) { $this->controls[ $name ] = $options; }
		public function add_group_control( $type, $options ) { $this->controls[ $options['name'] ] = [ 'type' => $type ] + $options; }
		public function end_controls_section() {}
	}

	require dirname( __DIR__ ) . '/marrison-addon/includes/modules/class-marrison-addon-read-more.php';

	$module = new \Marrison_Addon_Read_More();
	check( is_callable( [ $module, 'enqueue_assets' ] ), 'Frontend asset callback must be publicly callable by WordPress hooks.' );
	check( 1 === count( $GLOBALS['marrison_test_hooks']['elementor/element/after_section_end'] ?? [] ), 'Control hook was not registered.' );
	check( 1 === count( $GLOBALS['marrison_test_hooks']['wp_enqueue_scripts'] ?? [] ), 'Frontend assets should be hooked before the page head is printed.' );
	check( 1 === count( $GLOBALS['marrison_test_filters']['elementor/widget/render_content'] ?? [] ), 'Render filter was not registered.' );

	$text_widget = new Fake_Read_More_Widget( 'text-editor' );
	$module->maybe_register_controls( $text_widget, 'section_editor' );
	$module->maybe_register_controls( $text_widget, 'section_style' );
	check( isset( $text_widget->sections['marrison_read_more_section'] ), 'Text Editor controls were not added.' );
	check( isset( $text_widget->sections['marrison_read_more_style_section'] ), 'Style controls were not added.' );
	check( 3 === $text_widget->controls['marrison_read_more_lines']['default'], 'Visible lines default changed.' );
	check( isset( $text_widget->controls['marrison_read_more_typography'] ), 'Typography control missing.' );
	check( 1 === count( array_filter( array_keys( $text_widget->sections ), static function ( $key ) {
		return 'marrison_read_more_section' === $key;
	} ) ), 'Controls were registered more than once for the same widget instance.' );

	$jet_widget = new Fake_Read_More_Widget( 'jet-listing-dynamic-field' );
	$module->maybe_register_controls( $jet_widget, 'unknown_jet_section' );
	check( isset( $jet_widget->sections['marrison_read_more_section'] ), 'JetEngine Dynamic Field controls were not added.' );

	$plain_widget = new Fake_Read_More_Widget( 'heading' );
	$module->maybe_register_controls( $plain_widget, 'section_title' );
	check( empty( $plain_widget->sections ), 'Unsupported widgets received Read More controls.' );

	$enabled_widget = new Fake_Read_More_Widget(
		'jet-listing-dynamic-field',
		[
			'marrison_read_more_enabled' => 'yes',
			'marrison_read_more_lines' => 100,
			'marrison_read_more_label_more' => 'Apri',
			'marrison_read_more_label_less' => 'Chiudi',
		]
	);
	$output = $module->render_read_more( '<div class="elementor-widget-container"><p>Testo lungo di prova</p></div>', $enabled_widget );
	check( false !== strpos( $output, 'data-marrison-read-more' ), 'Enabled widget was not wrapped.' );
	check( 0 === strpos( $output, '<div class="elementor-widget-container"><div class="marrison-read-more"' ), 'Read More should stay inside Elementor widget container.' );
	check( false !== strpos( $output, '"lines":30' ) || false !== strpos( $output, '&quot;lines&quot;:30' ), 'Line count was not clamped.' );
	check( false !== strpos( $output, 'Apri' ), 'Custom closed label missing.' );
	check( 2 === count( $GLOBALS['marrison_test_assets'] ), 'Read More assets were not enqueued once.' );

	$module->render_read_more( '<p>Secondo testo</p>', $enabled_widget );
	check( 2 === count( $GLOBALS['marrison_test_assets'] ), 'Assets were enqueued more than once.' );

	$cached_module = new \Marrison_Addon_Read_More();
	$cached_data = [
		[
			'elType' => 'container',
			'elements' => [
				[
					'elType' => 'widget',
					'widgetType' => 'text-editor',
					'settings' => [
						'marrison_read_more_enabled' => 'yes',
					],
				],
			],
		],
	];
	check( $cached_data === $cached_module->maybe_enqueue_assets_from_data( $cached_data ), 'Cached Elementor data should not be changed.' );
	check( 4 === count( $GLOBALS['marrison_test_assets'] ), 'Cached enabled widget did not enqueue assets.' );
	$cached_module->maybe_enqueue_assets_from_data( $cached_data );
	check( 4 === count( $GLOBALS['marrison_test_assets'] ), 'Cached enabled widget enqueued assets more than once.' );

	$disabled_widget = new Fake_Read_More_Widget( 'text-editor', [ 'marrison_read_more_enabled' => '' ] );
	check( '<p>No wrap</p>' === $module->render_read_more( '<p>No wrap</p>', $disabled_widget ), 'Disabled widget content changed.' );

	echo "Read More PHP contract: PASS\n";
}
