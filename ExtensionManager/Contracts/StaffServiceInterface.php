<?php
/**
 * Staff Service Interface.
 *
 * @package SlotNova\Booking\ExtensionManager\Contracts
 */

namespace SlotNova\Booking\ExtensionManager\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface StaffServiceInterface {
	public function getStaff( int $staffId ): ?array;
	public function listStaff( array $args = [] ): array;
}
