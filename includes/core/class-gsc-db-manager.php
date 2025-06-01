<?php
/**
 * GSC Database Manager
 *
 * Handles database tables creation, upgrades, and migrations.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSC_DB_Manager {
    /**
     * The single instance of the class
     */
    protected static $_instance = null;

    /**
     * Required database tables
     */
    protected $tables = array(
        'gsc_payment_batches',
        'gsc_payment_batch_items'
    );

    /**
     * Main Instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize database on plugin activation
        register_activation_hook(GSC_PLUGIN_FILE, array($this, 'install'));
        
        // Check for database updates when admin loads
        add_action('admin_init', array($this, 'check_version'));
    }

    /**
     * Check database version and update if needed
     */
    public function check_version() {
        $current_version = get_option('gsc_db_version');
        
        if ($current_version != GSC_VERSION) {
            $this->install();
        }
    }

    /**
     * Install database tables
     */
    public function install() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Payment Batches table
        $table = $wpdb->prefix . 'gsc_payment_batches';
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            total_referrals int(11) NOT NULL DEFAULT 0,
            processed_referrals int(11) NOT NULL DEFAULT 0,
            successful_payouts int(11) NOT NULL DEFAULT 0,
            failed_payouts int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Payment Batch Items table
        $table = $wpdb->prefix . 'gsc_payment_batch_items';
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id bigint(20) unsigned NOT NULL,
            referral_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            transaction_id varchar(255) DEFAULT NULL,
            transaction_data text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY batch_id (batch_id),
            KEY referral_id (referral_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Update database version
        update_option('gsc_db_version', GSC_VERSION);
    }

    /**
     * Check if a table exists
     *
     * @param string $table Table name without prefix
     * @return bool
     */
    public function table_exists($table) {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }

    /**
     * Get a list of missing tables
     *
     * @return array Missing tables
     */
    public function get_missing_tables() {
        $missing = array();
        
        foreach ($this->tables as $table) {
            if (!$this->table_exists($table)) {
                $missing[] = $table;
            }
        }
        
        return $missing;
    }

    /**
     * Clear all plugin tables
     * 
     * @return bool Success or failure
     */
    public function clear_tables() {
        global $wpdb;
        
        foreach ($this->tables as $table) {
            $table_name = $wpdb->prefix . $table;
            
            if ($this->table_exists($table)) {
                $wpdb->query("TRUNCATE TABLE {$table_name}");
            }
        }
        
        return true;
    }

    /**
     * Drop all plugin tables
     * 
     * @return bool Success or failure
     */
    public function drop_tables() {
        global $wpdb;
        
        foreach ($this->tables as $table) {
            $table_name = $wpdb->prefix . $table;
            
            if ($this->table_exists($table)) {
                $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
            }
        }
        
        delete_option('gsc_db_version');
        
        return true;
    }
}
