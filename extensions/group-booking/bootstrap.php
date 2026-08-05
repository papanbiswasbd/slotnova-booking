<?php
/**
 * Bootstrap entry point for SlotNova Group Booking Extension.
 *
 * @package SlotNova\Extensions\GroupBooking
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/Helpers/GroupBookingHelper.php';
require_once __DIR__ . '/src/GroupBookingExtension.php';

/**
 * Public global accessor function for SlotNova Group Booking extension.
 *
 * @return \SlotNova\Extensions\GroupBooking\GroupBookingExtension
 */
if ( ! function_exists( 'slotnova_group_booking' ) ) {
	function slotnova_group_booking(): \SlotNova\Extensions\GroupBooking\GroupBookingExtension {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new \SlotNova\Extensions\GroupBooking\GroupBookingExtension();
		}
		return $instance;
	}
}

return slotnova_group_booking();
