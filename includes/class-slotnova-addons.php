<?php
/**
 * SlotNova Booking Extensions Manager & Marketplace Admin UI
 *
 * Dynamically loads extension catalog live from Cloudflare Worker & R2.
 *
 * @package SlotNova\Booking
 * @version 1.1.1
 */

namespace SlotNova\Booking;

use SlotNova\Booking\ExtensionManager\ExtensionManager;
use SlotNova\Booking\ExtensionManager\Repositories\ExtensionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class SlotNova_Addons
 */
class SlotNova_Addons {

	private ExtensionManager $extensionManager;
	private ExtensionRepository $repository;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->extensionManager = new ExtensionManager();
		$this->repository       = new ExtensionRepository();

		// AJAX Handlers for Extension System
		add_action( 'wp_ajax_slotnova_install_extension', array( $this, 'ajax_install_extension' ) );
		add_action( 'wp_ajax_slotnova_toggle_extension', array( $this, 'ajax_toggle_extension' ) );
		add_action( 'wp_ajax_slotnova_update_extension', array( $this, 'ajax_update_extension' ) );
		add_action( 'wp_ajax_slotnova_uninstall_extension', array( $this, 'ajax_uninstall_extension' ) );

		// Direct GET Fallback Handler
		add_action( 'admin_init', array( $this, 'handle_get_actions' ) );

		// Legacy aliases
		add_action( 'wp_ajax_slotnova_install_addon', array( $this, 'ajax_install_extension' ) );
		add_action( 'wp_ajax_slotnova_toggle_addon', array( $this, 'ajax_toggle_extension' ) );
	}

	/**
	 * Get available remote extension catalog live from Cloudflare Worker API.
	 *
	 * @return array
	 */
	public function get_extensions_catalog(): array {
		$worker_endpoint = get_option( 'slotnova_cloudflare_worker_endpoint', get_option( 'slotnova_api_endpoint', 'https://slotnova-booking.papan-biswas-bd.workers.dev/addons/download' ) );

		// Format Cloudflare Worker List URL (/addons/list)
		$list_url = str_replace( '/addons/download', '/addons/list', $worker_endpoint );
		if ( false === strpos( $list_url, '/addons/list' ) ) {
			$list_url = str_replace( '/download', '/list', $list_url );
		}
		if ( false === strpos( $list_url, '/list' ) ) {
			$list_url = add_query_arg( 'action', 'list', $list_url );
		}
		$list_url = add_query_arg( 't', time(), $list_url ); // Cache buster

		$response = wp_remote_get(
			$list_url,
			array(
				'timeout'   => 8,
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
				foreach ( $data as $item ) {
					$id = $item['slug'] ?? ( $item['id'] ?? '' );
					if ( empty( $id ) ) {
						continue;
					}
					$catalog[] = [
						'id'           => $id,
						'slug'         => $id,
						'title'        => $item['title'] ?? ( $item['name'] ?? $id ),
						'name'         => $item['title'] ?? ( $item['name'] ?? $id ),
						'type'         => $item['type'] ?? 'pro',
						'price'        => $item['price'] ?? ( 'pro' === ( $item['type'] ?? '' ) ? '$3.99' : 'Free' ),
						'version'      => $item['version'] ?? '1.0.0',
						'description'  => $item['description'] ?? '',
						'icon'         => $item['icon'] ?? 'dashicons-admin-plugins',
						'purchase_url' => $item['purchase_url'] ?? ( $item['buy_url'] ?? '' ),
						'buy_url'      => $item['purchase_url'] ?? ( $item['buy_url'] ?? '' ),
						'demo_url'     => $item['demo_url'] ?? '',
						'settings_url' => $item['settings_url'] ?? '',
					];
				}
				update_option( 'slotnova_extensions_known_catalog', $catalog );
			}
		} elseif ( empty( $catalog ) ) {
			$catalog = get_option( 'slotnova_extensions_known_catalog', array() );
		}

		return apply_filters( 'slotnova_extensions_catalog', $catalog );
	}

	/**
	 * Render Extensions Manager Admin Screen
	 *
	 * @return void
	 */
	public function render_addons_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'slotnova-booking' ) );
		}

		$catalog            = $this->get_extensions_catalog();
		$installedManifests = $this->extensionManager->scan();
		$installedStates    = $this->repository->getAllInstalled();
		$nonce              = wp_create_nonce( 'slotnova_extensions_nonce' );
		?>
		<div class="wrap slotnova-addons-wrap">
			<h1 class="wp-heading-inline" style="display:none;"><?php esc_html_e( 'SlotNova Extensions', 'slotnova-booking' ); ?></h1>
			<hr class="wp-header-end">

			<!-- Hero Banner -->
			<div class="slotnova-hero-banner" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 12px; padding: 24px 32px; margin-bottom: 24px; color: #fff; display: flex; justify-content: space-between; align-items: center;">
				<div class="slotnova-hero-content">
					<div class="slotnova-hero-badges" style="margin-bottom: 8px;">
						<span class="slotnova-pill-badge" style="background: rgba(99, 102, 241, 0.2); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.4); padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 12px;">
							⚡ <?php esc_html_e( 'Cloudflare R2 Extension System', 'slotnova-booking' ); ?>
						</span>
					</div>
					<h1 style="color: #ffffff; margin: 8px 0; font-size: 24px; font-weight: 700;"><?php esc_html_e( 'SlotNova Extensions Hub', 'slotnova-booking' ); ?></h1>
					<p style="color: #94a3b8; margin: 0; font-size: 14px;"><?php esc_html_e( 'Modular extensions loaded inside SlotNova core without cluttering your WordPress plugins list.', 'slotnova-booking' ); ?></p>
				</div>
				<div>
					<button type="button" class="button button-secondary" onclick="location.reload();" style="border-radius: 6px; background: rgba(255,255,255,0.1); color: #fff; border-color: rgba(255,255,255,0.2);">
						🔄 <?php esc_html_e( 'Sync Cloudflare', 'slotnova-booking' ); ?>
					</button>
				</div>
			</div>

			<!-- Filter Bar -->
			<div class="slotnova-stats-filter-bar" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
				<div style="display: flex; gap: 12px; align-items: center;">
					<span style="font-weight: 700; color: #0f172a; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Filter:', 'slotnova-booking' ); ?></span>
					<div class="slotnova-filter-preset-row" id="slotnova-extension-filter-tabs" style="display: inline-flex; background: #f1f5f9; padding: 4px; border-radius: 10px; gap: 4px;">
						<button type="button" class="slotnova-filter-btn active" data-filter="all" style="background: #4f46e5; color: #ffffff; border: none; border-radius: 7px; padding: 6px 14px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(79,70,229,0.3);"><?php esc_html_e( 'All Extensions', 'slotnova-booking' ); ?></button>
						<button type="button" class="slotnova-filter-btn" data-filter="installed" style="background: transparent; color: #64748b; border: none; border-radius: 7px; padding: 6px 14px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;"><?php esc_html_e( 'Installed', 'slotnova-booking' ); ?></button>
						<button type="button" class="slotnova-filter-btn" data-filter="available" style="background: transparent; color: #64748b; border: none; border-radius: 7px; padding: 6px 14px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;"><?php esc_html_e( 'Available', 'slotnova-booking' ); ?></button>
						<button type="button" class="slotnova-filter-btn" data-filter="updates" style="background: transparent; color: #64748b; border: none; border-radius: 7px; padding: 6px 14px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;"><?php esc_html_e( 'Updates', 'slotnova-booking' ); ?></button>
					</div>
				</div>
				<div style="position: relative; width: 240px;">
					<span class="dashicons dashicons-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; width: 16px; height: 16px;"></span>
					<input type="text" id="slotnova-extension-search" placeholder="<?php esc_attr_e( 'Search extensions...', 'slotnova-booking' ); ?>" style="width: 100%; padding: 6px 10px 6px 32px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; background: #fafafa;" />
				</div>
			</div>

			<!-- Extension Grid -->
			<div class="slotnova-addons-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 20px;">
				<?php
				$all_items = [];
				foreach ( $installedManifests as $id => $manifest ) {
					$clean_id = ( 0 === strpos( $id, 'slotnova-' ) ) ? substr( $id, 9 ) : $id;
					$all_items[ $clean_id ] = [
						'id'           => $clean_id,
						'slug'         => $clean_id,
						'title'        => $manifest->getName(),
						'type'         => $manifest->getType(),
						'price'        => $manifest->getPrice(),
						'version'      => $manifest->getVersion(),
						'description'  => $manifest->getDescription(),
						'icon'         => 'dashicons-admin-plugins',
						'purchase_url' => $manifest->getPurchaseUrl(),
						'demo_url'     => $manifest->getDemoUrl(),
						'settings_url' => $manifest->getSettingsUrl(),
					];
				}

				foreach ( $catalog as $cat ) {
					$raw_id   = $cat['id'] ?? $cat['slug'];
					$clean_id = ( 0 === strpos( $raw_id, 'slotnova-' ) ) ? substr( $raw_id, 9 ) : $raw_id;

					if ( isset( $all_items[ $clean_id ] ) ) {
						if ( ! empty( $cat['title'] ) )        $all_items[ $clean_id ]['title']        = $cat['title'];
						if ( ! empty( $cat['type'] ) )         $all_items[ $clean_id ]['type']         = $cat['type'];
						if ( ! empty( $cat['price'] ) )        $all_items[ $clean_id ]['price']        = $cat['price'];
						if ( ! empty( $cat['purchase_url'] ) ) $all_items[ $clean_id ]['purchase_url'] = $cat['purchase_url'];
						if ( ! empty( $cat['demo_url'] ) )     $all_items[ $clean_id ]['demo_url']     = $cat['demo_url'];
						if ( ! empty( $cat['settings_url'] ) ) $all_items[ $clean_id ]['settings_url'] = $cat['settings_url'];
						if ( ! empty( $cat['description'] ) )  $all_items[ $clean_id ]['description']  = $cat['description'];
					} else {
						$all_items[ $clean_id ]       = $cat;
						$all_items[ $clean_id ]['id'] = $clean_id;
					}
				}
				?>
				<?php if ( ! empty( $all_items ) ) : ?>
					<?php foreach ( $all_items as $id => $item ) :
						$alt_id         = ( 0 === strpos( $id, 'slotnova-' ) ) ? substr( $id, 9 ) : 'slotnova-' . $id;
						$manifest       = isset( $installedManifests[ $id ] ) ? $installedManifests[ $id ] : ( isset( $installedManifests[ $alt_id ] ) ? $installedManifests[ $alt_id ] : null );
						$state          = isset( $installedStates[ $id ] ) ? $installedStates[ $id ] : ( isset( $installedStates[ $alt_id ] ) ? $installedStates[ $alt_id ] : null );
						$effective_id   = $manifest ? $manifest->getId() : ( $state ? $state->getId() : $id );
						$is_installed   = ( null !== $manifest || null !== $state );
						$is_active      = $is_installed && ( $this->extensionManager->isLoaded( $effective_id ) || $this->extensionManager->isLoaded( $id ) || ( $state ? $state->isActive() : false ) );
						$local_version  = $manifest ? $manifest->getVersion() : ( $state ? $state->getVersion() : '1.0.0' );
						$remote_version = $item['version'] ?? '1.0.0';
						$has_update     = $is_installed && version_compare( $remote_version, $local_version, '>' );
						$icon           = $item['icon'] ?? 'dashicons-admin-plugins';
						$is_image_icon  = ( 0 === strpos( $icon, 'http' ) || false !== strpos( $icon, '.' ) );
						$ext_type       = $item['type'] ?? ( $manifest ? $manifest->getType() : 'free' );
						$raw_price      = $item['price'] ?? ( $manifest ? $manifest->getPrice() : 'Free' );
						if ( 'free' === strtolower( $ext_type ) || '0' === (string) $raw_price || empty( $raw_price ) ) {
							$display_price = 'FREE';
						} elseif ( is_numeric( $raw_price ) ) {
							$val           = (float) $raw_price;
							$formatted     = ( floor( $val ) == $val ) ? number_format( $val, 0 ) : number_format( $val, 2 );
							$display_price = '$' . $formatted . '/mo';
						} elseif ( false === strpos( $raw_price, '$' ) && 'free' !== strtolower( $raw_price ) ) {
							$display_price = '$' . $raw_price;
						} else {
							$display_price = $raw_price;
						}
						$purchase_url   = ! empty( $item['purchase_url'] ) ? $item['purchase_url'] : ( $item['buy_url'] ?? ( $manifest ? $manifest->getPurchaseUrl() : '' ) );
						$demo_url       = ! empty( $item['demo_url'] ) ? $item['demo_url'] : ( $manifest ? $manifest->getDemoUrl() : '' );
						$raw_sett_url   = ! empty( $item['settings_url'] ) ? $item['settings_url'] : ( $manifest ? $manifest->getSettingsUrl() : '' );
						?>
						<div class="slotnova-addon-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02); transition: transform 0.15s ease, box-shadow 0.15s ease;"
							 data-id="<?php echo esc_attr( $id ); ?>"
							 data-installed="<?php echo $is_installed ? '1' : '0'; ?>"
							 data-active="<?php echo $is_active ? '1' : '0'; ?>"
							 data-update="<?php echo $has_update ? '1' : '0'; ?>">

							<div>
								<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 14px;">
									<div style="display: flex; gap: 12px; align-items: flex-start; flex: 1; min-width: 0;">
										<div style="width: 44px; height: 44px; min-width: 44px; border-radius: 10px; background: linear-gradient(135deg, #e0e7ff 0%, #f0fdf4 100%); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; color: #4f46e5; margin-top: 2px;">
											<?php if ( $is_image_icon ) : ?>
												<img src="<?php echo esc_url( $icon ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" style="width: 100%; height: 100%; object-fit: contain; padding: 6px;" />
											<?php else : ?>
												<span class="dashicons <?php echo esc_attr( $icon ); ?>" style="font-size: 22px; width: 22px; height: 22px;"></span>
											<?php endif; ?>
										</div>
										<div style="flex: 1; min-width: 0;">
											<h3 style="margin: 0 0 2px 0; font-size: 15px; font-weight: 700; color: #0f172a; line-height: 1.35; word-break: break-word;"><?php echo esc_html( $item['title'] ?? $item['name'] ); ?></h3>
											<span style="font-size: 12px; color: #64748b; font-weight: 500;"><?php echo esc_html( 'v' . ( $is_installed ? $local_version : $remote_version ) ); ?></span>
										</div>
									</div>
									<div style="display: flex; gap: 6px; align-items: center; flex-shrink: 0; justify-content: flex-end;">
										<?php if ( $is_active ) : ?>
											<span style="background: #dcfce7; color: #15803d; border-radius: 12px; padding: 3px 9px; font-size: 11px; font-weight: 700; white-space: nowrap; text-transform: uppercase; letter-spacing: 0.3px;"><?php esc_html_e( 'Active', 'slotnova-booking' ); ?></span>
										<?php elseif ( $is_installed ) : ?>
											<span style="background: #f1f5f9; color: #64748b; border-radius: 12px; padding: 3px 9px; font-size: 11px; font-weight: 700; white-space: nowrap; text-transform: uppercase; letter-spacing: 0.3px;"><?php esc_html_e( 'Disabled', 'slotnova-booking' ); ?></span>
										<?php endif; ?>
										<span style="background: #e0e7ff; color: #4338ca; border-radius: 12px; padding: 3px 9px; font-size: 11px; font-weight: 700; white-space: nowrap;"><?php echo esc_html( $display_price ); ?></span>
									</div>
								</div>

								<p style="color: #475569; font-size: 13px; line-height: 1.5; margin: 0 0 16px 0; min-height: 40px;"><?php echo esc_html( $item['description'] ?? '' ); ?></p>

								<?php if ( ! $is_installed && 'pro' === strtolower( $ext_type ) ) : ?>
									<div style="margin-bottom: 10px;">
										<input type="text" class="slotnova-license-input" placeholder="<?php esc_attr_e( 'Enter License Key...', 'slotnova-booking' ); ?>" style="width: 100%; font-size: 12px; padding: 7px 10px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fafafa; transition: all 0.2s ease;" />
									</div>
								<?php endif; ?>

								<div class="slotnova-card-notice" style="display: none; margin-bottom: 12px; padding: 8px 12px; border-radius: 6px; background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; font-size: 12px; font-weight: 500; align-items: center; gap: 6px; line-height: 1.4;">
									<span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px; color: #dc2626; flex-shrink: 0;"></span>
									<span class="slotnova-notice-text"></span>
								</div>
							</div>

							<div style="border-top: 1px solid #f1f5f9; padding-top: 14px; margin-top: auto; display: flex; gap: 8px; justify-content: flex-end; align-items: center; flex-wrap: nowrap; width: 100%;">
								<?php
								$effective_demo_url = ! empty( $demo_url ) ? $demo_url : 'https://slotnova.com/demo/' . esc_attr( $id ) . '/';
								?>
								<a href="<?php echo esc_url( $effective_demo_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="color: #475569; border-color: #cbd5e1; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 4px; white-space: nowrap; height: 34px; padding: 0 12px; font-size: 12px; flex: 1; min-width: 0;">
									<span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
									<span><?php esc_html_e( 'Live Demo', 'slotnova-booking' ); ?></span>
								</a>

								<?php if ( $is_active ) :
									$target_settings_url = ! empty( $raw_sett_url ) ? ( 0 === strpos( $raw_sett_url, 'http' ) ? $raw_sett_url : admin_url( $raw_sett_url ) ) : admin_url( 'admin.php?page=slotnova-settings#slotnova-' . esc_attr( $id ) . '-settings' );
								?>
									<a href="<?php echo esc_url( $target_settings_url ); ?>" class="button button-secondary" style="color: #4f46e5; border-color: #a5b4fc; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 4px; white-space: nowrap; height: 34px; padding: 0 12px; font-size: 12px; flex: 1; min-width: 0;">
										<span class="dashicons dashicons-admin-generic" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
										<span><?php esc_html_e( 'Settings', 'slotnova-booking' ); ?></span>
									</a>
								<?php endif; ?>

								<?php if ( $has_update ) : ?>
									<button type="button" class="button button-secondary slotnova-ext-btn" data-action="update" data-id="<?php echo esc_attr( $effective_id ); ?>" style="color: #d97706; border-color: #f59e0b; white-space: nowrap; height: 34px; padding: 0 12px; font-size: 12px; flex: 1; min-width: 0;">
										<?php esc_html_e( 'Update Now', 'slotnova-booking' ); ?>
									</button>
								<?php endif; ?>

								<?php
								$toggle_nonce = wp_create_nonce( 'slotnova_toggle_' . $effective_id );
								?>
								<?php if ( $is_active ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=slotnova-addons&action=disable&extension_id=' . esc_attr( $effective_id ) . '&_wpnonce=' . $toggle_nonce ) ); ?>" class="button slotnova-ext-btn" data-action="disable" data-id="<?php echo esc_attr( $effective_id ); ?>" style="white-space: nowrap; height: 34px; padding: 0 12px; font-size: 12px; flex: 1; min-width: 0; display: inline-flex; align-items: center; justify-content: center;">
										<?php esc_html_e( 'Deactive', 'slotnova-booking' ); ?>
									</a>
								<?php elseif ( $is_installed ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=slotnova-addons&action=enable&extension_id=' . esc_attr( $effective_id ) . '&_wpnonce=' . $toggle_nonce ) ); ?>" class="button button-primary slotnova-ext-btn" data-action="enable" data-id="<?php echo esc_attr( $effective_id ); ?>" style="white-space: nowrap; height: 34px; padding: 0 12px; font-size: 12px; flex: 1; min-width: 0; display: inline-flex; align-items: center; justify-content: center;">
										<?php esc_html_e( 'Active', 'slotnova-booking' ); ?>
									</a>
									<button type="button" class="button slotnova-ext-btn" data-action="remove" data-id="<?php echo esc_attr( $effective_id ); ?>" style="color: #dc2626; border-color: #fca5a5; white-space: nowrap; height: 34px; padding: 0 12px; font-size: 12px; flex: 1; min-width: 0;">
										<?php esc_html_e( 'Remove', 'slotnova-booking' ); ?>
									</button>
								<?php else : ?>
									<?php if ( 'pro' === strtolower( $ext_type ) ) :
										$buy_link = ! empty( $purchase_url ) ? $purchase_url : 'https://checkout.freemius.com/plugin/36458/plan/80445/';
									?>
										<a href="<?php echo esc_url( $buy_link ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="border-color: #6366f1; color: #4f46e5; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 4px; white-space: nowrap; height: 34px; padding: 0 12px; font-size: 12px; flex: 1; min-width: 0;">
											<span class="dashicons dashicons-cart" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
											<span><?php esc_html_e( 'Get License', 'slotnova-booking' ); ?></span>
										</a>
										<button type="button" class="button button-primary slotnova-ext-btn" data-action="install" data-id="<?php echo esc_attr( $id ); ?>" style="white-space: nowrap; height: 34px; padding: 0 12px; font-size: 12px; flex: 1; min-width: 0; display: inline-flex; align-items: center; justify-content: center;">
											<?php esc_html_e( 'Verify & Install', 'slotnova-booking' ); ?>
										</button>
									<?php else : ?>
										<button type="button" class="button button-primary slotnova-ext-btn" data-action="install" data-id="<?php echo esc_attr( $id ); ?>" style="white-space: nowrap; height: 34px; padding: 0 12px; font-size: 12px; flex: 1; min-width: 0; display: inline-flex; align-items: center; justify-content: center;">
											<?php esc_html_e( 'Install', 'slotnova-booking' ); ?>
										</button>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div style="grid-column: 1 / -1; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 40px; text-align: center;">
						<div style="margin-bottom: 12px; color: #94a3b8;">
							<span class="dashicons dashicons-admin-plugins" style="font-size: 36px; width: 36px; height: 36px;"></span>
						</div>
						<h3 style="margin: 0 0 6px 0; color: #0f172a; font-weight: 700;"><?php esc_html_e( 'No Extensions Available Right Now', 'slotnova-booking' ); ?></h3>
						<p style="margin: 0; color: #64748b; font-size: 13px;"><?php esc_html_e( 'New extensions will automatically appear here as soon as they are added to Cloudflare.', 'slotnova-booking' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			const nonce = '<?php echo esc_js( $nonce ); ?>';

			// Filter tabs styling update on click
			$('#slotnova-extension-filter-tabs button').on('click', function() {
				$('#slotnova-extension-filter-tabs button').css({
					'background': 'transparent',
					'color': '#64748b',
					'font-weight': '500',
					'box-shadow': 'none'
				}).removeClass('active');

				$(this).css({
					'background': '#4f46e5',
					'color': '#ffffff',
					'font-weight': '600',
					'box-shadow': '0 1px 2px rgba(79,70,229,0.3)'
				}).addClass('active');

				const filter = $(this).data('filter');

				$('.slotnova-addon-card').each(function() {
					const installed = $(this).data('installed') === 1;
					const active = $(this).data('active') === 1;
					const hasUpdate = $(this).data('update') === 1;

					if (filter === 'all') {
						$(this).show();
					} else if (filter === 'installed' && installed) {
						$(this).show();
					} else if (filter === 'available' && !installed) {
						$(this).show();
					} else if (filter === 'updates' && hasUpdate) {
						$(this).show();
					} else {
						$(this).hide();
					}
				});
			});

			// Real-time search filter input
			$('#slotnova-extension-search').on('input', function() {
				const query = $(this).val().toLowerCase().trim();
				$('.slotnova-addon-card').each(function() {
					const title = $(this).find('h3').text().toLowerCase();
					const desc  = $(this).find('p').text().toLowerCase();
					if (title.indexOf(query) !== -1 || desc.indexOf(query) !== -1) {
						$(this).show();
					} else {
						$(this).hide();
					}
				});
			});

			// Action buttons
			$(document).on('click', '.slotnova-ext-btn', function(e) {
				e.preventDefault();
				const $btn = $(this);
				const action = $btn.attr('data-action') || $btn.data('action');
				const id = $btn.attr('data-id') || $btn.data('id');
				const $card = $btn.closest('.slotnova-addon-card');
				const $licenseInput = $card.find('.slotnova-license-input');
				const licenseKey = $licenseInput.length ? $licenseInput.val().trim() : '';
				const $notice = $card.find('.slotnova-card-notice');

				// Reset previous warning notice state
				$notice.hide().find('.slotnova-notice-text').text('');
				if ($licenseInput.length) {
					$licenseInput.css({'border': '1px solid #cbd5e1', 'background': '#fafafa'});
				}

				if (action === 'install' && $licenseInput.length && !licenseKey) {
					$licenseInput.css({'border': '1px solid #ef4444', 'background': '#fff5f5'}).focus();
					$notice.css('display', 'flex').find('.slotnova-notice-text').text('Please enter a valid license key before installing.');
					return;
				}

				$btn.prop('disabled', true).text('Processing...');

				let ajaxAction = 'slotnova_toggle_extension';
				if (action === 'install') ajaxAction = 'slotnova_install_extension';
				if (action === 'update') ajaxAction = 'slotnova_update_extension';
				if (action === 'remove') ajaxAction = 'slotnova_uninstall_extension';

				const ajaxUrl = window.ajaxurl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

				$.post(ajaxUrl, {
					action: ajaxAction,
					security: nonce,
					extension_id: id,
					action_type: (action === 'enable' || action === 'activate' ? 'activate' : 'deactivate'),
					license_key: licenseKey
				}, function(res) {
					if (typeof res === 'string') {
						try { res = JSON.parse(res); } catch(err) {}
					}
					if (res && res.success) {
						location.reload();
					} else {
						const errorMsg = (res && res.data && res.data.message) ? res.data.message : 'Invalid license or unauthorized extension download from Cloudflare.';
						if ($licenseInput.length) {
							$licenseInput.css({'border': '1px solid #ef4444', 'background': '#fff5f5'});
						}
						$notice.css('display', 'flex').find('.slotnova-notice-text').text(errorMsg);
						$btn.prop('disabled', false).text(action === 'install' ? 'Verify & Install' : 'Try Again');
					}
				}, 'json').fail(function(xhr, status, err) {
					$notice.css('display', 'flex').find('.slotnova-notice-text').text('Network request failed. Please verify your connection or license.');
					$btn.prop('disabled', false).text('Try Again');
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Direct GET fallback handler for Enable / Disable actions.
	 */
	public function handle_get_actions(): void {
		if ( ! is_admin() || ! isset( $_GET['page'] ) || 'slotnova-addons' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) || ! isset( $_GET['extension_id'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$action      = sanitize_key( $_GET['action'] );
		$extensionId = sanitize_key( $_GET['extension_id'] );
		$nonce       = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'slotnova_toggle_' . $extensionId ) ) {
			return;
		}

		if ( 'enable' === $action || 'activate' === $action ) {
			$this->extensionManager->activate( $extensionId );
			wp_safe_redirect( admin_url( 'admin.php?page=slotnova-addons&status=activated' ) );
			exit;
		} elseif ( 'disable' === $action || 'deactivate' === $action ) {
			$this->extensionManager->deactivate( $extensionId );
			wp_safe_redirect( admin_url( 'admin.php?page=slotnova-addons&status=deactivated' ) );
			exit;
		}
	}

	/**
	 * AJAX Handler: Install Extension
	 */
	public function ajax_install_extension(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'slotnova-booking' ) ) );
		}

		check_ajax_referer( 'slotnova_extensions_nonce', 'security' );

		$extensionId = isset( $_POST['extension_id'] ) ? sanitize_key( wp_unslash( $_POST['extension_id'] ) ) : ( isset( $_POST['addon_slug'] ) ? sanitize_key( wp_unslash( $_POST['addon_slug'] ) ) : '' );
		$licenseKey  = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

		if ( empty( $extensionId ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid extension ID.', 'slotnova-booking' ) ) );
		}

		try {
			$cleanId    = ( 0 === strpos( $extensionId, 'slotnova-' ) ) ? substr( $extensionId, 9 ) : $extensionId;
			$bundledDir = SLOTNOVA_BOOKING_PATH . 'extensions/' . $cleanId;

			if ( is_dir( $bundledDir ) && file_exists( $bundledDir . '/extension.json' ) ) {
				$this->repository->updateStatus( $extensionId, 'inactive', $bundledDir );
				wp_send_json_success( array( 'message' => __( 'Extension installed successfully!', 'slotnova-booking' ) ) );
				return;
			}

			if ( ! empty( $licenseKey ) ) {
				$freemiusValidator = \SlotNova\Booking\ExtensionManager\Container\Container::getInstance()->make( \SlotNova\Booking\ExtensionManager\Services\FreemiusValidator::class );
				$freemiusValidator->validateLicense( $extensionId, $licenseKey );
			}

			$success = $this->extensionManager->install( $extensionId, $licenseKey );
			if ( $success ) {
				$this->repository->updateStatus( $extensionId, 'inactive' );
				wp_send_json_success( array( 'message' => __( 'Extension installed successfully!', 'slotnova-booking' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Failed to install extension.', 'slotnova-booking' ) ) );
			}
		} catch ( \Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX Handler: Enable / Disable Extension
	 */
	public function ajax_toggle_extension(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'slotnova-booking' ) ) );
		}

		check_ajax_referer( 'slotnova_extensions_nonce', 'security' );

		$extensionId = isset( $_POST['extension_id'] ) ? sanitize_key( wp_unslash( $_POST['extension_id'] ) ) : '';
		$actionType  = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : '';

		if ( empty( $extensionId ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid extension ID.', 'slotnova-booking' ) ) );
		}

		if ( 'activate' === $actionType || 'enable' === $actionType ) {
			$res = $this->extensionManager->activate( $extensionId );
			$msg = __( 'Extension activated!', 'slotnova-booking' );
		} else {
			$res = $this->extensionManager->deactivate( $extensionId );
			$msg = __( 'Extension deactivated.', 'slotnova-booking' );
		}

		if ( $res ) {
			wp_send_json_success( array( 'message' => $msg ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to toggle extension status.', 'slotnova-booking' ) ) );
		}
	}

	/**
	 * AJAX Handler: Update Extension
	 */
	public function ajax_update_extension(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'slotnova-booking' ) ) );
		}

		check_ajax_referer( 'slotnova_extensions_nonce', 'security' );

		$extensionId = isset( $_POST['extension_id'] ) ? sanitize_key( wp_unslash( $_POST['extension_id'] ) ) : '';

		if ( empty( $extensionId ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid extension ID.', 'slotnova-booking' ) ) );
		}

		try {
			$success = $this->extensionManager->update( $extensionId );
			if ( $success ) {
				wp_send_json_success( array( 'message' => __( 'Extension updated successfully!', 'slotnova-booking' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Extension update failed.', 'slotnova-booking' ) ) );
			}
		} catch ( \Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX Handler: Uninstall Extension
	 */
	public function ajax_uninstall_extension(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'slotnova-booking' ) ) );
		}

		check_ajax_referer( 'slotnova_extensions_nonce', 'security' );

		$extensionId = isset( $_POST['extension_id'] ) ? sanitize_key( wp_unslash( $_POST['extension_id'] ) ) : '';

		if ( empty( $extensionId ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid extension ID.', 'slotnova-booking' ) ) );
		}

		$success = $this->extensionManager->uninstall( $extensionId );
		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'Extension removed successfully.', 'slotnova-booking' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to remove extension.', 'slotnova-booking' ) ) );
		}
	}
}
