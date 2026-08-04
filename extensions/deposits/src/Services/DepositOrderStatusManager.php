<?php
/**
 * Deposit Order Status Manager Service.
 *
 * Registers "Partial Deposit" custom order status in WooCommerce,
 * automatically updates order statuses for deposit bookings,
 * augments SlotNova Bookings Management list data and view modal details.
 *
 * @package SlotNova\Extensions\Deposits\Services
 */

namespace SlotNova\Extensions\Deposits\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DepositOrderStatusManager {

	/**
	 * Register "Partial Deposit" custom order status in WooCommerce.
	 */
	public function registerOrderStatus( array $order_statuses ): array {
		$order_statuses['wc-partial-deposit'] = array(
			'label'                     => _x( 'Partial Deposit', 'Order status', 'slotnova-booking' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop( 'Partial Deposit <span class="count">(%s)</span>', 'Partial Deposit <span class="count">(%s)</span>', 'slotnova-booking' ),
		);
		return $order_statuses;
	}

	/**
	 * Add "Partial Deposit" to WooCommerce order status list.
	 */
	public function addOrderStatusToList( array $order_statuses ): array {
		$order_statuses['wc-partial-deposit'] = __( 'Partial Deposit', 'slotnova-booking' );
		return $order_statuses;
	}

	/**
	 * Add Partial Deposit statuses to SlotNova bookings query statuses list.
	 */
	public function filterBookingsQueryStatuses( array $statuses ): array {
		if ( ! in_array( 'wc-partial-deposit', $statuses, true ) ) {
			$statuses[] = 'wc-partial-deposit';
		}
		if ( ! in_array( 'partial-deposit', $statuses, true ) ) {
			$statuses[] = 'partial-deposit';
		}
		return $statuses;
	}

	/**
	 * Force WooCommerce payment complete order status to "partial-deposit" for partial deposit orders.
	 */
	public function filterPaymentCompleteOrderStatus( string $status, $order_id_or_obj = null, $order_obj = null ): string {
		$order_id = 0;
		if ( is_numeric( $order_id_or_obj ) && $order_id_or_obj > 0 ) {
			$order_id = (int) $order_id_or_obj;
		} elseif ( is_object( $order_id_or_obj ) && method_exists( $order_id_or_obj, 'get_id' ) ) {
			$order_id = $order_id_or_obj->get_id();
		} elseif ( is_object( $order_obj ) && method_exists( $order_obj, 'get_id' ) ) {
			$order_id = $order_obj->get_id();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( isset( $_GET['pay_for_order'] ) || isset( $_POST['woocommerce_pay'] ) || 'yes' === get_post_meta( $order_id, '_slotnova_due_paid', true ) ) {
			return $status;
		}

		$is_deposit = get_post_meta( $order_id, '_slotnova_is_deposit', true );
		$due        = (float) get_post_meta( $order_id, '_slotnova_deposit_due', true );

		if ( 'yes' === $is_deposit && $due > 0 ) {
			return 'partial-deposit';
		}

		return $status;
	}

	/**
	 * Auto set order status to "Partial Deposit" after checkout order placement or admin load.
	 */
	public function setOrderDepositStatus( $order_id_or_obj = null ): void {
		$order_id = 0;
		if ( is_numeric( $order_id_or_obj ) && $order_id_or_obj > 0 ) {
			$order_id = (int) $order_id_or_obj;
		} elseif ( is_object( $order_id_or_obj ) && method_exists( $order_id_or_obj, 'get_id' ) ) {
			$order_id = $order_id_or_obj->get_id();
		}

		if ( ! $order_id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( isset( $_GET['pay_for_order'] ) || isset( $_POST['woocommerce_pay'] ) || 'yes' === get_post_meta( $order_id, '_slotnova_due_paid', true ) ) {
			return;
		}

		$is_deposit = get_post_meta( $order_id, '_slotnova_is_deposit', true );
		$due        = (float) get_post_meta( $order_id, '_slotnova_deposit_due', true );

		if ( 'yes' === $is_deposit && $due > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order && ! in_array( $order->get_status(), array( 'partial-deposit', 'wc-partial-deposit' ), true ) ) {
				$order->update_status( 'partial-deposit', __( 'Booking placed with Partial Deposit.', 'slotnova-booking' ) );
			}
		}
	}

	/**
	 * Augment SlotNova Bookings Management list data with deposit details.
	 */
	public function filterBookingListData( array $result ): array {
		if ( empty( $result['list'] ) || ! is_array( $result['list'] ) ) {
			return $result;
		}

		foreach ( $result['list'] as $key => $booking ) {
			$order_id   = $booking['order_id'] ?? 0;
			$is_deposit = get_post_meta( $order_id, '_slotnova_is_deposit', true );
			$due        = (float) get_post_meta( $order_id, '_slotnova_deposit_due', true );

			if ( 'yes' === $is_deposit && $due > 0 ) {
				$paid = (float) get_post_meta( $order_id, '_slotnova_deposit_paid', true );

				$result['list'][ $key ]['is_deposit']             = true;
				$result['list'][ $key ]['deposit_paid']           = $paid;
				$result['list'][ $key ]['deposit_due']            = $due;
				$result['list'][ $key ]['deposit_paid_formatted'] = function_exists( 'wc_price' ) ? wc_price( $paid ) : '$' . number_format( $paid, 2 );
				$result['list'][ $key ]['deposit_due_formatted']  = function_exists( 'wc_price' ) ? wc_price( $due ) : '$' . number_format( $due, 2 );

				$result['list'][ $key ]['status']     = __( 'Partial Deposit', 'slotnova-booking' );
				$result['list'][ $key ]['status_raw'] = 'partial-deposit';
			}
		}

		return $result;
	}

	/**
	 * Print CSS styles & Modal JS extension for Partial Deposit in WP Admin.
	 */
	public function printAdminHeadStyles(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id    = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$is_deposit = false;

		if ( $post_id > 0 && function_exists( 'wc_get_order' ) ) {
			$is_deposit = ( 'yes' === get_post_meta( $post_id, '_slotnova_is_deposit', true ) );
			$due        = (float) get_post_meta( $post_id, '_slotnova_deposit_due', true );
			if ( $is_deposit && $due > 0 ) {
				$order = wc_get_order( $post_id );
				if ( $order && ! in_array( $order->get_status(), array( 'partial-deposit', 'wc-partial-deposit' ), true ) ) {
					$order->update_status( 'partial-deposit', __( 'Updated status to Partial Deposit.', 'slotnova-booking' ) );
				}
			}
		}

		$paid_html = ( $is_deposit && function_exists( 'wc_price' ) ) ? wc_price( get_post_meta( $post_id, '_slotnova_deposit_paid', true ) ) : '';
		$due_html  = ( $is_deposit && function_exists( 'wc_price' ) ) ? wc_price( get_post_meta( $post_id, '_slotnova_deposit_due', true ) ) : '';
		if ( $is_deposit ) : ?>
			<script>
			jQuery(document).ready(function($) {
				var depositPaidHtml = <?php echo wp_json_encode( $paid_html ); ?>;
				var depositDueHtml  = <?php echo wp_json_encode( $due_html ); ?>;

				setTimeout(function() {
					$('.wc-order-totals-items, table.wc-order-totals').find('tr').each(function() {
						var $lbl = $(this).find('td.label');
						var txt = $lbl.text().trim();

						if (txt.indexOf('Order Total') !== -1 || txt === 'Total:') {
							$lbl.text('Partial Payment:');
							if (depositPaidHtml) $(this).find('td.total').html('<b>' + depositPaidHtml + '</b>');
						}
						if (txt.indexOf('Remaining Balance') !== -1 || txt.indexOf('Fees') !== -1) {
							$lbl.text('Remaining Balance:');
							if (depositDueHtml) $(this).find('td.total').html('<b>' + depositDueHtml + '</b>');
						}
					});
				}, 100);
			});
			</script>
		<?php endif;
	}
}
