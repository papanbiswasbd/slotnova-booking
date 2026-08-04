<?php
/**
 * Service Domain Service.
 *
 * @package SlotNova\Booking\ExtensionManager\API\Services
 */

namespace SlotNova\Booking\ExtensionManager\API\Services;

use SlotNova\Booking\ExtensionManager\Contracts\ServiceServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ServiceService implements ServiceServiceInterface {

	public function getService( int $serviceId ): ?array {
		$post = get_post( $serviceId );
		if ( ! $post || 'product' !== $post->post_type ) {
			return null;
		}
		return [
			'id'    => $post->ID,
			'title' => $post->post_title,
		];
	}

	public function listServices( array $args = [] ): array {
		$posts = get_posts(
			array_merge(
				[
					'post_type'      => 'product',
					'posts_per_page' => 20,
				],
				$args
			)
		);
		$list = [];
		foreach ( $posts as $p ) {
			$list[] = [
				'id'    => $p->ID,
				'title' => $p->post_title,
			];
		}
		return $list;
	}
}
