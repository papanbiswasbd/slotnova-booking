<?php
/**
 * Service Service Interface.
 *
 * @package SlotNova\Booking\ExtensionManager\Contracts
 */

namespace SlotNova\Booking\ExtensionManager\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ServiceServiceInterface {
	public function getService( int $serviceId ): ?array;
	public function listServices( array $args = [] ): array;
}
