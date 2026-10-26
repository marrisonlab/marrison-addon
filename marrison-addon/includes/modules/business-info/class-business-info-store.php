<?php
/** Builder-independent company directory. Values are plain data; consumers escape at output. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Marrison_Addon_Business_Info_Store {
	const OPTION = 'marrison_business_info';

	public static function company_fields() {
		return [ 'trade_name' => 'Nome commerciale', 'legal_name' => 'Ragione sociale', 'legal_form' => 'Forma giuridica', 'vat' => 'Partita IVA', 'tax_code' => 'Codice fiscale', 'sdi' => 'Codice SDI', 'pec' => 'PEC', 'rea' => 'Numero REA', 'capital' => 'Capitale sociale', 'description' => 'Descrizione breve', 'logo' => 'Logo aziendale' ];
	}
	public static function contact_types() { return [ 'email' => 'Email', 'phone' => 'Telefono', 'mobile' => 'Cellulare', 'whatsapp' => 'WhatsApp', 'fax' => 'Fax', 'other' => 'Altro recapito' ]; }
	public static function custom_types() { return [ 'text' => 'Testo', 'textarea' => 'Testo lungo', 'url' => 'URL', 'email' => 'Email', 'phone' => 'Telefono', 'number' => 'Numero', 'date' => 'Data' ]; }
	public static function data() {
		$data = get_option( self::OPTION, false );
		if ( false === $data ) {
			$data = [ 'contacts' => [], 'locations' => [ [ 'id' => 'location-primary', 'key' => 'sede_principale', 'label' => 'Sede principale', 'type' => 'legal', 'street' => '', 'postcode' => '', 'city' => '', 'province' => '', 'region' => '', 'country' => '', 'lat' => '', 'lng' => '', 'default' => true ] ] ];
			foreach ( [ 'email' => [ 'email_principale', 'Email principale' ], 'phone' => [ 'telefono_ufficio', 'Telefono ufficio' ], 'mobile' => [ 'cellulare_principale', 'Cellulare principale' ], 'whatsapp' => [ 'whatsapp_commerciale', 'WhatsApp commerciale' ], 'fax' => [ 'fax_principale', 'Fax' ] ] as $type => $spec ) { $data['contacts'][] = [ 'id' => 'contact-' . $type, 'key' => $spec[0], 'label' => $spec[1], 'type' => $type, 'value' => '', 'default' => true ]; }
		}
		return array_replace( [ 'company' => [], 'contacts' => [], 'locations' => [], 'social' => [], 'custom' => [], 'privacy' => [], 'hours' => [ 'weekly' => [], 'exceptions' => [] ] ], is_array( $data ) ? $data : [] );
	}
	private static function text( $value ) { return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ); }
	public static function date_valid( $date ) {
		if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) ) { return false; }
		return checkdate( (int) substr( $date, 5, 2 ), (int) substr( $date, 8, 2 ), (int) substr( $date, 0, 4 ) );
	}
	private static function value( $value, $type ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		if ( 'email' === $type ) { return sanitize_email( $value ); }
		if ( 'url' === $type ) { return esc_url_raw( $value, [ 'http', 'https' ] ); }
		if ( 'textarea' === $type ) { return sanitize_textarea_field( $value ); }
		if ( 'number' === $type ) { return is_numeric( $value ) ? $value : ''; }
		if ( 'date' === $type ) { return self::date_valid( $value ) ? $value : ''; }
		return self::text( $value );
	}

	/** Atomic validation. UUIDs own the immutable key, independently of display order. */
	public static function sanitize( $input, $old = null ) {
		if ( ! is_array( $input ) ) { return new WP_Error( 'business_info', 'Dati non validi.' ); }
		$old = null === $old ? self::data() : $old;
		$clean = [ 'company' => [], 'contacts' => [], 'locations' => [], 'social' => [], 'custom' => [], 'privacy' => [], 'hours' => [] ];
		foreach ( self::company_fields() as $key => $label ) {
			$value = $input['company'][ $key ] ?? '';
			if ( ! is_scalar( $value ) ) { return new WP_Error( 'business_info', 'Valore non valido per ' . $label . '.' ); }
			$clean['company'][ $key ] = 'logo' === $key ? absint( $value ) : self::value( $value, 'pec' === $key ? 'email' : ( 'description' === $key ? 'textarea' : 'text' ) );
			if ( 'pec' === $key && '' !== trim( (string) $value ) && '' === $clean['company'][ $key ] ) { return new WP_Error( 'business_info', 'Inserisci un indirizzo PEC valido.' ); }
			if ( 'logo' === $key && $clean['company'][ $key ] && ! wp_attachment_is_image( $clean['company'][ $key ] ) ) { return new WP_Error( 'business_info', 'Il logo deve essere un’immagine della libreria media.' ); }
		}
		foreach ( [ 'contacts', 'locations', 'social', 'custom' ] as $group ) {
			$seen = []; $seen_ids = []; $defaults = []; $previous = [];
			foreach ( $old[ $group ] ?? [] as $row ) { $previous[ $row['id'] ] = $row; }
			foreach ( is_array( $input[ $group ] ?? null ) ? $input[ $group ] : [] as $row ) {
				if ( ! is_array( $row ) ) { continue; }
				$id = self::text( $row['id'] ?? '' );
				if ( ! preg_match( '/^[a-zA-Z0-9-]{8,64}$/D', $id ) ) { $id = wp_generate_uuid4(); }
				$key = isset( $previous[ $id ] ) ? $previous[ $id ]['key'] : self::text( $row['key'] ?? '' );
				if ( ! preg_match( '/^[a-z][a-z0-9_]*$/D', $key ) || isset( $seen[ $key ] ) || isset( $seen_ids[ $id ] ) ) { return new WP_Error( 'business_info', 'Chiavi mancanti, duplicate o non valide in ' . $group . '. Usa lettere minuscole, numeri e underscore, iniziando con una lettera.' ); }
				$seen[ $key ] = true; $seen_ids[ $id ] = true;
				$item = [ 'id' => $id, 'key' => $key, 'label' => self::text( $row['label'] ?? '' ) ];
				if ( '' === $item['label'] ) { return new WP_Error( 'business_info', 'Inserisci un’etichetta per ' . $key . '.' ); }
				if ( 'locations' === $group ) {
					$item['type'] = in_array( $row['type'] ?? '', [ 'legal', 'operational', 'shop', 'warehouse', 'other' ], true ) ? $row['type'] : 'other';
					foreach ( [ 'street', 'postcode', 'city', 'province', 'region', 'country' ] as $part ) { $item[ $part ] = self::text( $row[ $part ] ?? '' ); }
					foreach ( [ 'lat' => 90, 'lng' => 180 ] as $part => $limit ) {
						$v = $row[ $part ] ?? '';
						if ( '' !== $v && ( ! is_numeric( $v ) || abs( (float) $v ) > $limit ) ) { return new WP_Error( 'business_info', 'Coordinate non valide per ' . $item['label'] . '.' ); }
						$item[ $part ] = '' === $v ? '' : (string) (float) $v;
					}
				} else {
					$types = 'contacts' === $group ? self::contact_types() : self::custom_types();
					if ( 'social' !== $group && ( ! is_string( $row['type'] ?? null ) || ! array_key_exists( $row['type'], $types ) ) ) { return new WP_Error( 'business_info', 'Tipo di dato non valido per ' . $item['label'] . '.' ); }
					$item['type'] = 'social' === $group ? 'url' : $row['type'];
					$item['value'] = self::value( $row['value'] ?? '', $item['type'] );
					if ( '' !== trim( (string) ( is_scalar( $row['value'] ?? '' ) ? $row['value'] ?? '' : '' ) ) && '' === $item['value'] ) { return new WP_Error( 'business_info', 'Valore non valido per ' . $item['label'] . '.' ); }
				}
				$category = 'contacts' === $group ? $item['type'] : $group;
				$item['default'] = ! empty( $row['default'] ) && empty( $defaults[ $category ] );
				if ( $item['default'] ) { $defaults[ $category ] = true; }
				$clean[ $group ][] = $item;
			}
		}
		$p = is_array( $input['privacy'] ?? null ) ? $input['privacy'] : [];
		$clean['privacy']['different_controller'] = ! empty( $p['different_controller'] );
		foreach ( [ 'email_key', 'phone_key', 'location_key' ] as $key ) { $clean['privacy'][ $key ] = sanitize_key( self::text( $p[ $key ] ?? '' ) ); }
		foreach ( [ 'controller_name', 'controller_address', 'controller_vat', 'controller_tax_code', 'controller_phone', 'representative', 'dpo' ] as $key ) { $clean['privacy'][ $key ] = self::value( $p[ $key ] ?? '', in_array( $key, [ 'representative', 'dpo', 'controller_address' ], true ) ? 'textarea' : 'text' ); }
		$clean['privacy']['controller_email'] = sanitize_email( self::text( $p['controller_email'] ?? '' ) );
		if ( '' !== self::text( $p['controller_email'] ?? '' ) && '' === $clean['privacy']['controller_email'] ) { return new WP_Error( 'business_info', 'Inserisci un indirizzo email valido per il titolare diverso.' ); }
		$hours = Marrison_Addon_Business_Hours::sanitize( $input['hours'] ?? [] );
		if ( is_wp_error( $hours ) ) { return $hours; }
		$clean['hours'] = $hours;
		return $clean;
	}
	public static function record( $group, $key = '', $type = '' ) {
		$rows = self::data()[ $group ] ?? [];
		if ( '' !== $key ) { foreach ( $rows as $row ) { if ( $row['key'] === $key && ( ! $type || $row['type'] === $type ) ) { return $row; } } return []; }
		$rows = array_values( array_filter( $rows, static function( $row ) use ( $type ) { return ! $type || $row['type'] === $type; } ) );
		foreach ( $rows as $row ) { if ( ! empty( $row['default'] ) ) { return $row; } }
		return $rows[0] ?? [];
	}
	public static function address( $row ) {
		return implode( ', ', array_filter( [ $row['street'] ?? '', trim( ( $row['postcode'] ?? '' ) . ' ' . ( $row['city'] ?? '' ) ), $row['province'] ?? '', $row['region'] ?? '', $row['country'] ?? '' ], 'strlen' ) );
	}
	/** Descriptors back every graphical selector and the universal shortcode. */
	public static function fields() {
		$fields = []; $data = self::data();
		foreach ( self::company_fields() as $key => $label ) { $fields[ $key ] = [ 'label' => $label, 'type' => 'logo' === $key ? 'image' : ( 'pec' === $key ? 'email' : 'text' ), 'value' => $data['company'][ $key ] ?? '' ]; }
		foreach ( [ 'contacts' => 'Contatti', 'locations' => 'Sedi', 'social' => 'Social', 'custom' => 'Personalizzati' ] as $group => $label ) {
			foreach ( $data[ $group ] as $row ) {
				$path = $group . '.' . $row['key'];
				$fields[ $path ] = [ 'label' => $label . ' / ' . $row['label'], 'type' => 'locations' === $group ? 'text' : $row['type'], 'value' => 'locations' === $group ? self::address( $row ) : $row['value'] ];
				if ( 'locations' === $group ) { foreach ( [ 'street' => 'Via', 'postcode' => 'CAP', 'city' => 'Città', 'province' => 'Provincia', 'region' => 'Regione', 'country' => 'Nazione', 'lat' => 'Latitudine', 'lng' => 'Longitudine' ] as $part => $name ) { $fields[ $path . '.' . $part ] = [ 'label' => $row['label'] . ' / ' . $name, 'type' => 'text', 'value' => $row[ $part ] ?? '' ]; } }
			}
		}
		foreach ( self::controller() as $key => $value ) { $fields[ 'privacy.' . $key ] = [ 'label' => 'Privacy / ' . ( [ 'controller_name' => 'Titolare', 'controller_address' => 'Indirizzo titolare', 'controller_email' => 'Email privacy', 'controller_phone' => 'Telefono privacy', 'controller_vat' => 'Partita IVA titolare', 'controller_tax_code' => 'Codice fiscale titolare', 'representative' => 'Rappresentante UE', 'dpo' => 'DPO' ][ $key ] ?? $key ), 'type' => 'controller_email' === $key ? 'email' : ( 'controller_phone' === $key ? 'phone' : 'text' ), 'value' => $value ]; }
		return $fields;
	}
	public static function descriptor( $key ) {
		$fields = self::fields();
		if ( isset( $fields[ $key ] ) ) { return $fields[ $key ]; }
		// Short aliases are allowed only when unambiguous. Qualified paths always work.
		$matches = [];
		foreach ( $fields as $path => $field ) { if ( substr( $path, strpos( $path, '.' ) + 1 ) === $key && false !== strpos( $path, '.' ) ) { $matches[] = $field; } }
		return 1 === count( $matches ) ? $matches[0] : [ 'label' => '', 'type' => 'text', 'value' => '' ];
	}
	public static function get( $key, $fallback = '' ) { $value = self::descriptor( $key )['value']; return '' === $value ? $fallback : $value; }
	public static function url( $value, $type ) {
		if ( 'email' === $type ) { return $value ? 'mailto:' . sanitize_email( $value ) : ''; }
		if ( in_array( $type, [ 'phone', 'mobile', 'fax', 'whatsapp' ], true ) ) { $number = preg_replace( '/[^0-9+]/', '', $value ); return $number ? ( 'whatsapp' === $type ? 'https://wa.me/' . ltrim( $number, '+' ) : 'tel:' . $number ) : ''; }
		return 'url' === $type ? esc_url_raw( $value, [ 'http', 'https' ] ) : '';
	}
	public static function controller() {
		$data = self::data(); $p = $data['privacy'];
		$result = array_fill_keys( [ 'controller_name', 'controller_address', 'controller_email', 'controller_vat', 'controller_tax_code', 'controller_phone', 'representative', 'dpo' ], '' );
		foreach ( [ 'representative', 'dpo' ] as $key ) { $result[ $key ] = $p[ $key ] ?? ''; }
		$result['controller_name'] = $data['company']['legal_name'] ?? ''; if ( ! $result['controller_name'] ) { $result['controller_name'] = $data['company']['trade_name'] ?? ''; }
		if ( '' !== trim( $p['controller_name'] ?? '' ) ) { $result['controller_name'] = $p['controller_name']; }
		$result['controller_vat'] = $data['company']['vat'] ?? ''; $result['controller_tax_code'] = $data['company']['tax_code'] ?? '';
		$result['controller_email'] = self::record( 'contacts', $p['email_key'] ?? '', 'email' )['value'] ?? '';
		$phone = self::record( 'contacts', $p['phone_key'] ?? '' );
		if ( empty( $p['phone_key'] ) ) { $phone = self::record( 'contacts', '', 'phone' ); if ( ! $phone ) { $phone = self::record( 'contacts', '', 'mobile' ); } }
		$result['controller_phone'] = in_array( $phone['type'] ?? '', [ 'phone', 'mobile', 'whatsapp', 'fax' ], true ) ? $phone['value'] : '';
		$result['controller_address'] = self::address( self::record( 'locations', $p['location_key'] ?? '' ) );
		if ( ! empty( $p['different_controller'] ) ) {
			foreach ( [ 'controller_address', 'controller_email', 'controller_phone', 'controller_vat', 'controller_tax_code' ] as $key ) {
				if ( '' !== trim( $p[ $key ] ?? '' ) ) { $result[ $key ] = $p[ $key ]; }
			}
		}
		return $result;
	}
	public static function missing_privacy() {
		$p = self::controller(); $missing = [];
		foreach ( [ 'controller_name' => 'Titolare / ragione sociale', 'controller_address' => 'Indirizzo del titolare', 'controller_email' => 'Email per la privacy' ] as $key => $label ) { if ( '' === trim( $p[ $key ] ) ) { $missing[] = $label; } }
		return $missing;
	}
}
