<?php
/**
 * Group Booking Helper.
 *
 * @package SlotNova\Extensions\GroupBooking\Helpers
 */

namespace SlotNova\Extensions\GroupBooking\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GroupBookingHelper {

	/**
	 * Get global extension option with fallback default.
	 *
	 * @param string $key Option key suffix.
	 * @param mixed  $default Default value if option is empty.
	 * @return mixed
	 */
	public static function getOption( string $key, $default = '' ) {
		$fullKey = 'slotnova_group_' . $key;
		$val     = get_option( $fullKey, $default );
		return ( false === $val || '' === $val ) ? $default : $val;
	}

	/**
	 * Check if group booking is enabled globally or for a specific product.
	 *
	 * @param int $productId Optional product ID.
	 * @return bool
	 */
	public static function isGroupBookingEnabled( int $productId = 0 ): bool {
		$globalEnabled = 'yes' === self::getOption( 'enabled', 'yes' );
		if ( ! $globalEnabled ) {
			return false;
		}

		if ( $productId > 0 ) {
			$override = get_post_meta( $productId, '_slotnova_group_override', true );
			if ( 'yes' === $override ) {
				$prodEnabled = get_post_meta( $productId, '_slotnova_group_enabled', true );
				return 'yes' === $prodEnabled;
			}
		}

		return true;
	}

	/**
	 * Get custom Group Booking Base Price for a product if configured.
	 *
	 * @param int $productId Product ID.
	 * @return float
	 */
	public static function getGroupBasePrice( int $productId ): float {
		if ( $productId > 0 ) {
			$price = get_post_meta( $productId, '_slotnova_group_price', true );
			if ( '' !== $price && null !== $price && floatval( $price ) > 0 ) {
				return floatval( $price );
			}
		}
		return 0.0;
	}

	/**
	 * Get configured max capacity for a service / product.
	 *
	 * @param int $productId Product ID.
	 * @param int $serviceId Service term ID.
	 * @return int
	 */
	public static function getMaxCapacity( int $productId = 0, int $serviceId = 0 ): int {
		if ( $productId > 0 ) {
			$override = get_post_meta( $productId, '_slotnova_group_override', true );
			if ( 'yes' === $override ) {
				$capacity = get_post_meta( $productId, '_slotnova_group_max_capacity', true );
				if ( '' !== $capacity && null !== $capacity && (int) $capacity > 0 ) {
					return (int) $capacity;
				}
			}
		}

		if ( $serviceId > 0 ) {
			$serviceCap = get_term_meta( $serviceId, 'slotnova_service_capacity', true );
			if ( '' !== $serviceCap && null !== $serviceCap && (int) $serviceCap > 0 ) {
				return (int) $serviceCap;
			}
		}

		return (int) self::getOption( 'default_max_capacity', 20 );
	}

	/**
	 * Get configured min capacity.
	 *
	 * @param int $productId Product ID.
	 * @param int $serviceId Service term ID.
	 * @return int
	 */
	public static function getMinCapacity( int $productId = 0, int $serviceId = 0 ): int {
		if ( $productId > 0 ) {
			$override = get_post_meta( $productId, '_slotnova_group_override', true );
			if ( 'yes' === $override ) {
				$minCap = get_post_meta( $productId, '_slotnova_group_min_capacity', true );
				if ( '' !== $minCap && null !== $minCap && (int) $minCap >= 1 ) {
					return (int) $minCap;
				}
			}
		}

		return (int) self::getOption( 'default_min_capacity', 1 );
	}

	/**
	 * Get pricing mode for product or service.
	 *
	 * @param int $productId Product ID.
	 * @return string ('per_person', 'fixed_group', 'tier_pricing')
	 */
	public static function getPricingMode( int $productId = 0 ): string {
		if ( $productId > 0 ) {
			$override = get_post_meta( $productId, '_slotnova_group_override', true );
			if ( 'yes' === $override ) {
				$mode = get_post_meta( $productId, '_slotnova_group_pricing_mode', true );
				if ( ! empty( $mode ) ) {
					return $mode;
				}
			}
		}

		return self::getOption( 'default_pricing_mode', 'per_person' );
	}

	/**
	 * Get list of configurable participant fields and their required/optional state.
	 *
	 * @param int $productId Optional product ID.
	 * @return array
	 */
	public static function getParticipantFields( int $productId = 0 ): array {
		$defaults = array(
			'name'   => array( 'label' => __( 'Full Name', 'slotnova-booking' ), 'enabled' => true, 'required' => true ),
			'email'  => array( 'label' => __( 'Email Address', 'slotnova-booking' ), 'enabled' => true, 'required' => true ),
			'phone'  => array( 'label' => __( 'Phone Number', 'slotnova-booking' ), 'enabled' => true, 'required' => false ),
			'gender' => array( 'label' => __( 'Gender', 'slotnova-booking' ), 'enabled' => false, 'required' => false ),
			'age'    => array( 'label' => __( 'Age', 'slotnova-booking' ), 'enabled' => false, 'required' => false ),
			'notes'  => array( 'label' => __( 'Special Requests / Notes', 'slotnova-booking' ), 'enabled' => true, 'required' => false ),
		);

		$savedFields = get_option( 'slotnova_group_participant_fields', array() );
		if ( ! is_array( $savedFields ) || empty( $savedFields ) ) {
			return $defaults;
		}

		foreach ( $defaults as $key => $config ) {
			if ( isset( $savedFields[ $key ] ) ) {
				$defaults[ $key ]['enabled']  = ! empty( $savedFields[ $key ]['enabled'] );
				$defaults[ $key ]['required'] = ! empty( $savedFields[ $key ]['required'] );
			}
		}

		return $defaults;
	}

	/**
	 * Sanitize participant input dataset.
	 *
	 * @param array $rawParticipants Raw post data array.
	 * @return array Sanitized array of participant details.
	 */
	public static function sanitizeParticipantsData( array $rawParticipants ): array {
		$sanitized = array();
		foreach ( $rawParticipants as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$name  = isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : '';
			$email = isset( $item['email'] ) ? sanitize_email( $item['email'] ) : '';
			$phone = isset( $item['phone'] ) ? sanitize_text_field( $item['phone'] ) : '';
			$gender = isset( $item['gender'] ) ? sanitize_text_field( $item['gender'] ) : '';
			$age   = isset( $item['age'] ) && '' !== $item['age'] ? absint( $item['age'] ) : null;
			$notes = isset( $item['notes'] ) ? sanitize_textarea_field( $item['notes'] ) : '';

			if ( ! empty( $name ) ) {
				$sanitized[] = array(
					'name'   => $name,
					'email'  => $email,
					'phone'  => $phone,
					'gender' => $gender,
					'age'    => $age,
					'notes'  => $notes,
				);
			}
		}
		return $sanitized;
	}
}
