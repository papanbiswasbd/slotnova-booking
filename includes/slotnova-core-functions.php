<?php
/**
 * SlotNova Core Functions
 *
 * Global helper functions for developers and add-on plugins.
 *
 * @package SlotNova\Booking
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'slotnova_booking' ) ) {
	/**
	 * Main instance of SlotNova Booking for developers and addon plugins.
	 *
	 * @return \SlotNova\Booking\Plugin
	 */
	function slotnova_booking() {
		return \SlotNova\Booking\Plugin::instance();
	}
}

if ( ! function_exists( 'slotnova_parse_date' ) ) {
	/**
	 * Parse date string and format to Y-m-d reliably across all formats and ordinal suffixes.
	 *
	 * @param string $date_str Raw date string.
	 * @return string|false Standardized Y-m-d string or false.
	 */
	function slotnova_parse_date( $date_str ) {
		if ( empty( $date_str ) ) {
			return false;
		}
		$clean = trim( (string) $date_str );

		// 1. Direct Y-m-d matching (e.g., "2026-08-18")
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $clean, $matches ) ) {
			if ( checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
				return sprintf( '%04d-%02d-%02d', $matches[1], $matches[2], $matches[3] );
			}
		}

		// 2. Strip ordinal suffixes: 1st, 2nd, 3rd, 4th, etc.
		$clean = preg_replace( '/(\d+)(st|nd|rd|th)/i', '$1', $clean );

		// 3. If no 4-digit year is present, append current year to prevent strtotime parsing numbers as hours
		if ( ! preg_match( '/\b\d{4}\b/', $clean ) ) {
			$clean .= ' ' . date( 'Y' );
		}

		// 4. Parse using DateTime with UTC timezone to prevent any offset shifts
		try {
			$dt = new DateTime( $clean, new DateTimeZone( 'UTC' ) );
			return $dt->format( 'Y-m-d' );
		} catch ( Exception $e ) {
			$ts = strtotime( $clean . ' 12:00:00 UTC' );
			if ( false !== $ts ) {
				return gmdate( 'Y-m-d', $ts );
			}
		}

		return false;
	}
}

if ( ! function_exists( 'slotnova_parse_time' ) ) {
	/**
	 * Parse time string and format to 12h AM/PM (h:i A) reliably.
	 *
	 * @param string $time_str Raw time string.
	 * @return string|false Standardized h:i A string or false.
	 */
	function slotnova_parse_time( $time_str ) {
		if ( empty( $time_str ) ) {
			return false;
		}
		$clean = trim( (string) $time_str );
		$ts = strtotime( '1970-01-01 ' . $clean . ' UTC' );
		if ( false === $ts ) {
			$ts = strtotime( $clean );
		}
		if ( false === $ts ) {
			return false;
		}
		return gmdate( 'h:i A', $ts );
	}
}
