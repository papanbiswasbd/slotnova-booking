<?php
/**
 * Capacity Badge Renderer.
 *
 * Renders frontend capacity status indicators.
 *
 * @package SlotNova\Extensions\GroupBooking\Frontend\Components
 */

namespace SlotNova\Extensions\GroupBooking\Frontend\Components;

use SlotNova\Extensions\GroupBooking\Services\CapacityValidationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CapacityBadgeRenderer {

	private CapacityValidationService $capacityService;

	public function __construct( CapacityValidationService $capacityService ) {
		$this->capacityService = $capacityService;
	}

	/**
	 * Output capacity badge HTML.
	 *
	 * @param mixed  $productOrId Product ID or WC_Product object.
	 * @param int    $serviceId Service ID.
	 * @param string $date Selected date.
	 * @param string $time Selected time.
	 * @return void
	 */
	public function renderBadge( $productOrId = 0, int $serviceId = 0, string $date = '', string $time = '' ): void {
		// Disabled per user request
		return;
	}
}
