<?php
/**
 * Admin Attendance Controller.
 *
 * Renders participant roster attendance management page in WP Admin.
 *
 * @package SlotNova\Extensions\GroupBooking\Admin\Controllers
 */

namespace SlotNova\Extensions\GroupBooking\Admin\Controllers;

use SlotNova\Extensions\GroupBooking\Repositories\ParticipantRepository;
use SlotNova\Extensions\GroupBooking\Services\AttendanceService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AttendanceController {

	private ParticipantRepository $participantRepo;
	private AttendanceService $attendanceService;

	public function __construct( ParticipantRepository $participantRepo, AttendanceService $attendanceService ) {
		$this->participantRepo   = $participantRepo;
		$this->attendanceService = $attendanceService;
	}

	public function registerAdminMenu(): void {
		add_submenu_page(
			'slotnova-booking',
			__( 'Group Attendance', 'slotnova-booking' ),
			__( 'Attendance Roster', 'slotnova-booking' ),
			'manage_options',
			'slotnova-group-attendance',
			array( $this, 'renderPage' )
		);
	}

	public function renderPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user capacity.', 'slotnova-booking' ) );
		}

		$dateFilter = isset( $_GET['booking_date'] ) ? sanitize_text_field( $_GET['booking_date'] ) : current_time( 'Y-m-d' );
		global $wpdb;

		$sql     = $wpdb->prepare( "SELECT * FROM {$this->participantRepo->getTableName()} WHERE booking_date = %s ORDER BY booking_time ASC, id ASC", $dateFilter );
		$results = $wpdb->get_results( $sql, ARRAY_A );
		?>
		<div class="wrap slotnova-admin-wrap" style="max-width: 1200px; margin: 20px auto;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
				<h1 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;"><?php esc_html_e( 'Group Attendance Roster', 'slotnova-booking' ); ?></h1>
				<form method="get" action="">
					<input type="hidden" name="page" value="slotnova-group-attendance" />
					<label style="font-weight: 600; font-size: 13px; margin-right: 8px;"><?php esc_html_e( 'Filter Date:', 'slotnova-booking' ); ?></label>
					<input type="date" name="booking_date" value="<?php echo esc_attr( $dateFilter ); ?>" onchange="this.form.submit()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1;" />
				</form>
			</div>

			<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<table class="wp-list-table widefat fixed striped" style="border: none;">
					<thead>
						<tr>
							<th style="font-weight: 700; padding: 12px 16px;"><?php esc_html_e( 'Time Slot', 'slotnova-booking' ); ?></th>
							<th style="font-weight: 700; padding: 12px 16px;"><?php esc_html_e( 'Participant Name', 'slotnova-booking' ); ?></th>
							<th style="font-weight: 700; padding: 12px 16px;"><?php esc_html_e( 'Contact Info', 'slotnova-booking' ); ?></th>
							<th style="font-weight: 700; padding: 12px 16px;"><?php esc_html_e( 'Order #', 'slotnova-booking' ); ?></th>
							<th style="font-weight: 700; padding: 12px 16px;"><?php esc_html_e( 'Attendance Action', 'slotnova-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $results ) ) : ?>
							<tr>
								<td colspan="5" style="text-align: center; padding: 30px; color: #64748b;">
									<?php esc_html_e( 'No participants registered for this date.', 'slotnova-booking' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $results as $row ) :
								$att = $this->attendanceService->getSlotAttendanceSummary( (int) $row['product_id'], (int) $row['service_id'], $row['booking_date'], $row['booking_time'] );
								$status = isset( $att['status'] ) ? $att['status'] : 'pending';
							?>
								<tr>
									<td style="padding: 12px 16px; font-weight: 600; color: #4f46e5;"><?php echo esc_html( $row['booking_time'] ? $row['booking_time'] : 'All Day' ); ?></td>
									<td style="padding: 12px 16px; font-weight: 700; color: #0f172a;"><?php echo esc_html( $row['participant_name'] ); ?></td>
									<td style="padding: 12px 16px; color: #64748b; font-size: 13px;">
										<?php echo esc_html( $row['participant_email'] ); ?><br/>
										<small><?php echo esc_html( $row['participant_phone'] ); ?></small>
									</td>
									<td style="padding: 12px 16px;">
										<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row['order_id'] . '&action=edit' ) ); ?>" style="font-weight: 600; text-decoration: none; color: #2563eb;">
											#<?php echo esc_html( $row['order_id'] ); ?>
										</a>
									</td>
									<td style="padding: 12px 16px;">
										<select class="slotnova-mark-attendance-select" data-participant-id="<?php echo esc_attr( $row['id'] ); ?>" style="padding: 4px 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
											<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'slotnova-booking' ); ?></option>
											<option value="checked_in" <?php selected( $status, 'checked_in' ); ?>><?php esc_html_e( '🟢 Checked In', 'slotnova-booking' ); ?></option>
											<option value="absent" <?php selected( $status, 'absent' ); ?>><?php esc_html_e( '🔴 Absent', 'slotnova-booking' ); ?></option>
											<option value="late" <?php selected( $status, 'late' ); ?>><?php esc_html_e( '🟡 Late', 'slotnova-booking' ); ?></option>
										</select>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<script>
			jQuery(document).ready(function($){
				$('.slotnova-mark-attendance-select').on('change', function(){
					var pId = $(this).data('participant-id');
					var val = $(this).val();
					$.post(ajaxurl, {
						action: 'slotnova_group_mark_attendance',
						nonce: '<?php echo esc_js( wp_create_nonce( 'slotnova_group_nonce' ) ); ?>',
						participant_id: pId,
						status: val
					}, function(res){
						if(res.success){
							console.log('Attendance updated');
						}
					});
				});
			});
		</script>
		<?php
	}
}
