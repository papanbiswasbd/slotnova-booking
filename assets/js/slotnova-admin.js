/**
 * SlotNova Booking Admin JavaScript
 *
 * @package SlotNova\Booking
 * @version 1.1.0
 */

jQuery(document).ready(function($) {
	'use strict';

	// Initialize WP Color Picker on Settings page
	if ($('.slotnova-color-picker').length && $.fn.wpColorPicker) {
		$('.slotnova-color-picker').wpColorPicker();
	}

	/* -------------------------------------------------------------------------
	 * 1. Dashboard Chart (Chart.js Dual Datasets & Gradient Fills)
	 * ------------------------------------------------------------------------- */
	if (typeof slotnova_admin_data !== 'undefined' && slotnova_admin_data.chart) {
		var chartEl = document.getElementById('slotnovaBookingsChart');
		if (chartEl && typeof Chart !== 'undefined') {
			var ctx = chartEl.getContext('2d');

			// Indigo Gradient fill for bookings
			var gradientBlue = ctx.createLinearGradient(0, 0, 0, 320);
			gradientBlue.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
			gradientBlue.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

			// Emerald Gradient fill for revenue
			var gradientGreen = ctx.createLinearGradient(0, 0, 0, 320);
			gradientGreen.addColorStop(0, 'rgba(16, 185, 129, 0.4)');
			gradientGreen.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

			var dashboardChart = new Chart(ctx, {
				type: 'line',
				data: {
					labels: slotnova_admin_data.chart.labels,
					datasets: [{
						label: slotnova_admin_data.chart.i18n_label,
						data: slotnova_admin_data.chart.values,
						borderColor: '#6366f1',
						backgroundColor: gradientBlue,
						borderWidth: 3,
						pointBackgroundColor: '#6366f1',
						pointBorderColor: '#ffffff',
						pointBorderWidth: 2,
						pointRadius: 5,
						pointHoverRadius: 7,
						fill: true,
						tension: 0.4
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: { display: false },
						tooltip: {
							backgroundColor: '#0f172a',
							titleFont: { size: 13, weight: 'bold' },
							bodyFont: { size: 12 },
							padding: 10,
							cornerRadius: 8,
							displayColors: false
						}
					},
					scales: {
						x: {
							grid: { display: false },
							ticks: { color: '#64748b', font: { size: 12 } }
						},
						y: {
							beginAtZero: true,
							grid: { color: '#f1f5f9' },
							ticks: { color: '#64748b', font: { size: 12 }, stepSize: 1 }
						}
					}
				}
			});

			// Toggle chart datasets (Bookings vs Revenue)
			$(document).on('click', '.slotnova-chart-toggle-btn', function(e) {
				e.preventDefault();
				$('.slotnova-chart-toggle-btn').removeClass('active');
				$(this).addClass('active');

				var datasetType = $(this).data('dataset');

				if (datasetType === 'revenue') {
					dashboardChart.data.datasets[0].label = slotnova_admin_data.chart.i18n_revenue;
					dashboardChart.data.datasets[0].data = slotnova_admin_data.chart.revenue_values;
					dashboardChart.data.datasets[0].borderColor = '#10b981';
					dashboardChart.data.datasets[0].pointBackgroundColor = '#10b981';
					dashboardChart.data.datasets[0].backgroundColor = gradientGreen;
				} else {
					dashboardChart.data.datasets[0].label = slotnova_admin_data.chart.i18n_label;
					dashboardChart.data.datasets[0].data = slotnova_admin_data.chart.values;
					dashboardChart.data.datasets[0].borderColor = '#6366f1';
					dashboardChart.data.datasets[0].pointBackgroundColor = '#6366f1';
					dashboardChart.data.datasets[0].backgroundColor = gradientBlue;
				}

				dashboardChart.update();
			});
		}

		// Auto submit dashboard date filter dropdown
		$(document).on('change', '.slotnova-filter-select', function() {
			$(this).closest('form').submit();
		});
	}

	/* -------------------------------------------------------------------------
	 * 2. Smart Actions: CSV Export Tool
	 * ------------------------------------------------------------------------- */
	$(document).on('click', '#slotnova-export-csv-btn', function(e) {
		e.preventDefault();
		if (typeof slotnova_admin_data === 'undefined') return;

		var urlParams = new URLSearchParams(window.location.search);
		var searchVal = urlParams.get('s') || '';
		var serviceVal = urlParams.get('service') || '';
		var employeeVal = urlParams.get('employee') || '';
		var statusVal = urlParams.get('status') || '';

		var exportUrl = slotnova_admin_data.ajax_url +
			'?action=slotnova_export_bookings_csv' +
			'&security=' + encodeURIComponent(slotnova_admin_data.nonce) +
			'&search=' + encodeURIComponent(searchVal) +
			'&service=' + encodeURIComponent(serviceVal) +
			'&employee=' + encodeURIComponent(employeeVal) +
			'&status=' + encodeURIComponent(statusVal);

		window.location.href = exportUrl;
	});

	/* -------------------------------------------------------------------------
	 * 3. Smart Actions: Add Manual Booking Modal & Schedule Controls
	 * ------------------------------------------------------------------------- */
	var $modal = $('#slotnova-manual-booking-modal');

	function mbTimeTo24h(timeStr) {
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

	function mbNormalizeTo24h(timeStr) {
		if (!timeStr) return '';
		var str = timeStr.trim();
		if (str.indexOf('AM') !== -1 || str.indexOf('PM') !== -1) {
			return mbTimeTo24h(str);
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

	function mbIsDateDisabled(dateObj, disableArr) {
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

	function mbGetFirstAvailableDate(startDateObj, closingTime, disableArr) {
		var checkDate = new Date(startDateObj.getTime());
		var siteDate = (typeof slotnova_admin_data !== 'undefined' && slotnova_admin_data.site_current_date) ? slotnova_admin_data.site_current_date : '';
		var siteTime = (typeof slotnova_admin_data !== 'undefined' && slotnova_admin_data.site_current_time) ? slotnova_admin_data.site_current_time : '';
		var todayYMD = siteDate || checkDate.toISOString().split('T')[0];

		var closing24h = mbNormalizeTo24h(closingTime);
		var siteTime24h = mbNormalizeTo24h(siteTime);

		var isTodayPastClosing = false;
		if (closing24h && siteTime24h && siteTime24h >= closing24h) {
			isTodayPastClosing = true;
		}

		for (var dayOffset = 0; dayOffset < 90; dayOffset++) {
			var y = checkDate.getFullYear();
			var m = ('0' + (checkDate.getMonth() + 1)).slice(-2);
			var d = ('0' + checkDate.getDate()).slice(-2);
			var currentYMD = y + '-' + m + '-' + d;

			var isOff = mbIsDateDisabled(checkDate, disableArr);

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

	function initManualBookingFlatpickr() {
		var dateInput = document.getElementById('mb_booking_date');
		if (!dateInput || typeof flatpickr === 'undefined') return;

		if (dateInput._flatpickr) {
			return;
		}

		var offDaysRaw = dateInput.getAttribute('data-off-days');
		var closingTime = dateInput.getAttribute('data-closing-time');
		var siteDate = (typeof slotnova_admin_data !== 'undefined' && slotnova_admin_data.site_current_date) ? slotnova_admin_data.site_current_date : '';
		var siteTime = (typeof slotnova_admin_data !== 'undefined' && slotnova_admin_data.site_current_time) ? slotnova_admin_data.site_current_time : '';

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
					disableArr.push(day);
				}
			});
		}

		var closing24h = mbNormalizeTo24h(closingTime);
		var siteTime24h = mbNormalizeTo24h(siteTime);
		if (closing24h && siteTime24h && siteTime24h >= closing24h && siteDate) {
			if (disableArr.indexOf(siteDate) === -1) {
				disableArr.push(siteDate);
			}
		}

		var initialDateYMD = mbGetFirstAvailableDate(new Date(), closingTime, disableArr);

		flatpickr(dateInput, {
			dateFormat: 'Y-m-d',
			minDate: 'today',
			defaultDate: initialDateYMD,
			inline: true,
			disable: disableArr,
			onChange: function(selectedDates, dateStr) {
				if (dateStr) {
					$('.mb-time-slots-wrapper').removeClass('slotnova-is-hidden');
					$('#mb-summary-booking-date').text(dateStr);
					$('#mb_booking_time').val('');
					$('#mb_time_pills .slotnova-time-pill').removeClass('active');
					$('#mb-summary-booking-time').text('-');

					fetchMBBookedSlots(dateStr);
					checkMBSummaryVisibility();
				}
			}
		});

		if (initialDateYMD) {
			$('.mb-time-slots-wrapper').removeClass('slotnova-is-hidden');
			$('#mb-summary-booking-date').text(initialDateYMD);
			dateInput.value = initialDateYMD;
			fetchMBBookedSlots(initialDateYMD);
		}
	}

	function fetchMBBookedSlots(dateStr) {
		if (!dateStr || typeof slotnova_admin_data === 'undefined' || !slotnova_admin_data.ajax_url) {
			return;
		}

		var serviceId = $('#mb_service_id').val() || '';
		var employeeId = $('#mb_employee_id').val() || '';
		var siteDate = slotnova_admin_data.site_current_date || '';
		var siteTime = slotnova_admin_data.site_current_time || '';
		var timePills = document.querySelectorAll('#mb_time_pills .slotnova-time-pill');

		var postData = {
			action: 'slotnova_get_booked_slots',
			date: dateStr,
			service_id: serviceId,
			employee_id: employeeId,
			nonce: slotnova_admin_data.nonce
		};

		$.post(slotnova_admin_data.ajax_url, postData, function(res) {
			var booked = (res.success && res.data && res.data.booked_slots) ? res.data.booked_slots : [];
			var bookedLabel = slotnova_admin_data.booked_text || 'Booked';
			var passedLabel = slotnova_admin_data.passed_text || 'Time Passed';
			var isToday = (dateStr === siteDate);

			timePills.forEach(function(pill) {
				var slotVal = pill.getAttribute('data-value');
				var isBooked = (booked.indexOf(slotVal) !== -1);
				var isPassed = false;

				if (isToday && siteTime) {
					var slot24h = mbTimeTo24h(slotVal);
					if (slot24h && slot24h <= siteTime) {
						isPassed = true;
					}
				}

				if (isBooked || isPassed) {
					pill.classList.add('disabled');
					pill.disabled = true;
					pill.setAttribute('title', isPassed ? passedLabel : bookedLabel);
					if (pill.classList.contains('active')) {
						pill.classList.remove('active');
						$('#mb_booking_time').val('');
						$('#mb-summary-booking-time').text('-');
					}
				} else {
					pill.classList.remove('disabled');
					pill.disabled = false;
					pill.removeAttribute('title');
				}
			});
		});
	}

	function checkMBSummaryVisibility() {
		$('#mb-slotnova-summary').removeClass('slotnova-is-hidden');
	}

	// Time Slot Pill Click Handler inside Manual Booking modal
	$(document).on('click', '#mb_time_pills .slotnova-time-pill', function(e) {
		e.preventDefault();
		if ($(this).is(':disabled') || $(this).hasClass('disabled')) {
			return false;
		}

		$('#mb_time_pills .slotnova-time-pill').removeClass('active');
		$(this).addClass('active');

		var value = $(this).attr('data-value');
		$('#mb_booking_time').val(value);
		$('#mb-summary-booking-time').text(value);

		checkMBSummaryVisibility();
	});

	// Custom Dropdowns in Manual Booking Modal
	function filterMBOptions($dropdown, query) {
		query = query.trim().toLowerCase();
		var $options = $dropdown.find('.slotnova-select-option');
		var $noResults = $dropdown.find('.slotnova-select-no-results');
		var visibleCount = 0;

		$options.each(function() {
			var name = ($(this).attr('data-name') || $(this).text() || '').toLowerCase();
			if (!query || name.indexOf(query) !== -1) {
				$(this).show();
				visibleCount++;
			} else {
				$(this).hide();
			}
		});

		if (visibleCount === 0) {
			$noResults.removeClass('slotnova-is-hidden');
		} else {
			$noResults.addClass('slotnova-is-hidden');
		}
	}

	function openMBDropdown($dropdown) {
		var $options = $dropdown.find('.slotnova-select-options');
		var $trigger = $dropdown.find('.slotnova-select-trigger');
		var $input = $dropdown.find('.slotnova-select-search-input');

		$('#slotnova-manual-booking-modal .slotnova-select-options').not($options).removeClass('open');
		$('#slotnova-manual-booking-modal .slotnova-select-trigger').not($trigger).removeClass('active');

		$dropdown.find('.slotnova-select-option').show();
		$dropdown.find('.slotnova-select-no-results').addClass('slotnova-is-hidden');

		$options.addClass('open');
		$trigger.addClass('active');

		if ($input.length) {
			$input.trigger('select');
		}
	}

	$(document).on('click', '#slotnova-manual-booking-modal .slotnova-custom-select .slotnova-select-trigger', function(e) {
		e.stopPropagation();
		var $dropdown = $(this).closest('.slotnova-custom-select');
		var $options = $dropdown.find('.slotnova-select-options');

		if (!$options.hasClass('open')) {
			openMBDropdown($dropdown);
		}
	});

	$(document).on('focus', '#slotnova-manual-booking-modal .slotnova-select-search-input', function(e) {
		e.stopPropagation();
		var $dropdown = $(this).closest('.slotnova-custom-select');
		var $options = $dropdown.find('.slotnova-select-options');

		if (!$options.hasClass('open')) {
			openMBDropdown($dropdown);
		} else {
			$(this).trigger('select');
		}
	});

	$(document).on('input', '#slotnova-manual-booking-modal .slotnova-select-search-input', function(e) {
		e.stopPropagation();
		var $dropdown = $(this).closest('.slotnova-custom-select');
		var $options = $dropdown.find('.slotnova-select-options');

		if (!$options.hasClass('open')) {
			openMBDropdown($dropdown);
		}
		filterMBOptions($dropdown, $(this).val());
	});

	$(document).on('click', '#slotnova-manual-booking-modal .slotnova-select-option', function(e) {
		e.stopPropagation();
		var $opt = $(this);
		var $dropdown = $opt.closest('.slotnova-custom-select');
		var $trigger = $dropdown.find('.slotnova-select-trigger');
		var $searchInput = $dropdown.find('.slotnova-select-search-input');
		var $options = $dropdown.find('.slotnova-select-options');

		var value = $opt.attr('data-value');
		var name = $opt.attr('data-name');
		var priceVal = parseFloat($opt.attr('data-price'));

		var isService = $dropdown.attr('id') === 'mb_service_dropdown';
		var isEmployee = $dropdown.attr('id') === 'mb_employee_dropdown';

		var displayName = name;

		if (isService) {
			$('#mb_service_id').val(value);
			$('#mb_service_name').val(name);
			$('#mb-summary-service-name').text(name || '-');
			var symbol = (typeof slotnova_admin_data !== 'undefined' && slotnova_admin_data.currency_symbol) ? slotnova_admin_data.currency_symbol : '$';
			if (!isNaN(priceVal)) {
				if (priceVal > 0) {
					$('#mb-summary-service-price').text(symbol + priceVal.toFixed(2));
					displayName = name + ' (' + symbol + priceVal.toFixed(2) + ')';
				} else if (priceVal === 0) {
					var freeTxt = (typeof slotnova_admin_data !== 'undefined' && slotnova_admin_data.free_text) ? slotnova_admin_data.free_text : 'Free';
					$('#mb-summary-service-price').text(freeTxt);
					displayName = name + ' (' + freeTxt + ')';
				}
			}
		}

		if (isEmployee) {
			$('#mb_employee_id').val(value);
			$('#mb_employee_name').val(name);
			$('#mb-summary-employee-name').text(name || '-');
			if (value) {
				$('#mb-summary-employee-row').removeClass('slotnova-is-hidden');
			} else {
				$('#mb-summary-employee-row').addClass('slotnova-is-hidden');
			}
		}

		if ($searchInput.length) {
			$searchInput.val(displayName).attr('data-selected-name', displayName);
		}
		$options.removeClass('open');
		$trigger.removeClass('active');

		var dateVal = $('#mb_booking_date').val();
		if (dateVal) {
			fetchMBBookedSlots(dateVal);
		}
		checkMBSummaryVisibility();
	});

	$(document).on('click', function() {
		$('#slotnova-manual-booking-modal .slotnova-select-options').removeClass('open');
		$('#slotnova-manual-booking-modal .slotnova-select-trigger').removeClass('active');
		$('#slotnova-manual-booking-modal .slotnova-select-search-input').each(function() {
			var selName = $(this).attr('data-selected-name') || '';
			$(this).val(selName);
		});
	});

	$(document).on('click', '#slotnova-open-manual-booking-modal', function(e) {
		e.preventDefault();
		$modal.removeClass('slotnova-is-hidden').show();
		setTimeout(function() {
			initManualBookingFlatpickr();
		}, 100);
	});

	$(document).on('click', '.slotnova-modal-close, .slotnova-btn-cancel', function(e) {
		e.preventDefault();
		$('.slotnova-modal-overlay').addClass('slotnova-is-hidden').hide();
	});

	$(document).on('click', '.slotnova-modal-overlay', function(e) {
		if ($(e.target).hasClass('slotnova-modal-overlay')) {
			$(this).addClass('slotnova-is-hidden').hide();
		}
	});

	$(document).on('submit', '#slotnova-manual-booking-form', function(e) {
		e.preventDefault();
		if (typeof slotnova_admin_data === 'undefined') return;

		var $form = $(this);
		var serviceVal = $('#mb_service_id').val() || $('#mb_service_name').val();
		var dateVal = $('#mb_booking_date').val();
		var timeVal = $('#mb_booking_time').val();

		if (!serviceVal) {
			alert('Please select a service before creating a booking.');
			return false;
		}

		if (!dateVal) {
			alert('Please select a booking date.');
			return false;
		}

		if (!timeVal) {
			alert('Please select a time slot.');
			return false;
		}

		var $submitBtn = $form.find('button[type="submit"]');
		var originalText = $submitBtn.text();

		$submitBtn.prop('disabled', true).text(slotnova_admin_data.i18n.saving);

		var formData = $form.serializeArray();
		var postData = {
			action: 'slotnova_create_manual_booking',
			security: slotnova_admin_data.nonce
		};

		$.each(formData, function(i, field) {
			postData[field.name] = field.value;
		});

		$.post(slotnova_admin_data.ajax_url, postData, function(response) {
			$submitBtn.prop('disabled', false).text(originalText);

			if (response.success) {
				alert(response.data.message);
				$modal.addClass('slotnova-is-hidden').hide();
				$form[0].reset();
				window.location.reload();
			} else {
				alert(response.data.message || 'Error creating booking.');
			}
		}).fail(function() {
			$submitBtn.prop('disabled', false).text(originalText);
			alert('Server error. Please try again.');
		});
	});

	/* -------------------------------------------------------------------------
	 * 4. Bookings Calendar (FullCalendar & Tab Switching)
	 * ------------------------------------------------------------------------- */
	var tabList = document.getElementById('slotnova-tab-list-view');
	var tabCal = document.getElementById('slotnova-tab-calendar-view');
	var containerList = document.getElementById('slotnova-list-view-container');
	var containerCal = document.getElementById('slotnova-calendar-view-container');
	var calendarEl = document.getElementById('slotnova-fullcalendar');
	var calendarInstance = null;

	function renderSlotNovaCalendar() {
		if (!calendarEl || typeof FullCalendar === 'undefined') return;

		if (!calendarInstance) {
			var eventsData = (typeof slotnova_admin_data !== 'undefined' && slotnova_admin_data.calendar) ? slotnova_admin_data.calendar.events : [];
			calendarInstance = new FullCalendar.Calendar(calendarEl, {
				initialView: 'dayGridMonth',
				headerToolbar: {
					left: 'prev,next today',
					center: 'title',
					right: 'dayGridMonth,timeGridWeek,timeGridDay'
				},
				events: eventsData
			});
			calendarInstance.render();
		} else {
			calendarInstance.updateSize();
		}
	}

	if (tabList && tabCal) {
		$(tabList).on('click', function(e) {
			e.preventDefault();
			$(tabList).addClass('active');
			$(tabCal).removeClass('active');
			$(containerList).removeClass('slotnova-is-hidden').show();
			$(containerCal).addClass('slotnova-is-hidden').hide();
		});

		$(tabCal).on('click', function(e) {
			e.preventDefault();
			$(tabCal).addClass('active');
			$(tabList).removeClass('active');
			$(containerList).addClass('slotnova-is-hidden').hide();
			$(containerCal).removeClass('slotnova-is-hidden').show();

			setTimeout(function() {
				renderSlotNovaCalendar();
			}, 50);
		});
	}

	/* -------------------------------------------------------------------------
	 * 5. Flatpickr (Global Settings & Product Slot Manager)
	 * ------------------------------------------------------------------------- */
	if (typeof flatpickr !== 'undefined') {
		if ($('#slotnova_specific_off_days').length) {
			flatpickr("#slotnova_specific_off_days", {
				mode: "multiple",
				dateFormat: "Y-m-d"
			});
		}
		if ($('#_slotnova_specific_off_days').length) {
			flatpickr("#_slotnova_specific_off_days", {
				mode: "multiple",
				dateFormat: "Y-m-d"
			});
		}
	}

	/* -------------------------------------------------------------------------
	 * 6. Product Tab Repeaters (Services & Employees)
	 * ------------------------------------------------------------------------- */
	var defaultServicePrices = (typeof slotnova_admin_data !== 'undefined' && slotnova_admin_data.default_service_prices) ? slotnova_admin_data.default_service_prices : {};

	// Auto-fill price when a service is selected
	$('body').on('change', '.slotnova-service-select', function() {
		var termId = $(this).val();
		var priceInput = $(this).closest('tr').find('.slotnova-service-price-input');
		if (termId && defaultServicePrices[termId] !== undefined) {
			priceInput.val(defaultServicePrices[termId]);
		}
	});

	// Make repeater tables sortable
	if ($('#slotnova-services-table tbody').length) {
		$('#slotnova-services-table tbody').sortable({ handle: '.slotnova-drag-handle' });
	}
	if ($('#slotnova-employees-table tbody').length) {
		$('#slotnova-employees-table tbody').sortable({ handle: '.slotnova-drag-handle' });
	}

	// Remove row
	$('body').on('click', '.slotnova-remove-row', function(e) {
		e.preventDefault();
		$(this).closest('tr').remove();
	});

	// Add Service Row
	$('#slotnova-add-service').on('click', function(e) {
		e.preventDefault();
		if (typeof slotnova_admin_data === 'undefined') return;

		var optionsHtml = '<option value="">' + slotnova_admin_data.i18n.select_service + '</option>';
		$.each(slotnova_admin_data.all_services, function(id, name) {
			optionsHtml += '<option value="' + id + '">' + name + '</option>';
		});

		var row = '<tr>' +
			'<td class="slotnova-drag-handle">☰</td>' +
			'<td><select name="slotnova_repeater_service_id[]" class="slotnova-table-select slotnova-service-select">' + optionsHtml + '</select></td>' +
			'<td><input type="number" name="slotnova_repeater_service_price[]" value="" step="0.01" min="0" class="slotnova-table-input-price slotnova-service-price-input"></td>' +
			'<td class="slotnova-align-center"><a href="#" class="slotnova-remove-row" title="' + slotnova_admin_data.i18n.remove + '"><span class="dashicons dashicons-trash"></span></a></td>' +
			'</tr>';

		$('#slotnova-services-table tbody').append(row);
	});

	// Add Employee Row
	$('#slotnova-add-employee').on('click', function(e) {
		e.preventDefault();
		if (typeof slotnova_admin_data === 'undefined') return;

		var optionsHtml = '<option value="">' + slotnova_admin_data.i18n.select_employee + '</option>';
		$.each(slotnova_admin_data.all_employees, function(id, name) {
			optionsHtml += '<option value="' + id + '">' + name + '</option>';
		});

		var row = '<tr>' +
			'<td class="slotnova-drag-handle">☰</td>' +
			'<td><select name="slotnova_repeater_employee_id[]" class="slotnova-table-select">' + optionsHtml + '</select></td>' +
			'<td class="slotnova-align-center"><a href="#" class="slotnova-remove-row" title="' + slotnova_admin_data.i18n.remove + '"><span class="dashicons dashicons-trash"></span></a></td>' +
			'</tr>';

		$('#slotnova-employees-table tbody').append(row);
	});

	/* -------------------------------------------------------------------------
	 * 7. Media Uploader for Taxonomy Term Images
	 * ------------------------------------------------------------------------- */
	$('body').on('click', '.slotnova-upload-image-button', function(e) {
		e.preventDefault();
		var button = $(this);
		var inputField = button.siblings('.slotnova-image-id');
		var previewImg = button.siblings('.slotnova-image-preview');

		var customUploader = wp.media({
			title: (typeof slotnova_admin_data !== 'undefined' && slotnova_admin_data.i18n) ? slotnova_admin_data.i18n.choose_image : 'Choose Image',
			button: { text: (typeof slotnova_admin_data !== 'undefined' && slotnova_admin_data.i18n) ? slotnova_admin_data.i18n.use_image : 'Use Image' },
			multiple: false
		}).on('select', function() {
			var attachment = customUploader.state().get('selection').first().toJSON();
			inputField.val(attachment.id);
			previewImg.attr('src', attachment.url).removeClass('slotnova-is-hidden').show();
			button.siblings('.slotnova-remove-image-button').removeClass('slotnova-is-hidden').show();
		}).open();
	});

	/* -------------------------------------------------------------------------
	 * 8. Booking Details Modal Popup
	 * ------------------------------------------------------------------------- */
	var $detailsModal = $('#slotnova-booking-details-modal');

	$(document).on('click', '.slotnova-open-details-modal', function(e) {
		e.preventDefault();
		var booking = $(this).data('booking');
		if (!booking) return;

		$('#bd-modal-order-id').text('#' + booking.order_id);
		$('#bd-modal-customer-name').text(booking.customer || 'Guest');
		
		var initial = (booking.customer && booking.customer.length > 0) ? booking.customer.charAt(0).toUpperCase() : 'G';
		$('#bd-modal-avatar').text(initial);

		var statusClass = 'status-' + (booking.status_raw ? booking.status_raw.toLowerCase() : 'processing');
		$('#bd-modal-status-badge').attr('class', 'slotnova-badge ' + statusClass).text(booking.status);

		var formattedDate = booking.date || '';
		if (booking.time) {
			formattedDate += ' at ' + booking.time;
		}
		$('#bd-modal-datetime').text(formattedDate || '-');
		$('#bd-modal-service').text(booking.service || 'General Service');
		$('#bd-modal-employee').text(booking.employee || 'Any Staff');
		
		if (booking.total_formatted) {
			$('#bd-modal-total').html(booking.total_formatted);
		} else {
			$('#bd-modal-total').text('$' + (booking.total || '0.00'));
		}

		if (booking.email) {
			$('#bd-modal-email').attr('href', 'mailto:' + booking.email).text(booking.email);
		} else {
			$('#bd-modal-email').removeAttr('href').text('N/A');
		}

		$('#bd-modal-phone').text(booking.phone || 'N/A');
		$('#bd-modal-address').text(booking.address || 'N/A');
		$('#bd-modal-order-link').attr('href', booking.order_url || '#');

		$detailsModal.removeClass('slotnova-is-hidden').show();
	});

	$(document).on('click', '#slotnova-booking-details-modal', function(e) {
		if ($(e.target).is('#slotnova-booking-details-modal')) {
			$detailsModal.addClass('slotnova-is-hidden').hide();
		}
	});

	$('body').on('click', '.slotnova-remove-image-button', function(e) {
		e.preventDefault();
		var button = $(this);
		button.siblings('.slotnova-image-id').val('');
		button.siblings('.slotnova-image-preview').attr('src', '').hide();
		button.hide();
	});

	/* -------------------------------------------------------------------------
	 * 9. Auto Select 1st SlotNova Tab on Product Type Selection
	 * ------------------------------------------------------------------------- */
	if ($('#product-type').length) {
		$(document).on('change', '#product-type', function() {
			if ('slotnova' === $(this).val()) {
				setTimeout(function() {
					$('.slotnova_booking_options a, .slotnova_booking_tab a').first().trigger('click');
				}, 50);
			}
		});
	}

});
