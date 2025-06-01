<?php
/**
 * GSC Payment Gateway Manager
 *
 * Manages payment gateway integrations, settings, and operations.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSC_Payment_Gateway {
    /**
     * Settings group
     */
    const SETTINGS_GROUP = 'gsc_payment_gateway_options';
    
    /**
     * Settings option name
     */
    const SETTINGS_OPTION = 'gsc_payment_gateway_options';

    /**
     * The single instance of the class
     */
    protected static $_instance = null;

    /**
     * Available gateways
     */
    protected $gateways = array();

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
        $this->init_gateways();
    }

    /**
     * Initialize available gateways
     */
    public function init_gateways() {
        $this->gateways = array(
            'cashfree' => new GSC_Cashfree_Gateway(),
            'razorpay' => new GSC_Razorpay_Gateway(),
            'payu'     => new GSC_PayU_Gateway()
        );
    }

    /**
     * Get all available gateways
     */
    public function get_gateways() {
        return $this->gateways;
    }

    /**
     * Get the active payment gateway
     *
     * @return GSC_Abstract_Gateway|null The active gateway instance or null if none found
     */
    public function get_active_gateway() {
        $options = get_option(self::SETTINGS_OPTION, array());
        $active_gateway_id = isset($options['active_gateway']) ? $options['active_gateway'] : 'cashfree';
        
        if (isset($this->gateways[$active_gateway_id])) {
            $gateway = $this->gateways[$active_gateway_id];
            
            // Set API credentials from options
            if (isset($options['gateways'][$active_gateway_id]['api_key'])) {
                $gateway->set_api_key($options['gateways'][$active_gateway_id]['api_key']);
            }
            
            if (isset($options['gateways'][$active_gateway_id]['api_secret'])) {
                $gateway->set_api_secret($options['gateways'][$active_gateway_id]['api_secret']);
            }
            
            return $gateway;
        }
        
        return null;
    }

    /**
     * Verify UPI ID using the active gateway
     *
     * @param string $upi_id The UPI ID to verify
     * @param string $account_name The account holder's name
     * @return array The verification result with status and message
     */
    public function verify_upi($upi_id, $account_name = '') {
        $gateway = $this->get_active_gateway();
        
        if ($gateway) {
            // Use the mock mode setting if available
            $mock_mode = gsc_is_upi_verification_mock_mode();
            
            if ($mock_mode) {
                // Log that we're using mock mode
                error_log('Global mock mode: true');
                error_log('Gateway test mode: ' . ($gateway->is_test_mode() ? 'true' : 'false'));
                error_log('Mock response for UPI ID: ' . $upi_id);
                
                // Simulate UPI verification based on UPI ID format
                if (strpos($upi_id, '@success.upi') !== false) {
                    // Success case for testing
                    error_log(substr($upi_id, strpos($upi_id, '@') + 1) . ' ' . $upi_id);
                    return array(
                        'status' => 'success',
                        'message' => __('UPI ID verified successfully.', 'gscwordpress'),
                        'account_holder' => !empty($account_name) ? $account_name : substr($upi_id, 0, strpos($upi_id, '@')),
                        'upi_id' => $upi_id
                    );
                } else {
                    // Failure case for testing
                    return array(
                        'status' => 'error',
                        'message' => __('UPI ID verification failed. Please check and try again.', 'gscwordpress')
                    );
                }
            } else {
                // Real verification through gateway
                return $gateway->verify_upi($upi_id, $account_name);
            }
        }
        
        return array(
            'status' => 'error',
            'message' => __('No active payment gateway found.', 'gscwordpress')
        );
    }

    /**
     * Process payout using the active gateway
     *
     * @param array $payout_data The payout data including beneficiary details and amount
     * @return array The payout result with status, message, and transaction ID if successful
     */
    public function process_payout($payout_data) {
        $gateway = $this->get_active_gateway();
        
        if ($gateway) {
            return $gateway->process_payout($payout_data);
        }
        
        return array(
            'status' => 'error',
            'message' => __('No active payment gateway found.', 'gscwordpress')
        );
    }

    /**
     * Check payout status using the active gateway
     *
     * @param string $transaction_id The transaction ID to check
     * @return array The status result with status, message, and additional details
     */
    public function check_payout_status($transaction_id) {
        $gateway = $this->get_active_gateway();
        
        if ($gateway) {
            return $gateway->check_payout_status($transaction_id);
        }
        
        return array(
            'status' => 'error',
            'message' => __('No active payment gateway found.', 'gscwordpress')
        );
    }
}
