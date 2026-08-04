/**
 * SlotNova Deposits Extension - Admin JavaScript
 *
 * @package SlotNova\Extensions\Deposits
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		// 1. Settings Toggle Switch & Calculation Cards Logic
		var $toggleCheckbox = $('#slotnova_deposit_enabled_toggle');
		var $toggleTrack    = $('#slotnova_toggle_track');
		var $toggleKnob     = $('#slotnova_toggle_knob');

		if ($toggleCheckbox.length && $toggleTrack.length) {
			$toggleTrack.on('click', function(e) {
				e.preventDefault();
				var isChecked = !$toggleCheckbox.prop('checked');
				$toggleCheckbox.prop('checked', isChecked).trigger('change');
			});

			$toggleCheckbox.on('change', function() {
				if ($(this).is(':checked')) {
					$toggleTrack.css('background-color', '#4f46e5');
					$toggleKnob.css('left', '24px');
				} else {
					$toggleTrack.css('background-color', '#cbd5e1');
					$toggleKnob.css('left', '3px');
				}
			});
		}

		$('.slotnova-type-card-radio').on('click', function() {
			$('.slotnova-type-card').removeClass('active').css({
				'border-color': '#e2e8f0',
				'background': '#ffffff'
			});
			$(this).closest('.slotnova-type-card').addClass('active').css({
				'border-color': '#4f46e5',
				'background': '#f5f3ff'
			});
		});

		// 2. SlotNova Bookings Table Modal Details Handler
		$(document).on('click', '.slotnova-open-details-modal', function() {
			var booking = $(this).data('booking');
			if (!booking) return;

			var $existingBox = $('#slotnova-modal-deposit-box');
			if ($existingBox.length) $existingBox.remove();

			if (booking.is_deposit) {
				var statusBadge = $('#bd-modal-status-badge');
				if (booking.status_raw === 'partial-deposit' || booking.status === 'Partial Deposit') {
					statusBadge.attr('class', 'slotnova-badge status-partial-deposit').text('Partial Deposit');
				}

				var totalFormatted = booking.total_formatted || ('$' + (booking.total || '0.00'));
				var depositHtml = '<div id="slotnova-modal-deposit-box" class="slotnova-modal-deposit-box">' +
					'<div class="slotnova-modal-deposit-header">' +
					'<div class="slotnova-modal-deposit-title-group">' +
					'<span class="dashicons dashicons-money-alt" style="font-size: 18px; color: #6b21a8; width: 18px; height: 18px;"></span>' +
					'<h4 style="margin: 0; font-size: 13px; font-weight: 700; color: #6b21a8; text-transform: uppercase; letter-spacing: 0.5px;">Deposit & Payment Breakdown</h4>' +
					'</div>' +
					'<span style="background: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff; border-radius: 20px; padding: 2px 10px; font-size: 11px; font-weight: 700;">PARTIAL DEPOSIT</span>' +
					'</div>' +
					'<div class="slotnova-modal-deposit-grid">' +
					'<div><span style="font-size: 11px; color: #64748b; font-weight: 600; display: block; text-transform: uppercase; margin-bottom: 4px;">Total Price</span><strong style="font-size: 15px; color: #0f172a; font-weight: 700;">' + totalFormatted + '</strong></div>' +
					'<div><span style="font-size: 11px; color: #64748b; font-weight: 600; display: block; text-transform: uppercase; margin-bottom: 4px;">Paid Upfront</span><strong style="font-size: 15px; color: #166534; font-weight: 700;">' + (booking.deposit_paid_formatted || '$0.00') + '</strong></div>' +
					'<div><span style="font-size: 11px; color: #64748b; font-weight: 600; display: block; text-transform: uppercase; margin-bottom: 4px;">Balance Due</span><strong style="font-size: 15px; color: #dc2626; font-weight: 700;">' + (booking.deposit_due_formatted || '$0.00') + '</strong></div>' +
					'</div></div>';

				$('.slotnova-details-contact-card').before(depositHtml);
			}
		});

	});

})(jQuery);
