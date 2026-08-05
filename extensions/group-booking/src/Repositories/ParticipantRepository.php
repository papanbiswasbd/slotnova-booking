<?php
/**
 * Participant Repository.
 *
 * Handles database operations for group participants table.
 *
 * @package SlotNova\Extensions\GroupBooking\Repositories
 */

namespace SlotNova\Extensions\GroupBooking\Repositories;

use SlotNova\Extensions\GroupBooking\Models\Participant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ParticipantRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'slotnova_group_participants';
	}

	public function getTableName(): string {
		return $this->table;
	}

	/**
	 * Insert a new participant record.
	 *
	 * @param array $data Participant data.
	 * @return int Inserted record ID or 0 on failure.
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table,
			array(
				'order_id'          => isset( $data['order_id'] ) ? (int) $data['order_id'] : 0,
				'order_item_id'     => isset( $data['order_item_id'] ) ? (int) $data['order_item_id'] : 0,
				'product_id'        => isset( $data['product_id'] ) ? (int) $data['product_id'] : 0,
				'service_id'        => isset( $data['service_id'] ) ? (int) $data['service_id'] : 0,
				'booking_date'      => isset( $data['booking_date'] ) ? sanitize_text_field( $data['booking_date'] ) : '',
				'booking_time'      => isset( $data['booking_time'] ) ? sanitize_text_field( $data['booking_time'] ) : '',
				'customer_user_id' => isset( $data['customer_user_id'] ) ? (int) $data['customer_user_id'] : 0,
				'participant_name'  => isset( $data['participant_name'] ) ? sanitize_text_field( $data['participant_name'] ) : '',
				'participant_email' => isset( $data['participant_email'] ) ? sanitize_email( $data['participant_email'] ) : '',
				'participant_phone' => isset( $data['participant_phone'] ) ? sanitize_text_field( $data['participant_phone'] ) : '',
				'participant_gender'=> isset( $data['participant_gender'] ) ? sanitize_text_field( $data['participant_gender'] ) : '',
				'participant_age'   => isset( $data['participant_age'] ) && '' !== $data['participant_age'] ? (int) $data['participant_age'] : null,
				'participant_notes' => isset( $data['participant_notes'] ) ? sanitize_textarea_field( $data['participant_notes'] ) : '',
				'created_at'        => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Get participant count for a specific date and time slot.
	 *
	 * @param int    $productId Product ID.
	 * @param int    $serviceId Service term ID.
	 * @param string $date Date (Y-m-d).
	 * @param string $time Time string.
	 * @return int
	 */
	public function getParticipantCountForSlot( int $productId, int $serviceId, string $date, string $time = '' ): int {
		global $wpdb;

		if ( empty( $time ) ) {
			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE product_id = %d AND service_id = %d AND booking_date = %s",
				$productId,
				$serviceId,
				$date
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE product_id = %d AND service_id = %d AND booking_date = %s AND booking_time = %s",
				$productId,
				$serviceId,
				$date,
				$time
			);
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get participants by order item ID.
	 *
	 * @param int $orderItemId Order Item ID.
	 * @return Participant[]
	 */
	public function getByOrderItemId( int $orderItemId ): array {
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE order_item_id = %d ORDER BY id ASC", $orderItemId ),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			return array();
		}

		return array_map( function( $row ) {
			return new Participant( $row );
		}, $results );
	}

	/**
	 * Get participants by order ID.
	 *
	 * @param int $orderId Order ID.
	 * @return Participant[]
	 */
	public function getByOrderId( int $orderId ): array {
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE order_id = %d ORDER BY id ASC", $orderId ),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			return array();
		}

		return array_map( function( $row ) {
			return new Participant( $row );
		}, $results );
	}

	/**
	 * Get participants roster for slot.
	 *
	 * @param int    $productId Product ID.
	 * @param int    $serviceId Service term ID.
	 * @param string $date Date.
	 * @param string $time Time.
	 * @return Participant[]
	 */
	public function getParticipantsForSlot( int $productId, int $serviceId, string $date, string $time = '' ): array {
		global $wpdb;

		if ( empty( $time ) ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE product_id = %d AND service_id = %d AND booking_date = %s ORDER BY id ASC",
				$productId,
				$serviceId,
				$date
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE product_id = %d AND service_id = %d AND booking_date = %s AND booking_time = %s ORDER BY id ASC",
				$productId,
				$serviceId,
				$date,
				$time
			);
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $results ) ) {
			return array();
		}

		return array_map( function( $row ) {
			return new Participant( $row );
		}, $results );
	}

	/**
	 * Delete participant records by order ID.
	 *
	 * @param int $orderId Order ID.
	 * @return bool
	 */
	public function deleteByOrderId( int $orderId ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $this->table, array( 'order_id' => $orderId ), array( '%d' ) );
	}
}
