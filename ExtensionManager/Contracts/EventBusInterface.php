<?php
/**
 * Event Bus Interface for SlotNova Extension Hook System.
 *
 * @package SlotNova\Booking\ExtensionManager\Contracts
 */

namespace SlotNova\Booking\ExtensionManager\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface EventBusInterface {
	/**
	 * Register an event listener.
	 *
	 * @param string   $eventName Name of the event/hook.
	 * @param callable $listener  Callback function.
	 * @param int      $priority  Priority level.
	 * @return void
	 */
	public function listen( string $eventName, callable $listener, int $priority = 10 ): void;

	/**
	 * Dispatch an event with optional payload data.
	 *
	 * @param string $eventName Name of the event/hook.
	 * @param mixed  $payload   Payload data passed to listeners.
	 * @return mixed Filtered payload or dispatch results.
	 */
	public function dispatch( string $eventName, $payload = null );
}
