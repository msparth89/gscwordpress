<?php
/**
 * Admin Dashboard Page
 * 
 * Main dashboard for the GSC WordPress plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSC_Admin_Dashboard extends GSC_Admin_Page {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_gsc_get_dashboard_stats', array($this, 'ajax_get_stats'));
    }

    /**
     * Get menu title
     * 
     * @return string
     */
    public function get_menu_title() {
        return __('Dashboard', 'gscwordpress');
    }

    /**
     * Get page title
     * 
     * @return string
     */
    public function get_page_title() {
        return __('GSC WordPress Dashboard', 'gscwordpress');
    }

    /**
     * Get menu slug
     * 
     * @return string
     */
    public function get_menu_slug() {
        return 'gsc-dashboard';
    }

    /**
     * Enqueue dashboard scripts and styles
     */
    public function enqueue_scripts() {
        if (!$this->is_current_page()) {
            return;
        }

        wp_enqueue_style(
            'gsc-admin-dashboard',
            GSC_PLUGIN_URL . 'assets/css/admin-dashboard.css',
            array(),
            GSC_VERSION
        );

        wp_enqueue_script(
            'gsc-admin-dashboard',
            GSC_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            array('jquery'),
            GSC_VERSION,
            true
        );

        wp_localize_script('gsc-admin-dashboard', 'gscDashboard', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gsc_dashboard_nonce')
        ));
    }

    /**
     * Render the dashboard page
     */
    public function render_page() {
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="wrap gsc-dashboard">
            <h1><?php echo esc_html($this->get_page_title()); ?></h1>

            <!-- Quick Stats -->
            <div class="gsc-dashboard-stats">
                <div class="gsc-stat-box">
                    <h3><?php _e('Total Payments', 'gscwordpress'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['total_payments']); ?></div>
                </div>

                <div class="gsc-stat-box">
                    <h3><?php _e('Success Rate', 'gscwordpress'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['success_rate']); ?>%</div>
                </div>

                <div class="gsc-stat-box">
                    <h3><?php _e('Active Gateway', 'gscwordpress'); ?></h3>
                    <div class="stat-value"><?php echo esc_html($stats['active_gateway']); ?></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="gsc-dashboard-actions">
                <h2><?php _e('Quick Actions', 'gscwordpress'); ?></h2>
                <div class="action-buttons">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gsc-payment-settings')); ?>" class="button button-primary">
                        <?php _e('Payment Settings', 'gscwordpress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gsc-payment-settings&tab=batches')); ?>" class="button button-secondary">
                        <?php _e('Create Payment Batch', 'gscwordpress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gsc-reports')); ?>" class="button button-secondary">
                        <?php _e('View Reports', 'gscwordpress'); ?>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="gsc-dashboard-recent">
                <h2><?php _e('Recent Payment Batches', 'gscwordpress'); ?></h2>
                <?php $this->render_recent_batches(); ?>
            </div>

            <!-- System Status -->
            <div class="gsc-dashboard-status">
                <h2><?php _e('System Status', 'gscwordpress'); ?></h2>
                <?php $this->render_system_status(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get dashboard statistics
     * 
     * @return array
     */
    private function get_dashboard_stats() {
        global $wpdb;

        // Get active gateway
        $options = get_option('gsc_payment_gateway_options', array());
        $active_gateway = isset($options['active_gateway']) ? $options['active_gateway'] : 'none';

        // Get payment stats from batches table
        $total_payments = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gsc_payment_batch_items"
        );

        $successful_payments = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gsc_payment_batch_items WHERE status = 'completed'"
        );

        $success_rate = $total_payments > 0 
            ? round(($successful_payments / $total_payments) * 100, 2)
            : 0;

        return array(
            'total_payments' => $total_payments,
            'success_rate' => $success_rate,
            'active_gateway' => ucfirst($active_gateway)
        );
    }

    /**
     * Render recent payment batches
     */
    private function render_recent_batches() {
        global $wpdb;

        $batches = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}gsc_payment_batches 
             ORDER BY created_at DESC LIMIT 5"
        );

        if (empty($batches)) {
            echo '<p>' . esc_html__('No recent payment batches.', 'gscwordpress') . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Batch ID', 'gscwordpress') . '</th>';
        echo '<th>' . esc_html__('Items', 'gscwordpress') . '</th>';
        echo '<th>' . esc_html__('Status', 'gscwordpress') . '</th>';
        echo '<th>' . esc_html__('Created', 'gscwordpress') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($batches as $batch) {
            printf(
                '<tr>
                    <td><a href="%s">%s</a></td>
                    <td>%d</td>
                    <td><span class="status-badge status-%s">%s</span></td>
                    <td>%s</td>
                </tr>',
                esc_url(admin_url('admin.php?page=gsc-payment-settings&tab=batches&batch_id=' . $batch->id)),
                esc_html($batch->id),
                esc_html($batch->total_items),
                esc_attr($batch->status),
                esc_html(ucfirst($batch->status)),
                esc_html(human_time_diff(strtotime($batch->created_at), current_time('timestamp')) . ' ago')
            );
        }

        echo '</tbody></table>';
    }

    /**
     * Render system status
     */
    private function render_system_status() {
        $status_items = array(
            array(
                'label' => __('Payment Gateway', 'gscwordpress'),
                'status' => $this->check_gateway_status()
            ),
            array(
                'label' => __('Database Tables', 'gscwordpress'),
                'status' => $this->check_database_status()
            ),
            array(
                'label' => __('File Permissions', 'gscwordpress'),
                'status' => $this->check_file_permissions()
            )
        );

        echo '<table class="wp-list-table widefat fixed striped">';
        foreach ($status_items as $item) {
            printf(
                '<tr>
                    <td>%s</td>
                    <td><span class="status-indicator %s"></span> %s</td>
                </tr>',
                esc_html($item['label']),
                esc_attr($item['status']['class']),
                esc_html($item['status']['message'])
            );
        }
        echo '</table>';
    }

    /**
     * Check payment gateway status
     * 
     * @return array Status info
     */
    private function check_gateway_status() {
        $options = get_option('gsc_payment_gateway_options', array());
        $active_gateway = isset($options['active_gateway']) ? $options['active_gateway'] : '';
        $mock_mode = isset($options['mock_mode']) ? $options['mock_mode'] : false;

        if (!$active_gateway) {
            return array(
                'class' => 'error',
                'message' => __('No gateway configured', 'gscwordpress')
            );
        }

        if ($mock_mode) {
            return array(
                'class' => 'warning',
                'message' => sprintf(
                    __('%s (Test Mode)', 'gscwordpress'),
                    ucfirst($active_gateway)
                )
            );
        }

        return array(
            'class' => 'success',
            'message' => sprintf(
                __('%s (Live)', 'gscwordpress'),
                ucfirst($active_gateway)
            )
        );
    }

    /**
     * Check database status
     * 
     * @return array Status info
     */
    private function check_database_status() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'gsc_payment_batches',
            $wpdb->prefix . 'gsc_payment_batch_items'
        );

        $missing_tables = array();
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                $missing_tables[] = $table;
            }
        }

        if (!empty($missing_tables)) {
            return array(
                'class' => 'error',
                'message' => __('Missing required tables', 'gscwordpress')
            );
        }

        return array(
            'class' => 'success',
            'message' => __('All tables present', 'gscwordpress')
        );
    }

    /**
     * Check file permissions
     * 
     * @return array Status info
     */
    private function check_file_permissions() {
        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/gscwordpress';

        if (!file_exists($base_path)) {
            if (!wp_mkdir_p($base_path)) {
                return array(
                    'class' => 'error',
                    'message' => __('Unable to create upload directory', 'gscwordpress')
                );
            }
        }

        if (!is_writable($base_path)) {
            return array(
                'class' => 'error',
                'message' => __('Upload directory not writable', 'gscwordpress')
            );
        }

        return array(
            'class' => 'success',
            'message' => __('File permissions OK', 'gscwordpress')
        );
    }

    /**
     * AJAX handler for getting dashboard stats
     */
    public function ajax_get_stats() {
        check_ajax_referer('gsc_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success($this->get_dashboard_stats());
    }
}
