<?php
namespace Marrison_Addon\Modules\Business_Info\Widgets;

use Elementor\Controls_Manager as Controls;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Business_Hours_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'marrison_business_hours'; }
	public function get_title() { return esc_html__( 'Marrison Business Hours', 'marrison-addon' ); }
	public function get_icon() { return 'eicon-clock-o'; }
	public function get_categories() { return [ 'general' ]; }
	public function get_keywords() { return [ 'orari', 'apertura', 'azienda', 'hours', 'marrison' ]; }
	public function get_style_depends() { return [ 'marrison-business-hours' ]; }
	public function get_script_depends() { return [ 'marrison-business-hours' ]; }
	protected function is_dynamic_content(): bool { return true; }
	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => esc_html__( 'Orari di apertura', 'marrison-addon' ) ] );
		$this->add_control( 'layout', [ 'label' => esc_html__( 'Layout', 'marrison-addon' ), 'type' => Controls::SELECT, 'default' => 'list', 'options' => [ 'list' => esc_html__( 'Lista verticale', 'marrison-addon' ), 'compact' => esc_html__( 'Compatto / orizzontale', 'marrison-addon' ), 'status' => esc_html__( 'Solo stato apertura', 'marrison-addon' ) ] ] );
		foreach ( [ 'hide_closed' => 'Nascondi giorni chiusi', 'group_days' => 'Raggruppa giorni consecutivi uguali', 'short_days' => 'Abbrevia i nomi dei giorni', 'icons' => 'Mostra icona' ] as $key => $label ) { $this->add_control( $key, [ 'label' => esc_html__( $label, 'marrison-addon' ), 'type' => Controls::SWITCHER, 'default' => 'icons' === $key ? 'yes' : '' ] ); }
		$this->add_control( 'separator', [ 'label' => esc_html__( 'Separatore fasce', 'marrison-addon' ), 'type' => Controls::TEXT, 'default' => ' / ' ] );
		$this->add_control( 'closed_text', [ 'label' => esc_html__( 'Testo giorno chiuso', 'marrison-addon' ), 'type' => Controls::TEXT, 'default' => esc_html__( 'Chiuso', 'marrison-addon' ) ] );
		$this->add_control( 'first_day', [ 'label' => esc_html__( 'Primo giorno', 'marrison-addon' ), 'type' => Controls::SELECT, 'default' => 1, 'options' => \Marrison_Addon_Business_Hours::days() ] );
		$this->add_control( 'format', [ 'label' => esc_html__( 'Formato orario', 'marrison-addon' ), 'type' => Controls::SELECT, 'default' => '24', 'options' => [ '24' => '24h', '12' => '12h' ] ] );
		$this->add_control( 'status_icon', [ 'label' => esc_html__( 'Icona stato attività', 'marrison-addon' ), 'type' => Controls::ICONS, 'condition' => [ 'icons' => 'yes' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'container', [ 'label' => esc_html__( 'Contenitore', 'marrison-addon' ), 'tab' => Controls::TAB_STYLE ] );
		$this->add_group_control( \Elementor\Group_Control_Background::get_type(), [ 'name' => 'background', 'types' => [ 'classic', 'gradient' ], 'selector' => '{{WRAPPER}} .marrison-bh' ] );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), [ 'name' => 'border', 'selector' => '{{WRAPPER}} .marrison-bh' ] );
		$this->dimensions( 'radius', 'Border radius', '{{WRAPPER}} .marrison-bh', 'border-radius' );
		$this->dimensions( 'padding', 'Padding', '{{WRAPPER}} .marrison-bh', 'padding' );
		$this->end_controls_section();

		foreach ( [ 'days' => [ 'Giorni', '.marrison-bh__day' ], 'times' => [ 'Orari', '.marrison-bh__times' ] ] as $key => $spec ) {
			$this->start_controls_section( $key, [ 'label' => esc_html__( $spec[0], 'marrison-addon' ), 'tab' => Controls::TAB_STYLE ] );
			$selector = '{{WRAPPER}} ' . $spec[1];
			$this->typography( $key . '_type', $selector );
			$this->color( $key . '_color', 'Colore', $selector );
			$this->add_responsive_control( $key . '_align', [ 'label' => esc_html__( 'Allineamento', 'marrison-addon' ), 'type' => Controls::CHOOSE, 'options' => [ 'left' => [ 'title' => esc_html__( 'Sinistra', 'marrison-addon' ), 'icon' => 'eicon-text-align-left' ], 'center' => [ 'title' => esc_html__( 'Centro', 'marrison-addon' ), 'icon' => 'eicon-text-align-center' ], 'right' => [ 'title' => esc_html__( 'Destra', 'marrison-addon' ), 'icon' => 'eicon-text-align-right' ] ], 'selectors' => [ $selector => 'text-align: {{VALUE}}; width:100%;' ] ] );
			$this->end_controls_section();
		}
		$this->start_controls_section( 'rows', [ 'label' => esc_html__( 'Righe', 'marrison-addon' ), 'tab' => Controls::TAB_STYLE ] );
		$this->slider( 'row_gap', 'Spaziatura', '{{WRAPPER}} .marrison-bh__rows, {{WRAPPER}} .marrison-bh__exception-rows', 'gap' );
		$this->dimensions( 'row_padding', 'Padding riga', '{{WRAPPER}} .marrison-bh__row', 'padding' );
		$this->add_control( 'row_separator', [ 'label' => esc_html__( 'Separatore righe', 'marrison-addon' ), 'type' => Controls::SELECT, 'default' => 'solid', 'options' => [ 'none' => esc_html__( 'Nessuno', 'marrison-addon' ), 'solid' => esc_html__( 'Continuo', 'marrison-addon' ), 'dashed' => esc_html__( 'Tratteggiato', 'marrison-addon' ), 'dotted' => esc_html__( 'Puntinato', 'marrison-addon' ) ], 'selectors' => [ '{{WRAPPER}} .marrison-bh__row' => 'border-bottom-style: {{VALUE}};' ] ] );
		$this->color( 'separator_color', 'Colore separatori', '{{WRAPPER}} .marrison-bh__row', 'border-bottom-color' );
		$this->slider( 'separator_width', 'Spessore separatori', '{{WRAPPER}} .marrison-bh__row', 'border-bottom-width' );
		$this->end_controls_section();

		$this->start_controls_section( 'status', [ 'label' => esc_html__( 'Stato attività', 'marrison-addon' ), 'tab' => Controls::TAB_STYLE ] );
		foreach ( [ 'open' => 'Colore aperto', 'closed' => 'Colore chiuso', 'pause' => 'Colore pausa' ] as $state => $label ) { $this->color( $state . '_color', $label, '{{WRAPPER}} .marrison-bh__status[data-state="' . $state . '"]' ); }
		$this->typography( 'status_type', '{{WRAPPER}} .marrison-bh__status' );
		$this->slider( 'icon_size', 'Dimensione icona', '{{WRAPPER}} .marrison-bh__icon', 'font-size' );
		$this->slider( 'icon_gap', 'Distanza icona e testo', '{{WRAPPER}} .marrison-bh__status', 'gap' );
		$this->end_controls_section();
		// Margins, responsive visibility and all Advanced controls remain native to Elementor.
	}
	private function typography( $name, $selector ) { $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => $name, 'selector' => $selector, 'global' => [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT ] ] ); }
	private function color( $name, $label, $selector, $property = 'color' ) { $this->add_control( $name, [ 'label' => esc_html__( $label, 'marrison-addon' ), 'type' => Controls::COLOR, 'global' => [ 'default' => in_array( $name, [ 'open_color', 'closed_color', 'pause_color', 'separator_color' ], true ) ? '' : \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT ], 'selectors' => [ $selector => $property . ': {{VALUE}};' ] ] ); }
	private function slider( $name, $label, $selector, $property ) { $this->add_responsive_control( $name, [ 'label' => esc_html__( $label, 'marrison-addon' ), 'type' => Controls::SLIDER, 'size_units' => [ 'px', 'em', 'rem' ], 'selectors' => [ $selector => $property . ': {{SIZE}}{{UNIT}};' ] ] ); }
	private function dimensions( $name, $label, $selector, $property ) { $this->add_responsive_control( $name, [ 'label' => esc_html__( $label, 'marrison-addon' ), 'type' => Controls::DIMENSIONS, 'size_units' => [ 'px', '%', 'em', 'rem' ], 'selectors' => [ $selector => $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] ); }
	protected function render() {
		$settings = $this->get_settings_for_display(); $icon = null;
		if ( ! empty( $settings['status_icon']['value'] ) ) { ob_start(); \Elementor\Icons_Manager::render_icon( $settings['status_icon'], [ 'aria-hidden' => 'true' ] ); $icon = ob_get_clean(); }
		echo \Marrison_Addon_Business_Info_Display::render( $settings, $icon );
	}
}
