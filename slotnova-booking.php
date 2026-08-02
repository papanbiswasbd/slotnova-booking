<?php
/**
 * Plugin Name: SlotNova Booking for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/slotnova-booking
 * Description: WooCommerce Booking Plugin for SPA Centers, Salons, and Service Businesses.
 * Version: 1.0.0
 * Author: SlotNova
 * Author URI: https://profiles.wordpress.org/papanbiswasbd/
 * Text Domain: slotnova-booking
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SlotNova\Booking
 */

namespace SlotNova\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'SLOTNOVA_BOOKING_VERSION', '1.0.0' );
define( 'SLOTNOVA_BOOKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'SLOTNOVA_BOOKING_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main SlotNova Booking Plugin Class
 */
final class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Main Plugin Instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once SLOTNOVA_BOOKING_PATH . 'includes/class-wc-product-slotnova.php';
		require_once SLOTNOVA_BOOKING_PATH . 'includes/class-slotnova-taxonomies.php';
		require_once SLOTNOVA_BOOKING_PATH . 'includes/class-slotnova-frontend.php';
		require_once SLOTNOVA_BOOKING_PATH . 'includes/class-slotnova-cart.php';

		if ( is_admin() ) {
			require_once SLOTNOVA_BOOKING_PATH . 'includes/class-slotnova-admin.php';
		}
	}

	/**
	 * Initialize hooks and instantiate components.
	 */
	private function init_hooks() {
		new Taxonomies();
		new Frontend();
		new Cart();

		if ( is_admin() ) {
			new Admin();
		}
	}
}

/**
 * Declare WooCommerce HPOS Compatibility.
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Check if WooCommerce is active before loading plugin.
 *
 * @return void
 */
function slotnova_booking_init() {
	if ( class_exists( 'WooCommerce' ) ) {
		Plugin::instance();
	} else {
		add_action( 'admin_notices', __NAMESPACE__ . '\slotnova_woocommerce_missing_notice' );
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\slotnova_booking_init' );

/**
 * Notice if WooCommerce is not active.
 *
 * @return void
 */
function slotnova_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'SlotNova Booking requires WooCommerce to be installed and active.', 'slotnova-booking' ); ?></p>
	</div>
	<?php
}
