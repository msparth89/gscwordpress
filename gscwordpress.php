<?php
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
        error_log('GSCWordPress: Plugin activated');
        error_log('GSCWordPress: Activation time: ' . current_time('mysql'));
        
        // Add any activation code here
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation hook.
     */
    public function deactivate() {
        error_log('GSCWordPress: Plugin deactivated');
        error_log('GSCWordPress: Deactivation time: ' . current_time('mysql'));
        
        // Add any deactivation code here
        flush_rewrite_rules();
    }

    /**
     * Initialize the plugin.
     */
    public function init() {
        error_log('GSCWordPress: Plugin initialized');
        
        // Include affiliate class
        require_once plugin_dir_path(__FILE__) . 'includes/class-gsc-affiliate.php';
        
        // Initialize affiliate functionality
        $this->affiliate = GSCAffiliate::instance();
        error_log('GSCWordPress: Affiliate functionality initialized');
    }

    /**
     * Initialize admin functionality.
     */
    public function admin_init() {
        error_log('GSCWordPress: Admin functionality initialized');
        
        // Add any admin initialization code here
    }

    /**
     * Enqueue scripts for the frontend.
     */
    public function enqueue_scripts() {
        error_log('GSCWordPress: Enqueuing frontend scripts');
        
        // Add any frontend scripts here
    }

    /**
     * Enqueue scripts for the admin.
     */
    public function admin_enqueue_scripts() {
        error_log('GSCWordPress: Enqueuing admin scripts');
        
        // Add any admin scripts here
    }
}

// Initialize the plugin
global $gscwordpress;
$gscwordpress = new GSCWordPress();
