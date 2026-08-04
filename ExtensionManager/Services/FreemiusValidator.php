<?php
/**
 * Freemius License Validator Service
 *
 * @package SlotNova\Booking\ExtensionManager\Services
 */

namespace SlotNova\Booking\ExtensionManager\Services;

use SlotNova\Booking\ExtensionManager\Exceptions\ExtensionException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FreemiusValidator
 */
class FreemiusValidator {

	/**
	 * Freemius API Base Endpoint.
	 *
	 * @var string
	 */
	private $apiEndpoint;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->apiEndpoint = get_option(
			'slotnova_api_endpoint',
			get_option( 'slotnova_cloudflare_worker_endpoint', 'https://slotnova-booking.papan-biswas-bd.workers.dev/addons/download' )
		);
	}

	/**
	 * Validate and activate a Freemius license key for a given extension.
	 *
	 * @param string $extensionId Extension ID / Slug.
	 * @param string $licenseKey  License key entered by user.
	 * @param string $domain      Site domain.
	 * @return array Array containing validation status and details.
	 * @throws ExtensionException On validation failure.
	 */
	public function validateLicense( string $extensionId, string $licenseKey, string $domain = '' ): array {
		if ( empty( $licenseKey ) ) {
			throw new ExtensionException( esc_html__( 'Please enter a valid Freemius license key.', 'slotnova-booking' ) );
		}

		if ( empty( $domain ) ) {
			$domain = wp_parse_url( get_site_url(), PHP_URL_HOST );
		}

		$requestUrl = add_query_arg(
			array(
				'action'       => 'validate_license',
				'slug'         => $extensionId,
				'extension_id' => $extensionId,
				'license_key'  => $licenseKey,
				'domain'       => $domain,
			),
			$this->apiEndpoint
		);

		$response = wp_remote_post(
			$requestUrl,
			array(
				'timeout'   => 30,
				'sslverify' => true,
				'body'      => array(
					'action'       => 'validate_license',
					'extension_id' => $extensionId,
					'slug'         => $extensionId,
					'license_key'  => $licenseKey,
					'domain'       => $domain,
					'core_version' => defined( 'SLOTNOVA_BOOKING_VERSION' ) ? SLOTNOVA_BOOKING_VERSION : '1.0.0',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ExtensionException( esc_html( sprintf( __( 'Freemius license server request failed: %s', 'slotnova-booking' ), $response->get_error_message() ) ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && is_array( $body ) && ! empty( $body['success'] ) ) {
			$licenseData = array(
				'license_key'  => $licenseKey,
				'status'       => 'active',
				'plan_name'    => $body['plan_name'] ?? 'Pro',
				'expires_at'   => $body['expires_at'] ?? '',
				'activated_at' => current_time( 'mysql' ),
			);
			update_option( 'slotnova_fs_license_' . $extensionId, $licenseData );
			return $licenseData;
		}

		$errorMessage = is_array( $body ) && ! empty( $body['message'] )
			? $body['message']
			: __( 'Invalid license key or unauthorized download from Freemius / Cloudflare.', 'slotnova-booking' );

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		throw new ExtensionException( esc_html( $errorMessage ) );
	}

	/**
	 * Get cached Freemius license status for an extension.
	 *
	 * @param string $extensionId Extension ID.
	 * @return array|null
	 */
	public function getLicense( string $extensionId ): ?array {
		$license = get_option( 'slotnova_fs_license_' . $extensionId, null );
		return is_array( $license ) ? $license : null;
	}

	/**
	 * Deactivate Freemius license for an extension.
	 *
	 * @param string $extensionId Extension ID.
	 * @return bool
	 */
	public function deactivateLicense( string $extensionId ): bool {
		delete_option( 'slotnova_fs_license_' . $extensionId );
		return true;
	}
}
