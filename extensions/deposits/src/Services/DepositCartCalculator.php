<?php
/**
 * Deposit Cart & Checkout Calculator Service.
 *
 * Calculates upfront deposit & balance due for WooCommerce bookings,
 * adjusts cart totals, saves order meta, and renders email/thank you notices.
 *
 * @package SlotNova\Extensions\Deposits\Services
 */

namespace SlotNova\Extensions\Deposits\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DepositCartCalculator {

	/**
	 * Update cart item payment_type when user switches option on Checkout page via AJAX.
	 */
	public function updatePaymentTypeFromCheckout( $post_data_str ): void {
		if ( empty( $post_data_str ) || ! isset( WC()->cart ) ) {
			return;
		}

		parse_str( $post_data_str, $post_data );
		if ( isset( $post_data['slotnova_payment_type'] ) ) {
			$type = sanitize_text_field( $post_data['slotnova_payment_type'] );
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				WC()->cart->cart_contents[ $cart_item_key ]['slotnova_payment_type'] = $type;
			}
			WC()->cart->set_session();
		}
	}

	public function ajaxUpdateCartPaymentType(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['payment_type'] ) && isset( WC()->cart ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$type = sanitize_text_field( wp_unslash( $_POST['payment_type'] ) );
			if ( in_array( $type, array( 'full', 'deposit' ), true ) ) {
				foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
					WC()->cart->cart_contents[ $cart_item_key ]['slotnova_payment_type'] = $type;
				}
				WC()->cart->set_session();
				WC()->cart->calculate_totals();
			}
		}
		wp_send_json_success();
	}

	/**
	 * Get deposit configuration for a product (checks product-level override, falls back to global).
	 *
	 * - Global ENABLED ('yes'): Deposits apply globally to all booking products, unless a product sets override = 'no'.
	 * - Global DISABLED ('no'): Deposits do NOT apply globally; only products with override = 'yes' activate deposits.
	 */
	public static function getProductDepositConfig( $product_id = 0 ): array {
		$global_enabled = function_exists( 'get_option' ) ? get_option( 'slotnova_deposit_enabled', 'no' ) : 'no';
		$global_type    = function_exists( 'get_option' ) ? get_option( 'slotnova_deposit_type', 'percentage' ) : 'percentage';
		$global_amount  = function_exists( 'get_option' ) ? (float) get_option( 'slotnova_deposit_amount', 20 ) : 20;

		if ( ! $product_id ) {
			return array(
				'enabled' => $global_enabled,
				'type'    => $global_type,
				'amount'  => $global_amount,
			);
		}

		$override = get_post_meta( $product_id, '_slotnova_deposit_enable_override', true );
		$type     = get_post_meta( $product_id, '_slotnova_deposit_type_override', true );
		$amount   = get_post_meta( $product_id, '_slotnova_deposit_amount_override', true );

		$calc_type   = ! empty( $type ) ? $type : $global_type;
		$calc_amount = ( '' !== $amount && false !== $amount ) ? (float) $amount : $global_amount;

		if ( 'yes' === $override ) {
			return array(
				'enabled' => 'yes',
				'type'    => $calc_type,
				'amount'  => $calc_amount,
			);
		} elseif ( 'no' === $override ) {
			return array(
				'enabled' => 'no',
				'type'    => $calc_type,
				'amount'  => $calc_amount,
			);
		}

		// 'global' or default fallback:
		return array(
			'enabled' => $global_enabled,
			'type'    => $calc_type,
			'amount'  => $calc_amount,
		);
	}

	public function calculateDeposit( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( isset( WC()->session ) && WC()->session->get( 'slotnova_is_pay_due_checkout' ) ) {
			return;
		}

		// Check if payment_type was posted from checkout form submission or AJAX update
		$posted_type = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['slotnova_payment_type'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$posted_type = sanitize_text_field( wp_unslash( $_POST['slotnova_payment_type'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} elseif ( isset( $_POST['post_data'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw_post_data = sanitize_text_field( wp_unslash( $_POST['post_data'] ) );
			parse_str( $raw_post_data, $post_data );
			if ( isset( $post_data['slotnova_payment_type'] ) ) {
				$posted_type = sanitize_text_field( $post_data['slotnova_payment_type'] );
			}
		}

		if ( null !== $posted_type && in_array( $posted_type, array( 'full', 'deposit' ), true ) ) {
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				$cart->cart_contents[ $cart_item_key ]['slotnova_payment_type'] = $posted_type;
			}
		}

		// Check if any cart item selected 'deposit' payment option
		$has_deposit_item = false;
		foreach ( $cart->get_cart() as $cart_item ) {
			$payment_type = $cart_item['slotnova_payment_type'] ?? 'full';
			if ( 'deposit' === $payment_type ) {
				$has_deposit_item = true;
				break;
			}
		}

		if ( ! $has_deposit_item ) {
			if ( isset( WC()->session ) ) {
				WC()->session->set( 'slotnova_deposit_due', 0 );
				WC()->session->set( 'slotnova_remaining_balance', 0 );
				WC()->session->set( 'slotnova_full_subtotal', 0 );
			}
			return; // Customer selected Full Payment
		}

		$total_deposit   = 0;
		$total_subtotal  = 0;

		foreach ( $cart->get_cart() as $cart_item ) {
			$line_subtotal = (float) $cart_item['line_subtotal'];
			$product_id    = $cart_item['product_id'] ?? 0;
			$config        = self::getProductDepositConfig( $product_id );

			if ( 'yes' !== $config['enabled'] ) {
				$total_deposit  += $line_subtotal;
				$total_subtotal += $line_subtotal;
				continue;
			}

			$item_deposit = 0;
			if ( 'percentage' === $config['type'] ) {
				$item_deposit = ( $line_subtotal * $config['amount'] ) / 100;
			} else {
				$item_deposit = min( $config['amount'] * ( $cart_item['quantity'] ?? 1 ), $line_subtotal );
			}

			$total_deposit  += $item_deposit;
			$total_subtotal += $line_subtotal;
		}

		if ( $total_subtotal <= 0 ) {
			return;
		}

		$depositDue       = $total_deposit;
		$remainingBalance = max( 0, $total_subtotal - $depositDue );

		// Store deposit details in WooCommerce session
		if ( isset( WC()->session ) ) {
			WC()->session->set( 'slotnova_deposit_due', $depositDue );
			WC()->session->set( 'slotnova_remaining_balance', $remainingBalance );
			WC()->session->set( 'slotnova_full_subtotal', $total_subtotal );
		}

		// Apply negative fee to reduce checkout total to deposit amount
		if ( $remainingBalance > 0 ) {
			$cart->add_fee( __( 'Remaining Balance (Pay at Appointment)', 'slotnova-booking' ), -$remainingBalance, false );
		}
	}

	/**
	 * Render payment plan selector (Full Payment vs Partial Deposit) inside WooCommerce Order Review Table.
	 * Positioned after total price using theme default table row styling (tr/th/td).
	 */
	public function renderCheckoutPaymentPlanSelector(): void {
		if ( ! isset( WC()->cart ) ) {
			return;
		}

		if ( isset( WC()->session ) && WC()->session->get( 'slotnova_is_pay_due_checkout' ) ) {
			return; // Do not show switcher when paying an existing order balance
		}

		$has_enabled_deposit = false;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = $cart_item['product_id'] ?? 0;
			$config     = self::getProductDepositConfig( $product_id );
			if ( 'yes' === $config['enabled'] ) {
				$has_enabled_deposit = true;
				break;
			}
		}

		if ( ! $has_enabled_deposit ) {
			return;
		}

		$current_payment_type = 'full';
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['slotnova_payment_type'] ) ) {
				$current_payment_type = $cart_item['slotnova_payment_type'];
				break;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['slotnova_payment_type'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$current_payment_type = sanitize_text_field( wp_unslash( $_POST['slotnova_payment_type'] ) );
		}

		$subtotal = (float) WC()->cart->get_subtotal();
		if ( $subtotal <= 0 ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$subtotal += (float) ( $cart_item['line_subtotal'] ?? 0 );
			}
		}

		// Calculate deposit using per-product config (same logic as calculateDeposit())
		$total_deposit = 0;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$line_subtotal = (float) ( $cart_item['line_subtotal'] ?? 0 );
			$product_id    = $cart_item['product_id'] ?? 0;
			$config        = self::getProductDepositConfig( $product_id );

			if ( 'yes' !== $config['enabled'] ) {
				$total_deposit += $line_subtotal;
				continue;
			}

			if ( 'percentage' === $config['type'] ) {
				$total_deposit += ( $line_subtotal * $config['amount'] ) / 100;
			} else {
				$total_deposit += min( $config['amount'] * ( $cart_item['quantity'] ?? 1 ), $line_subtotal );
			}
		}

		$deposit_amount = $total_deposit;

		$primary_color = function_exists( 'get_option' ) ? get_option( 'slotnova_primary_color', '#2271b1' ) : '#2271b1';
		$currency      = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
		?>
		<tr class="slotnova-checkout-plan-table-row">
			<th style="vertical-align: middle; padding-top: 12px; padding-bottom: 12px; font-size: 14px; white-space: nowrap;">
				<?php esc_html_e( 'Payment Option', 'slotnova-booking' ); ?>
			</th>
			<td style="vertical-align: middle; padding-top: 12px; padding-bottom: 12px; text-align: left;">
				<div style="display: flex; flex-direction: column; gap: 8px; width: 100%; text-align: left;">
					<!-- Option 1: Full Payment -->
					<label id="slotnova-co-label-full" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border-radius: 8px; border: 1.5px solid <?php echo 'full' === $current_payment_type ? esc_attr( $primary_color ) : '#e2e8f0'; ?>; background: <?php echo 'full' === $current_payment_type ? '#f0f9ff' : '#ffffff'; ?>; cursor: pointer; margin: 0; transition: all 0.2s ease;">
						<span style="display: flex; align-items: center; gap: 8px; white-space: nowrap;">
							<input type="radio" name="slotnova_payment_type" value="full" <?php checked( $current_payment_type, 'full' ); ?> class="slotnova-checkout-pay-radio" style="margin: 0; width: 16px; height: 16px; accent-color: <?php echo esc_attr( $primary_color ); ?>;" />
							<span style="font-size: 13px; font-weight: 600; color: #0f172a; white-space: nowrap;"><?php esc_html_e( 'Full Payment', 'slotnova-booking' ); ?></span>
						</span>
						<span id="slotnova-co-full-price" style="font-size: 13px; font-weight: 700; color: #0f172a; white-space: nowrap; margin-left: 12px;"><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $subtotal ) ) : esc_html( $currency . number_format( $subtotal, 2 ) ); ?></span>
					</label>

					<!-- Option 2: Partial Deposit -->
					<label id="slotnova-co-label-deposit" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border-radius: 8px; border: 1.5px solid <?php echo 'deposit' === $current_payment_type ? esc_attr( $primary_color ) : '#e2e8f0'; ?>; background: <?php echo 'deposit' === $current_payment_type ? '#f0f9ff' : '#ffffff'; ?>; cursor: pointer; margin: 0; transition: all 0.2s ease;">
						<span style="display: flex; align-items: center; gap: 8px; white-space: nowrap;">
							<input type="radio" name="slotnova_payment_type" value="deposit" <?php checked( $current_payment_type, 'deposit' ); ?> class="slotnova-checkout-pay-radio" style="margin: 0; width: 16px; height: 16px; accent-color: <?php echo esc_attr( $primary_color ); ?>;" />
							<span style="font-size: 13px; font-weight: 600; color: #0f172a; white-space: nowrap;"><?php esc_html_e( 'Pay Deposit', 'slotnova-booking' ); ?></span>
						</span>
						<span id="slotnova-co-deposit-price" style="font-size: 13px; font-weight: 700; color: <?php echo esc_attr( $primary_color ); ?>; white-space: nowrap; margin-left: 12px;"><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $deposit_amount ) ) : esc_html( $currency . number_format( $deposit_amount, 2 ) ); ?></span>
					</label>
				</div>
			</td>
		</tr>

		<!-- Due at Appointment row — hidden until Pay Deposit is selected -->
		<tr id="slotnova-co-due-row" style="<?php echo 'deposit' === $current_payment_type ? '' : 'display:none;'; ?>">
			<th><?php esc_html_e( 'Due at Appointment', 'slotnova-booking' ); ?></th>
			<td><span class="woocommerce-Price-amount amount"><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( max( 0, $subtotal - $deposit_amount ) ) ) : esc_html( $currency . number_format( max( 0, $subtotal - $deposit_amount ), 2 ) ); ?></span></td>
		</tr>

		<style>
		tr.fee {
			display: none !important;
		}
		</style>
		<script>
		if (typeof jQuery !== 'undefined') {
			var slotnovaFullPrice    = <?php echo (float) $subtotal; ?>;
			var slotnovaDepositPrice = <?php echo (float) $deposit_amount; ?>;
			var slotnovaPrimaryColor = <?php echo wp_json_encode( $primary_color ); ?>;

			function slotnovaUpdateCheckoutLabels() {
				var selectedVal = jQuery('input[name="slotnova_payment_type"]:checked').val();
				var labelFull    = document.getElementById('slotnova-co-label-full');
				var labelDeposit = document.getElementById('slotnova-co-label-deposit');

				if (!labelFull || !labelDeposit) return;

				if (selectedVal === 'deposit') {
					labelFull.style.borderColor    = '#e2e8f0';
					labelFull.style.background     = '#ffffff';
					labelDeposit.style.borderColor = slotnovaPrimaryColor;
					labelDeposit.style.background  = '#f0f9ff';
				} else {
					labelFull.style.borderColor    = slotnovaPrimaryColor;
					labelFull.style.background     = '#f0f9ff';
					labelDeposit.style.borderColor = '#e2e8f0';
					labelDeposit.style.background  = '#ffffff';
				}
				// Also show/hide the Due at Appointment row
				var dueRow = document.getElementById('slotnova-co-due-row');
				if (dueRow) {
					dueRow.style.display = (selectedVal === 'deposit') ? '' : 'none';
				}
			}

			function hideSlotNovaFeeRows() {
				jQuery('tr.fee').each(function() {
					var text = jQuery(this).text();
					if (text.indexOf('Remaining Balance') !== -1 || text.indexOf('Pay at Appointment') !== -1) {
						jQuery(this).attr('style', 'display: none !important;');
					}
				});
			}

			// On checkout updated (AJAX refresh), re-sync label styles
			jQuery(document).on('updated_checkout updated_cart_totals updated_wc_div', function() {
				hideSlotNovaFeeRows();
				slotnovaUpdateCheckoutLabels();
			});

			jQuery(document).on('change', '.slotnova-checkout-pay-radio', function() {
				slotnovaUpdateCheckoutLabels();
				var newType = jQuery(this).val();
				var ajaxUrl = (typeof slotnova_params !== 'undefined' && slotnova_params.ajax_url) ? slotnova_params.ajax_url : '/wp-admin/admin-ajax.php';
				jQuery.post(ajaxUrl, {
					action: 'slotnova_update_cart_payment_type',
					payment_type: newType
				}, function() {
					jQuery(document.body).trigger('update_checkout');
					jQuery(document.body).trigger('wc_update_cart');
				});
			});

			// Init on load
			hideSlotNovaFeeRows();
			slotnovaUpdateCheckoutLabels();
		}
		</script>
		<?php
	}

	/**
	 * Filter WooCommerce Checkout Place Order button text to show payable amount.
	 * e.g. "Place order ($80.00)" when Pay Deposit is selected.
	 */
	public function filterOrderButtonText( $button_text ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return $button_text;
		}

		if ( ! isset( WC()->cart ) ) {
			return $button_text;
		}

		$payable_amount = (float) WC()->cart->total;

		if ( function_exists( 'wc_price' ) && $payable_amount > 0 ) {
			$formatted_price = html_entity_decode( wp_strip_all_tags( wc_price( $payable_amount ) ) );
			/* translators: %s: Payable amount */
			return sprintf( __( 'Place order (%s)', 'slotnova-booking' ), $formatted_price );
		}

		return $button_text;
	}

	public function filterCheckoutOrderTotalHtml( $value, $cart = null ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return $value;
		}

		if ( isset( WC()->session ) && (float) WC()->session->get( 'slotnova_full_subtotal', 0 ) > 0 ) {
			$full = (float) WC()->session->get( 'slotnova_full_subtotal' );
			if ( $full > 0 && function_exists( 'wc_price' ) ) {
				return '<strong>' . wp_kses_post( wc_price( $full ) ) . '</strong>';
			}
		}

		return $value;
	}

	public function displayDepositBreakdownOnCheckout() {
		$enabled = function_exists( 'get_option' ) ? get_option( 'slotnova_deposit_enabled', 'no' ) : 'no';
		if ( 'yes' !== $enabled || ! isset( WC()->session ) ) {
			return;
		}

		$depositDue    = WC()->session->get( 'slotnova_deposit_due', 0 );
		$remaining     = WC()->session->get( 'slotnova_remaining_balance', 0 );
		$primary_color = function_exists( 'get_option' ) ? get_option( 'slotnova_primary_color', '#2271b1' ) : '#2271b1';

		if ( $depositDue > 0 && function_exists( 'wc_price' ) ) {
			echo '<tr class="slotnova-checkout-deposit-row" style="font-weight: 700;">';
			echo '<th>' . esc_html__( 'Pay deposit', 'slotnova-booking' ) . '</th>';
			echo '<td><strong style="color: ' . esc_attr( $primary_color ) . ';">' . wp_kses_post( wc_price( $depositDue ) ) . '</strong></td>';
			echo '</tr>';

			echo '<tr class="slotnova-checkout-due-row">';
			echo '<th style="font-weight: normal; color: inherit;">' . esc_html__( 'Remaining Balance (Due at Appointment)', 'slotnova-booking' ) . '</th>';
			echo '<td style="font-weight: normal; color: inherit;">' . wp_kses_post( wc_price( $remaining ) ) . '</td>';
			echo '</tr>';
		}
	}

	public function saveOrderDepositMeta( $order_id ) {
		if ( ! isset( WC()->session ) ) {
			return;
		}

		$depositDue = WC()->session->get( 'slotnova_deposit_due', 0 );
		$remaining  = WC()->session->get( 'slotnova_remaining_balance', 0 );

		if ( $depositDue > 0 ) {
			update_post_meta( $order_id, '_slotnova_deposit_paid', $depositDue );
			update_post_meta( $order_id, '_slotnova_deposit_due', $remaining );
			update_post_meta( $order_id, '_slotnova_initial_deposit', $depositDue );
			update_post_meta( $order_id, '_slotnova_initial_remaining', $remaining );
			update_post_meta( $order_id, '_slotnova_is_deposit', 'yes' );
		}
	}

	public function addOrderDetailsDepositInfo( $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$is_deposit   = get_post_meta( $order->get_id(), '_slotnova_is_deposit', true );
		$deposit_paid = (float) get_post_meta( $order->get_id(), '_slotnova_deposit_paid', true );
		$deposit_due  = (float) get_post_meta( $order->get_id(), '_slotnova_deposit_due', true );
		$due_paid     = get_post_meta( $order->get_id(), '_slotnova_due_paid', true );

		if ( 'yes' === $is_deposit && function_exists( 'wc_price' ) ) {
			echo '<div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; margin-top: 20px;">';
			echo '<h3 style="margin: 0 0 10px 0; font-size: 15px; color: #0f172a;">' . esc_html__( 'Deposit & Balance Breakdown', 'slotnova-booking' ) . '</h3>';

			if ( 'yes' === $due_paid || $deposit_due <= 0 ) {
				$full_total = $deposit_paid > 0 ? $deposit_paid : $order->get_total();
				echo '<p style="margin: 4px 0; font-size: 14px; color: #166534; font-weight: 700;"><strong>' . esc_html__( 'Full Paid:', 'slotnova-booking' ) . '</strong> ' . wp_kses_post( wc_price( $full_total ) ) . '</p>';
			} else {
				echo '<p style="margin: 4px 0; font-size: 13px;"><strong>' . esc_html__( 'Upfront Deposit Paid:', 'slotnova-booking' ) . '</strong> ' . wp_kses_post( wc_price( $deposit_paid ) ) . '</p>';
				echo '<p style="margin: 4px 0; font-size: 13px; color: #dc2626;"><strong>' . esc_html__( 'Remaining Balance Due at Appointment:', 'slotnova-booking' ) . '</strong> ' . wp_kses_post( wc_price( $deposit_due ) ) . '</p>';
			}

			echo '</div>';
		}
	}

	/**
	 * Format Admin Order Details totals block to show Total Amount, Partial, and Remaining Balance.
	 */
	public function renderAdminOrderTotalsScript(): void {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : ( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 );
		if ( ! $post_id || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return;
		}

		$is_deposit   = get_post_meta( $post_id, '_slotnova_is_deposit', true );
		$deposit_paid = (float) get_post_meta( $post_id, '_slotnova_deposit_paid', true );
		$deposit_due  = (float) get_post_meta( $post_id, '_slotnova_deposit_due', true );
		$due_paid     = get_post_meta( $post_id, '_slotnova_due_paid', true );

		if ( 'yes' !== $is_deposit && $deposit_paid <= 0 && $deposit_due <= 0 ) {
			return;
		}

		$full_price = $deposit_paid + $deposit_due;
		if ( $full_price <= 0 ) {
			$full_price = (float) $order->get_subtotal();
		}
		if ( $full_price <= 0 ) {
			$full_price = (float) $order->get_total();
		}

		$is_fully_paid   = ( 'yes' === $due_paid || $deposit_due <= 0 );
		$initial_deposit = (float) get_post_meta( $post_id, '_slotnova_initial_deposit', true );
		$initial_due     = (float) get_post_meta( $post_id, '_slotnova_initial_remaining', true );

		if ( $initial_deposit <= 0 ) {
			if ( ! $is_fully_paid && $deposit_paid > 0 && $deposit_due > 0 ) {
				$initial_deposit = $deposit_paid;
				$initial_due     = $deposit_due;
			} else {
				$type   = get_option( 'slotnova_deposit_type', 'percentage' );
				$amount = (float) get_option( 'slotnova_deposit_amount', 20 );
				if ( 'percentage' === $type ) {
					$initial_deposit = ( $full_price * $amount ) / 100;
				} else {
					$initial_deposit = min( $amount, $full_price );
				}
				$initial_due = max( 0, $full_price - $initial_deposit );
			}
		}

		$deposit_date_obj = $order->get_date_paid() ? $order->get_date_paid() : $order->get_date_created();
		$deposit_date_str = $deposit_date_obj ? $deposit_date_obj->date_i18n( 'M j, Y \a\t g:i A' ) : '';

		$due_paid_date_raw = get_post_meta( $post_id, '_slotnova_due_paid_date', true );
		$due_date_str      = $due_paid_date_raw ? date_i18n( 'M j, Y \a\t g:i A', strtotime( $due_paid_date_raw ) ) : '';
		if ( ! $due_date_str && $is_fully_paid && $deposit_date_obj ) {
			$due_date_str = $deposit_date_obj->date_i18n( 'M j, Y \a\t g:i A' );
		}

		$currency_symbol    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $order->get_currency() ) : '$';
		$formatted_total    = function_exists( 'wc_price' ) ? wc_price( $full_price, array( 'currency' => $order->get_currency() ) ) : $currency_symbol . number_format( $full_price, 2 );
		$formatted_dep_paid = function_exists( 'wc_price' ) ? wc_price( $initial_deposit, array( 'currency' => $order->get_currency() ) ) : $currency_symbol . number_format( $initial_deposit, 2 );
		$formatted_rem_due  = function_exists( 'wc_price' ) ? wc_price( $initial_due, array( 'currency' => $order->get_currency() ) ) : $currency_symbol . number_format( $initial_due, 2 );
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			function updateAdminOrderTotalsTable() {
				var totalsTable = document.querySelector('table.wc-order-totals');
				if (!totalsTable) return;

				var isFullyPaid = <?php echo $is_fully_paid ? 'true' : 'false'; ?>;
				var totalHtml = <?php echo wp_json_encode( $formatted_total ); ?>;
				var depPaidHtml = <?php echo wp_json_encode( $formatted_dep_paid ); ?>;
				var remDueHtml = <?php echo wp_json_encode( $formatted_rem_due ); ?>;
				var depositDateStr = <?php echo wp_json_encode( $deposit_date_str ); ?>;
				var dueDateStr = <?php echo wp_json_encode( $due_date_str ); ?>;

				var html = '';
				// 1. Total Amount Row
				html += '<tr>';
				html += '<td class="label" style="font-weight:700; color:#0f172a;">' + <?php echo wp_json_encode( __( 'Total Amount:', 'slotnova-booking' ) ); ?> + '</td>';
				html += '<td class="total" style="font-weight:700; color:#0f172a;">' + totalHtml + '</td>';
				html += '</tr>';

				// 2. Deposit Paid Row
				html += '<tr>';
				html += '<td class="label" style="color:#166534; font-weight:600;">';
				html += '<span>' + <?php echo wp_json_encode( __( 'Deposit Paid:', 'slotnova-booking' ) ); ?> + '</span>';
				if (depositDateStr) {
					html += ' <small style="font-size:11px; font-weight:normal; color:#64748b; margin-left:4px;">(' + depositDateStr + ')</small>';
				}
				html += '</td>';
				html += '<td class="total" style="color:#166534; font-weight:600;">' + depPaidHtml + '</td>';
				html += '</tr>';

				// 3. Remaining / Final Balance Row
				html += '<tr>';
				if (isFullyPaid) {
					html += '<td class="label" style="color:#166534; font-weight:600;">';
					html += '<span>' + <?php echo wp_json_encode( __( 'Final Balance Paid:', 'slotnova-booking' ) ); ?> + '</span>';
					if (dueDateStr) {
						html += ' <small style="font-size:11px; font-weight:normal; color:#64748b; margin-left:4px;">(' + dueDateStr + ')</small>';
					}
					html += '</td>';
					html += '<td class="total" style="color:#166534; font-weight:600;">' + remDueHtml + '</td>';
				} else {
					html += '<td class="label" style="color:#dc2626; font-weight:700;">';
					html += '<span>' + <?php echo wp_json_encode( __( 'Remaining Balance:', 'slotnova-booking' ) ); ?> + '</span>';
					html += ' <small style="font-size:11px; font-weight:normal; color:#dc2626; margin-left:4px;">(Due at Appointment)</small>';
					html += '</td>';
					html += '<td class="total" style="color:#dc2626; font-weight:700;">' + remDueHtml + '</td>';
				}
				html += '</tr>';

				// 4. Total Paid Row (If fully paid)
				if (isFullyPaid) {
					html += '<tr style="border-top: 1px solid #cbd5e1;">';
					html += '<td class="label" style="color:#15803d; font-weight:700; font-size:14px; padding-top:8px;">' + <?php echo wp_json_encode( __( 'Total Paid:', 'slotnova-booking' ) ); ?> + '</td>';
					html += '<td class="total" style="color:#15803d; font-weight:700; font-size:14px; padding-top:8px;">✓ ' + totalHtml + '</td>';
					html += '</tr>';
				}

				totalsTable.innerHTML = html;

				// Update native WooCommerce Paid row below totals table
				document.querySelectorAll('#woocommerce-order-items td.label, #woocommerce-order-items .wc-order-totals-paid').forEach(function(el) {
					var text = (el.textContent || '').trim();
					if (text === 'Paid:' || text.indexOf('Paid:') === 0) {
						var parentRow = el.closest('tr') || el;
						var valCell = parentRow.querySelector('.total, td:last-child, strong');
						if (valCell) {
							valCell.innerHTML = isFullyPaid ? totalHtml : paidHtml;
						}
					}
				});

				// Also hide negative fee row in items table
				document.querySelectorAll('#woocommerce-order-items tr.fee, #order_fee_line_items tr.fee').forEach(function(row) {
					var text = row.textContent || '';
					if (text.indexOf('Remaining Balance') !== -1 || text.indexOf('Balance Due') !== -1) {
						row.style.display = 'none';
					}
				});
			}

			updateAdminOrderTotalsTable();
			setTimeout(updateAdminOrderTotalsTable, 300);
			setTimeout(updateAdminOrderTotalsTable, 1000);
		});
		</script>
		<?php
	}

	/**
	 * Filter WooCommerce Order Item Totals to display Subtotal, Partial Payment, and Remaining Balance.
	 */
	public function filterOrderTotalsLabels( array $total_rows, $order = null, $tax_display = '' ): array {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return $total_rows;
		}

		$is_deposit   = get_post_meta( $order->get_id(), '_slotnova_is_deposit', true );
		$deposit_paid = (float) get_post_meta( $order->get_id(), '_slotnova_deposit_paid', true );
		$deposit_due  = (float) get_post_meta( $order->get_id(), '_slotnova_deposit_due', true );
		$due_paid     = get_post_meta( $order->get_id(), '_slotnova_due_paid', true );

		if ( 'yes' === $is_deposit && function_exists( 'wc_price' ) ) {
			$subtotal = $deposit_paid + $deposit_due;
			if ( $subtotal <= 0 ) {
				$subtotal = $order->get_subtotal();
			}

			$currency_args = array( 'currency' => $order->get_currency() );

			$new_rows = array();
			$new_rows['cart_subtotal'] = array(
				'label' => __( 'Total Amount:', 'slotnova-booking' ),
				'value' => wc_price( $subtotal, $currency_args ),
			);

			if ( 'yes' === $due_paid || $deposit_due <= 0 ) {
				$new_rows['full_paid'] = array(
					'label' => __( 'Full Paid:', 'slotnova-booking' ),
					'value' => wc_price( $subtotal, $currency_args ),
				);
			} else {
				$new_rows['partial_payment'] = array(
					'label' => __( 'Partial:', 'slotnova-booking' ),
					'value' => wc_price( $deposit_paid, $currency_args ),
				);
				$new_rows['remaining_balance'] = array(
					'label' => __( 'Remaining Balance:', 'slotnova-booking' ),
					'value' => wc_price( $deposit_due, $currency_args ),
				);
			}

			return $new_rows;
		}

		return $total_rows;
	}

	/**
	 * Filter WooCommerce Order Item Fee Name in admin & order views.
	 */
	public function filterFeeItemName( string $name, $item = null ): string {
		if ( false !== strpos( strtolower( $name ), 'remaining balance' ) || false !== strpos( strtolower( $name ), 'balance due' ) ) {
			return __( 'Remaining Balance', 'slotnova-booking' );
		}
		return $name;
	}

	/**
	 * Filter gettext for WooCommerce Order Edit Screen to replace "Fees:" label with "Remaining Balance:".
	 */
	public function filterGettextFeeLabel( string $translated_text, string $text, string $domain ): string {
		if ( 'woocommerce' === $domain && 'Fees:' === $text ) {
			return __( 'Remaining Balance:', 'slotnova-booking' );
		}
		return $translated_text;
	}
}
