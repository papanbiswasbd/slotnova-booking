<?php
/**
 * Action Hooks Manager.
 *
 * Centralizes third-party action hooks dispatched by the Group Booking extension.
 *
 * @package SlotNova\Extensions\GroupBooking\Hooks
 */

namespace SlotNova\Extensions\GroupBooking\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ActionHooksManager {

	/**
	 * Documented Action Hooks:
	 *
	 * 1. do_action( 'slotnova_group_booking_created', $orderId )
	 * 2. do_action( 'slotnova_group_after_booking', $productId, $cartBookingMeta )
	 * 3. do_action( 'slotnova_group_waiting_list_added', $entryId, $data )
	 * 4. do_action( 'slotnova_group_waiting_list_promoted', $entryObject )
	 * 5. do_action( 'slotnova_group_attendance_saved', $participantId, $status, $notes )
	 * 6. do_action( 'slotnova_group_session_cancelled', $productId, $serviceId, $date, $time, $booked, $minCap )
	 */
	public function register(): void {
		// Hook listeners for custom developer actions if needed
	}
}
