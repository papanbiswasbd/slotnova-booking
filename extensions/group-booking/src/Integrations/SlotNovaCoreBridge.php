<?php
/**
 * SlotNova Core Bridge.
 *
 * Interacts with SlotNova core via public API facade slotnova().
 *
 * @package SlotNova\Extensions\GroupBooking\Integrations
 */

namespace SlotNova\Extensions\GroupBooking\Integrations;

use SlotNova\Booking\ExtensionManager\API\SlotNovaApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SlotNovaCoreBridge {

	private ?SlotNovaApi $api = null;

	public function __construct( ?SlotNovaApi $api = null ) {
		if ( null !== $api ) {
			$this->api = $api;
		} elseif ( function_exists( 'slotnova' ) ) {
			$this->api = slotnova();
		}
	}

	public function getApi(): ?SlotNovaApi {
		if ( null === $this->api && function_exists( 'slotnova' ) ) {
			$this->api = slotnova();
		}
		return $this->api;
	}

	/**
	 * Get service details using core public API.
	 *
	 * @param int $serviceId Service ID.
	 * @return array|null
	 */
	public function getService( int $serviceId ): ?array {
		$api = $this->getApi();
		if ( $api ) {
			return $api->services()->getService( $serviceId );
		}
		return null;
	}

	/**
	 * Dispatch event via core EventBus / hooks.
	 *
	 * @param string $eventName Event name.
	 * @param mixed  $payload Payload.
	 * @return mixed
	 */
	public function dispatchEvent( string $eventName, $payload = null ) {
		$api = $this->getApi();
		if ( $api ) {
			return $api->events()->dispatch( $eventName, $payload );
		}
		return $payload;
	}
}
