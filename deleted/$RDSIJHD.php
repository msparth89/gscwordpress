<?php
/**
 * Payment Batch Processor Class
 * 
 * Handles creating and processing payment batches for affiliate commissions
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSC_Payment_Batch {
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
        // Add cron schedule for batch processing
        add_filter('cron_schedules', array($this, 'add_cron_schedule'));
        
        // Schedule the cron if not already scheduled
        if (!wp_next_scheduled('gsc_process_payment_batches')) {
            wp_schedule_event(time(), 'every_hour', 'gsc_process_payment_batches');
        }
        
        // Hook into the cron action
        add_action('gsc_process_payment_batches', array($this, 'process_pending_batches'));
    }

    /**
     * Add custom cron schedule
     */
    public function add_cron_schedule($schedules) {
        $schedules['every_hour'] = array(
            'interval' => 3600, // Every hour
            'display'  => __('Every Hour', 'gscwordpress')
        );
        return $schedules;
    }

    /**
     * Create a new payment batch
     * 
     * @param array $referrals Array of referral IDs to process
     * @return int|WP_Error Batch ID if successful, WP_Error on failure
     */
    public function create_batch($referrals) {
        global $wpdb;
        
        if (empty($referrals)) {
            return new WP_Error('empty_batch', 'No referrals provided for batch');
        }

        // Get active payment gateway
        $gateway = GSC_Payment_Gateway::instance()->get_active_gateway();
        if (!$gateway) {
            return new WP_Error('no_gateway', 'No active payment gateway configured');
        }

        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Create batch record
            $batch_result = $wpdb->insert(
                $wpdb->prefix . 'gsc_payment_batches',
                array(
                    'gateway' => $gateway->get_id(),
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s')
            );

            if (!$batch_result) {
                throw new Exception('Failed to create batch record');
            }

            $batch_id = $wpdb->insert_id;
            $total_amount = 0;

            // Process each referral
            foreach ($referrals as $referral_id) {
                // Get referral details (implement this based on your referral system)
                $referral = $this->get_referral_details($referral_id);
                if (!$referral) {
                    continue;
                }

                // Add payment item
                $item_result = $wpdb->insert(
                    $wpdb->prefix . 'gsc_payment_items',
                    array(
                        'batch_id' => $batch_id,
                        'referral_id' => $referral_id,
                        'amount' => $referral['amount'],
                        'upi_id' => $referral['upi_id'],
                        'account_name' => $referral['account_name'],
                        'status' => 'pending',
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%f', '%s', '%s', '%s', '%s')
                );

                if (!$item_result) {
                    throw new Exception('Failed to create payment item');
                }

                $total_amount += $referral['amount'];
            }

            // Update batch with total
            $wpdb->update(
                $wpdb->prefix . 'gsc_payment_batches',
                array(
                    'total_amount' => $total_amount,
                    'total_items' => count($referrals)
                ),
                array('batch_id' => $batch_id),
                array('%f', '%d'),
                array('%d')
            );

            // Commit transaction
            $wpdb->query('COMMIT');
            return $batch_id;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('batch_creation_failed', $e->getMessage());
        }
    }

    /**
     * Process pending payment batches
     */
    public function process_pending_batches() {
        global $wpdb;
        
        // Get active gateway
        $gateway = GSC_Payment_Gateway::instance()->get_active_gateway();
        if (!$gateway) {
            return;
        }

        // Get pending batches
        $batches = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}gsc_payment_batches 
             WHERE status = 'pending' 
             ORDER BY created_at ASC 
             LIMIT 1"
        );

        foreach ($batches as $batch) {
            // Get batch items
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gsc_payment_items 
                 WHERE batch_id = %d AND status = 'pending'",
                $batch->batch_id
            ));

            // Process each item
            foreach ($items as $item) {
                // Process payment through gateway
                $result = $gateway->process_payout(
                    $item->upi_id,
                    $item->amount,
                    'REF-' . $item->referral_id
                );

                // Update item status
                $status = isset($result['success']) && $result['success'] ? 'completed' : 'failed';
                $wpdb->update(
                    $wpdb->prefix . 'gsc_payment_items',
                    array(
                        'status' => $status,
                        'gateway_response' => json_encode($result)
                    ),
                    array('item_id' => $item->item_id),
                    array('%s', '%s'),
                    array('%d')
                );

                // If payment successful, mark referral as paid
                if ($status === 'completed') {
                    $this->mark_referral_paid($item->referral_id);
                }
            }

            // Update batch status
            $completed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gsc_payment_items 
                 WHERE batch_id = %d AND status = 'completed'",
                $batch->batch_id
            ));

            $status = ($completed == $batch->total_items) ? 'completed' : 'partial';
            $wpdb->update(
                $wpdb->prefix . 'gsc_payment_batches',
                array('status' => $status),
                array('batch_id' => $batch->batch_id),
                array('%s'),
                array('%d')
            );
        }
    }

    /**
     * Get referral details
     * 
     * @param int $referral_id Referral ID
     * @return array|false Referral details or false if not found
     */
    private function get_referral_details($referral_id) {
        // Implement this based on your referral system
        // Should return array with: amount, upi_id, account_name
        return false;
    }

    /**
     * Mark a referral as paid
     * 
     * @param int $referral_id Referral ID
     */
    private function mark_referral_paid($referral_id) {
        // Implement this based on your referral system
    }
}
