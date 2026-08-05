<?php
/**
 * Admin Settings Controller.
 *
 * Registers Group Booking settings fields under SlotNova Global Settings vertical tabs.
 *
 * @package SlotNova\Extensions\GroupBooking\Admin\Controllers
 */

namespace SlotNova\Extensions\GroupBooking\Admin\Controllers;

use SlotNova\Extensions\GroupBooking\Helpers\GroupBookingHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminSettingsController {

	public function registerSettings(): void {
		if ( function_exists( 'register_setting' ) ) {
			register_setting( 'slotnova_settings_group', 'slotnova_group_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
			register_setting( 'slotnova_settings_group', 'slotnova_group_default_max_capacity', array( 'sanitize_callback' => 'absint' ) );
			register_setting( 'slotnova_settings_group', 'slotnova_group_default_min_capacity', array( 'sanitize_callback' => 'absint' ) );
			register_setting( 'slotnova_settings_group', 'slotnova_group_default_pricing_mode', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		}
	}

	public function registerVerticalTab( array $tabs ): array {
		$tabs['group-booking'] = array(
			'title' => __( 'Group Booking', 'slotnova-booking' ),
			'icon'  => 'dashicons-groups',
		);
		return $tabs;
	}

	public function renderSettingsSection(): void {
		$enabled     = GroupBookingHelper::getOption( 'enabled', 'yes' );
		$maxCap      = GroupBookingHelper::getOption( 'default_max_capacity', 20 );
		$minCap      = GroupBookingHelper::getOption( 'default_min_capacity', 1 );
		$pricingMode = GroupBookingHelper::getOption( 'default_pricing_mode', 'per_person' );
		?>
		<div class="slotnova-vtab-panel" id="slotnova-vtab-group-booking" style="display: none; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">

			<!-- Header -->
			<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 24px;">
				<div>
					<div style="display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-groups" style="font-size: 22px; color: #4f46e5; width: 22px; height: 22px;"></span>
						<h2 style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Group Booking Settings', 'slotnova-booking' ); ?></h2>
					</div>
					<p style="color: #64748b; font-size: 13px; margin: 4px 0 0 0;"><?php esc_html_e( 'Configure capacity bounds, participant rosters, and pricing modes.', 'slotnova-booking' ); ?></p>
				</div>
				<span style="background: #dcfce7; color: #15803d; border-radius: 20px; padding: 4px 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px;"><?php esc_html_e( 'ACTIVE EXTENSION', 'slotnova-booking' ); ?></span>
			</div>

			<!-- Main Toggle -->
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
				<div>
					<h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Enable Group Booking Add-on', 'slotnova-booking' ); ?></h4>
					<p style="margin: 0; font-size: 13px; color: #64748b;"><?php esc_html_e( 'Allow multi-participant capacity bookings across your services.', 'slotnova-booking' ); ?></p>
				</div>
				<label class="slotnova-toggle-wrapper">
					<input type="hidden" name="slotnova_group_enabled" value="no">
					<input type="checkbox" name="slotnova_group_enabled" value="yes" <?php checked( $enabled, 'yes' ); ?> style="display: none;" id="slotnova_group_enabled_toggle">
					<span id="slotnova_group_toggle_track" class="slotnova-toggle-track <?php echo 'yes' === $enabled ? 'active' : ''; ?>">
						<span id="slotnova_group_toggle_knob" class="slotnova-toggle-knob"></span>
					</span>
				</label>
			</div>

			<!-- Capacity Controls -->
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
				<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;">
					<label for="slotnova_group_default_max_capacity" style="display: block; font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 8px;"><?php esc_html_e( 'Default Maximum Capacity', 'slotnova-booking' ); ?></label>
					<input type="number" min="1" step="1" name="slotnova_group_default_max_capacity" id="slotnova_group_default_max_capacity" value="<?php echo esc_attr( $maxCap ); ?>" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px;" />
					<span style="font-size: 12px; color: #64748b; display: block; margin-top: 6px;"><?php esc_html_e( 'Max seats available per time slot.', 'slotnova-booking' ); ?></span>
				</div>

				<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;">
					<label for="slotnova_group_default_min_capacity" style="display: block; font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 8px;"><?php esc_html_e( 'Default Minimum Capacity Threshold', 'slotnova-booking' ); ?></label>
					<input type="number" min="1" step="1" name="slotnova_group_default_min_capacity" id="slotnova_group_default_min_capacity" value="<?php echo esc_attr( $minCap ); ?>" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px;" />
					<span style="font-size: 12px; color: #64748b; display: block; margin-top: 6px;"><?php esc_html_e( 'Minimum seats required to confirm class/session.', 'slotnova-booking' ); ?></span>
				</div>
			</div>

			<!-- Pricing Mode Selector -->
			<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px;">
				<label for="slotnova_group_default_pricing_mode" style="display: block; font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 8px;"><?php esc_html_e( 'Default Group Pricing Mode', 'slotnova-booking' ); ?></label>
				<select name="slotnova_group_default_pricing_mode" id="slotnova_group_default_pricing_mode" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px;">
					<option value="per_person" <?php selected( $pricingMode, 'per_person' ); ?>><?php esc_html_e( 'Per Person (Standard Rate x Participants)', 'slotnova-booking' ); ?></option>
					<option value="fixed_group" <?php selected( $pricingMode, 'fixed_group' ); ?>><?php esc_html_e( 'Fixed Group Price (Single Flat Rate per Session)', 'slotnova-booking' ); ?></option>
					<option value="tier_pricing" <?php selected( $pricingMode, 'tier_pricing' ); ?>><?php esc_html_e( 'Tiered Volume Pricing Table', 'slotnova-booking' ); ?></option>
				</select>
			</div>

		</div>
		<script>
			jQuery(document).ready(function($){
				$('#slotnova_group_enabled_toggle').on('change', function(){
					if($(this).is(':checked')){
						$('#slotnova_group_toggle_track').addClass('active');
					} else {
						$('#slotnova_group_toggle_track').removeClass('active');
					}
				});
			});
		</script>
		<?php
	}
}
