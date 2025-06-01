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
                        <input type="text" id="affiliate-url" value="<?php echo esc_url(add_query_arg($param_name, $affiliate_id, site_url())); ?>" readonly>
                        <br> <button type="button" class="copy-url-btn" onclick="copyAffiliateUrl()">Copy to clipboard</button>
                    </div>
                </div>
                <p>You can also add ?<?php echo esc_html($param_name); ?>=<?php echo esc_html($affiliate_id); ?> to any link on <?php echo esc_html(site_url()); ?> to track referrals from your account.</p>
            <?php else: ?>
                <p class="link-error">Affiliate ID not found. Please contact support.</p>
            <?php endif; ?>
        </div>
        <script>
        function copyAffiliateUrl() {
            var urlInput = document.getElementById('affiliate-url');
            urlInput.select();
            document.execCommand('copy');
            
            var copyBtn = document.querySelector('.copy-url-btn');
            copyBtn.textContent = 'Copied!';
            setTimeout(function() {
                copyBtn.textContent = 'Copy to clipboard';
            }, 2000);
        }
        </script>
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
        }
        .gsc-form-row input {
            width: 100%;
            padding: 8px 12px !important;
            border: 1px solid #8c8f94 !important;
            border-radius: 4px !important;
            line-height: 2 !important;
            min-height: 40px !important;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.07);
            background-color: #fff;
            color: #2c3338;
        }
        .gsc-form-row input:focus {
            border-color: #2271b1 !important;
            box-shadow: 0 0 0 1px #2271b1 !important;
            outline: none;
        }
        .gsc-affiliate-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #c3c4c7;
            padding: 0;
        }
        .gsc-tab-button {
            background: #f0f0f1;
            border: 1px solid #c3c4c7;
            border-bottom: none;
            padding: 0.5rem 1rem;
            font-size: 14px;
            color: #50575e;
            cursor: pointer;
            margin: 0 0.3rem 0 0;
            border-radius: 4px 4px 0 0;
        }
        .gsc-tab-button.active {
            background: #fff !important;
            color: #000 !important;
            font-weight: 600 !important;
            border-bottom-color: #fff !important;
            position: relative;
            z-index: 1;
        }
        .gsc-tab-content {
            padding: 1rem;
            border: 1px solid #c3c4c7;
            border-top: none;
            margin-top: -1px;
        }
        .verify-upi-btn:disabled,
        .save-profile-btn:disabled,
        button:disabled {
            opacity: 0.65 !important;
            cursor: not-allowed !important;
            background-color: #e2e2e2 !important;
            border-color: #ddd !important;
            color: #666 !important;
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
                            <br>
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