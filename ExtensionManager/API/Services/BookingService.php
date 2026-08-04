<?php
/**
 * Booking Domain Service.
 *
 * @package SlotNova\Booking\ExtensionManager\API\Services
 */

namespace SlotNova\Booking\ExtensionManager\API\Services;

use SlotNova\Booking\ExtensionManager\Contracts\BookingServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BookingService implements BookingServiceInterface {

	public function getBooking( int $bookingId ): ?array {
		$post = get_post( $bookingId );
		if ( ! $post || 'wc_booking' !== $post->post_type ) {
			return null;
		}
		return [
			'id'          => $post->ID,
			'status'      => $post->post_status,
			'created_at'  => $post->post_date,
			'customer_id' => get_post_meta( $post->ID, '_booking_customer_id', true ),
		];
	}

	public function createBooking( array $data ): int {
		return (int) apply_filters( 'slotnova_create_booking', 0, $data );
	}

	public function updateBooking( int $bookingId, array $data ): bool {
		return (bool) apply_filters( 'slotnova_update_booking', false, $bookingId, $data );
	}

	public function listBookings( array $args = [] ): array {
		$query = new \WP_Query(
			array_merge(
				[
					'post_type'      => 'wc_booking',
					'posts_per_page' => 20,
				],
				$args
			)
		);
		$results = [];
		foreach ( $query->posts as $post ) {
			$results[] = [
				'id'     => $post->ID,
				'status' => $post->post_status,
			];
		}
		return $results;
	}
}
