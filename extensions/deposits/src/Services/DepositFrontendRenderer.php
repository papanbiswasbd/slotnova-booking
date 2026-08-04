<?php
/**
 * Frontend Deposit Selection Renderer.
 *
 * Renders Full Payment vs Partial Deposit options after Service selection
 * on single product booking page and updates booking summary dynamically.
 *
 * @package SlotNova\Extensions\Deposits\Services
 */

namespace SlotNova\Extensions\Deposits\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DepositFrontendRenderer {

	public function renderPaymentOptionField( $product ): void {
		$product_id = is_object( $product ) && method_exists( $product, 'get_id' ) ? $product->get_id() : 0;
		$config     = DepositCartCalculator::getProductDepositConfig( $product_id );

		if ( 'yes' !== $config['enabled'] ) {
			return;
		}

		$type          = $config['type'];
		$amount        = $config['amount'];
		$symbol        = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
		$primary_color = function_exists( 'get_option' ) ? get_option( 'slotnova_primary_color', '#2271b1' ) : '#2271b1';
		?>
		<div class="slotnova-payment-options-wrapper slotnova-is-hidden" id="slotnova-payment-options-box" data-deposit-type="<?php echo esc_attr( $type ); ?>" data-deposit-amount="<?php echo esc_attr( $amount ); ?>" data-currency-symbol="<?php echo esc_attr( $symbol ); ?>" data-primary-color="<?php echo esc_attr( $primary_color ); ?>" style="margin-top: 24px; margin-bottom: 24px; display: none;">
			<label style="display: block; font-weight: 700; font-size: 12px; color: #64748b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
				<?php esc_html_e( 'Payment Plan', 'slotnova-booking' ); ?>
			</label>
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">

				<!-- Option 1: Full Payment (Default Selected) -->
				<label class="slotnova-payment-card active" id="slotnova-opt-full" style="border: 1.5px solid <?php echo esc_attr( $primary_color ); ?>; background: #ffffff; border-radius: 10px; padding: 14px 16px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 12px; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
					<div style="display: flex; align-items: center; gap: 10px;">
						<input type="radio" name="slotnova_payment_type" value="full" checked="checked" style="margin: 0; width: 16px; height: 16px; accent-color: <?php echo esc_attr( $primary_color ); ?>;" class="slotnova-pay-radio" />
						<div>
							<strong style="display: block; font-size: 13px; color: #0f172a; font-weight: 700; line-height: 1.2;"><?php esc_html_e( 'Full Payment', 'slotnova-booking' ); ?></strong>
							<span style="font-size: 11px; color: #64748b; display: block; margin-top: 2px;"><?php esc_html_e( 'Pay 100% now', 'slotnova-booking' ); ?></span>
						</div>
					</div>
					<span id="slotnova-card-full-badge" style="font-size: 13px; font-weight: 700; color: #0f172a;">-</span>
				</label>

				<!-- Option 2: Partial Deposit -->
				<label class="slotnova-payment-card" id="slotnova-opt-deposit" style="border: 1.5px solid #e2e8f0; background: #ffffff; border-radius: 10px; padding: 14px 16px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 12px; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
					<div style="display: flex; align-items: center; gap: 10px;">
						<input type="radio" name="slotnova_payment_type" value="deposit" style="margin: 0; width: 16px; height: 16px; accent-color: <?php echo esc_attr( $primary_color ); ?>;" class="slotnova-pay-radio" />
						<div>
							<strong style="display: block; font-size: 13px; color: #0f172a; font-weight: 700; line-height: 1.2;"><?php esc_html_e( 'Pay Deposit', 'slotnova-booking' ); ?></strong>
							<span style="font-size: 11px; color: #64748b; display: block; margin-top: 2px;" id="slotnova-deposit-subtext">
								<?php
								if ( 'percentage' === $type ) {
									/* translators: %s: Deposit percentage amount */
									printf( esc_html__( '%s%% deposit', 'slotnova-booking' ), esc_html( $amount ) );
								} else {
									/* translators: 1: Currency symbol 2: Deposit fixed amount */
									printf( esc_html__( '%1$s%2$s deposit', 'slotnova-booking' ), esc_html( $symbol ), esc_html( $amount ) );
								}
								?>
							</span>
						</div>
					</div>
					<span id="slotnova-card-deposit-badge" style="font-size: 13px; font-weight: 700; color: <?php echo esc_attr( $primary_color ); ?>;">-</span>
				</label>

			</div>
		</div>
		<?php
	}

	public function addCartItemData( $cart_item_data ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['slotnova_payment_type'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$cart_item_data['slotnova_payment_type'] = sanitize_text_field( wp_unslash( $_POST['slotnova_payment_type'] ) );
		} else {
			$cart_item_data['slotnova_payment_type'] = 'full';
		}
		return $cart_item_data;
	}

	/**
	 * Add custom Deposits tab under WooCommerce Product Data meta box.
	 */
	public function addProductDepositTab( array $tabs ): array {
		$tabs['slotnova_deposits'] = array(
			'label'    => __( 'Deposits & Payments', 'slotnova-booking' ),
			'target'   => 'slotnova_deposit_product_data',
			'class'    => array( 'show_if_slotnova' ),
			'priority' => 65,
		);
		return $tabs;
	}

	/**
	 * Render Deposits product settings panel.
	 */
	public function renderProductDepositPanel(): void {
		?>
		<div id="slotnova_deposit_product_data" class="panel woocommerce_options_panel show_if_slotnova hidden">
			<div class="options_group">
				<h4 style="margin: 14px 16px 6px; font-weight: 700; font-size: 14px; color: #0f172a;">
					<?php esc_html_e( 'Individual Deposit & Partial Payment Settings', 'slotnova-booking' ); ?>
				</h4>
				<p style="margin: 0 16px 14px; color: #64748b; font-size: 13px;">
					<?php esc_html_e( 'Configure custom deposit rules for this product. Selecting "Use Global Settings" will follow the global deposit extension settings.', 'slotnova-booking' ); ?>
				</p>
				<?php
				woocommerce_wp_select( array(
					'id'          => '_slotnova_deposit_enable_override',
					'label'       => __( 'Deposit Status', 'slotnova-booking' ),
					'description' => __( 'Choose whether deposits are enabled for this product.', 'slotnova-booking' ),
					'desc_tip'    => true,
					'options'     => array(
						'global' => __( 'Use Global Settings', 'slotnova-booking' ),
						'yes'    => __( 'Enable Deposit (Override)', 'slotnova-booking' ),
						'no'     => __( 'Disable Deposit (Force Full Payment)', 'slotnova-booking' ),
					),
				) );

				woocommerce_wp_select( array(
					'id'          => '_slotnova_deposit_type_override',
					'label'       => __( 'Deposit Type', 'slotnova-booking' ),
					'description' => __( 'Select percentage or fixed amount for this product.', 'slotnova-booking' ),
					'desc_tip'    => true,
					'options'     => array(
						'percentage' => __( 'Percentage (%)', 'slotnova-booking' ),
						'fixed'      => __( 'Fixed Amount ($)', 'slotnova-booking' ),
					),
				) );

				woocommerce_wp_text_input( array(
					'id'          => '_slotnova_deposit_amount_override',
					'label'       => __( 'Deposit Amount', 'slotnova-booking' ),
					'placeholder' => __( 'e.g. 20', 'slotnova-booking' ),
					'description' => __( 'Enter the deposit percentage or fixed dollar amount.', 'slotnova-booking' ),
					'desc_tip'    => true,
					'type'        => 'number',
					'custom_attributes' => array(
						'step' => 'any',
						'min'  => '0',
					),
				) );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save product deposit override post meta on product save.
	 */
	public function saveProductDepositMeta( $post_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['_slotnova_deposit_enable_override'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $post_id, '_slotnova_deposit_enable_override', sanitize_text_field( wp_unslash( $_POST['_slotnova_deposit_enable_override'] ) ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['_slotnova_deposit_type_override'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $post_id, '_slotnova_deposit_type_override', sanitize_text_field( wp_unslash( $_POST['_slotnova_deposit_type_override'] ) ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['_slotnova_deposit_amount_override'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $post_id, '_slotnova_deposit_amount_override', sanitize_text_field( wp_unslash( $_POST['_slotnova_deposit_amount_override'] ) ) );
		}
	}
}
