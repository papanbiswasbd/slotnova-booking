<?php
/**
 * Attendance Service.
 *
 * Handles attendance status marking, check-in reporting, and participant tracking.
 *
 * @package SlotNova\Extensions\GroupBooking\Services
 */

namespace SlotNova\Extensions\GroupBooking\Services;

use SlotNova\Extensions\GroupBooking\Repositories\AttendanceRepository;
use SlotNova\Extensions\GroupBooking\Repositories\ParticipantRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AttendanceService {

	private AttendanceRepository $attendanceRepo;
	private ParticipantRepository $participantRepo;

	public function __construct( AttendanceRepository $attendanceRepo, ParticipantRepository $participantRepo ) {
		$this->attendanceRepo  = $attendanceRepo;
		$this->participantRepo = $participantRepo;
	}

	/**
	 * Mark attendance status for a participant.
	 *
	 * @param int    $participantId Participant ID.
	 * @param string $status Status ('checked_in', 'absent', 'late', 'pending').
	 * @param string $notes Optional notes.
	 * @param int    $markedBy User ID of admin marking attendance.
	 * @return bool
	 */
	public function markAttendance( int $participantId, string $status, string $notes = '', int $markedBy = 0 ): bool {
		$validStatuses = array( 'pending', 'checked_in', 'absent', 'late' );
		if ( ! in_array( $status, $validStatuses, true ) ) {
			return false;
		}

		$participants = $this->participantRepo->getByOrderItemId( $participantId );
		$productId    = 0;
		$serviceId    = 0;
		$date         = '';
		$time         = '';

		// If looking up directly by participant record ID
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->participantRepo->getTableName()} WHERE id = %d", $participantId ), ARRAY_A );
		if ( $row ) {
			$productId = (int) $row['product_id'];
			$serviceId = (int) $row['service_id'];
			$date      = (string) $row['booking_date'];
			$time      = (string) $row['booking_time'];
		}

		$recordId = $this->attendanceRepo->save( array(
			'participant_id'    => $participantId,
			'product_id'        => $productId,
			'service_id'        => $serviceId,
			'booking_date'      => $date,
			'booking_time'      => $time,
			'attendance_status' => $status,
			'marked_by'         => $markedBy > 0 ? $markedBy : get_current_user_id(),
			'notes'             => $notes,
		) );

		if ( $recordId > 0 ) {
			do_action( 'slotnova_group_attendance_saved', $participantId, $status, $notes );
			return true;
		}

		return false;
	}

	/**
	 * Get attendance stats for a slot.
	 *
	 * @param int    $productId Product ID.
	 * @param int    $serviceId Service term ID.
	 * @param string $date Date.
	 * @param string $time Time.
	 * @return array
	 */
	public function getSlotAttendanceSummary( int $productId, int $serviceId, string $date, string $time = '' ): array {
		return $this->attendanceRepo->getAttendanceStatsForSlot( $productId, $serviceId, $date, $time );
	}
}
