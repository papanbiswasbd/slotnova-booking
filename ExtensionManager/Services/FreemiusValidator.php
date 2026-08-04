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
	 * Freemius Developer Credentials.
	 *
	 * @var string
	 */
	private string $developerId        = '26826';
	private string $developerPublicKey = 'pk_efbfa191a3cf8b19526d29f189177';
	private string $developerSecretKey = 'sk_cDv*k{6VRH:m<{>6ts-1o;#*IubhO';

	/**
	 * Map of Extension ID -> Freemius App / Product ID.
	 *
	 * @var array
	 */
	private array $freemiusAppMap = array(
		'deposits'           => '36458',
		'google-calendar'   => '36449',
		'sms-notifications'  => '36449',
	);

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
	 * Get Freemius App ID for a specific extension.
	 *
	 * @param string $extensionId Extension ID.
	 * @return string
	 */
	public function getFreemiusAppId( string $extensionId ): string {
		$cleanId = ( 0 === strpos( $extensionId, 'slotnova-' ) ) ? substr( $extensionId, 9 ) : $extensionId;
		$customMap = get_option( 'slotnova_freemius_app_map', array() );
		if ( is_array( $customMap ) && ! empty( $customMap[ $cleanId ] ) ) {
			return (string) $customMap[ $cleanId ];
		}
		return $this->freemiusAppMap[ $cleanId ] ?? '';
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
		$licenseKey = trim( rawurldecode( $licenseKey ) );
		if ( 0 === strpos( $licenseKey, 'sk_' ) ) {
			$licenseKey = str_replace( array( '%2B', '%2b', ' ' ), '+', $licenseKey );
		}
		if ( empty( $licenseKey ) ) {
			throw new ExtensionException( esc_html__( 'Please enter a valid Freemius license key.', 'slotnova-booking' ) );
		}

		if ( empty( $domain ) ) {
			$domain = wp_parse_url( get_site_url(), PHP_URL_HOST );
		}

		$freemiusAppId = $this->getFreemiusAppId( $extensionId );

		$verifyEndpoint = str_replace( '/addons/download', '/verify', $this->apiEndpoint );
		if ( false === strpos( $verifyEndpoint, '/verify' ) ) {
			$verifyEndpoint = str_replace( '/download', '/verify', $verifyEndpoint );
		}

		$requestUrl = add_query_arg(
			array(
				'action'          => 'verify',
				'slug'            => $extensionId,
				'extension_id'    => $extensionId,
				'freemius_app_id' => $freemiusAppId,
				'license_key'     => rawurlencode( $licenseKey ),
				'domain'          => $domain,
			),
			$verifyEndpoint
		);

		$response = wp_remote_get(
			$requestUrl,
			array(
				'timeout'   => 30,
				'sslverify' => true,
				'headers'   => array(
					'Cache-Control' => 'no-cache, no-store, must-revalidate',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ExtensionException( esc_html( sprintf( __( 'Freemius license server request failed: %s', 'slotnova-booking' ), $response->get_error_message() ) ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$isValid = ( ! empty( $body['success'] ) || ! empty( $body['valid'] ) );
		if ( 200 === $code && is_array( $body ) && $isValid ) {
			$licenseData = array(
				'license_key'    => $licenseKey,
				'status'         => 'active',
				'customer_name'  => $body['name'] ?? ( $body['user_name'] ?? ( $body['customer_name'] ?? '' ) ),
				'customer_email' => $body['email'] ?? ( $body['user_email'] ?? ( $body['customer_email'] ?? '' ) ),
				'plan_name'      => $body['plan_name'] ?? ( $body['plan'] ?? 'Pro' ),
				'expires_at'     => $body['expires_at'] ?? ( $body['expires'] ?? 'Active Subscription' ),
				'activated_at'   => current_time( 'mysql' ),
			);
			update_option( 'slotnova_fs_license_' . $extensionId, $licenseData );
			return $licenseData;
		}

		$errorMessage = is_array( $body ) && ! empty( $body['error'] )
			? $body['error']
			: ( is_array( $body ) && ! empty( $body['message'] )
				? $body['message']
				: __( 'Invalid license key or unauthorized download for this specific extension.', 'slotnova-booking' ) );

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
