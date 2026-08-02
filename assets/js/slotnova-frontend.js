/**
 * SlotNova Booking Frontend JavaScript
 *
 * @package SlotNova\Booking
 * @version 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
	'use strict';

	var summaryBox = document.getElementById('slotnova-summary');
	var summaryName = document.getElementById('summary-service-name');
	var summaryEmployeeName = document.getElementById('summary-employee-name');
	var summaryEmployeeRow = document.getElementById('summary-employee-row');
	var summaryPrice = document.getElementById('summary-service-price');
	var summaryDate = document.getElementById('summary-booking-date');
	var summaryTime = document.getElementById('summary-booking-time');

	var timeSlotsWrapper = document.querySelector('.slotnova-time-slots-wrapper');
	var timeInput = document.getElementById('slotnova_booking_time');

	var dateInput = document.getElementById('slotnova_booking_date');
	if (dateInput && typeof flatpickr !== 'undefined') {
		var offDaysRaw = dateInput.getAttribute('data-off-days');
		var disableArr = [];
		if (offDaysRaw) {
			var offDays = offDaysRaw.split(',').map(function(item) { return item.trim(); });
			var dayNameToNum = { 'sunday': 0, 'monday': 1, 'tuesday': 2, 'wednesday': 3, 'thursday': 4, 'friday': 5, 'saturday': 6 };

			offDays.forEach(function(day) {
				var lowerDay = day.toLowerCase();
				if (dayNameToNum.hasOwnProperty(lowerDay)) {
					var dayNum = dayNameToNum[lowerDay];
					disableArr.push(function(date) { return date.getDay() === dayNum; });
				} else if (day) {
					// Specific date YYYY-MM-DD
					disableArr.push(day);
				}
			});
		}

		flatpickr(dateInput, {
			dateFormat: 'Y-m-d',
			minDate: 'today',
			inline: true,
			disable: disableArr,
			onChange: function(selectedDates, dateStr) {
				if (dateStr) {
					// Reveal time slots grid
					if (timeSlotsWrapper) {
						timeSlotsWrapper.classList.remove('slotnova-is-hidden');
					}
					// Update Summary Date
					if (summaryDate) summaryDate.textContent = dateStr;

					// Clear selected time slot on date change
					if (timeInput) {
						timeInput.value = '';
					}
					var activePill = document.querySelector('.slotnova-time-pill.active');
					if (activePill) activePill.classList.remove('active');

					if (summaryTime) summaryTime.textContent = '-';

					// Check booked slots for selected date via AJAX
					fetchBookedSlots(dateStr);

					// Update summary box visibility
					checkSummaryVisibility();
				}
			}
		});
	}

	function fetchBookedSlots(dateStr) {
		var bookingForm = document.querySelector('.slotnova-form');
		var productId = bookingForm ? bookingForm.getAttribute('data-product-id') : 0;

		if (!productId || !dateStr || typeof slotnova_params === 'undefined' || !slotnova_params.ajax_url) {
			return;
		}

		var serviceInput = document.getElementById('slotnova_service');
		var employeeInput = document.getElementById('slotnova_employee');

		var serviceId = serviceInput ? serviceInput.value : '';
		var employeeId = employeeInput ? employeeInput.value : '';

		var timePills = document.querySelectorAll('.slotnova-time-pill');

		var formData = new FormData();
		formData.append('action', 'slotnova_get_booked_slots');
		formData.append('product_id', productId);
		formData.append('date', dateStr);
		formData.append('service_id', serviceId);
		formData.append('employee_id', employeeId);
		formData.append('nonce', slotnova_params.nonce);

		fetch(slotnova_params.ajax_url, {
			method: 'POST',
			body: formData
		})
		.then(function(res) { return res.json(); })
		.then(function(res) {
			var booked = (res.success && res.data && res.data.booked_slots) ? res.data.booked_slots : [];
			var bookedLabel = (typeof slotnova_params !== 'undefined' && slotnova_params.booked_text) ? slotnova_params.booked_text : 'Booked';

			timePills.forEach(function(pill) {
				var slotVal = pill.getAttribute('data-value');
				if (booked.indexOf(slotVal) !== -1) {
					pill.classList.add('disabled');
					pill.disabled = true;
					pill.setAttribute('title', bookedLabel);
					if (pill.classList.contains('active')) {
						pill.classList.remove('active');
						if (timeInput) timeInput.value = '';
						if (summaryTime) summaryTime.textContent = '-';
					}
				} else {
					pill.classList.remove('disabled');
					pill.disabled = false;
					pill.removeAttribute('title');
				}
			});
		})
		.catch(function(err) {
			console.error('Error fetching booked slots:', err);
		});
	}

	// Time Slots Pill Click Handler
	var timePills = document.querySelectorAll('.slotnova-time-pill');
	timePills.forEach(function(pill) {
		pill.addEventListener('click', function(e) {
			e.preventDefault();
			if (this.disabled || this.classList.contains('disabled')) {
				return false;
			}
			timePills.forEach(function(p) { p.classList.remove('active'); });
			this.classList.add('active');

			var value = this.getAttribute('data-value');
			if (timeInput) timeInput.value = value;
			if (summaryTime) summaryTime.textContent = value;

			checkSummaryVisibility();
		});
	});

	// Custom Dropdown Logic
	var dropdowns = document.querySelectorAll('.slotnova-custom-select');

	dropdowns.forEach(function(dropdown) {
		var trigger = dropdown.querySelector('.slotnova-select-trigger');
		var optionsContainer = dropdown.querySelector('.slotnova-select-options');
		var hiddenInput = dropdown.parentElement.querySelector('input[type="hidden"]');

		var isServiceDropdown = hiddenInput && hiddenInput.id === 'slotnova_service';
		var isEmployeeDropdown = hiddenInput && hiddenInput.id === 'slotnova_employee';

		if (trigger && optionsContainer) {
			trigger.addEventListener('click', function(e) {
				e.stopPropagation();
				// Close all other dropdowns
				document.querySelectorAll('.slotnova-select-options').forEach(function(other) {
					if (other !== optionsContainer) other.classList.remove('open');
				});
				document.querySelectorAll('.slotnova-select-trigger').forEach(function(other) {
					if (other !== trigger) other.classList.remove('active');
				});

				optionsContainer.classList.toggle('open');
				trigger.classList.toggle('active');
			});

			var options = optionsContainer.querySelectorAll('.slotnova-select-option');
			options.forEach(function(option) {
				option.addEventListener('click', function(e) {
					e.stopPropagation();

					var value = this.getAttribute('data-value');
					var name = this.getAttribute('data-name');

					// Update hidden input
					if (hiddenInput) {
						hiddenInput.value = value;
					}

					// Update visible trigger content
					var triggerContent = trigger.querySelector('.slotnova-select-trigger-content');
					if (triggerContent) {
						triggerContent.innerHTML = this.innerHTML;
					}

					// Close dropdown
					optionsContainer.classList.remove('open');
					trigger.classList.remove('active');

					// Handle Service Selection Summary
					if (isServiceDropdown) {
						var priceVal = parseFloat(this.getAttribute('data-price'));
						if (summaryName) summaryName.textContent = name || '-';
						if (summaryPrice) {
							if (priceVal > 0) {
								var symbol = (typeof slotnova_params !== 'undefined') ? slotnova_params.currency_symbol : '$';
								summaryPrice.textContent = symbol + priceVal.toFixed(2);
							} else {
								summaryPrice.textContent = (typeof slotnova_params !== 'undefined') ? slotnova_params.free_text : 'Free';
							}
						}
					}

					// Handle Employee Selection Summary
					if (isEmployeeDropdown) {
						if (summaryEmployeeName) summaryEmployeeName.textContent = name || '-';
						if (summaryEmployeeRow) {
							if (value) {
								summaryEmployeeRow.classList.remove('slotnova-is-hidden');
							} else {
								summaryEmployeeRow.classList.add('slotnova-is-hidden');
							}
						}
					}

					// Re-evaluate time slot availability for the selected Service or Employee
					if (dateInput && dateInput.value) {
						fetchBookedSlots(dateInput.value);
					}

					checkSummaryVisibility();
				});
			});
		}
	});

	function checkSummaryVisibility() {
		if (!summaryBox) return;
		var serviceInput = document.getElementById('slotnova_service');
		if (serviceInput && serviceInput.value) {
			summaryBox.classList.remove('slotnova-is-hidden');
		} else {
			summaryBox.classList.add('slotnova-is-hidden');
		}
	}

	// Close dropdowns when clicking outside
	document.addEventListener('click', function() {
		document.querySelectorAll('.slotnova-select-options').forEach(function(container) {
			container.classList.remove('open');
		});
		document.querySelectorAll('.slotnova-select-trigger').forEach(function(trigger) {
			trigger.classList.remove('active');
		});
	});

	// Form Validation
	var bookingForm = document.querySelector('.slotnova-form');
	if (bookingForm) {
		bookingForm.addEventListener('submit', function(e) {
			var serviceInput = document.getElementById('slotnova_service');
			var employeeInput = document.getElementById('slotnova_employee');
			var dateInput = document.getElementById('slotnova_booking_date');
			var timeInput = document.getElementById('slotnova_booking_time');

			var i18n = (typeof slotnova_params !== 'undefined' && slotnova_params.i18n) ? slotnova_params.i18n : {};

			if (serviceInput && !serviceInput.value) {
				alert(i18n.select_service || 'Please select a service before booking.');
				e.preventDefault();
				return false;
			}
			if (employeeInput && !employeeInput.value && document.getElementById('slotnova_employee_dropdown')) {
				alert(i18n.select_employee || 'Please select an employee before booking.');
				e.preventDefault();
				return false;
			}
			if (dateInput && !dateInput.value) {
				alert(i18n.select_date || 'Please select a date before booking.');
				e.preventDefault();
				return false;
			}
			if (timeInput && !timeInput.value && document.querySelector('.slotnova-time-slots-wrapper')) {
				alert(i18n.select_time || 'Please select a time before booking.');
				e.preventDefault();
				return false;
			}
		});
	}
});
