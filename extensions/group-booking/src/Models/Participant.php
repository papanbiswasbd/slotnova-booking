<?php
/**
 * Participant Model.
 *
 * @package SlotNova\Extensions\GroupBooking\Models
 */

namespace SlotNova\Extensions\GroupBooking\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Participant {
	public int $id = 0;
	public int $orderId = 0;
	public int $orderItemId = 0;
	public int $productId = 0;
	public int $serviceId = 0;
	public string $bookingDate = '';
	public string $bookingTime = '';
	public int $customerUserId = 0;
	public string $name = '';
	public string $email = '';
	public string $phone = '';
	public string $gender = '';
	public ?int $age = null;
	public string $notes = '';
	public string $createdAt = '';

	public function __construct( array $data = array() ) {
		if ( ! empty( $data ) ) {
			$this->id             = isset( $data['id'] ) ? (int) $data['id'] : 0;
			$this->orderId        = isset( $data['order_id'] ) ? (int) $data['order_id'] : 0;
			$this->orderItemId    = isset( $data['order_item_id'] ) ? (int) $data['order_item_id'] : 0;
			$this->productId      = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
			$this->serviceId      = isset( $data['service_id'] ) ? (int) $data['service_id'] : 0;
			$this->bookingDate    = isset( $data['booking_date'] ) ? (string) $data['booking_date'] : '';
			$this->bookingTime    = isset( $data['booking_time'] ) ? (string) $data['booking_time'] : '';
			$this->customerUserId = isset( $data['customer_user_id'] ) ? (int) $data['customer_user_id'] : 0;
			$this->name           = isset( $data['participant_name'] ) ? (string) $data['participant_name'] : '';
			$this->email          = isset( $data['participant_email'] ) ? (string) $data['participant_email'] : '';
			$this->phone          = isset( $data['participant_phone'] ) ? (string) $data['participant_phone'] : '';
			$this->gender         = isset( $data['participant_gender'] ) ? (string) $data['participant_gender'] : '';
			$this->age            = isset( $data['participant_age'] ) && '' !== $data['participant_age'] ? (int) $data['participant_age'] : null;
			$this->notes          = isset( $data['participant_notes'] ) ? (string) $data['participant_notes'] : '';
			$this->createdAt      = isset( $data['created_at'] ) ? (string) $data['created_at'] : '';
		}
	}

	public function toArray(): array {
		return array(
			'id'                => $this->id,
			'order_id'          => $this->orderId,
			'order_item_id'     => $this->orderItemId,
			'product_id'        => $this->productId,
			'service_id'        => $this->serviceId,
			'booking_date'      => $this->bookingDate,
			'booking_time'      => $this->bookingTime,
			'customer_user_id' => $this->customerUserId,
			'participant_name'  => $this->name,
			'participant_email' => $this->email,
			'participant_phone' => $this->phone,
			'participant_gender'=> $this->gender,
			'participant_age'   => $this->age,
			'participant_notes' => $this->notes,
			'created_at'        => $this->createdAt,
		);
	}
}
