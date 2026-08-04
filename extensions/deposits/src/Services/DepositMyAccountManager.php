<?php
/**
 * Deposit My Account Manager Service.
 *
 * Adds "Bookings" tab after Dashboard on WooCommerce My Account page,
 * renders user booking history list table, handles "Pay Due" button click,
 * routes remaining balance checkout, and auto updates status to "Processing".
 *
 * @package SlotNova\Extensions\Deposits\Services
 */

namespace SlotNova\Extensions\Deposits\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DepositMyAccountManager {



	/**
	 * Allow deposit orders with remaining due balance to be paid via WooCommerce order-pay endpoint.
	 */
	public function allowDepositOrderStatusForPayment( array $statuses, $order = null ): array {
		if ( $order && is_a( $order, 'WC_Order' ) ) {
			$due = (float) get_post_meta( $order->get_id(), '_slotnova_deposit_due', true );
			if ( $due > 0 ) {
				$statuses[] = $order->get_status();
			}
		}
		return array_unique( $statuses );
	}

	/**
	 * Filter order total to return due balance amount when customer is paying on WooCommerce order-pay endpoint.
	 */
	public function filterOrderTotalForDuePayment( $total, $order ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return $total;
		}

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return $total;
		}

		$due = (float) get_post_meta( $order->get_id(), '_slotnova_deposit_due', true );
		if ( $due > 0 ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			if ( ( function_exists( 'is_checkout' ) && is_checkout() && isset( $_GET['pay_for_order'] ) ) || isset( $_POST['woocommerce_pay'] ) ) {
				return $due;
			}
		}

		return $total;
	}

	/**
	 * On successful due payment completion on parent order, update deposit paid & clear deposit due meta.
	 * Only executes when the customer is explicitly paying an existing order's due balance (pay_for_order).
	 */
	public function handleOrderDuePaymentComplete( $order_id_or_obj ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		// Strictly check if this request is paying an existing order's due balance
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$is_due_payment = isset( $_GET['pay_for_order'] ) || isset( $_POST['woocommerce_pay'] );
		if ( ! $is_due_payment ) {
			return; // Do NOT clear due balance during initial deposit checkout!
		}

		$order_id = 0;
		if ( is_numeric( $order_id_or_obj ) && $order_id_or_obj > 0 ) {
			$order_id = (int) $order_id_or_obj;
		} elseif ( is_object( $order_id_or_obj ) && method_exists( $order_id_or_obj, 'get_id' ) ) {
			$order_id = $order_id_or_obj->get_id();
		}

		if ( ! $order_id ) {
			return;
		}

		$due = (float) get_post_meta( $order_id, '_slotnova_deposit_due', true );
		if ( $due > 0 ) {
			$paid     = (float) get_post_meta( $order_id, '_slotnova_deposit_paid', true );
			$new_paid = $paid + $due;

			update_post_meta( $order_id, '_slotnova_deposit_paid', $new_paid );
			update_post_meta( $order_id, '_slotnova_deposit_due', 0 );
			update_post_meta( $order_id, '_slotnova_due_paid', 'yes' );
			update_post_meta( $order_id, '_slotnova_due_paid_date', current_time( 'mysql' ) );

			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->set_total( $new_paid );
				/* translators: %s: remaining balance amount */
				$order->add_order_note( sprintf( __( 'Remaining balance of %s paid successfully directly on this order.', 'slotnova-booking' ), function_exists( 'wc_price' ) ? wc_price( $due ) : '$' . number_format( $due, 2 ) ) );

				if ( 'processing' !== $order->get_status() && 'completed' !== $order->get_status() ) {
					$order->set_status( 'processing', __( 'Full remaining balance paid.', 'slotnova-booking' ) );
				}
				$order->save();
			}
		}
	}

	/**
	 * Add "Pay due" action button under Actions column in WooCommerce My Account > Orders tab.
	 *
	 * @param array     $actions Order actions array.
	 * @param \WC_Order $order   Order object.
	 * @return array
	 */
	public function filterMyOrdersActions( array $actions, $order ): array {
		if ( ! $order instanceof \WC_Order ) {
			return $actions;
		}

		$order_id = $order->get_id();
		$due      = (float) get_post_meta( $order_id, '_slotnova_deposit_due', true );

		if ( $due > 0 ) {
			unset( $actions['pay'] );
			$actions['pay_due'] = array(
				'url'  => $order->get_checkout_payment_url(),
				'name' => __( 'Pay due', 'slotnova-booking' ),
			);
		}

		return $actions;
	}



	/**
	 * Render custom Total column content in WooCommerce My Account > Orders tab.
	 * Displays full service price along with Partial Payment, Due Payment, or Full Paid breakdown.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function renderOrderTotalColumn( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$order_id     = $order->get_id();
		$is_deposit   = get_post_meta( $order_id, '_slotnova_is_deposit', true );
		$deposit_paid = (float) get_post_meta( $order_id, '_slotnova_deposit_paid', true );
		$deposit_due  = (float) get_post_meta( $order_id, '_slotnova_deposit_due', true );
		$due_paid     = get_post_meta( $order_id, '_slotnova_due_paid', true );

		$item_count = $order->get_item_count();

		// Calculate full service original price ($500.00)
		$full_price = (float) $order->get_total();
		if ( 'yes' === $is_deposit || $deposit_paid > 0 || $deposit_due > 0 ) {
			if ( $deposit_paid > 0 && $deposit_due > 0 ) {
				$full_price = $deposit_paid + $deposit_due;
			} elseif ( $deposit_paid > 0 ) {
				$full_price = $deposit_paid;
			} elseif ( (float) $order->get_subtotal() > $full_price ) {
				$full_price = (float) $order->get_subtotal();
			}
		}

		$formatted_full_price = function_exists( 'wc_price' ) ? wc_price( $full_price, array( 'currency' => $order->get_currency() ) ) : '$' . number_format( $full_price, 2 );

		/* translators: 1: formatted order total 2: total order items */
		$total_text = sprintf( _n( '%1$s for %2$s item', '%1$s for %2$s items', $item_count, 'slotnova-booking' ), $formatted_full_price, $item_count );

		echo '<div style="font-size: 14px; color: #0f172a;">' . wp_kses_post( $total_text ) . '</div>';

		if ( 'yes' === $is_deposit || $deposit_paid > 0 || $deposit_due > 0 ) :
			?>
			<div class="slotnova-order-payment-info" style="margin-top: 6px; font-size: 12px; line-height: 1.45;">
				<?php if ( 'yes' === $due_paid || $deposit_due <= 0 ) : ?>
					<div style="color: #166534; font-weight: 700;">
						<span><?php esc_html_e( 'Full Paid:', 'slotnova-booking' ); ?></span>
						<?php echo wp_kses_post( wc_price( $full_price, array( 'currency' => $order->get_currency() ) ) ); ?>
					</div>
				<?php else : ?>
					<?php if ( $deposit_paid > 0 ) : ?>
						<div style="color: #166534; font-weight: 600;">
							<span><?php esc_html_e( 'Partial:', 'slotnova-booking' ); ?></span>
							<?php echo wp_kses_post( wc_price( $deposit_paid, array( 'currency' => $order->get_currency() ) ) ); ?>
						</div>
					<?php endif; ?>
					<div style="color: #dc2626; font-weight: 700; margin-top: 2px;">
						<span><?php esc_html_e( 'Due:', 'slotnova-booking' ); ?></span>
						<?php echo wp_kses_post( wc_price( $deposit_due, array( 'currency' => $order->get_currency() ) ) ); ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
		endif;
	}
}
