<?php
/**
 * Class to handle affiliate functionality
 */
require_once plugin_dir_path(__FILE__) . '../../../affiliates/lib/core/class-affiliates-registration.php';

class GSCAffiliate {
    /**
     * The single instance of the class.
     */
    protected static $_instance = null;

    /**
     * Constructor.
     */
    public function __construct() {
       // error_log('GSCAffiliate: Affiliate class constructor called');
        
        // Hook into user registration
        add_action('user_register', array($this, 'create_affiliate_on_register'));
        
        // Hook into affiliate creation
        add_action('affiliates_register_affiliate', array($this, 'handle_affiliate_creation'), 10, 2);
        
        // error_log('GSCAffiliate: Affiliate hooks set up');
    }

    /**
     * Create affiliate when user registers
     *
     * @param int $user_id The ID of the newly registered user
     */
    public function create_affiliate_on_register($user_id) {
        // error_log('GSCAffiliate: Starting affiliate creation for user ' . $user_id);
        
        if (!class_exists('Affiliates_Registration')) {
            // error_log('GSCAffiliate: ERROR: Affiliate Registration class not loaded');
            return;
        }
        
        $user = get_userdata($user_id);

        if (!$user) {
            // error_log('GSCAffiliate: ERROR: User not found for ID: ' . $user_id);
            return;
        }
        
        // Check if user is already affiliate
        $affiliate_id = get_user_meta($user_id, '_affiliate_id', true);
        if ($affiliate_id) {
            // error_log('GSCAffiliate: User already has affiliate account. Affiliate ID: ' . $affiliate_id);
            return;
        }
        
        // Prepare affiliate data
        $affiliate_data = array(
            'user_id' => $user_id,
            'first_name' => $user->user_nicename,
            'last_name' => $user->display_name,
            'user_email' => $user->user_email,
            'status' => 'active'
        );
        
        // Create new affiliate
        $result = Affiliates_Registration::store_affiliate($user_id, $affiliate_data, 'active');
        
        if ($result) {
            // error_log('GSCAffiliate: SUCCESS: Affiliate created successfully. Affiliate ID: ' . $result);
        } else {
            // error_log('GSCAffiliate: ERROR: Failed to create affiliate');
        }
    }

    /**
     * Handle affiliate creation
     *
     * @param int $affiliate_id The ID of the new affiliate
     * @param array $affiliate_data The affiliate data
     */
    public function handle_affiliate_creation($affiliate_id, $affiliate_data) {
        // error_log('GSCAffiliate: Affiliate created. ID: ' . $affiliate_id);
        // error_log('GSCAffiliate: Affiliate data: ' . print_r($affiliate_data, true));
        
        // Add any additional affiliate setup here
    }

    /**
     * Get the single instance of the class.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    
}
