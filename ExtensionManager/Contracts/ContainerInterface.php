<?php
/**
 * Container Interface for SlotNova Booking Extension System.
 *
 * @package SlotNova\Booking\ExtensionManager\Contracts
 */

namespace SlotNova\Booking\ExtensionManager\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ContainerInterface {
	/**
	 * Register a binding with the container.
	 *
	 * @param string $abstract Abstract type or alias.
	 * @param mixed  $concrete Concrete implementation or closure.
	 * @param bool   $singleton Whether instance should be a singleton.
	 * @return void
	 */
	public function bind( string $abstract, $concrete = null, bool $singleton = false ): void;

	/**
	 * Register a shared binding in the container.
	 *
	 * @param string $abstract Abstract type or alias.
	 * @param mixed  $concrete Concrete implementation or closure.
	 * @return void
	 */
	public function singleton( string $abstract, $concrete = null ): void;

	/**
	 * Resolve the given type from the container.
	 *
	 * @param string $abstract Abstract type or alias.
	 * @return mixed
	 */
	public function make( string $abstract );

	/**
	 * Check if the given abstract type is bound.
	 *
	 * @param string $abstract Abstract type or alias.
	 * @return bool
	 */
	public function has( string $abstract ): bool;
}
