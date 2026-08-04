<?php
/**
 * Extension Registry Service.
 *
 * @package SlotNova\Booking\ExtensionManager\API\Services
 */

namespace SlotNova\Booking\ExtensionManager\API\Services;

use SlotNova\Booking\ExtensionManager\Contracts\ExtensionRegistryInterface;
use SlotNova\Booking\ExtensionManager\Repositories\ExtensionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ExtensionRegistry implements ExtensionRegistryInterface {

	private ExtensionRepository $repository;

	public function __construct( ExtensionRepository $repository ) {
		$this->repository = $repository;
	}

	public function getInstalled(): array {
		return $this->repository->getAllInstalled();
	}

	public function getActive(): array {
		return $this->repository->getActive();
	}

	public function isInstalled( string $extensionId ): bool {
		return $this->repository->exists( $extensionId );
	}

	public function isActive( string $extensionId ): bool {
		return $this->repository->isActive( $extensionId );
	}
}
