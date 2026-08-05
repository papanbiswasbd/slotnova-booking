<?php
/**
 * WooCommerce Checkout Validator.
 *
 * Enforces atomic capacity validation during final checkout submission to prevent race condition overbooking.
 *
 * @package SlotNova\Extensions\GroupBooking\WooCommerce
 */

namespace SlotNova\Extensions\GroupBooking\WooCommerce;

use SlotNova\Extensions\GroupBooking\Services\CapacityValidationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WcCheckoutValidator {

	private CapacityValidationService $capacityService;

	public function __construct( CapacityValidationService $capacityService ) {
		$this->capacityService = $capacityService;
	}

	/**
	 * Validate cart items during checkout processing.
	 *
	 * @param array     $data Posted data.
	 * @param \WP_Error $errors Validation errors object.
	 * @return void
	 */
	public function validateCheckout( array $data, \WP_Error $errors ): void {
		if ( null === WC()->cart ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cartItem ) {
			if ( isset( $cartItem['slotnova_group_booking'] ) && isset( $cartItem['slotnova_booking'] ) ) {
				$groupData    = $cartItem['slotnova_group_booking'];
				$bookingData  = $cartItem['slotnova_booking'];
				$requestedQty = max( 1, (int) $groupData['quantity'] );
				$productId    = (int) $cartItem['product_id'];
				$serviceId    = (int) $bookingData['service_id'];
				$date         = (string) $bookingData['date'];
				$time         = isset( $bookingData['time'] ) ? (string) $bookingData['time'] : '';

				$validation = $this->capacityService->validateRequestedQuantity( $requestedQty, $productId, $serviceId, $date, $time );

				if ( ! $validation['valid'] ) {
					$errors->add(
						'slotnova_group_capacity_exceeded',
						sprintf(
							/* translators: 1: Product/service title, 2: Error message */
							__( 'Could not complete checkout for "%1$s": %2$s', 'slotnova-booking' ),
							get_the_title( $productId ),
							$validation['message']
						)
					);
				}

				do_action( 'slotnova_group_after_validation', $validation, $cartItem );
			}
		}
	}
}
