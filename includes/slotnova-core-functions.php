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

if ( ! function_exists( 'slotnova' ) ) {
	/**
	 * Main public API facade accessor for SlotNova Booking extensions.
	 *
	 * @return \SlotNova\Booking\ExtensionManager\API\SlotNovaApi
	 */
	function slotnova(): \SlotNova\Booking\ExtensionManager\API\SlotNovaApi {
		return \SlotNova\Booking\ExtensionManager\Container\Container::getInstance()->make( \SlotNova\Booking\ExtensionManager\API\SlotNovaApi::class );
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
			$clean .= ' ' . gmdate( 'Y' );
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

if ( ! function_exists( 'slotnova_format_time' ) ) {
	/**
	 * Format time slot display according to global SlotNova time_slot_format setting.
	 *
	 * Supports:
	 * - 'start_only': "09:00 AM"
	 * - 'range': "09:00 AM - 10:00 AM"
	 *
	 * @param string $slot_time Start time string (e.g., "09:00 AM").
	 * @param int    $duration_minutes Duration in minutes (0 to use global option).
	 * @return string Formatted time slot string.
	 */
	function slotnova_format_time( $slot_time, $duration_minutes = 0 ) {
		if ( empty( $slot_time ) ) {
			return '';
		}

		$clean = trim( (string) $slot_time );
		$format_mode = get_option( 'slotnova_time_slot_format', 'start_only' );

		if ( 'range' !== $format_mode ) {
			return $clean;
		}

		// If time slot already contains a range "-", return as is
		if ( strpos( $clean, '-' ) !== false ) {
			return $clean;
		}

		$ts = strtotime( '1970-01-01 ' . $clean . ' UTC' );
		if ( false === $ts ) {
			$ts = strtotime( $clean );
		}
		if ( false === $ts ) {
			return $clean;
		}

		$duration = (int) $duration_minutes;
		if ( $duration <= 0 ) {
			$duration = (int) get_option( 'slotnova_slot_duration', 60 );
		}
		if ( $duration <= 0 ) {
			$duration = 60;
		}

		$end_ts  = $ts + ( $duration * 60 );
		$end_str = gmdate( 'h:i A', $end_ts );

		return $clean . ' - ' . $end_str;
	}
}

if ( ! function_exists( 'slotnova_get_product_base_price' ) ) {
	/**
	 * Get the global base booking price for a product.
	 *
	 * Falls back to _slotnova_base_price meta, then to WooCommerce regular/sale price.
	 *
	 * @param int|WC_Product $product Product object or ID.
	 * @return float
	 */
	function slotnova_get_product_base_price( $product ) {
		if ( is_numeric( $product ) ) {
			$product_id  = intval( $product );
			$product_obj = wc_get_product( $product_id );
		} elseif ( $product instanceof WC_Product ) {
			$product_id  = $product->get_id();
			$product_obj = $product;
		} else {
			return 0.0;
		}

		$base_price = get_post_meta( $product_id, '_slotnova_base_price', true );
		if ( '' !== $base_price && null !== $base_price && is_numeric( $base_price ) ) {
			return floatval( $base_price );
		}

		if ( $product_obj ) {
			return floatval( $product_obj->get_price() );
		}

		return 0.0;
	}
}
