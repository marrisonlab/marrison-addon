<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Marrison_Addon_Calendar_Sync {

	private const OPTION_NAME = 'marrison_calendar_sync_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'template_redirect', array( $this, 'download_ics' ) );
		add_shortcode( 'evento_calendario_link', array( $this, 'calendar_link_shortcode' ) );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'marrison_addon_panel',
			esc_html__( 'Calendar Sync', 'marrison-addon' ),
			esc_html__( 'Calendar Sync', 'marrison-addon' ),
			'manage_options',
			'marrison_addon_calendar_sync',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'marrison_addon_calendar_sync_group',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_default_settings(),
			)
		);

		add_settings_section(
			'marrison_calendar_sync_main_section',
			esc_html__( 'Impostazioni calendario', 'marrison-addon' ),
			'__return_empty_string',
			'marrison_addon_calendar_sync'
		);

		add_settings_field(
			'start_meta',
			esc_html__( 'Meta key inizio', 'marrison-addon' ),
			array( $this, 'render_text_field' ),
			'marrison_addon_calendar_sync',
			'marrison_calendar_sync_main_section',
			array(
				'label_for' => 'start_meta',
				'name'      => 'start_meta',
			)
		);

		add_settings_field(
			'end_meta',
			esc_html__( 'Meta key fine', 'marrison-addon' ),
			array( $this, 'render_text_field' ),
			'marrison_addon_calendar_sync',
			'marrison_calendar_sync_main_section',
			array(
				'label_for' => 'end_meta',
				'name'      => 'end_meta',
			)
		);

		add_settings_field(
			'ics_timezone',
			esc_html__( 'Fuso orario file ICS', 'marrison-addon' ),
			array( $this, 'render_select_field' ),
			'marrison_addon_calendar_sync',
			'marrison_calendar_sync_main_section',
			array(
				'label_for' => 'ics_timezone',
				'name'      => 'ics_timezone',
			)
		);
	}

	public function sanitize_settings( $input ) {
		$defaults = $this->get_default_settings();
		$input = is_array( $input ) ? $input : array();
		$timezone_options = $this->get_ics_timezone_options();
		$selected_timezone = isset( $input['ics_timezone'] ) ? sanitize_text_field( wp_unslash( $input['ics_timezone'] ) ) : $defaults['ics_timezone'];

		return array(
			'start_meta'   => isset( $input['start_meta'] ) ? sanitize_text_field( wp_unslash( $input['start_meta'] ) ) : $defaults['start_meta'],
			'end_meta'     => isset( $input['end_meta'] ) ? sanitize_text_field( wp_unslash( $input['end_meta'] ) ) : $defaults['end_meta'],
			'ics_timezone' => array_key_exists( $selected_timezone, $timezone_options ) ? $selected_timezone : $defaults['ics_timezone'],
		);
	}

	public function render_text_field( $args ) {
		$settings = $this->get_settings();
		$name = $args['name'];
		$value = isset( $settings[ $name ] ) ? $settings[ $name ] : '';

		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
			esc_attr( $name ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value )
		);
	}

	public function render_select_field( $args ) {
		$settings = $this->get_settings();
		$name = $args['name'];
		$current = isset( $settings[ $name ] ) ? (string) $settings[ $name ] : 'site';
		$options = $this->get_ics_timezone_options();

		printf(
			'<select id="%1$s" name="%2$s[%1$s]">',
			esc_attr( $name ),
			esc_attr( self::OPTION_NAME )
		);

		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Calendar Sync', 'marrison-addon' ); ?></h1>
			<p><code>[evento_calendario_link type="google"]</code></p>
			<p><code>[evento_calendario_link type="ics"]</code></p>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'marrison_addon_calendar_sync_group' );
				do_settings_sections( 'marrison_addon_calendar_sync' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function calendar_link_shortcode( $atts ) {
		$settings = $this->get_settings();

		$atts = shortcode_atts(
			array(
				'post_id'    => 0,
				'type'       => 'google',
				'start_meta' => '',
				'end_meta'   => '',
				'location'   => '',
			),
			$atts,
			'evento_calendario_link'
		);

		$post_id = absint( $atts['post_id'] );

		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return '';
		}

		$start_meta = '' !== trim( (string) $atts['start_meta'] ) ? sanitize_text_field( wp_unslash( $atts['start_meta'] ) ) : $settings['start_meta'];
		$end_meta = '' !== trim( (string) $atts['end_meta'] ) ? sanitize_text_field( wp_unslash( $atts['end_meta'] ) ) : $settings['end_meta'];
		$location = sanitize_text_field( wp_unslash( $atts['location'] ) );
		$type = sanitize_key( $atts['type'] );
		$links = $this->get_event_links( $post_id, $start_meta, $end_meta, $location );

		if ( false === $links || empty( $links[ $type ] ) ) {
			return '';
		}

		return esc_url( $links[ $type ] );
	}

	public function download_ics() {
		if ( empty( $_GET['marrison_event_ics'] ) ) {
			return;
		}

		$post_id = absint( wp_unslash( $_GET['marrison_event_ics'] ) );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_die( esc_html__( 'Evento non valido.', 'marrison-addon' ) );
		}

		$settings = $this->get_settings();
		$start_value = get_post_meta( $post_id, $settings['start_meta'], true );
		$end_value = get_post_meta( $post_id, $settings['end_meta'], true );
		$start_timestamp = $this->get_timestamp( $start_value );
		$end_timestamp = $this->get_timestamp( $end_value );
		$ics_timezone_id = isset( $settings['ics_timezone'] ) ? (string) $settings['ics_timezone'] : 'site';

		if ( ! $start_timestamp || ! $end_timestamp || $end_timestamp <= $start_timestamp ) {
			wp_die( esc_html__( 'Date evento non disponibili.', 'marrison-addon' ) );
		}

		$title = get_the_title( $post_id );
		$description = wp_strip_all_tags( get_the_excerpt( $post_id ) );
		$event_url = get_permalink( $post_id );
		$ics_start = $this->format_ics_datetime( $start_timestamp, $ics_timezone_id );
		$ics_end = $this->format_ics_datetime( $end_timestamp, $ics_timezone_id );
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = $host ? $host : wp_parse_url( site_url(), PHP_URL_HOST );
		$host = $host ? $host : 'localhost';
		$uid = $post_id . '-' . md5( $event_url ) . '@' . $host;

		$ics  = "BEGIN:VCALENDAR\r\n";
		$ics .= "VERSION:2.0\r\n";
		$ics .= 'PRODID:-//' . $this->ics_escape( get_bloginfo( 'name' ) ) . "//Event Calendar//IT\r\n";
		$ics .= "CALSCALE:GREGORIAN\r\n";
		$ics .= "METHOD:PUBLISH\r\n";
		$ics .= 'X-WR-TIMEZONE:' . $this->ics_escape( $ics_start['timezone'] ) . "\r\n";
		$ics .= "BEGIN:VEVENT\r\n";
		$ics .= 'UID:' . $this->ics_escape( $uid ) . "\r\n";
		$ics .= 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) . "\r\n";

		if ( 'UTC' === $ics_start['timezone'] ) {
			$ics .= 'DTSTART:' . $this->ics_escape( $ics_start['datetime'] ) . "\r\n";
			$ics .= 'DTEND:' . $this->ics_escape( $ics_end['datetime'] ) . "\r\n";
		} else {
			$ics .= 'DTSTART;TZID=' . $this->ics_escape( $ics_start['timezone'] ) . ':' . $this->ics_escape( $ics_start['datetime'] ) . "\r\n";
			$ics .= 'DTEND;TZID=' . $this->ics_escape( $ics_end['timezone'] ) . ':' . $this->ics_escape( $ics_end['datetime'] ) . "\r\n";
		}

		$ics .= 'SUMMARY:' . $this->ics_escape( $title ) . "\r\n";
		$ics .= 'DESCRIPTION:' . $this->ics_escape( trim( $description . "\n\n" . $event_url ) ) . "\r\n";
		$ics .= 'LOCATION:' . $this->ics_escape( '' ) . "\r\n";
		$ics .= 'URL:' . $this->ics_escape( $event_url ) . "\r\n";
		$ics .= "END:VEVENT\r\n";
		$ics .= "END:VCALENDAR\r\n";

		$filename = sanitize_title( $title );
		$filename = '' !== $filename ? $filename . '.ics' : 'evento.ics';

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $ics ) );

		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private function get_default_settings() {
		return array(
			'start_meta'   => 'data_ora_inizio',
			'end_meta'     => 'data_ora_fine',
			'ics_timezone' => 'site',
		);
	}

	private function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, $this->get_default_settings() );
	}

	private function get_timestamp( $value ) {
		if ( empty( $value ) ) {
			return false;
		}

		if ( is_numeric( $value ) ) {
			$timestamp = (int) $value;

			if ( $timestamp > 9999999999 ) {
				$timestamp = (int) floor( $timestamp / 1000 );
			}

			return $timestamp;
		}

		$timestamp = strtotime( (string) $value );

		return $timestamp ?: false;
	}

	private function format_utc( $timestamp ) {
		$date = new DateTime( '@' . $timestamp );
		$date->setTimezone( new DateTimeZone( 'UTC' ) );

		return $date->format( 'Ymd\THis\Z' );
	}

	private function get_site_timezone() {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}

		$timezone_string = get_option( 'timezone_string' );

		if ( ! empty( $timezone_string ) ) {
			return new DateTimeZone( $timezone_string );
		}

		$offset = (float) get_option( 'gmt_offset', 0 );
		$hours = (int) $offset;
		$mins = abs( ( $offset - $hours ) * 60 );
		$sign = ( $offset < 0 ) ? '-' : '+';

		return new DateTimeZone( sprintf( '%s%02d:%02d', $sign, abs( $hours ), $mins ) );
	}

	private function get_ics_timezone_object( $timezone_id ) {
		$timezone_id = (string) $timezone_id;

		if ( 'site' === $timezone_id || '' === $timezone_id ) {
			return $this->get_site_timezone();
		}

		return new DateTimeZone( $timezone_id );
	}

	private function format_ics_datetime( $timestamp, $timezone_id ) {
		$timezone = $this->get_ics_timezone_object( $timezone_id );
		$date = new DateTime( '@' . $timestamp );
		$date->setTimezone( $timezone );
		$timezone_name = $timezone->getName();
		$is_utc = 'UTC' === $timezone_name;

		return array(
			'datetime' => $is_utc ? $date->format( 'Ymd\THis\Z' ) : $date->format( 'Ymd\THis' ),
			'timezone' => $timezone_name,
			'is_utc'   => $is_utc,
		);
	}

	private function get_ics_timezone_options() {
		$options = array(
			'site' => esc_html__( 'Timezone del sito WordPress', 'marrison-addon' ),
			'UTC'  => 'UTC+00:00',
		);

		for ( $offset = -12; $offset <= 14; $offset++ ) {
			if ( 0 === $offset ) {
				continue;
			}

			$sign = $offset > 0 ? '+' : '-';
			$abs = abs( $offset );
			$label = sprintf( 'UTC%s%02d:00', $sign, $abs );
			$timezone = $offset > 0 ? sprintf( 'Etc/GMT-%d', $offset ) : sprintf( 'Etc/GMT+%d', abs( $offset ) );
			$options[ $timezone ] = $label;
		}

		return $options;
	}

	private function ics_escape( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( ';', '\;', $value );
		$value = str_replace( ',', '\,', $value );
		$value = str_replace( array( "\r\n", "\r", "\n" ), '\n', $value );

		return $value;
	}

	private function get_ics_url( $post_id ) {
		return add_query_arg(
			array(
				'marrison_event_ics' => absint( $post_id ),
			),
			home_url( '/' )
		);
	}

	private function get_google_calendar_url( $title, $description, $location, $start_timestamp, $end_timestamp ) {
		$google_dates = $this->format_utc( $start_timestamp ) . '/' . $this->format_utc( $end_timestamp );

		return add_query_arg(
			array(
				'action'   => 'TEMPLATE',
				'text'     => $title,
				'dates'    => $google_dates,
				'details'  => trim( $description ),
				'location' => $location,
			),
			'https://calendar.google.com/calendar/render'
		);
	}

	private function get_event_links( $post_id, $start_meta, $end_meta, $location = '' ) {
		$start_value = get_post_meta( $post_id, $start_meta, true );
		$end_value = get_post_meta( $post_id, $end_meta, true );
		$start_timestamp = $this->get_timestamp( $start_value );
		$end_timestamp = $this->get_timestamp( $end_value );

		if ( ! $start_timestamp || ! $end_timestamp || $end_timestamp <= $start_timestamp ) {
			return false;
		}

		$title = get_the_title( $post_id );
		$description = wp_strip_all_tags( get_the_excerpt( $post_id ) );
		$event_url = get_permalink( $post_id );

		return array(
			'google' => $this->get_google_calendar_url(
				$title,
				trim( $description . "\n\n" . $event_url ),
				$location,
				$start_timestamp,
				$end_timestamp
			),
			'ics'    => $this->get_ics_url( $post_id ),
		);
	}
}
