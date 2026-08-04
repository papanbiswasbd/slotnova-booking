<?php
/**
 * Extension Interface for SlotNova Booking Extensions.
 *
 * @package SlotNova\Booking\ExtensionManager\Contracts
 */

namespace SlotNova\Booking\ExtensionManager\Contracts;

use SlotNova\Booking\ExtensionManager\API\SlotNovaApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ExtensionInterface {
	/**
	 * Get extension unique identifier.
	 *
	 * @return string
	 */
	public function getId(): string;

	/**
	 * Get extension human-readable name.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Get extension semver version string.
	 *
	 * @return string
	 */
	public function getVersion(): string;

	/**
	 * Boot the extension when core is initializing active extensions.
	 * Extensions MUST use $api for interacting with SlotNova core.
	 *
	 * @param SlotNovaApi $api Main public API facade.
	 * @return void
	 */
	public function boot( SlotNovaApi $api ): void;

	/**
	 * Triggered when extension is activated by the user.
	 *
	 * @return void
	 */
	public function activate(): void;

	/**
	 * Triggered when extension is deactivated by the user.
	 *
	 * @return void
	 */
	public function deactivate(): void;

	/**
	 * Triggered when extension is uninstalled/deleted.
	 *
	 * @return void
	 */
	public function uninstall(): void;
}
