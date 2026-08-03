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
	 * Get list of available SlotNova Addons dynamically from Cloudflare Worker API.
	 *
	 * @return array
	 */
	/**
	 * Get list of available SlotNova Addons dynamically from Cloudflare Worker API.
	 *
	 * @return array
	 */
	public function get_addons_catalog() {
		$worker_endpoint = get_option( 'slotnova_cloudflare_worker_endpoint', 'https://slotnova-booking.papan-biswas-bd.workers.dev/addons/download' );
		$list_url        = str_replace( '/addons/download', '/addons/list', $worker_endpoint );
		if ( false === strpos( $list_url, '/addons/list' ) ) {
			$list_url = add_query_arg( 'action', 'list', $worker_endpoint );
		}
		$list_url = add_query_arg( 't', time(), $list_url ); // Prevent cache header caching

		$response = wp_remote_get(
			$list_url,
			array(
				'timeout'   => 5,
				'sslverify' => true,
				'headers'   => array(
					'Cache-Control' => 'no-cache',
				),
			)
		);

		$catalog = array();
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			if ( is_array( $data ) ) {
				$catalog = $data;
			}
		}

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
			<h1 class="wp-heading-inline" style="display:none;"><?php esc_html_e( 'SlotNova Addons', 'slotnova-booking' ); ?></h1>
			<hr class="wp-header-end">

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
				<?php if ( ! empty( $catalog ) ) : ?>
					<?php foreach ( $catalog as $addon ) :
						$is_installed = file_exists( WP_PLUGIN_DIR . '/' . $addon['file'] );
						$is_active    = $is_installed && is_plugin_active( $addon['file'] );
						$lic_entry    = isset( $licenses[ $addon['slug'] ] ) ? $licenses[ $addon['slug'] ] : null;
						$has_license  = ! empty( $lic_entry );
						$saved_key    = is_array( $lic_entry ) ? ( isset( $lic_entry['key'] ) ? $lic_entry['key'] : '' ) : (string) $lic_entry;
					?>
						<div class="slotnova-addon-card slotnova-card-classic" data-type="<?php echo esc_attr( $addon['type'] ); ?>" data-installed="<?php echo $is_installed ? '1' : '0'; ?>">
							<div class="slotnova-classic-header">
								<div class="slotnova-addon-icon-box">
									<?php if ( ! empty( $addon['icon'] ) && ( 0 === strpos( $addon['icon'], 'http' ) || false !== strpos( $addon['icon'], '.' ) ) ) : ?>
										<img src="<?php echo esc_url( $addon['icon'] ); ?>" alt="<?php echo esc_attr( $addon['title'] ); ?>" />
									<?php else : ?>
										<span class="dashicons <?php echo esc_attr( ! empty( $addon['icon'] ) ? $addon['icon'] : ( 'pro' === $addon['type'] ? 'dashicons-star-filled' : 'dashicons-admin-plugins' ) ); ?>"></span>
									<?php endif; ?>
								</div>
								<div class="slotnova-classic-title-area">
									<div class="slotnova-title-row-top">
										<h3><?php echo esc_html( $addon['title'] ); ?></h3>
										<?php if ( 'pro' === $addon['type'] ) : ?>
											<span class="slotnova-badge-classic slotnova-badge-pro">PRO</span>
										<?php else : ?>
											<span class="slotnova-badge-classic slotnova-badge-free">FREE</span>
										<?php endif; ?>
									</div>
									<div class="slotnova-classic-author">
										<?php esc_html_e( 'By SlotNova', 'slotnova-booking' ); ?> &bull; <span class="slotnova-classic-version"><?php echo esc_html( isset( $addon['version'] ) ? 'v' . $addon['version'] : 'v1.0.0' ); ?></span>
									</div>
								</div>
							</div>

							<div class="slotnova-classic-body">
								<p><?php echo esc_html( $addon['description'] ); ?></p>
							</div>

							<div class="slotnova-classic-footer">
								<div class="slotnova-addon-card-notice" style="display: none;"></div>
								<?php if ( $is_active ) : ?>
									<span class="slotnova-classic-status slotnova-status-active">
										<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Active', 'slotnova-booking' ); ?>
									</span>
									<button type="button" class="button button-secondary slotnova-toggle-addon-btn slotnova-btn-classic-deactivate" data-file="<?php echo esc_attr( $addon['file'] ); ?>" data-action="deactivate">
										<?php esc_html_e( 'Deactivate', 'slotnova-booking' ); ?>
									</button>
								<?php elseif ( $is_installed ) : ?>
									<span class="slotnova-classic-status slotnova-status-installed">
										<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Installed', 'slotnova-booking' ); ?>
									</span>
									<button type="button" class="button button-primary slotnova-toggle-addon-btn slotnova-btn-classic-activate" data-file="<?php echo esc_attr( $addon['file'] ); ?>" data-action="activate">
										<?php esc_html_e( 'Activate', 'slotnova-booking' ); ?>
									</button>
								<?php else : ?>
									<span class="slotnova-classic-price-label"><?php echo esc_html( 'pro' === $addon['type'] ? ( ! empty( $addon['price'] ) ? $addon['price'] : '$3.99/mo' ) : __( 'Free', 'slotnova-booking' ) ); ?></span>
									<button type="button" class="button button-primary slotnova-install-addon-btn slotnova-btn-classic-install" data-slug="<?php echo esc_attr( $addon['slug'] ); ?>">
										<span class="dashicons dashicons-download"></span>
										<span><?php esc_html_e( 'Install Now', 'slotnova-booking' ); ?></span>
									</button>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="slotnova-empty-state-card" style="grid-column: 1 / -1; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 40px; text-align: center;">
						<div class="slotnova-empty-icon" style="margin-bottom: 12px; color: #94a3b8;">
							<span class="dashicons dashicons-admin-plugins" style="font-size: 36px; width: 36px; height: 36px;"></span>
						</div>
						<h3 style="margin: 0 0 6px 0; color: #0f172a; font-weight: 700;"><?php esc_html_e( 'No Addons Available Right Now', 'slotnova-booking' ); ?></h3>
						<p style="margin: 0; color: #64748b; font-size: 13px;"><?php esc_html_e( 'New extension packages will automatically appear here as soon as they are added to Cloudflare.', 'slotnova-booking' ); ?></p>
					</div>
				<?php endif; ?>
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

		$addon_slug = isset( $_POST['addon_slug'] ) ? sanitize_key( wp_unslash( $_POST['addon_slug'] ) ) : '';

		if ( empty( $addon_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid addon requested.', 'slotnova-booking' ) ) );
		}

		$site_url        = get_site_url();
		$domain          = wp_parse_url( $site_url, PHP_URL_HOST );
		$worker_endpoint = get_option( 'slotnova_cloudflare_worker_endpoint', 'https://slotnova-booking.papan-biswas-bd.workers.dev/addons/download' );
		$download_url    = add_query_arg(
			array(
				'slug'   => $addon_slug,
				'domain' => $domain,
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
