<?php
/**
 * WooCommerce Order Manager.
 *
 * Handles order item metadata storage and database synchronization.
 *
 * @package SlotNova\Extensions\GroupBooking\WooCommerce
 */

namespace SlotNova\Extensions\GroupBooking\WooCommerce;

use SlotNova\Extensions\GroupBooking\Repositories\ParticipantRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WcOrderManager {

	private ParticipantRepository $participantRepo;

	public function __construct( ParticipantRepository $participantRepo ) {
		$this->participantRepo = $participantRepo;
	}

	/**
	 * Save group booking data to line item meta.
	 *
	 * @param \WC_Order_Item_Product $item Line item.
	 * @param string                 $cartItemKey Cart item key.
	 * @param array                  $values Cart item values.
	 * @param \WC_Order              $order Order object.
	 * @return void
	 */
	public function saveOrderLineItemMeta( $item, string $cartItemKey, array $values, \WC_Order $order ): void {
		if ( isset( $values['slotnova_group_booking'] ) ) {
			$groupData    = $values['slotnova_group_booking'];
			$participants = isset( $groupData['participants'] ) ? $groupData['participants'] : array();

			$item->add_meta_data( '_slotnova_group_quantity', count( $participants ) );
			$item->add_meta_data( '_slotnova_group_participants', $participants );

			$item->add_meta_data(
				__( 'Participant Count', 'slotnova-booking' ),
				count( $participants )
			);
		}
	}

	/**
	 * Synchronize order item participants into custom DB table upon order creation/processing.
	 *
	 * @param int $orderId Order ID.
	 * @return void
	 */
	public function syncOrderParticipantsToDb( int $orderId ): void {
		$order = wc_get_order( $orderId );
		if ( ! $order ) {
			return;
		}

		$customerId = $order->get_customer_id();

		foreach ( $order->get_items() as $itemId => $item ) {
			$participants = $item->get_meta( '_slotnova_group_participants' );
			$serviceId    = (int) $item->get_meta( '_slotnova_service_id' );
			$bookingDate  = (string) $item->get_meta( __( 'Date', 'slotnova-booking' ) );
			$bookingTime  = (string) $item->get_meta( __( 'Time', 'slotnova-booking' ) );
			$productId    = $item->get_product_id();

			if ( ! empty( $participants ) && is_array( $participants ) && $productId > 0 ) {
				foreach ( $participants as $p ) {
					$name  = isset( $p['name'] ) ? sanitize_text_field( $p['name'] ) : '';
					$email = isset( $p['email'] ) ? sanitize_email( $p['email'] ) : '';
					$phone = isset( $p['phone'] ) ? sanitize_text_field( $p['phone'] ) : '';
					$gender = isset( $p['gender'] ) ? sanitize_text_field( $p['gender'] ) : '';
					$age   = isset( $p['age'] ) && '' !== $p['age'] ? absint( $p['age'] ) : null;
					$notes = isset( $p['notes'] ) ? sanitize_textarea_field( $p['notes'] ) : '';

					if ( ! empty( $name ) ) {
						$this->participantRepo->insert( array(
							'order_id'          => $orderId,
							'order_item_id'     => $itemId,
							'product_id'        => $productId,
							'service_id'        => $serviceId,
							'booking_date'      => $bookingDate,
							'booking_time'      => $bookingTime,
							'customer_user_id' => $customerId,
							'participant_name'  => $name,
							'participant_email' => $email,
							'participant_phone' => $phone,
							'participant_gender'=> $gender,
							'participant_age'   => $age,
							'participant_notes' => $notes,
						) );
					}
				}
			}
		}

		do_action( 'slotnova_group_booking_created', $orderId );
	}

	/**
	 * Handle order status changes (e.g. cancelled/refunded).
	 *
	 * @param int      $orderId Order ID.
	 * @param string   $oldStatus Old status.
	 * @param string   $newStatus New status.
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	public function handleOrderStatusChange( int $orderId, string $oldStatus, string $newStatus, \WC_Order $order ): void {
		$cancelledStatuses = array( 'cancelled', 'refunded', 'failed' );

		if ( in_array( $newStatus, $cancelledStatuses, true ) ) {
			$this->participantRepo->deleteByOrderId( $orderId );
		}
	}
}
