<?php
/**
 * Attendance Repository.
 *
 * Handles database operations for participant attendance records.
 *
 * @package SlotNova\Extensions\GroupBooking\Repositories
 */

namespace SlotNova\Extensions\GroupBooking\Repositories;

use SlotNova\Extensions\GroupBooking\Models\Attendance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AttendanceRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'slotnova_group_attendance';
	}

	public function getTableName(): string {
		return $this->table;
	}

	/**
	 * Insert or update attendance record.
	 *
	 * @param array $data Attendance data.
	 * @return int Record ID or 0.
	 */
	public function save( array $data ): int {
		global $wpdb;

		$participantId = isset( $data['participant_id'] ) ? (int) $data['participant_id'] : 0;
		if ( $participantId <= 0 ) {
			return 0;
		}

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table} WHERE participant_id = %d", $participantId ) );

		if ( $existing ) {
			$wpdb->update(
				$this->table,
				array(
					'attendance_status' => isset( $data['attendance_status'] ) ? sanitize_text_field( $data['attendance_status'] ) : 'pending',
					'marked_by'         => isset( $data['marked_by'] ) ? (int) $data['marked_by'] : get_current_user_id(),
					'marked_at'         => current_time( 'mysql', true ),
					'notes'             => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
				),
				array( 'id' => (int) $existing ),
				array( '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
			return (int) $existing;
		}

		$inserted = $wpdb->insert(
			$this->table,
			array(
				'participant_id'    => $participantId,
				'product_id'        => isset( $data['product_id'] ) ? (int) $data['product_id'] : 0,
				'service_id'        => isset( $data['service_id'] ) ? (int) $data['service_id'] : 0,
				'booking_date'      => isset( $data['booking_date'] ) ? sanitize_text_field( $data['booking_date'] ) : '',
				'booking_time'      => isset( $data['booking_time'] ) ? sanitize_text_field( $data['booking_time'] ) : '',
				'attendance_status' => isset( $data['attendance_status'] ) ? sanitize_text_field( $data['attendance_status'] ) : 'pending',
				'marked_by'         => isset( $data['marked_by'] ) ? (int) $data['marked_by'] : get_current_user_id(),
				'marked_at'         => current_time( 'mysql', true ),
				'notes'             => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Get attendance record by participant ID.
	 *
	 * @param int $participantId Participant ID.
	 * @return Attendance|null
	 */
	public function getByParticipantId( int $participantId ): ?Attendance {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE participant_id = %d", $participantId ),
			ARRAY_A
		);

		return $row ? new Attendance( $row ) : null;
	}

	/**
	 * Get attendance summary report for slot.
	 *
	 * @param int    $productId Product ID.
	 * @param int    $serviceId Service term ID.
	 * @param string $date Date.
	 * @param string $time Time.
	 * @return array Counts grouped by status.
	 */
	public function getAttendanceStatsForSlot( int $productId, int $serviceId, string $date, string $time = '' ): array {
		global $wpdb;

		if ( empty( $time ) ) {
			$sql = $wpdb->prepare(
				"SELECT attendance_status, COUNT(*) as count FROM {$this->table} WHERE product_id = %d AND service_id = %d AND booking_date = %s GROUP BY attendance_status",
				$productId,
				$serviceId,
				$date
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT attendance_status, COUNT(*) as count FROM {$this->table} WHERE product_id = %d AND service_id = %d AND booking_date = %s AND booking_time = %s GROUP BY attendance_status",
				$productId,
				$serviceId,
				$date,
				$time
			);
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );
		$stats   = array(
			'checked_in' => 0,
			'absent'     => 0,
			'late'       => 0,
			'pending'    => 0,
		);

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$status = $row['attendance_status'];
				if ( isset( $stats[ $status ] ) ) {
					$stats[ $status ] = (int) $row['count'];
				}
			}
		}

		return $stats;
	}
}
