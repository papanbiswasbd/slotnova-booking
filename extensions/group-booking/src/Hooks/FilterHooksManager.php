<?php
/**
 * Filter Hooks Manager.
 *
 * Centralizes third-party filter hooks available for extension customization.
 *
 * @package SlotNova\Extensions\GroupBooking\Hooks
 */

namespace SlotNova\Extensions\GroupBooking\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FilterHooksManager {

	/**
	 * Documented Filter Hooks:
	 *
	 * 1. apply_filters( 'slotnova_group_remaining_capacity', $remaining, $productId, $serviceId, $date, $time )
	 * 2. apply_filters( 'slotnova_group_before_validation', $passed, $productId, $count, $serviceId, $date, $time )
	 * 3. apply_filters( 'slotnova_group_after_validation', $validation, $cartItem )
	 * 4. apply_filters( 'slotnova_group_calculated_total_price', $totalPrice, $basePrice, $count, $productId, $pricingMode )
	 * 5. apply_filters( 'slotnova_group_calculated_unit_price', $unitPrice, $basePrice, $count, $productId, $pricingMode )
	 */
	public function register(): void {
		// Hook listeners for custom developer filters if needed
	}
}
