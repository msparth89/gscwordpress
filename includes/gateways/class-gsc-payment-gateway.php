<?php
/**
 * Payment Gateway Manager
 * 
 * Manages all payment gateways and provides a unified interface
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSC_Payment_Gateway {
    /**
     * Instance of this class
     * 
     * @var GSC_Payment_Gateway
     */
    private static $instance = null;

    /**
     * Available gateways
     * 
     * @var array
     */
    private $gateways = array();

    /**
     * Active gateway instance
     * 
     * @var GSC_Abstract_Gateway
     */
    private $active_gateway = null;

    /**
     * Get instance of this class
     * 
     * @return GSC_Payment_Gateway
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_gateways();
        $this->load_active_gateway();
    }

    /**
     * Initialize available gateways
     */
    private function init_gateways() {
        $options = get_option('gsc_payment_gateway_options', array());
        $gateway_settings = isset($options['gateways']) ? $options['gateways'] : array();
        
        $this->gateways = array(
            'cashfree' => new GSC_Cashfree_Gateway(isset($gateway_settings['cashfree']) ? $gateway_settings['cashfree'] : array()),
            'razorpay' => new GSC_Razorpay_Gateway(isset($gateway_settings['razorpay']) ? $gateway_settings['razorpay'] : array()),
            'payu' => new GSC_PayU_Gateway(isset($gateway_settings['payu']) ? $gateway_settings['payu'] : array())
        );
    }

    /**
     * Load active gateway from settings
     */
    private function load_active_gateway() {
        $options = get_option('gsc_payment_gateway_options', array());
        $active_gateway_id = isset($options['active_gateway']) ? $options['active_gateway'] : '';
        
        if (!empty($active_gateway_id) && isset($this->gateways[$active_gateway_id])) {
            $this->active_gateway = $this->gateways[$active_gateway_id];
        }
    }

    /**
     * Get active gateway
     * 
     * @return GSC_Abstract_Gateway|null
     */
    public function get_active_gateway() {
        return $this->active_gateway;
    }

    /**
     * Get all available gateways
     * 
     * @return array
     */
    public function get_gateways() {
        return $this->gateways;
    }

    /**
     * Get gateway by ID
     * 
     * @param string $gateway_id
     * @return GSC_Abstract_Gateway|null
     */
    public function get_gateway($gateway_id) {
        return isset($this->gateways[$gateway_id]) ? $this->gateways[$gateway_id] : null;
    }

    /**
     * Verify UPI ID using active gateway
     * 
     * @param string $upi_id
     * @return array Response with status and message
     */
    public function verify_upi($upi_id) {
        if (!$this->active_gateway) {
            return array(
                'success' => false,
                'message' => __('No active payment gateway configured', 'gscwordpress')
            );
        }

        return $this->active_gateway->verify_upi($upi_id);
    }

    /**
     * Process payment using active gateway
     * 
     * @param array $payment_data Payment data including amount and UPI ID
     * @return array Response with status and transaction details
     */
    public function process_payment($payment_data) {
        if (!$this->active_gateway) {
            return array(
                'success' => false,
                'message' => __('No active payment gateway configured', 'gscwordpress')
            );
        }

        return $this->active_gateway->process_payment($payment_data);
    }

    /**
     * Check if mock mode is enabled
     * 
     * @return bool
     */
    public function is_mock_mode() {
        $options = get_option('gsc_payment_gateway_options', array());
        return isset($options['mock_mode']) && $options['mock_mode'];
    }
}
