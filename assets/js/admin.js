/**
 * GSC WordPress Admin Scripts
 */
(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        // Handle tab navigation
        $('.gsc-nav-tab-wrapper .nav-tab').on('click', function(e) {
            // Already handled by WordPress admin URL params
        });

        // Add validation to gateway API credentials
        $('.gsc-payment-gateways-table input[type="text"], .gsc-payment-gateways-table input[type="password"]').on('change', function() {
            var $row = $(this).closest('tr');
            var $radio = $row.find('input[type="radio"]');
            
            // If this gateway has credentials but isn't selected, highlight it
            if ($(this).val() && !$radio.prop('checked')) {
                $row.addClass('gsc-gateway-highlight');
            } else {
                $row.removeClass('gsc-gateway-highlight');
            }
        });

        // Show confirmation when enabling mock mode
        $('#gsc_mock_mode').on('change', function() {
            if ($(this).prop('checked')) {
                // Just a simple reminder, no action needed
                console.log('Mock mode enabled for UPI verification');
            }
        });
    });

})(jQuery);
