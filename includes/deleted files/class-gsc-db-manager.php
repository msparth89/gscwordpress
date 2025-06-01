<?php
/**
 * Database Manager Class
 * 
 * Handles database table creation and updates
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSC_DB_Manager {
    /**
     * Create required database tables
     */
    public static function create_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Payment Batches table
        $table_batches = $wpdb->prefix . 'gsc_payment_batches';
        $sql_batches = "CREATE TABLE IF NOT EXISTS $table_batches (
            batch_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            gateway VARCHAR(50) NOT NULL,
            transaction_id VARCHAR(255),
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_items INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            notes TEXT,
            PRIMARY KEY (batch_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Payment Items table
        $table_items = $wpdb->prefix . 'gsc_payment_items';
        $sql_items = "CREATE TABLE IF NOT EXISTS $table_items (
            item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id BIGINT UNSIGNED NOT NULL,
            referral_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            upi_id VARCHAR(255) NOT NULL,
            account_name VARCHAR(255),
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            gateway_response TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (item_id),
            KEY batch_id (batch_id),
            KEY referral_id (referral_id),
            KEY status (status),
            FOREIGN KEY (batch_id) REFERENCES ${table_batches}(batch_id) ON DELETE CASCADE
        ) $charset_collate;";

        // Create/update tables
        dbDelta($sql_batches);
        dbDelta($sql_items);
    }

    /**
     * Get table names
     * 
     * @return array Array of table names
     */
    public static function get_table_names() {
        global $wpdb;
        return array(
            'batches' => $wpdb->prefix . 'gsc_payment_batches',
            'items' => $wpdb->prefix . 'gsc_payment_items'
        );
    }
}
