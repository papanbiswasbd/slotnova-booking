<?php
/**
 * Extension Installer Service.
 * Downloads, verifies, extracts, and registers remote extensions.
 *
 * @package SlotNova\Booking\ExtensionManager\Services
 */

namespace SlotNova\Booking\ExtensionManager\Services;

use SlotNova\Booking\ExtensionManager\Repositories\ExtensionRepository;
use SlotNova\Booking\ExtensionManager\Models\ExtensionState;
use SlotNova\Booking\ExtensionManager\Models\ExtensionManifest;
use SlotNova\Booking\ExtensionManager\Exceptions\ExtensionException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Installer {

	private ExtensionRepository $repository;
	private ChecksumVerifier $checksumVerifier;
	private ManifestValidator $validator;

	public function __construct(
		ExtensionRepository $repository,
		ChecksumVerifier $checksumVerifier,
		ManifestValidator $validator
	) {
		$this->repository       = $repository;
		$this->checksumVerifier = $checksumVerifier;
		$this->validator        = $validator;
	}

	/**
	 * Get the target base directory for remote extensions.
	 *
	 * @return string
	 */
	public static function getExtensionsStorageDir(): string {
		$uploadDir = wp_upload_dir();
		$baseDir   = wp_normalize_path( $uploadDir['basedir'] . '/slotnova/extensions' );
		if ( ! is_dir( $baseDir ) ) {
			wp_mkdir_p( $baseDir );
			self::secureStorageDirectory( dirname( $baseDir ) );
		}
		return $baseDir;
	}

	/**
	 * Get temp directory for downloading archives.
	 *
	 * @return string
	 */
	public static function getTempDir(): string {
		$uploadDir = wp_upload_dir();
		$tempDir   = wp_normalize_path( $uploadDir['basedir'] . '/slotnova/temp' );
		if ( ! is_dir( $tempDir ) ) {
			wp_mkdir_p( $tempDir );
		}
		return $tempDir;
	}

	/**
	 * Secure storage directory by adding htaccess and index.php.
	 *
	 * @param string $slotnovaDir
	 * @return void
	 */
	public static function secureStorageDirectory( string $slotnovaDir ): void {
		if ( ! is_dir( $slotnovaDir ) ) {
			wp_mkdir_p( $slotnovaDir );
		}

		$htaccessFile = $slotnovaDir . '/.htaccess';
		if ( is_dir( $slotnovaDir ) ) {
			$rules = "<FilesMatch \"\\.php$\">\n  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n  <IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n  </IfModule>\n</FilesMatch>";
			@file_put_contents( $htaccessFile, $rules );
		}

		$indexFile = $slotnovaDir . '/index.php';
		if ( ! file_exists( $indexFile ) && is_dir( $slotnovaDir ) ) {
			@file_put_contents( $indexFile, "<?php // Silence is golden." );
		}
	}

	/**
	 * Download and install an extension.
	 *
	 * @param string $extensionId Extension ID.
	 * @param string $licenseKey  Optional license key.
	 * @return bool
	 * @throws ExtensionException
	 */
	public function installFromRemote( string $extensionId, string $licenseKey = '' ): bool {
		$siteUrl        = get_site_url();
		$domain          = wp_parse_url( $siteUrl, PHP_URL_HOST );
		$apiEndpoint    = get_option( 'slotnova_api_endpoint', get_option( 'slotnova_cloudflare_worker_endpoint', 'https://slotnova-booking.papan-biswas-bd.workers.dev/addons/download' ) );

		// Prepare request payload for Cloudflare Worker / API
		$requestUrl = add_query_arg(
			[
				'slug'         => $extensionId,
				'extension_id' => $extensionId,
				'domain'       => $domain,
				'license_key'  => $licenseKey,
			],
			$apiEndpoint
		);

		$response = wp_remote_post(
			$requestUrl,
			[
				'timeout'   => 30,
				'sslverify' => true,
				'body'      => [
					'extension_id' => $extensionId,
					'slug'         => $extensionId,
					'license_key'  => $licenseKey,
					'domain'       => $domain,
					'core_version' => defined( 'SLOTNOVA_BOOKING_VERSION' ) ? SLOTNOVA_BOOKING_VERSION : '1.0.0',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new ExtensionException( "Cloudflare Worker request failed: " . $response->get_error_message() );
		}

		$code        = wp_remote_retrieve_response_code( $response );
		$rawBody     = wp_remote_retrieve_body( $response );
		$body        = json_decode( $rawBody, true );
		$downloadUrl = '';
		$expectedHash = '';

		if ( is_array( $body ) && ! empty( $body['download_url'] ) ) {
			$downloadUrl  = $body['download_url'];
			$expectedHash = $body['checksum'] ?? '';
		} elseif ( 200 === $code && filter_var( trim( $rawBody ), FILTER_VALIDATE_URL ) ) {
			// Direct Cloudflare R2 presigned URL string returned
			$downloadUrl = trim( $rawBody );
		} elseif ( 200 === $code ) {
			// Direct URL via request parameters
			$downloadUrl = $requestUrl;
		} else {
			$msg = is_array( $body ) && isset( $body['message'] ) ? $body['message'] : 'Invalid license or unauthorized extension download from Cloudflare.';
			throw new ExtensionException( $msg );
		}

		// Download ZIP archive from Cloudflare R2 / CDN to temp
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tempFile = download_url( $downloadUrl, 300 );
		if ( is_wp_error( $tempFile ) ) {
			throw new ExtensionException( "Failed to download extension package: " . $tempFile->get_error_message() );
		}

		// Verify SHA-256 Checksum
		if ( ! empty( $expectedHash ) ) {
			$this->checksumVerifier->verify( $tempFile, $expectedHash );
		}

		// Extract package
		$tempExtractDir = self::getTempDir() . '/' . $extensionId . '_' . time();
		wp_mkdir_p( $tempExtractDir );

		// Initialize WP_Filesystem
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		$unzipped = false;
		if ( $wp_filesystem ) {
			$unzipped = unzip_file( $tempFile, $tempExtractDir );
		}

		// Fallback to native PHP ZipArchive if WP_Filesystem returns error or unavailable
		if ( true !== $unzipped || is_wp_error( $unzipped ) ) {
			if ( class_exists( '\ZipArchive' ) ) {
				$zip = new \ZipArchive();
				if ( true === $zip->open( $tempFile ) ) {
					$zip->extractTo( $tempExtractDir );
					$zip->close();
					$unzipped = true;
				}
			}
		}

		@unlink( $tempFile ); // Remove temp zip

		if ( true !== $unzipped && is_wp_error( $unzipped ) ) {
			$this->recursiveRmdir( $tempExtractDir );
			throw new ExtensionException( "Failed to extract extension package: " . $unzipped->get_error_message() );
		}

		// Locate extension.json or bootstrap.php in extracted files
		$manifestPath = $tempExtractDir . '/extension.json';
		if ( ! file_exists( $manifestPath ) ) {
			// Check one level deep inside extracted folder
			$subdirs = array_filter( glob( $tempExtractDir . '/*' ), 'is_dir' );
			if ( ! empty( $subdirs ) && file_exists( $subdirs[0] . '/extension.json' ) ) {
				$tempExtractDir = $subdirs[0];
				$manifestPath   = $tempExtractDir . '/extension.json';
			} elseif ( ! empty( $subdirs ) ) {
				$tempExtractDir = $subdirs[0];
			}
		}

		if ( file_exists( $manifestPath ) ) {
			$manifestData = json_decode( file_get_contents( $manifestPath ), true );
			if ( is_array( $manifestData ) ) {
				$this->validator->validate( $manifestData );
			}
		} else {
			// Fallback manifest for Cloudflare ZIPs
			$autoloadFile = 'bootstrap.php';
			if ( ! file_exists( $tempExtractDir . '/bootstrap.php' ) ) {
				$phpFiles = glob( $tempExtractDir . '/*.php' );
				if ( ! empty( $phpFiles ) ) {
					$autoloadFile = basename( $phpFiles[0] );
				}
			}

			$manifestData = [
				'id'            => $extensionId,
				'name'          => ucwords( str_replace( [ '-', '_' ], ' ', $extensionId ) ),
				'version'       => '1.0.0',
				'requires_core' => '1.0.0',
				'requires_php'  => '7.4',
				'autoload'      => $autoloadFile,
			];
			file_put_contents( $tempExtractDir . '/extension.json', json_encode( $manifestData, JSON_PRETTY_PRINT ) );
		}
		$this->validator->validate( $manifestData );

		$targetDir = self::getExtensionsStorageDir() . '/' . $extensionId;
		if ( is_dir( $targetDir ) ) {
			$this->recursiveRmdir( $targetDir );
		}

		rename( $tempExtractDir, $targetDir );

		// Register in DB
		$state = new ExtensionState(
			[
				'id'             => $extensionId,
				'name'           => $manifestData['name'],
				'version'        => $manifestData['version'],
				'status'         => ExtensionState::STATUS_ACTIVE,
				'license_key'    => $licenseKey,
				'license_status' => ! empty( $licenseKey ) ? 'active' : 'free',
				'installed_path' => $targetDir,
			]
		);

		return $this->repository->save( $state );
	}

	private function recursiveRmdir( string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return false;
		}
		$files = array_diff( scandir( $dir ), [ '.', '..' ] );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			( is_dir( $path ) ) ? $this->recursiveRmdir( $path ) : unlink( $path );
		}
		return rmdir( $dir );
	}
}
