<?php
/**
 * Extension Scanner Service.
 * Scans directories for extensions containing valid extension.json manifests.
 *
 * @package SlotNova\Booking\ExtensionManager\Services
 */

namespace SlotNova\Booking\ExtensionManager\Services;

use SlotNova\Booking\ExtensionManager\Models\ExtensionManifest;
use SlotNova\Booking\ExtensionManager\Exceptions\ManifestValidationException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scanner {

	private ManifestValidator $validator;

	public function __construct( ManifestValidator $validator ) {
		$this->validator = $validator;
	}

	/**
	 * Scan an array of directory paths for extensions.
	 *
	 * @param array $directories Directory paths to scan.
	 * @return array Array of ExtensionManifest objects keyed by extension ID.
	 */
	public function scanDirectories( array $directories ): array {
		$manifests = [];

		foreach ( $directories as $dir ) {
			$dir = wp_normalize_path( $dir );
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$items = scandir( $dir );
			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}

				$extensionPath = $dir . '/' . $item;
				if ( ! is_dir( $extensionPath ) ) {
					continue;
				}

				$manifestFile = $extensionPath . '/extension.json';
				if ( ! file_exists( $manifestFile ) ) {
					continue;
				}

				try {
					$manifest = $this->parseManifest( $manifestFile, $extensionPath );
					if ( isset( $manifests[ $manifest->getId() ] ) ) {
						// Remote upload directory takes precedence over bundled directory
						continue;
					}
					$manifests[ $manifest->getId() ] = $manifest;
				} catch ( ManifestValidationException $e ) {
					error_log( "[SlotNova ExtensionScanner] Invalid manifest in {$extensionPath}: " . $e->getMessage() );
				}
			}
		}

		return $manifests;
	}

	/**
	 * Parse and validate an extension.json file.
	 *
	 * @param string $jsonFile Path to extension.json.
	 * @param string $path     Extension directory path.
	 * @return ExtensionManifest
	 * @throws ManifestValidationException
	 */
	public function parseManifest( string $jsonFile, string $path ): ExtensionManifest {
		$content = file_get_contents( $jsonFile );
		if ( false === $content ) {
			throw new ManifestValidationException( "Unable to read manifest file: {$jsonFile}" );
		}

		$data = json_decode( $content, true );
		if ( ! is_array( $data ) ) {
			throw new ManifestValidationException( "Invalid JSON structure in manifest file: {$jsonFile}" );
		}

		$this->validator->validate( $data );

		return new ExtensionManifest( $data, $path );
	}
}
