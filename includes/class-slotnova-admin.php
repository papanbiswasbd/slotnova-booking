<?php
/**
 * SlotNova Admin Class
 *
 * @package SlotNova\Booking
 * @version 1.0.0
 */

namespace SlotNova\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 *
 * Handles admin menus, settings, dashboard analytics, and product meta boxes.
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'register_settings_options' ) );

		// Register Product Type
		add_filter( 'product_type_selector', array( $this, 'add_slotnova_product_type' ) );
		add_filter( 'woocommerce_product_class', array( $this, 'woocommerce_product_class' ), 10, 2 );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_slotnova_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_slotnova_product_tab_content' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_slotnova_product_tab_data' ) );

		// AJAX Handlers for Smart Features
		add_action( 'wp_ajax_slotnova_export_bookings_csv', array( $this, 'ajax_export_bookings_csv' ) );
		add_action( 'wp_ajax_slotnova_create_manual_booking', array( $this, 'ajax_create_manual_booking' ) );

		// Native WP Admin Dashboard Widget Hook
		add_action( 'wp_dashboard_setup', array( $this, 'register_wp_dashboard_widget' ) );
	}

	/**
	 * Register native WP Admin Dashboard widget.
	 *
	 * @return void
	 */
	public function register_wp_dashboard_widget() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			wp_add_dashboard_widget(
				'slotnova_wp_dashboard_widget',
				__( 'SlotNova Booking Overview', 'slotnova-booking' ),
				array( $this, 'render_wp_dashboard_widget' )
			);
		}
	}

	/**
	 * Render native WP Admin Dashboard widget content.
	 *
	 * @return void
	 */
	public function render_wp_dashboard_widget() {
		$data = $this->get_dashboard_data( 'this_week' );
		?>
		<div class="slotnova-wp-widget-content">
			<p><strong><?php esc_html_e( "Today's Appointments:", 'slotnova-booking' ); ?></strong> <?php echo esc_html( $data['today_bookings'] ); ?></p>
			<p><strong><?php esc_html_e( 'Total Revenue (This Week):', 'slotnova-booking' ); ?></strong> <?php echo wp_kses_post( wc_price( $data['total_revenue'] ) ); ?></p>
			<p><strong><?php esc_html_e( 'Pending Confirmation:', 'slotnova-booking' ); ?></strong> <?php echo esc_html( $data['pending_bookings'] ); ?></p>

			<hr style="border: 0; border-top: 1px solid #eee; margin: 12px 0;" />

			<div style="display: flex; gap: 10px; flex-wrap: wrap;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=slotnova-dashboard' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Open SlotNova Dashboard', 'slotnova-booking' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=slotnova-calendar' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'View All Bookings', 'slotnova-booking' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX Handler: Export bookings to CSV.
	 *
	 * @return void
	 */
	public function ajax_export_bookings_csv() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'slotnova-booking' ) );
		}

		check_ajax_referer( 'slotnova_admin_nonce', 'security' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search          = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$service_filter  = isset( $_GET['service'] ) ? sanitize_text_field( wp_unslash( $_GET['service'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$employee_filter = isset( $_GET['employee'] ) ? sanitize_text_field( wp_unslash( $_GET['employee'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		$data     = $this->get_all_bookings_data( $search, $service_filter, $employee_filter, $status_filter );
		$bookings = $data['list'];

		$filename = 'slotnova-bookings-' . wp_date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		fputcsv( $output, array(
			'Order ID',
			'Customer Name',
			'Email',
			'Phone',
			'Service',
			'Employee',
			'Booking Date',
			'Time Slot',
			'Status',
			'Total Price',
		) );

		foreach ( $bookings as $b ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
			fputcsv( $output, array(
				'#' . $b['order_id'],
				$b['customer'],
				$b['email'],
				$b['phone'],
				$b['service'],
				$b['employee'],
				$b['date'],
				$b['time'],
				$b['status'],
				wc_format_decimal( $b['total'], 2 ),
			) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}

	/**
	 * AJAX Handler: Create manual booking from Admin.
	 *
	 * @return void
	 */
	public function ajax_create_manual_booking() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'slotnova-booking' ) ) );
		}

		check_ajax_referer( 'slotnova_admin_nonce', 'security' );

		$product_id     = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$first_name     = isset( $_POST['billing_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ) ) : '';
		$last_name      = isset( $_POST['billing_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ) ) : '';
		$customer_email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
		$customer_phone = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';
		$company        = isset( $_POST['billing_company'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_company'] ) ) : '';
		$address_1      = isset( $_POST['billing_address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_address_1'] ) ) : '';
		$city           = isset( $_POST['billing_city'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_city'] ) ) : '';
		$postcode       = isset( $_POST['billing_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_postcode'] ) ) : '';
		$country        = isset( $_POST['billing_country'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) : '';

		// Fallback for customer_name if billing_first_name was empty
		if ( empty( $first_name ) && isset( $_POST['customer_name'] ) ) {
			$cust_name  = sanitize_text_field( wp_unslash( $_POST['customer_name'] ) );
			$name_parts = explode( ' ', $cust_name, 2 );
			$first_name = $name_parts[0];
			$last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';
		}

		if ( empty( $customer_email ) && isset( $_POST['customer_email'] ) ) {
			$customer_email = sanitize_email( wp_unslash( $_POST['customer_email'] ) );
		}

		if ( empty( $customer_phone ) && isset( $_POST['customer_phone'] ) ) {
			$customer_phone = sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) );
		}

		$service_id    = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
		$employee_id   = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0;
		$service_name  = isset( $_POST['service_name'] ) ? sanitize_text_field( wp_unslash( $_POST['service_name'] ) ) : '';
		$employee_name = isset( $_POST['employee_name'] ) ? sanitize_text_field( wp_unslash( $_POST['employee_name'] ) ) : '';
		$booking_date  = isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '';
		$booking_time  = isset( $_POST['booking_time'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_time'] ) ) : '';

		if ( $service_id > 0 && empty( $service_name ) ) {
			$svc_term = get_term( $service_id, 'slotnova_service' );
			if ( $svc_term && ! is_wp_error( $svc_term ) ) {
				$service_name = $svc_term->name;
			}
		}

		if ( $employee_id > 0 && empty( $employee_name ) ) {
			$emp_term = get_term( $employee_id, 'slotnova_employee' );
			if ( $emp_term && ! is_wp_error( $emp_term ) ) {
				$employee_name = $emp_term->name;
			}
		}

		if ( empty( $first_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter the customer first name.', 'slotnova-booking' ) ) );
		}

		if ( empty( $booking_date ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a booking date.', 'slotnova-booking' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			$args     = array(
				'post_type'      => 'product',
				'posts_per_page' => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => 'slotnova',
					),
				),
			);
			$products = get_posts( $args );
			if ( ! empty( $products ) ) {
				$product_id = $products[0]->ID;
				$product    = wc_get_product( $product_id );
			}
		}

		$svc_price = 0;
		if ( $product_id ) {
			$saved_services = get_post_meta( $product_id, '_slotnova_product_services', true );
			if ( is_array( $saved_services ) ) {
				foreach ( $saved_services as $saved ) {
					if ( (int) $saved['term_id'] === $service_id && isset( $saved['price'] ) && '' !== $saved['price'] && floatval( $saved['price'] ) > 0 ) {
						$svc_price = floatval( $saved['price'] );
						break;
					}
				}
			}
		}

		if ( $svc_price <= 0 && $service_id ) {
			$svc_price = floatval( get_term_meta( $service_id, 'slotnova_service_price', true ) );
		}

		if ( $svc_price <= 0 && $product ) {
			$svc_price = floatval( $product->get_price() );
		}

		$order = wc_create_order();
		if ( is_wp_error( $order ) ) {
			wp_send_json_error( array( 'message' => $order->get_error_message() ) );
		}

		$item_id = 0;
		if ( $product ) {
			$item_id = $order->add_product( $product, 1, array(
				'subtotal' => $svc_price,
				'total'    => $svc_price,
			) );
		} else {
			$item = new \WC_Order_Item_Fee();
			$item->set_name( ! empty( $service_name ) ? $service_name : __( 'Manual SlotNova Booking', 'slotnova-booking' ) );
			$item->set_subtotal( $svc_price );
			$item->set_total( $svc_price );
			$order->add_item( $item );
			$item_id = $item->get_id();
		}

		if ( $item_id ) {
			$order_item = $order->get_item( $item_id );
			if ( $order_item ) {
				if ( ! empty( $service_name ) ) {
					$order_item->add_meta_data( 'Service', $service_name );
				}
				if ( ! empty( $employee_name ) ) {
					$order_item->add_meta_data( 'Employee', $employee_name );
				}
				$order_item->add_meta_data( 'Date', $booking_date );
				if ( ! empty( $booking_time ) ) {
					$order_item->add_meta_data( 'Time', $booking_time );
				}
				if ( $service_id ) {
					$order_item->add_meta_data( '_slotnova_service_id', $service_id );
				}
				if ( $employee_id ) {
					$order_item->add_meta_data( '_slotnova_employee_id', $employee_id );
				}

				if ( $svc_price > 0 ) {
					$order_item->set_subtotal( $svc_price );
					$order_item->set_total( $svc_price );
				}

				$order_item->save();
			}
		}

		do_action( 'slotnova_before_manual_booking_created', $_POST );

		$address = array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'company'    => $company,
			'email'      => $customer_email,
			'phone'      => $customer_phone,
			'address_1'  => $address_1,
			'city'       => $city,
			'postcode'   => $postcode,
			'country'    => $country,
		);

		$order->set_address( $address, 'billing' );
		$status = apply_filters( 'slotnova_manual_booking_order_status', 'pending', $_POST );
		$order->set_status( $status, __( 'Manual booking created via SlotNova Dashboard.', 'slotnova-booking' ) );
		$order->calculate_totals();

		if ( $svc_price > 0 ) {
			$order->set_total( $svc_price );
		}

		$order->save();

		do_action( 'slotnova_after_manual_booking_created', $order->get_id(), $order, $_POST );

		wp_send_json_success( array(
			'message'  => __( 'Manual booking created successfully!', 'slotnova-booking' ),
			'order_id' => $order->get_id(),
		) );
	}

	/**
	 * Add slotnova product type to WooCommerce selector.
	 *
	 * @param array $types Existing product types.
	 * @return array
	 */
	public function add_slotnova_product_type( $types ) {
		$types['slotnova'] = __( 'SlotNova Booking', 'slotnova-booking' );
		return $types;
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
	 * Register global plugin options.
	 *
	 * @return void
	 */
	public function register_settings_options() {
		register_setting( 'slotnova_settings_group', 'slotnova_opening_time', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'slotnova_settings_group', 'slotnova_closing_time', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'slotnova_settings_group', 'slotnova_slot_duration', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'slotnova_settings_group', 'slotnova_weekly_off_days', array( 'sanitize_callback' => array( $this, 'sanitize_array' ) ) );
		register_setting( 'slotnova_settings_group', 'slotnova_specific_off_days', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'slotnova_settings_group', 'slotnova_enable_time_slots', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// Style & Color settings
		register_setting( 'slotnova_settings_group', 'slotnova_primary_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( 'slotnova_settings_group', 'slotnova_accent_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( 'slotnova_settings_group', 'slotnova_bg_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( 'slotnova_settings_group', 'slotnova_text_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( 'slotnova_settings_group', 'slotnova_border_radius', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	/**
	 * Sanitize array setting.
	 *
	 * @param mixed $input Input data.
	 * @return array
	 */
	public function sanitize_array( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', $input );
	}

	/**
	 * Add product data tabs for SlotNova.
	 *
	 * @param array $tabs Existing product data tabs.
	 * @return array
	 */
	public function add_slotnova_product_tab( $tabs ) {
		$tabs['slotnova_booking'] = array(
			'label'    => __( 'SlotNova Booking', 'slotnova-booking' ),
			'target'   => 'slotnova_booking_data',
			'class'    => array( 'show_if_slotnova' ),
			'priority' => 1,
		);

		$tabs['slotnova_slot_manager'] = array(
			'label'    => __( 'Slot Manager', 'slotnova-booking' ),
			'target'   => 'slotnova_slot_manager_data',
			'class'    => array( 'show_if_slotnova' ),
			'priority' => 2,
		);

		uasort( $tabs, function( $a, $b ) {
			$a_priority = isset( $a['priority'] ) ? intval( $a['priority'] ) : 50;
			$b_priority = isset( $b['priority'] ) ? intval( $b['priority'] ) : 50;
			if ( $a_priority === $b_priority ) {
				return 0;
			}
			return ( $a_priority < $b_priority ) ? -1 : 1;
		} );

		return $tabs;
	}

	/**
	 * Enqueue admin scripts and styles conditionally.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		$is_slotnova_page = ( strpos( $hook, 'slotnova' ) !== false );
		$is_product_page  = in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_slotnova_page && ! $is_product_page ) {
			return;
		}

		$admin_js_deps = array( 'jquery' );

		if ( $is_slotnova_page || $is_product_page ) {
			wp_enqueue_style( 'slotnova-flatpickr-css', SLOTNOVA_BOOKING_URL . 'assets/css/flatpickr-light.css', array(), '4.6.13' );
			wp_enqueue_script( 'slotnova-flatpickr-js', SLOTNOVA_BOOKING_URL . 'assets/js/flatpickr.min.js', array(), '4.6.13', true );
			$admin_js_deps[] = 'slotnova-flatpickr-js';
		}

		if ( strpos( $hook, 'slotnova-calendar' ) !== false ) {
			wp_enqueue_script( 'slotnova-fullcalendar-js', SLOTNOVA_BOOKING_URL . 'assets/js/fullcalendar.min.js', array(), '6.1.15', true );
			$admin_js_deps[] = 'slotnova-fullcalendar-js';
		}

		// Always enqueue core admin CSS and JS on relevant pages
		wp_enqueue_style( 'slotnova-admin-css', SLOTNOVA_BOOKING_URL . 'assets/css/slotnova-admin.css', array( 'slotnova-flatpickr-css' ), SLOTNOVA_BOOKING_VERSION );
		wp_enqueue_script( 'slotnova-admin-js', SLOTNOVA_BOOKING_URL . 'assets/js/slotnova-admin.js', $admin_js_deps, SLOTNOVA_BOOKING_VERSION, true );

		$localized_data = array(
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'slotnova_admin_nonce' ),
			'currency_symbol'   => get_woocommerce_currency_symbol(),
			'free_text'         => __( 'Free', 'slotnova-booking' ),
			'booked_text'       => __( 'Booked', 'slotnova-booking' ),
			'passed_text'       => __( 'Time Passed', 'slotnova-booking' ),
			'site_current_date' => wp_date( 'Y-m-d' ),
			'site_current_time' => wp_date( 'H:i' ),
			'i18n'              => array(
				'select_service'  => __( 'Select Service...', 'slotnova-booking' ),
				'select_employee' => __( 'Select Employee...', 'slotnova-booking' ),
				'remove'          => __( 'Remove', 'slotnova-booking' ),
				'choose_image'    => __( 'Choose Image', 'slotnova-booking' ),
				'use_image'       => __( 'Use this image', 'slotnova-booking' ),
				'export_error'    => __( 'Failed to export CSV. Please try again.', 'slotnova-booking' ),
				'saving'          => __( 'Saving...', 'slotnova-booking' ),
			),
		);

		if ( strpos( $hook, 'slotnova-dashboard' ) !== false || strpos( $hook, 'slotnova-calendar' ) !== false ) {
			$all_services  = get_terms( array( 'taxonomy' => 'slotnova_service', 'hide_empty' => false ) );
			$all_employees = get_terms( array( 'taxonomy' => 'slotnova_employee', 'hide_empty' => false ) );

			$service_list  = array();
			$employee_list = array();

			if ( ! is_wp_error( $all_services ) && ! empty( $all_services ) ) {
				foreach ( $all_services as $s ) {
					$service_list[] = $s->name;
				}
			}
			if ( ! is_wp_error( $all_employees ) && ! empty( $all_employees ) ) {
				foreach ( $all_employees as $e ) {
					$employee_list[] = $e->name;
				}
			}

			$localized_data['services']  = array_values( array_unique( $service_list ) );
			$localized_data['employees'] = array_values( array_unique( $employee_list ) );
		}

		if ( strpos( $hook, 'slotnova-dashboard' ) !== false ) {
			wp_enqueue_script( 'slotnova-chart-js', SLOTNOVA_BOOKING_URL . 'assets/js/chart.min.js', array(), '4.4.0', true );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_filter = isset( $_GET['date_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['date_filter'] ) ) : 'last_7_days';
			$data           = $this->get_dashboard_data( $current_filter );

			$localized_data['chart'] = array(
				'labels'         => $data['chart_labels'],
				'values'         => $data['chart_values'],
				'revenue_values' => $data['chart_revenue'],
				'i18n_label'     => __( 'Bookings Made', 'slotnova-booking' ),
				'i18n_revenue'   => __( 'Revenue ($)', 'slotnova-booking' ),
			);
		}

		if ( strpos( $hook, 'slotnova-calendar' ) !== false ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$search          = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$service_filter  = isset( $_GET['service'] ) ? sanitize_text_field( wp_unslash( $_GET['service'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$employee_filter = isset( $_GET['employee'] ) ? sanitize_text_field( wp_unslash( $_GET['employee'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$status_filter   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

			$data                       = $this->get_all_bookings_data( $search, $service_filter, $employee_filter, $status_filter );
			$localized_data['calendar'] = array(
				'events' => $data['events'],
			);
		}

		if ( strpos( $hook, 'slotnova-settings' ) !== false || $is_product_page ) {
			wp_enqueue_style( 'slotnova-flatpickr-css', SLOTNOVA_BOOKING_URL . 'assets/css/flatpickr.min.css', array(), '4.6.13' );
			wp_enqueue_script( 'slotnova-flatpickr-js', SLOTNOVA_BOOKING_URL . 'assets/js/flatpickr.min.js', array(), '4.6.13', true );
		}

		if ( strpos( $hook, 'slotnova-settings' ) !== false ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}

		if ( $is_product_page ) {
			wp_enqueue_script( 'jquery-ui-sortable' );

			$all_services           = get_terms( array( 'taxonomy' => 'slotnova_service', 'hide_empty' => false ) );
			$all_employees          = get_terms( array( 'taxonomy' => 'slotnova_employee', 'hide_empty' => false ) );
			$default_service_prices = array();
			$service_options        = array();
			$employee_options       = array();

			if ( ! is_wp_error( $all_services ) && ! empty( $all_services ) ) {
				foreach ( $all_services as $service ) {
					$default_service_prices[ $service->term_id ] = get_term_meta( $service->term_id, 'slotnova_service_price', true );
					$service_options[ $service->term_id ]        = $service->name;
				}
			}

			if ( ! is_wp_error( $all_employees ) && ! empty( $all_employees ) ) {
				foreach ( $all_employees as $employee ) {
					$employee_options[ $employee->term_id ] = $employee->name;
				}
			}

			$localized_data['default_service_prices'] = $default_service_prices;
			$localized_data['all_services']           = $service_options;
			$localized_data['all_employees']          = $employee_options;
		}

		$localized_data = apply_filters( 'slotnova_admin_localized_data', $localized_data, $hook );
		wp_localize_script( 'slotnova-admin-js', 'slotnova_admin_data', $localized_data );
	}

	/**
	 * Register admin menu items.
	 *
	 * @return void
	 */
	public function register_menus() {
		add_menu_page(
			__( 'SlotNova Booking', 'slotnova-booking' ),
			__( 'SlotNova', 'slotnova-booking' ),
			'manage_woocommerce',
			'slotnova-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-calendar-alt',
			54
		);

		add_submenu_page(
			'slotnova-dashboard',
			__( 'Bookings', 'slotnova-booking' ),
			__( 'Bookings', 'slotnova-booking' ),
			'manage_woocommerce',
			'slotnova-calendar',
			array( $this, 'render_calendar' )
		);

		add_submenu_page(
			'slotnova-dashboard',
			__( 'Settings', 'slotnova-booking' ),
			__( 'Settings', 'slotnova-booking' ),
			'manage_woocommerce',
			'slotnova-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Calculate dashboard metrics & smart analytics.
	 *
	 * @param string $filter Selected filter range.
	 * @return array
	 */
	private function get_dashboard_data( $filter = 'last_7_days' ) {
		$args = array(
			'status' => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ),
			'limit'  => -1,
		);

		$today_ts  = current_time( 'timestamp' );
		$today     = wp_date( 'Y-m-d', $today_ts );
		$is_yearly = false;

		switch ( $filter ) {
			case 'this_week':
				$start_date = wp_date( 'Y-m-d', strtotime( 'monday this week', $today_ts ) );
				$end_date   = wp_date( 'Y-m-d', strtotime( 'sunday this week', $today_ts ) );
				break;
			case 'this_month':
				$start_date = wp_date( 'Y-m-01', $today_ts );
				$end_date   = wp_date( 'Y-m-t', $today_ts );
				break;
			case 'last_month':
				$start_date = wp_date( 'Y-m-01', strtotime( 'first day of last month', $today_ts ) );
				$end_date   = wp_date( 'Y-m-t', strtotime( 'last day of last month', $today_ts ) );
				break;
			case 'this_year':
				$start_date = wp_date( 'Y-01-01', $today_ts );
				$end_date   = wp_date( 'Y-12-31', $today_ts );
				$is_yearly  = true;
				break;
			case 'last_year':
				$start_date = wp_date( 'Y-01-01', strtotime( 'last year', $today_ts ) );
				$end_date   = wp_date( 'Y-12-31', strtotime( 'last year', $today_ts ) );
				$is_yearly  = true;
				break;
			case 'last_7_days':
			default:
				$start_date = wp_date( 'Y-m-d', strtotime( '-6 days', $today_ts ) );
				$end_date   = wp_date( 'Y-m-d', $today_ts );
				break;
		}

		$args['date_created'] = $start_date . '...' . $end_date;
		$orders               = wc_get_orders( $args );

		$total_bookings    = 0;
		$total_revenue     = 0;
		$upcoming_bookings = 0;
		$today_bookings    = 0;
		$pending_bookings  = 0;
		$completed_count   = 0;

		$chart_bookings   = array();
		$chart_revenue    = array();
		$services_count   = array();
		$employees_count  = array();
		$todays_agenda    = array();
		$peak_hours_count = array();
		$day_counts       = array(
			'Mon' => 0,
			'Tue' => 0,
			'Wed' => 0,
			'Thu' => 0,
			'Fri' => 0,
			'Sat' => 0,
			'Sun' => 0,
		);

		if ( $is_yearly ) {
			for ( $m = 1; $m <= 12; $m++ ) {
				$month_key                    = wp_date( 'Y-m', strtotime( wp_date( 'Y', strtotime( $start_date ) ) . '-' . sprintf( '%02d', $m ) . '-01' ) );
				$chart_bookings[ $month_key ] = 0;
				$chart_revenue[ $month_key ]  = 0;
			}
		} else {
			$current = strtotime( $start_date );
			$end     = strtotime( $end_date );
			while ( $current <= $end ) {
				$key                    = wp_date( 'Y-m-d', $current );
				$chart_bookings[ $key ] = 0;
				$chart_revenue[ $key ]  = 0;
				$current                = strtotime( '+1 day', $current );
			}
		}

		foreach ( $orders as $order ) {
			$order_status = $order->get_status();
			if ( 'completed' === $order_status ) {
				$completed_count++;
			}
			if ( 'processing' === $order_status || 'on-hold' === $order_status ) {
				$pending_bookings++;
			}

			foreach ( $order->get_items() as $item ) {
				$booking_date = $item->get_meta( 'Date' );
				if ( ! empty( $booking_date ) ) {
					$total_bookings++;
					$item_total     = (float) $item->get_total();
					$total_revenue += $item_total;

					if ( $booking_date >= $today ) {
						$upcoming_bookings++;
					}
					if ( $booking_date === $today ) {
						$today_bookings++;
					}

					// Day distribution
					$day_name = wp_date( 'D', strtotime( $booking_date ) );
					if ( isset( $day_counts[ $day_name ] ) ) {
						$day_counts[ $day_name ]++;
					}

					$service  = $item->get_meta( 'Service' );
					$employee = $item->get_meta( 'Employee' );
					$time     = $item->get_meta( 'Time' );

					if ( ! empty( $time ) ) {
						$hour_formatted                       = wp_date( 'g:00 A', strtotime( $time ) );
						$peak_hours_count[ $hour_formatted ] = ( isset( $peak_hours_count[ $hour_formatted ] ) ? $peak_hours_count[ $hour_formatted ] : 0 ) + 1;
					}

					if ( ! empty( $service ) ) {
						$services_count[ $service ] = ( isset( $services_count[ $service ] ) ? $services_count[ $service ] : 0 ) + 1;
					}
					if ( ! empty( $employee ) ) {
						$employees_count[ $employee ] = ( isset( $employees_count[ $employee ] ) ? $employees_count[ $employee ] : 0 ) + 1;
					}

					if ( $booking_date === $today ) {
						$cust_name = $order->get_formatted_billing_full_name();
						if ( empty( $cust_name ) ) {
							$cust_name = __( 'Guest', 'slotnova-booking' );
						}
						$todays_agenda[] = array(
							'order_id'  => $order->get_id(),
							'order_url' => $order->get_edit_order_url(),
							'customer'  => $cust_name,
							'service'   => ! empty( $service ) ? $service : __( 'General Service', 'slotnova-booking' ),
							'employee'  => ! empty( $employee ) ? $employee : __( 'Any Staff', 'slotnova-booking' ),
							'time'      => ! empty( $time ) ? $time : __( 'All Day', 'slotnova-booking' ),
							'status'    => wc_get_order_status_name( $order_status ),
						);
					}

					$order_key = $is_yearly ? $order->get_date_created()->date( 'Y-m' ) : $order->get_date_created()->date( 'Y-m-d' );

					if ( isset( $chart_bookings[ $order_key ] ) ) {
						$chart_bookings[ $order_key ]++;
						$chart_revenue[ $order_key ] += $item_total;
					}
				}
			}
		}

		arsort( $services_count );
		arsort( $employees_count );
		arsort( $peak_hours_count );

		$top_services  = array_slice( $services_count, 0, 5, true );
		$top_employees = array_slice( $employees_count, 0, 5, true );
		$peak_hours    = array_slice( $peak_hours_count, 0, 5, true );

		$avg_booking_value = $total_bookings > 0 ? ( $total_revenue / $total_bookings ) : 0;
		$completion_rate   = count( $orders ) > 0 ? round( ( $completed_count / count( $orders ) ) * 100 ) : 100;

		$chart_labels = array();
		foreach ( $chart_bookings as $key => $val ) {
			if ( $is_yearly ) {
				$chart_labels[] = wp_date( 'M Y', strtotime( $key . '-01' ) );
			} else {
				$chart_labels[] = wp_date( 'M j', strtotime( $key ) );
			}
		}

		$metrics = array(
			'total_bookings'    => $total_bookings,
			'total_revenue'     => $total_revenue,
			'upcoming_bookings' => $upcoming_bookings,
			'today_bookings'    => $today_bookings,
			'pending_bookings'  => $pending_bookings,
			'avg_booking_value' => $avg_booking_value,
			'completion_rate'   => $completion_rate,
			'top_services'      => $top_services,
			'top_employees'     => $top_employees,
			'peak_hours'        => $peak_hours,
			'day_distribution'  => $day_counts,
			'todays_agenda'     => $todays_agenda,
			'chart_labels'      => $chart_labels,
			'chart_values'      => array_values( $chart_bookings ),
			'chart_revenue'     => array_values( $chart_revenue ),
		);

		return apply_filters( 'slotnova_dashboard_metrics', $metrics, $filter );
	}

	/**
	 * Render Dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'slotnova-booking' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_filter = isset( $_GET['date_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['date_filter'] ) ) : 'last_7_days';
		$data           = $this->get_dashboard_data( $current_filter );

		$filter_options = array(
			'this_week'   => __( 'This Week', 'slotnova-booking' ),
			'last_7_days' => __( 'Last 7 Days', 'slotnova-booking' ),
			'this_month'  => __( 'This Month', 'slotnova-booking' ),
			'last_month'  => __( 'Last Month', 'slotnova-booking' ),
			'this_year'   => __( 'This Year', 'slotnova-booking' ),
			'last_year'   => __( 'Last Year', 'slotnova-booking' ),
		);

		$all_services  = get_terms( array( 'taxonomy' => 'slotnova_service', 'hide_empty' => false ) );
		$all_employees = get_terms( array( 'taxonomy' => 'slotnova_employee', 'hide_empty' => false ) );
		?>
		<div class="wrap slotnova-dashboard-wrap">
			<h1 class="wp-heading-inline screen-reader-text"><?php esc_html_e( 'SlotNova Dashboard', 'slotnova-booking' ); ?></h1>
			<!-- Hero Header Banner -->
			<div class="slotnova-hero-banner">
				<div class="slotnova-hero-content">
					<div class="slotnova-hero-badges">
						<span class="slotnova-pill-badge slotnova-pill-pulse">
							<span class="slotnova-pulse-dot"></span> <?php esc_html_e( 'Live System Active', 'slotnova-booking' ); ?>
						</span>
						<span class="slotnova-pill-badge slotnova-pill-date">
							<span class="dashicons dashicons-calendar-alt"></span> <?php echo esc_html( wp_date( 'l, F j, Y' ) ); ?>
						</span>
					</div>
					<h1 class="slotnova-hero-title"><?php esc_html_e( 'SlotNova Command Center', 'slotnova-booking' ); ?></h1>
					<p class="slotnova-hero-subtitle"><?php esc_html_e( 'Monitor real-time appointment volume, revenue performance, and manage your booking operations seamlessly.', 'slotnova-booking' ); ?></p>
				</div>
				<div class="slotnova-hero-actions">
					<form method="get" action="" class="slotnova-inline-form">
						<input type="hidden" name="page" value="slotnova-dashboard" />
						<div class="slotnova-select-wrapper">
							<select name="date_filter" class="slotnova-filter-select">
								<?php foreach ( $filter_options as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_filter, $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</form>
					<button type="button" class="slotnova-btn slotnova-btn-primary" id="slotnova-open-manual-booking-modal">
						<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Manual Booking', 'slotnova-booking' ); ?>
					</button>
					<button type="button" class="slotnova-btn slotnova-btn-glass" id="slotnova-export-csv-btn">
						<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export CSV', 'slotnova-booking' ); ?>
					</button>
				</div>
			</div>

			<!-- Modern Stat Cards Grid -->
			<div class="slotnova-dashboard-grid">
				<div class="slotnova-stat-card slotnova-card-indigo">
					<div class="slotnova-stat-icon-wrapper">
						<div class="slotnova-stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
					</div>
					<div class="slotnova-stat-details">
						<div class="slotnova-stat-top">
							<h3 class="slotnova-stat-card-title"><?php esc_html_e( 'Total Bookings', 'slotnova-booking' ); ?></h3>
							<span class="slotnova-trend-tag slotnova-tag-blue"><?php echo esc_html( $filter_options[ $current_filter ] ); ?></span>
						</div>
						<p class="slotnova-stat-card-value"><?php echo esc_html( $data['total_bookings'] ); ?></p>
						<span class="slotnova-stat-subtext"><?php esc_html_e( 'Confirmed & Processed Orders', 'slotnova-booking' ); ?></span>
					</div>
				</div>

				<div class="slotnova-stat-card slotnova-card-emerald">
					<div class="slotnova-stat-icon-wrapper">
						<div class="slotnova-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
					</div>
					<div class="slotnova-stat-details">
						<div class="slotnova-stat-top">
							<h3 class="slotnova-stat-card-title"><?php esc_html_e( 'Total Revenue', 'slotnova-booking' ); ?></h3>
							<span class="slotnova-trend-tag slotnova-tag-green">+100% <?php esc_html_e( 'Net', 'slotnova-booking' ); ?></span>
						</div>
						<p class="slotnova-stat-card-value"><?php echo wp_kses_post( wc_price( $data['total_revenue'] ) ); ?></p>
						<span class="slotnova-stat-subtext"><?php esc_html_e( 'Earned from SlotNova services', 'slotnova-booking' ); ?></span>
					</div>
				</div>

				<div class="slotnova-stat-card slotnova-card-amber">
					<div class="slotnova-stat-icon-wrapper">
						<div class="slotnova-stat-icon"><span class="dashicons dashicons-clock"></span></div>
					</div>
					<div class="slotnova-stat-details">
						<div class="slotnova-stat-top">
							<h3 class="slotnova-stat-card-title"><?php esc_html_e( "Today's Schedule", 'slotnova-booking' ); ?></h3>
							<span class="slotnova-trend-tag slotnova-tag-yellow"><?php esc_html_e( 'Live Today', 'slotnova-booking' ); ?></span>
						</div>
						<p class="slotnova-stat-card-value"><?php echo esc_html( $data['today_bookings'] ); ?></p>
						<span class="slotnova-stat-subtext"><?php esc_html_e( 'Appointments scheduled today', 'slotnova-booking' ); ?></span>
					</div>
				</div>

				<div class="slotnova-stat-card slotnova-card-rose">
					<div class="slotnova-stat-icon-wrapper">
						<div class="slotnova-stat-icon"><span class="dashicons dashicons-hourglass"></span></div>
					</div>
					<div class="slotnova-stat-details">
						<div class="slotnova-stat-top">
							<h3 class="slotnova-stat-card-title"><?php esc_html_e( 'Pending Action', 'slotnova-booking' ); ?></h3>
							<span class="slotnova-trend-tag slotnova-tag-purple"><?php esc_html_e( 'Action Needed', 'slotnova-booking' ); ?></span>
						</div>
						<p class="slotnova-stat-card-value"><?php echo esc_html( $data['pending_bookings'] ); ?></p>
						<span class="slotnova-stat-subtext"><?php esc_html_e( 'Processing or On-Hold Bookings', 'slotnova-booking' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Enhanced KPI Summary Strip -->
			<div class="slotnova-kpi-bar">
				<div class="slotnova-kpi-item">
					<div class="slotnova-kpi-icon-mini"><span class="dashicons dashicons-chart-line"></span></div>
					<div class="slotnova-kpi-text">
						<span class="slotnova-kpi-label"><?php esc_html_e( 'Avg. Booking Value', 'slotnova-booking' ); ?></span>
						<strong class="slotnova-kpi-val"><?php echo wp_kses_post( wc_price( $data['avg_booking_value'] ) ); ?></strong>
					</div>
				</div>
				<div class="slotnova-kpi-divider"></div>
				<div class="slotnova-kpi-item">
					<div class="slotnova-kpi-icon-mini"><span class="dashicons dashicons-yes-alt"></span></div>
					<div class="slotnova-kpi-text">
						<span class="slotnova-kpi-label"><?php esc_html_e( 'Fulfillment Rate', 'slotnova-booking' ); ?></span>
						<strong class="slotnova-kpi-val"><?php echo esc_html( $data['completion_rate'] ); ?>%</strong>
					</div>
				</div>
				<div class="slotnova-kpi-divider"></div>
				<div class="slotnova-kpi-item">
					<div class="slotnova-kpi-icon-mini"><span class="dashicons dashicons-performance"></span></div>
					<div class="slotnova-kpi-text">
						<span class="slotnova-kpi-label"><?php esc_html_e( 'Top Peak Hour', 'slotnova-booking' ); ?></span>
						<strong class="slotnova-kpi-val">
							<?php 
							$peak_keys = array_keys( $data['peak_hours'] );
							echo ! empty( $peak_keys ) ? esc_html( $peak_keys[0] ) : esc_html__( 'N/A', 'slotnova-booking' ); 
							?>
						</strong>
					</div>
				</div>
				<div class="slotnova-kpi-divider"></div>
				<div class="slotnova-kpi-item">
					<div class="slotnova-kpi-icon-mini"><span class="dashicons dashicons-groups"></span></div>
					<div class="slotnova-kpi-text">
						<span class="slotnova-kpi-label"><?php esc_html_e( 'Active Staff', 'slotnova-booking' ); ?></span>
						<strong class="slotnova-kpi-val"><?php echo esc_html( count( $data['top_employees'] ) ); ?></strong>
					</div>
				</div>
			</div>

			<!-- Chart & Performance Trends -->
			<div class="slotnova-chart-container">
				<div class="slotnova-chart-header">
					<div class="slotnova-chart-title-group">
						<h3 class="slotnova-chart-title">
							<span class="dashicons dashicons-chart-area"></span>
							<?php 
							/* translators: %s: filter label */
							printf( esc_html__( 'Performance Analytics (%s)', 'slotnova-booking' ), esc_html( $filter_options[ $current_filter ] ) ); 
							?>
						</h3>
						<p class="slotnova-chart-desc"><?php esc_html_e( 'Visual representation of appointment volume and revenue stream over time.', 'slotnova-booking' ); ?></p>
					</div>
					<div class="slotnova-chart-toggles">
						<button type="button" class="slotnova-chart-toggle-btn active" data-dataset="bookings">
							<span class="slotnova-btn-dot dot-blue"></span> <?php esc_html_e( 'Bookings Count', 'slotnova-booking' ); ?>
						</button>
						<button type="button" class="slotnova-chart-toggle-btn" data-dataset="revenue">
							<span class="slotnova-btn-dot dot-green"></span> <?php esc_html_e( 'Revenue ($)', 'slotnova-booking' ); ?>
						</button>
					</div>
				</div>
				<div class="slotnova-chart-wrapper">
					<canvas id="slotnovaBookingsChart" height="85"></canvas>
				</div>
			</div>

			<!-- Smart Analytics Row: Peak Hours & Busiest Days -->
			<div class="slotnova-dashboard-columns slotnova-mb-25">
				<!-- Peak Booking Hours Widget -->
				<div class="slotnova-dashboard-card">
					<div class="slotnova-card-header">
						<h3><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Peak Booking Hours Analytics', 'slotnova-booking' ); ?></h3>
						<span class="slotnova-header-tag"><?php esc_html_e( 'Demand Distribution', 'slotnova-booking' ); ?></span>
					</div>
					<div class="slotnova-card-content">
						<?php if ( empty( $data['peak_hours'] ) ) : ?>
							<div class="slotnova-empty-state">
								<div class="slotnova-empty-icon"><span class="dashicons dashicons-clock"></span></div>
								<p><?php esc_html_e( 'No slot time data available for this date range.', 'slotnova-booking' ); ?></p>
							</div>
						<?php else : ?>
							<ul class="slotnova-progress-list">
								<?php 
								$max_peak = max( array_values( $data['peak_hours'] ) );
								foreach ( $data['peak_hours'] as $hour_slot => $h_count ) :
									$perc = $max_peak > 0 ? round( ( $h_count / $max_peak ) * 100 ) : 0;
								?>
									<li>
										<div class="slotnova-progress-info">
											<span class="slotnova-time-chip"><span class="dashicons dashicons-clock"></span> <strong><?php echo esc_html( $hour_slot ); ?></strong></span>
											<span class="slotnova-count-badge"><?php echo esc_html( $h_count ); ?> <?php esc_html_e( 'bookings', 'slotnova-booking' ); ?></span>
										</div>
										<div class="slotnova-bar-bg"><div class="slotnova-bar-fill" style="width: <?php echo esc_attr( $perc ); ?>%;"></div></div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>

				<!-- Busiest Days of Week Widget -->
				<div class="slotnova-dashboard-card">
					<div class="slotnova-card-header">
						<h3><span class="dashicons dashicons-calendar"></span> <?php esc_html_e( 'Busiest Days of the Week', 'slotnova-booking' ); ?></h3>
						<span class="slotnova-header-tag"><?php esc_html_e( 'Weekly Load', 'slotnova-booking' ); ?></span>
					</div>
					<div class="slotnova-card-content">
						<?php 
						$max_day = max( array_values( $data['day_distribution'] ) );
						if ( $max_day === 0 ) :
						?>
							<div class="slotnova-empty-state">
								<div class="slotnova-empty-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
								<p><?php esc_html_e( 'No booking day distribution available yet.', 'slotnova-booking' ); ?></p>
							</div>
						<?php else : ?>
							<div class="slotnova-day-grid">
								<?php foreach ( $data['day_distribution'] as $day_code => $d_count ) :
									$d_perc = $max_day > 0 ? round( ( $d_count / $max_day ) * 100 ) : 0;
								?>
									<div class="slotnova-day-bar-col">
										<span class="slotnova-day-count"><?php echo esc_html( $d_count ); ?></span>
										<div class="slotnova-vbar-container" title="<?php echo esc_attr( $d_count . ' bookings' ); ?>">
											<div class="slotnova-vbar-fill" style="height: <?php echo esc_attr( max( 8, $d_perc ) ); ?>%;"></div>
										</div>
										<span class="slotnova-day-name"><?php echo esc_html( $day_code ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Two Column Dashboard Widgets Layout -->
			<div class="slotnova-dashboard-columns">
				<!-- Today's Agenda Widget -->
				<div class="slotnova-dashboard-card slotnova-agenda-card">
					<div class="slotnova-card-header">
						<h3><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( "Today's Appointments Agenda", 'slotnova-booking' ); ?></h3>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=slotnova-calendar' ) ); ?>" class="slotnova-link-more">
							<?php esc_html_e( 'View All Bookings', 'slotnova-booking' ); ?> <span class="dashicons dashicons-arrow-right-alt"></span>
						</a>
					</div>
					<div class="slotnova-card-content">
						<?php if ( empty( $data['todays_agenda'] ) ) : ?>
							<div class="slotnova-empty-state">
								<div class="slotnova-empty-icon"><span class="dashicons dashicons-smiley"></span></div>
								<p><?php esc_html_e( 'No appointments scheduled for today.', 'slotnova-booking' ); ?></p>
							</div>
						<?php else : ?>
							<ul class="slotnova-agenda-list">
								<?php foreach ( $data['todays_agenda'] as $agenda ) :
									$cust_initials = mb_strtoupper( mb_substr( $agenda['customer'], 0, 1 ) );
								?>
									<li class="slotnova-agenda-item">
										<div class="slotnova-avatar-circle">
											<?php echo esc_html( $cust_initials ); ?>
										</div>
										<div class="slotnova-agenda-info">
											<div class="slotnova-agenda-customer-line">
												<strong><?php echo esc_html( $agenda['customer'] ); ?></strong>
												<span class="slotnova-agenda-time-tag"><span class="dashicons dashicons-clock"></span> <?php echo esc_html( $agenda['time'] ); ?></span>
											</div>
											<div class="slotnova-agenda-sub">
												<span class="slotnova-service-chip"><?php echo esc_html( $agenda['service'] ); ?></span>
												<span class="slotnova-staff-chip"><span class="dashicons dashicons-admin-users"></span> <?php echo esc_html( $agenda['employee'] ); ?></span>
											</div>
										</div>
										<div class="slotnova-agenda-action">
											<span class="slotnova-badge status-<?php echo sanitize_html_class( strtolower( $agenda['status'] ) ); ?>"><?php echo esc_html( $agenda['status'] ); ?></span>
											<a href="<?php echo esc_url( $agenda['order_url'] ); ?>" class="slotnova-btn-round" title="<?php esc_attr_e( 'View Order Details', 'slotnova-booking' ); ?>">
												<span class="dashicons dashicons-arrow-right-alt2"></span>
											</a>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>

				<!-- Popularity Insights Card -->
				<div class="slotnova-dashboard-card slotnova-breakdown-card">
					<div class="slotnova-card-header">
						<h3><span class="dashicons dashicons-chart-pie"></span> <?php esc_html_e( 'Popularity Insights', 'slotnova-booking' ); ?></h3>
						<span class="slotnova-header-tag"><?php esc_html_e( 'Top Rankings', 'slotnova-booking' ); ?></span>
					</div>
					<div class="slotnova-card-content">
						<div class="slotnova-breakdown-section">
							<h4 class="slotnova-breakdown-heading"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Top Booked Services', 'slotnova-booking' ); ?></h4>
							<?php if ( empty( $data['top_services'] ) ) : ?>
								<p class="slotnova-empty-text"><?php esc_html_e( 'No service booking data available yet.', 'slotnova-booking' ); ?></p>
							<?php else : ?>
								<ul class="slotnova-ranking-list">
									<?php 
									$rank = 1;
									foreach ( $data['top_services'] as $svc_name => $count ) : 
									?>
										<li class="slotnova-ranking-item">
											<span class="slotnova-rank-badge rank-<?php echo esc_attr( $rank ); ?>">#<?php echo esc_html( $rank ); ?></span>
											<div class="slotnova-ranking-details">
												<span class="slotnova-ranking-name"><?php echo esc_html( $svc_name ); ?></span>
												<strong class="slotnova-ranking-count"><?php echo esc_html( $count ); ?> <?php esc_html_e( 'bookings', 'slotnova-booking' ); ?></strong>
											</div>
										</li>
									<?php 
										$rank++;
									endforeach; 
									?>
								</ul>
							<?php endif; ?>
						</div>

						<div class="slotnova-breakdown-section slotnova-mt-20">
							<h4 class="slotnova-breakdown-heading"><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Top Performing Staff', 'slotnova-booking' ); ?></h4>
							<?php if ( empty( $data['top_employees'] ) ) : ?>
								<p class="slotnova-empty-text"><?php esc_html_e( 'No staff booking data available yet.', 'slotnova-booking' ); ?></p>
							<?php else : ?>
								<ul class="slotnova-ranking-list">
									<?php 
									$staff_rank = 1;
									foreach ( $data['top_employees'] as $emp_name => $count ) : 
									?>
										<li class="slotnova-ranking-item">
											<span class="slotnova-rank-badge rank-<?php echo esc_attr( $staff_rank ); ?>">#<?php echo esc_html( $staff_rank ); ?></span>
											<div class="slotnova-ranking-details">
												<span class="slotnova-ranking-name"><?php echo esc_html( $emp_name ); ?></span>
												<strong class="slotnova-ranking-count"><?php echo esc_html( $count ); ?> <?php esc_html_e( 'bookings', 'slotnova-booking' ); ?></strong>
											</div>
										</li>
									<?php 
										$staff_rank++;
									endforeach; 
									?>
								</ul>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<?php $this->render_manual_booking_modal(); ?>

		</div>
		<?php
	}

	/**
	 * Get list and calendar event data.
	 *
	 * @param string $search Search query.
	 * @param string $service_filter Filter by service name.
	 * @param string $employee_filter Filter by employee name.
	 * @param string $status_filter Filter by status.
	 * @return array
	 */
	private function get_all_bookings_data( $search = '', $service_filter = '', $employee_filter = '', $status_filter = '' ) {
		$args   = array(
			'status' => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'wc-refunded' ),
			'limit'  => -1,
		);
		$orders = wc_get_orders( $args );

		$list_data       = array();
		$calendar_events = array();

		foreach ( $orders as $order ) {
			$order_status = $order->get_status();
			if ( ! empty( $status_filter ) && $order_status !== $status_filter ) {
				continue;
			}

			foreach ( $order->get_items() as $item ) {
				$booking_date = $item->get_meta( 'Date' );

				if ( ! empty( $booking_date ) ) {
					$time          = $item->get_meta( 'Time' );
					$service       = $item->get_meta( 'Service' );
					$employee      = $item->get_meta( 'Employee' );
					$customer_name = $order->get_formatted_billing_full_name();
					if ( empty( $customer_name ) ) {
						$customer_name = __( 'Guest', 'slotnova-booking' );
					}
					$email = $order->get_billing_email();
					$phone = $order->get_billing_phone();

					if ( ! empty( $service_filter ) && $service !== $service_filter ) {
						continue;
					}
					if ( ! empty( $employee_filter ) && $employee !== $employee_filter ) {
						continue;
					}

					if ( ! empty( $search ) ) {
						$search_lc = strtolower( $search );
						$match     = ( strpos( strtolower( $customer_name ), $search_lc ) !== false ) ||
						             ( strpos( strtolower( $email ), $search_lc ) !== false ) ||
						             ( strpos( strtolower( $phone ), $search_lc ) !== false ) ||
						             ( strpos( (string) $order->get_id(), $search_lc ) !== false ) ||
						             ( strpos( strtolower( $service ), $search_lc ) !== false );
						if ( ! $match ) {
							continue;
						}
					}

					$list_data[] = array(
						'order_id'   => $order->get_id(),
						'order_url'  => $order->get_edit_order_url(),
						'customer'   => $customer_name,
						'email'      => $email,
						'phone'      => $phone,
						'address'    => str_replace( '<br/>', ', ', $order->get_formatted_billing_address() ),
						'service'    => ! empty( $service ) ? $service : __( 'General Service', 'slotnova-booking' ),
						'employee'   => ! empty( $employee ) ? $employee : __( 'Any Staff', 'slotnova-booking' ),
						'date'       => $booking_date,
						'time'       => ! empty( $time ) ? $time : __( 'All Day', 'slotnova-booking' ),
						'status'          => wc_get_order_status_name( $order_status ),
						'status_raw'      => $order_status,
						'total'           => $item->get_total(),
						'total_formatted' => wp_kses_post( wc_price( $item->get_total() ) ),
					);

					$event_title = $customer_name . ' - ' . ( ! empty( $service ) ? $service : __( 'Booking', 'slotnova-booking' ) );
					$event_start = $booking_date;
					if ( ! empty( $time ) ) {
						$time_24     = wp_date( 'H:i:s', strtotime( $time ) );
						$event_start .= 'T' . $time_24;
					}

					$bg_color = ( 'completed' === $order_status ) ? '#00a32a' : ( ( 'processing' === $order_status ) ? '#2271b1' : '#dba617' );

					$calendar_events[] = array(
						'title'           => $event_title,
						'start'           => $event_start,
						'url'             => $order->get_edit_order_url(),
						'backgroundColor' => $bg_color,
						'borderColor'     => $bg_color,
					);
				}
			}
		}

		$result = array(
			'list'   => $list_data,
			'events' => $calendar_events,
		);

		return apply_filters( 'slotnova_calendar_events', $result, $search, $service_filter, $employee_filter, $status_filter );
	}

	/**
	 * Render Calendar / All Bookings page.
	 *
	 * @return void
	 */
	public function render_calendar() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'slotnova-booking' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search          = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$service_filter  = isset( $_GET['service'] ) ? sanitize_text_field( wp_unslash( $_GET['service'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$employee_filter = isset( $_GET['employee'] ) ? sanitize_text_field( wp_unslash( $_GET['employee'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		$data = $this->get_all_bookings_data( $search, $service_filter, $employee_filter, $status_filter );

		$all_services  = get_terms( array( 'taxonomy' => 'slotnova_service', 'hide_empty' => false ) );
		$all_employees = get_terms( array( 'taxonomy' => 'slotnova_employee', 'hide_empty' => false ) );
		?>
		<div class="wrap slotnova-bookings-wrap">
			<h1 class="wp-heading-inline screen-reader-text"><?php esc_html_e( 'All Bookings Management', 'slotnova-booking' ); ?></h1>
			
			<!-- Hero Header Banner -->
			<div class="slotnova-hero-banner">
				<div class="slotnova-hero-content">
					<div class="slotnova-hero-badges">
						<span class="slotnova-pill-badge slotnova-pill-pulse">
							<span class="slotnova-pulse-dot"></span> <?php esc_html_e( 'Bookings Database', 'slotnova-booking' ); ?>
						</span>
						<span class="slotnova-pill-badge slotnova-pill-date">
							<span class="dashicons dashicons-list-view"></span> <?php echo esc_html( count( $data['list'] ) ); ?> <?php esc_html_e( 'Records Found', 'slotnova-booking' ); ?>
						</span>
					</div>
					<h1 class="slotnova-hero-title"><?php esc_html_e( 'All Bookings Management', 'slotnova-booking' ); ?></h1>
					<p class="slotnova-hero-subtitle"><?php esc_html_e( 'Browse, filter, and track customer appointments seamlessly across list and interactive calendar views.', 'slotnova-booking' ); ?></p>
				</div>
				<div class="slotnova-hero-actions">
					<button type="button" class="slotnova-btn slotnova-btn-primary" id="slotnova-open-manual-booking-modal">
						<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Manual Booking', 'slotnova-booking' ); ?>
					</button>
					<button type="button" class="slotnova-btn slotnova-btn-glass" id="slotnova-export-csv-btn">
						<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export CSV', 'slotnova-booking' ); ?>
					</button>
				</div>
			</div>

			<!-- Segmented Pill Tab Switcher -->
			<div class="slotnova-tab-switcher">
				<a href="#" class="slotnova-tab-pill active" id="slotnova-tab-list-view">
					<span class="dashicons dashicons-menu-alt3"></span> <?php esc_html_e( 'List View', 'slotnova-booking' ); ?>
				</a>
				<a href="#" class="slotnova-tab-pill" id="slotnova-tab-calendar-view">
					<span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Calendar View', 'slotnova-booking' ); ?>
				</a>
			</div>

			<div id="slotnova-list-view-container">
				<!-- Search & Filters Toolbar Card -->
				<form method="get" action="" class="slotnova-filter-card">
					<input type="hidden" name="page" value="slotnova-calendar" />
					<div class="slotnova-toolbar-grid">
						<div class="slotnova-search-box">
							<span class="dashicons dashicons-search slotnova-search-icon"></span>
							<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search customer, email, phone, order #...', 'slotnova-booking' ); ?>" class="slotnova-input-styled" />
						</div>

						<select name="service" class="slotnova-select-styled">
							<option value=""><?php esc_html_e( 'All Services', 'slotnova-booking' ); ?></option>
							<?php if ( ! is_wp_error( $all_services ) && ! empty( $all_services ) ) : ?>
								<?php foreach ( $all_services as $svc ) : ?>
									<option value="<?php echo esc_attr( $svc->name ); ?>" <?php selected( $service_filter, $svc->name ); ?>><?php echo esc_html( $svc->name ); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>

						<select name="employee" class="slotnova-select-styled">
							<option value=""><?php esc_html_e( 'All Staff', 'slotnova-booking' ); ?></option>
							<?php if ( ! is_wp_error( $all_employees ) && ! empty( $all_employees ) ) : ?>
								<?php foreach ( $all_employees as $emp ) : ?>
									<option value="<?php echo esc_attr( $emp->name ); ?>" <?php selected( $employee_filter, $emp->name ); ?>><?php echo esc_html( $emp->name ); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>

						<select name="status" class="slotnova-select-styled">
							<option value=""><?php esc_html_e( 'All Statuses', 'slotnova-booking' ); ?></option>
							<option value="processing" <?php selected( $status_filter, 'processing' ); ?>><?php esc_html_e( 'Processing', 'slotnova-booking' ); ?></option>
							<option value="completed" <?php selected( $status_filter, 'completed' ); ?>><?php esc_html_e( 'Completed', 'slotnova-booking' ); ?></option>
							<option value="on-hold" <?php selected( $status_filter, 'on-hold' ); ?>><?php esc_html_e( 'On Hold', 'slotnova-booking' ); ?></option>
							<option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'slotnova-booking' ); ?></option>
						</select>

						<div class="slotnova-filter-actions-group">
							<button type="submit" class="slotnova-btn-action slotnova-btn-action-primary">
								<span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Filter', 'slotnova-booking' ); ?>
							</button>
							<?php if ( ! empty( $search ) || ! empty( $service_filter ) || ! empty( $employee_filter ) || ! empty( $status_filter ) ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=slotnova-calendar' ) ); ?>" class="slotnova-btn-action slotnova-btn-action-reset">
									<?php esc_html_e( 'Reset', 'slotnova-booking' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				</form>

				<!-- Premium Table Card -->
				<div class="slotnova-table-card">
					<table class="slotnova-modern-table">
						<thead>
							<tr>
								<th style="width: 100px;"><?php esc_html_e( 'Order ID', 'slotnova-booking' ); ?></th>
								<th><?php esc_html_e( 'Customer', 'slotnova-booking' ); ?></th>
								<th><?php esc_html_e( 'Service', 'slotnova-booking' ); ?></th>
								<th><?php esc_html_e( 'Assigned Staff', 'slotnova-booking' ); ?></th>
								<th><?php esc_html_e( 'Booking Date & Time', 'slotnova-booking' ); ?></th>
								<th><?php esc_html_e( 'Status', 'slotnova-booking' ); ?></th>
								<th style="width: 110px; text-align: right;"><?php esc_html_e( 'Action', 'slotnova-booking' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $data['list'] ) ) : ?>
								<tr>
									<td colspan="7" class="slotnova-empty-table-cell">
										<div class="slotnova-empty-state">
											<div class="slotnova-empty-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
											<p><?php esc_html_e( 'No bookings matching your criteria.', 'slotnova-booking' ); ?></p>
										</div>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $data['list'] as $booking ) :
									$cust_initial = mb_strtoupper( mb_substr( $booking['customer'], 0, 1 ) );
								?>
									<tr class="slotnova-table-row">
										<td>
											<a href="<?php echo esc_url( $booking['order_url'] ); ?>" class="slotnova-order-pill">
												#<?php echo esc_html( $booking['order_id'] ); ?>
											</a>
										</td>
										<td>
											<div class="slotnova-customer-cell">
												<div class="slotnova-avatar-circle-sm">
													<?php echo esc_html( $cust_initial ); ?>
												</div>
												<div class="slotnova-customer-meta">
													<strong><?php echo esc_html( $booking['customer'] ); ?></strong>
													<div class="slotnova-contact-sub">
														<span><?php echo esc_html( $booking['email'] ); ?></span>
														<?php if ( ! empty( $booking['phone'] ) ) : ?>
															&bull; <span><?php echo esc_html( $booking['phone'] ); ?></span>
														<?php endif; ?>
													</div>
												</div>
											</div>
										</td>
										<td>
											<span class="slotnova-badge-service"><?php echo esc_html( $booking['service'] ); ?></span>
										</td>
										<td>
											<span class="slotnova-staff-tag">
												<span class="dashicons dashicons-admin-users"></span> <?php echo esc_html( $booking['employee'] ); ?>
											</span>
										</td>
										<td>
											<div class="slotnova-datetime-cell">
												<span class="slotnova-date-text"><?php echo esc_html( wp_date( 'M d, Y', strtotime( $booking['date'] ) ) ); ?></span>
												<span class="slotnova-time-text"><span class="dashicons dashicons-clock"></span> <?php echo esc_html( $booking['time'] ); ?></span>
											</div>
										</td>
										<td>
											<span class="slotnova-badge status-<?php echo sanitize_html_class( strtolower( $booking['status_raw'] ) ); ?>">
												<?php echo esc_html( $booking['status'] ); ?>
											</span>
										</td>
										<td style="text-align: right;">
											<div class="slotnova-action-cell-group">
												<button type="button" class="slotnova-btn-action-view slotnova-open-details-modal" data-booking="<?php echo esc_attr( wp_json_encode( $booking ) ); ?>" title="<?php esc_attr_e( 'View Booking Details', 'slotnova-booking' ); ?>">
													<span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'View', 'slotnova-booking' ); ?>
												</button>
												<a href="<?php echo esc_url( $booking['order_url'] ); ?>" class="slotnova-btn-action-icon" title="<?php esc_attr_e( 'Edit Order in WooCommerce', 'slotnova-booking' ); ?>">
													<span class="dashicons dashicons-edit"></span>
												</a>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div id="slotnova-calendar-view-container" class="slotnova-calendar-container slotnova-is-hidden">
				<div id="slotnova-fullcalendar"></div>
			</div>

			<!-- Booking Details Modal Popup (Classic Style) -->
			<div id="slotnova-booking-details-modal" class="slotnova-modal-overlay slotnova-is-hidden">
				<div class="slotnova-modal-content slotnova-details-modal-content">
					<div class="slotnova-modal-header slotnova-classic-modal-header">
						<div class="slotnova-modal-header-title">
							<h2><?php esc_html_e( 'Booking Details', 'slotnova-booking' ); ?> <span id="bd-modal-order-id" class="slotnova-order-pill"></span></h2>
						</div>
						<button type="button" class="slotnova-modal-close" aria-label="<?php esc_attr_e( 'Close', 'slotnova-booking' ); ?>">&times;</button>
					</div>

					<div class="slotnova-modal-body slotnova-details-modal-body">
						<div class="slotnova-details-hero-classic">
							<div class="slotnova-avatar-circle-classic" id="bd-modal-avatar">G</div>
							<div class="slotnova-details-hero-text">
								<h3 id="bd-modal-customer-name">Guest Customer</h3>
								<span id="bd-modal-status-badge" class="slotnova-badge status-processing">Processing</span>
							</div>
						</div>

						<!-- Summary Grid Cards -->
						<div class="slotnova-details-grid">
							<div class="slotnova-detail-box-classic">
								<span class="slotnova-detail-label"><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Date & Time', 'slotnova-booking' ); ?></span>
								<strong id="bd-modal-datetime" class="slotnova-detail-val">-</strong>
							</div>
							<div class="slotnova-detail-box-classic">
								<span class="slotnova-detail-label"><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Service', 'slotnova-booking' ); ?></span>
								<strong id="bd-modal-service" class="slotnova-detail-val">-</strong>
							</div>
							<div class="slotnova-detail-box-classic">
								<span class="slotnova-detail-label"><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'Assigned Staff', 'slotnova-booking' ); ?></span>
								<strong id="bd-modal-employee" class="slotnova-detail-val">-</strong>
							</div>
							<div class="slotnova-detail-box-classic">
								<span class="slotnova-detail-label"><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Total Amount', 'slotnova-booking' ); ?></span>
								<strong id="bd-modal-total" class="slotnova-detail-val">-</strong>
							</div>
						</div>

						<!-- Customer Info Section Card -->
						<div class="slotnova-details-contact-card">
							<h4 class="slotnova-contact-heading"><span class="dashicons dashicons-businessperson"></span> <?php esc_html_e( 'Customer Information', 'slotnova-booking' ); ?></h4>
							<div class="slotnova-contact-grid">
								<div class="slotnova-contact-item">
									<span class="slotnova-contact-label"><span class="dashicons dashicons-email"></span> <?php esc_html_e( 'Email Address', 'slotnova-booking' ); ?></span>
									<a href="" id="bd-modal-email" class="slotnova-contact-value-link">-</a>
								</div>
								<div class="slotnova-contact-item">
									<span class="slotnova-contact-label"><span class="dashicons dashicons-phone"></span> <?php esc_html_e( 'Phone Number', 'slotnova-booking' ); ?></span>
									<span id="bd-modal-phone" class="slotnova-contact-value">-</span>
								</div>
								<div class="slotnova-contact-item slotnova-full-width">
									<span class="slotnova-contact-label"><span class="dashicons dashicons-location"></span> <?php esc_html_e( 'Billing Address', 'slotnova-booking' ); ?></span>
									<span id="bd-modal-address" class="slotnova-contact-value">-</span>
								</div>
							</div>
						</div>
					</div>
					<div class="slotnova-modal-footer">
						<button type="button" class="slotnova-btn-modal-cancel slotnova-modal-close"><?php esc_html_e( 'Close', 'slotnova-booking' ); ?></button>
						<a href="#" id="bd-modal-order-link" target="_blank" class="slotnova-btn-modal-primary">
							<span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit WooCommerce Order', 'slotnova-booking' ); ?>
						</a>
					</div>
				</div>
			</div>

			<?php $this->render_manual_booking_modal(); ?>
		</div>
		<?php
	}

	/**
	 * Render Manual Booking Modal.
	 *
	 * @return void
	 */
	private function render_manual_booking_modal() {
		$all_services  = get_terms( array( 'taxonomy' => 'slotnova_service', 'hide_empty' => false ) );
		$all_employees = get_terms( array( 'taxonomy' => 'slotnova_employee', 'hide_empty' => false ) );

		$opening      = get_option( 'slotnova_opening_time', '09:00' );
		$closing      = get_option( 'slotnova_closing_time', '17:00' );
		$duration_val = get_option( 'slotnova_slot_duration', '60' );
		$duration     = (int) $duration_val;
		if ( $duration < 5 ) {
			$duration = 60;
		}

		$weekly_off = get_option( 'slotnova_weekly_off_days', array() );
		if ( ! is_array( $weekly_off ) ) {
			$weekly_off = array();
		}
		$specific_off     = get_option( 'slotnova_specific_off_days', '' );
		$all_off_days_arr = $weekly_off;
		if ( ! empty( $specific_off ) ) {
			$specific_off_arr = array_map( 'trim', explode( ',', $specific_off ) );
			$all_off_days_arr = array_merge( $all_off_days_arr, $specific_off_arr );
		}
		$combined_off_days = implode( ',', $all_off_days_arr );
		?>
		<!-- Manual Booking Modal -->
		<div id="slotnova-manual-booking-modal" class="slotnova-modal-overlay slotnova-is-hidden">
			<div class="slotnova-modal-content slotnova-manual-modal-content">
				<div class="slotnova-modal-header slotnova-classic-modal-header">
					<div class="slotnova-modal-header-title">
						<h2>
							<span class="dashicons dashicons-plus-alt2" style="color: #4f46e5; margin-right: 6px;"></span>
							<?php esc_html_e( 'Add Manual Booking', 'slotnova-booking' ); ?>
						</h2>
					</div>
					<button type="button" class="slotnova-modal-close" aria-label="<?php esc_attr_e( 'Close', 'slotnova-booking' ); ?>">&times;</button>
				</div>
				<form id="slotnova-manual-booking-form">
					<div class="slotnova-modal-body slotnova-manual-modal-body">
						
						<!-- Section 1: Customer Billing Details -->
						<div class="slotnova-modal-section">
							<h4 class="slotnova-section-title">
								<span class="dashicons dashicons-id"></span>
								<?php esc_html_e( 'Customer Billing Information (WooCommerce)', 'slotnova-booking' ); ?>
							</h4>
							<div class="slotnova-form-row">
								<div class="slotnova-form-group">
									<label for="mb_billing_first_name"><?php esc_html_e( 'First Name *', 'slotnova-booking' ); ?></label>
									<input type="text" id="mb_billing_first_name" name="billing_first_name" required placeholder="<?php esc_attr_e( 'e.g. John', 'slotnova-booking' ); ?>" class="slotnova-input-styled" />
								</div>
								<div class="slotnova-form-group">
									<label for="mb_billing_last_name"><?php esc_html_e( 'Last Name', 'slotnova-booking' ); ?></label>
									<input type="text" id="mb_billing_last_name" name="billing_last_name" placeholder="<?php esc_attr_e( 'e.g. Doe', 'slotnova-booking' ); ?>" class="slotnova-input-styled" />
								</div>
							</div>

							<div class="slotnova-form-row">
								<div class="slotnova-form-group">
									<label for="mb_billing_email"><?php esc_html_e( 'Email Address *', 'slotnova-booking' ); ?></label>
									<input type="email" id="mb_billing_email" name="billing_email" required placeholder="<?php esc_attr_e( 'e.g. john@example.com', 'slotnova-booking' ); ?>" class="slotnova-input-styled" />
								</div>
								<div class="slotnova-form-group">
									<label for="mb_billing_phone"><?php esc_html_e( 'Phone Number', 'slotnova-booking' ); ?></label>
									<input type="text" id="mb_billing_phone" name="billing_phone" placeholder="<?php esc_attr_e( 'e.g. +1 234 567 890', 'slotnova-booking' ); ?>" class="slotnova-input-styled" />
								</div>
							</div>

							<div class="slotnova-form-row">
								<div class="slotnova-form-group">
									<label for="mb_billing_company"><?php esc_html_e( 'Company Name', 'slotnova-booking' ); ?></label>
									<input type="text" id="mb_billing_company" name="billing_company" placeholder="<?php esc_attr_e( 'Optional', 'slotnova-booking' ); ?>" class="slotnova-input-styled" />
								</div>
								<div class="slotnova-form-group">
									<label for="mb_billing_address_1"><?php esc_html_e( 'Street Address', 'slotnova-booking' ); ?></label>
									<input type="text" id="mb_billing_address_1" name="billing_address_1" placeholder="<?php esc_attr_e( 'e.g. 123 Main Street', 'slotnova-booking' ); ?>" class="slotnova-input-styled" />
								</div>
							</div>

							<div class="slotnova-form-row">
								<div class="slotnova-form-group">
									<label for="mb_billing_city"><?php esc_html_e( 'City / Town', 'slotnova-booking' ); ?></label>
									<input type="text" id="mb_billing_city" name="billing_city" placeholder="<?php esc_attr_e( 'e.g. New York', 'slotnova-booking' ); ?>" class="slotnova-input-styled" />
								</div>
								<div class="slotnova-form-group">
									<label for="mb_billing_postcode"><?php esc_html_e( 'Postcode / ZIP', 'slotnova-booking' ); ?></label>
									<input type="text" id="mb_billing_postcode" name="billing_postcode" placeholder="<?php esc_attr_e( 'e.g. 10001', 'slotnova-booking' ); ?>" class="slotnova-input-styled" />
								</div>
							</div>
						</div>

						<!-- Section 2: Appointment Details -->
						<div class="slotnova-modal-section">
							<h4 class="slotnova-section-title">
								<span class="dashicons dashicons-calendar-alt"></span>
								<?php esc_html_e( 'Appointment & Booking Schedule', 'slotnova-booking' ); ?>
							</h4>

							<!-- Service Custom Dropdown -->
							<div class="slotnova-form-group slotnova-custom-select-wrapper">
								<label><?php esc_html_e( 'Select Service', 'slotnova-booking' ); ?></label>
								<div class="slotnova-custom-select" id="mb_service_dropdown">
									<div class="slotnova-select-trigger">
										<input type="text" class="slotnova-select-search-input" placeholder="<?php esc_attr_e( 'Choose a service...', 'slotnova-booking' ); ?>" autocomplete="off" />
										<div class="slotnova-select-arrow"></div>
									</div>
									<div class="slotnova-select-options">
										<div class="slotnova-select-options-list">
											<?php if ( ! is_wp_error( $all_services ) && ! empty( $all_services ) ) : ?>
												<?php foreach ( $all_services as $svc ) :
													$price         = get_term_meta( $svc->term_id, 'slotnova_service_price', true );
													$price_val     = floatval( $price );
													$price_display = ( $price_val > 0 ) ? wc_price( $price_val ) : __( 'Free', 'slotnova-booking' );
													$image_id      = get_term_meta( $svc->term_id, 'slotnova_image_id', true );
													$image_url     = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
													?>
													<div class="slotnova-select-option" data-value="<?php echo esc_attr( $svc->term_id ); ?>" data-name="<?php echo esc_attr( $svc->name ); ?>" data-price="<?php echo esc_attr( $price_val ); ?>">
														<?php if ( $image_url ) : ?>
															<img src="<?php echo esc_url( $image_url ); ?>" alt="" class="slotnova-option-img">
														<?php else : ?>
															<div class="slotnova-option-img-placeholder"></div>
														<?php endif; ?>
														<div class="slotnova-option-details">
															<span class="slotnova-option-name"><?php echo esc_html( $svc->name ); ?></span>
															<span class="slotnova-option-price"><?php echo esc_html( wp_strip_all_tags( $price_display ) ); ?></span>
														</div>
													</div>
												<?php endforeach; ?>
											<?php endif; ?>
										</div>
										<div class="slotnova-select-no-results slotnova-is-hidden"><?php esc_html_e( 'No results found', 'slotnova-booking' ); ?></div>
									</div>
								</div>
								<input type="hidden" name="service_id" id="mb_service_id" />
								<input type="hidden" name="service_name" id="mb_service_name" />
							</div>

							<!-- Employee Custom Dropdown -->
							<div class="slotnova-form-group slotnova-custom-select-wrapper">
								<label><?php esc_html_e( 'Select Staff / Employee', 'slotnova-booking' ); ?></label>
								<div class="slotnova-custom-select" id="mb_employee_dropdown">
									<div class="slotnova-select-trigger">
										<input type="text" class="slotnova-select-search-input" placeholder="<?php esc_attr_e( 'Choose an employee...', 'slotnova-booking' ); ?>" autocomplete="off" />
										<div class="slotnova-select-arrow"></div>
									</div>
									<div class="slotnova-select-options">
										<div class="slotnova-select-options-list">
											<?php if ( ! is_wp_error( $all_employees ) && ! empty( $all_employees ) ) : ?>
												<?php foreach ( $all_employees as $emp ) :
													$image_id  = get_term_meta( $emp->term_id, 'slotnova_image_id', true );
													$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
													?>
													<div class="slotnova-select-option" data-value="<?php echo esc_attr( $emp->term_id ); ?>" data-name="<?php echo esc_attr( $emp->name ); ?>">
														<?php if ( $image_url ) : ?>
															<img src="<?php echo esc_url( $image_url ); ?>" alt="" class="slotnova-option-img">
														<?php else : ?>
															<div class="slotnova-option-img-placeholder"></div>
														<?php endif; ?>
														<div class="slotnova-option-details">
															<span class="slotnova-option-name"><?php echo esc_html( $emp->name ); ?></span>
														</div>
													</div>
												<?php endforeach; ?>
											<?php endif; ?>
										</div>
										<div class="slotnova-select-no-results slotnova-is-hidden"><?php esc_html_e( 'No results found', 'slotnova-booking' ); ?></div>
									</div>
								</div>
								<input type="hidden" name="employee_id" id="mb_employee_id" />
								<input type="hidden" name="employee_name" id="mb_employee_name" />
							</div>

							<!-- Booking Date Inline Flatpickr -->
							<div class="slotnova-form-group">
								<label for="mb_booking_date"><?php esc_html_e( 'Select Date', 'slotnova-booking' ); ?></label>
								<input type="text" name="booking_date" id="mb_booking_date" required class="slotnova-is-hidden" data-off-days="<?php echo esc_attr( $combined_off_days ); ?>" data-opening-time="<?php echo esc_attr( $opening ); ?>" data-closing-time="<?php echo esc_attr( $closing ); ?>">
							</div>

							<!-- Time Slot Selection Pills Grid -->
							<div class="slotnova-form-group mb-time-slots-wrapper slotnova-is-hidden">
								<label><?php esc_html_e( 'Select Time Slot', 'slotnova-booking' ); ?></label>
								<div class="slotnova-time-pills-grid" id="mb_time_pills">
									<?php
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
								<input type="hidden" name="booking_time" id="mb_booking_time" required>
							</div>

							<!-- Booking Summary Box -->
							<div id="mb-slotnova-summary" class="slotnova-summary-box">
								<div class="slotnova-summary-header">
									<span class="slotnova-summary-icon">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
									</span>
									<h4 class="slotnova-summary-title"><?php esc_html_e( 'Booking Summary', 'slotnova-booking' ); ?></h4>
								</div>
								<div class="slotnova-summary-body">
									<div class="slotnova-summary-row">
										<span class="slotnova-summary-label"><?php esc_html_e( 'Service', 'slotnova-booking' ); ?></span>
										<span class="slotnova-summary-value" id="mb-summary-service-name">-</span>
									</div>
									<div class="slotnova-summary-row" id="mb-summary-employee-row">
										<span class="slotnova-summary-label"><?php esc_html_e( 'Employee', 'slotnova-booking' ); ?></span>
										<span class="slotnova-summary-value" id="mb-summary-employee-name">-</span>
									</div>
									<div class="slotnova-summary-row">
										<span class="slotnova-summary-label"><?php esc_html_e( 'Date', 'slotnova-booking' ); ?></span>
										<span class="slotnova-summary-value" id="mb-summary-booking-date">-</span>
									</div>
									<div class="slotnova-summary-row" id="mb-summary-time-row">
										<span class="slotnova-summary-label"><?php esc_html_e( 'Time', 'slotnova-booking' ); ?></span>
										<span class="slotnova-summary-value" id="mb-summary-booking-time">-</span>
									</div>
									<div class="slotnova-summary-divider"></div>
									<div class="slotnova-summary-row slotnova-summary-total">
										<span class="slotnova-summary-label"><?php esc_html_e( 'Total Amount', 'slotnova-booking' ); ?></span>
										<span class="slotnova-summary-value" id="mb-summary-service-price">-</span>
									</div>
								</div>
							</div>
						</div>

					</div>
					<div class="slotnova-modal-footer">
						<button type="button" class="slotnova-btn-modal-cancel slotnova-modal-close"><?php esc_html_e( 'Cancel', 'slotnova-booking' ); ?></button>
						<button type="submit" class="slotnova-btn-modal-primary">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Create Manual Booking', 'slotnova-booking' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Global Settings page.
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'slotnova-booking' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SlotNova Global Settings', 'slotnova-booking' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'slotnova_settings_group' );
				do_settings_sections( 'slotnova_settings_group' );

				$opening_time  = get_option( 'slotnova_opening_time', '09:00' );
				$closing_time  = get_option( 'slotnova_closing_time', '17:00' );
				$weekly_off    = get_option( 'slotnova_weekly_off_days', array() );
				if ( ! is_array( $weekly_off ) ) {
					$weekly_off = array();
				}

				$days_of_week = array(
					'Sunday'    => __( 'Sunday', 'slotnova-booking' ),
					'Monday'    => __( 'Monday', 'slotnova-booking' ),
					'Tuesday'   => __( 'Tuesday', 'slotnova-booking' ),
					'Wednesday' => __( 'Wednesday', 'slotnova-booking' ),
					'Thursday'  => __( 'Thursday', 'slotnova-booking' ),
					'Friday'    => __( 'Friday', 'slotnova-booking' ),
					'Saturday'  => __( 'Saturday', 'slotnova-booking' ),
				);
				?>
				<h2><?php esc_html_e( 'Smart Time Slot Generator', 'slotnova-booking' ); ?></h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Enable Time Slots', 'slotnova-booking' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="slotnova_enable_time_slots" value="yes" <?php checked( get_option( 'slotnova_enable_time_slots', 'yes' ), 'yes' ); ?> />
									<?php esc_html_e( 'Enable time slot selection for bookings.', 'slotnova-booking' ); ?>
								</label>
							</fieldset>
							<p class="description"><?php esc_html_e( 'If disabled, customers will only select a Date for their booking.', 'slotnova-booking' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Opening Time', 'slotnova-booking' ); ?></th>
						<td>
							<input type="time" name="slotnova_opening_time" value="<?php echo esc_attr( $opening_time ); ?>" />
							<p class="description"><?php esc_html_e( 'When does your business open? (e.g. 09:00 AM)', 'slotnova-booking' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Closing Time', 'slotnova-booking' ); ?></th>
						<td>
							<input type="time" name="slotnova_closing_time" value="<?php echo esc_attr( $closing_time ); ?>" />
							<p class="description"><?php esc_html_e( 'When does your business close? (e.g. 05:00 PM)', 'slotnova-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Slot Duration (Minutes)', 'slotnova-booking' ); ?></th>
						<td>
							<input type="number" name="slotnova_slot_duration" value="<?php echo esc_attr( get_option( 'slotnova_slot_duration', '60' ) ); ?>" class="regular-text" step="5" min="5" />
							<p class="description"><?php esc_html_e( 'Duration of each booking slot (e.g., 60 for 1 hour).', 'slotnova-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Off-Days & Vacations', 'slotnova-booking' ); ?></h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Weekly Off-Days', 'slotnova-booking' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( $days_of_week as $day_key => $day_label ) : ?>
									<label class="slotnova-checkbox-label">
										<input type="checkbox" name="slotnova_weekly_off_days[]" value="<?php echo esc_attr( $day_key ); ?>" class="slotnova-checkbox-input" <?php checked( in_array( $day_key, $weekly_off, true ) ); ?> />
										<?php echo esc_html( $day_label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Select days of the week when your business is always closed.', 'slotnova-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Specific Vacations', 'slotnova-booking' ); ?></th>
						<td>
							<input type="text" name="slotnova_specific_off_days" id="slotnova_specific_off_days" class="large-text" value="<?php echo esc_attr( get_option( 'slotnova_specific_off_days' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Select multiple specific dates you will be closed.', 'slotnova-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Style & Theme Controls', 'slotnova-booking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Customize colors and visual styling of your booking forms.', 'slotnova-booking' ); ?></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Primary Theme Color', 'slotnova-booking' ); ?></th>
						<td>
							<input type="text" name="slotnova_primary_color" value="<?php echo esc_attr( get_option( 'slotnova_primary_color', '#2271b1' ) ); ?>" class="slotnova-color-picker" data-default-color="#2271b1" />
							<p class="description"><?php esc_html_e( 'Primary accent color for active buttons, time pills, focus borders, and calendar selections.', 'slotnova-booking' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Hover / Accent Color', 'slotnova-booking' ); ?></th>
						<td>
							<input type="text" name="slotnova_accent_color" value="<?php echo esc_attr( get_option( 'slotnova_accent_color', '#135e96' ) ); ?>" class="slotnova-color-picker" data-default-color="#135e96" />
							<p class="description"><?php esc_html_e( 'Hover state color for interactive buttons and pills.', 'slotnova-booking' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Card & Container Background', 'slotnova-booking' ); ?></th>
						<td>
							<input type="text" name="slotnova_bg_color" value="<?php echo esc_attr( get_option( 'slotnova_bg_color', '#ffffff' ) ); ?>" class="slotnova-color-picker" data-default-color="#ffffff" />
							<p class="description"><?php esc_html_e( 'Background color for booking summary cards and dropdown popups.', 'slotnova-booking' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Text & Title Color', 'slotnova-booking' ); ?></th>
						<td>
							<input type="text" name="slotnova_text_color" value="<?php echo esc_attr( get_option( 'slotnova_text_color', '#0f172a' ) ); ?>" class="slotnova-color-picker" data-default-color="#0f172a" />
							<p class="description"><?php esc_html_e( 'Color for text labels, service names, and section titles.', 'slotnova-booking' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Corner Rounding (Border Radius)', 'slotnova-booking' ); ?></th>
						<td>
							<?php $radius = get_option( 'slotnova_border_radius', '12px' ); ?>
							<select name="slotnova_border_radius">
								<option value="6px" <?php selected( $radius, '6px' ); ?>><?php esc_html_e( 'Compact (6px)', 'slotnova-booking' ); ?></option>
								<option value="8px" <?php selected( $radius, '8px' ); ?>><?php esc_html_e( 'Medium (8px)', 'slotnova-booking' ); ?></option>
								<option value="12px" <?php selected( $radius, '12px' ); ?>><?php esc_html_e( 'Rounded (12px)', 'slotnova-booking' ); ?></option>
								<option value="16px" <?php selected( $radius, '16px' ); ?>><?php esc_html_e( 'Extra Rounded (16px)', 'slotnova-booking' ); ?></option>
								<option value="24px" <?php selected( $radius, '24px' ); ?>><?php esc_html_e( 'Pill (24px)', 'slotnova-booking' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Controls the roundness of buttons, summary cards, and dropdown triggers.', 'slotnova-booking' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Product Data tab panel content.
	 *
	 * @return void
	 */
	public function render_slotnova_product_tab_content() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		global $post;
		?>
		<div id="slotnova_booking_data" class="panel woocommerce_options_panel show_if_slotnova">
			<div class="options_group">
				<h3><?php esc_html_e( 'Services', 'slotnova-booking' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Add services, drag to reorder them, and set their price.', 'slotnova-booking' ); ?></p>
				<?php
				$all_services   = get_terms( array( 'taxonomy' => 'slotnova_service', 'hide_empty' => false ) );
				$saved_services = get_post_meta( $post->ID, '_slotnova_product_services', true );
				if ( ! is_array( $saved_services ) ) {
					$saved_services = array();
				}
				?>
				<table class="wp-list-table widefat fixed striped slotnova-repeater-table" id="slotnova-services-table">
					<thead>
						<tr>
							<th width="5%" class="slotnova-align-center"></th>
							<th width="45%"><?php esc_html_e( 'Service', 'slotnova-booking' ); ?></th>
							<th width="40%"><?php esc_html_e( 'Price', 'slotnova-booking' ); ?></th>
							<th width="10%" class="slotnova-align-center"></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! is_wp_error( $all_services ) ) : ?>
							<?php foreach ( $saved_services as $saved ) :
								$price = $saved['price'];
								if ( '' === $price || null === $price ) {
									$price = get_term_meta( $saved['term_id'], 'slotnova_service_price', true );
								}
								?>
								<tr>
									<td class="slotnova-drag-handle">☰</td>
									<td>
										<select name="slotnova_repeater_service_id[]" class="slotnova-table-select slotnova-service-select">
											<option value=""><?php esc_html_e( 'Select Service...', 'slotnova-booking' ); ?></option>
											<?php foreach ( $all_services as $service ) : ?>
												<option value="<?php echo esc_attr( $service->term_id ); ?>" <?php selected( $saved['term_id'], $service->term_id ); ?>><?php echo esc_html( $service->name ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<input type="number" name="slotnova_repeater_service_price[]" value="<?php echo esc_attr( $price ); ?>" step="0.01" min="0" class="slotnova-table-input-price slotnova-service-price-input">
									</td>
									<td class="slotnova-align-center"><a href="#" class="slotnova-remove-row" title="<?php esc_attr_e( 'Remove', 'slotnova-booking' ); ?>"><span class="dashicons dashicons-trash"></span></a></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
				<p>
					<button type="button" class="button button-primary" id="slotnova-add-service"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Service', 'slotnova-booking' ); ?></button>
				</p>
			</div>

			<div class="options_group">
				<h3><?php esc_html_e( 'Employees', 'slotnova-booking' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Add employees and drag to reorder them.', 'slotnova-booking' ); ?></p>
				<?php
				$all_employees   = get_terms( array( 'taxonomy' => 'slotnova_employee', 'hide_empty' => false ) );
				$saved_employees = get_post_meta( $post->ID, '_slotnova_product_employees', true );
				if ( ! is_array( $saved_employees ) ) {
					$saved_employees = array();
				}
				?>
				<table class="wp-list-table widefat fixed striped slotnova-repeater-table" id="slotnova-employees-table">
					<thead>
						<tr>
							<th width="5%" class="slotnova-align-center"></th>
							<th width="85%"><?php esc_html_e( 'Employee', 'slotnova-booking' ); ?></th>
							<th width="10%" class="slotnova-align-center"></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! is_wp_error( $all_employees ) ) : ?>
							<?php foreach ( $saved_employees as $saved ) : ?>
								<tr>
									<td class="slotnova-drag-handle">☰</td>
									<td>
										<select name="slotnova_repeater_employee_id[]" class="slotnova-table-select">
											<option value=""><?php esc_html_e( 'Select Employee...', 'slotnova-booking' ); ?></option>
											<?php foreach ( $all_employees as $employee ) : ?>
												<option value="<?php echo esc_attr( $employee->term_id ); ?>" <?php selected( $saved['term_id'], $employee->term_id ); ?>><?php echo esc_html( $employee->name ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td class="slotnova-align-center"><a href="#" class="slotnova-remove-row" title="<?php esc_attr_e( 'Remove', 'slotnova-booking' ); ?>"><span class="dashicons dashicons-trash"></span></a></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
				<p>
					<button type="button" class="button button-primary" id="slotnova-add-employee"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Employee', 'slotnova-booking' ); ?></button>
				</p>
			</div>
		</div>

		<div id="slotnova_slot_manager_data" class="panel woocommerce_options_panel show_if_slotnova">
			<div class="options_group">
				<?php
				woocommerce_wp_select( array(
					'id'          => '_slotnova_enable_time_slots',
					'label'       => __( 'Enable Time Slots', 'slotnova-booking' ),
					'description' => __( 'Override the global time slots setting for this product.', 'slotnova-booking' ),
					'desc_tip'    => true,
					'options'     => array(
						'global' => __( 'Global Default', 'slotnova-booking' ),
						'yes'    => __( 'Yes (Enable)', 'slotnova-booking' ),
						'no'     => __( 'No (Disable)', 'slotnova-booking' ),
					),
				) );

				woocommerce_wp_text_input( array(
					'id'          => '_slotnova_opening_time',
					'label'       => __( 'Opening Time', 'slotnova-booking' ),
					'description' => __( 'Override global opening time (e.g., 09:00). Leave blank to use global settings.', 'slotnova-booking' ),
					'desc_tip'    => true,
					'type'        => 'time',
				) );

				woocommerce_wp_text_input( array(
					'id'          => '_slotnova_closing_time',
					'label'       => __( 'Closing Time', 'slotnova-booking' ),
					'description' => __( 'Override global closing time (e.g., 17:00). Leave blank to use global settings.', 'slotnova-booking' ),
					'desc_tip'    => true,
					'type'        => 'time',
				) );

				woocommerce_wp_text_input( array(
					'id'                => '_slotnova_slot_duration',
					'label'             => __( 'Slot Duration (Minutes)', 'slotnova-booking' ),
					'description'       => __( 'Duration in minutes. Leave blank to use global settings.', 'slotnova-booking' ),
					'desc_tip'          => true,
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => '5',
						'min'  => '5',
					),
				) );
				?>
			</div>
			<div class="options_group">
				<h3><?php esc_html_e( 'Off-Days & Vacations', 'slotnova-booking' ); ?></h3>
				<p class="description"><?php esc_html_e( 'These will override the global off-days. Leave both blank to use global settings.', 'slotnova-booking' ); ?></p>

				<?php
				$saved_weekly = get_post_meta( $post->ID, '_slotnova_weekly_off_days', true );
				if ( ! is_array( $saved_weekly ) ) {
					$saved_weekly = array();
				}
				$days_of_week = array(
					'Sunday'    => __( 'Sunday', 'slotnova-booking' ),
					'Monday'    => __( 'Monday', 'slotnova-booking' ),
					'Tuesday'   => __( 'Tuesday', 'slotnova-booking' ),
					'Wednesday' => __( 'Wednesday', 'slotnova-booking' ),
					'Thursday'  => __( 'Thursday', 'slotnova-booking' ),
					'Friday'    => __( 'Friday', 'slotnova-booking' ),
					'Saturday'  => __( 'Saturday', 'slotnova-booking' ),
				);
				?>
				<p class="form-field">
					<label for="_slotnova_weekly_off_days"><?php esc_html_e( 'Weekly Off-Days', 'slotnova-booking' ); ?></label>
					<span class="slotnova-flex-checkbox-group">
					<?php foreach ( $days_of_week as $day_key => $day_label ) : ?>
						<label class="slotnova-checkbox-label">
							<input type="checkbox" name="_slotnova_weekly_off_days[]" value="<?php echo esc_attr( $day_key ); ?>" class="slotnova-checkbox-input" <?php checked( in_array( $day_key, $saved_weekly, true ) ); ?> />
							<?php echo esc_html( $day_label ); ?>
						</label>
					<?php endforeach; ?>
					</span>
				</p>
				<?php
				woocommerce_wp_text_input( array(
					'id'          => '_slotnova_specific_off_days',
					'label'       => __( 'Specific Vacations', 'slotnova-booking' ),
					'description' => __( 'Select multiple specific dates you will be closed.', 'slotnova-booking' ),
					'desc_tip'    => true,
				) );
				?>
			</div>
			<?php do_action( 'slotnova_product_data_tab_content', $post ); ?>
		</div>
		<?php
	}

	/**
	 * Save SlotNova product tab meta data.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	public function save_slotnova_product_tab_data( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		$product_type = empty( $_POST['product-type'] ) ? 'simple' : sanitize_title( wp_unslash( $_POST['product-type'] ) );
		if ( 'slotnova' !== $product_type ) {
			return;
		}

		// Save Services
		$services_meta = array();
		$service_terms = array();
		if ( isset( $_POST['slotnova_repeater_service_id'] ) && is_array( $_POST['slotnova_repeater_service_id'] ) ) {
			$service_ids    = array_map( 'intval', wp_unslash( (array) $_POST['slotnova_repeater_service_id'] ) );
			$service_prices = isset( $_POST['slotnova_repeater_service_price'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['slotnova_repeater_service_price'] ) ) : array();

			foreach ( $service_ids as $index => $term_id ) {
				$term_id = intval( $term_id );
				if ( $term_id > 0 ) {
					$price           = isset( $service_prices[ $index ] ) ? sanitize_text_field( $service_prices[ $index ] ) : '';
					$services_meta[] = array(
						'term_id' => $term_id,
						'price'   => $price,
					);
					$service_terms[] = $term_id;
				}
			}
		}
		update_post_meta( $post_id, '_slotnova_product_services', $services_meta );
		wp_set_object_terms( $post_id, $service_terms, 'slotnova_service' );

		// Save Employees
		$employees_meta = array();
		$employee_terms = array();
		if ( isset( $_POST['slotnova_repeater_employee_id'] ) && is_array( $_POST['slotnova_repeater_employee_id'] ) ) {
			$employee_ids = array_map( 'intval', wp_unslash( (array) $_POST['slotnova_repeater_employee_id'] ) );

			foreach ( $employee_ids as $term_id ) {
				$term_id = intval( $term_id );
				if ( $term_id > 0 ) {
					$employees_meta[] = array( 'term_id' => $term_id );
					$employee_terms[] = $term_id;
				}
			}
		}
		update_post_meta( $post_id, '_slotnova_product_employees', $employees_meta );
		wp_set_object_terms( $post_id, $employee_terms, 'slotnova_employee' );

		// Save Slot Manager Data
		$opening_time = isset( $_POST['_slotnova_opening_time'] ) ? sanitize_text_field( wp_unslash( $_POST['_slotnova_opening_time'] ) ) : '';
		update_post_meta( $post_id, '_slotnova_opening_time', $opening_time );

		$closing_time = isset( $_POST['_slotnova_closing_time'] ) ? sanitize_text_field( wp_unslash( $_POST['_slotnova_closing_time'] ) ) : '';
		update_post_meta( $post_id, '_slotnova_closing_time', $closing_time );

		if ( isset( $_POST['_slotnova_slot_duration'] ) ) {
			update_post_meta( $post_id, '_slotnova_slot_duration', sanitize_text_field( wp_unslash( $_POST['_slotnova_slot_duration'] ) ) );
		}

		if ( isset( $_POST['_slotnova_enable_time_slots'] ) ) {
			update_post_meta( $post_id, '_slotnova_enable_time_slots', sanitize_text_field( wp_unslash( $_POST['_slotnova_enable_time_slots'] ) ) );
		} else {
			delete_post_meta( $post_id, '_slotnova_enable_time_slots' );
		}

		$weekly_off = isset( $_POST['_slotnova_weekly_off_days'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['_slotnova_weekly_off_days'] ) ) : array();
		update_post_meta( $post_id, '_slotnova_weekly_off_days', $weekly_off );

		$specific_off = isset( $_POST['_slotnova_specific_off_days'] ) ? sanitize_textarea_field( wp_unslash( $_POST['_slotnova_specific_off_days'] ) ) : '';
		update_post_meta( $post_id, '_slotnova_specific_off_days', $specific_off );

		// Set virtual product flag for slotnova
		update_post_meta( $post_id, '_virtual', 'yes' );

		// Calculate min service price to populate _regular_price and _price if not set
		if ( ! empty( $services_meta ) ) {
			$prices = array();
			foreach ( $services_meta as $sm ) {
				if ( isset( $sm['price'] ) && '' !== $sm['price'] && is_numeric( $sm['price'] ) ) {
					$prices[] = floatval( $sm['price'] );
				}
			}
			if ( ! empty( $prices ) ) {
				$min_price = min( $prices );
				update_post_meta( $post_id, '_regular_price', $min_price );
				update_post_meta( $post_id, '_price', $min_price );
			}
		}

		do_action( 'slotnova_save_product_tab_data', $post_id, $_POST );
	}
}
