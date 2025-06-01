<?php
/**
 * GSC Admin Payment Settings Class
 * 
 * Handles payment gateway settings configuration
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSC_Admin_Payment_Settings {
    /**
     * Settings group
     */
    const SETTINGS_GROUP = 'gsc_payment_gateway_options';
    
    /**
     * Settings option name
     */
    const SETTINGS_OPTION = 'gsc_payment_gateway_options';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register the settings
     */
    public function register_settings() {
        register_setting(
            self::SETTINGS_GROUP,
            self::SETTINGS_OPTION,
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Sanitize settings before saving
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize active gateway
        if (isset($input['active_gateway'])) {
            $sanitized['active_gateway'] = sanitize_text_field($input['active_gateway']);
        }
        
        // Sanitize mock mode
        $sanitized['mock_mode'] = isset($input['mock_mode']) ? 1 : 0;
        
        // Sanitize gateway settings
        if (isset($input['gateways']) && is_array($input['gateways'])) {
            $sanitized['gateways'] = array();
            
            foreach ($input['gateways'] as $gateway_id => $gateway_data) {
                $sanitized['gateways'][$gateway_id] = array();
                
                if (isset($gateway_data['api_key'])) {
                    $sanitized['gateways'][$gateway_id]['api_key'] = sanitize_text_field($gateway_data['api_key']);
                }
                
                if (isset($gateway_data['api_secret'])) {
                    $sanitized['gateways'][$gateway_id]['api_secret'] = sanitize_text_field($gateway_data['api_secret']);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Render the payment gateway settings
     */
    public function render() {
        // Get gateway manager instance
        $gateway_manager = GSC_Payment_Gateway::instance();
        
        // Get available gateways
        $gateways = $gateway_manager->get_gateways();
        
        // Get saved options
        $options = get_option(self::SETTINGS_OPTION, array());
        
        // Get active gateway
        $active_gateway = isset($options['active_gateway']) ? $options['active_gateway'] : 'cashfree';
        
        // Get mock mode setting
        $mock_mode = isset($options['mock_mode']) ? $options['mock_mode'] : 0;
        
        ?>
        <div class="gsc-payment-settings-wrapper">
            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>
                
                <div class="gsc-settings-section">
                    <h2><?php echo esc_html__('Payment Gateway Settings', 'gscwordpress'); ?></h2>
                    <p><?php echo esc_html__('Configure your payment gateways for UPI verification and payouts.', 'gscwordpress'); ?></p>
                    
                    <table class="form-table gsc-payment-gateways-table">
                        <thead>
                            <tr>
                                <th class="gsc-radio-col"><?php echo esc_html__('Active', 'gscwordpress'); ?></th>
                                <th><?php echo esc_html__('Gateway', 'gscwordpress'); ?></th>
                                <th><?php echo esc_html__('API Key', 'gscwordpress'); ?></th>
                                <th><?php echo esc_html__('API Secret', 'gscwordpress'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gateways as $gateway_id => $gateway) : ?>
                                <tr>
                                    <td class="gsc-radio-col">
                                        <input type="radio" 
                                               id="gateway_<?php echo esc_attr($gateway_id); ?>" 
                                               name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[active_gateway]" 
                                               value="<?php echo esc_attr($gateway_id); ?>" 
                                               <?php checked($active_gateway, $gateway_id); ?> />
                                    </td>
                                    <td>
                                        <label for="gateway_<?php echo esc_attr($gateway_id); ?>">
                                            <?php echo esc_html($gateway->get_title()); ?>
                                        </label>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               class="regular-text" 
                                               name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[gateways][<?php echo esc_attr($gateway_id); ?>][api_key]" 
                                               value="<?php echo esc_attr($this->get_gateway_option($options, $gateway_id, 'api_key')); ?>" 
                                               placeholder="<?php echo esc_attr__('API Key', 'gscwordpress'); ?>" />
                                    </td>
                                    <td>
                                        <input type="password" 
                                               class="regular-text" 
                                               name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[gateways][<?php echo esc_attr($gateway_id); ?>][api_secret]" 
                                               value="<?php echo esc_attr($this->get_gateway_option($options, $gateway_id, 'api_secret')); ?>" 
                                               placeholder="<?php echo esc_attr__('API Secret', 'gscwordpress'); ?>" />
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="gsc-settings-section">
                    <h3><?php echo esc_html__('Global Settings', 'gscwordpress'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php echo esc_html__('Mock Mode', 'gscwordpress'); ?>
                            </th>
                            <td>
                                <label for="gsc_mock_mode">
                                    <input type="checkbox" 
                                           id="gsc_mock_mode" 
                                           name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[mock_mode]" 
                                           value="1" 
                                           <?php checked($mock_mode, 1); ?> />
                                    <?php echo esc_html__('Enable mock mode for UPI verification (for testing purposes)', 'gscwordpress'); ?>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__('When enabled, UPI verification will use mock responses instead of connecting to payment gateways.', 'gscwordpress'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Helper to get gateway option
     */
    private function get_gateway_option($options, $gateway_id, $key) {
        if (isset($options['gateways'][$gateway_id][$key])) {
            return $options['gateways'][$gateway_id][$key];
        }
        return '';
    }
}
