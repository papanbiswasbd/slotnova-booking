<?php
/**
 * Attendance REST Controller.
 *
 * Exposes /wp-json/slotnova/v1/group-booking/attendance REST endpoint.
 *
 * @package SlotNova\Extensions\GroupBooking\REST
 */

namespace SlotNova\Extensions\GroupBooking\REST;

use SlotNova\Extensions\GroupBooking\Services\AttendanceService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AttendanceRestController extends \WP_REST_Controller {

	protected $namespace = 'slotnova/v1';
	protected $rest_base = 'group-booking/attendance';
	private AttendanceService $attendanceService;

	public function __construct( AttendanceService $attendanceService ) {
		$this->attendanceService = $attendanceService;
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	public function permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	public function create_item( $request ) {
		$participantId = (int) $request->get_param( 'participant_id' );
		$status        = sanitize_text_field( (string) $request->get_param( 'status' ) );
		$notes         = sanitize_textarea_field( (string) $request->get_param( 'notes' ) );

		if ( $participantId <= 0 || empty( $status ) ) {
			return new \WP_Error( 'missing_params', __( 'participant_id and status are required.', 'slotnova-booking' ), array( 'status' => 400 ) );
		}

		$success = $this->attendanceService->markAttendance( $participantId, $status, $notes );

		if ( $success ) {
			return rest_ensure_response( array( 'success' => true, 'message' => __( 'Attendance updated.', 'slotnova-booking' ) ) );
		}

		return new \WP_Error( 'update_failed', __( 'Could not update attendance status.', 'slotnova-booking' ), array( 'status' => 500 ) );
	}
}
