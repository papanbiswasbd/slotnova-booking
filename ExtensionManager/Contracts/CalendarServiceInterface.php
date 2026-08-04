<?php
/**
 * Calendar Service Interface.
 *
 * @package SlotNova\Booking\ExtensionManager\Contracts
 */

namespace SlotNova\Booking\ExtensionManager\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface CalendarServiceInterface {
	public function getAvailableSlots( int $serviceId, string $date, int $staffId = 0 ): array;
}
