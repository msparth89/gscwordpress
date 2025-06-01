<?php
class GSC_Frontend_Affiliate {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('gsc_affiliate_area', array($this, 'render_affiliate_area'));
        add_shortcode('gsc_affiliate_profile', array($this, 'render_profile_section'));
        add_shortcode('gsc_affiliate_link', array($this, 'render_affiliate_link'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_gsc_verify_upi', array($this, 'ajax_verify_upi'));
        add_action('wp_ajax_gsc_save_profile', array($this, 'ajax_save_profile'));
    }

    /**
     * Render affiliate link section
     * 
     * @return string
     */
    public function render_affiliate_link() {
        if (!is_user_logged_in()) {
            return '<p class="gsc-affiliate-error">Please log in to view your affiliate link.</p>';
        }

        global $wpdb;
        
        // Get affiliate parameter name from wp_options
        $param_name = get_option('aff_pname', 'aff');
        
        // Get affiliate ID from affiliates_users table
        $affiliate_id = $wpdb->get_var($wpdb->prepare(
            "SELECT affiliate_id FROM {$wpdb->prefix}aff_affiliates_users WHERE user_id = %d",
            get_current_user_id()
        ));

        ob_start();
        ?>
        <div class="gsc-affiliate-link-container">
            <h3>Links</h3>
            <?php if ($affiliate_id): ?>
                <div class="gsc-form-row">
                    <label>Your affiliate URL:</label>
                    <div class="affiliate-url-group">
                        <input type="text" id="affiliate-url-shortcode" value="<?php echo esc_url(add_query_arg($param_name, $affiliate_id, site_url())); ?>" readonly>
                        <button type="button" class="copy-url-btn" onclick="copyAffiliateUrl()">Copy to clipboard</button>
                    </div>
                </div>
                <p>You can also add ?<?php echo esc_html($param_name); ?>=<?php echo esc_html($affiliate_id); ?> to any link on <?php echo esc_html(site_url()); ?> to track referrals from your account.</p>
            <?php else: ?>
                <p class="link-error">Affiliate ID not found. Please contact support.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render profile section
     * 
     * @return string
     */
    public function render_profile_section() {
        if (!is_user_logged_in()) {
            return '<p class="gsc-affiliate-error">Please log in to view your profile.</p>';
        }

        $data = $this->get_affiliate_data();
        ob_start();
        ?>
        <!-- <div class="gsc-affiliate-container profile-only">
            <div class="gsc-tab-content profile active"> -->
                <form id="gsc-profile-form" class="gsc-profile-form">
                    <div class="gsc-form-row">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($data['first_name']); ?>" required>
                    </div>

                    <div class="gsc-form-row">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($data['last_name']); ?>" required>
                    </div>

                    <div class="gsc-form-row">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo esc_attr($data['email']); ?>" required>
                    </div>

                    <div class="gsc-form-row">
                        <label for="upi_id">UPI ID</label>
                        <div class="upi-field-group">
                            <input type="text" id="upi_id" name="upi_id" value="<?php echo esc_attr($data['upi_id']); ?>" required>
                            <button type="button" id="verify-upi" class="verify-upi-btn">Verify UPI</button>
                        </div>
                        <div id="upi-verification-status"></div>
                        <div id="upi-confirmation" style="display: none;" class="upi-confirmation">
                            <div class="account-name"></div>
                            <div class="checkbox-field">
                                <input type="checkbox" id="confirm_upi" name="confirm_upi">
                                <label for="confirm_upi">I confirm this is my UPI ID</label>
                            </div>
                        </div>
                        <input type="hidden" id="verified_account_name" name="verified_account_name" value="">
                    </div>

                    <div class="gsc-form-row save-row">
                        <button type="submit" class="save-profile-btn" <?php echo ($data['upi_id'] ? '' : 'disabled'); ?>>Save Changes</button>
                        <span class="save-status"></span>
                    </div>
                </form>
            <!-- </div>
        </div> -->
                        <!-- <form id="gsc-profile-form" class="gsc-profile-form">
                    <div class="gsc-form-row">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($data['first_name']); ?>" required>
                    </div>

                    <div class="gsc-form-row">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($data['last_name']); ?>" required>
                    </div>

                    <div class="gsc-form-row">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo esc_attr($data['email']); ?>" required>
                    </div>

                    <div class="gsc-form-row">
                        <label for="upi_id">UPI ID</label>
                        <div class="upi-field-group">
                            <input type="text" id="upi_id" name="upi_id" value="<?php echo esc_attr($data['upi_id']); ?>" required>
                            <button type="button" id="verify-upi" class="verify-upi-btn">Verify UPI</button>
                        </div>
                        <div id="upi-verification-status"></div>
                        <div id="upi-confirmation" style="display: none;" class="upi-confirmation">
                            <div class="account-name"></div>
                            <div class="gsc-form-row checkbox-row">
                                <div class="checkbox-field">
                                    <input type="checkbox" id="confirm_upi" name="confirm_upi">
                                    <label for="confirm_upi">I confirm this is my UPI ID</label>
                                </div>
                            </div>
                            <input type="hidden" id="verified_account_name" name="verified_account_name" value="">
                        </div>
                    </div>

                    <div class="gsc-form-row save-row">
                        <button type="submit" class="save-profile-btn" <?php echo ($data['upi_id'] ? '' : 'disabled'); ?>>Save Changes</button>
                        <span class="save-status"></span>
                    </div>
                </form> -->
        <?php
        return ob_get_clean();
    }

    public function enqueue_scripts() {
        // Force reload of CSS and JS on every page load while developing
        $cache_buster = time();
        
        // REMOVED: header() calls for cache control, as they can cause issues here.
        // Rely on $cache_buster for enqueueing.
        
        // Get the URL to the directory of the current file
        $current_dir_url = plugin_dir_url( __FILE__ ); // Ends with /wp-content/plugins/gscwordpress/includes/user/

        wp_enqueue_style(
            'gsc-affiliate-style',
            $current_dir_url . '../../assets/css/affiliate.css', // Navigate up two levels to plugin root, then to assets
            array(),
            $cache_buster
        );

        wp_enqueue_script(
            'gsc-affiliate-script',
            $current_dir_url . '../../assets/js/affiliate.js', // Navigate up two levels to plugin root, then to assets
            array('jquery'),
            $cache_buster,
            true
        );

        // TEMPORARILY COMMENTED OUT: Inline CSS and JS to isolate issues
        /*
        wp_add_inline_style('gsc-affiliate-style', '
            .gsc-tab-button.active {
                background-color: white !important;
                border-bottom: 3px solid #2271b1 !important;
                color: #2271b1 !important;
                font-weight: bold !important;
            }
            .verify-upi-btn:disabled, 
            .save-profile-btn:disabled {
                opacity: 0.65 !important;
                cursor: not-allowed !important;
                background-color: #e2e2e2 !important;
                border-color: #ddd !important;
                color: #666 !important;
            }
            .gsc-form-row input {
                border: 1px solid #8c8f94 !important;
                border-radius: 4px !important;
                padding: 8px 12px !important;
                box-shadow: inset 0 1px 2px rgba(0,0,0,0.07) !important;
            }
        ');
        
        wp_add_inline_script('gsc-affiliate-script', '
            jQuery(document).ready(function($) {
                // Force disable buttons immediately
                $("#verify-upi").prop("disabled", true);
                $("#save-profile").prop("disabled", true);
                
                // Store original UPI value
                var originalUpi = $("#upi_id").val();
                $("#upi_id").data("original", originalUpi);
            });
        ', 'before');
        */

        wp_localize_script('gsc-affiliate-script', 'gscAffiliateData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gsc_affiliate_nonce'),
            'cache_buster' => $cache_buster
        ));
    }

    private function get_affiliate_data() {
        $user_id = get_current_user_id();
        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);
        $upi_id = get_user_meta($user_id, 'upi_id', true);
        $upi_verified = get_user_meta($user_id, 'upi_verified', true);
        $verified_account_name = get_user_meta($user_id, 'verified_account_name', true);
        
        global $wpdb;
        $affiliate = $wpdb->get_row($wpdb->prepare(
            "SELECT a.* FROM {$wpdb->prefix}aff_affiliates a 
            JOIN {$wpdb->prefix}aff_affiliates_users au ON a.affiliate_id = au.affiliate_id 
            WHERE au.user_id = %d",
            $user_id
        ));
        
        return array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $affiliate ? $affiliate->email : '',
            'upi_id' => $upi_id,
            'upi_verified' => $upi_verified ? true : false,
            'verified_account_name' => $verified_account_name
        );
    }

    public function render_affiliate_area() {
        if (!is_user_logged_in()) {
            return '<p class="gsc-affiliate-error">Please log in to view your affiliate area.</p>';
        }

        $data = $this->get_affiliate_data();

        global $wpdb; // Ensure $wpdb is in scope
        // Get affiliate parameter name from wp_options
        $param_name = get_option('aff_pname', 'aff');
        
        // Get affiliate ID from affiliates_users table
        $affiliate_id = $wpdb->get_var($wpdb->prepare(
            "SELECT affiliate_id FROM {$wpdb->prefix}aff_affiliates_users WHERE user_id = %d",
            get_current_user_id()
        ));
        
        ob_start();
        ?>
        <!-- Inline styles to fix appearance issues -->
        <style>
    .gsc-affiliate-container {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        background-color: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    /* Tabs Styling */
    .gsc-affiliate-tabs {
        display: flex;
        margin-bottom: 0; /* Remove bottom margin to connect with content */
        border-bottom: 1px solid #ddd;
    }
    .gsc-tab-button {
        background: #e9ecef;
        border: 1px solid #ddd;
        border-bottom: none;
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 500;
        color: #495057;
        cursor: pointer;
        border-radius: 6px 6px 0 0;
        margin-right: 5px;
        transition: background-color 0.2s ease, color 0.2s ease;
        position: relative;
        top: 1px; /* Align with the content border */
    }
    .gsc-tab-button:hover {
        background-color: #dee2e6;
        color: #343a40;
    }
    .gsc-tab-button.active {
        background-color: #fff;
        color: #007bff;
        font-weight: 600;
        border-color: #ddd;
        border-bottom: 1px solid #fff; /* Make it look connected to content */
        z-index: 2;
    }

    /* Tab Content Styling */
    .gsc-tab-content {
        background-color: #fff;
        padding: 25px;
        border: 1px solid #ddd;
        border-top: none; /* Tabs provide the top border appearance */
        border-radius: 0 0 8px 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    /* Form Elements Styling */
    .gsc-profile-form .gsc-form-row {
        margin-bottom: 20px;
    }
    .gsc-profile-form label {
        display: block;
        font-weight: 500;
        margin-bottom: 8px;
        color: #343a40;
        font-size: 14px;
    }
    .gsc-profile-form input[type="text"],
    .gsc-profile-form input[type="email"] {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        box-sizing: border-box;
    }
    .gsc-profile-form input[type="text"]:focus,
    .gsc-profile-form input[type="email"]:focus {
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }


    .gsc-name-fields-group {
        display: flex;
        gap: 20px; /* Space between the two fields */
        margin-bottom: 20px; /* Keep original bottom margin for the group */
    }
    .gsc-name-fields-group .gsc-form-row {
        flex: 1; /* Each field takes equal width */
        margin-bottom: 0; /* Remove individual bottom margin as group has it */
    }

    /* UPI Field Group */
    .upi-field-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .upi-field-group input[type="text"] {
        flex-grow: 1;
    }

    /* Buttons Styling */
    .verify-upi-btn, .save-profile-btn, .copy-url-btn {
        padding: 10px 18px;
        font-size: 14px;
        font-weight: 500;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    .verify-upi-btn {
        background-color: #20c997; /* Teal color */
        color: white;
        border-color: #20c997;
    }
    .verify-upi-btn:hover {
        background-color: #1baa80; /* Darker teal on hover */
        border-color: #199d75;
    }
    .save-profile-btn {
        background-color: #007bff;
        color: white;
        border-color: #007bff;
    }
    .save-profile-btn:hover {
        background-color: #0069d9;
        border-color: #0062cc;
    }
    .copy-url-btn {
        background-color: #28a745;
        color: white;
        border-color: #28a745;
        display: inline-block; /* Align with input text */
        margin-top: 5px; /* Space from input */
    }
    .copy-url-btn:hover {
        background-color: #218838;
        border-color: #1e7e34;
    }

    /* Disabled Button Styling */
    .verify-upi-btn:disabled,
    .save-profile-btn:disabled,
    button:disabled {
        background-color: #e9ecef !important;
        border-color: #ced4da !important;
        color: #6c757d !important;
        opacity: 0.65 !important;
        cursor: not-allowed !important;
    }

    /* Checkbox Styling */
    #upi-confirm-row {
        display: flex;
        align-items: center;
        margin-top: 15px;
        padding: 10px;
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 4px;
    }
    #upi-confirm-row input[type="checkbox"] {
        margin-right: 10px;
        width: 18px; /* Custom size */
        height: 18px; /* Custom size */
        cursor: pointer;
    }
    #upi-confirm-row label {
        margin-bottom: 0; /* Override default label margin */
        font-weight: normal;
        color: #495057;
    }

    /* Status Messages */
    #upi-verification-status, .save-status {
        margin-top: 10px;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 14px;
    }
    #upi-verification-status.success, .save-status.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    #upi-verification-status.error, .save-status.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    #upi-verification-status.info, .save-status.info {
        background-color: #cce5ff;
        color: #004085;
        border: 1px solid #b8daff;
    }

    /* Verified Account Name Display */
    .upi-confirmation p.account-name {
        margin-top: 10px;
        padding: 8px 12px;
        background-color: #e2f0fb;
        border: 1px solid #b8daff;
        border-radius: 4px;
        color: #0c5460;
        font-size: 14px;
    }
    .upi-confirmation p.account-name strong {
        font-weight: 600;
    }

    /* Affiliate URL section in Profile Tab */
    .gsc-tab-content .gsc-form-row .affiliate-url-group input[type="text"] {
        margin-bottom: 5px; /* Space before copy button */
    }

    .link-error {
        color: #721c24;
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        padding: 10px;
        border-radius: 4px;
    }
    </style>
        <div class="gsc-affiliate-container">
            <nav class="gsc-affiliate-tabs">
                <button class="gsc-tab-button active" data-tab="overview">Overview</button>
                <button class="gsc-tab-button" data-tab="earnings">Earnings</button>
                <button class="gsc-tab-button" data-tab="profile">Profile</button>
            </nav>

            <div class="gsc-tab-content active" id="gsc-tab-overview">
                <!-- Overview content here -->
                <p>Welcome to your affiliate dashboard. Here you can track your referrals and earnings.</p>
                <!-- Additional overview content can be added here -->
            </div>

            <div class="gsc-tab-content" id="gsc-tab-earnings">
                <!-- Earnings content here -->
                <p>Your commission history and earnings details will appear here.</p>
                <!-- Additional earnings content can be added here -->
            </div>

            <div class="gsc-tab-content" id="gsc-tab-profile">
                <form id="gsc-profile-form" class="gsc-profile-form">
                    <?php wp_nonce_field('gsc_affiliate_nonce', 'gsc_profile_nonce'); ?>
                    
                    <div class="gsc-name-fields-group">
                        <div class="gsc-form-row">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($data['first_name']); ?>" required>
                        </div>
                        <div class="gsc-form-row">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($data['last_name']); ?>" required>
                        </div>
                    </div>

                    <div class="gsc-form-row">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo esc_attr($data['email']); ?>" required>
                    </div>

                    <div class="gsc-form-row">
                        <label for="upi_id">UPI ID</label>
                        <div class="upi-field-group">
                            <input type="text" id="upi_id" name="upi_id" value="<?php echo esc_attr($data['upi_id']); ?>" placeholder="yourname@upi">
                            <button type="button" id="verify-upi" class="verify-upi-btn" disabled>Verify UPI</button>
                        </div>
                        <div id="upi-verification-status"></div>
                        
                        <?php if ($data['upi_verified'] && $data['verified_account_name']): ?>
                        <div class="upi-confirmation" id="existing-verification">
                            <p class="account-name">Verified Account: <strong><?php echo esc_html($data['verified_account_name']); ?></strong></p>
                        </div>
                        <?php endif; ?>
                        
                        <div id="upi-confirm-row" class="checkbox-field checkbox-row" style="display: none;">
                            <input type="checkbox" id="confirm_upi" name="confirm_upi">
                            <label for="confirm_upi">I confirm this is my correct UPI ID</label>
                        </div>
                        <input type="hidden" id="verified_account_name" name="verified_account_name" value="<?php echo esc_attr($data['verified_account_name']); ?>">
                    </div>

                    <div class="gsc-form-row gsc-form-actions">
                        <button type="button" id="save-profile" class="button button-primary save-profile-btn" disabled><?php esc_html_e('Save Changes', 'gscwordpress'); ?></button>
                        <div class="save-status" style="display:inline-block; margin-left: 10px;"></div>
                    </div>
                    <?php if ($affiliate_id): ?>
                    <div class="gsc-form-row">
                        <label>Your affiliate URL:</label>
                        <div class="affiliate-url-group">
                            <input type="text" id="affiliate-url" value="<?php echo esc_url(add_query_arg($param_name, $affiliate_id, site_url())); ?>" readonly>
                            <button type="button" class="copy-url-btn" onclick="copyAffiliateUrl()">Copy to clipboard</button>
                        </div>
                    </div>
                    <p>You can also add ?<?php echo esc_html($param_name); ?>=<?php echo esc_html($affiliate_id); ?> to any link on <?php echo esc_html(site_url()); ?> to track referrals from your account.</p>
                    <?php else: ?>
                    <p class="link-error">Affiliate ID not found. Please contact support.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_verify_upi() {
        check_ajax_referer('gsc_affiliate_nonce', 'nonce');
        
        $upi_id = sanitize_text_field($_POST['upi_id']);
        if (empty($upi_id)) {
            wp_send_json_error('UPI ID is required');
        }

        // Get the payment gateway instance
        $gateway = GSC_Payment_Gateway::instance();
        $active_gateway = $gateway->get_active_gateway();
        
        if (!$active_gateway) {
            wp_send_json_error('No payment gateway is currently enabled in admin settings');
        }

        // Verify UPI using the active gateway
        $result = $active_gateway->verify_upi($upi_id);
        
        // For testing - format the response nicely
        $response_info = array(
            'gateway' => $result['gateway'],
            'success' => $result['success']
        );
        
        if ($result['success']) {
            if (isset($result['account_name'])) {
                $response_info['account_name'] = $result['account_name'];
            }
            wp_send_json_success($response_info);
        } else {
            $response_info['error'] = $result['error'];
            wp_send_json_error($response_info);
        }
    }

    public function ajax_save_profile() {
        check_ajax_referer('gsc_affiliate_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $upi_id = sanitize_text_field($_POST['upi_id']);
        $confirm_upi = isset($_POST['confirm_upi']) ? (bool)$_POST['confirm_upi'] : false;
        $verified_account_name = sanitize_text_field($_POST['verified_account_name']);

        // Verify UPI confirmation if UPI is changed
        $current_upi = get_user_meta($user_id, 'upi_id', true);
        if ($current_upi !== $upi_id) {
            // New or changed UPI ID - require verification
            if (!$confirm_upi || empty($verified_account_name)) {
                wp_send_json_error('Please verify your UPI ID and confirm it is correct');
            }
            $upi_verified = true;
        } else {
            // UPI unchanged - keep existing verification status
            $upi_verified = get_user_meta($user_id, 'upi_verified', true);
            $verified_account_name = get_user_meta($user_id, 'verified_account_name', true);
        }

        // Update user meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'upi_id', $upi_id);
        update_user_meta($user_id, 'upi_verified', $upi_verified);
        update_user_meta($user_id, 'verified_account_name', $verified_account_name);

        // Update affiliate table
        global $wpdb;
        
        // Get affiliate_id for the user
        $affiliate_id = $wpdb->get_var($wpdb->prepare(
            "SELECT affiliate_id FROM {$wpdb->prefix}aff_affiliates_users WHERE user_id = %d",
            $user_id
        ));
        
        if ($affiliate_id) {
            $wpdb->update(
                $wpdb->prefix . 'aff_affiliates',
                array(
                    'name' => $first_name . ' ' . $last_name,
                    'email' => $email
                ),
                array('affiliate_id' => $affiliate_id),
                array('%s', '%s'),
                array('%d')
            );
        }

        wp_send_json_success('Profile updated successfully');
    }
}

// Initialize the class
GSC_Frontend_Affiliate::get_instance();