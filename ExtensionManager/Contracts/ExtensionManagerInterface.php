<?php
/**
 * Extension Manager Interface.
 *
 * @package SlotNova\Booking\ExtensionManager\Contracts
 */

namespace SlotNova\Booking\ExtensionManager\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ExtensionManagerInterface {
	/**
	 * Boot the Extension Manager and load active extensions.
	 *
	 * @return void
	 */
	public function boot(): void;

	/**
	 * Scan configured directories for extensions.
	 *
	 * @return array Array of ExtensionManifest objects.
	 */
	public function scan(): array;

	/**
	 * Download and install an extension remotely.
	 *
	 * @param string $extensionId Extension ID.
	 * @param string $licenseKey  Freemius/SlotNova License Key.
	 * @return bool True on success.
	 */
	public function install( string $extensionId, string $licenseKey = '' ): bool;

	/**
	 * Activate an installed extension.
	 *
	 * @param string $extensionId Extension ID.
	 * @return bool True on success.
	 */
	public function activate( string $extensionId ): bool;

	/**
	 * Deactivate an active extension.
	 *
	 * @param string $extensionId Extension ID.
	 * @return bool True on success.
	 */
	public function deactivate( string $extensionId ): bool;

	/**
	 * Uninstall and remove extension files.
	 *
	 * @param string $extensionId Extension ID.
	 * @return bool True on success.
	 */
	public function uninstall( string $extensionId ): bool;

	/**
	 * Update an installed extension.
	 *
	 * @param string $extensionId Extension ID.
	 * @return bool True on success.
	 */
	public function update( string $extensionId ): bool;
}
