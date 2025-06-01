<?php
/**
 * Abstract Payment Gateway Class
 * 
 * Defines the interface that all payment gateways must implement
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

abstract class GSC_Abstract_Gateway {
    /**
     * Check if gateway is in test mode
     * 
     * @return bool
     */
    protected function is_test_mode() {
        $options = get_option('gsc_payment_gateway_options', array());
        $global_mock_mode = isset($options['mock_mode']) && $options['mock_mode'];
        $gateway_test_mode = isset($this->settings['test_mode']) && $this->settings['test_mode'];
        
        error_log('Global mock mode: ' . ($global_mock_mode ? 'true' : 'false'));
        error_log('Gateway test mode: ' . ($gateway_test_mode ? 'true' : 'false'));
        
        return $global_mock_mode || $gateway_test_mode;
    }
    
    /**
     * Get mock response for UPI verification
     * 
     * @param string $upi_id UPI ID to test
     * @return array Mock response
     */
    protected function get_mock_response($upi_id) {
        error_log('Mock response for UPI ID: ' . $upi_id);
        if (strpos($upi_id, '@success.upi') !== false) {
            error_log('success.upi ' . $upi_id);
            return array(
                'success' => true,
                'gateway' => $this->get_name(),
                'response' => array('status' => 'SUCCESS'),
                'account_name' => 'Test Account ' . substr($upi_id, 0, strpos($upi_id, '@'))
            );
        }
        
        if (strpos($upi_id, '@fail.upi') !== false) {
            error_log('fail.upi ' . $upi_id);
            return array(
                'success' => false,
                'gateway' => $this->get_name(),
                'error' => 'Invalid UPI ID (mock response)',
                'response' => array('status' => 'FAILURE')
            );
        }
        
        // For any other test UPI, return success without account name
        if (strpos($upi_id, '@test.upi') !== false) {
            return array(
                'success' => true,
                'gateway' => $this->get_name(),
                'response' => array('status' => 'SUCCESS')
            );
        }
        
        return false; // Not a test UPI, proceed with real API call
    }
    /**
     * Gateway ID
     * 
     * @var string
     */
    protected $id;
    
    /**
     * Gateway name
     * 
     * @var string
     */
    protected $name;
    
    /**
     * Gateway settings
     * 
     * @var array
     */
    protected $settings;
    
    /**
     * Constructor
     * 
     * @param array $settings Gateway settings
     */
    public function __construct($settings = array()) {
        $this->settings = $settings;
    }
    
    /**
     * Get gateway ID
     * 
     * @return string
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Get gateway name
     * 
     * @return string
     */
    public function get_name() {
        return $this->name;
    }
    
    /**
     * Get gateway settings
     * 
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Update gateway settings
     * 
     * @param array $settings
     */
    public function update_settings($settings) {
        $this->settings = $settings;
    }

    /**
     * Set API key
     * 
     * @param string $key API Key
     */
    public function set_api_key($key) {
        $this->settings['api_key'] = $key;
    }

    /**
     * Set API secret
     * 
     * @param string $secret API Secret
     */
    public function set_api_secret($secret) {
        $this->settings['api_secret'] = $secret;
    }
    
    /**
     * Verify UPI ID
     * 
     * @param string $upi_id UPI ID to verify
     * @return bool|array True if verified, array with error details if failed
     */
    abstract public function verify_upi($upi_id);
    
    /**
     * Process payout to UPI ID
     * 
     * @param string $upi_id UPI ID to send payment to
     * @param float $amount Amount to pay
     * @param string $reference Reference ID for the transaction
     * @return array Transaction details or error
     */
    abstract public function process_payout($upi_id, $amount, $reference);
    
    /**
     * Check payout status
     * 
     * @param string $payout_id Payout ID to check
     * @return array Status details
     */
    abstract public function check_payout_status($payout_id);

    /**
     * Get gateway title for settings page
     * 
     * @return string
     */
    public function get_title() {
        return $this->name . ' ' . __('Settings', 'gscwordpress');
    }

    /**
     * Get settings fields
     * 
     * @return array
     */
    public function get_settings_fields() {
        return array(
            'api_key' => array(
                'label' => __('API Key', 'gscwordpress'),
                'type' => 'text',
                'required' => true
            ),
            'api_secret' => array(
                'label' => __('API Secret', 'gscwordpress'),
                'type' => 'password',
                'required' => true
            )
        );
    }

    /**
     * Render settings description
     */
    public function render_settings_description() {
        echo '<p>' . sprintf(
            __('Configure your %s integration. Get your API credentials from your %s dashboard.', 'gscwordpress'),
            $this->name,
            $this->name
        ) . '</p>';
    }

    /**
     * Render settings field
     * 
     * @param array $args Field arguments
     */
    public function render_settings_field($args) {
        $field_id = $args['field_id'];
        $fields = $this->get_settings_fields();
        $field = $fields[$field_id];
        $value = isset($this->settings[$field_id]) ? $this->settings[$field_id] : '';
        $type = isset($field['type']) ? $field['type'] : 'text';
        $required = isset($field['required']) && $field['required'];

        printf(
            '<input type="%s" name="gsc_payment_gateway_options[gateways][%s][%s]" value="%s" class="regular-text" %s>',
            esc_attr($type),
            esc_attr($this->id),
            esc_attr($field_id),
            esc_attr($value),
            $required ? 'required' : ''
        );

        if (isset($field['description'])) {
            printf('<p class="description">%s</p>', esc_html($field['description']));
        }
    }
}
