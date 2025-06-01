<?php
/**
 * GSC Payment Gateway Class
 * 
 * Handles payment gateway integration and settings
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include gateway classes
require_once plugin_dir_path(__FILE__) . 'gateways/class-gsc-abstract-gateway.php';
require_once plugin_dir_path(__FILE__) . 'gateways/class-gsc-cashfree-gateway.php';
require_once plugin_dir_path(__FILE__) . 'gateways/class-gsc-razorpay-gateway.php';
require_once plugin_dir_path(__FILE__) . 'gateways/class-gsc-payu-gateway.php';

class GSC_Payment_Gateway {
    /**
     * The single instance of the class.
     */
    protected static $_instance = null;

    /**
     * Settings group name
     */
    const SETTINGS_GROUP = 'gsc_payment_gateway_settings';
    
    /**
     * Settings option name
     */
    const SETTINGS_OPTION = 'gsc_payment_gateway_options';
    
    /**
     * Available gateways
     */
    protected $gateways = array();

    /**
     * Constructor.
     */
    public function __construct() {
        // Initialize available gateways
        $this->init_gateways();
    }
    
    /**
     * Initialize available gateways
     */
    private function init_gateways() {
        $this->gateways = array(
            'cashfree' => new GSC_Cashfree_Gateway(),
            'razorpay' => new GSC_Razorpay_Gateway(),
            'payu' => new GSC_PayU_Gateway()
        );
    }

    /**
     * Main GSC_Payment_Gateway Instance.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
     * Get the active gateway instance
     * 
     * @return GSC_Abstract_Gateway|null Gateway instance or null if not found
     */
    public function get_active_gateway() {
        $options = get_option(self::SETTINGS_OPTION, array());
        $active_gateway_id = isset($options['active_gateway']) ? $options['active_gateway'] : 'cashfree';
        
        if (!isset($this->gateways[$active_gateway_id])) {
            return null;
        }
        
        $gateway = $this->gateways[$active_gateway_id];
        
        // Set the gateway settings
        if (isset($options['gateways'][$active_gateway_id])) {
            $gateway->update_settings($options['gateways'][$active_gateway_id]);
        }
        
        return $gateway;
    }
    
    /**
     * Verify a UPI ID using the active gateway
     * 
     * @param string $upi_id The UPI ID to verify
     * @return bool|array True if valid, error array if invalid
     */
    public function verify_upi($upi_id) {
        $gateway = $this->get_active_gateway();
        
        if (!$gateway) {
            return array('error' => 'No active payment gateway configured');
        }
        
        return $gateway->verify_upi($upi_id);
    }
    
    /**
     * Process a payout to a UPI ID
     * 
     * @param string $upi_id UPI ID to send payment to
     * @param float $amount Amount to pay
     * @param string $reference Reference ID for the transaction
     * @return array Transaction details or error
     */
    public function process_payout($upi_id, $amount, $reference) {
        $gateway = $this->get_active_gateway();
        
        if (!$gateway) {
            return array('error' => 'No active payment gateway configured');
        }
        
        return $gateway->process_payout($upi_id, $amount, $reference);
    }
    
    /**
     * Check payout status
     * 
     * @param string $payout_id Payout ID to check
     * @return array Status details
     */
    public function check_payout_status($payout_id) {
        $gateway = $this->get_active_gateway();
        
        if (!$gateway) {
            return array('error' => 'No active payment gateway configured');
        }
        
        return $gateway->check_payout_status($payout_id);
    }

    /**
     * Get all available payment gateways
     * 
     * @return array Array of gateway instances
     */
    public function get_gateways() {
        return $this->gateways;
    }
}