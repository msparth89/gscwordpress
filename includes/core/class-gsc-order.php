<?php
/**
 * GSCOrderModular: Modular WooCommerce order serial number management.
 * This implementation follows a clean, modular approach to handle serial numbers
 * for sold, returned, and replacement items.
 */
class GSCOrderModular {
    // Meta key constants
    const META_SOLD = 'gsc_sn_sold';
    const META_RETURNED = 'gsc_sn_returned';
    const META_ENABLE_RETURNED = 'gsc_sn_enable_returned';
    const META_ENABLE_REPLACEMENT = 'gsc_sn_enable_replacement';
    const META_REPLACEMENT_ORDER_ID = 'gsc_sn_replacement_order_id';
    const META_SKIP_VALIDATION = 'gsc_sn_skip_validation';
    private static $instance = null;

    /**
     * Singleton instance getter
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register hooks
     */
    public function __construct() {
        if (!class_exists('WooCommerce')) return;
        
        // Debug log on initialization
        // error_log('GSCOrderModular: Initializing');
        
        // Render serial numbers UI in admin order
        add_action('woocommerce_admin_order_totals_after_total', [$this, 'render_serials_section']);
        
        // Save serial numbers on order update (classic and HPOS)
        add_action('woocommerce_process_shop_order_meta', [$this, 'handle_order_save'], 99, 1);
        add_action('woocommerce_admin_process_order_object', [$this, 'handle_order_save'], 99, 1);
    }

    /**
     * Render serials section in admin order
     */
    public function render_serials_section($order) {
        // Convert order param to WC_Order object if needed
        $order_id = is_object($order) ? $order->get_id() : $order;
        $wc_order = is_object($order) ? $order : wc_get_order($order_id);
        
        // Validate we have a valid order
        if (!$wc_order || !is_a($wc_order, 'WC_Order')) {
            error_log('GSCOrderModular: Invalid order in render_serials_section');
            return;
        }
        
        error_log('GSCOrderModular: Rendering serials section for order #' . $wc_order->get_id());
        
        // IMPORTANT: Always get fresh meta values directly from the database 
        // This ensures we show the most current data after saving
        $wc_order = wc_get_order($order_id); // Reload to ensure fresh data
        $saved_serials = $wc_order->get_meta(self::META_SOLD);
        error_log('GSCOrderModular: Loaded serials from DB: ' . substr($saved_serials, 0, 50));
        
        // Only use POST data if we're coming from a form submission, otherwise use saved data
        $serials_value = (isset($_POST['gsc_sn_sold']) && isset($_POST['gsc_sn_nonce'])) ? $_POST['gsc_sn_sold'] : $saved_serials;
        if (is_array($serials_value)) {
            $serials_value = implode("\n", $serials_value);
        }
        
        // Check for validation errors (from various sources)
        $errors = [];
        
        // 1. Check URL parameter
        if (isset($_GET['gsc_sn_error'])) {
            $errors[] = urldecode($_GET['gsc_sn_error']);
        }
        
        // 2. Check for stored errors in transient
        $user_id = get_current_user_id();
        if ($user_id) {
            $transient_errors = get_transient('gsc_sn_errors_' . $user_id);
            if ($transient_errors) {
                $errors = array_merge($errors, $transient_errors);
                delete_transient('gsc_sn_errors_' . $user_id); // Clean up after using
            }
            
            // Check for error flag in user meta
            $error_flag = get_user_meta($user_id, '_gsc_sn_error_flag', true);
            if ($error_flag) {
                delete_user_meta($user_id, '_gsc_sn_error_flag'); // Clean up
            }
        }
        
        // 3. Check WC session for errors
        if (function_exists('WC') && isset(WC()->session)) {
            $session_errors = WC()->session->get('gsc_sn_errors');
            if ($session_errors) {
                $errors = array_merge($errors, $session_errors);
                WC()->session->set('gsc_sn_errors', null); // Clean up
            }
        }
        
        // Display errors if we found any
        if (!empty($errors)) {
            echo '<div id="gsc-serial-errors" class="notice notice-error" style="margin:15px 0; padding:12px; border-left:4px solid #dc3232; background-color:#fbeaea;">';
            echo '<p><strong>Serial Number Validation Failed:</strong></p>';
            echo '<ul style="margin-left: 20px; list-style-type: disc;">';
            foreach ($errors as $error) {
                echo '<li>' . esc_html(ltrim($error, '• ')) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
            error_log('GSCOrderModular: Displaying ' . count($errors) . ' validation errors');
        }
        
        // Get stored returned serials
        $saved_returned_serials = $wc_order->get_meta(self::META_RETURNED);
        $returned_serials_value = (isset($_POST['gsc_sn_returned']) && isset($_POST['gsc_sn_nonce'])) ? $_POST['gsc_sn_returned'] : $saved_returned_serials;
        if (is_array($returned_serials_value)) {
            $returned_serials_value = implode("\n", $returned_serials_value);
        }
        
        // Check if returned items checkbox was checked
        $returned_items_enabled = isset($_POST['gsc_sn_enable_returned']) ? true : (bool)$wc_order->get_meta(self::META_ENABLE_RETURNED);
        
        // Render the serial numbers section
        echo '<div class="gsc-serial-number-section" style="margin:30px 0; padding:20px; border:1px solid #ddd; background:#f9f9f9;">';
        echo '<h3>Serial Numbers</h3>';
    
    // Skip validation checkbox
    $skip_validation = (bool)$wc_order->get_meta(self::META_SKIP_VALIDATION);
    echo '<div style="margin-bottom:18px;">';
    echo '<div style="display:flex; align-items:center; margin-bottom:12px;">';
    echo '<input type="checkbox" id="gsc_skip_validation" name="gsc_skip_validation" ' . ($skip_validation ? 'checked' : '') . ' style="margin-right:8px;">';
    echo '<label for="gsc_skip_validation"><strong>Skip Serial Validation</strong></label>';
    echo '</div>';
    echo '<div style="margin-left:24px; font-style:italic; color:#666; font-size:12px;">Enable this option to bypass serial number validation. Use only when tracking serials is not required.</div>';
    echo '</div>';
        
        // Sold Items Section
        echo '<div style="margin-bottom:18px;">';
        echo '<strong>Sold Items</strong>';
        echo '<div style="margin-top:8px;">';
        echo '<textarea id="gsc_sn_sold" name="gsc_sn_sold" placeholder="Scan or enter serial numbers, one per line" rows="3" style="width:100%;">' . esc_textarea($serials_value) . '</textarea>';
        echo '</div>';
        echo '</div>';
        
        // Returned Items Section with checkbox
        echo '<div style="margin-bottom:18px;">';
        echo '<div style="display:flex; align-items:center; margin-bottom:8px;">';
        echo '<input type="checkbox" id="gsc_sn_enable_returned" name="gsc_sn_enable_returned" ' . ($returned_items_enabled ? 'checked' : '') . ' style="margin-right:8px;">';
        echo '<label for="gsc_sn_enable_returned"><strong>Returned Items</strong></label>';
        echo '</div>';
        echo '<div id="gsc_sn_returned_container" style="margin-top:8px; ' . (!$returned_items_enabled ? 'display:none;' : '') . '">';
        echo '<textarea id="gsc_sn_returned" name="gsc_sn_returned" placeholder="Scan or enter returned serial numbers, one per line" rows="3" style="width:100%;">' . esc_textarea($returned_serials_value) . '</textarea>';
        echo '</div>';
        echo '</div>';
        
        // Replacement Section
        // Get stored replacement order ID and check if replacement items checkbox was checked
        $replacement_order_id = $wc_order->get_meta(self::META_REPLACEMENT_ORDER_ID);
        $replacement_items_enabled = isset($_POST['gsc_sn_enable_replacement']) ? true : (bool)$wc_order->get_meta(self::META_ENABLE_REPLACEMENT);
        
        echo '<div style="margin-bottom:18px;">';
        echo '<div style="display:flex; align-items:center; margin-bottom:8px;">';
        echo '<input type="checkbox" id="gsc_sn_enable_replacement" name="gsc_sn_enable_replacement" ' . ($replacement_items_enabled ? 'checked' : '') . ' style="margin-right:8px;">';
        echo '<label for="gsc_sn_enable_replacement"><strong>Replacement Items</strong></label>';
        echo '</div>';
        echo '<div id="gsc_sn_replacement_container" style="margin-top:8px; ' . (!$replacement_items_enabled ? 'display:none;' : '') . '">';
        echo '<input type="text" id="gsc_sn_replacement_order_id" name="gsc_sn_replacement_order_id" value="' . esc_attr($replacement_order_id) . '" placeholder="Enter the order number that contains the returned items being replaced" style="width:100%;">';
        echo '</div>';
        echo '</div>';
        
        // Add JavaScript for toggle functionality
        echo '<script>
            jQuery(document).ready(function($) {
                $("#gsc_sn_enable_returned").change(function() {
                    if($(this).is(":checked")) {
                        $("#gsc_sn_returned_container").show();
                        // Disable replacement section
                        $("#gsc_sn_enable_replacement").prop("checked", false).trigger("change");
                    } else {
                        $("#gsc_sn_returned_container").hide();
                    }
                });
                
                $("#gsc_sn_enable_replacement").change(function() {
                    if($(this).is(":checked")) {
                        $("#gsc_sn_replacement_container").show();
                        // Disable returned section
                        $("#gsc_sn_enable_returned").prop("checked", false).trigger("change");
                    } else {
                        $("#gsc_sn_replacement_container").hide();
                    }
                });
            });
        </script>';
        
        echo '<input type="hidden" name="gsc_sn_nonce" value="' . esc_attr(wp_create_nonce('gsc_sn_save')) . '">';
        echo '</div>';
    }

    /**
     * Handle order save
     */
    public function handle_order_save($order) {
        // Convert order param to WC_Order object if needed
        $order_id = is_object($order) ? $order->get_id() : $order;
        $wc_order = is_object($order) ? $order : wc_get_order($order_id);
        
        // Validate order object
        if (!$wc_order || !is_a($wc_order, 'WC_Order')) return;
        
        // Debug logging
        error_log("GSCOrderModular: Processing order save for #" . $order_id);
        
        // Verify nonce
        if (!isset($_POST['gsc_sn_nonce']) || !wp_verify_nonce($_POST['gsc_sn_nonce'], 'gsc_sn_save')) {
            error_log("GSCOrderModular: Nonce verification failed");
            return;
        }
        
        // Get and sanitize sold serials
        $serials_raw = isset($_POST['gsc_sn_sold']) ? sanitize_textarea_field($_POST['gsc_sn_sold']) : '';
        error_log("GSCOrderModular: Processing sold serials: " . substr($serials_raw, 0, 50));
        
        // Check if serial validation should be skipped
        $skip_validation = isset($_POST['gsc_skip_validation']);
        $wc_order->update_meta_data(self::META_SKIP_VALIDATION, $skip_validation ? '1' : '0');
        
        // Skip validation if the checkbox is checked
        $errors = [];
        if (!$skip_validation) {
            // Validate sold serials with stricter checks
            $errors = $this->validate_sold_serials($wc_order, $serials_raw);
        } else {
            error_log("GSCOrderModular: Skipping serial validation as requested");
        }
        
        // Handle validation errors for sold items
        if (!empty($errors)) {
            error_log("GSCOrderModular: Validation failed with " . count($errors) . " errors for sold items");
            $this->handle_validation_errors($errors);
            return;
        }
        
        // Save valid sold serials
        $this->save_sold_serials($wc_order, $serials_raw);
        
        // Process returned serials if enabled
        $enable_returned = isset($_POST['gsc_sn_enable_returned']);
        $enable_replacement = isset($_POST['gsc_sn_enable_replacement']);
        
        // Clear any previous flags
        $wc_order->delete_meta_data(self::META_ENABLE_RETURNED);
        $wc_order->delete_meta_data(self::META_ENABLE_REPLACEMENT);
        
        if ($enable_returned) {
            // Returned items mode
            error_log("GSCOrderModular: Processing returned serials mode");
            $returned_serials_raw = isset($_POST['gsc_sn_returned']) ? sanitize_textarea_field($_POST['gsc_sn_returned']) : '';
            
            // Update flag
            $wc_order->update_meta_data(self::META_ENABLE_RETURNED, '1');
            
            // Validate returned serials unless skipping validation
            $skip_validation = isset($_POST['gsc_skip_validation']);
            $returned_errors = [];
            if (!$skip_validation) {
                $returned_errors = $this->validate_returned_serials($wc_order, $returned_serials_raw, $serials_raw);
            } else {
                error_log("GSCOrderModular: Skipping returned serials validation as requested");
            }
            
            // Handle returned validation errors
            if (!empty($returned_errors)) {
                error_log("GSCOrderModular: Validation failed with " . count($returned_errors) . " errors for returned items");
                $this->handle_validation_errors($returned_errors);
                return;
            }
            
            // Save valid returned serials
            $this->save_returned_serials($wc_order, $returned_serials_raw);
            
            // Clear any replacement data (mutual exclusivity)
            $wc_order->delete_meta_data(self::META_REPLACEMENT_ORDER_ID);
            
        } else if ($enable_replacement) {
            // Replacement mode
            error_log("GSCOrderModular: Processing replacement mode");
            $replacement_order_id = isset($_POST['gsc_sn_replacement_order_id']) ? 
                sanitize_text_field($_POST['gsc_sn_replacement_order_id']) : '';
                
            // Update flag
            $wc_order->update_meta_data(self::META_ENABLE_REPLACEMENT, '1');
            
            // Validate replacement order ID unless skipping validation
            $skip_validation = isset($_POST['gsc_skip_validation']);
            $replacement_errors = [];
            if (!$skip_validation) {
                $replacement_errors = $this->validate_replacement_order_id($wc_order, $replacement_order_id, $serials_raw);
            } else {
                error_log("GSCOrderModular: Skipping replacement validation as requested");
            }
            
            // Handle replacement validation errors
            if (!empty($replacement_errors)) {
                error_log("GSCOrderModular: Validation failed with " . count($replacement_errors) . " errors for replacement order");
                $this->handle_validation_errors($replacement_errors);
                return;
            }
            
            // Save valid replacement order ID
            $this->save_replacement_order_id($wc_order, $replacement_order_id);
            
            // Clear any returned data (mutual exclusivity)
            $wc_order->delete_meta_data(self::META_RETURNED);
            
        } else {
            // Neither returns nor replacements are enabled
            error_log("GSCOrderModular: Neither returns nor replacements enabled");
            
            // Clear all return/replacement data
            $wc_order->delete_meta_data(self::META_RETURNED);
            $wc_order->delete_meta_data(self::META_REPLACEMENT_ORDER_ID);
        }
        
        // Save all changes
        $wc_order->save();
        wp_cache_flush(); // Flush WP cache
        error_log("GSCOrderModular: Successfully saved all serials for order #" . $wc_order->get_id());
    }
    
    /**
     * Save sold serials to order meta
     */
    private function save_sold_serials($order, $serials_raw) {
        // Clear any previous meta value first to ensure update takes effect
        $order->delete_meta_data(self::META_SOLD);
        $order->save();
        
        // Add the new value and save again
        $order->update_meta_data(self::META_SOLD, trim($serials_raw));
        $order->save();
        
        // Force WP to flush its meta cache for this order
        clean_post_cache($order->get_id());
    }
    
    /**
     * Save returned serials to order meta
     */
    public function save_returned_serials($order, $serials_raw) {
        // Clear any previous returned serials
        $order->delete_meta_data(self::META_RETURNED);
        
        // Save if we have valid content
        if (!empty(trim($serials_raw))) {
            $order->update_meta_data(self::META_RETURNED, $serials_raw);
        }
        
        // Also set the enabled flag explicitly for clarity
        $order->update_meta_data(self::META_ENABLE_RETURNED, '1');
        $order->save();
    }
    
    /**
     * Save replacement order ID to order meta
     */
    public function save_replacement_order_id($order, $replacement_order_id) {
        // Clear any previous replacement order ID
        $order->delete_meta_data(self::META_REPLACEMENT_ORDER_ID);
        
        // Save if we have valid content
        if (!empty(trim($replacement_order_id))) {
            $order->update_meta_data(self::META_REPLACEMENT_ORDER_ID, $replacement_order_id);
        }
        
        // Also set the enabled flag explicitly for clarity
        $order->update_meta_data(self::META_ENABLE_REPLACEMENT, '1');
        $order->save();
    }
    
    /**
     * Check if serial validation should be skipped for this order
     */
    public function is_validation_skipped($order) {
        return (bool)$order->get_meta(self::META_SKIP_VALIDATION);
    }
    
    /**
     * Validate sold serials
     */
    public function validate_sold_serials($order, $serials_raw) {
        $errors = [];
        $lines = $this->parse_lines($serials_raw);
        
        // Error if no serials but the order has items
        if (empty($lines)) {
            $order_items = $this->get_order_items_barcode_qty($order, $errors);
            if (!empty($order_items)) {
                $errors[] = "No serial numbers provided for order with " . count($order_items) . " items.";
                return $errors;
            }
            return $errors; // Empty is valid only if order has no items
        }
        
        $order_items = $this->get_order_items_barcode_qty($order, $errors);
        $serial_regex = $this->get_serial_regex();
        $serial_map = [];
        $all_serials = [];
        
        error_log('GSCOrderModular: Validating ' . count($lines) . ' serial numbers for order with ' . count($order_items) . ' items');
        
        // First pass: Check format and extract data
        foreach ($lines as $line) {
            // Validate format
            if (!$this->is_valid_serial_format($line, $serial_regex)) {
                $errors[] = "Invalid serial format: $line";
                continue;
            }
            
            // Extract barcode and serial
            list($barcode, $serial) = $this->extract_barcode_and_serial($line, $serial_regex);
            
            // Verify barcode is in order
            if (!$this->barcode_in_order($barcode, $order_items)) {
                $errors[] = "Barcode $barcode not in order.";
                continue;
            }
            
            // Track serials
            if (!isset($serial_map[$barcode])) {
                $serial_map[$barcode] = [];
            }
            $serial_map[$barcode][] = $serial;
            // Use the full URL as the unique identifier instead of just the serial part
            $all_serials[] = $line;
        }
        
        // Second pass: Check for duplicates across all serials (using full URL as unique identifier)
        $unique_serials = array_unique($all_serials);
        if (count($unique_serials) < count($all_serials)) {
            // Find duplicates
            $counts = array_count_values($all_serials);
            foreach ($counts as $full_serial => $count) {
                if ($count > 1) {
                    $errors[] = "Duplicate serial found: $full_serial (used $count times)";
                }
            }
        }
        
        // Third pass: Check quantities match exactly
        foreach ($order_items as $barcode => $qty) {
            $found = isset($serial_map[$barcode]) ? count($serial_map[$barcode]) : 0;
            if ($found != $qty) {
                $errors[] = "Quantity mismatch for product $barcode: need exactly $qty serial(s), found $found.";
            }
        }
        
        return $errors;
    }

    /**
     * Show errors and prevent meta save on validation failure
     */
    private function handle_validation_errors($errors) {
        // Detailed error log with full error list
        error_log('GSCOrderModular: Validation failed with errors: ' . print_r($errors, true));
        
        // Format errors for display with bullet points
        $formatted_errors = [];
        foreach ($errors as $error) {
            $formatted_errors[] = '• ' . $error;
        }
        
        // Store errors in a transient for 30 seconds
        $user_id = get_current_user_id();
        set_transient('gsc_sn_errors_' . $user_id, $formatted_errors, 30);
        
        // Set a flag in user meta to signal that there are errors
        if (function_exists('gsc_set_sn_error_flag')) {
            gsc_set_sn_error_flag();
        } else {
            if ($user_id) {
                update_user_meta($user_id, '_gsc_sn_error_flag', 1);
            }
        }
        
        // Add a filter to the redirect URL to include our error flag
        add_filter('redirect_post_location', function($location) {
            // Remove any success message
            $location = remove_query_arg('message', $location);
            
            // Add our error parameter
            return add_query_arg('gsc_sn_error_flag', '1', $location);
        });
        
        // Force immediate display of errors using WooCommerce notices
        if (function_exists('wc_add_notice')) {
            foreach ($errors as $error) {
                wc_add_notice($error, 'error');
            }
        }
        
        // Show errors immediately using session too
        if (function_exists('WC') && isset(WC()->session)) {
            WC()->session->set('gsc_sn_errors', $errors);
        }
        
        // Display immediately using PHP output buffer
        ob_start();
        echo '<div class="error" id="gsc-serial-errors" style="margin:10px 0; padding:10px; border:2px solid #dc3232; background-color:#fbeaea;">';
        echo '<p><strong>Serial Number Validation Failed:</strong></p>';
        echo '<ul style="margin-left: 20px; list-style-type: disc;">';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
        $error_html = ob_get_clean();
        
        // Add to admin footer to ensure it's displayed
        add_action('admin_footer', function() use ($error_html) {
            echo $error_html;
            echo '<script>
                jQuery(document).ready(function($) {
                    // Move error message to top of meta boxes
                    $("#gsc-serial-errors").insertBefore(".gsc-serial-number-section");
                });
            </script>';
        });
    }

    // --- Helper methods ---
    
    /**
     * Parse raw serials input into an array of lines
     */
    private function parse_lines($serials_raw) {
        return array_filter(array_map('trim', explode("\n", $serials_raw)));
    }
    
    /**
     * Get all order items with barcodes and quantities
     */
    private function get_order_items_barcode_qty($order, &$errors) {
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $barcode = $product->get_data()['global_unique_id'];
            
            if (!$barcode) {
                $errors[] = "Product '{$product->get_name()}' is missing a barcode.";
                continue;
            }
            
            $items[$barcode] = $item->get_quantity();
        }
        return $items;
    }
    
    /**
     * Get regex for validating serial format
     */
    private function get_serial_regex() {
        // Get and log the raw site URL
        $raw_site_url = get_site_url();
        error_log('GSCOrderModular: Raw site URL from get_site_url(): "' . $raw_site_url . '"');
        
        // Process the URL
        $site_url = trim($raw_site_url, '/');
        error_log('GSCOrderModular: Trimmed site URL: "' . $site_url . '"');
        
        // Breaking down URL components
        $url_parts = parse_url($site_url);
        error_log('GSCOrderModular: URL scheme: ' . ($url_parts['scheme'] ?? 'none'));
        error_log('GSCOrderModular: URL host: ' . ($url_parts['host'] ?? 'none'));
        error_log('GSCOrderModular: URL path: ' . ($url_parts['path'] ?? 'none'));
        
        // Create quoted pattern
        $site_url_pattern = preg_quote($site_url, '/');
        error_log('GSCOrderModular: URL pattern after preg_quote: "' . $site_url_pattern . '"');
        
        // Build regex pattern that requires site URL + correct format
        // FIXED: Removed the extra slash since the URL already contains the full path
        $regex = '/^' . $site_url_pattern . '\?p=(\d{10})(\d{10})$/';
        
        // Debug the regex pattern
        error_log('GSCOrderModular: FINAL regex pattern: "' . $regex . '"');
        
        // Log examples of valid and invalid serials for testing
        $example1 = $site_url . '/asiatech?p=12345678901234567890';
        $example2 = 'http://localhost/asiatech?p=12345678901234567890';
        error_log('GSCOrderModular: Example 1 (with site URL): "' . $example1 . '"');
        error_log('GSCOrderModular: Example 2 (hardcoded): "' . $example2 . '"');
        
        // Test both examples with the pattern
        $test1 = preg_match($regex, $example1, $matches1);
        $test2 = preg_match($regex, $example2, $matches2);
        error_log('GSCOrderModular: Pattern matches Example 1? ' . ($test1 ? 'YES' : 'NO'));
        error_log('GSCOrderModular: Pattern matches Example 2? ' . ($test2 ? 'YES' : 'NO'));
        
        // Return final regex
        return $regex;
    }
    
    /**
     * Check if a line matches the serial format
     */
    private function is_valid_serial_format($line, $serial_regex) {
        // Debug the input line being validated
        error_log('GSCOrderModular: Testing serial format: ' . $line);
        
        // Test with preg_match and log the result
        $result = preg_match($serial_regex, $line);
        error_log('GSCOrderModular: Regex match result: ' . ($result ? 'MATCH' : 'NO MATCH'));
        
        // If it doesn't match, provide a fallback for testing
        if (!$result) {
            // Try a very simple pattern as fallback for testing purposes
            $simple_pattern = '/\?p=(\d{10})(\d{10})$/';
            $fallback_result = preg_match($simple_pattern, $line);
            error_log('GSCOrderModular: Fallback regex result: ' . ($fallback_result ? 'MATCH' : 'NO MATCH'));
            
            // If fallback matches but main pattern doesn't, use fallback temporarily
            if ($fallback_result) {
                error_log('GSCOrderModular: Using fallback pattern - site URL may need adjustment');
                return $fallback_result;
            }
        }
        
        return $result;
    }
    
    /**
     * Extract barcode and serial from a line
     * Returns [barcode, serial_part, full_serial]
     * where full_serial is the combined barcode+serial for uniqueness checking
     */
    private function extract_barcode_and_serial($line, $serial_regex) {
        $results = [null, null, null]; // Default values if no match [barcode, serial, full_serial]
        if (preg_match($serial_regex, $line, $m) && isset($m[1]) && isset($m[2])) {
            $barcode = $m[1];
            $serial_part = $m[2];
            $full_serial = $barcode . $serial_part; // Combined for uniqueness
            $results = [$barcode, $serial_part, $full_serial];
            error_log("GSCOrderModular: Successfully extracted barcode {$barcode} and serial {$serial_part}, full: {$full_serial}");
        } else {
            error_log("GSCOrderModular: Failed to extract barcode and serial from: $line");
        }
        return $results;
    }
    
    /**
     * Check if a barcode exists in order items
     */
    private function barcode_in_order($barcode, $order_items) {
        return isset($order_items[$barcode]);
    }
    
    /**
     * Check if a serial is already in the list
     * Uses the full serial (barcode+serial) for uniqueness check
     */
    private function is_duplicate_serial($full_serial, $serials) {
        return in_array($full_serial, $serials);
    }
    
    /**
     * Check if number of serials matches required quantities
     */
    private function check_serial_quantities($order_items, $serial_map) {
        $errors = [];
        foreach ($order_items as $barcode => $qty) {
            $found = isset($serial_map[$barcode]) ? count(array_unique($serial_map[$barcode])) : 0;
            if ($found !== $qty) {
                $errors[] = "Product $barcode requires $qty serial(s), found $found.";
            }
        }
        return $errors;
    }
    
    /**
     * Validate returned serials
     * Ensures that returned serials were actually sold and are in the correct format
     * Also verifies that returned serials match WooCommerce refunded items
     * 
     * NOTE: The full URL is the serial identifier, with the product barcode embedded in it
     */
    private function validate_returned_serials($order, $serials_raw, $sold_serials_raw) {
        // Convert order param to WC_Order object if needed
        $order_id = is_object($order) ? $order->get_id() : $order;
        $wc_order = is_object($order) ? $order : wc_get_order($order_id);
        
        // Validate we have a valid order
        if (!$wc_order || !is_a($wc_order, 'WC_Order')) return ['Invalid order'];        
        
        // If no returned serials, that's valid (empty is allowed)
        if (empty(trim($serials_raw))) {
            return [];
        }
        
        $errors = [];
        $serial_regex = $this->get_serial_regex();
        
        // Get refund data from the order
        $refunded_quantities = [];
        $refunds = $wc_order->get_refunds();
        error_log('GSCOrderModular: Found ' . count($refunds) . ' refunds for order #' . $order_id);
        
        // Get item data from the order to map SKUs and product IDs to barcodes
        $order_items = $this->get_order_items_barcode_qty($wc_order, $errors);
        
        // Process all refunds to count total refunded quantities by barcode
        foreach ($refunds as $refund) {
            error_log('GSCOrderModular: Processing refund #' . $refund->get_id());
            
            foreach ($refund->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();
                $refunded_qty = abs($item->get_quantity()); // Absolute value since refunds are negative
                
                // Get product
                $product = wc_get_product($product_id);
                if (!$product) {
                    error_log('GSCOrderModular: Could not load product #' . $product_id);
                    continue;
                }
                
                // Get the global_unique_id (barcode) directly from the product
                $barcode = $product->get_data()['global_unique_id'];
                
                // Verify this barcode exists in the order items
                if (empty($barcode) || !isset($order_items[$barcode])) {
                    error_log('GSCOrderModular: Barcode ' . $barcode . ' from refunded product #' . $product_id . ' not found in order items');
                    continue;
                }
                
                error_log('GSCOrderModular: Found refund for product with barcode ' . $barcode . ', qty: ' . $refunded_qty);
                
                // Add to refunded quantities
                if (!isset($refunded_quantities[$barcode])) {
                    $refunded_quantities[$barcode] = 0;
                }
                $refunded_quantities[$barcode] += $refunded_qty;
            }
        }
        
        // Parse serials into an array
        $returned_lines = $this->parse_lines($serials_raw);
        error_log('GSCOrderModular: Validating ' . count($returned_lines) . ' returned serial numbers');
        
        // Parse sold serials to check against
        $sold_lines = $this->parse_lines($sold_serials_raw);
        $sold_serials = [];
        $sold_by_barcode = [];
        
        // Build a map of sold serials for checking
        foreach ($sold_lines as $line) {
            if ($this->is_valid_serial_format($line, $serial_regex)) {
                list($barcode, $serial) = $this->extract_barcode_and_serial($line, $serial_regex);
                if ($barcode && $serial) {
                    // The full URL is the unique identifier
                    $sold_serials[] = $line;
                    
                    // Group by barcode for quantity validation
                    if (!isset($sold_by_barcode[$barcode])) {
                        $sold_by_barcode[$barcode] = [];
                    }
                    $sold_by_barcode[$barcode][] = $line;
                }
            }
        }
        
        // Track returned serials for validation
        $returned_serials = [];
        $returned_by_barcode = [];
        
        // Validate each returned serial
        foreach ($returned_lines as $line) {
            // Skip empty lines
            if (empty(trim($line))) continue;
            
            // Validate serial format
            if (!$this->is_valid_serial_format($line, $serial_regex)) {
                $errors[] = "Invalid returned serial format: $line";
                continue;
            }
            
            // Extract barcode and serial for grouping by product
            list($barcode, $serial) = $this->extract_barcode_and_serial($line, $serial_regex);
            
            // Validate extraction worked
            if (!$barcode || !$serial) {
                $errors[] = "Could not extract barcode/serial from: $line";
                continue;
            }
            
            // Check if this serial was actually sold
            if (!in_array($line, $sold_serials)) {
                $errors[] = "Serial '$line' was not found in sold items";
                continue;
            }
            
            // Check for duplicates in returned serials
            if (in_array($line, $returned_serials)) {
                $errors[] = "Duplicate returned serial: $line";
                continue;
            }
            
            // Add to tracking arrays
            $returned_serials[] = $line;
            if (!isset($returned_by_barcode[$barcode])) {
                $returned_by_barcode[$barcode] = [];
            }
            $returned_by_barcode[$barcode][] = $line;
        }
        
        // Check if returned quantities match refunded quantities
        if (!empty($refunded_quantities)) {
            error_log('GSCOrderModular: Validating returned quantities against refunded quantities');
            
            // Check if all refunded products have the correct number of returned serials
            foreach ($refunded_quantities as $barcode => $refunded_qty) {
                $returned_qty = isset($returned_by_barcode[$barcode]) ? count($returned_by_barcode[$barcode]) : 0;
                
                if ($returned_qty !== $refunded_qty) {
                    $errors[] = "Quantity mismatch for returned product $barcode: expected $refunded_qty, found $returned_qty";
                    error_log("GSCOrderModular: Quantity mismatch for returned product $barcode: expected $refunded_qty, found $returned_qty");
                }
            }
            
            // Check if there are returned serials for non-refunded products
            foreach ($returned_by_barcode as $barcode => $serials) {
                if (!isset($refunded_quantities[$barcode]) || $refunded_quantities[$barcode] <= 0) {
                    $errors[] = "Product $barcode has returned serials but no refunds in WooCommerce";
                    error_log("GSCOrderModular: Product $barcode has returned serials but no refunds in WooCommerce");
                }
            }
        } else {
            // No refunds found, but we have returned serials
            if (!empty($returned_serials)) {
                $errors[] = "No refunds found in WooCommerce, but returned serials were provided";
                error_log('GSCOrderModular: No refunds found in WooCommerce, but returned serials were provided');
            }
        }
        
        if (!empty($errors)) {
            error_log('GSCOrderModular: Returned validation failed with ' . count($errors) . ' errors');
            // Log first few errors for debugging
            $error_sample = array_slice($errors, 0, 3);
            error_log('GSCOrderModular: Returned validation errors (sample): ' . print_r($error_sample, true));
        }
        
        return $errors;
    }
    
    /**
     * Validate replacement order ID
     * 
     * Implements business rules for replacements:
     * 1. The referenced order must exist
     * 2. The referenced order must have returned items
     * 3. Current order's sold serials must be replacing items returned in referenced order
     * 4. Quantity matching between replacements and returned items must be maintained
     * 
     * @param WC_Order $order Current order
     * @param string $replacement_order_id Order ID containing the returned items
     * @param string $sold_serials_raw Current order's sold serials
     * @return array Validation errors if any
     */
    public function validate_replacement_order_id($order, $replacement_order_id, $sold_serials_raw) {
        $errors = [];
        error_log("GSCOrderModular: Validating replacement order ID: $replacement_order_id");
        
        // Check if replacement order ID is provided
        if (empty(trim($replacement_order_id))) {
            $errors[] = "Replacement order ID is required";
            return $errors;
        }
        
        // Check if the referenced order exists
        $referenced_order = wc_get_order(trim($replacement_order_id));
        if (!$referenced_order || !is_a($referenced_order, 'WC_Order')) {
            $errors[] = "Order #$replacement_order_id does not exist";
            return $errors;
        }
        
        // Check if the referenced order has the returns flag enabled
        $has_returns = (bool)$referenced_order->get_meta(self::META_ENABLE_RETURNED);
        if (!$has_returns) {
            $errors[] = "Order #$replacement_order_id does not have any returned items";
            return $errors;
        }
        
        // Get returned serials from the referenced order
        $returned_serials_raw = $referenced_order->get_meta(self::META_RETURNED);
        if (empty(trim($returned_serials_raw))) {
            $errors[] = "Order #$replacement_order_id has no returned serial numbers";
            return $errors;
        }
        
        // Parse the current order's sold serials and the referenced order's returned serials
        $sold_lines = $this->parse_lines($sold_serials_raw);
        $returned_lines = $this->parse_lines($returned_serials_raw);
        
        // Get regex for serial validation
        $serial_regex = $this->get_serial_regex();
        
        // Track returned serials by barcode for validation
        $returned_by_barcode = [];
        foreach ($returned_lines as $line) {
            // Skip empty lines and invalid format
            if (empty(trim($line)) || !$this->is_valid_serial_format($line, $serial_regex)) continue;
            
            // Extract barcode for grouping by product
            list($barcode, $serial) = $this->extract_barcode_and_serial($line, $serial_regex);
            if (!$barcode || !$serial) continue;
            
            // Add to tracking array
            if (!isset($returned_by_barcode[$barcode])) {
                $returned_by_barcode[$barcode] = [];
            }
            $returned_by_barcode[$barcode][] = $line;
        }
        
        // Ensure we have returned items to match against
        if (empty($returned_by_barcode)) {
            $errors[] = "No valid returned serials found in order #$replacement_order_id";
            return $errors;
        }
        
        // Track sold serials in current order by barcode
        $sold_by_barcode = [];
        foreach ($sold_lines as $line) {
            // Skip empty lines and invalid format
            if (empty(trim($line)) || !$this->is_valid_serial_format($line, $serial_regex)) continue;
            
            // Extract barcode for grouping by product
            list($barcode, $serial) = $this->extract_barcode_and_serial($line, $serial_regex);
            if (!$barcode || !$serial) continue;
            
            // Add to tracking array
            if (!isset($sold_by_barcode[$barcode])) {
                $sold_by_barcode[$barcode] = [];
            }
            $sold_by_barcode[$barcode][] = $line;
        }
        
        // Validate that each product in current order has corresponding returned items
        foreach ($sold_by_barcode as $barcode => $serials) {
            // Check if this barcode has returned items
            if (!isset($returned_by_barcode[$barcode])) {
                $errors[] = "Product $barcode in current order doesn't have returned items in order #$replacement_order_id";
                continue;
            }
            
            // Check quantity matching
            $sold_qty = count($serials);
            $returned_qty = count($returned_by_barcode[$barcode]);
            
            if ($sold_qty > $returned_qty) {
                $errors[] = "Quantity mismatch for product $barcode: replacing $sold_qty items but only $returned_qty were returned in order #$replacement_order_id";
            }
        }
        
        // Log validation results
        if (!empty($errors)) {
            error_log("GSCOrderModular: Replacement validation failed with " . count($errors) . " errors");
            $error_sample = array_slice($errors, 0, 3);
            error_log("GSCOrderModular: Replacement validation errors (sample): " . print_r($error_sample, true));
        } else {
            error_log("GSCOrderModular: Replacement validation successful for order #$replacement_order_id");
        }
        
        return $errors;
    }
}