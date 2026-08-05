<?php
/**
 * Pricing Engine Service.
 *
 * Calculates line-item totals for group bookings based on pricing modes (Per Person, Fixed Group, Tier Pricing).
 *
 * @package SlotNova\Extensions\GroupBooking\Services
 */

namespace SlotNova\Extensions\GroupBooking\Services;

use SlotNova\Extensions\GroupBooking\Helpers\GroupBookingHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PricingEngineService {

	/**
	 * Calculate total price and unit price for a group booking line item.
	 *
	 * @param float $basePrice Unit base price of service/product.
	 * @param int   $participantCount Number of participants.
	 * @param int   $productId Product ID.
	 * @return array array( 'total_price' => float, 'unit_price' => float, 'pricing_mode' => string )
	 */
	public function calculateGroupPrice( float $basePrice, int $participantCount, int $productId = 0 ): array {
		$participantCount = max( 1, $participantCount );
		$pricingMode      = GroupBookingHelper::getPricingMode( $productId );

		if ( $basePrice <= 0 && $productId > 0 ) {
			$groupBasePrice = GroupBookingHelper::getGroupBasePrice( $productId );
			if ( $groupBasePrice > 0 ) {
				$basePrice = $groupBasePrice;
			}
		}

		switch ( $pricingMode ) {
			case 'fixed_group':
				// Flat rate for group booking up to capacity
				$totalPrice = $basePrice;
				$unitPrice  = $totalPrice / $participantCount;
				break;

			case 'tier_pricing':
				$totalPrice = $this->calculateTierPrice( $basePrice, $participantCount, $productId );
				$unitPrice  = $totalPrice / $participantCount;
				break;

			case 'per_person':
			default:
				$totalPrice = $basePrice * $participantCount;
				$unitPrice  = $basePrice;
				break;
		}

		$totalPrice = apply_filters( 'slotnova_group_calculated_total_price', $totalPrice, $basePrice, $participantCount, $productId, $pricingMode );
		$unitPrice  = apply_filters( 'slotnova_group_calculated_unit_price', $unitPrice, $basePrice, $participantCount, $productId, $pricingMode );

		return array(
			'total_price'  => floatval( $totalPrice ),
			'unit_price'   => floatval( $unitPrice ),
			'pricing_mode' => $pricingMode,
		);
	}

	/**
	 * Calculate tier price for participant count.
	 *
	 * @param float $basePrice Base price.
	 * @param int   $count Participant count.
	 * @param int   $productId Product ID.
	 * @return float
	 */
	private function calculateTierPrice( float $basePrice, int $count, int $productId ): float {
		$tiers = get_post_meta( $productId, '_slotnova_group_tier_rates', true );

		if ( empty( $tiers ) || ! is_array( $tiers ) ) {
			// Fallback to global tier settings or per_person
			$tiers = get_option( 'slotnova_group_global_tier_rates', array() );
		}

		if ( empty( $tiers ) || ! is_array( $tiers ) ) {
			return $basePrice * $count;
		}

		// Find matching tier
		foreach ( $tiers as $tier ) {
			$min = isset( $tier['min_qty'] ) ? (int) $tier['min_qty'] : 1;
			$max = isset( $tier['max_qty'] ) && '' !== $tier['max_qty'] ? (int) $tier['max_qty'] : 999999;
			$rate = isset( $tier['price'] ) ? floatval( $tier['price'] ) : $basePrice;

			if ( $count >= $min && $count <= $max ) {
				$type = isset( $tier['type'] ) ? $tier['type'] : 'per_person';
				return 'flat' === $type ? $rate : ( $rate * $count );
			}
		}

		return $basePrice * $count;
	}
}
