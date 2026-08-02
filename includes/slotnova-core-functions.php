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
		// Strip ordinal suffixes: 1st, 2nd, 3rd, 4th, etc.
		$clean = preg_replace( '/(\d+)(st|nd|rd|th)/i', '$1', $clean );

		$ts = strtotime( $clean . ' 12:00:00 UTC' );
		if ( false === $ts ) {
			$ts = strtotime( $clean );
		}
		if ( false === $ts ) {
			return false;
		}
		return gmdate( 'Y-m-d', $ts );
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
