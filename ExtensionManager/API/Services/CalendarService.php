<?php
/**
 * Calendar Domain Service.
 *
 * @package SlotNova\Booking\ExtensionManager\API\Services
 */

namespace SlotNova\Booking\ExtensionManager\API\Services;

use SlotNova\Booking\ExtensionManager\Contracts\CalendarServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CalendarService implements CalendarServiceInterface {

	public function getAvailableSlots( int $serviceId, string $date, int $staffId = 0 ): array {
		return apply_filters( 'slotnova_calendar_available_slots', [], $serviceId, $date, $staffId );
	}
}
