<?php
/**
 * GSC Helper Functions
 *
 * Helper functions for the GSC WordPress plugin.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Check if UPI verification mock mode is enabled
 *
 * @return bool
 */
function gsc_is_upi_verification_mock_mode() {
    $options = get_option('gsc_payment_gateway_options', array());
    return isset($options['mock_mode']) && $options['mock_mode'] == 1;
}

/**
 * Get active payment gateway ID
 *
 * @return string
 */
function gsc_get_active_gateway_id() {
    $options = get_option('gsc_payment_gateway_options', array());
    return isset($options['active_gateway']) ? $options['active_gateway'] : 'cashfree';
}

/**
 * Get payment gateway settings
 *
 * @param string $gateway_id
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function gsc_get_gateway_setting($gateway_id, $key, $default = '') {
    $options = get_option('gsc_payment_gateway_options', array());
    
    if (isset($options['gateways'][$gateway_id][$key])) {
        return $options['gateways'][$gateway_id][$key];
    }
    
    return $default;
}
