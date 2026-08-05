<?php
/**
 * Email Notification Service.
 *
 * Sends transactional email notifications for group booking events.
 *
 * @package SlotNova\Extensions\GroupBooking\Services
 */

namespace SlotNova\Extensions\GroupBooking\Services;

use SlotNova\Extensions\GroupBooking\Models\Participant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EmailNotificationService {

	/**
	 * Send generic confirmation email to participant.
	 *
	 * @param Participant $participant Participant object.
	 * @param string      $date Session date.
	 * @param string      $time Session time.
	 * @return bool
	 */
	public function sendBookingConfirmationEmail( Participant $participant, string $date, string $time ): bool {
		$product = wc_get_product( $participant->productId );
		$title   = $product ? $product->get_name() : __( 'Booking Session', 'slotnova-booking' );

		$subject = sprintf( __( 'Group Booking Confirmed - %s', 'slotnova-booking' ), $title );
		$message = sprintf(
			__( "Hello %1\$s,\n\nYour spot for %2\$s on %3\$s %4\$s has been confirmed!\n\nBest regards,\n%5\$s", 'slotnova-booking' ),
			$participant->name,
			$title,
			$date,
			$time,
			get_bloginfo( 'name' )
		);

		return $this->sendMail( $participant->email, $subject, $message );
	}

	/**
	 * Helper method to dispatch emails.
	 *
	 * @param string $to Recipient email.
	 * @param string $subject Subject line.
	 * @param string $message Email body.
	 * @return bool
	 */
	private function sendMail( string $to, string $subject, string $message ): bool {
		if ( empty( $to ) || ! is_email( $to ) ) {
			return false;
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return wp_mail( $to, $subject, $message, $headers );
	}
}
