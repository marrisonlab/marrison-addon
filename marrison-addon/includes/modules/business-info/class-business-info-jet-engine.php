<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Public extension points documented by Crocoblock; no synthetic Option Page. */
final class Marrison_Addon_Business_Info_Jet_Engine {
	const PREFIX = 'marrison_business_info:';
	public function __construct() {
		add_filter( 'jet-engine/listings/data/sources', [ $this, 'sources' ] );
		add_filter( 'jet-engine/listings/dynamic-field/field-value', [ $this, 'value' ], 10, 2 );
	}
	public function sources( $sources ) {
		foreach ( Marrison_Addon_Business_Info_Store::fields() as $key => $field ) { if ( 'image' !== $field['type'] ) { $sources[ self::PREFIX . $key ] = 'Business Info / ' . $field['label']; } }
		return $sources;
	}
	public function value( $value, $settings ) {
		$source = $settings['dynamic_field_source'] ?? '';
		if ( ! is_string( $source ) || 0 !== strpos( $source, self::PREFIX ) ) { return $value; }
		return esc_html( Marrison_Addon_Business_Info_Store::get( substr( $source, strlen( self::PREFIX ) ) ) );
	}
}
