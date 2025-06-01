<?php
/**
 * Admin Payment Settings Page
 * 
 * Handles both payment gateway settings and batch management
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSC_Admin_Payment_Settings extends GSC_Admin_Page {
    /**
     * Current active tab
     * 
     * @var string
     */
    private $current_tab;

    /**
     * Available tabs
     * 
     * @var array
     */
    private $tabs;

    /**
     * Constructor
     */
    public function __construct() {
        $this->tabs = array(
            'gateways' => __('Payment Gateways', 'gscwordpress'),
            'batches' => __('Payment Batches', 'gscwordpress')
        );
        
        $this->current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'gateways';
    }

    /**
     * Initialize the page
     */
    public function init() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add AJAX handlers for batch management
        add_action('wp_ajax_gsc_create_payment_batch', array($this, 'ajax_create_batch'));
        add_action('wp_ajax_gsc_process_batch', array($this, 'ajax_process_batch'));
    }

    /**
     * Get menu title
     * 
     * @return string
     */
    public function get_menu_title() {
        return __('Payment Settings', 'gscwordpress');
    }

    /**
     * Get page title
     * 
     * @return string
     */
    public function get_page_title() {
        return __('Payment Settings', 'gscwordpress');
    }

    /**
     * Get menu slug
     * 
     * @return string
     */
    public function get_menu_slug() {
        return 'gsc-payment-settings';
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'gsc_payment_settings',
            'gsc_payment_gateway_options',
            array($this, 'sanitize_gateway_settings')
        );

        // General section
        add_settings_section(
            'gsc_payment_general',
            __('General Settings', 'gscwordpress'),
            array($this, 'render_general_section'),
            'gsc_payment_settings'
        );

        // Add mock mode field
        add_settings_field(
            'mock_mode',
            __('Test Mode', 'gscwordpress'),
            array($this, 'render_mock_mode_field'),
            'gsc_payment_settings',
            'gsc_payment_general'
        );

        // Add active gateway field
        add_settings_field(
            'active_gateway',
            __('Active Gateway', 'gscwordpress'),
            array($this, 'render_active_gateway_field'),
            'gsc_payment_settings',
            'gsc_payment_general'
        );

        // Gateway specific settings sections
        $gateways = GSC_Payment_Gateway::instance()->get_gateways();
        $options = get_option('gsc_payment_gateway_options', array());
        
        foreach ($gateways as $gateway_id => $gateway) {
            // Initialize gateway with saved settings
            if (isset($options['gateways'][$gateway_id])) {
                $gateway->update_settings($options['gateways'][$gateway_id]);
            }
            
            $section_id = 'gsc_payment_' . $gateway_id;
            add_settings_section(
                $section_id,
                $gateway->get_title(),
                array($gateway, 'render_settings_description'),
                'gsc_payment_settings'
            );

            // Add gateway specific fields
            $fields = $gateway->get_settings_fields();
            foreach ($fields as $field_id => $field) {
                add_settings_field(
                    $field_id,
                    $field['label'],
                    array($gateway, 'render_settings_field'),
                    'gsc_payment_settings',
                    $section_id,
                    array('field_id' => $field_id)
                );
            }
        }
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if (!$this->is_current_page()) {
            return;
        }

        // Common styles
        wp_enqueue_style(
            'gsc-admin-payment-settings',
            GSC_PLUGIN_URL . 'assets/css/admin-payment-settings.css',
            array(),
            GSC_VERSION
        );

        // Tab specific scripts
        if ($this->current_tab === 'batches') {
            wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
            wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'));
            
            wp_enqueue_style('wp-jquery-ui-dialog');
            wp_enqueue_script('jquery-ui-dialog');
            
            wp_enqueue_script(
                'gsc-admin-payment-batch',
                GSC_PLUGIN_URL . 'assets/js/admin-payment-batch.js',
                array('jquery', 'select2', 'jquery-ui-dialog'),
                GSC_VERSION,
                true
            );
            
            wp_localize_script('gsc-admin-payment-batch', 'gsc_admin', array(
                'nonce' => wp_create_nonce('gsc_admin_ajax'),
                'strings' => array(
                    'confirm_process' => __('Are you sure you want to process this batch?', 'gscwordpress'),
                    'processing' => __('Processing...', 'gscwordpress'),
                    'success' => __('Batch processed successfully', 'gscwordpress'),
                    'error' => __('Error processing batch', 'gscwordpress')
                )
            ));
        }
    }

    /**
     * Render the page
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->get_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <?php foreach ($this->tabs as $tab_id => $tab_name): ?>
                    <a href="<?php echo esc_url(add_query_arg('tab', $tab_id)); ?>" 
                       class="nav-tab <?php echo $this->current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_name); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <div class="tab-content">
                <?php
                if ($this->current_tab === 'gateways') {
                    $this->render_gateways_tab();
                } else {
                    $this->render_batches_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render gateways tab
     */
    private function render_gateways_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('gsc_payment_settings');
            do_settings_sections('gsc_payment_settings');
            submit_button();
            ?>
        </form>
        <?php
    }

    /**
     * Render batches tab
     */
    private function render_batches_tab() {
        // This will render the batch management interface
        do_action('gsc_payment_batch_page');
    }

    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure general payment settings and select your active payment gateway.', 'gscwordpress') . '</p>';
    }

    /**
     * Render mock mode field
     */
    public function render_mock_mode_field() {
        $options = get_option('gsc_payment_gateway_options', array());
        $mock_mode = isset($options['mock_mode']) ? $options['mock_mode'] : false;
        ?>
        <label>
            <input type="checkbox" 
                   name="gsc_payment_gateway_options[mock_mode]" 
                   value="1" 
                   <?php checked(1, $mock_mode); ?>>
            <?php _e('Enable test mode (uses mock responses for testing)', 'gscwordpress'); ?>
        </label>
        <p class="description">
            <?php _e('When enabled, payments will use test data. Use @success.upi or @fail.upi for testing.', 'gscwordpress'); ?>
        </p>
        <?php if ($mock_mode): ?>
        <div class="notice notice-warning inline">
            <p><?php _e('Test mode is enabled. No real payments will be processed.', 'gscwordpress'); ?></p>
        </div>
        <?php endif;
    }

    /**
     * Render active gateway field
     */
    public function render_active_gateway_field() {
        $options = get_option('gsc_payment_gateway_options', array());
        $active_gateway = isset($options['active_gateway']) ? $options['active_gateway'] : '';
        $gateways = array(
            'cashfree' => __('Cashfree', 'gscwordpress'),
            'razorpay' => __('Razorpay', 'gscwordpress'),
            'payu' => __('PayU', 'gscwordpress')
        );
        ?>
        <select name="gsc_payment_gateway_options[active_gateway]">
            <option value=""><?php _e('Select a gateway', 'gscwordpress'); ?></option>
            <?php foreach ($gateways as $gateway_id => $gateway_name): ?>
                <option value="<?php echo esc_attr($gateway_id); ?>" 
                        <?php selected($active_gateway, $gateway_id); ?>>
                    <?php echo esc_html($gateway_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Sanitize gateway settings
     * 
     * @param array $input
     * @return array
     */
    public function sanitize_gateway_settings($input) {
        $sanitized = array();
        
        // Mock mode
        $sanitized['mock_mode'] = isset($input['mock_mode']) ? 1 : 0;
        
        // Active gateway
        if (isset($input['active_gateway'])) {
            $sanitized['active_gateway'] = sanitize_text_field($input['active_gateway']);
        }
        
        // Gateway specific settings
        if (isset($input['gateways']) && is_array($input['gateways'])) {
            $sanitized['gateways'] = array();
            foreach ($input['gateways'] as $gateway => $settings) {
                $sanitized['gateways'][$gateway] = array();
                foreach ($settings as $key => $value) {
                    $sanitized['gateways'][$gateway][$key] = sanitize_text_field($value);
                }
            }
        }
        
        return $sanitized;
    }
}
