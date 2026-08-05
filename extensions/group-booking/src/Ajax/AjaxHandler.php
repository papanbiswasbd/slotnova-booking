<?php
/**
 * AJAX Handler.
 *
 * Handles AJAX requests for frontend capacity badges, calendar date capacity tooltips, dynamic participant form fields, and price calculations.
 *
 * @package SlotNova\Extensions\GroupBooking\Ajax
 */

namespace SlotNova\Extensions\GroupBooking\Ajax;

use SlotNova\Extensions\GroupBooking\Services\CapacityValidationService;
use SlotNova\Extensions\GroupBooking\Services\PricingEngineService;
use SlotNova\Extensions\GroupBooking\Services\AttendanceService;
use SlotNova\Extensions\GroupBooking\Helpers\GroupBookingHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AjaxHandler {

	private CapacityValidationService $capacityService;
	private PricingEngineService $pricingEngine;
	private AttendanceService $attendanceService;

	public function __construct(
		CapacityValidationService $capacityService,
		PricingEngineService $pricingEngine,
		AttendanceService $attendanceService
	) {
		$this->capacityService   = $capacityService;
		$this->pricingEngine     = $pricingEngine;
		$this->attendanceService = $attendanceService;
	}

	public function registerHooks(): void {
		add_action( 'wp_ajax_slotnova_group_get_capacity', array( $this, 'getCapacity' ) );
		add_action( 'wp_ajax_nopriv_slotnova_group_get_capacity', array( $this, 'getCapacity' ) );

		add_action( 'wp_ajax_slotnova_group_get_date_capacity', array( $this, 'getDateCapacity' ) );
		add_action( 'wp_ajax_nopriv_slotnova_group_get_date_capacity', array( $this, 'getDateCapacity' ) );

		add_action( 'wp_ajax_slotnova_group_calculate_price', array( $this, 'calculatePrice' ) );
		add_action( 'wp_ajax_nopriv_slotnova_group_calculate_price', array( $this, 'calculatePrice' ) );

		add_action( 'wp_ajax_slotnova_group_mark_attendance', array( $this, 'markAttendance' ) );
	}

	public function getCapacity(): void {
		check_ajax_referer( 'slotnova_group_nonce', 'nonce' );

		$pId  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$sId  = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
		$date = isset( $_POST['booking_date'] ) ? sanitize_text_field( $_POST['booking_date'] ) : '';
		$time = isset( $_POST['booking_time'] ) ? sanitize_text_field( $_POST['booking_time'] ) : '';

		$capacity = $this->capacityService->getSlotCapacity( $pId, $sId, $date, $time );

		wp_send_json_success( $capacity->toArray() );
	}

	public function getDateCapacity(): void {
		check_ajax_referer( 'slotnova_group_nonce', 'nonce' );

		$pId  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$sId  = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
		$date = isset( $_POST['booking_date'] ) ? sanitize_text_field( $_POST['booking_date'] ) : '';

		if ( $pId <= 0 || empty( $date ) ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		}

		$capacity = $this->capacityService->getSlotCapacity( $pId, $sId, $date, '' );

		wp_send_json_success( array(
			'date'            => $date,
			'remaining_seats' => $capacity->remainingSeats,
			'max_capacity'    => $capacity->maxCapacity,
			'booked_seats'    => $capacity->bookedSeats,
			'is_full'         => $capacity->isFull,
		) );
	}

	public function calculatePrice(): void {
		check_ajax_referer( 'slotnova_group_nonce', 'nonce' );

		$pId   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$sId   = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
		$qty   = isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 1;
		$price = isset( $_POST['base_price'] ) ? floatval( $_POST['base_price'] ) : 0;

		if ( $price <= 0 && $pId > 0 ) {
			if ( $sId > 0 ) {
				$saved_services = get_post_meta( $pId, '_slotnova_product_services', true );
				if ( is_array( $saved_services ) ) {
					foreach ( $saved_services as $saved ) {
						if ( isset( $saved['term_id'] ) && (int) $saved['term_id'] === $sId ) {
							if ( isset( $saved['price'] ) && '' !== $saved['price'] && floatval( $saved['price'] ) > 0 ) {
								$price = floatval( $saved['price'] );
							} else {
								$term_price = get_term_meta( $sId, 'slotnova_service_price', true );
								if ( '' !== $term_price && false !== $term_price ) {
									$price = floatval( $term_price );
								}
							}
							break;
						}
					}
				}
			}

			if ( $price <= 0 ) {
				$groupBasePrice = GroupBookingHelper::getGroupBasePrice( $pId );
				if ( $groupBasePrice > 0 ) {
					$price = $groupBasePrice;
				} else {
					$product = wc_get_product( $pId );
					if ( $product ) {
						$price = floatval( $product->get_price() );
					}
				}
			}
		}

		$calc = $this->pricingEngine->calculateGroupPrice( $price, $qty, $pId );
		$calc['formatted_total'] = wc_price( $calc['total_price'] );
		$calc['formatted_unit']  = wc_price( $calc['unit_price'] );

		wp_send_json_success( $calc );
	}

	public function markAttendance(): void {
		check_ajax_referer( 'slotnova_group_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized capability.', 'slotnova-booking' ) ) );
		}

		$pId    = isset( $_POST['participant_id'] ) ? absint( $_POST['participant_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'pending';

		$success = $this->attendanceService->markAttendance( $pId, $status );

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'Attendance updated.', 'slotnova-booking' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update attendance.', 'slotnova-booking' ) ) );
		}
	}
}
