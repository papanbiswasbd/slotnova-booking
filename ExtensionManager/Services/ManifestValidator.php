<?php
/**
 * Manifest Validator Service.
 *
 * @package SlotNova\Booking\ExtensionManager\Services
 */

namespace SlotNova\Booking\ExtensionManager\Services;

use SlotNova\Booking\ExtensionManager\Exceptions\ManifestValidationException;
use SlotNova\Booking\ExtensionManager\Exceptions\IncompatibleVersionException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ManifestValidator {

	/**
	 * Validate manifest data array.
	 *
	 * @param array $data Manifest data.
	 * @return void
	 * @throws ManifestValidationException
	 * @throws IncompatibleVersionException
	 */
	public function validate( array $data ): void {
		$requiredKeys = [ 'id', 'name', 'version', 'autoload' ];

		foreach ( $requiredKeys as $key ) {
			if ( empty( $data[ $key ] ) ) {
				throw new ManifestValidationException( esc_html( sprintf( "Manifest is missing required field: '%s'", $key ) ) );
			}
		}

		if ( ! preg_match( '/^[a-z0-9\-_]+$/i', $data['id'] ) ) {
			throw new ManifestValidationException( esc_html__( 'Extension ID must contain only alphanumeric characters, dashes, or underscores.', 'slotnova-booking' ) );
		}

		// Validate PHP version compatibility
		if ( ! empty( $data['requires_php'] ) ) {
			if ( version_compare( PHP_VERSION, $data['requires_php'], '<' ) ) {
				throw new IncompatibleVersionException(
					esc_html(
						sprintf(
							"Extension '%s' requires PHP version %s or higher (Current: %s).",
							$data['name'],
							$data['requires_php'],
							PHP_VERSION
						)
					)
				);
			}
		}

		// Validate Core SlotNova version compatibility
		if ( ! empty( $data['requires_core'] ) && defined( 'SLOTNOVA_BOOKING_VERSION' ) ) {
			if ( version_compare( SLOTNOVA_BOOKING_VERSION, $data['requires_core'], '<' ) ) {
				throw new IncompatibleVersionException(
					esc_html(
						sprintf(
							"Extension '%s' requires SlotNova Core version %s or higher (Current: %s).",
							$data['name'],
							$data['requires_core'],
							SLOTNOVA_BOOKING_VERSION
						)
					)
				);
			}
		}
	}
}
