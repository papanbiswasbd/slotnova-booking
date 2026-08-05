<?php
/**
 * Attendance Model.
 *
 * @package SlotNova\Extensions\GroupBooking\Models
 */

namespace SlotNova\Extensions\GroupBooking\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Attendance {
	public int $id = 0;
	public int $participantId = 0;
	public int $productId = 0;
	public int $serviceId = 0;
	public string $bookingDate = '';
	public string $bookingTime = '';
	public string $attendanceStatus = 'pending'; // pending, checked_in, absent, late
	public int $markedBy = 0;
	public ?string $markedAt = null;
	public string $notes = '';

	public function __construct( array $data = array() ) {
		if ( ! empty( $data ) ) {
			$this->id               = isset( $data['id'] ) ? (int) $data['id'] : 0;
			$this->participantId    = isset( $data['participant_id'] ) ? (int) $data['participant_id'] : 0;
			$this->productId        = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
			$this->serviceId        = isset( $data['service_id'] ) ? (int) $data['service_id'] : 0;
			$this->bookingDate      = isset( $data['booking_date'] ) ? (string) $data['booking_date'] : '';
			$this->bookingTime      = isset( $data['booking_time'] ) ? (string) $data['booking_time'] : '';
			$this->attendanceStatus = isset( $data['attendance_status'] ) ? (string) $data['attendance_status'] : 'pending';
			$this->markedBy         = isset( $data['marked_by'] ) ? (int) $data['marked_by'] : 0;
			$this->markedAt         = isset( $data['marked_at'] ) ? (string) $data['marked_at'] : null;
			$this->notes            = isset( $data['notes'] ) ? (string) $data['notes'] : '';
		}
	}

	public function toArray(): array {
		return array(
			'id'                => $this->id,
			'participant_id'    => $this->participantId,
			'product_id'        => $this->productId,
			'service_id'        => $this->serviceId,
			'booking_date'      => $this->bookingDate,
			'booking_time'      => $this->bookingTime,
			'attendance_status' => $this->attendanceStatus,
			'marked_by'         => $this->markedBy,
			'marked_at'         => $this->markedAt,
			'notes'             => $this->notes,
		);
	}
}
