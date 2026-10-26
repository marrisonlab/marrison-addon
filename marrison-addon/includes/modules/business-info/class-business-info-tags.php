<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

trait Marrison_Business_Info_Tag_Controls {
	public function get_group() { return 'marrison-business-info'; }
	public function get_panel_template_setting_key() { return 'field'; }
	public function is_dynamic_content(): bool { return true; }
	protected function field_control( $types = [] ) {
		$options = [];
		foreach ( Marrison_Addon_Business_Info_Store::fields() as $key => $field ) { if ( ! $types || in_array( $field['type'], $types, true ) ) { $options[ $key ] = $field['label']; } }
		$this->add_control( 'field', [ 'label' => __( 'Campo', 'marrison-addon' ), 'type' => \Elementor\Controls_Manager::SELECT2, 'options' => $options, 'label_block' => true ] );
	}
}

class Marrison_Business_Info_Text_Tag extends \Elementor\Core\DynamicTags\Tag {
	use Marrison_Business_Info_Tag_Controls;
	public function get_name() { return 'marrison-business-info-text'; }
	public function get_title() { return __( 'Info Azienda — Testo', 'marrison-addon' ); }
	public function get_categories() { return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ]; }
	protected function register_controls() { $this->field_control( [ 'text', 'textarea', 'email', 'phone', 'mobile', 'whatsapp', 'fax', 'other', 'number', 'date', 'url' ] ); }
	public function render() { echo esc_html( Marrison_Addon_Business_Info_Store::get( $this->get_settings( 'field' ) ?: '' ) ); }
}

class Marrison_Business_Info_URL_Tag extends \Elementor\Core\DynamicTags\Tag {
	use Marrison_Business_Info_Tag_Controls;
	public function get_name() { return 'marrison-business-info-url'; }
	public function get_title() { return __( 'Info Azienda — Link / email / telefono', 'marrison-addon' ); }
	public function get_categories() { return [ \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ]; }
	protected function register_controls() { $this->field_control( [ 'url', 'email', 'phone', 'mobile', 'whatsapp', 'fax' ] ); }
	public function render() {
		$field = Marrison_Addon_Business_Info_Store::descriptor( $this->get_settings( 'field' ) ?: '' );
		echo esc_url( Marrison_Addon_Business_Info_Store::url( $field['value'], $field['type'] ) );
	}
}

class Marrison_Business_Info_Image_Tag extends \Elementor\Core\DynamicTags\Data_Tag {
	use Marrison_Business_Info_Tag_Controls;
	public function get_name() { return 'marrison-business-info-image'; }
	public function get_title() { return __( 'Info Azienda — Logo', 'marrison-addon' ); }
	public function get_categories() { return [ \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY ]; }
	protected function register_controls() { $this->field_control( [ 'image' ] ); }
	public function get_value( array $options = [] ) { $id = absint( Marrison_Addon_Business_Info_Store::get( $this->get_settings( 'field' ) ?: 'logo' ) ); return [ 'id' => $id, 'url' => $id ? ( wp_get_attachment_image_url( $id, 'full' ) ?: '' ) : '' ]; }
}

class Marrison_Business_Hours_Tag extends \Elementor\Core\DynamicTags\Tag {
	use Marrison_Business_Info_Tag_Controls;
	public function get_name() { return 'marrison-business-hours'; }
	public function get_title() { return __( 'Info Azienda — Orari e stato', 'marrison-addon' ); }
	public function get_categories() { return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ]; }
	protected function register_controls() {
		$this->add_control( 'field', [ 'label' => __( 'Dato', 'marrison-addon' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'today', 'options' => [ 'today' => __( 'Orari di oggi', 'marrison-addon' ), 'day' => __( 'Orari di un giorno', 'marrison-addon' ), 'next_open' => __( 'Prossima apertura', 'marrison-addon' ), 'next_close' => __( 'Prossima chiusura', 'marrison-addon' ), 'status' => __( 'Stato corrente', 'marrison-addon' ) ] ] );
		$this->add_control( 'day', [ 'label' => __( 'Giorno', 'marrison-addon' ), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => Marrison_Addon_Business_Hours::days(), 'default' => 1, 'condition' => [ 'field' => 'day' ] ] );
		$this->add_control( 'format', [ 'label' => __( 'Formato orario', 'marrison-addon' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '24', 'options' => [ '24' => '24h', '12' => '12h' ] ] );
	}
	public function render() {
		$field = $this->get_settings( 'field' ) ?: 'today';
		if ( ! in_array( $field, [ 'today', 'day', 'next_open', 'next_close', 'status' ], true ) ) { return; }
		echo Marrison_Addon_Business_Info_Display::dynamic_field( $field, max( 1, min( 7, (int) $this->get_settings( 'day' ) ) ), [ 'format' => $this->get_settings( 'format' ) ] ); // Generated, escaped markup.
	}
}
