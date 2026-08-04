<?php
/**
 * Checksum Verifier Service.
 *
 * @package SlotNova\Booking\ExtensionManager\Services
 */

namespace SlotNova\Booking\ExtensionManager\Services;

use SlotNova\Booking\ExtensionManager\Exceptions\ChecksumMismatchException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChecksumVerifier {

	/**
	 * Verify SHA-256 hash of a downloaded archive file.
	 *
	 * @param string $filePath Path to downloaded zip file.
	 * @param string $expectedHash Expected SHA-256 hash.
	 * @return void
	 * @throws ChecksumMismatchException
	 */
	public function verify( string $filePath, string $expectedHash ): void {
		if ( empty( $expectedHash ) ) {
			return; // Checksum verification optional if API didn't output hash
		}

		if ( ! file_exists( $filePath ) ) {
			throw new ChecksumMismatchException( "File not found for checksum verification: {$filePath}" );
		}

		$actualHash = hash_file( 'sha256', $filePath );
		if ( 0 !== strcasecmp( $actualHash, $expectedHash ) ) {
			throw new ChecksumMismatchException(
				sprintf(
					"Checksum mismatch! Expected SHA-256 hash '%s', got '%s'. Download may be corrupted or tampered with.",
					$expectedHash,
					$actualHash
				)
			);
		}
	}
}
