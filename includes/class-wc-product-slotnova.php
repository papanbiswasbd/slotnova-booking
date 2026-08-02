<?php
/**
 * SlotNova Custom Product Type
 *
 * @package SlotNova\Booking
 * @version 1.0.0
 */

namespace SlotNova\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Product
 *
 * Custom Product Type for SlotNova Bookings.
 */
class WC_Product extends \WC_Product {

	/**
	 * Initialize slotnova product.
	 *
	 * @param mixed $product Product object or ID.
	 */
	public function __construct( $product = 0 ) {
		$this->product_type = 'slotnova';
		parent::__construct( $product );
	}

	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'slotnova';
	}

	/**
	 * Get the add to cart button text.
	 *
	 * @return string
	 */
	public function add_to_cart_text() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		return apply_filters( 'woocommerce_product_add_to_cart_text', __( 'Book Now', 'slotnova-booking' ), $this );
	}

	/**
	 * Get the add to cart button text for single product page.
	 *
	 * @return string
	 */
	public function single_add_to_cart_text() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		return apply_filters( 'woocommerce_product_single_add_to_cart_text', __( 'Book Now', 'slotnova-booking' ), $this );
	}

	/**
	 * Check if product is purchasable.
	 *
	 * @return bool
	 */
	public function is_purchasable() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		return apply_filters( 'woocommerce_is_purchasable', true, $this );
	}

	/**
	 * SlotNova products are virtual service bookings.
	 *
	 * @return bool
	 */
	public function is_virtual() {
		return true;
	}

	/**
	 * Get product price fallback.
	 *
	 * @param string $context Context view or edit.
	 * @return string|float
	 */
	public function get_price( $context = 'view' ) {
		$price = parent::get_price( $context );
		if ( '' === $price || null === $price || false === $price ) {
			$saved_services = get_post_meta( $this->get_id(), '_slotnova_product_services', true );
			if ( is_array( $saved_services ) && ! empty( $saved_services ) ) {
				$prices = array();
				foreach ( $saved_services as $saved ) {
					if ( isset( $saved['price'] ) && '' !== $saved['price'] && null !== $saved['price'] && floatval( $saved['price'] ) > 0 ) {
						$prices[] = floatval( $saved['price'] );
					} elseif ( isset( $saved['term_id'] ) ) {
						$term_price = get_term_meta( $saved['term_id'], 'slotnova_service_price', true );
						if ( '' !== $term_price && false !== $term_price && floatval( $term_price ) > 0 ) {
							$prices[] = floatval( $term_price );
						}
					}
				}
				if ( ! empty( $prices ) ) {
					return min( $prices );
				}
			}
			return '0';
		}
		return $price;
	}

	/**
	 * Get price HTML for SlotNova product (single value or range).
	 *
	 * @param string $price Existing price string.
	 * @return string
	 */
	public function get_price_html( $price = '' ) {
		$price_html = Plugin::instance()->frontend->get_slotnova_product_price_html( $this );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		return apply_filters( 'woocommerce_get_price_html', $price_html, $this );
	}
}

if ( ! class_exists( 'WC_Product_Slotnova' ) ) {
	class_alias( '\\SlotNova\\Booking\\WC_Product', 'WC_Product_Slotnova' );
}

