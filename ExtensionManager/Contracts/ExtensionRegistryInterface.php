<?php
/**
 * Extension Registry Interface.
 *
 * @package SlotNova\Booking\ExtensionManager\Contracts
 */

namespace SlotNova\Booking\ExtensionManager\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ExtensionRegistryInterface {
	public function getInstalled(): array;
	public function getActive(): array;
	public function isInstalled( string $extensionId ): bool;
	public function isActive( string $extensionId ): bool;
}
