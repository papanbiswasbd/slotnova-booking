<?php
/**
 * SlotNova Group Booking Extension Main Class.
 *
 * Implements ExtensionInterface contract for SlotNova Extension Manager engine.
 *
 * @package SlotNova\Extensions\GroupBooking
 */

namespace SlotNova\Extensions\GroupBooking;

use SlotNova\Booking\ExtensionManager\Contracts\ExtensionInterface;
use SlotNova\Booking\ExtensionManager\API\SlotNovaApi;
use SlotNova\Extensions\GroupBooking\Repositories\ParticipantRepository;
use SlotNova\Extensions\GroupBooking\Repositories\AttendanceRepository;
use SlotNova\Extensions\GroupBooking\Services\CapacityValidationService;
use SlotNova\Extensions\GroupBooking\Services\PricingEngineService;
use SlotNova\Extensions\GroupBooking\Services\AttendanceService;
use SlotNova\Extensions\GroupBooking\Services\EmailNotificationService;
use SlotNova\Extensions\GroupBooking\Integrations\SlotNovaCoreBridge;
use SlotNova\Extensions\GroupBooking\WooCommerce\WcProductMetaManager;
use SlotNova\Extensions\GroupBooking\WooCommerce\WcCartManager;
use SlotNova\Extensions\GroupBooking\WooCommerce\WcCheckoutValidator;
use SlotNova\Extensions\GroupBooking\WooCommerce\WcOrderManager;
use SlotNova\Extensions\GroupBooking\Admin\Controllers\AdminSettingsController;
use SlotNova\Extensions\GroupBooking\Admin\Controllers\AttendanceController;
use SlotNova\Extensions\GroupBooking\Admin\Controllers\ReportsController;
use SlotNova\Extensions\GroupBooking\Frontend\Components\CapacityBadgeRenderer;
use SlotNova\Extensions\GroupBooking\Frontend\Components\ParticipantFormRenderer;
use SlotNova\Extensions\GroupBooking\Frontend\Components\PricingCalculatorRenderer;
use SlotNova\Extensions\GroupBooking\Frontend\MyAccount\CustomerDashboardManager;
use SlotNova\Extensions\GroupBooking\Ajax\AjaxHandler;
use SlotNova\Extensions\GroupBooking\REST\CapacityRestController;
use SlotNova\Extensions\GroupBooking\REST\ParticipantRestController;
use SlotNova\Extensions\GroupBooking\REST\AttendanceRestController;
use SlotNova\Extensions\GroupBooking\Helpers\GroupBookingHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GroupBookingExtension implements ExtensionInterface {

	private string $id      = 'group-booking';
	private string $name    = 'SlotNova Group Booking';
	private string $version = '1.0.0';

	public ParticipantRepository $participantRepo;
	public AttendanceRepository $attendanceRepo;
	public CapacityValidationService $capacityService;
	public PricingEngineService $pricingEngine;
	public AttendanceService $attendanceService;
	public EmailNotificationService $emailService;
	public SlotNovaCoreBridge $coreBridge;

	public function getId(): string {
		return $this->id;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getVersion(): string {
		return $this->version;
	}

	public function boot( SlotNovaApi $api ): void {
		// Instantiate Repositories
		$this->participantRepo = new ParticipantRepository();
		$this->attendanceRepo  = new AttendanceRepository();

		// Instantiate Services
		$this->emailService    = new EmailNotificationService();
		$this->capacityService = new CapacityValidationService( $this->participantRepo );
		$this->pricingEngine   = new PricingEngineService();
		$this->attendanceService = new AttendanceService( $this->attendanceRepo, $this->participantRepo );
		$this->coreBridge      = new SlotNovaCoreBridge( $api );

		// Instantiate WooCommerce & UI Managers
		$productMeta      = new WcProductMetaManager();
		$cartManager      = new WcCartManager( $this->capacityService, $this->pricingEngine );
		$checkoutValidator= new WcCheckoutValidator( $this->capacityService );
		$orderManager     = new WcOrderManager( $this->participantRepo );
		$adminSettings    = new AdminSettingsController();
		$adminAttendance  = new AttendanceController( $this->participantRepo, $this->attendanceService );
		$adminReports     = new ReportsController( $this->participantRepo, $this->attendanceRepo );

		$customerDashboard= new CustomerDashboardManager( $this->participantRepo );

		$ajaxHandler      = new AjaxHandler( $this->capacityService, $this->pricingEngine, $this->attendanceService );

		if ( function_exists( 'add_action' ) ) {
			// SlotNova Admin Settings Hooks
			add_action( 'slotnova_register_settings', array( $adminSettings, 'registerSettings' ) );
			add_filter( 'slotnova_settings_vertical_tabs', array( $adminSettings, 'registerVerticalTab' ) );
			add_action( 'slotnova_settings_tab_content', array( $adminSettings, 'renderSettingsSection' ) );

			// Admin Menu Items
			add_action( 'admin_menu', array( $adminAttendance, 'registerAdminMenu' ) );
			add_action( 'admin_menu', array( $adminReports, 'registerAdminMenu' ) );

			// Product Edit Page Meta Box Panel
			add_filter( 'woocommerce_product_data_tabs', array( $productMeta, 'addProductDataTab' ), 30 );
			add_action( 'woocommerce_product_data_panels', array( $productMeta, 'renderProductDataPanel' ) );
			add_action( 'woocommerce_process_product_meta', array( $productMeta, 'saveProductMeta' ) );

			// Single Product Booking Components (Rendered after Time Slots)
			add_action( 'slotnova_after_time_slots', function( $productParam = null ) {
				$pId = 0;
				if ( is_numeric( $productParam ) && (int) $productParam > 0 ) {
					$pId = (int) $productParam;
				} elseif ( is_object( $productParam ) && method_exists( $productParam, 'get_id' ) ) {
					$pId = (int) $productParam->get_id();
				} else {
					global $product;
					if ( $product && is_object( $product ) && method_exists( $product, 'get_id' ) ) {
						$pId = (int) $product->get_id();
					}
				}

				if ( $pId > 0 ) {
					$form = new ParticipantFormRenderer( $this->capacityService );
					$calc = new PricingCalculatorRenderer( $this->pricingEngine );

					$form->renderForm( $pId );
					$calc->renderPricingBreakdown( $pId );
				}
			} );

			// Filter Core Plugin Booked Slots for Group Capacity (Only disable slot if TRULY fully booked)
			add_filter( 'slotnova_get_booked_slots', function( $booked_slots, $product_id, $target_date, $service_id, $employee_id ) {
				if ( ! GroupBookingHelper::isGroupBookingEnabled( (int) $product_id ) ) {
					return $booked_slots;
				}

				$fully_booked_slots = array();

				if ( is_array( $booked_slots ) && ! empty( $booked_slots ) ) {
					foreach ( $booked_slots as $slot ) {
						$cap = $this->capacityService->getSlotCapacity( (int) $product_id, (int) $service_id, (string) $target_date, (string) $slot );
						if ( $cap->isFull || $cap->remainingSeats <= 0 ) {
							$fully_booked_slots[] = $slot;
						}
					}
				}

				return $fully_booked_slots;
			}, 10, 5 );

			// WooCommerce Cart & Checkout Hooks
			add_filter( 'woocommerce_add_to_cart_validation', array( $cartManager, 'validateAddToCart' ), 10, 3 );
			add_filter( 'woocommerce_add_cart_item_data', array( $cartManager, 'addCartItemData' ), 10, 3 );
			add_action( 'woocommerce_before_calculate_totals', array( $cartManager, 'updateCartItemPrice' ), 10, 1 );
			add_filter( 'woocommerce_get_item_data', array( $cartManager, 'displayCartItemMeta' ), 10, 2 );

			add_action( 'woocommerce_after_checkout_validation', array( $checkoutValidator, 'validateCheckout' ), 10, 2 );

			add_action( 'woocommerce_checkout_create_order_line_item', array( $orderManager, 'saveOrderLineItemMeta' ), 10, 4 );
			add_action( 'woocommerce_checkout_order_processed', array( $orderManager, 'syncOrderParticipantsToDb' ), 10, 1 );
			add_action( 'woocommerce_order_status_changed', array( $orderManager, 'handleOrderStatusChange' ), 10, 4 );

			// My Account Order Details
			add_action( 'woocommerce_order_details_after_order_table', array( $customerDashboard, 'renderOrderDetailsParticipants' ) );

			// Register REST API Endpoints
			add_action( 'rest_api_init', function() {
				$capRest = new CapacityRestController( $this->capacityService );
				$partRest= new ParticipantRestController( $this->participantRepo );
				$attRest = new AttendanceRestController( $this->attendanceService );

				$capRest->register_routes();
				$partRest->register_routes();
				$attRest->register_routes();
			} );

			// Register AJAX Hooks
			$ajaxHandler->registerHooks();

			// Enqueue Assets
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminAssets' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueueFrontendAssets' ) );
		}
	}

	public function getAssetsUrl(): string {
		$extensionDir = wp_normalize_path( dirname( __DIR__ ) );
		$contentDir   = wp_normalize_path( WP_CONTENT_DIR );
		$contentUrl   = content_url();

		if ( 0 === strpos( $extensionDir, $contentDir ) ) {
			$relativePath = ltrim( substr( $extensionDir, strlen( $contentDir ) ), '/' );
			return trailingslashit( $contentUrl . '/' . $relativePath . '/assets' );
		}

		return plugins_url( 'extensions/group-booking/assets/', SLOTNOVA_BOOKING_PATH . 'slotnova-booking.php' );
	}

	public function enqueueAdminAssets(): void {
		$assets_url = $this->getAssetsUrl();
		wp_enqueue_style( 'slotnova-group-admin-css', $assets_url . 'css/group-booking-admin.css', array(), $this->version );
		wp_enqueue_script( 'slotnova-group-admin-js', $assets_url . 'js/group-booking-admin.js', array( 'jquery' ), $this->version, true );
	}

	public function enqueueFrontendAssets(): void {
		$assets_url = $this->getAssetsUrl();
		wp_enqueue_style( 'slotnova-group-frontend-css', $assets_url . 'css/group-booking-frontend.css', array(), $this->version );
		wp_enqueue_script( 'slotnova-group-frontend-js', $assets_url . 'js/group-booking-frontend.js', array( 'jquery' ), $this->version, true );

		global $post;
		$productId = ( is_singular( 'product' ) && $post ) ? $post->ID : 0;

		wp_localize_script(
			'slotnova-group-frontend-js',
			'slotnova_group_vars',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'slotnova_group_nonce' ),
				'product_id' => $productId,
			)
		);
	}

	public function activate(): void {
		$this->createTables();

		if ( function_exists( 'get_option' ) && false === get_option( 'slotnova_group_enabled' ) ) {
			if ( function_exists( 'update_option' ) ) {
				update_option( 'slotnova_group_enabled', 'yes' );
				update_option( 'slotnova_group_default_max_capacity', '20' );
				update_option( 'slotnova_group_default_min_capacity', '1' );
				update_option( 'slotnova_group_default_pricing_mode', 'per_person' );
			}
		}

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	public function deactivate(): void {
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	public function uninstall(): void {
		if ( function_exists( 'delete_option' ) ) {
			delete_option( 'slotnova_group_enabled' );
			delete_option( 'slotnova_group_default_max_capacity' );
			delete_option( 'slotnova_group_default_min_capacity' );
			delete_option( 'slotnova_group_default_pricing_mode' );
		}
	}

	/**
	 * Create database custom tables using dbDelta().
	 *
	 * @return void
	 */
	private function createTables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$tableParticipants = $wpdb->prefix . 'slotnova_group_participants';
		$tableAttendance   = $wpdb->prefix . 'slotnova_group_attendance';

		$sqlParticipants = "CREATE TABLE {$tableParticipants} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			order_item_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			service_id BIGINT(20) UNSIGNED NOT NULL,
			booking_date DATE NOT NULL,
			booking_time VARCHAR(50) DEFAULT '',
			customer_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			participant_name VARCHAR(191) NOT NULL,
			participant_email VARCHAR(191) DEFAULT '',
			participant_phone VARCHAR(50) DEFAULT '',
			participant_gender VARCHAR(20) DEFAULT '',
			participant_age INT(3) DEFAULT NULL,
			participant_notes TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY order_item_idx (order_id, order_item_id),
			KEY slot_idx (product_id, service_id, booking_date, booking_time),
			KEY email_idx (participant_email)
		) {$charset_collate};";

		$sqlAttendance = "CREATE TABLE {$tableAttendance} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			participant_id BIGINT(20) UNSIGNED NOT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			service_id BIGINT(20) UNSIGNED NOT NULL,
			booking_date DATE NOT NULL,
			booking_time VARCHAR(50) DEFAULT '',
			attendance_status VARCHAR(50) NOT NULL DEFAULT 'pending',
			marked_by BIGINT(20) UNSIGNED DEFAULT 0,
			marked_at DATETIME DEFAULT NULL,
			notes TEXT DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY participant_idx (participant_id),
			KEY slot_status_idx (product_id, service_id, booking_date, booking_time, attendance_status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sqlParticipants );
		dbDelta( $sqlAttendance );
	}
}
