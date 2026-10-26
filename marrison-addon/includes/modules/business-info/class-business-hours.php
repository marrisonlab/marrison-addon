<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Marrison_Addon_Business_Hours {
	public static function days() { return [ 1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato', 7 => 'Domenica' ]; }
	private static function slots( $day ) {
		if ( empty( $day['open'] ) ) { return [ 'open' => false, 'slots' => [] ]; }
		$slots = [];
		foreach ( is_array( $day['slots'] ?? null ) ? $day['slots'] : [] as $slot ) {
			$start = is_string( $slot['start'] ?? null ) ? $slot['start'] : ''; $end = is_string( $slot['end'] ?? null ) ? $slot['end'] : '';
			if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $start ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $end ) || $end <= $start ) { return new WP_Error( 'business_hours', 'Fascia oraria non valida. La chiusura deve seguire l’apertura nello stesso giorno.' ); }
			$slots[] = [ 'start' => $start, 'end' => $end ];
		}
		usort( $slots, static function( $a, $b ) { return strcmp( $a['start'], $b['start'] ); } );
		$last = '';
		foreach ( $slots as $slot ) { if ( $slot['start'] < $last ) { return new WP_Error( 'business_hours', 'Le fasce orarie non possono sovrapporsi.' ); } $last = $slot['end']; }
		if ( ! $slots ) { return new WP_Error( 'business_hours', 'Aggiungi almeno una fascia ai giorni aperti oppure indica Chiuso.' ); }
		$merged = [];
		foreach ( $slots as $slot ) {
			$index = count( $merged ) - 1;
			if ( $index >= 0 && $merged[ $index ]['end'] === $slot['start'] ) { $merged[ $index ]['end'] = $slot['end']; }
			else { $merged[] = $slot; }
		}
		return [ 'open' => true, 'slots' => $merged ];
	}
	public static function sanitize( $hours ) {
		$hours = is_array( $hours ) ? $hours : []; $clean = [ 'weekly' => [], 'exceptions' => [] ];
		foreach ( self::days() as $n => $name ) { $day = self::slots( $hours['weekly'][ $n ] ?? [] ); if ( is_wp_error( $day ) ) { return new WP_Error( 'business_hours', $name . ': ' . $day->get_error_message() ); } $clean['weekly'][ $n ] = $day; }
		$dates = []; $ids = [];
		foreach ( is_array( $hours['exceptions'] ?? null ) ? $hours['exceptions'] : [] as $row ) {
			$date = $row['date'] ?? '';
			if ( ! Marrison_Addon_Business_Info_Store::date_valid( $date ) || isset( $dates[ $date ] ) ) { return new WP_Error( 'business_hours', 'Ogni eccezione deve avere una data valida e distinta.' ); }
			$dates[ $date ] = true; $day = self::slots( $row ); if ( is_wp_error( $day ) ) { return $day; }
			$id = sanitize_key( is_scalar( $row['id'] ?? null ) ? (string) $row['id'] : '' ); if ( ! $id ) { $id = wp_generate_uuid4(); }
			if ( isset( $ids[ $id ] ) ) { return new WP_Error( 'business_hours', 'Identificatore eccezione duplicato.' ); } $ids[ $id ] = true;
			$clean['exceptions'][] = array_merge( $day, [ 'id' => $id, 'date' => $date, 'label' => sanitize_text_field( is_scalar( $row['label'] ?? null ) ? (string) $row['label'] : '' ) ] );
		}
		return $clean;
	}
	public static function day( DateTimeImmutable $date, $hours = null ) {
		$hours = null === $hours ? Marrison_Addon_Business_Info_Store::data()['hours'] : $hours;
		foreach ( $hours['exceptions'] ?? [] as $row ) { if ( $row['date'] === $date->format( 'Y-m-d' ) ) { return $row; } }
		return $hours['weekly'][ (int) $date->format( 'N' ) ] ?? [ 'open' => false, 'slots' => [] ];
	}
	public static function now() { return new DateTimeImmutable( 'now', wp_timezone() ); }
	public static function status( ?DateTimeImmutable $now = null ) {
		$now = null === $now ? self::now() : $now->setTimezone( wp_timezone() );
		$hours = Marrison_Addon_Business_Info_Store::data()['hours']; $today = self::day( $now, $hours ); $past = false;
		$result = [ 'state' => 'closed', 'next_open' => null, 'next_close' => null, 'server_time' => $now->getTimestamp() ];
		foreach ( ! empty( $today['open'] ) ? $today['slots'] : [] as $slot ) {
			$start = new DateTimeImmutable( $now->format( 'Y-m-d' ) . ' ' . $slot['start'], wp_timezone() ); $end = new DateTimeImmutable( $now->format( 'Y-m-d' ) . ' ' . $slot['end'], wp_timezone() );
			if ( $start <= $now && $now < $end ) { $result['state'] = 'open'; $result['next_close'] = $end->getTimestamp(); }
			if ( $end <= $now ) { $past = true; }
			if ( $start > $now && null === $result['next_open'] ) { $result['next_open'] = $start->getTimestamp(); if ( 'open' !== $result['state'] && $past ) { $result['state'] = 'pause'; } }
		}
		// Search a normal week and the week after every exception. This also finds an
		// isolated future opening without scanning every calendar day for years.
		$dates = [];
		foreach ( array_merge( [ [ 'date' => $now->format( 'Y-m-d' ) ] ], $hours['exceptions'] ?? [] ) as $exception ) {
			$base = new DateTimeImmutable( $exception['date'], wp_timezone() );
			for ( $i = 0; $i <= 7; $i++ ) { $date = $base->modify( '+' . $i . ' days' ); if ( $date->format( 'Y-m-d' ) >= $now->format( 'Y-m-d' ) ) { $dates[ $date->format( 'Y-m-d' ) ] = $date; } }
		}
		ksort( $dates );
		foreach ( $dates as $date ) {
			if ( null !== $result['next_open'] && null !== $result['next_close'] ) { break; }
			$day = self::day( $date, $hours );
			foreach ( ! empty( $day['open'] ) ? $day['slots'] : [] as $slot ) {
				$start = new DateTimeImmutable( $date->format( 'Y-m-d' ) . ' ' . $slot['start'], wp_timezone() ); $end = new DateTimeImmutable( $date->format( 'Y-m-d' ) . ' ' . $slot['end'], wp_timezone() );
				if ( $start > $now ) { if ( null === $result['next_open'] ) { $result['next_open'] = $start->getTimestamp(); } if ( null === $result['next_close'] ) { $result['next_close'] = $end->getTimestamp(); } break; }
			}
		}
		$transitions = array_filter( [ $result['next_open'], $result['next_close'] ], static function( $time ) use ( $now ) { return $time && $time > $now->getTimestamp(); } );
		$result['refresh_in'] = $transitions ? max( 1, min( 60, min( $transitions ) - $now->getTimestamp() ) ) : 60;
		return $result;
	}
	public static function time( $value, $format = '24' ) { $d = DateTimeImmutable::createFromFormat( '!H:i', $value, wp_timezone() ); return $d ? $d->format( '12' === $format ? 'g:i a' : 'H:i' ) : ''; }
	public static function format_day( $day, $options = [] ) {
		if ( empty( $day['open'] ) ) { return $options['closed_text'] ?? 'Chiuso'; }
		$parts = [];
		foreach ( $day['slots'] as $slot ) { $parts[] = self::time( $slot['start'], $options['format'] ?? '24' ) . '–' . self::time( $slot['end'], $options['format'] ?? '24' ); }
		return implode( $options['separator'] ?? ' / ', $parts );
	}
	public static function status_text( $status, $options = [] ) {
		$state = $status['state']; $label = 'open' === $state ? 'Aperto ora' : ( 'pause' === $state ? 'In pausa' : ( $options['closed_text'] ?? 'Chiuso' ) );
		$timestamp = 'open' === $state ? $status['next_close'] : $status['next_open'];
		if ( $timestamp ) {
			$date = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() ); $now = ( new DateTimeImmutable( '@' . $status['server_time'] ) )->setTimezone( wp_timezone() );
			$time = $date->format( '12' === ( $options['format'] ?? '24' ) ? 'g:i a' : 'H:i' );
			if ( $date->format( 'Y-m-d' ) !== $now->format( 'Y-m-d' ) ) { $time = $date->format( 'd/m/Y' ) . ' ' . $time; }
			$label .= ' · ' . ( 'open' === $state ? 'Chiude alle ' : ( 'pause' === $state ? 'Riapre alle ' : 'Apre alle ' ) ) . $time;
		}
		return $label;
	}
	public static function rows( $options = [] ) {
		$hours = Marrison_Addon_Business_Info_Store::data()['hours']; $days = self::days(); $order = array_keys( $days );
		$first = (int) ( $options['first_day'] ?? 1 ); if ( $first >= 1 && $first <= 7 ) { $order = array_merge( range( $first, 7 ), $first > 1 ? range( 1, $first - 1 ) : [] ); }
		$rows = []; $last = null;
		foreach ( $order as $n ) {
			$day = $hours['weekly'][ $n ] ?? [ 'open' => false, 'slots' => [] ];
			if ( empty( $day['open'] ) && ! empty( $options['hide_closed'] ) ) { $last = null; continue; }
			$label = ! empty( $options['short_days'] ) ? mb_substr( $days[ $n ], 0, 3 ) : $days[ $n ]; $signature = wp_json_encode( $day );
			if ( ! empty( $options['group_days'] ) && $last === $signature ) { $i = count( $rows ) - 1; $rows[ $i ]['last'] = $label; $rows[ $i ]['label'] = $rows[ $i ]['first'] . '–' . $label; }
			else { $rows[] = [ 'first' => $label, 'last' => $label, 'label' => $label, 'hours' => self::format_day( $day, $options ) ]; }
			$last = $signature;
		}
		return $rows;
	}
}
