<?php
/**
 * Extension Lifecycle Manager.
 *
 * Handles activation, deactivation, uninstallation, and lifecycle hooks.
 *
 * @package SlotNova\Booking\ExtensionManager\Services
 */

namespace SlotNova\Booking\ExtensionManager\Services;

use SlotNova\Booking\ExtensionManager\Repositories\ExtensionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LifecycleManager {

	private ExtensionRepository $repository;

	public function __construct( ExtensionRepository $repository ) {
		$this->repository = $repository;
	}

	public function activate( string $extensionId ): bool {
		$success = $this->repository->updateStatus( $extensionId, 'active' );
		if ( $success ) {
			do_action( 'slotnova_extension_activated', $extensionId );
		}
		return $success;
	}

	public function deactivate( string $extensionId ): bool {
		$success = $this->repository->updateStatus( $extensionId, 'inactive' );
		if ( $success ) {
			do_action( 'slotnova_extension_deactivated', $extensionId );
		}
		return $success;
	}

	public function uninstall( string $extensionId ): bool {
		$state = $this->repository->get( $extensionId );
		$path  = $state ? $state->getInstalledPath() : '';

		if ( empty( $path ) || ! is_dir( $path ) ) {
			// Check standard upload directory and local extensions directory
			$uploadDir = Installer::getExtensionsStorageDir() . '/' . $extensionId;
			$localDir  = SLOTNOVA_BOOKING_PATH . 'extensions/' . $extensionId;

			if ( is_dir( $uploadDir ) ) {
				$path = $uploadDir;
			} elseif ( is_dir( $localDir ) ) {
				$path = $localDir;
			}
		}

		// Purge directory if path exists
		if ( ! empty( $path ) && is_dir( $path ) ) {
			$this->recursiveRmdir( $path );
		}

		// Remove DB record
		$this->repository->delete( $extensionId );

		do_action( 'slotnova_extension_uninstalled', $extensionId );
		return true;
	}

	private function recursiveRmdir( string $dir ): bool {
		$files = array_diff( scandir( $dir ), [ '.', '..' ] );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			( is_dir( $path ) ) ? $this->recursiveRmdir( $path ) : unlink( $path );
		}
		return rmdir( $dir );
	}
}
