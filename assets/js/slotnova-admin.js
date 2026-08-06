/**
 * SlotNova Booking Admin JavaScript
 *
 * @package SlotNova\Booking
 * @version 1.2.0
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
					maintainAspectRatio: false,
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
	 * SlotNova Clean Analytics (Chart.js + Dynamic Controls)
	 * ------------------------------------------------------------------------- */
	var slotnovaMainChart = null;
	var slotnovaHourlyChart = null;
	var slotnovaDayChart = null;
	var currentStatsData = null;
	var activeMetric = 'bookings';
	var activePeriod = 'daily';

	function renderSlotNovaStatsWidget(stats) {
		if (!stats) return;
		currentStatsData = stats;

		// 1. Update Top Metric Pills
		$('#slotnova-stat-total-bookings').text(stats.total_range_submissions || 0);
		$('#slotnova-stat-total-revenue').text(stats.formatted_total_revenue || '$0.00');
		$('#slotnova-stat-peak-hour').text(stats.peak_hour_display || 'N/A');
		$('#slotnova-stat-top-service').text(stats.top_service_display || 'N/A');
		$('#slotnova-main-chart-subtitle').text('Range: ' + stats.from_date + ' to ' + stats.to_date);

		// 2. Render Main Timeline Chart
		updateMainTimelineChart();

		// 3. Render Peak Hours Distribution Chart
		renderHourlyDistributionChart(stats.range_hourly);

		// 4. Render Day of Week Distribution Chart
		renderDayDistributionChart(stats.day_distribution);

		// 5. Render Field Report Bars
		renderSlotNovaReportBars(stats.field_report, stats.report_field);

		// 6. Render Recent Bookings Table
		if (stats.recent_bookings) {
			renderWidgetBookingsTable(stats.recent_bookings);
		}

		// 7. Render Calendar if active
		if (stats.calendar_events && !$('#slotnova-widget-calendar-view-container').hasClass('slotnova-is-hidden')) {
			renderWidgetFullCalendar(stats.calendar_events);
		}
	}

	function updateMainTimelineChart() {
		if (!currentStatsData || typeof Chart === 'undefined') return;
		var canvas = document.getElementById('slotnovaMainAnalyticsChart');
		if (!canvas) return;

		var labels = [];
		var data = [];
		var labelText = 'Bookings';
		var color = '#0284c7';
		var bgColor = 'rgba(2, 132, 199, 0.12)';

		if (activeMetric === 'revenue') {
			labelText = 'Revenue ($)';
			color = '#16a34a';
			bgColor = 'rgba(22, 163, 74, 0.12)';
			if (activePeriod === 'monthly') {
				labels = currentStatsData.sub_monthly.labels;
				data = currentStatsData.range_revenue ? currentStatsData.range_revenue.data : [];
			} else if (activePeriod === 'weekly') {
				labels = currentStatsData.sub_weekly.labels;
				data = currentStatsData.range_revenue ? currentStatsData.range_revenue.data : [];
			} else {
				labels = currentStatsData.range_revenue ? currentStatsData.range_revenue.labels : [];
				data = currentStatsData.range_revenue ? currentStatsData.range_revenue.data : [];
			}
		} else if (activeMetric === 'appointments') {
			labelText = 'Appointments Scheduled';
			color = '#8b5cf6';
			bgColor = 'rgba(139, 92, 246, 0.12)';
			if (activePeriod === 'monthly') {
				labels = currentStatsData.booked_monthly.labels;
				data = currentStatsData.booked_monthly.data;
			} else if (activePeriod === 'weekly') {
				labels = currentStatsData.booked_weekly.labels;
				data = currentStatsData.booked_weekly.data;
			} else {
				labels = currentStatsData.booked_daily.labels;
				data = currentStatsData.booked_daily.data;
			}
		} else {
			// Bookings (Incoming requests)
			labelText = 'Bookings Made';
			color = '#0284c7';
			bgColor = 'rgba(2, 132, 199, 0.12)';
			if (activePeriod === 'monthly') {
				labels = currentStatsData.sub_monthly.labels;
				data = currentStatsData.sub_monthly.data;
			} else if (activePeriod === 'weekly') {
				labels = currentStatsData.sub_weekly.labels;
				data = currentStatsData.sub_weekly.data;
			} else {
				labels = currentStatsData.range_daily ? currentStatsData.range_daily.labels : currentStatsData.sub_daily.labels;
				data = currentStatsData.range_daily ? currentStatsData.range_daily.data : currentStatsData.sub_daily.data;
			}
		}

		if (slotnovaMainChart) {
			slotnovaMainChart.destroy();
		}

		var ctx = canvas.getContext('2d');
		slotnovaMainChart = new Chart(ctx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [{
					label: labelText,
					data: data,
					borderColor: color,
					backgroundColor: bgColor,
					borderWidth: 3,
					pointBackgroundColor: color,
					pointBorderColor: '#ffffff',
					pointBorderWidth: 2,
					pointRadius: 4,
					pointHoverRadius: 6,
					fill: true,
					tension: 0.35
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: {
						backgroundColor: '#0f172a',
						padding: 10,
						cornerRadius: 8
					}
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: { font: { size: 11 }, maxRotation: 45 }
					},
					y: {
						beginAtZero: true,
						grid: { color: '#f1f5f9' },
						ticks: { font: { size: 11 }, precision: 0 }
					}
				}
			}
		});
	}

	function renderHourlyDistributionChart(hourlyData) {
		if (!hourlyData || typeof Chart === 'undefined') return;
		var canvas = document.getElementById('slotnovaHourlyDistributionChart');
		if (!canvas) return;

		if (slotnovaHourlyChart) {
			slotnovaHourlyChart.destroy();
		}

		var ctx = canvas.getContext('2d');
		slotnovaHourlyChart = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: hourlyData.labels,
				datasets: [{
					label: 'Bookings Count',
					data: hourlyData.data,
					backgroundColor: '#38bdf8',
					borderRadius: 4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: {
					x: { grid: { display: false }, ticks: { font: { size: 10 } } },
					y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { precision: 0, font: { size: 10 } } }
				}
			}
		});
	}

	function renderDayDistributionChart(dayData) {
		if (!dayData || typeof Chart === 'undefined') return;
		var canvas = document.getElementById('slotnovaDayDistributionChart');
		if (!canvas) return;

		if (slotnovaDayChart) {
			slotnovaDayChart.destroy();
		}

		var ctx = canvas.getContext('2d');
		slotnovaDayChart = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: dayData.labels,
				datasets: [{
					label: 'Bookings Count',
					data: dayData.data,
					backgroundColor: '#818cf8',
					borderRadius: 4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: {
					x: { grid: { display: false }, ticks: { font: { size: 10 } } },
					y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { precision: 0, font: { size: 10 } } }
				}
			}
		});
	}

	function renderSlotNovaReportBars(reportItems, reportField) {
		var formattedTitle = (reportField || 'service').charAt(0).toUpperCase() + (reportField || 'service').slice(1);
		var $container = $('#slotnova-widget-report-bars');
		$container.empty();

		if (!reportItems || reportItems.length === 0) {
			$container.html('<p class="slotnova-no-report-data">No booking data recorded for the selected range.</p>');
			return;
		}

		$.each(reportItems, function(idx, item) {
			var widthPercent = Math.max(5, item.percentage);
			var html = '<div class="slotnova-report-bar-item">' +
				'<div class="slotnova-report-bar-label">' +
					'<strong>' + item.count + ' bookings: ' + item.label + '</strong>' +
				'</div>' +
				'<div class="slotnova-report-bar-track">' +
					'<div class="slotnova-report-bar-fill" style="width: ' + widthPercent + '%; background-color: ' + item.color + ';">' +
						'<span class="slotnova-report-bar-text">' + item.percentage.toFixed(1) + '%</span>' +
					'</div>' +
				'</div>' +
			'</div>';
			$container.append(html);
		});
	}

	function renderWidgetBookingsTable(bookings) {
		var $tbody = $('#slotnova-widget-bookings-table-body');
		if (!$tbody.length) return;
		$tbody.empty();

		$('.slotnova-records-count-text').html('<span class="dashicons dashicons-list-view"></span> Showing latest ' + (bookings ? bookings.length : 0) + ' bookings');

		if (!bookings || bookings.length === 0) {
			$tbody.html('<tr><td colspan="7" class="slotnova-empty-table-cell"><div class="slotnova-empty-state"><div class="slotnova-empty-icon"><span class="dashicons dashicons-calendar-alt"></span></div><p>No bookings matching your criteria.</p></div></td></tr>');
			return;
		}

		$.each(bookings, function(idx, item) {
			var custInitial = (item.customer || 'G').charAt(0).toUpperCase();
			var statusClass = 'status-' + (item.status_raw || 'processing').toLowerCase();
			var jsonAttr = $('<div>').text(JSON.stringify(item)).html();

			var row = '<tr class="slotnova-table-row">' +
				'<td><a href="' + item.order_url + '" class="slotnova-order-pill">#' + item.order_id + '</a></td>' +
				'<td><div class="slotnova-customer-cell"><div class="slotnova-avatar-circle-sm">' + custInitial + '</div><div class="slotnova-customer-meta"><strong>' + item.customer + '</strong><div class="slotnova-contact-sub"><span>' + item.email + '</span></div></div></div></td>' +
				'<td><span class="slotnova-badge-service">' + item.service + '</span></td>' +
				'<td><span class="slotnova-staff-tag"><span class="dashicons dashicons-admin-users"></span> ' + item.employee + '</span></td>' +
				'<td><div class="slotnova-datetime-cell"><span class="slotnova-date-text">' + item.date + '</span><span class="slotnova-time-text"><span class="dashicons dashicons-clock"></span> ' + item.time + '</span></div></td>' +
				'<td><span class="slotnova-badge ' + statusClass + '">' + item.status + '</span></td>' +
				'<td style="text-align: right;"><div class="slotnova-action-cell-group">' +
					'<button type="button" class="slotnova-btn-action-view slotnova-open-details-modal" data-booking="' + jsonAttr + '" title="View Booking Details"><span class="dashicons dashicons-visibility"></span> View</button>' +
				'</div></td>' +
			'</tr>';

			$tbody.append(row);
		});
	}

	var slotnovaWidgetCalendar = null;
	function renderWidgetFullCalendar(events) {
		var containerCal = document.getElementById('slotnova-widget-fullcalendar');
		if (!containerCal || typeof FullCalendar === 'undefined') return;

		if (slotnovaWidgetCalendar) {
			slotnovaWidgetCalendar.removeAllEvents();
			if (events && events.length) {
				slotnovaWidgetCalendar.addEventSource(events);
			}
			slotnovaWidgetCalendar.render();
			return;
		}

		slotnovaWidgetCalendar = new FullCalendar.Calendar(containerCal, {
			initialView: 'dayGridMonth',
			headerToolbar: {
				left: 'prev,next today',
				center: 'title',
				right: 'dayGridMonth,timeGridWeek'
			},
			height: 460,
			events: events || [],
			eventClick: function(info) {
				if (info.event.url) {
					info.jsEvent.preventDefault();
					window.location.href = info.event.url;
				}
			}
		});

		slotnovaWidgetCalendar.render();
	}

	// Toggle List View vs Calendar View in Widget
	$(document).on('click', '#slotnova-widget-view-toggle-group .slotnova-toggle-btn', function(e) {
		e.preventDefault();
		$('#slotnova-widget-view-toggle-group .slotnova-toggle-btn').removeClass('active');
		$(this).addClass('active');

		var view = $(this).data('view');
		if (view === 'calendar') {
			$('#slotnova-widget-list-view-container').addClass('slotnova-is-hidden');
			$('#slotnova-widget-calendar-view-container').removeClass('slotnova-is-hidden');
			if (currentStatsData && currentStatsData.calendar_events) {
				renderWidgetFullCalendar(currentStatsData.calendar_events);
			}
		} else {
			$('#slotnova-widget-calendar-view-container').addClass('slotnova-is-hidden');
			$('#slotnova-widget-list-view-container').removeClass('slotnova-is-hidden');
		}
	});

	// Toggle metric buttons (Bookings, Revenue, Appointments)
	$(document).on('click', '#slotnova-metric-toggle-group .slotnova-toggle-btn', function(e) {
		e.preventDefault();
		$('#slotnova-metric-toggle-group .slotnova-toggle-btn').removeClass('active');
		$(this).addClass('active');
		activeMetric = $(this).data('metric');
		updateMainTimelineChart();
	});

	// Toggle period buttons (Daily, Weekly, Monthly)
	$(document).on('click', '#slotnova-period-toggle-group .slotnova-toggle-btn', function(e) {
		e.preventDefault();
		$('#slotnova-period-toggle-group .slotnova-toggle-btn').removeClass('active');
		$(this).addClass('active');
		activePeriod = $(this).data('period');
		updateMainTimelineChart();
	});

	if (typeof slotnova_admin_data !== 'undefined' && slotnova_admin_data.widget_stats) {
		renderSlotNovaStatsWidget(slotnova_admin_data.widget_stats);
	}

	// Click Filter button in Widget
	$(document).on('click', '#slotnova-widget-filter-btn', function(e) {
		e.preventDefault();
		fetchWidgetStats();
	});

	// Change report field dropdown
	$(document).on('change', '#slotnova-widget-report-field', function() {
		fetchWidgetStats();
	});

	/* -------------------------------------------------------------------------
	 * Addons Marketplace Manager (1-Click Install via Cloudflare Worker & Freemius)
	 * ------------------------------------------------------------------------- */
	// Filter Addons Grid by Tab
	$(document).on('click', '#slotnova-addon-filter-tabs .slotnova-preset-btn', function(e) {
		e.preventDefault();
		$('#slotnova-addon-filter-tabs .slotnova-preset-btn').removeClass('active');
		$(this).addClass('active');

		var filter = $(this).data('filter');
		$('.slotnova-addon-card').each(function() {
			var cardType = $(this).data('type');
			var isInstalled = $(this).data('installed') == '1';

			if (filter === 'free' && cardType !== 'free') {
				$(this).hide();
			} else if (filter === 'pro' && cardType !== 'pro') {
				$(this).hide();
			} else if (filter === 'installed' && !isInstalled) {
				$(this).hide();
			} else {
				$(this).show();
			}
		});
	});

	// Install Addon (Free or Pro)
	$(document).on('click', '.slotnova-install-addon-btn', function(e) {
		e.preventDefault();
		if (typeof slotnova_admin_data === 'undefined') return;

		var $btn = $(this);
		var $card = $btn.closest('.slotnova-addon-card');
		var $notice = $card.find('.slotnova-addon-card-notice');
		var addonSlug = $btn.data('slug');

		$notice.hide().removeClass('slotnova-notice-error slotnova-notice-success');
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <span>Installing...</span>');

		$.ajax({
			url: slotnova_admin_data.ajax_url,
			type: 'POST',
			data: {
				action: 'slotnova_install_addon',
				security: slotnova_admin_data.nonce,
				addon_slug: addonSlug
			},
			success: function(res) {
				if (res.success) {
					window.location.reload();
				} else {
					var msg = res.data && res.data.message ? res.data.message : 'Installation failed.';
					$notice.addClass('slotnova-notice-error')
						.html('<span class="dashicons dashicons-warning" style="margin-right:4px;"></span> ' + msg)
						.slideDown(200);
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> <span>Download & Activate</span>');
				}
			},
			error: function() {
				$notice.addClass('slotnova-notice-error')
					.html('<span class="dashicons dashicons-warning" style="margin-right:4px;"></span> Server connection error. Please try again.')
					.slideDown(200);
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> <span>Download & Activate</span>');
			}
		});
	});

	// Toggle Addon Active / Deactive State
	$(document).on('click', '.slotnova-toggle-addon-btn', function(e) {
		e.preventDefault();
		if (typeof slotnova_admin_data === 'undefined') return;

		var $btn = $(this);
		var $card = $btn.closest('.slotnova-addon-card');
		var $notice = $card.find('.slotnova-addon-card-notice');
		var file = $btn.data('file');
		var actionType = $btn.data('action');

		$notice.hide().removeClass('slotnova-notice-error slotnova-notice-success');
		$btn.prop('disabled', true).text('Processing...');

		$.ajax({
			url: slotnova_admin_data.ajax_url,
			type: 'POST',
			data: {
				action: 'slotnova_toggle_addon',
				security: slotnova_admin_data.nonce,
				file: file,
				action_type: actionType
			},
			success: function(res) {
				if (res.success) {
					window.location.reload();
				} else {
					var msg = res.data && res.data.message ? res.data.message : 'Action failed.';
					$notice.addClass('slotnova-notice-error')
						.html('<span class="dashicons dashicons-warning" style="margin-right:4px;"></span> ' + msg)
						.slideDown(200);
					$btn.prop('disabled', false).text(actionType === 'activate' ? 'Activate' : 'Deactivate');
				}
			},
			error: function() {
				$notice.addClass('slotnova-notice-error')
					.html('<span class="dashicons dashicons-warning" style="margin-right:4px;"></span> Server connection error.')
					.slideDown(200);
				$btn.prop('disabled', false).text(actionType === 'activate' ? 'Activate' : 'Deactivate');
			}
		});
	});

	// Buy Addon - Open Purchase URL
	$(document).on('click', '.slotnova-buy-addon-btn', function(e) {
		e.preventDefault();
		var buyUrl = $(this).data('buy-url');
		if (buyUrl && buyUrl.length > 5) {
			window.open(buyUrl, '_blank');
		} else {
			window.open('https://slotnova.com/addons/', '_blank');
		}
	});

	// Disconnect License Key
	$(document).on('click', '.slotnova-disconnect-license-btn', function(e) {
		e.preventDefault();
		if (typeof slotnova_admin_data === 'undefined') return;

		if (!confirm('Are you sure you want to disconnect this license?')) return;

		var $btn = $(this);
		var slug = $btn.data('slug');
		var file = $btn.data('file');

		$btn.prop('disabled', true).text('Disconnecting...');

		$.ajax({
			url: slotnova_admin_data.ajax_url,
			type: 'POST',
			data: {
				action: 'slotnova_disconnect_license',
				security: slotnova_admin_data.nonce,
				addon_slug: slug,
				file: file
			},
			success: function(res) {
				if (res.success) {
					window.location.reload();
				} else {
					alert(res.data && res.data.message ? res.data.message : 'Disconnect failed.');
					$btn.prop('disabled', false).text('Disconnect');
				}
			},
			error: function() {
				alert('Server connection error.');
				$btn.prop('disabled', false).text('Disconnect');
			}
		});
	});

	function fetchWidgetStats() {
		if (typeof slotnova_admin_data === 'undefined') return;

		var search = $('#slotnova-widget-search').val();
		var fromDate = $('#slotnova-widget-from').val();
		var toDate = $('#slotnova-widget-to').val();
		var service = $('#slotnova-widget-service').val();
		var reportField = $('#slotnova-widget-report-field').val();

		var $btn = $('#slotnova-widget-filter-btn');
		$btn.prop('disabled', true).text('Filtering...');

		$.ajax({
			url: slotnova_admin_data.ajax_url,
			type: 'POST',
			data: {
				action: 'slotnova_get_widget_stats',
				security: slotnova_admin_data.nonce,
				search: search,
				from_date: fromDate,
				to_date: toDate,
				service: service,
				report_field: reportField
			},
			success: function(res) {
				$btn.prop('disabled', false).text('Filter');
				if (res.success && res.data) {
					renderSlotNovaStatsWidget(res.data);
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Filter');
			}
		});
	}

	// Export CSV from widget
	$(document).on('click', '#slotnova-widget-export-btn', function(e) {
		e.preventDefault();
		var search = $('#slotnova-widget-search').val();
		var service = $('#slotnova-widget-service').val();
		var downloadUrl = slotnova_admin_data.ajax_url + '?action=slotnova_export_bookings_csv&security=' + slotnova_admin_data.nonce + '&search=' + encodeURIComponent(search) + '&service=' + encodeURIComponent(service);
		window.location.href = downloadUrl;
	});

	// Click Preset Date Buttons (Today, Last 7 Days, Last 30 Days, This Month, This Year)
	$(document).on('click', '.slotnova-preset-btn', function(e) {
		e.preventDefault();
		$('.slotnova-preset-btn').removeClass('active');
		$(this).addClass('active');

		var preset = $(this).data('preset');
		var today = new Date();
		var formatDate = function(d) {
			var year = d.getFullYear();
			var month = ('0' + (d.getMonth() + 1)).slice(-2);
			var day = ('0' + d.getDate()).slice(-2);
			return year + '-' + month + '-' + day;
		};

		var toDateStr = formatDate(today);
		var fromDateStr = toDateStr;

		if (preset === 'today') {
			fromDateStr = toDateStr;
		} else if (preset === 'last_7_days') {
			var d = new Date();
			d.setDate(d.getDate() - 6);
			fromDateStr = formatDate(d);
		} else if (preset === 'last_30_days') {
			var d = new Date();
			d.setDate(d.getDate() - 29);
			fromDateStr = formatDate(d);
		} else if (preset === 'this_month') {
			var d = new Date(today.getFullYear(), today.getMonth(), 1);
			fromDateStr = formatDate(d);
		} else if (preset === 'this_year') {
			var d = new Date(today.getFullYear(), 0, 1);
			fromDateStr = formatDate(d);
		}

		$('#slotnova-widget-from').val(fromDateStr);
		$('#slotnova-widget-to').val(toDateStr);
		fetchWidgetStats();
	});

	// Reset Filter Button
	$(document).on('click', '#slotnova-widget-reset-btn', function(e) {
		e.preventDefault();
		$('#slotnova-widget-search').val('');
		$('#slotnova-widget-service').val('');
		$('.slotnova-preset-btn[data-preset="last_30_days"]').trigger('click');
	});

	// Fullscreen Mode Toggle for Stats Widget
	$(document).on('click', '#slotnova-widget-fullscreen-toggle', function(e) {
		e.preventDefault();
		var $container = $('.slotnova-stats-dashboard-container');
		$container.toggleClass('slotnova-fullscreen-active');

		if ($container.hasClass('slotnova-fullscreen-active')) {
			$(this).find('.dashicons').removeClass('dashicons-fullscreen-alt').addClass('dashicons-fullscreen-exit-alt');
			$(this).find('.slotnova-fs-text').text('Exit Fullscreen');
			$('body').addClass('slotnova-fs-open');
		} else {
			$(this).find('.dashicons').removeClass('dashicons-fullscreen-exit-alt').addClass('dashicons-fullscreen-alt');
			$(this).find('.slotnova-fs-text').text('Fullscreen View');
			$('body').removeClass('slotnova-fs-open');
		}

		setTimeout(function() {
			window.dispatchEvent(new Event('resize'));
		}, 150);
	});

	// Ensure WP Dashboard container takes 100% width for SlotNova widget
	if ($('#slotnova_wp_dashboard_widget').length) {
		$('#slotnova_wp_dashboard_widget').closest('.postbox-container').css({
			'width': '100%',
			'max-width': '100%',
			'float': 'none',
			'clear': 'both'
		});
		$('#dashboard-widgets-wrap #dashboard-widgets').css({
			'display': 'flex',
			'flex-direction': 'column'
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
		var isToday = (dateStr === siteDate);
		var bookedLabel = slotnova_admin_data.booked_text || 'Booked';
		var passedLabel = slotnova_admin_data.passed_text || 'Time Passed';

		// INSTANT UI RESET: Un-disable all time slots immediately upon changing date/service/employee
		timePills.forEach(function(pill) {
			var slotVal = pill.getAttribute('data-value');
			var isPassed = false;
			if (isToday && siteTime) {
				var slot24h = mbTimeTo24h(slotVal);
				if (slot24h && slot24h <= siteTime) {
					isPassed = true;
				}
			}
			if (isPassed) {
				pill.classList.add('disabled');
				pill.disabled = true;
				pill.setAttribute('title', passedLabel);
			} else {
				pill.classList.remove('disabled');
				pill.disabled = false;
				pill.removeAttribute('title');
			}
		});

		var postData = {
			action: 'slotnova_get_booked_slots',
			date: dateStr,
			service_id: serviceId,
			employee_id: employeeId,
			nonce: slotnova_admin_data.nonce
		};

		$.post(slotnova_admin_data.ajax_url, postData, function(res) {
			var booked = (res.success && res.data && res.data.booked_slots) ? res.data.booked_slots : [];

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
	function toggleSlotNovaProductGroups() {
		var basePriceCheck = $('#_slotnova_enable_base_price');
		var servicesCheck = $('#_slotnova_enable_services');
		var employeesCheck = $('#_slotnova_enable_employees');

		if (basePriceCheck.length) {
			if (basePriceCheck.is(':checked')) {
				$('.slotnova-base-price-input-box').show();
			} else {
				$('.slotnova-base-price-input-box').hide();
			}
		}

		if (servicesCheck.length) {
			if (servicesCheck.is(':checked')) {
				$('#slotnova-services-group, .slotnova-services-label-box').show();
			} else {
				$('#slotnova-services-group, .slotnova-services-label-box').hide();
			}
		}

		if (employeesCheck.length) {
			if (employeesCheck.is(':checked')) {
				$('#slotnova-employees-group, .slotnova-employees-label-box').show();
			} else {
				$('#slotnova-employees-group, .slotnova-employees-label-box').hide();
			}
		}
	}

	$('body').on('change', '#_slotnova_enable_base_price, #_slotnova_enable_services, #_slotnova_enable_employees', toggleSlotNovaProductGroups);

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

	function initSlotnovaSearchableSelects(context) {
		var $selects;
		if (context) {
			$selects = $(context).find('.slotnova-service-select, .slotnova-employee-select');
			if ($(context).is('.slotnova-service-select, .slotnova-employee-select')) {
				$selects = $selects.add($(context));
			}
		} else {
			$selects = $('.slotnova-service-select, .slotnova-employee-select');
		}

		$selects.each(function() {
			var $s = $(this);
			if ($s.data('select2') || $s.hasClass('select2-hidden-accessible')) {
				return;
			}
			var placeholderText = $s.find('option:first-child').text() || 'Select...';
			if ($.fn.selectWoo) {
				$s.selectWoo({
					width: '100%',
					allowClear: false,
					placeholder: placeholderText
				});
			} else if ($.fn.select2) {
				$s.select2({
					width: '100%',
					allowClear: false,
					placeholder: placeholderText
				});
			}
		});
	}

	initSlotnovaSearchableSelects();

	$(document).on('woocommerce_product_type_changed woocommerce_variations_loaded click', '.product_data_tabs a', function() {
		setTimeout(function() {
			initSlotnovaSearchableSelects();
		}, 50);
	});

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

		var $row = $('<tr>' +
			'<td class="slotnova-drag-handle">☰</td>' +
			'<td><select name="slotnova_repeater_service_id[]" class="slotnova-table-select slotnova-service-select">' + optionsHtml + '</select></td>' +
			'<td><input type="number" name="slotnova_repeater_service_price[]" value="" step="0.01" min="0" class="slotnova-table-input-price slotnova-service-price-input"></td>' +
			'<td class="slotnova-align-center"><a href="#" class="slotnova-remove-row" title="' + slotnova_admin_data.i18n.remove + '"><span class="dashicons dashicons-trash"></span></a></td>' +
			'</tr>');

		$('#slotnova-services-table tbody').append($row);
		initSlotnovaSearchableSelects($row);
	});

	// Add Employee Row
	$('#slotnova-add-employee').on('click', function(e) {
		e.preventDefault();
		if (typeof slotnova_admin_data === 'undefined') return;

		var optionsHtml = '<option value="">' + slotnova_admin_data.i18n.select_employee + '</option>';
		$.each(slotnova_admin_data.all_employees, function(id, name) {
			optionsHtml += '<option value="' + id + '">' + name + '</option>';
		});

		var $row = $('<tr>' +
			'<td class="slotnova-drag-handle">☰</td>' +
			'<td><select name="slotnova_repeater_employee_id[]" class="slotnova-table-select slotnova-employee-select">' + optionsHtml + '</select></td>' +
			'<td class="slotnova-align-center"><a href="#" class="slotnova-remove-row" title="' + slotnova_admin_data.i18n.remove + '"><span class="dashicons dashicons-trash"></span></a></td>' +
			'</tr>');

		$('#slotnova-employees-table tbody').append($row);
		initSlotnovaSearchableSelects($row);
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

	/* -------------------------------------------------------------------------
	 * 10. Quick Change Booking Status from All Bookings Management Table
	 * ------------------------------------------------------------------------- */
	$(document).on('change', '.slotnova-status-quick-select', function() {
		var $select = $(this);
		var orderId = $select.data('order-id');
		var newStatus = $select.val();

		if (!orderId || !newStatus || typeof slotnova_admin_data === 'undefined') {
			return;
		}

		$select.addClass('updating');

		$.ajax({
			url: slotnova_admin_data.ajax_url,
			type: 'POST',
			data: {
				action: 'slotnova_update_booking_status',
				nonce: slotnova_admin_data.nonce,
				order_id: orderId,
				status: newStatus
			},
			success: function(response) {
				$select.removeClass('updating');
				if (response.success && response.data) {
					var cleanSlug = response.data.status_slug || newStatus;
					$select.removeClass(function(index, className) {
						return (className.match(/(^|\s)status-\S+/g) || []).join(' ');
					});
					$select.addClass('status-' + cleanSlug);

					$select.css('transform', 'scale(1.06)');
					setTimeout(function() {
						$select.css('transform', 'none');
					}, 200);
				} else {
					alert(response.data && response.data.message ? response.data.message : 'Failed to update order status.');
				}
			},
			error: function() {
				$select.removeClass('updating');
				alert('Network error while updating status. Please try again.');
			}
		});
	});

});
