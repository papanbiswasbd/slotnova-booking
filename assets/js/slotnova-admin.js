/**
 * SlotNova Booking Admin JavaScript
 *
 * @package SlotNova\Booking
 * @version 1.1.0
 */

jQuery(document).ready(function($) {
	'use strict';

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
	 * 3. Smart Actions: Add Manual Booking Modal
	 * ------------------------------------------------------------------------- */
	var $modal = $('#slotnova-manual-booking-modal');

	$(document).on('click', '#slotnova-open-manual-booking-modal', function(e) {
		e.preventDefault();
		$modal.removeClass('slotnova-is-hidden').show();
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
			$(tabList).addClass('active nav-tab-active');
			$(tabCal).removeClass('active nav-tab-active');
			$(containerList).removeClass('slotnova-is-hidden').show();
			$(containerCal).addClass('slotnova-is-hidden').hide();
		});

		$(tabCal).on('click', function(e) {
			e.preventDefault();
			$(tabCal).addClass('active nav-tab-active');
			$(tabList).removeClass('active nav-tab-active');
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
