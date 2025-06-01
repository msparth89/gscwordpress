<?php
/**
 * GSC Admin Class
 * 
 * Main admin class for GSC WordPress plugin.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSC_Admin {
    /**
     * The single instance of the class
     */
    protected static $_instance = null;

    /**
     * Tabs registry
     */
    protected $tabs = array();

    /**
     * Active tab
     */
    protected $active_tab = '';

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
        // Register the admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Initialize tabs
        $this->init_tabs();
    }

    /**
     * Initialize tabs
     */
    public function init_tabs() {
        // Register the payment gateway settings tab
        $this->register_tab('payment_gateways', __('Payment Gateways', 'gscwordpress'), array($this, 'render_payment_gateways_tab'));
        
        // Set the default active tab
        if (empty($this->active_tab)) {
            $this->active_tab = 'payment_gateways';
        }
    }

    /**
     * Register a new tab
     */
    public function register_tab($id, $title, $callback) {
        $this->tabs[$id] = array(
            'title' => $title,
            'callback' => $callback
        );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('GSC Settings', 'gscwordpress'),
            __('GSC Settings', 'gscwordpress'),
            'manage_options',
            'gsc-settings',
            array($this, 'render_admin_page'),
            'dashicons-money-alt',
            30
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        // Only load on our settings page
        if ($hook != 'toplevel_page_gsc-settings') {
            return;
        }

        // Enqueue styles
        wp_enqueue_style('gsc-admin-styles', plugin_dir_url(GSC_PLUGIN_FILE) . 'assets/css/admin.css', array(), GSC_VERSION);
        
        // Enqueue scripts
        wp_enqueue_script('gsc-admin-scripts', plugin_dir_url(GSC_PLUGIN_FILE) . 'assets/js/admin.js', array('jquery'), GSC_VERSION, true);
        
        // Localize script with AJAX url and nonce
        wp_localize_script('gsc-admin-scripts', 'GSC_Admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gsc-admin-nonce')
        ));
    }

    /**
     * Render the admin page
     */
    public function render_admin_page() {
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $this->active_tab;
        
        // If the tab doesn't exist, use the default
        if (!isset($this->tabs[$current_tab])) {
            $current_tab = $this->active_tab;
        }
        
        ?>
        <div class="wrap gsc-admin-wrap">
            <h1><?php echo esc_html__('GSC Settings', 'gscwordpress'); ?></h1>
            
            <?php if (count($this->tabs) > 1) : ?>
            <nav class="nav-tab-wrapper gsc-nav-tab-wrapper">
                <?php foreach ($this->tabs as $tab_id => $tab) : ?>
                    <a href="<?php echo esc_url(add_query_arg('tab', $tab_id, admin_url('admin.php?page=gsc-settings'))); ?>" 
                       class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab['title']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
            
            <div class="gsc-admin-content">
                <?php
                // Call the tab render callback
                if (isset($this->tabs[$current_tab]['callback']) && is_callable($this->tabs[$current_tab]['callback'])) {
                    call_user_func($this->tabs[$current_tab]['callback']);
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the payment gateways settings tab
     */
    public function render_payment_gateways_tab() {
        // Include the payment gateway settings renderer
        require_once plugin_dir_path(GSC_PLUGIN_FILE) . 'includes/admin/class-gsc-admin-payment-settings.php';
        $settings = new GSC_Admin_Payment_Settings();
        $settings->render();
    }
}

// Initialize the admin class
GSC_Admin::instance();
