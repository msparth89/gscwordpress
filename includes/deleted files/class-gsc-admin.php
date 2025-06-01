<?php
/**
 * GSC Admin Class
 * 
 * Handles admin interface and settings pages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSCAdmin {
    /**
     * The single instance of the class.
     */
    protected static $_instance = null;

    /**
     * Constructor.
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Main GSCAdmin Instance.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('GSC WordPress Settings', 'gscwordpress'),
            __('GSC WordPress', 'gscwordpress'),
            'manage_options',
            'gscwordpress',
            array($this, 'main_settings_page'),
            'dashicons-store',
            25
        );

        // Main settings submenu (same as parent)
        add_submenu_page(
            'gscwordpress',
            __('GSC WordPress Settings', 'gscwordpress'),
            __('General Settings', 'gscwordpress'),
            'manage_options',
            'gscwordpress',
            array($this, 'main_settings_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only enqueue on our plugin pages
        if (strpos($hook, 'gscwordpress') === false) {
            return;
        }
        
        // Add any admin CSS or JS here
        wp_enqueue_style(
            'gsc-admin-styles',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css',
            array(),
            '1.0.0'
        );
    }

    /**
     * Main settings page content
     */
    public function main_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap gsc-admin-wrapper">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="gsc-admin-content">
                <div class="gsc-admin-card">
                    <h2><?php _e('Welcome to GSC WordPress Plugin', 'gscwordpress'); ?></h2>
                    <p><?php _e('Use the links below to configure various aspects of the plugin.', 'gscwordpress'); ?></p>
                    
                    <div class="gsc-admin-modules">
                        <div class="gsc-admin-module">
                            <h3><?php _e('Serial Number Management', 'gscwordpress'); ?></h3>
                            <p><?php _e('Manage and validate product serial numbers for orders.', 'gscwordpress'); ?></p>
                            <p><?php _e('This module is automatically integrated with WooCommerce orders.', 'gscwordpress'); ?></p>
                        </div>
                        
                        <div class="gsc-admin-module">
                            <h3><?php _e('Affiliate System', 'gscwordpress'); ?></h3>
                            <p><?php _e('Manage affiliate registration and tracking.', 'gscwordpress'); ?></p>
                            <p><?php _e('Integrated with the Affiliates plugin to track referrals and commissions.', 'gscwordpress'); ?></p>
                        </div>
                        
                        <div class="gsc-admin-module">
                            <h3><?php _e('Payment Gateways', 'gscwordpress'); ?></h3>
                            <p><?php _e('Configure payment gateways for affiliate commission payouts.', 'gscwordpress'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=gsc-payment-gateway'); ?>" class="button button-primary"><?php _e('Configure Payment Gateways', 'gscwordpress'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
