<?php
/**
 * Booking Service Interface.
 *
 * @package SlotNova\Booking\ExtensionManager\Contracts
 */

namespace SlotNova\Booking\ExtensionManager\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface BookingServiceInterface {
	public function getBooking( int $bookingId ): ?array;
	public function createBooking( array $data ): int;
	public function updateBooking( int $bookingId, array $data ): bool;
	public function listBookings( array $args = [] ): array;
}
