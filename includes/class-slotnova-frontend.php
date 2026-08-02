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

		// AJAX endpoint for fetching booked time slots
		add_action( 'wp_ajax_slotnova_get_booked_slots', array( $this, 'ajax_get_booked_slots' ) );
		add_action( 'wp_ajax_nopriv_slotnova_get_booked_slots', array( $this, 'ajax_get_booked_slots' ) );
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
	 * Filter price HTML for SlotNova products to display single value or price range.
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
	 * @param \WC_Product $product The product object.
	 * @return string
	 */
	public function get_slotnova_product_price_html( $product ) {
		if ( ! $product || 'slotnova' !== $product->get_type() ) {
			return '';
		}

		$product_id     = $product->get_id();
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
					$svc_price = floatval( $product->get_price() );
				}
				if ( $svc_price >= 0 ) {
					$prices[] = $svc_price;
				}
			}
		}

		if ( empty( $prices ) ) {
			$base_price = floatval( $product->get_price() );
			return ( $base_price > 0 ) ? wc_price( $base_price ) : __( 'Free', 'slotnova-booking' );
		}

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

		if ( empty( $raw_date ) || false === strtotime( $raw_date ) ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
		}

		$target_date  = date( 'Y-m-d', strtotime( $raw_date ) );
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
			.slotnova-custom-select .slotnova-select-trigger {
				border-radius: {$border_radius} !important;
			}
			.slotnova-custom-select .slotnova-select-trigger.active,
			.slotnova-custom-select .slotnova-select-trigger:focus-within {
				border-color: {$primary_color} !important;
				box-shadow: 0 0 0 3px {$primary_color}25 !important;
			}
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
			.slotnova-form button.single_add_to_cart_button {
				background: linear-gradient(135deg, {$primary_color} 0%, {$accent_color} 100%) !important;
				border-radius: {$border_radius} !important;
				box-shadow: 0 4px 16px {$primary_color}40 !important;
			}
			.slotnova-form button.single_add_to_cart_button:hover {
				background: linear-gradient(135deg, {$accent_color} 0%, {$primary_color} 100%) !important;
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
			'site_current_date' => wp_date( 'Y-m-d' ),
			'site_current_time' => wp_date( 'H:i' ),
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

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$action_url = apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() );
		?>
		<form class="cart slotnova-form" data-product-id="<?php echo esc_attr( $product_id ); ?>" action="<?php echo esc_url( $action_url ); ?>" method="post" enctype='multipart/form-data'>

			<?php do_action( 'slotnova_before_booking_form', $product, $saved_services, $saved_employees ); ?>

			<?php if ( ! empty( $saved_services ) ) : ?>
			<?php do_action( 'slotnova_before_service_select', $product, $saved_services ); ?>
			<div class="form-row form-row-wide slotnova-custom-select-wrapper">
				<label for="slotnova_service"><?php esc_html_e( 'Select Service', 'slotnova-booking' ); ?></label>
				<div class="slotnova-custom-select" id="slotnova_service_dropdown">
					<div class="slotnova-select-trigger">
						<input type="text" class="slotnova-select-search-input" placeholder="<?php esc_attr_e( 'Choose a service...', 'slotnova-booking' ); ?>" autocomplete="off" />
						<div class="slotnova-select-arrow"></div>
					</div>
					<div class="slotnova-select-options">
						<div class="slotnova-select-options-list">
							<?php foreach ( $saved_services as $saved ) :
								$service = get_term( $saved['term_id'], 'slotnova_service' );
								if ( ! $service || is_wp_error( $service ) ) {
									continue;
								}

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
				<?php wp_nonce_field( 'slotnova_add_to_cart', 'slotnova_cart_nonce' ); ?>
			</div>
			<?php do_action( 'slotnova_after_service_select', $product, $saved_services ); ?>
			<?php else : ?>
				<p><em><?php esc_html_e( 'No services available for this booking.', 'slotnova-booking' ); ?></em></p>
			<?php endif; ?>

			<?php if ( ! empty( $saved_employees ) ) : ?>
			<?php do_action( 'slotnova_before_employee_select', $product, $saved_employees ); ?>
			<div class="form-row form-row-wide slotnova-custom-select-wrapper">
				<label for="slotnova_employee"><?php esc_html_e( 'Select Employee', 'slotnova-booking' ); ?></label>
				<div class="slotnova-custom-select" id="slotnova_employee_dropdown">
					<div class="slotnova-select-trigger">
						<input type="text" class="slotnova-select-search-input" placeholder="<?php esc_attr_e( 'Choose an employee...', 'slotnova-booking' ); ?>" autocomplete="off" />
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
								?>
								<div class="slotnova-select-option" data-value="<?php echo esc_attr( $employee->term_id ); ?>" data-name="<?php echo esc_attr( $employee->name ); ?>">
									<?php if ( $image_url ) : ?>
										<img src="<?php echo esc_url( $image_url ); ?>" alt="" class="slotnova-option-img">
									<?php else : ?>
										<div class="slotnova-option-img-placeholder"></div>
									<?php endif; ?>
									<div class="slotnova-option-details">
										<span class="slotnova-option-name"><?php echo esc_html( $employee->name ); ?></span>
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
			if ( empty( $specific_off ) ) {
				$specific_off = get_option( 'slotnova_specific_off_days', '' );
			}

			$all_off_days_arr = $weekly_off;
			if ( ! empty( $specific_off ) ) {
				$specific_off_arr = array_map( 'trim', explode( ',', $specific_off ) );
				$all_off_days_arr = array_merge( $all_off_days_arr, $specific_off_arr );
			}
			$combined_off_days = implode( ',', $all_off_days_arr );

			$opening = get_post_meta( $product_id, '_slotnova_opening_time', true );
			if ( empty( $opening ) ) {
				$opening = get_option( 'slotnova_opening_time', '09:00' );
			}

			$closing = get_post_meta( $product_id, '_slotnova_closing_time', true );
			if ( empty( $closing ) ) {
				$closing = get_option( 'slotnova_closing_time', '17:00' );
			}
			?>
			<?php do_action( 'slotnova_before_date_select', $product ); ?>
			<p class="form-row form-row-wide">
				<label for="slotnova_booking_date"><?php esc_html_e( 'Select Date', 'slotnova-booking' ); ?></label>
				<input type="text" name="slotnova_booking_date" id="slotnova_booking_date" required class="slotnova-is-hidden" data-off-days="<?php echo esc_attr( $combined_off_days ); ?>" data-opening-time="<?php echo esc_attr( $opening ); ?>" data-closing-time="<?php echo esc_attr( $closing ); ?>">
			</p>
			<?php do_action( 'slotnova_after_date_select', $product ); ?>

			<?php
			$enable_time_slots = get_post_meta( $product_id, '_slotnova_enable_time_slots', true );
			if ( empty( $enable_time_slots ) || 'global' === $enable_time_slots ) {
				$enable_time_slots = get_option( 'slotnova_enable_time_slots', 'yes' );
			}
			?>

			<?php if ( 'yes' === $enable_time_slots ) : ?>
			<?php do_action( 'slotnova_before_time_slots', $product ); ?>
			<div class="form-row form-row-wide slotnova-time-slots-wrapper slotnova-is-hidden">
				<label><?php esc_html_e( 'Select Time Slot', 'slotnova-booking' ); ?></label>
				<div class="slotnova-time-pills-grid" id="slotnova_time_pills">
					<?php
					$opening = get_post_meta( $product_id, '_slotnova_opening_time', true );
					if ( empty( $opening ) ) {
						$opening = get_option( 'slotnova_opening_time', '09:00' );
					}

					$closing = get_post_meta( $product_id, '_slotnova_closing_time', true );
					if ( empty( $closing ) ) {
						$closing = get_option( 'slotnova_closing_time', '17:00' );
					}

					$duration_val = get_post_meta( $product_id, '_slotnova_slot_duration', true );
					if ( empty( $duration_val ) ) {
						$duration_val = get_option( 'slotnova_slot_duration', '60' );
					}
					$duration = (int) $duration_val;
					if ( $duration < 5 ) {
						$duration = 60;
					}

					$start_time = strtotime( '1970-01-01 ' . $opening . ':00 UTC' );
					$end_time   = strtotime( '1970-01-01 ' . $closing . ':00 UTC' );

					if ( $start_time && $end_time && $start_time < $end_time ) {
						$current_time = $start_time;
						while ( $current_time + ( $duration * 60 ) <= $end_time ) {
							$slot_label = gmdate( 'h:i A', $current_time );
							echo '<button type="button" class="slotnova-time-pill" data-value="' . esc_attr( $slot_label ) . '">' . esc_html( $slot_label ) . '</button>';
							$current_time += ( $duration * 60 );
						}
					}
					?>
				</div>
				<input type="hidden" name="slotnova_booking_time" id="slotnova_booking_time" required>
			</div>
			<?php do_action( 'slotnova_after_time_slots', $product ); ?>
			<?php endif; ?>

			<?php do_action( 'slotnova_before_booking_summary', $product ); ?>
			<div id="slotnova-summary" class="slotnova-is-hidden">
				<div class="slotnova-summary-header">
					<span class="slotnova-summary-icon">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
					</span>
					<h4 class="slotnova-summary-title"><?php esc_html_e( 'Booking Summary', 'slotnova-booking' ); ?></h4>
				</div>
				<div class="slotnova-summary-body">
					<div class="slotnova-summary-row">
						<span class="slotnova-summary-label"><?php esc_html_e( 'Service', 'slotnova-booking' ); ?></span>
						<span class="slotnova-summary-value" id="summary-service-name">-</span>
					</div>
					<?php if ( ! empty( $saved_employees ) ) : ?>
					<div class="slotnova-summary-row slotnova-is-hidden" id="summary-employee-row">
						<span class="slotnova-summary-label"><?php esc_html_e( 'Employee', 'slotnova-booking' ); ?></span>
						<span class="slotnova-summary-value" id="summary-employee-name">-</span>
					</div>
					<?php endif; ?>
					<div class="slotnova-summary-row">
						<span class="slotnova-summary-label"><?php esc_html_e( 'Date', 'slotnova-booking' ); ?></span>
						<span class="slotnova-summary-value" id="summary-booking-date">-</span>
					</div>
					<?php if ( 'yes' === $enable_time_slots ) : ?>
					<div class="slotnova-summary-row" id="summary-time-row">
						<span class="slotnova-summary-label"><?php esc_html_e( 'Time', 'slotnova-booking' ); ?></span>
						<span class="slotnova-summary-value" id="summary-booking-time">-</span>
					</div>
					<?php endif; ?>
					<div class="slotnova-summary-divider"></div>
					<div class="slotnova-summary-row slotnova-summary-total">
						<span class="slotnova-summary-label"><?php esc_html_e( 'Total Amount', 'slotnova-booking' ); ?></span>
						<span class="slotnova-summary-value" id="summary-service-price">-</span>
					</div>
				</div>
			</div>
			<?php do_action( 'slotnova_after_booking_summary', $product ); ?>

			<p class="form-row form-row-wide slotnova-submit-wrapper">
				<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt"><?php esc_html_e( 'Book Appointment', 'slotnova-booking' ); ?></button>
			</p>
			<?php do_action( 'slotnova_after_booking_form', $product ); ?>
		</form>
		<?php
	}
}
