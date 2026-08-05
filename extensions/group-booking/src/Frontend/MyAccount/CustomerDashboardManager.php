<?php
/**
 * Customer Dashboard Manager.
 *
 * Enhances WooCommerce My Account dashboard with Group Booking participant details.
 *
 * @package SlotNova\Extensions\GroupBooking\Frontend\MyAccount
 */

namespace SlotNova\Extensions\GroupBooking\Frontend\MyAccount;

use SlotNova\Extensions\GroupBooking\Repositories\ParticipantRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CustomerDashboardManager {

	private ParticipantRepository $participantRepo;

	public function __construct( ParticipantRepository $participantRepo ) {
		$this->participantRepo = $participantRepo;
	}

	/**
	 * Output participant details in My Account View Order details.
	 *
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	public function renderOrderDetailsParticipants( \WC_Order $order ): void {
		$participants = $this->participantRepo->getByOrderId( $order->get_id() );
		if ( empty( $participants ) ) {
			return;
		}
		?>
		<section class="woocommerce-customer-details" style="margin-top: 24px;">
			<h2 class="woocommerce-column__title" style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 12px;"><?php esc_html_e( 'Registered Group Participants', 'slotnova-booking' ); ?></h2>
			<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Participant Name', 'slotnova-booking' ); ?></th>
						<th><?php esc_html_e( 'Email', 'slotnova-booking' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'slotnova-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $participants as $p ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $p->name ); ?></strong></td>
							<td><?php echo esc_html( $p->email ? $p->email : '-' ); ?></td>
							<td><?php echo esc_html( $p->phone ? $p->phone : '-' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php
	}
}
