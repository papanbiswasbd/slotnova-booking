<?php
/**
 * Staff Domain Service.
 *
 * @package SlotNova\Booking\ExtensionManager\API\Services
 */

namespace SlotNova\Booking\ExtensionManager\API\Services;

use SlotNova\Booking\ExtensionManager\Contracts\StaffServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StaffService implements StaffServiceInterface {

	public function getStaff( int $staffId ): ?array {
		$term = get_term( $staffId, 'slotnova_staff' );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		return [
			'id'   => $term->term_id,
			'name' => $term->name,
			'slug' => $term->slug,
		];
	}

	public function listStaff( array $args = [] ): array {
		$terms = get_terms(
			array_merge(
				[
					'taxonomy'   => 'slotnova_staff',
					'hide_empty' => false,
				],
				$args
			)
		);
		if ( is_wp_error( $terms ) ) {
			return [];
		}
		$list = [];
		foreach ( $terms as $t ) {
			$list[] = [
				'id'   => $t->term_id,
				'name' => $t->name,
			];
		}
		return $list;
	}
}
