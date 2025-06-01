<?php
/**
 * PayU Payment Gateway Class
 * 
 * Handles integration with PayU API for UPI payments
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once dirname(__FILE__) . '/class-gsc-abstract-gateway.php';

class GSC_PayU_Gateway extends GSC_Abstract_Gateway {
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
                    'description' => __('Enable test mode to use PayU test API endpoints', 'gscwordpress')
                )
            )
        );
    }

    /**
     * Gateway ID
     * 
     * @var string
     */
    protected $id = 'payu';
    
    /**
     * Gateway name
     * 
     * @var string
     */
    protected $name = 'PayU';
    
    /**
     * API endpoints
     */
    const API_BASE_PROD = 'https://www.payumoney.com/merchant-dashboard';
    const API_BASE_TEST = 'https://test.payumoney.com/merchant-dashboard';
    
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
        
        // Use PayU's VPA validation API
        $url = $this->get_api_base() . '/merchant/postservice?form=2';
        
        // Generate hash
        $hash_str = $api_key . '|validateVPA|' . $upi_id . '|' . $api_secret;
        $hash = hash('sha512', $hash_str);
        
        $response = wp_remote_post($url, array(
            'method'    => 'POST',
            'timeout'   => 45,
            'headers'   => array(
                'accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body'      => array(
                'key' => $api_key,
                'command' => 'validateVPA',
                'var1' => $upi_id,
                'hash' => $hash
            )
        ));
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // For testing - log the response
        error_log('PayU VPA Validation Response: ' . print_r($body, true));
        
        if (isset($body['status']) && $body['status'] === 'SUCCESS' && isset($body['isVPAValid']) && $body['isVPAValid'] == 1) {
            return array(
                'success' => true,
                'gateway' => 'PayU',
                'response' => $body,
                'account_name' => isset($body['payerAccountName']) ? $body['payerAccountName'] : ''
            );
        }
        
        return array(
            'success' => false,
            'gateway' => 'PayU',
            'error' => 'UPI ID verification failed',
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
        
        $url = $this->get_api_base() . '/payment/api/v1/payout';
        
        // Generate checksum
        $data = array(
            'key'           => $api_key,
            'txnid'         => $reference,
            'amount'        => $amount,
            'productinfo'   => 'Commission payout',
            'firstname'     => 'Affiliate',
            'email'         => 'affiliate@example.com', // Placeholder
            'udf1'          => $upi_id,
            'udf2'          => 'UPI',
        );
        
        $checksum_str = $api_key . '|' . $data['txnid'] . '|' . $data['amount'] . '|' . $data['productinfo'] . '|' . $data['firstname'] . '|' . $data['email'] . '|' . $data['udf1'] . '|' . $data['udf2'] . '||||||||||' . $api_secret;
        $data['hash'] = hash('sha512', $checksum_str);
        
        $response = wp_remote_post($url, array(
            'method'    => 'POST',
            'timeout'   => 45,
            'headers'   => array(
                'Content-Type'  => 'application/json',
            ),
            'body'      => json_encode($data),
        ));
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['status']) && $body['status'] === 'success') {
            return array(
                'success'   => true,
                'payout_id' => isset($body['payoutId']) ? $body['payoutId'] : $reference,
                'status'    => 'processing',
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
        
        $url = $this->get_api_base() . '/payment/api/v1/payout/status';
        
        // Generate checksum
        $data = array(
            'key'       => $api_key,
            'payoutId'  => $payout_id,
        );
        
        $checksum_str = $api_key . '|' . $data['payoutId'] . '|' . $api_secret;
        $data['hash'] = hash('sha512', $checksum_str);
        
        $response = wp_remote_post($url, array(
            'method'    => 'POST',
            'timeout'   => 45,
            'headers'   => array(
                'Content-Type'  => 'application/json',
            ),
            'body'      => json_encode($data),
        ));
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['status']) && $body['status'] === 'success') {
            return array(
                'success'   => true,
                'payout_id' => $payout_id,
                'status'    => isset($body['payoutStatus']) ? strtolower($body['payoutStatus']) : 'unknown',
                'amount'    => isset($body['amount']) ? $body['amount'] : 0,
                'upi_id'    => isset($body['upiId']) ? $body['upiId'] : '',
                'message'   => 'Payout status retrieved successfully'
            );
        }
        
        return array(
            'success'   => false,
            'error'     => isset($body['message']) ? $body['message'] : 'Failed to retrieve payout status'
        );
    }
}
