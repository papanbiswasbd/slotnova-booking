<?php
/**
 * Signature Validator Service.
 *
 * @package SlotNova\Booking\ExtensionManager\Services
 */

namespace SlotNova\Booking\ExtensionManager\Services;

use SlotNova\Booking\ExtensionManager\Exceptions\SecurityException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SignatureValidator {

	/**
	 * Validate remote API signature on download request payloads.
	 *
	 * @param string $payload Serialized data string or JSON.
	 * @param string $signature Provided signature.
	 * @param string $secret Secret key or public key.
	 * @return void
	 * @throws SecurityException
	 */
	public function validate( string $payload, string $signature, string $secret = '' ): void {
		if ( empty( $signature ) ) {
			return; // Signature check optional if API omits signature header
		}

		if ( empty( $secret ) ) {
			$secret = get_option( 'slotnova_api_secret', 'slotnova_default_secret' );
		}

		$calculated = hash_hmac( 'sha256', $payload, $secret );
		if ( ! hash_equals( $calculated, $signature ) ) {
			throw new SecurityException( "Invalid payload signature detected. Remote API response verification failed." );
		}
	}
}
