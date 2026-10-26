<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Marrison_Addon_Business_Info_Display {
	public static function options( $input ) {
		$input = is_array( $input ) ? $input : []; $clean = [];
		foreach ( [ 'hide_closed', 'group_days', 'short_days' ] as $key ) { $clean[ $key ] = in_array( $input[ $key ] ?? '', [ true, 1, '1', 'yes' ], true ); }
		$clean['icons'] = ! in_array( $input['icons'] ?? 'yes', [ false, 0, '0', 'no', '' ], true );
		foreach ( [ 'separator' => ' / ', 'closed_text' => 'Chiuso' ] as $key => $default ) { $clean[ $key ] = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? sanitize_text_field( (string) $input[ $key ] ) : $default; }
		$clean['layout'] = in_array( $input['layout'] ?? '', [ 'list', 'compact', 'status' ], true ) ? $input['layout'] : 'list';
		$clean['first_day'] = max( 1, min( 7, (int) ( $input['first_day'] ?? 1 ) ) );
		$clean['format'] = '12' === (string) ( $input['format'] ?? '24' ) ? '12' : '24';
		return $clean;
	}
	public static function info_shortcode( $atts ) {
		$atts = shortcode_atts( [ 'key' => '', 'output' => 'value', 'fallback' => '' ], $atts, 'marrison_info' );
		$field = Marrison_Addon_Business_Info_Store::descriptor( $atts['key'] ); $value = $field['value'];
		if ( '' === $value || 0 === $value ) { return esc_html( $atts['fallback'] ); }
		if ( 'image' === $field['type'] ) { return 'html' === $atts['output'] ? wp_get_attachment_image( absint( $value ), 'full' ) : esc_url( wp_get_attachment_image_url( absint( $value ), 'full' ) ?: '' ); }
		$url = Marrison_Addon_Business_Info_Store::url( $value, $field['type'] );
		if ( 'link' === $atts['output'] ) { return $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $value ) . '</a>' : esc_html( $value ); }
		return 'html' === $atts['output'] ? nl2br( esc_html( $value ) ) : esc_html( $value );
	}
	public static function rows_html( $rows ) {
		$html = '';
		foreach ( $rows as $row ) { $html .= '<div class="marrison-bh__row"><span class="marrison-bh__day">' . esc_html( $row['label'] ) . '</span><span class="marrison-bh__times">' . esc_html( $row['hours'] ) . '</span></div>'; }
		return $html;
	}
	public static function exceptions( $options ) {
		$today = Marrison_Addon_Business_Hours::now()->format( 'Y-m-d' ); $rows = [];
		foreach ( Marrison_Addon_Business_Info_Store::data()['hours']['exceptions'] ?? [] as $row ) { if ( $row['date'] >= $today ) { $rows[] = [ 'date' => $row['date'], 'label' => date_create_immutable( $row['date'] )->format( 'd/m/Y' ) . ( $row['label'] ? ' · ' . $row['label'] : '' ), 'hours' => Marrison_Addon_Business_Hours::format_day( $row, $options ) ]; } }
		usort( $rows, static function( $a, $b ) { return strcmp( $a['date'], $b['date'] ); } ); return $rows;
	}
	public static function hours_shortcode( $atts ) { return self::render( is_array( $atts ) ? $atts : [] ); }
	public static function status_shortcode( $atts ) { return self::render( array_merge( is_array( $atts ) ? $atts : [], [ 'layout' => 'status' ] ) ); }
	public static function render( $input = [], $icon = null ) {
		$options = self::options( $input );
		wp_enqueue_style( 'marrison-business-hours' ); wp_enqueue_script( 'marrison-business-hours' );
		$endpoint = admin_url( 'admin-ajax.php' );
		$config = [ 'options' => $options, 'endpoint' => $endpoint ];
		$html = '<div class="marrison-bh marrison-bh--' . esc_attr( $options['layout'] ) . '" data-marrison-business-hours="' . esc_attr( wp_json_encode( $config ) ) . '">';
		if ( 'status' !== $options['layout'] ) {
			$html .= '<div class="marrison-bh__rows">' . self::rows_html( Marrison_Addon_Business_Hours::rows( $options ) ) . '</div>';
			$exceptions = self::exceptions( $options );
			$html .= '<div class="marrison-bh__exceptions"' . ( $exceptions ? '' : ' hidden' ) . '><p class="marrison-bh__exceptions-title">' . esc_html__( 'Variazioni per data', 'marrison-addon' ) . '</p><div class="marrison-bh__exception-rows">' . self::rows_html( $exceptions ) . '</div></div>';
		}
		// Never embed a cached claim about current opening state. Hydrate from a no-store response.
		$html .= '<div class="marrison-bh__status" data-state="unknown" role="status" aria-live="polite">';
		if ( $options['icons'] ) { $html .= '<span class="marrison-bh__icon" aria-hidden="true">' . ( null !== $icon ? $icon : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>' ) . '</span>'; }
		$html .= '<span class="marrison-bh__status-text">' . esc_html__( 'Verifica orari…', 'marrison-addon' ) . '</span></div><noscript>' . esc_html__( 'Attiva JavaScript per conoscere lo stato di apertura aggiornato.', 'marrison-addon' ) . '</noscript></div>';
		return $html;
	}
	public static function time_field( $field, $day = 1, $options = [] ) {
		$now = Marrison_Addon_Business_Hours::now();
		if ( 'today' === $field ) { return Marrison_Addon_Business_Hours::format_day( Marrison_Addon_Business_Hours::day( $now ), $options ); }
		if ( 'day' === $field ) { $hours = Marrison_Addon_Business_Info_Store::data()['hours']; return Marrison_Addon_Business_Hours::format_day( $hours['weekly'][ $day ] ?? [], $options ); }
		$status = Marrison_Addon_Business_Hours::status();
		if ( 'status' === $field ) { return Marrison_Addon_Business_Hours::status_text( $status, $options ); }
		$timestamp = $status[ $field ] ?? null;
		return $timestamp ? wp_date( 'd/m/Y ' . ( '12' === ( $options['format'] ?? '24' ) ? 'g:i a' : 'H:i' ), $timestamp, wp_timezone() ) : '';
	}
	public static function dynamic_field( $field, $day = 1, $options = [] ) {
		wp_enqueue_script( 'marrison-business-hours' );
		$config = [ 'field' => $field, 'day' => (int) $day, 'options' => self::options( $options ), 'endpoint' => admin_url( 'admin-ajax.php' ) ];
		return '<span data-marrison-business-hours="' . esc_attr( wp_json_encode( $config ) ) . '">' . esc_html__( 'Verifica orari…', 'marrison-addon' ) . '</span>';
	}
	public static function ajax() {
		nocache_headers();
		$raw = isset( $_GET['options'] ) && is_string( $_GET['options'] ) ? wp_unslash( $_GET['options'] ) : '{}';
		$options = self::options( json_decode( substr( $raw, 0, 4000 ), true ) );
		$status = Marrison_Addon_Business_Hours::status();
		$data = [ 'status' => $status, 'text' => Marrison_Addon_Business_Hours::status_text( $status, $options ), 'rows' => Marrison_Addon_Business_Hours::rows( $options ), 'exceptions' => self::exceptions( $options ) ];
		$field = isset( $_GET['field'] ) && is_string( $_GET['field'] ) ? sanitize_key( wp_unslash( $_GET['field'] ) ) : '';
		if ( in_array( $field, [ 'today', 'day', 'status', 'next_open', 'next_close' ], true ) ) { $data['value'] = self::time_field( $field, max( 1, min( 7, (int) ( $_GET['day'] ?? 1 ) ) ), $options ); }
		wp_send_json_success( $data );
	}
}
