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
	// Helper function to convert 12-hour AM/PM time string ("10:00 AM", "01:30 PM") to 24-hour "HH:MM"
	function timeTo24h(timeStr) {
		if (!timeStr) return '';
		var str = timeStr.trim();
		var parts = str.split(' ');
		if (parts.length < 2) return str;
		var timeParts = parts[0].split(':');
		var hours = parseInt(timeParts[0], 10);
		var minutes = parseInt(timeParts[1], 10);
		var ampm = parts[1].toUpperCase();

		if (ampm === 'PM' && hours < 12) hours += 12;
		if (ampm === 'AM' && hours === 12) hours = 0;

		var hStr = hours < 10 ? '0' + hours : '' + hours;
		var mStr = minutes < 10 ? '0' + minutes : '' + minutes;
		return hStr + ':' + mStr;
	}

	var siteDate = (typeof slotnova_params !== 'undefined' && slotnova_params.site_current_date) ? slotnova_params.site_current_date : '';
	var siteTime = (typeof slotnova_params !== 'undefined' && slotnova_params.site_current_time) ? slotnova_params.site_current_time : '';

	function isDateDisabled(dateObj, disableArr) {
		for (var i = 0; i < disableArr.length; i++) {
			var dRule = disableArr[i];
			if (typeof dRule === 'function' && dRule(dateObj)) {
				return true;
			}
			if (typeof dRule === 'string') {
				var y = dateObj.getFullYear();
				var m = ('0' + (dateObj.getMonth() + 1)).slice(-2);
				var d = ('0' + dateObj.getDate()).slice(-2);
				if ((y + '-' + m + '-' + d) === dRule) {
					return true;
				}
			}
		}
		return false;
	}

	function normalizeTo24h(timeStr) {
		if (!timeStr) return '';
		var str = timeStr.trim();
		if (str.indexOf('AM') !== -1 || str.indexOf('PM') !== -1) {
			return timeTo24h(str);
		}
		var parts = str.split(':');
		if (parts.length >= 2) {
			var h = parseInt(parts[0], 10);
			var m = parseInt(parts[1], 10);
			var hStr = h < 10 ? '0' + h : '' + h;
			var mStr = m < 10 ? '0' + m : '' + m;
			return hStr + ':' + mStr;
		}
		return str;
	}

	function getFirstAvailableDate(startDateObj, closingTime, disableArr) {
		var checkDate = new Date(startDateObj.getTime());
		var todayYMD = siteDate;

		var closing24h = normalizeTo24h(closingTime);
		var siteTime24h = normalizeTo24h(siteTime);

		var isTodayPastClosing = false;
		if (closing24h && siteTime24h && siteTime24h >= closing24h) {
			isTodayPastClosing = true;
		}

		for (var dayOffset = 0; dayOffset < 90; dayOffset++) {
			var y = checkDate.getFullYear();
			var m = ('0' + (checkDate.getMonth() + 1)).slice(-2);
			var d = ('0' + checkDate.getDate()).slice(-2);
			var currentYMD = y + '-' + m + '-' + d;

			var isOff = isDateDisabled(checkDate, disableArr);

			if (!isOff) {
				if (currentYMD === todayYMD) {
					if (!isTodayPastClosing) {
						return currentYMD;
					}
				} else {
					return currentYMD;
				}
			}
			checkDate.setDate(checkDate.getDate() + 1);
		}
		return todayYMD;
	}

	var dateInput = document.getElementById('slotnova_booking_date');
	if (dateInput && typeof flatpickr !== 'undefined') {
		var offDaysRaw = dateInput.getAttribute('data-off-days');
		var closingTime = dateInput.getAttribute('data-closing-time');
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

		// If today is past closing time, disable today's date in Flatpickr so user cannot select it
		var closing24h = normalizeTo24h(closingTime);
		var siteTime24h = normalizeTo24h(siteTime);
		if (closing24h && siteTime24h && siteTime24h >= closing24h && siteDate) {
			if (disableArr.indexOf(siteDate) === -1) {
				disableArr.push(siteDate);
			}
		}

		// Disable fully booked dates
		var enableTimeSlots = dateInput.getAttribute('data-enable-time-slots') || 'yes';
		var bookedDatesRaw = dateInput.getAttribute('data-booked-dates') || '[]';
		var bookedDates = [];
		try { bookedDates = JSON.parse(bookedDatesRaw); } catch(e) { bookedDates = []; }

		bookedDates.forEach(function(bDate) {
			if (bDate && disableArr.indexOf(bDate) === -1) {
				disableArr.push(bDate);
			}
		});

		var calendarMode = (typeof slotnova_params !== 'undefined' && slotnova_params.calendar_mode) ? slotnova_params.calendar_mode : 'inline';
		var isInlineMode = (calendarMode !== 'popup');

		var fpInstance = flatpickr(dateInput, {
			dateFormat: 'Y-m-d',
			minDate: 'today',
			inline: isInlineMode,
			disable: disableArr,
			onDayCreate: function(dObj, dStr, fp, dayElement) {
				if (!dayElement || !dayElement.dateObj) return;
				var yyyy = dayElement.dateObj.getFullYear();
				var mm = String(dayElement.dateObj.getMonth() + 1).padStart(2, '0');
				var dd = String(dayElement.dateObj.getDate()).padStart(2, '0');
				var formattedDate = yyyy + '-' + mm + '-' + dd;

				if (bookedDates.indexOf(formattedDate) !== -1) {
					dayElement.classList.add('slotnova-fully-booked-day', 'flatpickr-disabled');
					dayElement.setAttribute('title', 'Already Booked');
					dayElement.setAttribute('data-tooltip', 'Already Booked');
				}
			},
			onChange: function(selectedDates, dateStr) {
				if (dateStr) {
					if (dateInput) dateInput.value = dateStr;
					if (summaryDate) summaryDate.textContent = dateStr;

					if (enableTimeSlots === 'no') {
						var tInput = document.getElementById('slotnova_booking_time');
						if (tInput) tInput.value = 'All Day';
						if (summaryTime) summaryTime.textContent = 'All Day';
					} else {
						// Clear selected time slot on date change
						var tInput = document.getElementById('slotnova_booking_time');
						if (tInput) tInput.value = '';
						var activePill = document.querySelector('.slotnova-time-pill.active');
						if (activePill) activePill.classList.remove('active');

						if (summaryTime) summaryTime.textContent = '-';

						// Reveal time slots grid and fetch availability for selected date
						var tSlotsWrapper = document.querySelector('.slotnova-time-slots-wrapper');
						if (tSlotsWrapper) {
							tSlotsWrapper.classList.remove('slotnova-is-hidden');
							tSlotsWrapper.style.display = 'block';
						}
						fetchBookedSlots(dateStr);
					}

					checkSummaryVisibility();
				}
			}
		});

		if (!isInlineMode) {
			var pickerIcon = document.querySelector('.slotnova-date-picker-icon');
			if (pickerIcon) {
				pickerIcon.addEventListener('click', function() {
					if (fpInstance) fpInstance.open();
				});
			}
		}
	}

	function fetchBookedSlots(dateStr) {
		var tSlotsWrapper = document.querySelector('.slotnova-time-slots-wrapper');
		if (tSlotsWrapper) {
			tSlotsWrapper.classList.remove('slotnova-is-hidden');
			tSlotsWrapper.style.display = 'block';
		}

		var bookingForm = document.querySelector('.slotnova-form') || document.querySelector('form.cart') || document.querySelector('[data-product-id]');
		var productId = bookingForm ? bookingForm.getAttribute('data-product-id') : 0;
		if (!productId) {
			var addBtn = document.querySelector('button[name="add-to-cart"]');
			if (addBtn) productId = addBtn.value;
		}
		if (!productId) {
			var valInput = document.querySelector('input[name="add-to-cart"]');
			if (valInput) productId = valInput.value;
		}

		if (!dateStr || typeof slotnova_params === 'undefined' || !slotnova_params.ajax_url) {
			return;
		}

		var serviceInput = document.getElementById('slotnova_service');
		var employeeInput = document.getElementById('slotnova_employee');
		var timeInput = document.getElementById('slotnova_booking_time');

		var serviceId = serviceInput ? serviceInput.value : '';
		var employeeId = employeeInput ? employeeInput.value : '';

		var timePills = document.querySelectorAll('.slotnova-time-pill');
		var isToday = (dateStr === siteDate);
		var passedLabel = (typeof slotnova_params !== 'undefined' && slotnova_params.passed_text) ? slotnova_params.passed_text : 'Time Passed';
		var bookedLabel = (typeof slotnova_params !== 'undefined' && slotnova_params.booked_text) ? slotnova_params.booked_text : 'Booked';
		var passedHint  = (typeof slotnova_params !== 'undefined' && slotnova_params.passed_hint) ? slotnova_params.passed_hint : 'This time slot has already passed for today. Please select another date or time.';
		var bookedHint  = (typeof slotnova_params !== 'undefined' && slotnova_params.booked_hint) ? slotnova_params.booked_hint : 'This time slot is already booked. Please try selecting a different date, employee, or service.';

		var disablePast = (typeof slotnova_params !== 'undefined' && typeof slotnova_params.disable_past_slots !== 'undefined') ? (slotnova_params.disable_past_slots === true || slotnova_params.disable_past_slots === 'yes' || slotnova_params.disable_past_slots === '1') : false;

		// INSTANT UI RESET: Clear disabled state unless past slots disabling is explicitly turned on
		timePills.forEach(function(pill) {
			var slotVal = pill.getAttribute('data-value');
			var isPassed = false;
			if (disablePast && isToday && siteTime) {
				var slot24h = timeTo24h(slotVal);
				if (slot24h && slot24h <= siteTime) {
					isPassed = true;
				}
			}
			if (isPassed) {
				pill.classList.add('disabled');
				pill.disabled = true;
				pill.setAttribute('title', passedLabel);
				pill.setAttribute('data-tooltip', passedHint);
			} else {
				pill.classList.remove('disabled');
				pill.disabled = false;
				pill.removeAttribute('title');
				pill.removeAttribute('data-tooltip');
			}
		});

		var pillsGrid = document.getElementById('slotnova_time_pills');
		if (pillsGrid) pillsGrid.classList.add('loading');

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
			if (pillsGrid) pillsGrid.classList.remove('loading');
			var booked = (res.success && res.data && res.data.booked_slots) ? res.data.booked_slots : [];

			timePills.forEach(function(pill) {
				var slotVal = pill.getAttribute('data-value');
				var slot24 = timeTo24h(slotVal);
				var isBooked = false;

				for (var b = 0; b < booked.length; b++) {
					if (timeTo24h(booked[b]) === slot24) {
						isBooked = true;
						break;
					}
				}
				
				var isPassed = false;
				if (disablePast && isToday && siteTime) {
					var slot24h = timeTo24h(slotVal);
					if (slot24h && slot24h <= siteTime) {
						isPassed = true;
					}
				}

				if (isBooked || isPassed) {
					pill.classList.add('disabled');
					pill.disabled = true;
					var hintMsg = isPassed ? passedHint : bookedHint;
					var titleMsg = isPassed ? passedLabel : bookedLabel;
					pill.setAttribute('title', titleMsg);
					pill.setAttribute('data-tooltip', hintMsg);

					if (pill.classList.contains('active')) {
						pill.classList.remove('active');
						if (timeInput) timeInput.value = '';
						if (summaryTime) summaryTime.textContent = '-';
					}
				} else {
					pill.classList.remove('disabled');
					pill.disabled = false;
					pill.removeAttribute('title');
					pill.removeAttribute('data-tooltip');
				}
			});
		})
		.catch(function(err) {
			if (pillsGrid) pillsGrid.classList.remove('loading');
			console.error('Error fetching booked slots:', err);
		});
	}

	// Time Slots Pill Click Handler (Event Delegation)
	document.addEventListener('click', function(e) {
		var pill = e.target.closest('.slotnova-time-pill');
		if (!pill) return;

		e.preventDefault();
		if (pill.disabled || pill.classList.contains('disabled')) {
			return false;
		}

		var allPills = document.querySelectorAll('.slotnova-time-pill');
		allPills.forEach(function(p) { p.classList.remove('active'); });
		pill.classList.add('active');

		var value = pill.getAttribute('data-value');
		var tInput = document.getElementById('slotnova_booking_time');
		var sTime = document.getElementById('summary-booking-time');

		if (tInput) tInput.value = value;
		if (sTime) sTime.textContent = value;

		checkSummaryVisibility();
	});

	// Custom Dropdown Logic
	var dropdowns = document.querySelectorAll('.slotnova-custom-select');

	dropdowns.forEach(function(dropdown) {
		var trigger = dropdown.querySelector('.slotnova-select-trigger');
		var searchInput = dropdown.querySelector('.slotnova-select-search-input');
		var optionsContainer = dropdown.querySelector('.slotnova-select-options');
		var hiddenInput = dropdown.parentElement.querySelector('input[type="hidden"]');
		var noResults = dropdown.querySelector('.slotnova-select-no-results');

		var isServiceDropdown = hiddenInput && hiddenInput.id === 'slotnova_service';
		var isEmployeeDropdown = hiddenInput && hiddenInput.id === 'slotnova_employee';
		var isTimeDropdown = (hiddenInput && hiddenInput.id === 'slotnova_booking_time') || (dropdown.id === 'slotnova_time_dropdown');
		var selectedName = '';

		function showAllOptions() {
			var options = optionsContainer.querySelectorAll('.slotnova-select-option');
			options.forEach(function(opt) {
				opt.classList.remove('slotnova-is-hidden');
				opt.style.removeProperty('display');
			});
			if (noResults) noResults.classList.add('slotnova-is-hidden');
		}

		function filterOptions(query) {
			query = query.trim().toLowerCase();
			var options = optionsContainer.querySelectorAll('.slotnova-select-option');
			var visibleCount = 0;

			options.forEach(function(option) {
				var name = (option.getAttribute('data-name') || '').toLowerCase();
				var fullText = (option.textContent || '').toLowerCase();
				if (!query || name.indexOf(query) !== -1 || fullText.indexOf(query) !== -1) {
					option.classList.remove('slotnova-is-hidden');
					option.style.removeProperty('display');
					visibleCount++;
				} else {
					option.classList.add('slotnova-is-hidden');
					option.style.setProperty('display', 'none', 'important');
				}
			});

			if (noResults) {
				if (visibleCount === 0) {
					noResults.classList.remove('slotnova-is-hidden');
				} else {
					noResults.classList.add('slotnova-is-hidden');
				}
			}
		}

		function openDropdown() {
			// Close all other dropdowns
			document.querySelectorAll('.slotnova-select-options').forEach(function(other) {
				if (other !== optionsContainer) other.classList.remove('open');
			});
			document.querySelectorAll('.slotnova-select-trigger').forEach(function(other) {
				if (other !== trigger) other.classList.remove('active');
			});
			document.querySelectorAll('.slotnova-dropdown-open').forEach(function(el) {
				el.classList.remove('slotnova-dropdown-open');
			});

			showAllOptions();
			optionsContainer.classList.add('open');
			trigger.classList.add('active');
			dropdown.classList.add('slotnova-dropdown-open');
			var parentWrapper = dropdown.closest('.form-row, .slotnova-custom-select-wrapper, .slotnova-time-slots-wrapper');
			if (parentWrapper) parentWrapper.classList.add('slotnova-dropdown-open');

			if (searchInput) {
				showAllOptions();
				searchInput.select();
			}
		}

		function closeDropdown() {
			optionsContainer.classList.remove('open');
			trigger.classList.remove('active');
			dropdown.classList.remove('slotnova-dropdown-open');
			var parentWrapper = dropdown.closest('.form-row, .slotnova-custom-select-wrapper, .slotnova-time-slots-wrapper');
			if (parentWrapper) parentWrapper.classList.remove('slotnova-dropdown-open');

			if (searchInput) {
				searchInput.value = selectedName;
			}
			showAllOptions();
		}

		if (trigger && optionsContainer) {
			trigger.addEventListener('click', function(e) {
				e.stopPropagation();
				if (!optionsContainer.classList.contains('open')) {
					openDropdown();
				}
			});

			if (searchInput) {
				searchInput.addEventListener('focus', function(e) {
					e.stopPropagation();
					if (!optionsContainer.classList.contains('open')) {
						openDropdown();
					} else {
						this.select();
					}
				});

				['input', 'keyup', 'change'].forEach(function(evtName) {
					searchInput.addEventListener(evtName, function(e) {
						e.stopPropagation();
						if (!optionsContainer.classList.contains('open')) {
							openDropdown();
						}
						filterOptions(this.value);
					});
				});
			}

			var options = optionsContainer.querySelectorAll('.slotnova-select-option');
			options.forEach(function(option) {
				option.addEventListener('click', function(e) {
					e.stopPropagation();

					var value = this.getAttribute('data-value');
					var name = this.getAttribute('data-name');
					var displayName = name;

					options.forEach(function(opt) { opt.classList.remove('active', 'selected'); });
					this.classList.add('active', 'selected');

					// Handle Service Selection Summary & Display Price in Trigger
					if (isServiceDropdown) {
						var priceVal = parseFloat(this.getAttribute('data-price'));
						var priceText = '';
						if (!isNaN(priceVal)) {
							if (priceVal > 0) {
								var symbol = (typeof slotnova_params !== 'undefined') ? slotnova_params.currency_symbol : '$';
								priceText = ' (' + symbol + priceVal.toFixed(2) + ')';
							} else if (priceVal === 0) {
								priceText = ' (' + ((typeof slotnova_params !== 'undefined') ? slotnova_params.free_text : 'Free') + ')';
							}
						}
						displayName = name + priceText;

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

					selectedName = displayName;

					// Update hidden input and trigger search input
					if (hiddenInput) {
						hiddenInput.value = value;
						// Store the price so the deposits extension can read it
						if (isServiceDropdown && !isNaN(priceVal)) {
							hiddenInput.setAttribute('data-price', priceVal);
						}
					}
					if (searchInput) {
						searchInput.value = displayName;
					}

					// Close dropdown
					closeDropdown();

					// Handle Employee Selection Summary
					if (isEmployeeDropdown) {
						if (summaryEmployeeName) summaryEmployeeName.textContent = name || '-';
						if (summaryEmployeeRow) {
							if (value) {
								summaryEmployeeRow.classList.remove('slotnova-is-hidden');
								summaryEmployeeRow.style.display = 'flex';
							} else {
								summaryEmployeeRow.classList.add('slotnova-is-hidden');
								summaryEmployeeRow.style.display = 'none';
							}
						}
					}

					// Handle Time Selection Summary
					if (isTimeDropdown) {
						if (summaryTime) summaryTime.textContent = value || name || '-';
						var tInput = document.getElementById('slotnova_booking_time');
						if (tInput) tInput.value = value;

						var allTimePills = document.querySelectorAll('.slotnova-time-pill');
						allTimePills.forEach(function(p) { p.classList.remove('active'); });
						option.classList.add('active');
					}

					// Reset active time slot selection on service/employee change
					if (isServiceDropdown || isEmployeeDropdown) {
						var tInput = document.getElementById('slotnova_booking_time');
						if (tInput) tInput.value = '';
						var activePill = document.querySelector('.slotnova-time-pill.active');
						if (activePill) activePill.classList.remove('active');
						if (summaryTime) summaryTime.textContent = '-';

						var timeSearchInput = document.getElementById('slotnova_booking_time_trigger');
						if (timeSearchInput) timeSearchInput.value = '';
					}

					// Re-evaluate time slot availability for the selected Service or Employee
					var dInput = document.getElementById('slotnova_booking_date');
					if (dInput && dInput.value) {
						var tWrapper = document.querySelector('.slotnova-time-slots-wrapper');
						if (tWrapper) tWrapper.classList.remove('slotnova-is-hidden');
						fetchBookedSlots(dInput.value);
					}

					checkSummaryVisibility();
				});
			});
		}
	});

	document.addEventListener('click', function() {
		document.querySelectorAll('.slotnova-select-options').forEach(function(opt) {
			opt.classList.remove('open');
		});
		document.querySelectorAll('.slotnova-select-trigger').forEach(function(trig) {
			trig.classList.remove('active');
		});
	});

	function formatSummaryTotalPrice() {
		var summaryPrice = document.getElementById('summary-service-price');
		if (!summaryPrice) return;

		var selectedServiceOption = document.querySelector('#slotnova_service_dropdown .slotnova-select-option.active, #slotnova_service_dropdown .slotnova-select-option.selected');
		if (selectedServiceOption) {
			var priceVal = parseFloat(selectedServiceOption.getAttribute('data-price'));
			if (!isNaN(priceVal) && priceVal >= 0) {
				var symbol = (typeof slotnova_params !== 'undefined' && slotnova_params.currency_symbol) ? slotnova_params.currency_symbol : '$';
				summaryPrice.innerHTML = (priceVal > 0) ? (symbol + priceVal.toFixed(2)) : ((typeof slotnova_params !== 'undefined') ? slotnova_params.free_text : 'Free');
				return;
			}
		}

		var defaultPrice = parseFloat(summaryPrice.getAttribute('data-default-price'));
		if (!isNaN(defaultPrice) && defaultPrice >= 0) {
			var symbol = (typeof slotnova_params !== 'undefined' && slotnova_params.currency_symbol) ? slotnova_params.currency_symbol : '$';
			summaryPrice.innerHTML = (defaultPrice > 0) ? (symbol + defaultPrice.toFixed(2)) : ((typeof slotnova_params !== 'undefined') ? slotnova_params.free_text : 'Free');
		}
	}

	function checkSummaryVisibility() {
		if (!summaryBox) return;
		var serviceInput = document.getElementById('slotnova_service');
		var employeeInput = document.getElementById('slotnova_employee');
		var dateInput = document.getElementById('slotnova_booking_date');
		var timeInput = document.getElementById('slotnova_booking_time');

		var hasSelection = (serviceInput && serviceInput.value) || (employeeInput && employeeInput.value) || (dateInput && dateInput.value) || (timeInput && timeInput.value);

		if (hasSelection) {
			summaryBox.classList.remove('slotnova-is-hidden');
			summaryBox.style.display = 'block';
			formatSummaryTotalPrice();
		} else {
			summaryBox.classList.add('slotnova-is-hidden');
			summaryBox.style.display = 'none';
		}
	}

	// Run initial summary visibility check
	checkSummaryVisibility();

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
