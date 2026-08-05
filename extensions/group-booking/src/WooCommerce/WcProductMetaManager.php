<?php
/**
 * WooCommerce Product Meta Manager.
 *
 * Adds and saves product-level Group Booking settings tabs and meta fields.
 *
 * @package SlotNova\Extensions\GroupBooking\WooCommerce
 */

namespace SlotNova\Extensions\GroupBooking\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WcProductMetaManager {

	/**
	 * Register product data tab.
	 *
	 * @param array $tabs Product tabs.
	 * @return array
	 */
	public function addProductDataTab( array $tabs ): array {
		$tabs['slotnova_group_booking'] = array(
			'label'    => __( 'Group Booking', 'slotnova-booking' ),
			'target'   => 'slotnova_group_booking_data',
			'class'    => array( 'show_if_slotnova' ),
			'priority' => 30,
		);
		return $tabs;
	}

	/**
	 * Render product data panel.
	 *
	 * @return void
	 */
	public function renderProductDataPanel(): void {
		global $post;
		$productId = $post->ID;

		$override    = get_post_meta( $productId, '_slotnova_group_override', true );
		$enabled     = get_post_meta( $productId, '_slotnova_group_enabled', true );
		$groupPrice  = get_post_meta( $productId, '_slotnova_group_price', true );
		$maxCap      = get_post_meta( $productId, '_slotnova_group_max_capacity', true );
		$minCap      = get_post_meta( $productId, '_slotnova_group_min_capacity', true );
		$pricingMode = get_post_meta( $productId, '_slotnova_group_pricing_mode', true );
		$currency    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
		?>
		<div id="slotnova_group_booking_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<p class="form-field">
					<label for="_slotnova_group_override"><strong><?php esc_html_e( 'Override Global Settings', 'slotnova-booking' ); ?></strong></label>
					<input type="checkbox" class="checkbox" name="_slotnova_group_override" id="_slotnova_group_override" value="yes" <?php checked( $override, 'yes' ); ?> />
					<span class="description"><?php esc_html_e( 'Enable custom group booking settings for this product.', 'slotnova-booking' ); ?></span>
				</p>
			</div>

			<div class="options_group slotnova-group-override-fields" style="<?php echo 'yes' === $override ? '' : 'display:none;'; ?>">
				<p class="form-field">
					<label for="_slotnova_group_enabled"><?php esc_html_e( 'Enable Group Booking', 'slotnova-booking' ); ?></label>
					<input type="checkbox" class="checkbox" name="_slotnova_group_enabled" id="_slotnova_group_enabled" value="yes" <?php checked( 'no' !== $enabled, true ); ?> />
				</p>

				<p class="form-field">
					<label for="_slotnova_group_price"><?php printf( esc_html__( 'Group Booking Price (%s)', 'slotnova-booking' ), esc_html( $currency ) ); ?></label>
					<input type="text" class="short wc_input_price" name="_slotnova_group_price" id="_slotnova_group_price" value="<?php echo esc_attr( $groupPrice ); ?>" placeholder="<?php esc_attr_e( 'e.g. 25.00', 'slotnova-booking' ); ?>" />
					<span class="description"><?php esc_html_e( 'Base price for group booking. Displayed directly if no specific service is selected.', 'slotnova-booking' ); ?></span>
				</p>

				<p class="form-field">
					<label for="_slotnova_group_max_capacity"><?php esc_html_e( 'Maximum Capacity (Seats)', 'slotnova-booking' ); ?></label>
					<input type="number" class="short" name="_slotnova_group_max_capacity" id="_slotnova_group_max_capacity" value="<?php echo esc_attr( $maxCap !== '' ? $maxCap : 20 ); ?>" min="1" step="1" />
					<span class="description"><?php esc_html_e( 'Maximum participants allowed per time slot.', 'slotnova-booking' ); ?></span>
				</p>

				<p class="form-field">
					<label for="_slotnova_group_min_capacity"><?php esc_html_e( 'Minimum Capacity (Threshold)', 'slotnova-booking' ); ?></label>
					<input type="number" class="short" name="_slotnova_group_min_capacity" id="_slotnova_group_min_capacity" value="<?php echo esc_attr( $minCap !== '' ? $minCap : 1 ); ?>" min="1" step="1" />
					<span class="description"><?php esc_html_e( 'Minimum participants required before session is confirmed.', 'slotnova-booking' ); ?></span>
				</p>

				<p class="form-field">
					<label for="_slotnova_group_pricing_mode"><?php esc_html_e( 'Pricing Mode', 'slotnova-booking' ); ?></label>
					<select name="_slotnova_group_pricing_mode" id="_slotnova_group_pricing_mode">
						<option value="per_person" <?php selected( $pricingMode, 'per_person' ); ?>><?php esc_html_e( 'Per Person (Unit Rate x Participants)', 'slotnova-booking' ); ?></option>
						<option value="fixed_group" <?php selected( $pricingMode, 'fixed_group' ); ?>><?php esc_html_e( 'Fixed Group Price (Flat Price per Slot)', 'slotnova-booking' ); ?></option>
						<option value="tier_pricing" <?php selected( $pricingMode, 'tier_pricing' ); ?>><?php esc_html_e( 'Tiered Pricing (Volume Rates)', 'slotnova-booking' ); ?></option>
					</select>
				</p>
			</div>
		</div>
		<script>
			jQuery(document).ready(function($){
				$('#_slotnova_group_override').on('change', function(){
					if($(this).is(':checked')){
						$('.slotnova-group-override-fields').show();
					} else {
						$('.slotnova-group-override-fields').hide();
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Save product meta fields.
	 *
	 * @param int $productId Product ID.
	 * @return void
	 */
	public function saveProductMeta( int $productId ): void {
		$override = isset( $_POST['_slotnova_group_override'] ) ? 'yes' : 'no';
		update_post_meta( $productId, '_slotnova_group_override', $override );

		if ( 'yes' === $override ) {
			$enabled    = isset( $_POST['_slotnova_group_enabled'] ) ? 'yes' : 'no';
			$groupPrice = isset( $_POST['_slotnova_group_price'] ) ? wc_format_decimal( wp_unslash( $_POST['_slotnova_group_price'] ) ) : '';
			$maxCap     = isset( $_POST['_slotnova_group_max_capacity'] ) ? absint( $_POST['_slotnova_group_max_capacity'] ) : 20;
			$minCap     = isset( $_POST['_slotnova_group_min_capacity'] ) ? absint( $_POST['_slotnova_group_min_capacity'] ) : 1;
			$mode       = isset( $_POST['_slotnova_group_pricing_mode'] ) ? sanitize_text_field( $_POST['_slotnova_group_pricing_mode'] ) : 'per_person';

			update_post_meta( $productId, '_slotnova_group_enabled', $enabled );
			update_post_meta( $productId, '_slotnova_group_price', $groupPrice );
			update_post_meta( $productId, '_slotnova_group_max_capacity', $maxCap );
			update_post_meta( $productId, '_slotnova_group_min_capacity', $minCap );
			update_post_meta( $productId, '_slotnova_group_pricing_mode', $mode );
		}
	}
}
