<?php
/**
 * GSC QR Router: Handles QR code URL routing and affiliate redirections
 * 
 * This class manages the routing of QR code scans to the appropriate product pages
 * while ensuring proper affiliate attribution to the original purchaser.
 * 
 * URL Format: http://site.com/?p=GGGGGGGGGGSSSSSSSSSS
 * where:
 * - GGGGGGGGGG is the 10-digit GTIN/barcode
 * - SSSSSSSSSS is the 10-digit serial number
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSC_QR_Router {
    /** @var GSC_QR_Router Single instance of this class */
    private static $instance = null;

    /** @var string Meta key for storing sold serials */
    const META_SOLD = 'gsc_sn_sold';

    /** @var string Meta key for storing product GTIN */
    const META_GTIN = '_global_unique_id';

    /**
     * Main GSC_QR_Router Instance
     * 
     * Ensures only one instance of GSC_QR_Router is loaded or can be loaded.
     * 
     * @return GSC_QR_Router - Main instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Hook into WordPress init for URL handling
        // Priority 20 to ensure WooCommerce is loaded
        add_action('init', [$this, 'handle_qr_request'], 20);

        // Debug log on initialization
        error_log('GSC_QR_Router: Initialized');
    }

    /**
     * Main handler for QR code requests
     * Processes URLs with the 'p' parameter containing GTIN and serial
     */
    public function handle_qr_request() {
        // Only process if 'p' parameter exists
        if (!isset($_GET['p'])) {
            return;
        }

        error_log('GSC_QR_Router: Processing QR request with p=' . $_GET['p']);

        // Basic sanitization
        $param = sanitize_text_field($_GET['p']);
        
        // Validate format (must be exactly 20 digits)
        if (!preg_match('/^\d{20}$/', $param)) {
            error_log('GSC_QR_Router: Invalid parameter format: ' . $param);
            $this->handle_error('invalid_format');
            return;
        }

        // Extract GTIN and serial
        $gtin = substr($param, 0, 10);
        $serial = substr($param, 10, 10);
        error_log("GSC_QR_Router: Extracted GTIN=$gtin, Serial=$serial");

        // Find original order and purchaser
        $order_info = $this->find_order_by_serial($gtin, $serial);
        if (!$order_info) {
            error_log('GSC_QR_Router: No order found for GTIN=' . $gtin . ' Serial=' . $serial);
            $this->handle_error('order_not_found');
            return;
        }

        // Get affiliate ID for the purchaser
        $affiliate_id = $this->get_affiliate_id($order_info['user_id']);
        if (!$affiliate_id) {
            error_log('GSC_QR_Router: No affiliate ID found for user ' . $order_info['user_id']);
            // Don't show error, just redirect to product without affiliate param
        }

        // Find product by GTIN
        $product_url = $this->get_product_url_by_gtin($gtin);
        if (!$product_url) {
            error_log('GSC_QR_Router: No product found for GTIN ' . $gtin);
            $this->handle_error('product_not_found');
            return;
        }

        // Build and redirect to affiliate URL
        $redirect_url = $this->build_affiliate_url($product_url, $affiliate_id);
        error_log('GSC_QR_Router: Redirecting to ' . $redirect_url);
        
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Find order containing the given serial number
     * 
     * @param string $gtin Product GTIN/barcode
     * @param string $serial Serial number
     * @return array|false Order info array or false if not found
     */
    private function find_order_by_serial($gtin, $serial) {
        global $wpdb;
        
        // Full serial to search for
        $full_serial = $gtin . $serial;
        
        error_log('GSC_QR_Router: Searching for full serial: ' . $full_serial);
        
        // Query orders with meta containing this serial
        $orders = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s
            AND meta_value LIKE %s
        ", self::META_SOLD, '%' . $wpdb->esc_like($full_serial) . '%'));

        foreach ($orders as $order) {
            // Get order object
            $wc_order = wc_get_order($order->post_id);
            if (!$wc_order) {
                error_log('GSC_QR_Router: Invalid order object for ID ' . $order->post_id);
                continue;
            }

            error_log('GSC_QR_Router: Found order #' . $order->post_id . ' for serial');
            return [
                'order_id' => $order->post_id,
                'user_id' => $wc_order->get_user_id()
            ];
        }

        return false;
    }

    /**
     * Get affiliate ID for a user
     * 
     * @param int $user_id WordPress user ID
     * @return int|false Affiliate ID or false if not found
     */
    private function get_affiliate_id($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT affiliate_id 
            FROM {$wpdb->prefix}aff_affiliates_users 
            WHERE user_id = %d
        ", $user_id));
    }

    /**
     * Get product URL by GTIN
     * 
     * @param string $gtin Product GTIN/barcode
     * @return string|false Product URL or false if not found
     */
    private function get_product_url_by_gtin($gtin) {
        global $wpdb;
        
        // Get product ID by GTIN (global_unique_id)
        $product_id = $wpdb->get_var($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND pm.meta_key = %s 
            AND pm.meta_value = %s
        ", self::META_GTIN, $gtin));

        if (!$product_id) {
            return false;
        }

        return get_permalink($product_id);
    }

    /**
     * Build affiliate URL with proper parameter
     * 
     * @param string $product_url Base product URL
     * @param int|false $affiliate_id Affiliate ID (optional)
     * @return string Complete URL with affiliate parameter if applicable
     */
    private function build_affiliate_url($product_url, $affiliate_id = false) {
        if (!$affiliate_id) {
            return $product_url;
        }

        $param_name = get_option('aff_pname', 'aff');
        return add_query_arg($param_name, $affiliate_id, $product_url);
    }

    /**
     * Handle routing errors
     * 
     * @param string $error_type Type of error encountered
     */
    private function handle_error($error_type) {
        $home_url = home_url();
        
        switch ($error_type) {
            case 'invalid_format':
                wp_redirect($home_url);
                break;
                
            case 'order_not_found':
            case 'product_not_found':
                // Could redirect to a specific error page or show WC notice
                wp_redirect($home_url);
                break;
                
            default:
                wp_redirect($home_url);
        }
        
        exit;
    }
}
