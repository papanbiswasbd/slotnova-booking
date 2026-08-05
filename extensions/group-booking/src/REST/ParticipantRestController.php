<?php
/**
 * Participant REST Controller.
 *
 * Exposes /wp-json/slotnova/v1/group-booking/participants REST endpoint.
 *
 * @package SlotNova\Extensions\GroupBooking\REST
 */

namespace SlotNova\Extensions\GroupBooking\REST;

use SlotNova\Extensions\GroupBooking\Repositories\ParticipantRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ParticipantRestController extends \WP_REST_Controller {

	protected $namespace = 'slotnova/v1';
	protected $rest_base = 'group-booking/participants';
	private ParticipantRepository $participantRepo;

	public function __construct( ParticipantRepository $participantRepo ) {
		$this->participantRepo = $participantRepo;
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	public function permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	public function get_items( $request ) {
		$orderId   = (int) $request->get_param( 'order_id' );
		$productId = (int) $request->get_param( 'product_id' );
		$serviceId = (int) $request->get_param( 'service_id' );
		$date      = sanitize_text_field( (string) $request->get_param( 'date' ) );
		$time      = sanitize_text_field( (string) $request->get_param( 'time' ) );

		if ( $orderId > 0 ) {
			$participants = $this->participantRepo->getByOrderId( $orderId );
		} elseif ( $productId > 0 && ! empty( $date ) ) {
			$participants = $this->participantRepo->getParticipantsForSlot( $productId, $serviceId, $date, $time );
		} else {
			return new \WP_Error( 'missing_params', __( 'Provide either order_id or product_id & date.', 'slotnova-booking' ), array( 'status' => 400 ) );
		}

		$data = array_map( function( $p ) {
			return $p->toArray();
		}, $participants );

		return rest_ensure_response( $data );
	}
}
