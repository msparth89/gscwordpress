jQuery(document).ready(function($) {
    // Auto-refresh dashboard stats every 60 seconds
    function refreshStats() {
        $.ajax({
            url: gscDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gsc_get_dashboard_stats',
                nonce: gscDashboard.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Update stats
                    $('.gsc-stat-box').each(function() {
                        const $box = $(this);
                        const key = $box.data('stat');
                        if (response.data[key]) {
                            $box.find('.stat-value').text(response.data[key]);
                        }
                    });
                }
            }
        });
    }

    // Set up auto-refresh if we're on the dashboard
    if ($('.gsc-dashboard').length) {
        setInterval(refreshStats, 60000); // Refresh every minute
    }
});
