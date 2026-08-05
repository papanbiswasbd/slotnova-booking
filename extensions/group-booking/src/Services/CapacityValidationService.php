<?php
/**
 * Capacity Validation Service.
 *
 * Core engine executing atomic count queries and validation for group slot capacity.
 *
 * @package SlotNova\Extensions\GroupBooking\Services
 */

namespace SlotNova\Extensions\GroupBooking\Services;

use SlotNova\Extensions\GroupBooking\Repositories\ParticipantRepository;
use SlotNova\Extensions\GroupBooking\Helpers\GroupBookingHelper;
use SlotNova\Extensions\GroupBooking\Models\Capacity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CapacityValidationService {

	private ParticipantRepository $participantRepo;

	public function __construct( ParticipantRepository $participantRepo ) {
		$this->participantRepo = $participantRepo;
	}

	/**
	 * Get full Capacity model object for a given product/service date & time slot.
	 *
	 * @param int    $productId Product ID.
	 * @param int    $serviceId Service term ID.
	 * @param string $date Date (Y-m-d).
	 * @param string $time Time.
	 * @return Capacity
	 */
	public function getSlotCapacity( int $productId, int $serviceId, string $date, string $time = '' ): Capacity {
		$max = GroupBookingHelper::getMaxCapacity( $productId, $serviceId );
		$min = GroupBookingHelper::getMinCapacity( $productId, $serviceId );

		$booked = $this->getReservedSeatCount( $productId, $serviceId, $date, $time );

		return new Capacity( $max, $min, $booked );
	}

	/**
	 * Get total reserved seats for a slot.
	 * Includes DB confirmed participants + active cart holds.
	 *
	 * @param int    $productId Product ID.
	 * @param int    $serviceId Service term ID.
	 * @param string $date Date.
	 * @param string $time Time.
	 * @return int
	 */
	public function getReservedSeatCount( int $productId, int $serviceId, string $date, string $time = '' ): int {
		$dbBooked = $this->participantRepo->getParticipantCountForSlot( $productId, $serviceId, $date, $time );

		// Filter capacity via hook to allow third party overrides or cart holds
		$booked = apply_filters( 'slotnova_group_slot_booked_seats', $dbBooked, $productId, $serviceId, $date, $time );

		return (int) $booked;
	}

	/**
	 * Check if requested participant quantity is valid for slot capacity.
	 *
	 * @param int    $requestedQty Requested seat quantity.
	 * @param int    $productId Product ID.
	 * @param int    $serviceId Service term ID.
	 * @param string $date Date.
	 * @param string $time Time.
	 * @return array array( 'valid' => bool, 'remaining' => int, 'message' => string )
	 */
	public function validateRequestedQuantity( int $requestedQty, int $productId, int $serviceId, string $date, string $time = '' ): array {
		if ( $requestedQty <= 0 ) {
			return array(
				'valid'     => false,
				'remaining' => 0,
				'message'   => __( 'Please select at least 1 participant.', 'slotnova-booking' ),
			);
		}

		$capacity  = $this->getSlotCapacity( $productId, $serviceId, $date, $time );
		$remaining = $capacity->remainingSeats;

		if ( $capacity->isFull ) {
			return array(
				'valid'     => false,
				'remaining' => 0,
				'message'   => __( 'This time slot is fully booked.', 'slotnova-booking' ),
			);
		}

		if ( $requestedQty > $remaining ) {
			return array(
				'valid'     => false,
				'remaining' => $remaining,
				'message'   => sprintf(
					/* translators: %d: number of remaining seats */
					_n( 'Only %d seat remaining for this time slot.', 'Only %d seats remaining for this time slot.', $remaining, 'slotnova-booking' ),
					$remaining
				),
			);
		}

		return array(
			'valid'     => true,
			'remaining' => $remaining,
			'message'   => __( 'Seats available.', 'slotnova-booking' ),
		);
	}
}
