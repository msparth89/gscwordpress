<?php
// error_log('GSCWordPress: TOP OF FILE');
/**
 * Plugin Name: GSC WordPress
 * Plugin URI: https://github.com/msparth89/gscwordpress
 * Description: A WordPress plugin by GSC
 * Version: 1.0.0
 * Author: GSC
 * Author URI: https://github.com/msparth89
 * Text Domain: gscwordpress
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin version and constants
if (!defined('GSC_VERSION')) {
    define('GSC_VERSION', '1.0.0');
}

// Define plugin file constant for use in other classes
if (!defined('GSC_PLUGIN_FILE')) {
    define('GSC_PLUGIN_FILE', __FILE__);
}

// Core classes
require_once plugin_dir_path(__FILE__) . 'includes/core/class-gsc-order.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/class-gsc-affiliate.php';
require_once plugin_dir_path(__FILE__) . 'includes/user/class-gsc-frontend-affiliate.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/class-gsc-qr-router.php';

// Payment Gateway classes
require_once plugin_dir_path(__FILE__) . 'includes/gateways/class-gsc-abstract-gateway.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/class-gsc-payment-gateway-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/gateways/class-gsc-cashfree-gateway.php';
require_once plugin_dir_path(__FILE__) . 'includes/gateways/class-gsc-razorpay-gateway.php';
require_once plugin_dir_path(__FILE__) . 'includes/gateways/class-gsc-payu-gateway.php';

// Core classes like GSC_DB_Manager and GSC_Payment_Batch are still required.
require_once plugin_dir_path(__FILE__) . 'includes/core/class-gsc-db-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/class-gsc-payment-batch.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/class-gsc-helper.php';

// Load admin UI class
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/admin/class-gsc-admin.php';
}

// The affiliate profile shortcode is registered in the GSCAffiliteProfile class

// Helper to set serial validation error flag for current user
if (!function_exists('gsc_set_sn_error_flag')) {
    function gsc_set_sn_error_flag() {
        $user_id = get_current_user_id();
        if ($user_id) {
            update_user_meta($user_id, '_gsc_sn_error_flag', 1);
        }
    }
}


/**
 * The core plugin class.
 */
class GSCWordPress {
    /**
     * Initialize the plugin.
     */
    public function __construct() {
        // Add activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Add deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add initialization hooks
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Add text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }

    /**
     * Load the plugin text domain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'gscwordpress',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    /**
     * Plugin activation hook.
     */
    public function activate() {
        // error_log('GSCWordPress: Plugin activated');
        // error_log('GSCWordPress: Activation time: ' . current_time('mysql'));
        
        // Create database tables
        GSC_DB_Manager::create_tables();
        
        // Add any activation code here
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation hook.
     */
    public function deactivate() {
        // error_log('GSCWordPress: Plugin deactivated');
        // error_log('GSCWordPress: Deactivation time: ' . current_time('mysql'));
        
        // Add any deactivation code here
        flush_rewrite_rules();
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        // error_log('GSCWordPress: Plugin initialized');
        
        // Initialize payment batch manager
        GSC_Payment_Batch::instance();
        
        // Initialize admin components
        if (is_admin()) {
            // Admin Menu UI removed.
        }
    }

    /**
     * Initialize admin functionality.
     */
    public function admin_init() {
        // error_log('GSCWordPress: Admin functionality initialized');
        
        // Initialize payment gateway (only needed in admin)
        GSC_Payment_Gateway::instance();
    }

    /**
     * Enqueue scripts for the frontend.
     */
    public function enqueue_scripts() {
        // error_log('GSCWordPress: Enqueuing frontend scripts');
        
        // Add any frontend scripts here
    }

    /**
     * Enqueue scripts for the admin.
     */
    public function admin_enqueue_scripts() {
        // Admin UI scripts removed.
    }
}

// Initialize the plugin
global $gscwordpress;
$gscwordpress = new GSCWordPress();

// Remove 'Order updated.' notice if serial validation failed for this user
add_action('admin_head', function() {
    if (!is_admin()) return;
    $user_id = get_current_user_id();
    if ($user_id && get_user_meta($user_id, '_gsc_sn_error_flag', true)) {
        global $woocommerce, $wp_filter;
        // Remove WooCommerce success notice for 'Order updated.'
        if (isset($GLOBALS['wc_admin_notices'])) {
            // Old WooCommerce
            unset($GLOBALS['wc_admin_notices']['order_updated']);
        }
        if (function_exists('wc_get_notices')) {
            $notices = wc_get_notices('success');
            if ($notices) {
                foreach ($notices as $key => $notice) {
                    if (is_array($notice) && isset($notice['notice']) && strpos($notice['notice'], 'Order updated.') !== false) {
                        wc_clear_notices(); // Remove all success notices (or selectively remove this one if needed)
                        break;
                    }
                }
            }
        }
        // Remove WordPress admin notice
        global $wp_filter;
        if (isset($wp_filter['admin_notices'])) {
            // This is a bit hacky, but can be used to filter out the notice
        }
        // Clear the flag
        delete_user_meta($user_id, '_gsc_sn_error_flag');
    }
});

// Debug: Log all hooks on admin order edit page
add_action('all', function($hook) {
    if (is_admin() && isset($_GET['post'])) {
        // error_log('HOOK FIRED: ' . $hook);
    }
});

// Ensure GSCOrderModular is initialized early
add_action('plugins_loaded', function() {
    // error_log('GSCWordPress: Checking GSCOrderModular class existence');
    if (class_exists('GSCOrderModular')) {
        // error_log('GSCWordPress: GSCOrderModular class exists, initializing instance');
        GSCOrderModular::instance();
        // error_log('GSCWordPress: GSCOrderModular initialized on plugins_loaded');
    } else {
        error_log('GSCWordPress: ERROR - GSCOrderModular class does not exist!');
    }
});
