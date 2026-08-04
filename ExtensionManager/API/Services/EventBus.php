<?php
/**
 * Event Bus Implementation wrapping WordPress Actions & Filters.
 *
 * @package SlotNova\Booking\ExtensionManager\API\Services
 */

namespace SlotNova\Booking\ExtensionManager\API\Services;

use SlotNova\Booking\ExtensionManager\Contracts\EventBusInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventBus implements EventBusInterface {

	/**
	 * Register an event listener (uses add_action / add_filter under the hood).
	 *
	 * @param string   $eventName Event/Hook identifier.
	 * @param callable $listener  Callback function.
	 * @param int      $priority  Priority level.
	 * @return void
	 */
	public function listen( string $eventName, callable $listener, int $priority = 10 ): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		add_action( $eventName, $listener, $priority, 99 );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		add_filter( $eventName, $listener, $priority, 99 );
	}

	/**
	 * Dispatch an event with optional payload data.
	 *
	 * @param string $eventName Event/Hook identifier.
	 * @param mixed  $payload   Payload data.
	 * @return mixed Filtered payload.
	 */
	public function dispatch( string $eventName, $payload = null ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		do_action( $eventName, $payload );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		return apply_filters( $eventName, $payload );
	}
}
