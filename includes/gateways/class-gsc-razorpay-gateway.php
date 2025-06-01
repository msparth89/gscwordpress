<?php
/**
 * RazorPay Payment Gateway Class
 * 
 * Handles integration with RazorPay API for UPI payments
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once dirname(__FILE__) . '/class-gsc-abstract-gateway.php';

class GSC_Razorpay_Gateway extends GSC_Abstract_Gateway {
    /**
     * Gateway ID
     * 
     * @var string
     */
    protected $id = 'razorpay';
    
    /**
     * Gateway name
     * 
     * @var string
     */
    protected $name = 'RazorPay';
    
    /**
     * API endpoints
     */
    const API_BASE = 'https://api.razorpay.com/v1';
    
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
        
        // Use RazorPay's VPA validation API
        $url = self::API_BASE . '/payments/validate/vpa';
        
        $response = wp_remote_post($url, array(
            'method'    => 'POST',
            'timeout'   => 45,
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_secret),
            ),
            'body'      => json_encode(array(
                'vpa' => $upi_id
            )),
        ));
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // For testing - log the response
        error_log('Razorpay VPA Validation Response: ' . print_r($body, true));
        
        // Check if the VPA is valid
        if (isset($body['success']) && $body['success']) {
            return array(
                'success' => true,
                'gateway' => 'Razorpay',
                'response' => $body
            );
        }
        
        return array(
            'success' => false,
            'gateway' => 'Razorpay',
            'error' => isset($body['error']['description']) ? $body['error']['description'] : 'UPI verification failed',
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
        
        // Step 1: Create a contact
        $contact_url = self::API_BASE . '/contacts';
        $contact_response = wp_remote_post($contact_url, array(
            'method'    => 'POST',
            'timeout'   => 45,
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_secret),
            ),
            'body'      => json_encode(array(
                'name'  => 'Affiliate ' . $reference,
                'type'  => 'customer',
                'reference_id' => $reference
            )),
        ));
        
        if (is_wp_error($contact_response)) {
            return array('error' => $contact_response->get_error_message());
        }
        
        $contact_body = json_decode(wp_remote_retrieve_body($contact_response), true);
        
        if (!isset($contact_body['id'])) {
            return array('error' => isset($contact_body['error']['description']) ? $contact_body['error']['description'] : 'Failed to create contact');
        }
        
        $contact_id = $contact_body['id'];
        
        // Step 2: Create a fund account
        $fund_account_url = self::API_BASE . '/fund_accounts';
        $fund_account_response = wp_remote_post($fund_account_url, array(
            'method'    => 'POST',
            'timeout'   => 45,
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_secret),
            ),
            'body'      => json_encode(array(
                'contact_id'    => $contact_id,
                'account_type'  => 'vpa',
                'vpa'           => array(
                    'address'   => $upi_id
                )
            )),
        ));
        
        if (is_wp_error($fund_account_response)) {
            return array('error' => $fund_account_response->get_error_message());
        }
        
        $fund_account_body = json_decode(wp_remote_retrieve_body($fund_account_response), true);
        
        if (!isset($fund_account_body['id'])) {
            return array('error' => isset($fund_account_body['error']['description']) ? $fund_account_body['error']['description'] : 'Failed to create fund account');
        }
        
        $fund_account_id = $fund_account_body['id'];
        
        // Step 3: Create a payout
        $payout_url = self::API_BASE . '/payouts';
        $payout_response = wp_remote_post($payout_url, array(
            'method'    => 'POST',
            'timeout'   => 45,
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_secret),
            ),
            'body'      => json_encode(array(
                'account_number' => $api_key, // Your RazorPay account number
                'fund_account_id' => $fund_account_id,
                'amount'        => $amount * 100, // RazorPay uses amount in paise
                'currency'      => 'INR',
                'mode'          => 'UPI',
                'purpose'       => 'commission',
                'reference_id'  => $reference,
                'narration'     => 'Commission payout for ' . $reference
            )),
        ));
        
        if (is_wp_error($payout_response)) {
            return array('error' => $payout_response->get_error_message());
        }
        
        $payout_body = json_decode(wp_remote_retrieve_body($payout_response), true);
        
        if (isset($payout_body['id'])) {
            return array(
                'success'   => true,
                'payout_id' => $payout_body['id'],
                'status'    => isset($payout_body['status']) ? strtolower($payout_body['status']) : 'processing',
                'message'   => 'Payout initiated successfully'
            );
        }
        
        return array(
            'success'   => false,
            'error'     => isset($payout_body['error']['description']) ? $payout_body['error']['description'] : 'Payout failed'
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
        
        $url = self::API_BASE . '/payouts/' . $payout_id;
        
        $response = wp_remote_get($url, array(
            'timeout'   => 45,
            'headers'   => array(
                'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_secret),
            )
        ));
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['id'])) {
            return array(
                'success'   => true,
                'payout_id' => $payout_id,
                'status'    => isset($body['status']) ? strtolower($body['status']) : 'unknown',
                'amount'    => isset($body['amount']) ? $body['amount'] / 100 : 0, // Convert from paise to rupees
                'upi_id'    => isset($body['vpa']) ? $body['vpa'] : '',
                'message'   => 'Payout status retrieved successfully'
            );
        }
        
        return array(
            'success'   => false,
            'error'     => isset($body['error']['description']) ? $body['error']['description'] : 'Failed to retrieve payout status'
        );
    }
}
