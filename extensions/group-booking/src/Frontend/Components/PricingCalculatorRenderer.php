<?php
/**
 * Pricing Calculator Renderer.
 *
 * Renders dynamic line item breakdown subtotal on single product booking form.
 *
 * @package SlotNova\Extensions\GroupBooking\Frontend\Components
 */

namespace SlotNova\Extensions\GroupBooking\Frontend\Components;

use SlotNova\Extensions\GroupBooking\Services\PricingEngineService;
use SlotNova\Extensions\GroupBooking\Helpers\GroupBookingHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PricingCalculatorRenderer {

	private PricingEngineService $pricingEngine;

	public function __construct( PricingEngineService $pricingEngine ) {
		$this->pricingEngine = $pricingEngine;
	}

	/**
	 * Render pricing breakdown box.
	 *
	 * @param mixed $productOrId Product ID or WC_Product object.
	 * @return void
	 */
	public function renderPricingBreakdown( $productOrId = 0 ): void {
		$productId = 0;
		if ( is_numeric( $productOrId ) ) {
			$productId = (int) $productOrId;
		} elseif ( is_object( $productOrId ) && method_exists( $productOrId, 'get_id' ) ) {
			$productId = (int) $productOrId->get_id();
		}

		if ( $productId <= 0 ) {
			global $product;
			if ( $product && is_object( $product ) && method_exists( $product, 'get_id' ) ) {
				$productId = (int) $product->get_id();
			}
		}

		if ( $productId <= 0 || ! GroupBookingHelper::isGroupBookingEnabled( $productId ) ) {
			return;
		}

		$mode = GroupBookingHelper::getPricingMode( $productId );

		$groupBasePrice = GroupBookingHelper::getGroupBasePrice( $productId );
		$product        = wc_get_product( $productId );
		$basePrice      = $groupBasePrice > 0 ? $groupBasePrice : ( $product ? floatval( $product->get_price() ) : 0 );

		$initialPriceHtml = '--';
		if ( $basePrice > 0 ) {
			$calc             = $this->pricingEngine->calculateGroupPrice( $basePrice, 1, $productId );
			$initialPriceHtml = wc_price( $calc['total_price'] );
		}
		?>
		<div id="slotnova_group_price_summary" class="slotnova-group-price-box" style="margin-top: 14px; padding: 12px 0; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
			<div>
				<span style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
					<?php esc_html_e( 'Total Group Price', 'slotnova-booking' ); ?>
				</span>
				<span id="slotnova_group_mode_label" style="display: block; font-size: 11px; color: #64748b; margin-top: 2px;">
					<?php
					if ( 'fixed_group' === $mode ) {
						esc_html_e( 'Flat Session Group Rate', 'slotnova-booking' );
					} elseif ( 'tier_pricing' === $mode ) {
						esc_html_e( 'Volume Tiered Discount Applied', 'slotnova-booking' );
					} else {
						esc_html_e( 'Per Person Rate x Participants', 'slotnova-booking' );
					}
					?>
				</span>
			</div>
			<div id="slotnova_group_subtotal_amount" style="font-size: 18px; font-weight: 800; color: #0f172a;">
				<?php echo wp_kses_post( $initialPriceHtml ); ?>
			</div>
		</div>
		<?php
	}
}
