<?php
/**
 * Capacity REST Controller.
 *
 * Exposes /wp-json/slotnova/v1/group-booking/capacity REST endpoint.
 *
 * @package SlotNova\Extensions\GroupBooking\REST
 */

namespace SlotNova\Extensions\GroupBooking\REST;

use SlotNova\Extensions\GroupBooking\Services\CapacityValidationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CapacityRestController extends \WP_REST_Controller {

	protected $namespace = 'slotnova/v1';
	protected $rest_base = 'group-booking/capacity';
	private CapacityValidationService $capacityService;

	public function __construct( CapacityValidationService $capacityService ) {
		$this->capacityService = $capacityService;
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	public function get_item( $request ) {
		$productId = (int) $request->get_param( 'product_id' );
		$serviceId = (int) $request->get_param( 'service_id' );
		$date      = sanitize_text_field( (string) $request->get_param( 'date' ) );
		$time      = sanitize_text_field( (string) $request->get_param( 'time' ) );

		if ( $productId <= 0 || empty( $date ) ) {
			return new \WP_Error( 'missing_params', __( 'product_id and date parameters are required.', 'slotnova-booking' ), array( 'status' => 400 ) );
		}

		$capacity = $this->capacityService->getSlotCapacity( $productId, $serviceId, $date, $time );

		return rest_ensure_response( $capacity->toArray() );
	}
}
