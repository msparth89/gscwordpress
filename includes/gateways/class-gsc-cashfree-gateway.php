<?php
/**
 * Cashfree Payment Gateway Class
 * 
 * Handles integration with Cashfree API for UPI payments
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once dirname(__FILE__) . '/class-gsc-abstract-gateway.php';

class GSC_Cashfree_Gateway extends GSC_Abstract_Gateway {
    /**
     * Get settings fields
     * 
     * @return array
     */
    public function get_settings_fields() {
        return array_merge(
            parent::get_settings_fields(),
            array(
                'test_mode' => array(
                    'label' => __('Test Mode', 'gscwordpress'),
                    'type' => 'checkbox',
                    'description' => __('Enable test mode to use Cashfree test API endpoints', 'gscwordpress')
                )
            )
        );
    }

    /**
     * Gateway ID
     * 
     * @var string
     */
    protected $id = 'cashfree';
    
    /**
     * Gateway name
     * 
     * @var string
     */
    protected $name = 'Cashfree';
    
    /**
     * API endpoints
     */
    const API_BASE_PROD = 'https://api.cashfree.com/api/v2';
    const API_BASE_TEST = 'https://test.cashfree.com/api/v2';
    
    /**
     * Get API base URL based on mode
     * 
     * @return string
     */
    private function get_api_base() {
        return isset($this->settings['test_mode']) && $this->settings['test_mode'] ? self::API_BASE_TEST : self::API_BASE_PROD;
    }
    
    /**
     * Get API key
     * 
     * @return string
     */
    private function get_api_key() {
        return isset($this->settings['api_key']) ? $this->settings['api_key'] : '';
    }
    
    /**
     * Get API secret
     * 
     * @return string
     */
    private function get_api_secret() {
        return isset($this->settings['api_secret']) ? $this->settings['api_secret'] : '';
    }
    
    /**
     * Verify UPI ID
     * 
     * @param string $upi_id UPI ID to verify
     * @return bool|array True if verified, array with error details if failed
     */
    public function verify_upi($upi_id) {
        if (empty($upi_id)) {
            return array('error' => 'UPI ID is empty');
        }
        
        // Check for test mode first
        if ($this->is_test_mode()) {
            $mock_response = $this->get_mock_response($upi_id);
            if ($mock_response !== false) {
                return $mock_response;
            }
        }
        
        $api_key = $this->get_api_key();
        $api_secret = $this->get_api_secret();
        
        if (empty($api_key) || empty($api_secret)) {
            return array('error' => 'API credentials not configured');
        }
        
        $url = $this->get_api_base() . '/upi/validate';
        
        $response = wp_remote_post($url, array(
            'method'    => 'POST',
            'timeout'   => 45,
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'X-Client-Id'   => $api_key,
                'X-Client-Secret' => $api_secret,
            ),
            'body'      => json_encode(array(
                'upiId' => $upi_id
            )),
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'gateway' => 'Cashfree',
                'error' => $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // For testing - log the response
        error_log('Cashfree VPA Validation Response: ' . print_r($body, true));
        
        // Check verification status
        if (isset($body['status']) && $body['status'] === 'OK') {
            return array(
                'success' => true,
                'gateway' => 'Cashfree',
                'response' => $body,
                'account_name' => isset($body['accountHolder']) ? $body['accountHolder'] : ''
            );
        }
        
        return array(
            'success' => false,
            'gateway' => 'Cashfree',
            'error' => isset($body['message']) ? $body['message'] : 'UPI verification failed',
            'response' => $body
        );
    }
    
    /**
     * Process payout to UPI ID
     * 
     * @param string $upi_id UPI ID to send payment to
     * @param float $amount Amount to pay
     * @param string $reference Reference ID for the transaction
     * @return array Transaction details or error
     */
    public function process_payout($upi_id, $amount, $reference) {
        if (empty($upi_id)) {
            return array('error' => 'UPI ID is empty');
        }
        
        $api_key = $this->get_api_key();
        $api_secret = $this->get_api_secret();
        
        if (empty($api_key) || empty($api_secret)) {
            return array('error' => 'API credentials not configured');
        }
        
        $url = $this->get_api_base() . '/payout';
        
        $response = wp_remote_post($url, array(
            'method'    => 'POST',
            'timeout'   => 45,
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'X-Client-Id'   => $api_key,
                'X-Client-Secret' => $api_secret,
            ),
            'body'      => json_encode(array(
                'upiId'         => $upi_id,
                'amount'        => $amount,
                'transferId'    => $reference,
                'transferMode'  => 'UPI',
                'remarks'       => 'Commission payout for ' . $reference
            )),
        ));
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['status']) && $body['status'] === 'SUCCESS') {
            return array(
                'success'   => true,
                'payout_id' => isset($body['data']['referenceId']) ? $body['data']['referenceId'] : $reference,
                'status'    => 'success',
                'message'   => 'Payout initiated successfully'
            );
        }
        
        return array(
            'success'   => false,
            'error'     => isset($body['message']) ? $body['message'] : 'Payout failed'
        );
    }
    
    /**
     * Check payout status
     * 
     * @param string $payout_id Payout ID to check
     * @return array Status details
     */
    public function check_payout_status($payout_id) {
        $api_key = $this->get_api_key();
        $api_secret = $this->get_api_secret();
        
        if (empty($api_key) || empty($api_secret)) {
            return array('error' => 'API credentials not configured');
        }
        
        $url = $this->get_api_base() . '/payout/status?transferId=' . urlencode($payout_id);
        
        $response = wp_remote_get($url, array(
            'timeout'   => 45,
            'headers'   => array(
                'X-Client-Id'   => $api_key,
                'X-Client-Secret' => $api_secret,
            )
        ));
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['status']) && $body['status'] === 'SUCCESS') {
            return array(
                'success'   => true,
                'payout_id' => $payout_id,
                'status'    => isset($body['data']['status']) ? strtolower($body['data']['status']) : 'unknown',
                'amount'    => isset($body['data']['amount']) ? $body['data']['amount'] : 0,
                'upi_id'    => isset($body['data']['upiId']) ? $body['data']['upiId'] : '',
                'message'   => 'Payout status retrieved successfully'
            );
        }
        
        return array(
            'success'   => false,
            'error'     => isset($body['message']) ? $body['message'] : 'Failed to retrieve payout status'
        );
    }
}
