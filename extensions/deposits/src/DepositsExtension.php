<?php
/**
 * Deposits & Partial Payments Extension Implementation.
 *
 * @package SlotNova\Extensions\Deposits
 */

namespace SlotNova\Extensions\Deposits;

use SlotNova\Booking\ExtensionManager\Contracts\ExtensionInterface;
use SlotNova\Booking\ExtensionManager\API\SlotNovaApi;
use SlotNova\Extensions\Deposits\Services\DepositSettingsRenderer;
use SlotNova\Extensions\Deposits\Services\DepositCartCalculator;
use SlotNova\Extensions\Deposits\Services\DepositFrontendRenderer;
use SlotNova\Extensions\Deposits\Services\DepositOrderStatusManager;
use SlotNova\Extensions\Deposits\Services\DepositMyAccountManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DepositsExtension
 */
class DepositsExtension implements ExtensionInterface {

	/**
	 * Extension unique ID.
	 *
	 * @var string
	 */
	private string $id = 'deposits';

	/**
	 * Extension human-readable name.
	 *
	 * @var string
	 */
	private string $name = 'SlotNova Deposits & Partial Payments';

	/**
	 * Extension semver version string.
	 *
	 * @var string
	 */
	private string $version = '1.0.0';

	/**
	 * Get extension unique ID.
	 *
	 * @return string
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get extension name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get extension version string.
	 *
	 * @return string
	 */
	public function getVersion(): string {
		return $this->version;
	}

	/**
	 * Boot the extension when core is initializing active extensions.
	 *
	 * @param SlotNovaApi $api Main public API facade.
	 * @return void
	 */
	public function boot( SlotNovaApi $api ): void {
		$renderer     = new DepositSettingsRenderer();
		$calculator   = new DepositCartCalculator();
		$frontend     = new DepositFrontendRenderer();
		$orderManager = new DepositOrderStatusManager();
		$account      = new DepositMyAccountManager();

		if ( function_exists( 'add_action' ) ) {
			add_action( 'slotnova_register_settings', array( $renderer, 'registerSettings' ) );
			add_filter( 'slotnova_settings_vertical_tabs', array( $renderer, 'registerVerticalTab' ) );
			add_action( 'slotnova_settings_tab_content', array( $renderer, 'renderSettingsSection' ) );

			// Single Product Booking Page Payment Selection (After Time Slots)
			add_action( 'slotnova_after_time_slots', array( $frontend, 'renderPaymentOptionField' ) );
			add_filter( 'woocommerce_add_cart_item_data', array( $frontend, 'addCartItemData' ) );

			// Product Edit Page Deposit Settings Tab & Panel (Deposits Extension Feature)
			add_filter( 'woocommerce_product_data_tabs', array( $frontend, 'addProductDepositTab' ), 25 );
			add_action( 'woocommerce_product_data_panels', array( $frontend, 'renderProductDepositPanel' ) );
			add_action( 'woocommerce_process_product_meta', array( $frontend, 'saveProductDepositMeta' ) );

			// WooCommerce Cart & Checkout Deposit Hooks
			add_filter( 'woocommerce_order_button_text', array( $calculator, 'filterOrderButtonText' ) );
			add_action( 'woocommerce_review_order_before_order_total', array( $calculator, 'renderCheckoutPaymentPlanSelector' ), 5 );
			add_action( 'woocommerce_cart_totals_before_order_total', array( $calculator, 'renderCheckoutPaymentPlanSelector' ), 5 );
			add_action( 'woocommerce_checkout_update_order_review', array( $calculator, 'updatePaymentTypeFromCheckout' ) );
			add_action( 'wp_ajax_slotnova_update_cart_payment_type', array( $calculator, 'ajaxUpdateCartPaymentType' ) );
			add_action( 'wp_ajax_nopriv_slotnova_update_cart_payment_type', array( $calculator, 'ajaxUpdateCartPaymentType' ) );
			add_action( 'woocommerce_cart_calculate_fees', array( $calculator, 'calculateDeposit' ) );
			add_action( 'woocommerce_checkout_update_order_meta', array( $calculator, 'saveOrderDepositMeta' ) );
			add_action( 'woocommerce_order_details_after_order_table', array( $calculator, 'addOrderDetailsDepositInfo' ) );
			add_action( 'woocommerce_email_after_order_table', array( $calculator, 'addOrderDetailsDepositInfo' ) );
			add_action( 'admin_head', array( $calculator, 'renderAdminOrderTotalsScript' ) );

			// Filter Fee / Order Total Row Labels (Replace "Fees:" with "Remaining Balance:")
			add_filter( 'woocommerce_get_order_item_totals', array( $calculator, 'filterOrderTotalsLabels' ), 20, 2 );
			add_filter( 'woocommerce_order_item_get_name', array( $calculator, 'filterFeeItemName' ), 20, 2 );
			add_filter( 'gettext', array( $calculator, 'filterGettextFeeLabel' ), 20, 3 );

			// Custom WooCommerce Order Status "Partial Deposit" Hooks
			add_filter( 'woocommerce_register_shop_order_post_statuses', array( $orderManager, 'registerOrderStatus' ) );
			add_filter( 'wc_order_statuses', array( $orderManager, 'addOrderStatusToList' ) );
			add_filter( 'slotnova_bookings_query_statuses', array( $orderManager, 'filterBookingsQueryStatuses' ) );
			add_filter( 'woocommerce_payment_complete_order_status', array( $orderManager, 'filterPaymentCompleteOrderStatus' ), 999, 3 );
			add_filter( 'woocommerce_cod_process_payment_order_status', array( $orderManager, 'filterPaymentCompleteOrderStatus' ), 999, 3 );
			add_filter( 'woocommerce_bacs_process_payment_order_status', array( $orderManager, 'filterPaymentCompleteOrderStatus' ), 999, 3 );
			add_action( 'woocommerce_checkout_order_processed', array( $orderManager, 'setOrderDepositStatus' ), 999 );
			add_action( 'woocommerce_thankyou', array( $orderManager, 'setOrderDepositStatus' ), 999 );
			add_action( 'woocommerce_payment_complete', array( $orderManager, 'setOrderDepositStatus' ), 999 );

			// SlotNova Bookings Table & Modal Integrations
			add_filter( 'slotnova_calendar_events', array( $orderManager, 'filterBookingListData' ) );
			add_action( 'admin_head', array( $orderManager, 'printAdminHeadStyles' ) );

			// My Account Orders Table Column Enhancements (Deposits Extension Features)
			add_action( 'woocommerce_my_account_my_orders_column_order-total', array( $account, 'renderOrderTotalColumn' ) );
			add_filter( 'woocommerce_my_account_my_orders_actions', array( $account, 'filterMyOrdersActions' ), 10, 2 );

			// Direct Same-Order Pay Due Hooks
			add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $account, 'allowDepositOrderStatusForPayment' ), 10, 2 );
			add_filter( 'woocommerce_order_get_total', array( $account, 'filterOrderTotalForDuePayment' ), 10, 2 );
			add_action( 'woocommerce_payment_complete', array( $account, 'handleOrderDuePaymentComplete' ), 10 );
			add_action( 'woocommerce_order_status_completed', array( $account, 'handleOrderDuePaymentComplete' ), 10 );
			add_action( 'woocommerce_order_status_processing', array( $account, 'handleOrderDuePaymentComplete' ), 10 );
			add_action( 'woocommerce_thankyou', array( $account, 'handleOrderDuePaymentComplete' ), 10 );

			// Enqueue Extension Asset Files (CSS & JS)
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminAssets' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueueFrontendAssets' ) );
		}
	}

	/**
	 * Get dynamic assets URL whether extension is in plugins/ or uploads/ directory.
	 *
	 * @return string
	 */
	public function getAssetsUrl(): string {
		$extensionDir = wp_normalize_path( dirname( __DIR__ ) );
		$contentDir   = wp_normalize_path( WP_CONTENT_DIR );
		$contentUrl   = content_url();

		if ( 0 === strpos( $extensionDir, $contentDir ) ) {
			$relativePath = ltrim( substr( $extensionDir, strlen( $contentDir ) ), '/' );
			return trailingslashit( $contentUrl . '/' . $relativePath . '/assets' );
		}

		return plugins_url( 'extensions/deposits/assets/', SLOTNOVA_BOOKING_PATH . 'slotnova-booking.php' );
	}

	/**
	 * Enqueue Deposits extension admin assets.
	 *
	 * @return void
	 */
	public function enqueueAdminAssets(): void {
		$assets_url = $this->getAssetsUrl();
		wp_enqueue_style( 'slotnova-deposits-admin-css', $assets_url . 'css/deposits-admin.css', array(), '1.0.0' );
		wp_enqueue_script( 'slotnova-deposits-admin-js', $assets_url . 'js/deposits-admin.js', array( 'jquery' ), '1.0.0', true );
	}

	/**
	 * Enqueue Deposits extension frontend assets.
	 *
	 * @return void
	 */
	public function enqueueFrontendAssets(): void {
		$assets_url = $this->getAssetsUrl();
		wp_enqueue_style( 'slotnova-deposits-frontend-css', $assets_url . 'css/deposits-frontend.css', array(), '1.0.0' );
		wp_enqueue_script( 'slotnova-deposits-frontend-js', $assets_url . 'js/deposits-frontend.js', array( 'jquery' ), '1.0.0', true );
	}

	/**
	 * Triggered when extension is activated by the user.
	 *
	 * @return void
	 */
	public function activate(): void {
		if ( function_exists( 'get_option' ) && false === get_option( 'slotnova_deposit_enabled' ) ) {
			if ( function_exists( 'update_option' ) ) {
				update_option( 'slotnova_deposit_enabled', 'yes' );
				update_option( 'slotnova_deposit_type', 'percentage' );
				update_option( 'slotnova_deposit_amount', '20' );
			}
		}
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Triggered when extension is deactivated by the user.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Triggered when extension is uninstalled/deleted.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		if ( function_exists( 'delete_option' ) ) {
			delete_option( 'slotnova_deposit_enabled' );
			delete_option( 'slotnova_deposit_type' );
			delete_option( 'slotnova_deposit_amount' );
		}
	}
}

