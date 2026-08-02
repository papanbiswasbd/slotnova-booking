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
