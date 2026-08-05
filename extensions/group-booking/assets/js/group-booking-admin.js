/**
 * SlotNova Group Booking Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Toggle switches in settings
        $('#slotnova_group_enabled_toggle').on('change', function() {
            if ($(this).is(':checked')) {
                $('#slotnova_group_toggle_track').addClass('active');
            } else {
                $('#slotnova_group_toggle_track').removeClass('active');
            }
        });
    });

})(jQuery);
