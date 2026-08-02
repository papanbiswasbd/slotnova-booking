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

		// Hide default WooCommerce price for SlotNova products
		add_filter( 'woocommerce_get_price_html', array( $this, 'hide_default_price' ), 10, 2 );

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
	 * Hide the default WooCommerce price for SlotNova products.
	 *
	 * @param string $price The price HTML.
	 * @param \WC_Product $product The product object.
	 * @return string
	 */
	public function hide_default_price( $price, $product ) {
		if ( $product && 'slotnova' === $product->get_type() ) {
			return '';
		}
		return $price;
	}

	/**
	 * AJAX handler to fetch booked time slots for a specific product and date.
	 *
	 * @return void
	 */
	public function ajax_get_booked_slots() {
		check_ajax_referer( 'slotnova_frontend_nonce', 'nonce' );

		$product_id  = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$date        = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$service_id  = isset( $_POST['service_id'] ) ? intval( $_POST['service_id'] ) : 0;
		$employee_id = isset( $_POST['employee_id'] ) ? intval( $_POST['employee_id'] ) : 0;

		if ( ! $product_id || empty( $date ) ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
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
				if ( (int) $item->get_product_id() === $product_id ) {
					$item_date        = $item->get_meta( 'Date' );
					$item_time        = $item->get_meta( 'Time' );
					$item_service_id  = (int) $item->get_meta( '_slotnova_service_id' );
					$item_employee_id = (int) $item->get_meta( '_slotnova_employee_id' );
					$item_service     = $item->get_meta( 'Service' );
					$item_employee    = $item->get_meta( 'Employee' );

					if ( $item_date === $date && ! empty( $item_time ) ) {
						$is_match = false;
						if ( $employee_id > 0 ) {
							if ( $item_employee_id > 0 ) {
								if ( $item_employee_id === $employee_id ) {
									$is_match = true;
								}
							} elseif ( ! empty( $item_employee ) ) {
								$emp_term = get_term( $employee_id, 'slotnova_employee' );
								if ( $emp_term && ! is_wp_error( $emp_term ) && $emp_term->name === $item_employee ) {
									$is_match = true;
								}
							} else {
								$is_match = true;
							}
						} elseif ( $service_id > 0 ) {
							if ( $item_service_id > 0 ) {
								if ( $item_service_id === $service_id ) {
									$is_match = true;
								}
							} elseif ( ! empty( $item_service ) ) {
								$svc_term = get_term( $service_id, 'slotnova_service' );
								if ( $svc_term && ! is_wp_error( $svc_term ) && $svc_term->name === $item_service ) {
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
			}
		}

		// Check active WC cart session
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( isset( $cart_item['product_id'] ) && (int) $cart_item['product_id'] === $product_id ) {
					if ( isset( $cart_item['slotnova_booking']['date'] ) && $cart_item['slotnova_booking']['date'] === $date ) {
						$cart_time = isset( $cart_item['slotnova_booking']['time'] ) ? $cart_item['slotnova_booking']['time'] : '';
						$cart_svc  = isset( $cart_item['slotnova_booking']['service_id'] ) ? (int) $cart_item['slotnova_booking']['service_id'] : 0;
						$cart_emp  = isset( $cart_item['slotnova_booking']['employee_id'] ) ? (int) $cart_item['slotnova_booking']['employee_id'] : 0;

						if ( ! empty( $cart_time ) ) {
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
				}
			}
		}

		wp_send_json_success( array( 'booked_slots' => $booked_slots ) );
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

		wp_localize_script( 'slotnova-frontend-js', 'slotnova_params', array(
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'slotnova_frontend_nonce' ),
			'currency_symbol'  => get_woocommerce_currency_symbol(),
			'free_text'        => __( 'Free', 'slotnova-booking' ),
			'choose_time_text' => __( 'Choose a time...', 'slotnova-booking' ),
			'booked_text'      => __( 'Booked', 'slotnova-booking' ),
			'i18n'             => array(
				'select_service'  => __( 'Please select a service before booking.', 'slotnova-booking' ),
				'select_employee' => __( 'Please select an employee before booking.', 'slotnova-booking' ),
				'select_date'     => __( 'Please select a date before booking.', 'slotnova-booking' ),
				'select_time'     => __( 'Please select a time before booking.', 'slotnova-booking' ),
			),
		) );
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

			<?php if ( ! empty( $saved_services ) ) : ?>
			<div class="form-row form-row-wide slotnova-custom-select-wrapper">
				<label for="slotnova_service"><?php esc_html_e( 'Select Service', 'slotnova-booking' ); ?></label>
				<div class="slotnova-custom-select" id="slotnova_service_dropdown">
					<div class="slotnova-select-trigger">
						<div class="slotnova-select-trigger-content">
							<span class="slotnova-select-text"><?php esc_html_e( 'Choose a service...', 'slotnova-booking' ); ?></span>
						</div>
						<div class="slotnova-select-arrow"></div>
					</div>
					<div class="slotnova-select-options">
						<?php foreach ( $saved_services as $saved ) :
							$service = get_term( $saved['term_id'], 'slotnova_service' );
							if ( ! $service || is_wp_error( $service ) ) {
								continue;
							}

							if ( isset( $saved['price'] ) && '' !== $saved['price'] ) {
								$price = $saved['price'];
							} else {
								$price = get_term_meta( $service->term_id, 'slotnova_service_price', true );
							}

							$price_val     = floatval( $price );
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
				</div>
				<input type="hidden" name="slotnova_service" id="slotnova_service" required>
				<?php wp_nonce_field( 'slotnova_add_to_cart', 'slotnova_cart_nonce' ); ?>
			</div>
			<?php else : ?>
				<p><em><?php esc_html_e( 'No services available for this booking.', 'slotnova-booking' ); ?></em></p>
			<?php endif; ?>

			<?php if ( ! empty( $saved_employees ) ) : ?>
			<div class="form-row form-row-wide slotnova-custom-select-wrapper">
				<label for="slotnova_employee"><?php esc_html_e( 'Select Employee', 'slotnova-booking' ); ?></label>
				<div class="slotnova-custom-select" id="slotnova_employee_dropdown">
					<div class="slotnova-select-trigger">
						<div class="slotnova-select-trigger-content">
							<span class="slotnova-select-text"><?php esc_html_e( 'Choose an employee...', 'slotnova-booking' ); ?></span>
						</div>
						<div class="slotnova-select-arrow"></div>
					</div>
					<div class="slotnova-select-options">
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
				</div>
				<input type="hidden" name="slotnova_employee" id="slotnova_employee" required>
			</div>
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
			?>
			<p class="form-row form-row-wide">
				<label for="slotnova_booking_date"><?php esc_html_e( 'Select Date', 'slotnova-booking' ); ?></label>
				<input type="text" name="slotnova_booking_date" id="slotnova_booking_date" required class="slotnova-is-hidden" data-off-days="<?php echo esc_attr( $combined_off_days ); ?>">
			</p>

			<?php
			$enable_time_slots = get_post_meta( $product_id, '_slotnova_enable_time_slots', true );
			if ( empty( $enable_time_slots ) || 'global' === $enable_time_slots ) {
				$enable_time_slots = get_option( 'slotnova_enable_time_slots', 'yes' );
			}
			?>

			<?php if ( 'yes' === $enable_time_slots ) : ?>
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

					$start_time = strtotime( $opening );
					$end_time   = strtotime( $closing );

					if ( $start_time && $end_time && $start_time < $end_time ) {
						$current_time = $start_time;
						while ( $current_time + ( $duration * 60 ) <= $end_time ) {
							$slot_label = wp_date( 'h:i A', $current_time );
							echo '<button type="button" class="slotnova-time-pill" data-value="' . esc_attr( $slot_label ) . '">' . esc_html( $slot_label ) . '</button>';
							$current_time += ( $duration * 60 );
						}
					}
					?>
				</div>
				<input type="hidden" name="slotnova_booking_time" id="slotnova_booking_time" required>
			</div>
			<?php endif; ?>

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

			<p class="form-row form-row-wide slotnova-submit-wrapper">
				<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt"><?php esc_html_e( 'Book Appointment', 'slotnova-booking' ); ?></button>
			</p>
		</form>
		<?php
	}
}
