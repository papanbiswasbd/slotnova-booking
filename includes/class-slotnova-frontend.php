<?php
/**
 * SlotNova Frontend Class
 *
 * @package SlotNova\Booking
 * @version 1.0.0
 */

namespace SlotNova\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Frontend
 *
 * Handles frontend rendering and script enqueuing for the booking system.
 */
class Frontend {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Register custom WooCommerce product class mapping
		add_filter( 'woocommerce_product_class', array( $this, 'woocommerce_product_class' ), 10, 2 );

		// Hook into the native WooCommerce custom product type add-to-cart action.
		add_action( 'woocommerce_slotnova_add_to_cart', array( $this, 'render_booking_form' ) );

		// Display custom price HTML (single price or price range) for SlotNova products
		add_filter( 'woocommerce_get_price_html', array( $this, 'filter_product_price_html' ), 10, 2 );

		// AJAX endpoint for fetching booked time slots & fully booked dates
		add_action( 'wp_ajax_slotnova_get_booked_slots', array( $this, 'ajax_get_booked_slots' ) );
		add_action( 'wp_ajax_nopriv_slotnova_get_booked_slots', array( $this, 'ajax_get_booked_slots' ) );
		add_action( 'wp_ajax_slotnova_get_fully_booked_dates', array( $this, 'ajax_get_fully_booked_dates' ) );
		add_action( 'wp_ajax_nopriv_slotnova_get_fully_booked_dates', array( $this, 'ajax_get_fully_booked_dates' ) );

		// WooCommerce My Account Orders Table Column (Row by Row under Order ID)
		add_action( 'woocommerce_my_account_my_orders_column_order-number', array( $this, 'render_account_order_number_column' ) );
	}

	/**
	 * Map slotnova product type to our custom class.
	 *
	 * @param string $classname Current class name.
	 * @param string $product_type Product type slug.
	 * @return string
	 */
	public function woocommerce_product_class( $classname, $product_type ) {
		if ( 'slotnova' === $product_type ) {
			$classname = '\\SlotNova\\Booking\\WC_Product';
		}
		return $classname;
	}

	/**
	 * Filter price HTML for SlotNova products to display service price, group booking price, or range.
	 *
	 * @param string $price The price HTML.
	 * @param \WC_Product $product The product object.
	 * @return string
	 */
	public function filter_product_price_html( $price, $product ) {
		if ( $product && 'slotnova' === $product->get_type() ) {
			return $this->get_slotnova_product_price_html( $product );
		}
		return $price;
	}

	/**
	 * Calculate and format SlotNova product price HTML (single price or price range).
	 *
	 * Supports both filter invocation (2 params: $price_html, $product) and direct call (1 param: $product).
	 *
	 * @param mixed $param1 HTML string or WC_Product object.
	 * @param \WC_Product|null $param2 WC_Product object or null.
	 * @return string
	 */
	public function get_slotnova_product_price_html( $param1 = '', $param2 = null ) {
		if ( $param1 instanceof \WC_Product ) {
			$product    = $param1;
			$price_html = '';
		} elseif ( $param2 instanceof \WC_Product ) {
			$product    = $param2;
			$price_html = (string) $param1;
		} else {
			return is_string( $param1 ) ? $param1 : '';
		}

		if ( ! $product || 'slotnova' !== $product->get_type() ) {
			return $price_html;
		}

		$product_id        = $product->get_id();
		$enable_base_price = get_post_meta( $product_id, '_slotnova_enable_base_price', true );
		if ( 'yes' === $enable_base_price ) {
			$base_price = get_post_meta( $product_id, '_slotnova_base_price', true );
			if ( '' !== $base_price && null !== $base_price && is_numeric( $base_price ) ) {
				$global_price = floatval( $base_price );
				return ( $global_price > 0 ) ? wc_price( $global_price ) : __( 'Free', 'slotnova-booking' );
			}
		}

		$saved_services = get_post_meta( $product_id, '_slotnova_product_services', true );
		$prices         = array();

		if ( is_array( $saved_services ) && ! empty( $saved_services ) ) {
			foreach ( $saved_services as $saved ) {
				$svc_price = -1;
				if ( isset( $saved['price'] ) && '' !== $saved['price'] && null !== $saved['price'] && floatval( $saved['price'] ) > 0 ) {
					$svc_price = floatval( $saved['price'] );
				}
				if ( $svc_price < 0 && isset( $saved['term_id'] ) ) {
					$term_price = get_term_meta( $saved['term_id'], 'slotnova_service_price', true );
					if ( '' !== $term_price && false !== $term_price ) {
						$svc_price = floatval( $term_price );
					}
				}
				if ( $svc_price < 0 ) {
					$svc_price = function_exists( 'slotnova_get_product_base_price' ) ? slotnova_get_product_base_price( $product ) : floatval( $product->get_price() );
				}
				if ( $svc_price >= 0 ) {
					$prices[] = $svc_price;
				}
			}

			if ( ! empty( $prices ) ) {
				$unique_prices = array_unique( $prices );

				// Single price if all services have the exact same price or only 1 service exists
				if ( count( $unique_prices ) === 1 ) {
					$single_price = reset( $unique_prices );
					return ( $single_price > 0 ) ? wc_price( $single_price ) : __( 'Free', 'slotnova-booking' );
				}

				// Price range if multiple services have different prices
				$min_price = min( $prices );
				$max_price = max( $prices );

				return wc_format_price_range( $min_price, $max_price );
			}
		}

		// NO services configured: Fallback to Group Booking Price if set, otherwise base product price
		$group_price = get_post_meta( $product_id, '_slotnova_group_price', true );
		if ( '' !== $group_price && null !== $group_price && floatval( $group_price ) > 0 ) {
			return wc_price( floatval( $group_price ) );
		}

		$base_price = function_exists( 'slotnova_get_product_base_price' ) ? slotnova_get_product_base_price( $product ) : floatval( $product->get_price() );
		return ( $base_price > 0 ) ? wc_price( $base_price ) : __( 'Free', 'slotnova-booking' );
	}

	/**
	 * AJAX endpoint for fetching booked time slots.
	 *
	 * @return void
	 */
	public function ajax_get_booked_slots() {
		if ( ! check_ajax_referer( 'slotnova_frontend_nonce', 'nonce', false ) && ! check_ajax_referer( 'slotnova_admin_nonce', 'nonce', false ) && ! check_ajax_referer( 'slotnova_admin_nonce', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security nonce.' ) );
		}

		$product_id  = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$raw_date    = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$service_id  = isset( $_POST['service_id'] ) ? intval( $_POST['service_id'] ) : 0;
		$employee_id = isset( $_POST['employee_id'] ) ? intval( $_POST['employee_id'] ) : 0;

		$target_date = slotnova_parse_date( $raw_date );
		if ( ! $target_date ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
		}

		$booked_slots = $this->get_booked_slots_for_date( $product_id, $target_date, $service_id, $employee_id );

		wp_send_json_success( array(
			'date'         => $target_date,
			'booked_slots' => $booked_slots,
		) );
	}

	/**
	 * Query booked time slots for a given product, date, service, and employee.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $date Date string.
	 * @param int    $service_id Service term ID.
	 * @param int    $employee_id Employee term ID.
	 * @return array
	 */
	public function get_booked_slots_for_date( $product_id, $date, $service_id = 0, $employee_id = 0 ) {
		$target_date = slotnova_parse_date( $date );
		if ( ! $target_date ) {
			return array();
		}

		$booked_slots = array();

		// Check active orders
		$args = array(
			'status' => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending' ),
			'limit'  => -1,
		);

		$orders = wc_get_orders( $args );
		foreach ( $orders as $order ) {
			if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
				continue;
			}

			$st = strtolower( (string) $order->get_status() );
			// Explicitly skip trashed, cancelled, refunded, failed, or draft orders
			if ( in_array( $st, array( 'cancelled', 'refunded', 'failed', 'trash', 'draft', 'wc-cancelled', 'wc-refunded', 'wc-failed', 'wc-trash' ), true ) ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				$item_prod_id = (int) $item->get_product_id();
				if ( $product_id > 0 && $item_prod_id > 0 && $item_prod_id !== (int) $product_id ) {
					continue;
				}

				$item_raw_date = $item->get_meta( 'Date' );
				$item_raw_time = $item->get_meta( 'Time' );

				$item_date = slotnova_parse_date( $item_raw_date );
				$item_time = slotnova_parse_time( $item_raw_time );

				if ( ! $item_date || ! $item_time || $item_date !== $target_date ) {
					continue;
				}

				$item_service_id  = (int) $item->get_meta( '_slotnova_service_id' );
				$item_employee_id = (int) $item->get_meta( '_slotnova_employee_id' );
				$item_service     = trim( (string) $item->get_meta( 'Service' ) );
				$item_employee    = trim( (string) $item->get_meta( 'Employee' ) );

				$is_match = false;

				// Employee matching: If an employee is selected, check if this order is for that employee
				if ( $employee_id > 0 ) {
					if ( $item_employee_id > 0 && $item_employee_id === $employee_id ) {
						$is_match = true;
					} elseif ( '' !== $item_employee ) {
						$emp_term = get_term( $employee_id, 'slotnova_employee' );
						if ( $emp_term && ! is_wp_error( $emp_term ) && strtolower( $emp_term->name ) === strtolower( $item_employee ) ) {
							$is_match = true;
						}
					} else {
						// Order item has no employee assigned; check if it matches service
						if ( $service_id > 0 ) {
							if ( $item_service_id > 0 && $item_service_id === $service_id ) {
								$is_match = true;
							} elseif ( '' !== $item_service ) {
								$svc_term = get_term( $service_id, 'slotnova_service' );
								if ( $svc_term && ! is_wp_error( $svc_term ) && strtolower( $svc_term->name ) === strtolower( $item_service ) ) {
									$is_match = true;
								}
							} else {
								$is_match = true;
							}
						} else {
							$is_match = true;
						}
					}
				} elseif ( $service_id > 0 ) {
					// Service matching: If no employee selected, check if order is for this service
					if ( $item_service_id > 0 && $item_service_id === $service_id ) {
						$is_match = true;
					} elseif ( '' !== $item_service ) {
						$svc_term = get_term( $service_id, 'slotnova_service' );
						if ( $svc_term && ! is_wp_error( $svc_term ) && strtolower( $svc_term->name ) === strtolower( $item_service ) ) {
							$is_match = true;
						}
					} else {
						$is_match = true;
					}
				} else {
					$is_match = true;
				}

				if ( $is_match && ! in_array( $item_time, $booked_slots, true ) ) {
					$booked_slots[] = $item_time;
				}
			}
		}

		// Check active WC cart session
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$cart_prod_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
				if ( $product_id > 0 && $cart_prod_id > 0 && $cart_prod_id !== (int) $product_id ) {
					continue;
				}

				$cart_raw_date = isset( $cart_item['slotnova_booking']['date'] ) ? $cart_item['slotnova_booking']['date'] : '';
				$cart_raw_time = isset( $cart_item['slotnova_booking']['time'] ) ? $cart_item['slotnova_booking']['time'] : '';

				$cart_date = slotnova_parse_date( $cart_raw_date );
				$cart_time = slotnova_parse_time( $cart_raw_time );

				if ( ! $cart_date || ! $cart_time || $cart_date !== $target_date ) {
					continue;
				}

				$cart_svc = isset( $cart_item['slotnova_booking']['service_id'] ) ? (int) $cart_item['slotnova_booking']['service_id'] : 0;
				$cart_emp = isset( $cart_item['slotnova_booking']['employee_id'] ) ? (int) $cart_item['slotnova_booking']['employee_id'] : 0;

				$is_match = false;

				if ( $employee_id > 0 ) {
					if ( $cart_emp === $employee_id || 0 === $cart_emp ) {
						$is_match = true;
					}
				} elseif ( $service_id > 0 ) {
					if ( $cart_svc === $service_id || 0 === $cart_svc ) {
						$is_match = true;
					}
				} else {
					$is_match = true;
				}

				if ( $is_match && ! in_array( $cart_time, $booked_slots, true ) ) {
					$booked_slots[] = $cart_time;
				}
			}
		}

		return apply_filters( 'slotnova_get_booked_slots', $booked_slots, $product_id, $target_date, $service_id, $employee_id );
	}

	/**
	 * Get list of dates that are fully booked for a product (whole day or all slots reserved).
	 *
	 * @param int $product_id Product ID.
	 * @param int $service_id Selected service term ID.
	 * @param int $employee_id Selected employee term ID.
	 * @return array
	 */
	public function get_fully_booked_dates( $product_id, $service_id = 0, $employee_id = 0 ) {
		$enable_time_slots = get_post_meta( $product_id, '_slotnova_enable_time_slots', true );
		if ( empty( $enable_time_slots ) || 'global' === $enable_time_slots ) {
			$enable_time_slots = get_option( 'slotnova_enable_time_slots', 'yes' );
		}

		$default_statuses = array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-partial-deposit', 'partial-deposit' );
		$statuses         = apply_filters( 'slotnova_bookings_query_statuses', $default_statuses );
		$orders           = wc_get_orders( array( 'status' => $statuses, 'limit' => -1 ) );

		$date_counts  = array();
		$fully_booked = array();

		$total_slots_per_day = 1;
		if ( 'yes' === $enable_time_slots ) {
			$opening_time = get_post_meta( $product_id, '_slotnova_opening_time', true );
			$closing_time = get_post_meta( $product_id, '_slotnova_closing_time', true );
			$duration     = get_post_meta( $product_id, '_slotnova_slot_duration', true );
			$all_slots    = function_exists( 'slotnova_generate_time_slots' ) ? slotnova_generate_time_slots( $opening_time, $closing_time, $duration ) : array();
			$total_slots_per_day = count( $all_slots );
			if ( $total_slots_per_day <= 0 ) {
				$total_slots_per_day = 9;
			}
		}

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$item_prod_id = $item->get_product_id();
				if ( $product_id > 0 && $item_prod_id > 0 && $item_prod_id !== (int) $product_id ) {
					continue;
				}

				$booking_date = $item->get_meta( 'Date' );
				if ( empty( $booking_date ) ) {
					continue;
				}
				$parsed_date = function_exists( 'slotnova_parse_date' ) ? slotnova_parse_date( $booking_date ) : $booking_date;
				if ( ! $parsed_date ) {
					continue;
				}

				$item_emp_id = (int) $item->get_meta( '_slotnova_employee_id' );
				$item_svc_id = (int) $item->get_meta( '_slotnova_service_id' );

				$is_match = true;
				if ( $employee_id > 0 && $item_emp_id > 0 && $item_emp_id !== $employee_id ) {
					$is_match = false;
				}
				if ( $service_id > 0 && $item_svc_id > 0 && $item_svc_id !== $service_id ) {
					$is_match = false;
				}

				if ( $is_match ) {
					if ( ! isset( $date_counts[ $parsed_date ] ) ) {
						$date_counts[ $parsed_date ] = 0;
					}
					$date_counts[ $parsed_date ]++;
				}
			}
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$cart_prod_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
				if ( $product_id > 0 && $cart_prod_id > 0 && $cart_prod_id !== (int) $product_id ) {
					continue;
				}
				$cart_raw_date = isset( $cart_item['slotnova_booking']['date'] ) ? $cart_item['slotnova_booking']['date'] : '';
				$cart_date     = function_exists( 'slotnova_parse_date' ) ? slotnova_parse_date( $cart_raw_date ) : $cart_raw_date;
				if ( $cart_date ) {
					if ( ! isset( $date_counts[ $cart_date ] ) ) {
						$date_counts[ $cart_date ] = 0;
					}
					$date_counts[ $cart_date ]++;
				}
			}
		}

		foreach ( $date_counts as $date => $count ) {
			if ( $count >= $total_slots_per_day ) {
				$fully_booked[] = $date;
			}
		}

		return array_values( array_unique( $fully_booked ) );
	}

	/**
	 * AJAX endpoint for fetching fully booked dates.
	 *
	 * @return void
	 */
	public function ajax_get_fully_booked_dates() {
		check_ajax_referer( 'slotnova_cart_nonce', 'nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$product_id  = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$service_id  = isset( $_POST['service_id'] ) ? intval( $_POST['service_id'] ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$employee_id = isset( $_POST['employee_id'] ) ? intval( $_POST['employee_id'] ) : 0;

		$fully_booked = $this->get_fully_booked_dates( $product_id, $service_id, $employee_id );

		wp_send_json_success( array( 'fully_booked_dates' => $fully_booked ) );
	}

	/**
	 * Enqueue scripts and styles for the frontend single product page.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! is_product() ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product || 'slotnova' !== $product->get_type() ) {
			return;
		}

		wp_enqueue_style( 'slotnova-flatpickr-css', SLOTNOVA_BOOKING_URL . 'assets/css/flatpickr-light.css', array(), '4.6.13' );
		wp_enqueue_script( 'slotnova-flatpickr-js', SLOTNOVA_BOOKING_URL . 'assets/js/flatpickr.min.js', array(), '4.6.13', true );

		wp_enqueue_style( 'slotnova-frontend-css', SLOTNOVA_BOOKING_URL . 'assets/css/slotnova-frontend.css', array( 'slotnova-flatpickr-css' ), SLOTNOVA_BOOKING_VERSION );
		wp_enqueue_script( 'slotnova-frontend-js', SLOTNOVA_BOOKING_URL . 'assets/js/slotnova-frontend.js', array( 'slotnova-flatpickr-js' ), SLOTNOVA_BOOKING_VERSION, true );

		$primary_color = get_option( 'slotnova_primary_color', '#2271b1' );
		$accent_color  = get_option( 'slotnova_accent_color', '#135e96' );
		$bg_color      = get_option( 'slotnova_bg_color', '#ffffff' );
		$text_color    = get_option( 'slotnova_text_color', '#0f172a' );
		$border_radius = get_option( 'slotnova_border_radius', '12px' );

		if ( empty( $primary_color ) ) { $primary_color = '#2271b1'; }
		if ( empty( $accent_color ) ) { $accent_color = '#135e96'; }
		if ( empty( $bg_color ) ) { $bg_color = '#ffffff'; }
		if ( empty( $text_color ) ) { $text_color = '#0f172a'; }
		if ( empty( $border_radius ) ) { $border_radius = '12px'; }

		$custom_css = "
			.slotnova-custom-select .slotnova-select-options {
				background: {$bg_color} !important;
				border-radius: {$border_radius} !important;
			}
			.slotnova-option-name {
				color: {$text_color} !important;
			}
			.slotnova-option-price {
				color: {$primary_color} !important;
			}
			.slotnova-time-pill.active {
				background: {$primary_color} !important;
				border-color: {$primary_color} !important;
				color: #ffffff !important;
			}
			.flatpickr-day.selected, .flatpickr-day.selected:hover,
			.flatpickr-day.startRange, .flatpickr-day.endRange {
				background: {$primary_color} !important;
				border-color: {$primary_color} !important;
				color: #ffffff !important;
			}
			.slotnova-summary-box {
				background: {$bg_color} !important;
				border-radius: {$border_radius} !important;
			}
			.slotnova-summary-title, .slotnova-summary-total .slotnova-summary-label {
				color: {$text_color} !important;
			}
			.slotnova-summary-total .slotnova-summary-value {
				color: {$primary_color} !important;
			}
		";
		wp_add_inline_style( 'slotnova-frontend-css', $custom_css );

		$params = apply_filters( 'slotnova_frontend_localized_params', array(
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'slotnova_frontend_nonce' ),
			'currency_symbol'   => get_woocommerce_currency_symbol(),
			'free_text'         => __( 'Free', 'slotnova-booking' ),
			'choose_time_text'  => __( 'Choose a time...', 'slotnova-booking' ),
			'booked_text'       => __( 'Booked', 'slotnova-booking' ),
			'passed_text'       => __( 'Time Passed', 'slotnova-booking' ),
			'booked_hint'       => __( 'This time slot is already booked. Please try selecting a different date, employee, or service.', 'slotnova-booking' ),
			'passed_hint'       => __( 'This time slot has already passed for today. Please select another date or time.', 'slotnova-booking' ),
			'site_current_date'  => wp_date( 'Y-m-d' ),
			'site_current_time'  => wp_date( 'H:i' ),
			'calendar_mode'      => get_option( 'slotnova_calendar_mode', 'inline' ),
			'time_picker_style'  => get_option( 'slotnova_time_picker_style', 'pills' ),
			'disable_past_slots' => apply_filters( 'slotnova_disable_past_slots', false ),
			'i18n'              => array(
				'select_service'  => __( 'Please select a service before booking.', 'slotnova-booking' ),
				'select_employee' => __( 'Please select an employee before booking.', 'slotnova-booking' ),
				'select_date'     => __( 'Please select a date before booking.', 'slotnova-booking' ),
				'select_time'     => __( 'Please select a time before booking.', 'slotnova-booking' ),
			),
		), $post );

		wp_localize_script( 'slotnova-frontend-js', 'slotnova_params', $params );
	}

	/**
	 * Render the custom booking form fields (Services, Employees, Dates, Time slots).
	 *
	 * @return void
	 */
	public function render_booking_form() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		global $product;

		if ( ! $product ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			$product = wc_get_product();
		}

		if ( ! $product || 'slotnova' !== $product->get_type() ) {
			return;
		}

		$product_id     = $product->get_id();
		$saved_services = get_post_meta( $product_id, '_slotnova_product_services', true );
		if ( ! is_array( $saved_services ) ) {
			$saved_services = array();
		}

		$saved_employees = get_post_meta( $product_id, '_slotnova_product_employees', true );
		if ( ! is_array( $saved_employees ) ) {
			$saved_employees = array();
		}

		$enable_services = get_post_meta( $product_id, '_slotnova_enable_services', true );
		if ( '' === $enable_services ) {
			$enable_services = 'yes';
		}

		$enable_employees = get_post_meta( $product_id, '_slotnova_enable_employees', true );
		if ( '' === $enable_employees ) {
			$enable_employees = 'yes';
		}

		$service_label = get_post_meta( $product_id, '_slotnova_service_title', true );
		if ( empty( $service_label ) ) {
			$service_label = __( 'Select Service', 'slotnova-booking' );
		}

		$employee_label = get_post_meta( $product_id, '_slotnova_employee_title', true );
		if ( empty( $employee_label ) ) {
			$employee_label = __( 'Select Employee', 'slotnova-booking' );
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$action_url = apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() );
		?>
		<form class="cart slotnova-form" data-product-id="<?php echo esc_attr( $product_id ); ?>" action="<?php echo esc_url( $action_url ); ?>" method="post" enctype='multipart/form-data'>

			<?php wp_nonce_field( 'slotnova_add_to_cart', 'slotnova_cart_nonce' ); ?>

			<?php do_action( 'slotnova_before_booking_form', $product, $saved_services, $saved_employees ); ?>

			<?php if ( 'yes' === $enable_services && ! empty( $saved_services ) ) : ?>
			<?php do_action( 'slotnova_before_service_select', $product, $saved_services ); ?>
			<div class="form-row form-row-wide slotnova-custom-select-wrapper">
				<label for="slotnova_service"><?php echo esc_html( $service_label ); ?></label>
				<div class="slotnova-custom-select" id="slotnova_service_dropdown">
					<div class="slotnova-select-trigger">
						<?php
						$service_clean_label = trim( str_replace( array( 'Select ', 'Choose ' ), '', $service_label ) );
						/* translators: %s: Service selection field label */
						$service_placeholder = sprintf( __( 'Choose %s...', 'slotnova-booking' ), $service_clean_label );
						?>
						<input type="text" class="slotnova-select-search-input" placeholder="<?php echo esc_attr( $service_placeholder ); ?>" autocomplete="off" />
						<div class="slotnova-select-arrow"></div>
					</div>
					<div class="slotnova-select-options">
						<div class="slotnova-select-options-list">
							<?php foreach ( $saved_services as $saved ) :
								$service = get_term( $saved['term_id'], 'slotnova_service' );
								if ( ! $service || is_wp_error( $service ) ) {
									continue;
								}

								$enable_base_price = get_post_meta( $product_id, '_slotnova_enable_base_price', true );
								$global_base_val   = -1;
								if ( 'yes' === $enable_base_price ) {
									$base_price = get_post_meta( $product_id, '_slotnova_base_price', true );
									if ( '' !== $base_price && null !== $base_price && is_numeric( $base_price ) ) {
										$global_base_val = floatval( $base_price );
									}
								}

								if ( $global_base_val >= 0 ) {
									$price = $global_base_val;
								} else {
									$price = 0;
									if ( isset( $saved['price'] ) && '' !== $saved['price'] && floatval( $saved['price'] ) > 0 ) {
										$price = floatval( $saved['price'] );
									}
									if ( $price <= 0 ) {
										$price = floatval( get_term_meta( $service->term_id, 'slotnova_service_price', true ) );
									}
									if ( $price <= 0 && $product ) {
										$price = floatval( $product->get_price() );
									}
								}

								$price_val     = $price;
								$price_display = ( $price_val > 0 ) ? wc_price( $price_val ) : __( 'Free', 'slotnova-booking' );
								$image_id      = get_term_meta( $service->term_id, 'slotnova_image_id', true );
								$image_url     = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
								?>
								<div class="slotnova-select-option" data-value="<?php echo esc_attr( $service->term_id ); ?>" data-price="<?php echo esc_attr( $price_val ); ?>" data-name="<?php echo esc_attr( $service->name ); ?>">
									<?php if ( $image_url ) : ?>
										<img src="<?php echo esc_url( $image_url ); ?>" alt="" class="slotnova-option-img">
									<?php else : ?>
										<div class="slotnova-option-img-placeholder"></div>
									<?php endif; ?>
									<div class="slotnova-option-details">
										<span class="slotnova-option-name"><?php echo esc_html( $service->name ); ?></span>
										<span class="slotnova-option-price"><?php echo esc_html( wp_strip_all_tags( $price_display ) ); ?></span>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
						<div class="slotnova-select-no-results slotnova-is-hidden"><?php esc_html_e( 'No results found', 'slotnova-booking' ); ?></div>
					</div>
				</div>
				<input type="hidden" name="slotnova_service" id="slotnova_service" required>
			</div>
			<?php endif; ?>

			<?php if ( 'yes' === $enable_employees && ! empty( $saved_employees ) ) : ?>
			<?php do_action( 'slotnova_before_employee_select', $product, $saved_employees ); ?>
			<div class="form-row form-row-wide slotnova-custom-select-wrapper">
				<label for="slotnova_employee"><?php echo esc_html( $employee_label ); ?></label>
				<div class="slotnova-custom-select" id="slotnova_employee_dropdown">
					<div class="slotnova-select-trigger">
						<?php
						$employee_clean_label = trim( str_replace( array( 'Select ', 'Choose ' ), '', $employee_label ) );
						/* translators: %s: Employee selection field label */
						$employee_placeholder = sprintf( __( 'Choose %s...', 'slotnova-booking' ), $employee_clean_label );
						?>
						<input type="text" class="slotnova-select-search-input" placeholder="<?php echo esc_attr( $employee_placeholder ); ?>" autocomplete="off" />
						<div class="slotnova-select-arrow"></div>
					</div>
					<div class="slotnova-select-options">
						<div class="slotnova-select-options-list">
							<?php foreach ( $saved_employees as $saved ) :
								$employee = get_term( $saved['term_id'], 'slotnova_employee' );
								if ( ! $employee || is_wp_error( $employee ) ) {
									continue;
								}

								$image_id  = get_term_meta( $employee->term_id, 'slotnova_image_id', true );
								$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

								$emp_desc = term_description( $employee->term_id );
								if ( empty( $emp_desc ) && ! empty( $employee->description ) ) {
									$emp_desc = $employee->description;
								}
								$emp_desc = trim( wp_strip_all_tags( $emp_desc ) );
								?>
								<div class="slotnova-select-option" data-value="<?php echo esc_attr( $employee->term_id ); ?>" data-name="<?php echo esc_attr( $employee->name ); ?>">
									<?php if ( $image_url ) : ?>
										<img src="<?php echo esc_url( $image_url ); ?>" alt="" class="slotnova-option-img">
									<?php else : ?>
										<div class="slotnova-option-img-placeholder"></div>
									<?php endif; ?>
									<div class="slotnova-option-details">
										<span class="slotnova-option-name"><?php echo esc_html( $employee->name ); ?></span>
										<?php if ( ! empty( $emp_desc ) ) : ?>
											<span class="slotnova-option-description"><?php echo esc_html( $emp_desc ); ?></span>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
						<div class="slotnova-select-no-results slotnova-is-hidden"><?php esc_html_e( 'No results found', 'slotnova-booking' ); ?></div>
					</div>
				</div>
				<input type="hidden" name="slotnova_employee" id="slotnova_employee" required>
			</div>
			<?php do_action( 'slotnova_after_employee_select', $product, $saved_employees ); ?>
			<?php endif; ?>

			<?php
			$weekly_off = get_post_meta( $product_id, '_slotnova_weekly_off_days', true );
			if ( empty( $weekly_off ) || ! is_array( $weekly_off ) ) {
				$weekly_off = get_option( 'slotnova_weekly_off_days', array() );
				if ( ! is_array( $weekly_off ) ) {
					$weekly_off = array();
				}
			}
			$specific_off = get_post_meta( $product_id, '_slotnova_specific_off_days', true );
			if ( empty( $specific_off ) || ! is_array( $specific_off ) ) {
				$specific_off = get_option( 'slotnova_specific_off_days', array() );
				if ( ! is_array( $specific_off ) ) {
					$specific_off = array();
				}
			}
			$all_off_days = array_merge( $weekly_off, $specific_off );
			$off_days_str = implode( ',', $all_off_days );

			$closing_time = get_post_meta( $product_id, '_slotnova_closing_time', true );
			if ( empty( $closing_time ) ) {
				$closing_time = get_option( 'slotnova_closing_time', '' );
			}
			$calendar_mode = get_option( 'slotnova_calendar_mode', 'inline' );

			$enable_time_slots = get_post_meta( $product_id, '_slotnova_enable_time_slots', true );
			if ( empty( $enable_time_slots ) || 'global' === $enable_time_slots ) {
				$enable_time_slots = get_option( 'slotnova_enable_time_slots', 'yes' );
			}

			$fully_booked_dates = $this->get_fully_booked_dates( $product_id );
			$fully_booked_json  = wp_json_encode( $fully_booked_dates );
			?>

			<div class="form-row form-row-wide slotnova-date-wrapper" data-calendar-mode="<?php echo esc_attr( $calendar_mode ); ?>">
				<label for="slotnova_booking_date"><?php esc_html_e( 'Select Date', 'slotnova-booking' ); ?></label>
				<?php if ( 'popup' === $calendar_mode ) : ?>
					<div class="slotnova-date-input-container">
						<input type="text" id="slotnova_booking_date" name="slotnova_booking_date" class="slotnova-date-picker slotnova-date-picker-popup" placeholder="<?php esc_attr_e( 'Click to select date...', 'slotnova-booking' ); ?>" data-off-days="<?php echo esc_attr( $off_days_str ); ?>" data-closing-time="<?php echo esc_attr( $closing_time ); ?>" data-enable-time-slots="<?php echo esc_attr( $enable_time_slots ); ?>" data-booked-dates="<?php echo esc_attr( $fully_booked_json ); ?>" required readonly>
						<span class="slotnova-date-picker-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
						</span>
					</div>
				<?php else : ?>
					<input type="text" id="slotnova_booking_date" name="slotnova_booking_date" class="slotnova-date-picker" placeholder="<?php esc_attr_e( 'Select Date', 'slotnova-booking' ); ?>" data-off-days="<?php echo esc_attr( $off_days_str ); ?>" data-closing-time="<?php echo esc_attr( $closing_time ); ?>" data-enable-time-slots="<?php echo esc_attr( $enable_time_slots ); ?>" data-booked-dates="<?php echo esc_attr( $fully_booked_json ); ?>" required readonly style="display:none;">
				<?php endif; ?>
			</div>

			<?php if ( 'yes' === $enable_time_slots ) : ?>
			<div class="form-row form-row-wide slotnova-time-slots-wrapper slotnova-is-hidden" style="display: none;" data-time-picker-style="pills">
				<label for="slotnova_booking_time_trigger"><?php esc_html_e( 'Select Time', 'slotnova-booking' ); ?></label>
				<div class="slotnova-time-slots-container">
					<?php
					$product_duration = get_post_meta( $product_id, '_slotnova_slot_duration', true );
					if ( empty( $product_duration ) || ! is_numeric( $product_duration ) ) {
						$product_duration = (int) get_option( 'slotnova_slot_duration', 60 );
					}

					$opening_time = get_post_meta( $product_id, '_slotnova_opening_time', true );
					if ( empty( $opening_time ) ) {
						$opening_time = get_option( 'slotnova_opening_time', '09:00 AM' );
					}
					$closing_time = get_post_meta( $product_id, '_slotnova_closing_time', true );
					if ( empty( $closing_time ) ) {
						$closing_time = get_option( 'slotnova_closing_time', '05:00 PM' );
					}

					$product_time_slots = get_post_meta( $product_id, '_slotnova_product_time_slots', true );
					if ( empty( $product_time_slots ) || ! is_array( $product_time_slots ) ) {
						if ( function_exists( 'slotnova_generate_time_slots' ) ) {
							$product_time_slots = slotnova_generate_time_slots( $opening_time, $closing_time, $product_duration );
						}
					}
					if ( empty( $product_time_slots ) || ! is_array( $product_time_slots ) ) {
						$product_time_slots = get_option( 'slotnova_time_slots', array( '09:00 AM', '10:00 AM', '11:00 AM', '12:00 PM', '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM' ) );
					}
					?>
					<?php if ( ! empty( $product_time_slots ) ) : ?>
						<div id="slotnova_time_pills" class="slotnova-time-pills-grid">
							<?php foreach ( $product_time_slots as $slot ) :
								$slot_formatted = function_exists( 'slotnova_format_time' ) ? slotnova_format_time( $slot, $product_duration ) : $slot;
								?>
								<button type="button" class="slotnova-time-pill button" data-value="<?php echo esc_attr( $slot_formatted ); ?>">
									<?php echo esc_html( $slot_formatted ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="slotnova-select-date-prompt"><?php esc_html_e( 'No time slots available for this booking.', 'slotnova-booking' ); ?></p>
					<?php endif; ?>
				</div>
				<input type="hidden" id="slotnova_booking_time" name="slotnova_booking_time" required>
			</div>
			<?php else : ?>
				<input type="hidden" id="slotnova_booking_time" name="slotnova_booking_time" value="All Day">
			<?php endif; ?>

			<?php do_action( 'slotnova_after_time_slots', $product ); ?>

			<div id="slotnova-summary" class="slotnova-summary-box slotnova-is-hidden" style="display: none;">
				<div class="slotnova-summary-header">
					<div class="slotnova-summary-icon">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
					</div>
					<h4 class="slotnova-summary-title"><?php esc_html_e( 'Booking Summary', 'slotnova-booking' ); ?></h4>
				</div>
				<div class="slotnova-summary-body">
					<?php
					$default_base_price    = function_exists( 'slotnova_get_product_base_price' ) ? slotnova_get_product_base_price( $product ) : floatval( $product->get_price() );
					$default_price_display = ( $default_base_price > 0 ) ? wc_price( $default_base_price ) : '-';
					?>

					<?php if ( 'yes' === $enable_services && ! empty( $saved_services ) ) : ?>
					<div class="slotnova-summary-row" id="summary-service-row">
						<span class="slotnova-summary-label"><?php echo esc_html( rtrim( str_replace( array( 'Select ', 'Choose ' ), '', $service_label ), ':' ) . ':' ); ?></span>
						<span class="slotnova-summary-value" id="summary-service-name">-</span>
					</div>
					<?php endif; ?>

					<?php if ( 'yes' === $enable_employees && ! empty( $saved_employees ) ) : ?>
					<div class="slotnova-summary-row" id="summary-employee-row">
						<span class="slotnova-summary-label"><?php echo esc_html( rtrim( str_replace( array( 'Select ', 'Choose ' ), '', $employee_label ), ':' ) . ':' ); ?></span>
						<span class="slotnova-summary-value" id="summary-employee-name">-</span>
					</div>
					<?php endif; ?>
					<div class="slotnova-summary-row" id="summary-date-row">
						<span class="slotnova-summary-label"><?php esc_html_e( 'Date:', 'slotnova-booking' ); ?></span>
						<span class="slotnova-summary-value" id="summary-booking-date">-</span>
					</div>
					<div class="slotnova-summary-row" id="summary-time-row">
						<span class="slotnova-summary-label"><?php esc_html_e( 'Time:', 'slotnova-booking' ); ?></span>
						<span class="slotnova-summary-value" id="summary-booking-time">-</span>
					</div>
					<div class="slotnova-summary-divider"></div>
					<div class="slotnova-summary-row slotnova-summary-total">
						<span class="slotnova-summary-label"><?php esc_html_e( 'Total Price:', 'slotnova-booking' ); ?></span>
						<span class="slotnova-summary-value" id="summary-service-price" data-default-price="<?php echo esc_attr( $default_base_price ); ?>"><?php echo wp_kses_post( $default_price_display ); ?></span>
					</div>
					<div class="slotnova-summary-row slotnova-is-hidden" id="summary-payable-row" style="display: none;">
						<span class="slotnova-summary-label"><?php esc_html_e( 'Deposit Amount:', 'slotnova-booking' ); ?></span>
						<span class="slotnova-summary-value" id="summary-payable-amount" style="color: #15803d; font-weight: 700;">-</span>
					</div>
					<div class="slotnova-summary-row slotnova-is-hidden" id="summary-due-row" style="display: none;">
						<span class="slotnova-summary-label"><?php esc_html_e( 'Remaining Amount:', 'slotnova-booking' ); ?></span>
						<span class="slotnova-summary-value" id="summary-due-amount" style="color: #dc2626; font-weight: 700;">-</span>
					</div>
				</div>
			</div>

			<?php do_action( 'slotnova_before_add_to_cart_button', $product ); ?>

			<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product_id ); ?>" />
			<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product_id ); ?>" class="single_add_to_cart_button button alt"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>

			<?php do_action( 'slotnova_after_add_to_cart_button', $product ); ?>
		</form>
		<?php
	}

	/**
	 * Render order number column in My Account > Orders page.
	 *
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	public function render_account_order_number_column( $order ) {
		if ( ! $order ) {
			return;
		}

		$order_id = $order->get_id();
		?>
		<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" style="font-weight: 700;">
			#<?php echo esc_html( $order->get_order_number() ); ?>
		</a>
		<?php
		$booking_items = array();

		foreach ( $order->get_items() as $item ) {
			$service  = $item->get_meta( 'Service' );
			$employee = $item->get_meta( 'Employee' );
			$date     = $item->get_meta( 'Date' );
			$time     = $item->get_meta( 'Time' );

			if ( ! $employee ) {
				$employee = $item->get_meta( 'Staff' );
			}

			if ( ! $service ) {
				$svc_id = $item->get_meta( '_slotnova_service_id' );
				if ( $svc_id ) {
					$term = get_term( (int) $svc_id, 'slotnova_service' );
					if ( $term && ! is_wp_error( $term ) ) {
						$service = $term->name;
					}
				}
			}

			if ( ! $employee ) {
				$emp_id = $item->get_meta( '_slotnova_employee_id' );
				if ( $emp_id ) {
					$term = get_term( (int) $emp_id, 'slotnova_employee' );
					if ( $term && ! is_wp_error( $term ) ) {
						$employee = $term->name;
					}
				}
			}

			if ( $service || $employee || $date ) {
				$booking_items[] = array(
					'service'  => $service,
					'employee' => $employee,
					'date'     => $date,
					'time'     => $time,
				);
			}
		}

		if ( ! empty( $booking_items ) ) :
			?>
			<div class="slotnova-account-order-meta" style="margin-top: 6px; font-size: 12px; line-height: 1.5; color: #475569;">
				<?php foreach ( $booking_items as $b ) : ?>
					<div class="slotnova-account-order-meta-item" style="margin-bottom: 4px;">
						<?php if ( ! empty( $b['service'] ) ) : ?>
							<div><strong><?php esc_html_e( 'Service:', 'slotnova-booking' ); ?></strong> <?php echo esc_html( $b['service'] ); ?></div>
						<?php endif; ?>
						<?php if ( ! empty( $b['employee'] ) ) : ?>
							<div><strong><?php esc_html_e( 'Staff:', 'slotnova-booking' ); ?></strong> <?php echo esc_html( $b['employee'] ); ?></div>
						<?php endif; ?>
						<?php if ( ! empty( $b['date'] ) ) : ?>
							<?php
							$formatted_date = function_exists( 'wp_date' ) ? wp_date( 'M j, Y', strtotime( $b['date'] ) ) : gmdate( 'M j, Y', strtotime( $b['date'] ) );
							$dt_str         = $formatted_date;
							if ( ! empty( $b['time'] ) ) {
								$dt_str .= ' (' . $b['time'] . ')';
							}
							?>
							<div><strong><?php esc_html_e( 'Date & Time:', 'slotnova-booking' ); ?></strong> <?php echo esc_html( $dt_str ); ?></div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		endif;
	}
}
