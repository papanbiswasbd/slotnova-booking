<?php
/**
 * Deposits Settings Renderer.
 *
 * Renders Deposits settings fields under SlotNova Global Settings vertical tabs.
 *
 * @package SlotNova\Extensions\Deposits\Services
 */

namespace SlotNova\Extensions\Deposits\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DepositSettingsRenderer {

	public function registerSettings(): void {
		if ( function_exists( 'register_setting' ) ) {
			register_setting( 'slotnova_settings_group', 'slotnova_deposit_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
			register_setting( 'slotnova_settings_group', 'slotnova_deposit_type', array( 'sanitize_callback' => 'sanitize_text_field' ) );
			register_setting( 'slotnova_settings_group', 'slotnova_deposit_amount', array( 'sanitize_callback' => 'floatval' ) );
		}
	}

	public function registerVerticalTab( array $tabs ): array {
		$tabs['deposits'] = array(
			'title' => __( 'Deposits & Payments', 'slotnova-booking' ),
			'icon'  => 'dashicons-money-alt',
		);
		return $tabs;
	}

	public function renderSettingsSection(): void {
		$enabled = function_exists( 'get_option' ) ? get_option( 'slotnova_deposit_enabled', 'no' ) : 'no';
		$type    = function_exists( 'get_option' ) ? get_option( 'slotnova_deposit_type', 'percentage' ) : 'percentage';
		$amount  = function_exists( 'get_option' ) ? get_option( 'slotnova_deposit_amount', '20' ) : '20';
		?>
		<div class="slotnova-vtab-panel" id="slotnova-vtab-deposits" style="display: none; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">

			<!-- Header -->
			<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 24px;">
				<div>
					<div style="display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-money-alt" style="font-size: 20px; color: #4f46e5; width: 20px; height: 20px;"></span>
						<h2 style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Deposits & Partial Payments', 'slotnova-booking' ); ?></h2>
					</div>
					<p style="color: #64748b; font-size: 13px; margin: 4px 0 0 0;"><?php esc_html_e( 'Require customers to pay an upfront deposit during WooCommerce booking checkout.', 'slotnova-booking' ); ?></p>
				</div>
				<span style="background: #dcfce7; color: #15803d; border-radius: 20px; padding: 4px 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px;"><?php esc_html_e( 'ACTIVE EXTENSION', 'slotnova-booking' ); ?></span>
			</div>

			<!-- Feature Toggle Switch -->
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
				<div>
					<h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Enable Upfront Deposit Requirement', 'slotnova-booking' ); ?></h4>
					<p style="margin: 0; font-size: 13px; color: #64748b;"><?php esc_html_e( 'When enabled, customers pay only the deposit amount at checkout and pay the remaining balance on appointment day.', 'slotnova-booking' ); ?></p>
				</div>
				<label class="slotnova-toggle-wrapper">
					<input type="hidden" name="slotnova_deposit_enabled" value="no">
					<input type="checkbox" name="slotnova_deposit_enabled" value="yes" <?php checked( $enabled, 'yes' ); ?> style="display: none;" id="slotnova_deposit_enabled_toggle">
					<span id="slotnova_toggle_track" class="slotnova-toggle-track <?php echo 'yes' === $enabled ? 'active' : ''; ?>">
						<span id="slotnova_toggle_knob" class="slotnova-toggle-knob"></span>
					</span>
				</label>
			</div>

			<!-- Calculation Method (Segmented Visual Cards) -->
			<div style="margin-bottom: 24px;">
				<label style="display: block; font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 10px;"><?php esc_html_e( 'Calculation Method', 'slotnova-booking' ); ?></label>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">

					<label class="slotnova-type-card <?php echo 'percentage' === $type ? 'active' : ''; ?>">
						<input type="radio" name="slotnova_deposit_type" value="percentage" <?php checked( $type, 'percentage' ); ?> class="slotnova-type-card-radio" style="margin-top: 2px;" />
						<div>
							<strong style="display: block; font-size: 14px; color: #0f172a; font-weight: 700;"><?php esc_html_e( 'Percentage (%)', 'slotnova-booking' ); ?></strong>
							<span style="font-size: 12px; color: #64748b; display: block; margin-top: 2px;"><?php esc_html_e( 'Calculates deposit as a percentage of the total service cost.', 'slotnova-booking' ); ?></span>
						</div>
					</label>

					<label class="slotnova-type-card <?php echo 'fixed' === $type ? 'active' : ''; ?>">
						<input type="radio" name="slotnova_deposit_type" value="fixed" <?php checked( $type, 'fixed' ); ?> class="slotnova-type-card-radio" style="margin-top: 2px;" />
						<div>
							<strong style="display: block; font-size: 14px; color: #0f172a; font-weight: 700;"><?php esc_html_e( 'Fixed Amount ($)', 'slotnova-booking' ); ?></strong>
							<span style="font-size: 12px; color: #64748b; display: block; margin-top: 2px;"><?php esc_html_e( 'Requires a fixed dollar amount upfront per booking.', 'slotnova-booking' ); ?></span>
						</div>
					</label>

				</div>
			</div>

			<!-- Deposit Amount Input Box -->
			<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;">
				<label for="slotnova_deposit_amount" style="display: block; font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 8px;"><?php esc_html_e( 'Deposit Value / Amount', 'slotnova-booking' ); ?></label>
				<div style="display: flex; align-items: center; gap: 12px;">
					<input type="number" step="0.01" min="0" name="slotnova_deposit_amount" id="slotnova_deposit_amount" value="<?php echo esc_attr( $amount ); ?>" style="width: 150px; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 15px; font-weight: 700; color: #0f172a;" />
					<span style="font-size: 13px; color: #64748b; font-weight: 500;"><?php esc_html_e( '(Enter value: e.g. 20 for 20% or 15 for $15.00 fixed amount)', 'slotnova-booking' ); ?></span>
				</div>
			</div>

		</div>
		<?php
	}
}
