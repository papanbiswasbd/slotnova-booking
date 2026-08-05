<?php
/**
 * Admin Reports Controller.
 *
 * Renders Group Booking analytics and reports cards in WP Admin.
 *
 * @package SlotNova\Extensions\GroupBooking\Admin\Controllers
 */

namespace SlotNova\Extensions\GroupBooking\Admin\Controllers;

use SlotNova\Extensions\GroupBooking\Repositories\ParticipantRepository;
use SlotNova\Extensions\GroupBooking\Repositories\AttendanceRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportsController {

	private ParticipantRepository $participantRepo;
	private AttendanceRepository $attendanceRepo;

	public function __construct(
		ParticipantRepository $participantRepo,
		AttendanceRepository $attendanceRepo
	) {
		$this->participantRepo = $participantRepo;
		$this->attendanceRepo  = $attendanceRepo;
	}

	public function registerAdminMenu(): void {
		add_submenu_page(
			'slotnova-booking',
			__( 'Group Analytics & Reports', 'slotnova-booking' ),
			__( 'Group Reports', 'slotnova-booking' ),
			'manage_options',
			'slotnova-group-reports',
			array( $this, 'renderPage' )
		);
	}

	public function renderPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user capacity.', 'slotnova-booking' ) );
		}

		global $wpdb;

		$totalParticipants = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->participantRepo->getTableName()}" );
		$totalCheckedIn   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->attendanceRepo->getTableName()} WHERE attendance_status = %s", 'checked_in' ) );
		$totalAbsent      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->attendanceRepo->getTableName()} WHERE attendance_status = %s", 'absent' ) );

		$attendanceRate = $totalParticipants > 0 ? round( ( $totalCheckedIn / $totalParticipants ) * 100, 1 ) : 0;
		?>
		<div class="wrap slotnova-admin-wrap" style="max-width: 1200px; margin: 20px auto;">
			<h1 style="font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 20px;"><?php esc_html_e( 'Group Booking Reports & Analytics', 'slotnova-booking' ); ?></h1>

			<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">
				<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<span style="font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Total Attendees', 'slotnova-booking' ); ?></span>
					<div style="font-size: 28px; font-weight: 800; color: #0f172a; margin-top: 8px;"><?php echo esc_html( number_format_i18n( $totalParticipants ) ); ?></div>
				</div>

				<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<span style="font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Attendance Rate', 'slotnova-booking' ); ?></span>
					<div style="font-size: 28px; font-weight: 800; color: #16a34a; margin-top: 8px;"><?php echo esc_html( $attendanceRate ); ?>%</div>
				</div>

				<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<span style="font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'No-Shows (Absent)', 'slotnova-booking' ); ?></span>
					<div style="font-size: 28px; font-weight: 800; color: #dc2626; margin-top: 8px;"><?php echo esc_html( number_format_i18n( $totalAbsent ) ); ?></div>
				</div>
			</div>
		</div>
		<?php
	}
}
