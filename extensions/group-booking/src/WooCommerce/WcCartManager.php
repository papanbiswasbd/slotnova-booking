<?php
/**
 * WooCommerce Cart Manager.
 *
 * Handles cart item validation, participant data attachment, price updates, and cart item meta rendering.
 *
 * @package SlotNova\Extensions\GroupBooking\WooCommerce
 */

namespace SlotNova\Extensions\GroupBooking\WooCommerce;

use SlotNova\Extensions\GroupBooking\Services\CapacityValidationService;
use SlotNova\Extensions\GroupBooking\Services\PricingEngineService;
use SlotNova\Extensions\GroupBooking\Helpers\GroupBookingHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WcCartManager {

	private CapacityValidationService $capacityService;
	private PricingEngineService $pricingEngine;

	public function __construct( CapacityValidationService $capacityService, PricingEngineService $pricingEngine ) {
		$this->capacityService = $capacityService;
		$this->pricingEngine   = $pricingEngine;
	}

	/**
	 * Validate cart add action for group booking capacity.
	 *
	 * @param bool $passed Standard validation result.
	 * @param int  $productId Product ID.
	 * @param int  $quantity Quantity.
	 * @return bool
	 */
	public function validateAddToCart( bool $passed, int $productId, int $quantity ): bool {
		if ( ! $passed || ! GroupBookingHelper::isGroupBookingEnabled( $productId ) ) {
			return $passed;
		}

		$requestedParticipants = isset( $_POST['slotnova_group_participants'] ) && is_array( $_POST['slotnova_group_participants'] ) ? $_POST['slotnova_group_participants'] : array();
		$participantCount      = count( $requestedParticipants );

		if ( $participantCount <= 0 && isset( $_POST['slotnova_group_quantity'] ) ) {
			$participantCount = absint( $_POST['slotnova_group_quantity'] );
		}

		if ( $participantCount <= 0 ) {
			$participantCount = 1;
		}

		$serviceId   = isset( $_POST['slotnova_service'] ) ? intval( $_POST['slotnova_service'] ) : 0;
		$bookingDate = isset( $_POST['slotnova_booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['slotnova_booking_date'] ) ) : '';
		$bookingTime = isset( $_POST['slotnova_booking_time'] ) ? sanitize_text_field( wp_unslash( $_POST['slotnova_booking_time'] ) ) : '';

		$validation = $this->capacityService->validateRequestedQuantity( $participantCount, $productId, $serviceId, $bookingDate, $bookingTime );

		if ( ! $validation['valid'] ) {
			wc_add_notice( $validation['message'], 'error' );
			return false;
		}

		return apply_filters( 'slotnova_group_before_validation', $passed, $productId, $participantCount, $serviceId, $bookingDate, $bookingTime );
	}

	/**
	 * Attach group booking participant data to WooCommerce cart item.
	 *
	 * @param array $cartItemData Existing cart item data.
	 * @param int   $productId Product ID.
	 * @param int   $variationId Variation ID.
	 * @return array
	 */
	public function addCartItemData( array $cartItemData, int $productId, int $variationId ): array {
		if ( ! GroupBookingHelper::isGroupBookingEnabled( $productId ) ) {
			return $cartItemData;
		}

		$rawParticipants = isset( $_POST['slotnova_group_participants'] ) && is_array( $_POST['slotnova_group_participants'] ) ? $_POST['slotnova_group_participants'] : array();
		$sanitized       = GroupBookingHelper::sanitizeParticipantsData( $rawParticipants );

		$qty = isset( $_POST['slotnova_group_quantity'] ) ? max( 1, absint( $_POST['slotnova_group_quantity'] ) ) : max( 1, count( $sanitized ) );

		// If form submitted simple names
		if ( empty( $sanitized ) && $qty > 0 ) {
			for ( $i = 1; $i <= $qty; $i++ ) {
				$sanitized[] = array(
					'name'  => sprintf( __( 'Participant %d', 'slotnova-booking' ), $i ),
					'email' => '',
					'phone' => '',
				);
			}
		}

		$cartItemData['slotnova_group_booking'] = array(
			'quantity'     => $qty,
			'participants' => $sanitized,
		);

		return $cartItemData;
	}

	/**
	 * Update price for cart item using PricingEngineService.
	 *
	 * @param \WC_Cart $cart Cart object.
	 * @return void
	 */
	public function updateCartItemPrice( \WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cartItem ) {
			if ( isset( $cartItem['slotnova_group_booking'] ) && isset( $cartItem['slotnova_booking'] ) ) {
				$groupData = $cartItem['slotnova_group_booking'];
				$count     = max( 1, (int) $groupData['quantity'] );
				$basePrice = isset( $cartItem['slotnova_booking']['price'] ) ? floatval( $cartItem['slotnova_booking']['price'] ) : floatval( $cartItem['data']->get_price() );

				$calculation = $this->pricingEngine->calculateGroupPrice( $basePrice, $count, $cartItem['product_id'] );
				$cartItem['data']->set_price( $calculation['unit_price'] );
			}
		}
	}

	/**
	 * Display participant info in cart & checkout item tables.
	 *
	 * @param array $itemData Item data array.
	 * @param array $cartItem Cart item.
	 * @return array
	 */
	public function displayCartItemMeta( array $itemData, array $cartItem ): array {
		if ( isset( $cartItem['slotnova_group_booking'] ) ) {
			$groupData    = $cartItem['slotnova_group_booking'];
			$participants = isset( $groupData['participants'] ) ? $groupData['participants'] : array();
			$count        = count( $participants );

			$itemData[] = array(
				'key'   => __( 'Participants', 'slotnova-booking' ),
				'value' => sprintf( _n( '%d Person', '%d People', $count, 'slotnova-booking' ), $count ),
			);

			if ( ! empty( $participants ) ) {
				$names = array_map( function( $p ) {
					return isset( $p['name'] ) ? esc_html( $p['name'] ) : '';
				}, $participants );

				$itemData[] = array(
					'key'   => __( 'Attendee Roster', 'slotnova-booking' ),
					'value' => implode( ', ', array_filter( $names ) ),
				);
			}
		}

		return $itemData;
	}
}
