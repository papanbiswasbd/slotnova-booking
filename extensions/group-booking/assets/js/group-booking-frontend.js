/**
 * SlotNova Group Booking Frontend JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Check if calendar date is selected to display user info form container
        function checkDateSelection() {
            var selectedDate = $('#slotnova_booking_date').val();
            var container = $('.slotnova-group-booking-container');
            if (!container.length) return;

            if (selectedDate && selectedDate.trim() !== '') {
                if (container.is(':hidden')) {
                    container.slideDown(250);
                }
            } else {
                container.hide();
            }
        }

        // Render participant table (# | Mr./Mrs. | Full Name | Email Address)
        function updateParticipantRoster() {
            var qty = parseInt($('#slotnova_group_quantity').val()) || 1;
            var container = $('#slotnova_participant_roster_container');
            if (!container.length) return;

            container.empty();

            // Render Single Header Row
            var headerHtml = '<div class="slotnova-participant-table-header">';
            headerHtml += '<div>#</div>';
            headerHtml += '<div>Mr./Mrs. *</div>';
            headerHtml += '<div>Full Name *</div>';
            headerHtml += '<div>Email Address *</div>';
            headerHtml += '</div>';
            container.append(headerHtml);

            // Render Numbered Participant Rows (1, 2, 3...)
            for (var i = 1; i <= qty; i++) {
                var idx = i - 1;
                var html = '<div class="slotnova-participant-row">';
                html += '<div class="slotnova-participant-grid">';
                
                // Column 0: Row Index Badge
                html += '<div style="display:flex; justify-content:center; align-items:center;"><span class="slotnova-row-num">' + i + '</span></div>';

                // Column 1: Mr./Mrs.
                html += '<div>';
                html += '<select name="slotnova_group_participants[' + idx + '][gender]" required style="box-sizing:border-box;">';
                html += '<option value="">Select</option>';
                html += '<option value="Mr.">Mr.</option>';
                html += '<option value="Mrs.">Mrs.</option>';
                html += '<option value="Ms.">Ms.</option>';
                html += '<option value="Dr.">Dr.</option>';
                html += '</select>';
                html += '</div>';

                // Column 2: Full Name
                html += '<div>';
                html += '<input type="text" name="slotnova_group_participants[' + idx + '][name]" placeholder="Enter full name" required style="box-sizing:border-box;" />';
                html += '</div>';

                // Column 3: Email Address
                html += '<div>';
                html += '<input type="email" name="slotnova_group_participants[' + idx + '][email]" placeholder="name@example.com" required style="box-sizing:border-box;" />';
                html += '</div>';

                html += '</div>'; // end grid
                html += '</div>'; // end row container

                container.append(html);
            }

            updateGroupPrice();
        }

        function updateGroupPrice() {
            var qty = parseInt($('#slotnova_group_quantity').val()) || 1;
            var serviceId = $('#slotnova_service').val() || 0;
            var productId = (typeof slotnova_group_vars !== 'undefined' && slotnova_group_vars.product_id) ? slotnova_group_vars.product_id : ($('input[name="add-to-cart"]').val() || $('[name="product_id"]').val() || 0);

            if (!productId || !$('#slotnova_group_subtotal_amount').length) return;

            $.post(slotnova_group_vars.ajax_url, {
                action: 'slotnova_group_calculate_price',
                nonce: slotnova_group_vars.nonce,
                product_id: productId,
                service_id: serviceId,
                quantity: qty
            }, function(res) {
                if (res.success && res.data.formatted_total) {
                    $('#slotnova_group_subtotal_amount').html(res.data.formatted_total);
                }
            });
        }

        // Date selection change triggers
        $(document).on('change input', '#slotnova_booking_date', function() {
            checkDateSelection();
        });

        $(document).on('click', '.flatpickr-day', function() {
            setTimeout(function() {
                checkDateSelection();
            }, 100);
        });

        $(document).on('change', '#slotnova_group_quantity', function() {
            updateParticipantRoster();
        });

        // Initialize state on page ready
        checkDateSelection();
        if ($('#slotnova_group_quantity').length) {
            updateParticipantRoster();
        }

        /* -------------------------------------------------------------
         * Time Slot Hover Remaining Seats Tooltip
         * ------------------------------------------------------------- */
        var capacityCache = {};
        var tooltipElem = null;

        function getOrCreateTooltip() {
            if (!tooltipElem || !tooltipElem.length) {
                tooltipElem = $('<div id="slotnova-time-tooltip" class="slotnova-calendar-tooltip"></div>').appendTo('body');
            }
            return tooltipElem;
        }

        function positionTooltip(targetElem) {
            var $target = $(targetElem);
            var offset = $target.offset();
            var width = $target.outerWidth();
            var tooltip = getOrCreateTooltip();

            tooltip.css({
                top: offset.top + 'px',
                left: (offset.left + (width / 2)) + 'px'
            }).addClass('active');
        }

        function hideTooltip() {
            if (tooltipElem) {
                tooltipElem.removeClass('active');
            }
        }

        $(document).on('mouseenter', '.slotnova-time-pill', function() {
            var pillElem = this;
            var timeVal = $(pillElem).attr('data-value') || $(pillElem).text().trim();
            var dateStr = $('#slotnova_booking_date').val();
            var productId = (typeof slotnova_group_vars !== 'undefined' && slotnova_group_vars.product_id) ? slotnova_group_vars.product_id : ($('input[name="add-to-cart"]').val() || $('[name="product_id"]').val() || 0);
            var serviceId = $('#slotnova_service').val() || 0;

            if (!timeVal || !productId || productId <= 0) return;

            var cacheKey = productId + '_' + serviceId + '_' + (dateStr || 'nodate') + '_' + timeVal;
            var tooltip = getOrCreateTooltip();

            positionTooltip(pillElem);

            if (capacityCache.hasOwnProperty(cacheKey)) {
                var data = capacityCache[cacheKey];
                renderTooltipContent(tooltip, data, timeVal);
            } else {
                var loadingHtml = '<div class="slotnova-tooltip-card">';
                loadingHtml += '<div class="slotnova-tooltip-top"><span class="slotnova-tooltip-date">' + timeVal + '</span><span class="slotnova-tooltip-badge low">Checking</span></div>';
                loadingHtml += '<div class="slotnova-tooltip-progress-bg"><div class="slotnova-tooltip-progress-bar low" style="width: 50%;"></div></div>';
                loadingHtml += '<div class="slotnova-tooltip-footer"><span class="slotnova-tooltip-seats">Checking seats...</span></div>';
                loadingHtml += '</div>';

                tooltip.html(loadingHtml);

                $.post(slotnova_group_vars.ajax_url, {
                    action: 'slotnova_group_get_capacity',
                    nonce: slotnova_group_vars.nonce,
                    product_id: productId,
                    service_id: serviceId,
                    booking_date: dateStr,
                    booking_time: timeVal
                }, function(res) {
                    if (res.success) {
                        capacityCache[cacheKey] = res.data;
                        if (tooltip.hasClass('active')) {
                            renderTooltipContent(tooltip, res.data, timeVal);
                        }
                    }
                });
            }
        });

        $(document).on('mouseleave', '.slotnova-time-pill', function() {
            hideTooltip();
        });

        $(window).on('scroll resize', function() {
            hideTooltip();
        });

        function renderTooltipContent(tooltip, data, timeVal) {
            var remaining = typeof data.remaining_seats !== 'undefined' ? data.remaining_seats : (typeof data.remaining !== 'undefined' ? data.remaining : 0);
            var maxCap = typeof data.max_capacity !== 'undefined' ? data.max_capacity : (typeof data.max !== 'undefined' ? data.max : 20);
            var isFull = data.is_full || remaining <= 0;

            var pct = Math.max(0, Math.min(100, Math.round((remaining / maxCap) * 100)));
            var statusClass = isFull ? 'full' : (remaining <= 3 ? 'low' : 'available');
            var statusLabel = isFull ? 'Full' : (remaining <= 3 ? 'Low Seats' : 'Available');

            var html = '<div class="slotnova-tooltip-card">';
            html += '<div class="slotnova-tooltip-top">';
            html += '<span class="slotnova-tooltip-date">' + timeVal + '</span>';
            html += '<span class="slotnova-tooltip-badge ' + statusClass + '">' + statusLabel + '</span>';
            html += '</div>';

            html += '<div class="slotnova-tooltip-progress-bg">';
            html += '<div class="slotnova-tooltip-progress-bar ' + statusClass + '" style="width: ' + pct + '%;"></div>';
            html += '</div>';

            html += '<div class="slotnova-tooltip-footer">';
            html += '<span class="slotnova-tooltip-seats"><strong>' + remaining + '</strong> seats left</span>';
            html += '<span class="slotnova-tooltip-cap">Cap: ' + maxCap + '</span>';
            html += '</div>';
            html += '</div>';

            tooltip.html(html);
        }

    });

})(jQuery);
