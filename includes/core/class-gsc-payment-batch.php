<?php
/**
 * GSC Payment Batch Processor
 *
 * Handles creation and processing of payment batches for affiliate payouts.
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
        
        if (empty($referrals) || !is_array($referrals)) {
            return new WP_Error('invalid_referrals', __('No valid referrals provided.', 'gscwordpress'));
        }
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Insert batch record
            $batch_inserted = $wpdb->insert(
                $wpdb->prefix . 'gsc_payment_batches',
                array(
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                    'total_referrals' => count($referrals),
                    'processed_referrals' => 0,
                    'successful_payouts' => 0,
                    'failed_payouts' => 0
                ),
                array('%s', '%s', '%d', '%d', '%d', '%d')
            );
            
            if (!$batch_inserted) {
                throw new Exception(__('Failed to create payment batch.', 'gscwordpress'));
            }
            
            $batch_id = $wpdb->insert_id;
            
            // Insert batch items
            foreach ($referrals as $referral_id) {
                $item_inserted = $wpdb->insert(
                    $wpdb->prefix . 'gsc_payment_batch_items',
                    array(
                        'batch_id' => $batch_id,
                        'referral_id' => $referral_id,
                        'status' => 'pending',
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%s', '%s')
                );
                
                if (!$item_inserted) {
                    throw new Exception(__('Failed to add referrals to batch.', 'gscwordpress'));
                }
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return $batch_id;
        } catch (Exception $e) {
            // Rollback transaction on error
            $wpdb->query('ROLLBACK');
            return new WP_Error('batch_creation_failed', $e->getMessage());
        }
    }

    /**
     * Process pending payment batches
     * 
     * @return array Stats about processed batches
     */
    public function process_pending_batches() {
        global $wpdb;
        
        $stats = array(
            'batches_processed' => 0,
            'successful_payouts' => 0,
            'failed_payouts' => 0
        );
        
        // Get pending batches
        $pending_batches = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}gsc_payment_batches WHERE status = 'pending' ORDER BY created_at ASC LIMIT 5"
        );
        
        if (empty($pending_batches)) {
            return $stats;
        }
        
        // Process each batch
        foreach ($pending_batches as $batch) {
            // Update batch to processing
            $wpdb->update(
                $wpdb->prefix . 'gsc_payment_batches',
                array('status' => 'processing', 'updated_at' => current_time('mysql')),
                array('id' => $batch->id),
                array('%s', '%s'),
                array('%d')
            );
            
            // Get pending items in this batch
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gsc_payment_batch_items WHERE batch_id = %d AND status = 'pending'",
                $batch->id
            ));
            
            if (empty($items)) {
                // Mark batch as completed if no pending items
                $wpdb->update(
                    $wpdb->prefix . 'gsc_payment_batches',
                    array('status' => 'completed', 'updated_at' => current_time('mysql')),
                    array('id' => $batch->id),
                    array('%s', '%s'),
                    array('%d')
                );
                continue;
            }
            
            // Get payment gateway
            $gateway = GSC_Payment_Gateway::instance()->get_active_gateway();
            
            if (!$gateway) {
                // Mark batch as failed if no gateway available
                $wpdb->update(
                    $wpdb->prefix . 'gsc_payment_batches',
                    array('status' => 'failed', 'updated_at' => current_time('mysql')),
                    array('id' => $batch->id),
                    array('%s', '%s'),
                    array('%d')
                );
                continue;
            }
            
            $batch_successful = 0;
            $batch_failed = 0;
            
            // Process each item
            foreach ($items as $item) {
                // Get referral details from the Affiliates plugin
                $referral_details = $this->get_referral_details($item->referral_id);
                
                if (is_wp_error($referral_details)) {
                    // Update item status to failed
                    $wpdb->update(
                        $wpdb->prefix . 'gsc_payment_batch_items',
                        array(
                            'status' => 'failed',
                            'updated_at' => current_time('mysql'),
                            'transaction_data' => json_encode(array(
                                'error' => $referral_details->get_error_message()
                            ))
                        ),
                        array('id' => $item->id),
                        array('%s', '%s', '%s'),
                        array('%d')
                    );
                    
                    $batch_failed++;
                    continue;
                }
                
                // Skip if the UPI ID is not verified
                if (empty($referral_details['upi_id']) || empty($referral_details['account_name'])) {
                    // Update item status to failed
                    $wpdb->update(
                        $wpdb->prefix . 'gsc_payment_batch_items',
                        array(
                            'status' => 'failed',
                            'updated_at' => current_time('mysql'),
                            'transaction_data' => json_encode(array(
                                'error' => __('Missing UPI ID or account name for the affiliate.', 'gscwordpress')
                            ))
                        ),
                        array('id' => $item->id),
                        array('%s', '%s', '%s'),
                        array('%d')
                    );
                    
                    $batch_failed++;
                    continue;
                }
                
                // Prepare payout data
                $payout_data = array(
                    'amount' => $referral_details['amount'],
                    'currency' => 'INR',
                    'beneficiary_name' => $referral_details['account_name'],
                    'beneficiary_upi' => $referral_details['upi_id'],
                    'reference_id' => 'ref_' . $item->referral_id . '_' . time(),
                    'purpose' => __('Affiliate Commission', 'gscwordpress')
                );
                
                // Process payout
                $result = GSC_Payment_Gateway::instance()->process_payout($payout_data);
                
                if ($result['status'] === 'success') {
                    // Update item status to success
                    $wpdb->update(
                        $wpdb->prefix . 'gsc_payment_batch_items',
                        array(
                            'status' => 'completed',
                            'updated_at' => current_time('mysql'),
                            'transaction_id' => $result['transaction_id'],
                            'transaction_data' => json_encode($result)
                        ),
                        array('id' => $item->id),
                        array('%s', '%s', '%s', '%s'),
                        array('%d')
                    );
                    
                    // Mark the referral as paid in the Affiliates plugin
                    $this->mark_referral_paid($item->referral_id, $result['transaction_id']);
                    
                    $batch_successful++;
                    $stats['successful_payouts']++;
                } else {
                    // Update item status to failed
                    $wpdb->update(
                        $wpdb->prefix . 'gsc_payment_batch_items',
                        array(
                            'status' => 'failed',
                            'updated_at' => current_time('mysql'),
                            'transaction_data' => json_encode($result)
                        ),
                        array('id' => $item->id),
                        array('%s', '%s', '%s'),
                        array('%d')
                    );
                    
                    $batch_failed++;
                    $stats['failed_payouts']++;
                }
            }
            
            // Update batch stats
            $batch_status = ($batch_successful > 0 && $batch_failed === 0) ? 'completed' : 
                           (($batch_successful === 0 && $batch_failed > 0) ? 'failed' : 'partial');
                           
            $wpdb->update(
                $wpdb->prefix . 'gsc_payment_batches',
                array(
                    'status' => $batch_status,
                    'updated_at' => current_time('mysql'),
                    'processed_referrals' => $batch_successful + $batch_failed,
                    'successful_payouts' => $batch_successful,
                    'failed_payouts' => $batch_failed
                ),
                array('id' => $batch->id),
                array('%s', '%s', '%d', '%d', '%d'),
                array('%d')
            );
            
            $stats['batches_processed']++;
        }
        
        return $stats;
    }

    /**
     * Get referral details from the Affiliates plugin
     *
     * @param int $referral_id The referral ID
     * @return array|WP_Error Referral details or error
     */
    public function get_referral_details($referral_id) {
        global $wpdb;
        
        // Get referral data from the Affiliates plugin
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aff_referrals WHERE referral_id = %d",
            $referral_id
        ));
        
        if (!$referral) {
            return new WP_Error('invalid_referral', __('Referral not found.', 'gscwordpress'));
        }
        
        // Get affiliate data
        $affiliate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aff_affiliates WHERE affiliate_id = %d",
            $referral->affiliate_id
        ));
        
        if (!$affiliate) {
            return new WP_Error('invalid_affiliate', __('Affiliate not found.', 'gscwordpress'));
        }
        
        // Get the user ID linked to this affiliate
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}aff_affiliates_users WHERE affiliate_id = %d",
            $affiliate->affiliate_id
        ));
        
        if (!$user_id) {
            return new WP_Error('invalid_user', __('User not found for this affiliate.', 'gscwordpress'));
        }
        
        // Get the verified UPI ID and account name from user meta
        $upi_id = get_user_meta($user_id, 'verified_upi_id', true);
        $account_name = get_user_meta($user_id, 'verified_upi_account_name', true);
        
        return array(
            'referral_id' => $referral->referral_id,
            'affiliate_id' => $affiliate->affiliate_id,
            'user_id' => $user_id,
            'amount' => $referral->amount,
            'currency_id' => $referral->currency_id,
            'status' => $referral->status,
            'upi_id' => $upi_id,
            'account_name' => $account_name
        );
    }

    /**
     * Mark a referral as paid in the Affiliates plugin
     *
     * @param int $referral_id The referral ID
     * @param string $transaction_id The payment transaction ID
     * @return bool Success or failure
     */
    public function mark_referral_paid($referral_id, $transaction_id) {
        global $wpdb;
        
        // Update the referral status to 'closed' (paid) in the Affiliates plugin
        $updated = $wpdb->update(
            $wpdb->prefix . 'aff_referrals',
            array(
                'status' => 'closed',
                'data' => json_encode(array(
                    'payment_transaction_id' => $transaction_id,
                    'payment_date' => current_time('mysql')
                ))
            ),
            array('referral_id' => $referral_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($updated) {
            // Log the payment
            $wpdb->insert(
                $wpdb->prefix . 'aff_referral_payouts',
                array(
                    'referral_id' => $referral_id,
                    'transaction_id' => $transaction_id,
                    'payout_date' => current_time('mysql'),
                    'payment_method' => 'upi'
                ),
                array('%d', '%s', '%s', '%s')
            );
            
            return true;
        }
        
        return false;
    }
}
