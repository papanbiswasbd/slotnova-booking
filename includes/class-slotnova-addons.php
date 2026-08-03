<?php
/**
 * SlotNova Booking Addons & Extension Marketplace Manager
 *
 * @package SlotNova\Booking
 * @version 1.1.1
 */

namespace SlotNova\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class SlotNova_Addons
 */
class SlotNova_Addons {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_slotnova_install_addon', array( $this, 'ajax_install_addon' ) );
		add_action( 'wp_ajax_slotnova_toggle_addon', array( $this, 'ajax_toggle_addon' ) );
	}

	/**
	 * Get list of available SlotNova Addons.
	 *
	 * @return array
	 */
	public function get_addons_catalog() {
		$catalog = array(
			array(
				'slug'        => 'slotnova-sms-notifications',
				'title'       => __( 'SMS & WhatsApp Notifications', 'slotnova-booking' ),
				'type'        => 'free',
				'price'       => __( 'Free', 'slotnova-booking' ),
				'icon'        => 'dashicons-smartphone',
				'description' => __( 'Send automated SMS and WhatsApp booking confirmations and reminder alerts to customers and staff.', 'slotnova-booking' ),
				'version'     => '1.0.0',
				'file'        => 'slotnova-sms-notifications/slotnova-sms-notifications.php',
			),
			array(
				'slug'        => 'slotnova-google-calendar',
				'title'       => __( 'Google Calendar 2-Way Sync', 'slotnova-booking' ),
				'type'        => 'pro',
				'price'       => '$29',
				'icon'        => 'dashicons-calendar-alt',
				'description' => __( 'Automatically sync staff appointments with personal Google Calendars in real-time with 2-way conflict prevention.', 'slotnova-booking' ),
				'version'     => '1.0.0',
				'file'        => 'slotnova-google-calendar/slotnova-google-calendar.php',
			),
			array(
				'slug'        => 'slotnova-staff-commission',
				'title'       => __( 'Staff Commission & Payouts', 'slotnova-booking' ),
				'type'        => 'pro',
				'price'       => '$19',
				'icon'        => 'dashicons-groups',
				'description' => __( 'Calculate automatic staff commissions per service, generate performance reports, and handle payouts.', 'slotnova-booking' ),
				'version'     => '1.0.0',
				'file'        => 'slotnova-staff-commission/slotnova-staff-commission.php',
			),
			array(
				'slug'        => 'slotnova-deposit-payments',
				'title'       => __( 'Partial Deposits & Payments', 'slotnova-booking' ),
				'type'        => 'pro',
				'price'       => '$25',
				'icon'        => 'dashicons-money-alt',
				'description' => __( 'Allow customers to pay a deposit amount online during checkout and pay the remaining balance in person.', 'slotnova-booking' ),
				'version'     => '1.0.0',
				'file'        => 'slotnova-deposit-payments/slotnova-deposit-payments.php',
			),
			array(
				'slug'        => 'slotnova-custom-fields',
				'title'       => __( 'Custom Booking Form Fields', 'slotnova-booking' ),
				'type'        => 'free',
				'price'       => __( 'Free', 'slotnova-booking' ),
				'icon'        => 'dashicons-forms',
				'description' => __( 'Add custom input fields, textareas, checkboxes, and file upload inputs to customer booking forms.', 'slotnova-booking' ),
				'version'     => '1.0.0',
				'file'        => 'slotnova-custom-fields/slotnova-custom-fields.php',
			),
			array(
				'slug'        => 'slotnova-multi-location',
				'title'       => __( 'Multi-Location & Branches', 'slotnova-booking' ),
				'type'        => 'pro',
				'price'       => '$39',
				'icon'        => 'dashicons-location-alt',
				'description' => __( 'Manage multiple business branches, locations, working hours, and location-based staff assignments.', 'slotnova-booking' ),
				'version'     => '1.0.0',
				'file'        => 'slotnova-multi-location/slotnova-multi-location.php',
			),
		);

		return apply_filters( 'slotnova_addons_catalog', $catalog );
	}

	/**
	 * Render Addons Marketplace Page
	 *
	 * @return void
	 */
	public function render_addons_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'slotnova-booking' ) );
		}

		$catalog  = $this->get_addons_catalog();
		$licenses = get_option( 'slotnova_addon_licenses', array() );
		?>
		<div class="wrap slotnova-addons-wrap">
			<!-- Hero Header -->
			<div class="slotnova-hero-banner" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
				<div class="slotnova-hero-content">
					<div class="slotnova-hero-badges">
						<span class="slotnova-pill-badge slotnova-pill-pulse">
							<span class="slotnova-pulse-dot"></span> <?php esc_html_e( 'Extension Hub', 'slotnova-booking' ); ?>
						</span>
					</div>
					<h1 class="slotnova-hero-title" style="color: #ffffff;"><?php esc_html_e( 'SlotNova Addons & Extensions', 'slotnova-booking' ); ?></h1>
					<p class="slotnova-hero-subtitle" style="color: #94a3b8;"><?php esc_html_e( 'Supercharge your booking platform with free & pro modular extensions powered by Cloudflare R2 & Freemius Licensing.', 'slotnova-booking' ); ?></p>
				</div>
			</div>

			<!-- Filter Bar -->
			<div class="slotnova-stats-filter-bar slotnova-mt-20">
				<div class="slotnova-filter-preset-row" id="slotnova-addon-filter-tabs" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
					<span class="slotnova-preset-label"><?php esc_html_e( 'Filter Extensions:', 'slotnova-booking' ); ?></span>
					<button type="button" class="slotnova-preset-btn active" data-filter="all"><?php esc_html_e( 'All Addons', 'slotnova-booking' ); ?></button>
					<button type="button" class="slotnova-preset-btn" data-filter="free"><?php esc_html_e( 'Free Addons', 'slotnova-booking' ); ?></button>
					<button type="button" class="slotnova-preset-btn" data-filter="pro"><?php esc_html_e( 'Pro Extensions', 'slotnova-booking' ); ?></button>
					<button type="button" class="slotnova-preset-btn" data-filter="installed"><?php esc_html_e( 'Installed', 'slotnova-booking' ); ?></button>
				</div>
			</div>

			<!-- Addons Cards Grid -->
			<div class="slotnova-addons-grid">
				<?php foreach ( $catalog as $addon ) :
					$is_installed = file_exists( WP_PLUGIN_DIR . '/' . $addon['file'] );
					$is_active    = $is_installed && is_plugin_active( $addon['file'] );
					$saved_key    = isset( $licenses[ $addon['slug'] ] ) ? $licenses[ $addon['slug'] ] : '';
				?>
					<div class="slotnova-addon-card" data-type="<?php echo esc_attr( $addon['type'] ); ?>" data-installed="<?php echo $is_installed ? '1' : '0'; ?>">
						<div class="slotnova-addon-header">
							<div class="slotnova-addon-icon-box">
								<span class="dashicons <?php echo esc_attr( $addon['icon'] ); ?>"></span>
							</div>
							<div class="slotnova-addon-badge-wrap">
								<?php if ( 'pro' === $addon['type'] ) : ?>
									<span class="slotnova-badge status-on-hold"><?php esc_html_e( 'PRO', 'slotnova-booking' ); ?> &bull; <?php echo esc_html( $addon['price'] ); ?></span>
								<?php else : ?>
									<span class="slotnova-badge status-completed"><?php esc_html_e( 'FREE', 'slotnova-booking' ); ?></span>
								<?php endif; ?>
							</div>
						</div>

						<div class="slotnova-addon-body">
							<h3><?php echo esc_html( $addon['title'] ); ?></h3>
							<p><?php echo esc_html( $addon['description'] ); ?></p>
						</div>

						<div class="slotnova-addon-footer">
							<?php if ( $is_active ) : ?>
								<div class="slotnova-addon-status-active">
									<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Active', 'slotnova-booking' ); ?>
								</div>
								<button type="button" class="button button-secondary slotnova-toggle-addon-btn" data-file="<?php echo esc_attr( $addon['file'] ); ?>" data-action="deactivate">
									<?php esc_html_e( 'Deactivate', 'slotnova-booking' ); ?>
								</button>
							<?php elseif ( $is_installed ) : ?>
								<div class="slotnova-addon-status-installed">
									<?php esc_html_e( 'Installed', 'slotnova-booking' ); ?>
								</div>
								<button type="button" class="button button-primary slotnova-toggle-addon-btn" data-file="<?php echo esc_attr( $addon['file'] ); ?>" data-action="activate">
									<?php esc_html_e( 'Activate', 'slotnova-booking' ); ?>
								</button>
							<?php else : ?>
								<?php if ( 'pro' === $addon['type'] ) : ?>
									<div class="slotnova-license-input-wrap">
										<input type="text" class="slotnova-addon-license-field" data-slug="<?php echo esc_attr( $addon['slug'] ); ?>" value="<?php echo esc_attr( $saved_key ); ?>" placeholder="<?php esc_attr_e( 'Enter License Key...', 'slotnova-booking' ); ?>" />
									</div>
									<div class="slotnova-addon-actions-pair">
										<button type="button" class="button button-primary slotnova-install-addon-btn" data-slug="<?php echo esc_attr( $addon['slug'] ); ?>">
											<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 3px;"></span>
											<?php esc_html_e( 'Install Pro', 'slotnova-booking' ); ?>
										</button>
										<button type="button" class="button button-secondary slotnova-buy-addon-btn" data-slug="<?php echo esc_attr( $addon['slug'] ); ?>">
											<?php esc_html_e( 'Buy Now', 'slotnova-booking' ); ?>
										</button>
									</div>
								<?php else : ?>
									<button type="button" class="button button-primary slotnova-install-addon-btn" data-slug="<?php echo esc_attr( $addon['slug'] ); ?>" style="width: 100%;">
										<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 3px;"></span>
										<?php esc_html_e( 'Download & Activate', 'slotnova-booking' ); ?>
									</button>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX Handler: Install & Activate Addon via Cloudflare R2 Gateway
	 *
	 * @return void
	 */
	public function ajax_install_addon() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'slotnova-booking' ) ) );
		}

		check_ajax_referer( 'slotnova_admin_nonce', 'security' );

		$addon_slug  = isset( $_POST['addon_slug'] ) ? sanitize_key( wp_unslash( $_POST['addon_slug'] ) ) : '';
		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

		if ( empty( $addon_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid addon requested.', 'slotnova-booking' ) ) );
		}

		// Save license key if provided
		if ( ! empty( $license_key ) ) {
			$licenses                = get_option( 'slotnova_addon_licenses', array() );
			$licenses[ $addon_slug ] = $license_key;
			update_option( 'slotnova_addon_licenses', $licenses );
		}

		$site_url     = get_site_url();
		$domain       = wp_parse_url( $site_url, PHP_URL_HOST );
		
		// Cloudflare Worker API Download Gateway
		$worker_endpoint = get_option( 'slotnova_cloudflare_worker_endpoint', 'https://api.slotnova.com/addons/download' );
		$download_url    = add_query_arg(
			array(
				'slug'        => $addon_slug,
				'license_key' => $license_key,
				'domain'      => $domain,
			),
			$worker_endpoint
		);

		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $download_url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Activate plugin
		$plugin_file = $addon_slug . '/' . $addon_slug . '.php';
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			activate_plugin( $plugin_file );
		}

		wp_send_json_success( array( 'message' => __( 'Addon installed and activated successfully!', 'slotnova-booking' ) ) );
	}

	/**
	 * AJAX Handler: Activate or Deactivate Addon
	 *
	 * @return void
	 */
	public function ajax_toggle_addon() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'slotnova-booking' ) ) );
		}

		check_ajax_referer( 'slotnova_admin_nonce', 'security' );

		$plugin_file = isset( $_POST['file'] ) ? sanitize_text_field( wp_unslash( $_POST['file'] ) ) : '';
		$action_type = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : '';

		if ( empty( $plugin_file ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin file.', 'slotnova-booking' ) ) );
		}

		if ( 'activate' === $action_type ) {
			activate_plugin( $plugin_file );
			wp_send_json_success( array( 'message' => __( 'Addon activated!', 'slotnova-booking' ) ) );
		} else {
			deactivate_plugins( $plugin_file );
			wp_send_json_success( array( 'message' => __( 'Addon deactivated.', 'slotnova-booking' ) ) );
		}
	}
}
