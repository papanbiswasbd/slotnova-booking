<?php
/**
 * SlotNova Cart Class
 *
 * @package SlotNova\Booking
 * @version 1.0.0
 */

namespace SlotNova\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cart
 *
 * Handles WooCommerce cart validation, double-booking checks, and item meta logic.
 */
class Cart {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_booking_data' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_booking_data_to_cart' ), 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_cart_item_price' ), 10, 1 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_cart_item_price_html' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_booking_data_in_cart' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_booking_data_to_order' ), 10, 4 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_internal_order_item_meta' ) );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'filter_formatted_order_item_meta' ), 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'hide_meta_key_display' ), 10, 3 );
	}

	/**
	 * Validate booking data and check for double-booking before adding to cart.
	 *
	 * @param bool $passed If the item passed validation.
	 * @param int  $product_id The product ID.
	 * @param int  $quantity The quantity.
	 * @return bool
	 */
	public function validate_booking_data( $passed, $product_id, $quantity ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'slotnova' !== $product->get_type() ) {
			return $passed;
		}

		if ( ! isset( $_POST['slotnova_cart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['slotnova_cart_nonce'] ) ), 'slotnova_add_to_cart' ) ) {
			wc_add_notice( __( 'Security check failed. Please try again.', 'slotnova-booking' ), 'error' );
			return false;
		}

		$enable_services = get_post_meta( $product_id, '_slotnova_enable_services', true );
		if ( '' === $enable_services ) {
			$enable_services = 'yes';
		}
		$saved_services = get_post_meta( $product_id, '_slotnova_product_services', true );

		if ( 'yes' === $enable_services && ! empty( $saved_services ) && empty( $_POST['slotnova_service'] ) ) {
			wc_add_notice( __( 'Please select a service for your booking.', 'slotnova-booking' ), 'error' );
			$passed = false;
		}

		$enable_employees = get_post_meta( $product_id, '_slotnova_enable_employees', true );
		if ( '' === $enable_employees ) {
			$enable_employees = 'yes';
		}
		$saved_employees = get_post_meta( $product_id, '_slotnova_product_employees', true );

		if ( 'yes' === $enable_employees && ! empty( $saved_employees ) && empty( $_POST['slotnova_employee'] ) ) {
			wc_add_notice( __( 'Please select an employee for your booking.', 'slotnova-booking' ), 'error' );
			$passed = false;
		}

		if ( empty( $_POST['slotnova_booking_date'] ) ) {
			wc_add_notice( __( 'Please select a date for your booking.', 'slotnova-booking' ), 'error' );
			$passed = false;
		}

		$enable_time_slots = get_post_meta( $product_id, '_slotnova_enable_time_slots', true );
		if ( empty( $enable_time_slots ) || 'global' === $enable_time_slots ) {
			$enable_time_slots = get_option( 'slotnova_enable_time_slots', 'yes' );
		}

		if ( 'yes' === $enable_time_slots && empty( $_POST['slotnova_booking_time'] ) ) {
			wc_add_notice( __( 'Please select a time for your booking.', 'slotnova-booking' ), 'error' );
			$passed = false;
		}

		if ( ! $passed ) {
			return false;
		}

		// Double-Booking Check: Query existing orders for date, time, service & employee conflict
		$requested_date = sanitize_text_field( wp_unslash( $_POST['slotnova_booking_date'] ) );
		$requested_time = isset( $_POST['slotnova_booking_time'] ) ? sanitize_text_field( wp_unslash( $_POST['slotnova_booking_time'] ) ) : '';
		$service_id     = isset( $_POST['slotnova_service'] ) ? intval( $_POST['slotnova_service'] ) : 0;
		$employee_id    = isset( $_POST['slotnova_employee'] ) ? intval( $_POST['slotnova_employee'] ) : 0;

		if ( $this->is_slot_already_booked( $product_id, $requested_date, $requested_time, $service_id, $employee_id ) ) {
			wc_add_notice( __( 'This time slot has already been booked for the selected employee/service. Please choose another date or time.', 'slotnova-booking' ), 'error' );
			return false;
		}

		return apply_filters( 'slotnova_validate_booking_data', $passed, $product_id, $quantity );
	}

	/**
	 * Check if a date and time slot is already booked for a specific employee or service in an active order.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $date Selected date.
	 * @param string $time Selected time.
	 * @param int    $service_id Selected service term ID.
	 * @param int    $employee_id Selected employee term ID.
	 * @return bool
	 */
	private function is_slot_already_booked( $product_id, $date, $time, $service_id = 0, $employee_id = 0 ) {
		$booked_slots = Plugin::instance()->frontend->get_booked_slots_for_date( $product_id, $date, $service_id, $employee_id );
		if ( empty( $booked_slots ) ) {
			return false;
		}

		if ( empty( $time ) ) {
			return true;
		}

		$formatted_time = gmdate( 'h:i A', strtotime( '1970-01-01 ' . $time . ' UTC' ) );
		foreach ( $booked_slots as $bs ) {
			if ( gmdate( 'h:i A', strtotime( '1970-01-01 ' . $bs . ' UTC' ) ) === $formatted_time ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add booking data to cart item.
	 *
	 * @param array $cart_item_data The cart item data.
	 * @param int   $product_id The product ID.
	 * @param int   $variation_id The variation ID.
	 * @return array
	 */
	public function add_booking_data_to_cart( $cart_item_data, $product_id, $variation_id ) {
		if ( isset( $_POST['slotnova_booking_date'] ) ) {

			if ( ! isset( $_POST['slotnova_cart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['slotnova_cart_nonce'] ) ), 'slotnova_add_to_cart' ) ) {
				return $cart_item_data;
			}

			$service_id   = isset( $_POST['slotnova_service'] ) ? intval( $_POST['slotnova_service'] ) : 0;
			$employee_id  = isset( $_POST['slotnova_employee'] ) ? intval( $_POST['slotnova_employee'] ) : 0;

			$service_name = '';
			if ( $service_id > 0 ) {
				$service_term = get_term( $service_id, 'slotnova_service' );
				$service_name = ( $service_term && ! is_wp_error( $service_term ) ) ? $service_term->name : '';
			}

			$employee_name = '';
			if ( $employee_id > 0 ) {
				$employee_term = get_term( $employee_id, 'slotnova_employee' );
				$employee_name = ( $employee_term && ! is_wp_error( $employee_term ) ) ? $employee_term->name : '';
			}

			$booking_date = sanitize_text_field( wp_unslash( $_POST['slotnova_booking_date'] ) );
			$booking_time = isset( $_POST['slotnova_booking_time'] ) ? sanitize_text_field( wp_unslash( $_POST['slotnova_booking_time'] ) ) : '';

			$price             = 0;
			$enable_base_price = get_post_meta( $product_id, '_slotnova_enable_base_price', true );
			if ( 'yes' === $enable_base_price ) {
				$base_price = get_post_meta( $product_id, '_slotnova_base_price', true );
				if ( '' !== $base_price && null !== $base_price && is_numeric( $base_price ) ) {
					$price = floatval( $base_price );
				}
			}

			if ( $price <= 0 ) {
				$saved_services = get_post_meta( $product_id, '_slotnova_product_services', true );
				if ( is_array( $saved_services ) ) {
					foreach ( $saved_services as $saved ) {
						if ( (int) $saved['term_id'] === $service_id ) {
							if ( isset( $saved['price'] ) && '' !== $saved['price'] && null !== $saved['price'] && floatval( $saved['price'] ) > 0 ) {
								$price = floatval( $saved['price'] );
							}
							break;
						}
					}
				}

				if ( $price <= 0 ) {
					$price = floatval( get_term_meta( $service_id, 'slotnova_service_price', true ) );
				}

				if ( $price <= 0 ) {
					$product = wc_get_product( $product_id );
					if ( $product ) {
						$price = floatval( $product->get_price() );
					}
				}
			}

			$booking_meta = array(
				'service_id'    => $service_id,
				'service_name'  => $service_name,
				'employee_id'   => $employee_id,
				'employee_name' => $employee_name,
				'date'          => $booking_date,
				'time'          => $booking_time,
				'price'         => $price,
			);

			$cart_item_data['slotnova_booking'] = apply_filters( 'slotnova_cart_item_booking_data', $booking_meta, $product_id, $variation_id );

			do_action( 'slotnova_before_add_to_cart', $product_id, $cart_item_data['slotnova_booking'] );
		}
		return $cart_item_data;
	}

	/**
	 * Update cart item price based on selected service.
	 *
	 * @param \WC_Cart $cart The WooCommerce cart object.
	 * @return void
	 */
	public function update_cart_item_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['slotnova_booking']['price'] ) && floatval( $cart_item['slotnova_booking']['price'] ) >= 0 ) {
				$price = apply_filters( 'slotnova_cart_item_price', floatval( $cart_item['slotnova_booking']['price'] ), $cart_item );
				$cart_item['data']->set_price( $price );
			}
		}
	}

	/**
	 * Filter cart item price HTML to display selected service price in mini-cart and cart widgets.
	 *
	 * @param string $price_html Existing price HTML.
	 * @param array  $cart_item Cart item data.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function filter_cart_item_price_html( $price_html, $cart_item, $cart_item_key ) {
		if ( isset( $cart_item['slotnova_booking']['price'] ) && floatval( $cart_item['slotnova_booking']['price'] ) >= 0 ) {
			$price = floatval( $cart_item['slotnova_booking']['price'] );
			return wc_price( $price );
		}
		return $price_html;
	}

	/**
	 * Display booking data in the cart.
	 *
	 * @param array $item_data The item data.
	 * @param array $cart_item The cart item.
	 * @return array
	 */
	public function display_booking_data_in_cart( $item_data, $cart_item ) {
		if ( isset( $cart_item['slotnova_booking'] ) ) {
			$booking    = $cart_item['slotnova_booking'];
			$product_id = isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0;

			$service_label = get_post_meta( $product_id, '_slotnova_service_title', true );
			if ( empty( $service_label ) ) {
				$service_label = __( 'Service', 'slotnova-booking' );
			}
			$service_label = rtrim( str_replace( array( 'Select ', 'Choose ' ), '', $service_label ), ':' );

			$employee_label = get_post_meta( $product_id, '_slotnova_employee_title', true );
			if ( empty( $employee_label ) ) {
				$employee_label = __( 'Employee', 'slotnova-booking' );
			}
			$employee_label = rtrim( str_replace( array( 'Select ', 'Choose ' ), '', $employee_label ), ':' );

			if ( ! empty( $booking['service_name'] ) ) {
				$item_data[] = array(
					'key'   => $service_label,
					'value' => $booking['service_name'],
				);
			}

			if ( ! empty( $booking['employee_name'] ) ) {
				$item_data[] = array(
					'key'   => $employee_label,
					'value' => $booking['employee_name'],
				);
			}

			if ( ! empty( $booking['date'] ) ) {
				$item_data[] = array(
					'key'   => __( 'Date', 'slotnova-booking' ),
					'value' => $booking['date'],
				);
			}

			if ( ! empty( $booking['time'] ) ) {
				$enable_time_slots = get_post_meta( $product_id, '_slotnova_enable_time_slots', true );
				if ( empty( $enable_time_slots ) || 'global' === $enable_time_slots ) {
					$enable_time_slots = get_option( 'slotnova_enable_time_slots', 'yes' );
				}
				if ( 'yes' === $enable_time_slots ) {
					$item_data[] = array(
						'key'   => __( 'Time', 'slotnova-booking' ),
						'value' => $booking['time'],
					);
				}
			}
		}
		return apply_filters( 'slotnova_display_item_data_in_cart', $item_data, $cart_item );
	}

	/**
	 * Save booking data to order line item meta.
	 *
	 * @param \WC_Order_Item_Product $item The order item object.
	 * @param string                 $cart_item_key The cart item key.
	 * @param array                  $values The cart item values.
	 * @param \WC_Order              $order The order object.
	 * @return void
	 */
	public function save_booking_data_to_order( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['slotnova_booking'] ) ) {
			$booking    = $values['slotnova_booking'];
			$product_id = isset( $values['product_id'] ) ? $values['product_id'] : 0;

			$service_label = get_post_meta( $product_id, '_slotnova_service_title', true );
			if ( empty( $service_label ) ) {
				$service_label = __( 'Service', 'slotnova-booking' );
			}
			$service_label = rtrim( str_replace( array( 'Select ', 'Choose ' ), '', $service_label ), ':' );

			$employee_label = get_post_meta( $product_id, '_slotnova_employee_title', true );
			if ( empty( $employee_label ) ) {
				$employee_label = __( 'Employee', 'slotnova-booking' );
			}
			$employee_label = rtrim( str_replace( array( 'Select ', 'Choose ' ), '', $employee_label ), ':' );

			if ( ! empty( $booking['service_name'] ) ) {
				$item->add_meta_data( $service_label, $booking['service_name'] );
			}

			if ( ! empty( $booking['service_id'] ) ) {
				$item->add_meta_data( '_slotnova_service_id', intval( $booking['service_id'] ) );
			}

			if ( ! empty( $booking['employee_name'] ) ) {
				$item->add_meta_data( $employee_label, $booking['employee_name'] );
			}

			if ( ! empty( $booking['employee_id'] ) ) {
				$item->add_meta_data( '_slotnova_employee_id', intval( $booking['employee_id'] ) );
			}

			if ( ! empty( $booking['date'] ) ) {
				$item->add_meta_data( __( 'Date', 'slotnova-booking' ), $booking['date'] );
			}

			if ( ! empty( $booking['time'] ) ) {
				$product_id        = $values['product_id'];
				$enable_time_slots = get_post_meta( $product_id, '_slotnova_enable_time_slots', true );
				if ( empty( $enable_time_slots ) || 'global' === $enable_time_slots ) {
					$enable_time_slots = get_option( 'slotnova_enable_time_slots', 'yes' );
				}
				if ( 'yes' === $enable_time_slots ) {
					$item->add_meta_data( __( 'Time', 'slotnova-booking' ), $booking['time'] );
				}
			}

			do_action( 'slotnova_save_booking_data_to_order', $item, $cart_item_key, $values, $order );
		}
	}

	/**
	 * Hide internal SlotNova meta keys from WooCommerce order item meta displays.
	 *
	 * @param array $hidden_meta List of hidden meta keys.
	 * @return array
	 */
	public function hide_internal_order_item_meta( $hidden_meta ) {
		$hidden_meta[] = '_slotnova_service_id';
		$hidden_meta[] = '_slotnova_employee_id';
		return $hidden_meta;
	}

	/**
	 * Filter formatted order item meta data to remove internal service and employee IDs.
	 *
	 * @param array                  $formatted_meta Array of meta objects.
	 * @param \WC_Order_Item_Product $item Order item object.
	 * @return array
	 */
	public function filter_formatted_order_item_meta( $formatted_meta, $item ) {
		foreach ( $formatted_meta as $key => $meta ) {
			if ( isset( $meta->key ) && in_array( $meta->key, array( '_slotnova_service_id', '_slotnova_employee_id' ), true ) ) {
				unset( $formatted_meta[ $key ] );
			}
		}
		return $formatted_meta;
	}

	/**
	 * Hide internal meta keys from order item display.
	 *
	 * @param string $display_key Display key string.
	 * @param object $meta Meta object.
	 * @param object $item Item object.
	 * @return string
	 */
	public function hide_meta_key_display( $display_key, $meta, $item ) {
		if ( isset( $meta->key ) && in_array( $meta->key, array( '_slotnova_service_id', '_slotnova_employee_id' ), true ) ) {
			return '';
		}
		return $display_key;
	}
}
