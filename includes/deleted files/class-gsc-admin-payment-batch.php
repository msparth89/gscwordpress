<?php
/**
 * Admin Payment Batch Management
 * 
 * Handles the admin interface for managing payment batches
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSC_Admin_Payment_Batch {
    /**
     * The single instance of the class
     */
    protected static $_instance = null;

    /**
     * Main Instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Add menu item
        add_action('admin_menu', array($this, 'add_menu_item'));
        
        // Add AJAX handlers
        add_action('wp_ajax_gsc_create_payment_batch', array($this, 'ajax_create_batch'));
        add_action('wp_ajax_gsc_process_batch', array($this, 'ajax_process_batch'));
    }

    /**
     * Add menu item
     */
    public function add_menu_item() {
        add_submenu_page(
            'gsc-settings',
            __('Payment Batches', 'gscwordpress'),
            __('Payment Batches', 'gscwordpress'),
            'manage_options',
            'gsc-payment-batches',
            array($this, 'render_page')
        );
    }

    /**
     * Render admin page
     */
    public function render_page() {
        // Get current action
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Payment Batches', 'gscwordpress') . '</h1>';
        
        if ($action === 'view' && isset($_GET['id'])) {
            $this->render_batch_details(intval($_GET['id']));
        } else {
            $this->render_batch_list();
        }
        
        echo '</div>';
    }

    /**
     * Render batch list
     */
    private function render_batch_list() {
        global $wpdb;
        
        // Add new batch button
        echo '<a href="#" class="page-title-action gsc-create-batch">' . __('Create New Batch', 'gscwordpress') . '</a>';
        
        // Get batches
        $batches = $wpdb->get_results(
            "SELECT b.*, 
                    COUNT(i.item_id) as total_items,
                    SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as completed_items
             FROM {$wpdb->prefix}gsc_payment_batches b
             LEFT JOIN {$wpdb->prefix}gsc_payment_items i ON b.batch_id = i.batch_id
             GROUP BY b.batch_id
             ORDER BY b.created_at DESC"
        );
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Batch ID', 'gscwordpress'); ?></th>
                    <th><?php _e('Gateway', 'gscwordpress'); ?></th>
                    <th><?php _e('Total Amount', 'gscwordpress'); ?></th>
                    <th><?php _e('Items', 'gscwordpress'); ?></th>
                    <th><?php _e('Status', 'gscwordpress'); ?></th>
                    <th><?php _e('Created', 'gscwordpress'); ?></th>
                    <th><?php _e('Actions', 'gscwordpress'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batches as $batch): ?>
                <tr>
                    <td><?php echo esc_html($batch->batch_id); ?></td>
                    <td><?php echo esc_html($batch->gateway); ?></td>
                    <td><?php echo esc_html(number_format($batch->total_amount, 2)); ?></td>
                    <td><?php echo sprintf(
                        __('%d/%d completed', 'gscwordpress'),
                        $batch->completed_items,
                        $batch->total_items
                    ); ?></td>
                    <td><?php echo esc_html(ucfirst($batch->status)); ?></td>
                    <td><?php echo esc_html(
                        date_i18n(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            strtotime($batch->created_at)
                        )
                    ); ?></td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(array(
                            'page' => 'gsc-payment-batches',
                            'action' => 'view',
                            'id' => $batch->batch_id
                        ))); ?>" class="button button-small">
                            <?php _e('View Details', 'gscwordpress'); ?>
                        </a>
                        <?php if ($batch->status === 'pending'): ?>
                        <button type="button" 
                                class="button button-small gsc-process-batch" 
                                data-batch-id="<?php echo esc_attr($batch->batch_id); ?>">
                            <?php _e('Process Now', 'gscwordpress'); ?>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($batches)): ?>
                <tr>
                    <td colspan="7"><?php _e('No payment batches found.', 'gscwordpress'); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Create Batch Modal -->
        <div id="gsc-create-batch-modal" style="display:none;">
            <h2><?php _e('Create Payment Batch', 'gscwordpress'); ?></h2>
            <p>
                <label>
                    <?php _e('Select Referrals', 'gscwordpress'); ?>
                    <select multiple class="gsc-referral-select">
                        <?php
                        // Get unpaid referrals
                        $referrals = $this->get_unpaid_referrals();
                        foreach ($referrals as $referral) {
                            printf(
                                '<option value="%d">%s - %s (%s)</option>',
                                esc_attr($referral->id),
                                esc_html($referral->affiliate_name),
                                esc_html($referral->upi_id),
                                esc_html(number_format($referral->amount, 2))
                            );
                        }
                        ?>
                    </select>
                </label>
            </p>
            <p>
                <button type="button" class="button button-primary gsc-create-batch-submit">
                    <?php _e('Create Batch', 'gscwordpress'); ?>
                </button>
                <button type="button" class="button gsc-create-batch-cancel">
                    <?php _e('Cancel', 'gscwordpress'); ?>
                </button>
            </p>
        </div>
        <?php
    }

    /**
     * Render batch details
     */
    private function render_batch_details($batch_id) {
        global $wpdb;
        
        // Get batch
        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gsc_payment_batches WHERE batch_id = %d",
            $batch_id
        ));
        
        if (!$batch) {
            wp_die(__('Batch not found', 'gscwordpress'));
        }
        
        // Get items
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, r.affiliate_name 
             FROM {$wpdb->prefix}gsc_payment_items i
             LEFT JOIN {$wpdb->prefix}gsc_referrals r ON i.referral_id = r.id
             WHERE i.batch_id = %d
             ORDER BY i.created_at ASC",
            $batch_id
        ));
        
        ?>
        <div class="gsc-batch-details">
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=gsc-payment-batches')); ?>" 
                   class="button">
                    <?php _e('← Back to Batches', 'gscwordpress'); ?>
                </a>
            </p>
            
            <h2><?php printf(__('Batch #%d Details', 'gscwordpress'), $batch_id); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th><?php _e('Gateway', 'gscwordpress'); ?></th>
                    <td><?php echo esc_html($batch->gateway); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Status', 'gscwordpress'); ?></th>
                    <td><?php echo esc_html(ucfirst($batch->status)); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Total Amount', 'gscwordpress'); ?></th>
                    <td><?php echo esc_html(number_format($batch->total_amount, 2)); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Created', 'gscwordpress'); ?></th>
                    <td><?php echo esc_html(
                        date_i18n(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            strtotime($batch->created_at)
                        )
                    ); ?></td>
                </tr>
            </table>
            
            <h3><?php _e('Payment Items', 'gscwordpress'); ?></h3>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Affiliate', 'gscwordpress'); ?></th>
                        <th><?php _e('UPI ID', 'gscwordpress'); ?></th>
                        <th><?php _e('Amount', 'gscwordpress'); ?></th>
                        <th><?php _e('Status', 'gscwordpress'); ?></th>
                        <th><?php _e('Response', 'gscwordpress'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item->affiliate_name); ?></td>
                        <td><?php echo esc_html($item->upi_id); ?></td>
                        <td><?php echo esc_html(number_format($item->amount, 2)); ?></td>
                        <td><?php echo esc_html(ucfirst($item->status)); ?></td>
                        <td>
                            <?php if ($item->gateway_response): ?>
                            <pre><?php echo esc_html(
                                json_encode(
                                    json_decode($item->gateway_response),
                                    JSON_PRETTY_PRINT
                                )
                            ); ?></pre>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Get unpaid referrals
     */
    private function get_unpaid_referrals() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT r.*, u.display_name as affiliate_name
             FROM {$wpdb->prefix}gsc_referrals r
             LEFT JOIN {$wpdb->users} u ON r.affiliate_id = u.ID
             WHERE r.status = 'unpaid'
             AND r.upi_id IS NOT NULL
             ORDER BY r.created_at ASC"
        );
    }

    /**
     * AJAX create batch
     */
    public function ajax_create_batch() {
        check_ajax_referer('gsc_admin_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $referrals = isset($_POST['referrals']) ? array_map('intval', $_POST['referrals']) : array();
        
        if (empty($referrals)) {
            wp_send_json_error('No referrals selected');
        }
        
        $batch = GSC_Payment_Batch::instance();
        $result = $batch->create_batch($referrals);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'batch_id' => $result,
            'redirect' => add_query_arg(array(
                'page' => 'gsc-payment-batches',
                'action' => 'view',
                'id' => $result
            ), admin_url('admin.php'))
        ));
    }

    /**
     * AJAX process batch
     */
    public function ajax_process_batch() {
        check_ajax_referer('gsc_admin_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $batch_id = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
        
        if (!$batch_id) {
            wp_send_json_error('Invalid batch ID');
        }
        
        $batch = GSC_Payment_Batch::instance();
        $batch->process_pending_batches();
        
        wp_send_json_success();
    }
}
