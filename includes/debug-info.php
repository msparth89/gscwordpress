<?php
/**
 * Debug information for the affiliate profile system
 */

// Don't allow direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display debug information if requested
 */
function gsc_display_debug_info() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (!isset($_GET['debug_gsc'])) {
        return;
    }
    
    // Show basic WordPress info
    echo '<div style="background: #f8f8f8; padding: 20px; border: 1px solid #ddd; margin: 20px 0; font-family: monospace;">';
    echo '<h2>WordPress Debug Info</h2>';
    echo '<p>WordPress Version: ' . get_bloginfo('version') . '</p>';
    echo '<p>PHP Version: ' . phpversion() . '</p>';
    echo '<p>Site URL: ' . get_site_url() . '</p>';
    echo '<p>Admin URL: ' . admin_url() . '</p>';
    echo '<p>AJAX URL: ' . admin_url('admin-ajax.php') . '</p>';
    
    // Display user information
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        echo '<h2>Current User</h2>';
        echo '<p>User ID: ' . $user_id . '</p>';
        echo '<p>Username: ' . $user->user_login . '</p>';
        echo '<p>Display Name: ' . $user->display_name . '</p>';
        echo '<p>Email: ' . $user->user_email . '</p>';
    }
    
    // Show active plugins
    echo '<h2>Active Plugins</h2>';
    $active_plugins = get_option('active_plugins');
    echo '<ul>';
    foreach ($active_plugins as $plugin) {
        echo '<li>' . $plugin . '</li>';
    }
    echo '</ul>';
    
    // Show form submission data if any
    if (!empty($_POST)) {
        echo '<h2>POST Data</h2>';
        echo '<pre>';
        print_r($_POST);
        echo '</pre>';
    }
    
    // Show $_SERVER data
    echo '<h2>Server Info</h2>';
    echo '<p>REQUEST_URI: ' . $_SERVER['REQUEST_URI'] . '</p>';
    echo '<p>SCRIPT_NAME: ' . $_SERVER['SCRIPT_NAME'] . '</p>';
    if (isset($_SERVER['HTTP_REFERER'])) {
        echo '<p>HTTP_REFERER: ' . $_SERVER['HTTP_REFERER'] . '</p>';
    }
    
    // Show shortcode info
    echo '<h2>Shortcode Info</h2>';
    global $shortcode_tags;
    if (isset($shortcode_tags['gsc_affiliate_profile'])) {
        echo '<p>gsc_affiliate_profile shortcode is registered.</p>';
    } else {
        echo '<p>gsc_affiliate_profile shortcode is NOT registered.</p>';
    }
    
    // Show database tables
    global $wpdb;
    echo '<h2>Database Tables</h2>';
    $affiliate_tables = [
        $wpdb->prefix . 'aff_affiliates',
        $wpdb->prefix . 'aff_affiliates_users'
    ];
    
    foreach ($affiliate_tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            echo '<p>' . $table . ' exists</p>';
            
            // Count records
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            echo '<p>Records: ' . $count . '</p>';
            
            // Show table structure
            $structure = $wpdb->get_results("DESCRIBE $table");
            echo '<p>Structure:</p>';
            echo '<ul>';
            foreach ($structure as $column) {
                echo '<li>' . $column->Field . ' (' . $column->Type . ')</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . $table . ' does not exist</p>';
        }
    }
    
    echo '</div>';
}

// Add hook to display debug info
add_action('wp_footer', 'gsc_display_debug_info');
