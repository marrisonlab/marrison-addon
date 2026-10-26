<?php
/** Capture native Elementor control definitions without an editor/database bootstrap. */
namespace Elementor {
	class Widget_Base {
		public $controls = [], $sections = [], $groups = [], $responsive = []; private $section;
		public function start_controls_section( $id, $args = [] ) { $this->section = $id; $this->sections[ $id ] = $args; }
		public function end_controls_section() { $this->section = null; }
		public function add_control( $id, $args ) { if ( ! $this->section ) { throw new \RuntimeException( 'Control outside section: ' . $id ); } $this->controls[ $id ] = $args; }
		public function add_responsive_control( $id, $args ) { $this->responsive[] = $id; $this->add_control( $id, $args ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = [ 'type' => $type ] + $args; }
	}
	class Controls_Manager { const SELECT = 'select', SWITCHER = 'switcher', TEXT = 'text', ICONS = 'icons', TAB_STYLE = 'style', CHOOSE = 'choose', COLOR = 'color', SLIDER = 'slider', DIMENSIONS = 'dimensions'; }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Typography { const TYPOGRAPHY_TEXT = 'text'; }
	class Global_Colors { const COLOR_TEXT = 'text'; }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	function esc_html__( $value ) { return $value; }
	function __( $value ) { return $value; }
	require __DIR__ . '/../marrison-addon/includes/modules/business-info/class-business-hours.php';
	require __DIR__ . '/../marrison-addon/includes/modules/business-info/widgets/business-hours-widget.php';
	$widget = new \Marrison_Addon\Modules\Business_Info\Widgets\Business_Hours_Widget();
	$register = new \ReflectionMethod( $widget, 'register_controls' ); if ( PHP_VERSION_ID < 80100 ) { $register->setAccessible( true ); } $register->invoke( $widget );
	function verify( $value, $message ) { if ( ! $value ) { throw new \RuntimeException( $message ); } }
	foreach ( [ 'layout', 'hide_closed', 'group_days', 'short_days', 'separator', 'closed_text', 'first_day', 'format', 'icons', 'status_icon' ] as $key ) { verify( isset( $widget->controls[ $key ] ), 'Missing presentation control: ' . $key ); }
	foreach ( [ 'container', 'days', 'times', 'rows', 'status' ] as $key ) { verify( $widget->sections[ $key ]['tab'] === 'style', 'Missing native style section: ' . $key ); }
	foreach ( [ 'days_type', 'times_type', 'status_type' ] as $key ) { verify( $widget->groups[ $key ]['type'] === 'typography' && isset( $widget->groups[ $key ]['global'] ), 'Missing native/global typography: ' . $key ); }
	verify( $widget->groups['background']['type'] === 'background' && $widget->groups['border']['type'] === 'border', 'Container uses proprietary styling' );
	foreach ( [ 'padding', 'radius', 'days_align', 'times_align', 'row_padding', 'row_gap', 'separator_width', 'icon_size', 'icon_gap' ] as $key ) { verify( in_array( $key, $widget->responsive, true ), 'Missing responsive control: ' . $key ); }
	foreach ( [ 'open_color', 'closed_color', 'pause_color' ] as $key ) { verify( isset( $widget->controls[ $key ]['global'] ), 'Global colors not supported' ); }
	verify( ! isset( $widget->controls['hours'], $widget->controls['weekly'] ), 'Widget stores business data' );
	$dynamic = new \ReflectionMethod( $widget, 'is_dynamic_content' ); if ( PHP_VERSION_ID < 80100 ) { $dynamic->setAccessible( true ); }
	verify( $dynamic->invoke( $widget ), 'Widget permits stale element cache' );
	echo "PASS widget controls: native content/style sections, Global Fonts/Colors, responsive typography/layout, native backgrounds/borders and dynamic rendering.\n";
}
