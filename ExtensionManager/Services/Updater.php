<?php
/**
 * Extension Updater Service.
 * Performs atomic extension updates with automated failure rollback.
 *
 * @package SlotNova\Booking\ExtensionManager\Services
 */

namespace SlotNova\Booking\ExtensionManager\Services;

use SlotNova\Booking\ExtensionManager\Repositories\ExtensionRepository;
use SlotNova\Booking\ExtensionManager\Models\ExtensionState;
use SlotNova\Booking\ExtensionManager\Exceptions\ExtensionException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Updater {

	private ExtensionRepository $repository;
	private Installer $installer;
	private Loader $loader;

	public function __construct(
		ExtensionRepository $repository,
		Installer $installer,
		Loader $loader
	) {
		$this->repository = $repository;
		$this->installer = $installer;
		$this->loader    = $loader;
	}

	/**
	 * Perform update for an installed extension with backup/rollback.
	 *
	 * @param string $extensionId Extension ID.
	 * @return bool
	 * @throws ExtensionException
	 */
	public function update( string $extensionId ): bool {
		$state = $this->repository->get( $extensionId );
		if ( ! $state ) {
			throw new ExtensionException( esc_html( sprintf( "Extension '%s' is not installed.", $extensionId ) ) );
		}

		$currentPath = $state->getInstalledPath();
		$backupPath  = $currentPath . '.bak';

		if ( ! is_dir( $currentPath ) ) {
			throw new ExtensionException( esc_html( sprintf( 'Extension path does not exist: %s', $currentPath ) ) );
		}

		// Create backup
		if ( is_dir( $backupPath ) ) {
			$this->recursiveRmdir( $backupPath );
		}
		$this->copyDir( $currentPath, $backupPath );

		try {
			// Perform remote download & extraction
			$success = $this->installer->installFromRemote( $extensionId, $state->getLicenseKey() );
			if ( ! $success ) {
				throw new ExtensionException( esc_html__( 'Failed to download update package.', 'slotnova-booking' ) );
			}

			// Clean backup folder on success
			$this->recursiveRmdir( $backupPath );
			do_action( 'slotnova_extension_updated', $extensionId );
			return true;

		} catch ( \Throwable $e ) {
			// Rollback on failure
			if ( is_dir( $backupPath ) ) {
				if ( is_dir( $currentPath ) ) {
					$this->recursiveRmdir( $currentPath );
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				rename( $backupPath, $currentPath );
			}
			throw new ExtensionException( esc_html( sprintf( "Update failed for '%s': %s. Previous version restored.", $extensionId, $e->getMessage() ) ) );
		}
	}

	private function copyDir( string $src, string $dst ): void {
		if ( function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( $dst );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			@mkdir( $dst, 0755, true );
		}
		$files = array_diff( scandir( $src ), [ '.', '..' ] );
		foreach ( $files as $file ) {
			$s = $src . '/' . $file;
			$d = $dst . '/' . $file;
			( is_dir( $s ) ) ? $this->copyDir( $s, $d ) : copy( $s, $d );
		}
	}

	private function recursiveRmdir( string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return false;
		}
		$files = array_diff( scandir( $dir ), [ '.', '..' ] );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				$this->recursiveRmdir( $path );
			} else {
				if ( function_exists( 'wp_delete_file' ) ) {
					wp_delete_file( $path );
				} else {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					@unlink( $path );
				}
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		return @rmdir( $dir );
	}
}
