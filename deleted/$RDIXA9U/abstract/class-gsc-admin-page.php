<?php
/**
 * Abstract Admin Page Class
 * 
 * Base class that all admin pages must extend
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

abstract class GSC_Admin_Page {
    /**
     * Get the menu title
     * 
     * @return string
     */
    abstract public function get_menu_title();

    /**
     * Get the page title
     * 
     * @return string
     */
    abstract public function get_page_title();

    /**
     * Get the menu slug
     * 
     * @return string
     */
    abstract public function get_menu_slug();

    /**
     * Render the page content
     */
    abstract public function render_page();

    /**
     * Initialize the page
     * Override this in child classes to add hooks, etc.
     */
    public function init() {
        // Add any page-specific initialization
    }

    /**
     * Get required capability for this page
     * 
     * @return string Capability name
     */
    protected function get_capability() {
        return 'manage_options';
    }

    /**
     * Register this page in the admin menu
     * 
     * @param string $parent_slug Parent menu slug
     */
    public function register_page($parent_slug) {
        add_submenu_page(
            $parent_slug,
            $this->get_page_title(),
            $this->get_menu_title(),
            $this->get_capability(),
            $this->get_menu_slug(),
            array($this, 'render_page')
        );
    }

    /**
     * Check if this is the current admin page
     * 
     * @return bool
     */
    protected function is_current_page() {
        $screen = get_current_screen();
        return $screen && $screen->id === 'gscwordpress_page_' . $this->get_menu_slug();
    }

    /**
     * Enqueue scripts and styles for this page
     * Override this in child classes to add page-specific assets
     */
    public function enqueue_scripts() {
        // Add page-specific scripts and styles
    }

    /**
     * Get admin page URL
     * 
     * @param array $args Additional query args
     * @return string
     */
    protected function get_page_url($args = array()) {
        $base_args = array(
            'page' => $this->get_menu_slug()
        );
        return add_query_arg(
            array_merge($base_args, $args),
            admin_url('admin.php')
        );
    }

    /**
     * Display admin notice
     * 
     * @param string $message Notice message
     * @param string $type Notice type (error, warning, success, info)
     */
    protected function add_notice($message, $type = 'info') {
        add_action('admin_notices', function() use ($message, $type) {
            if ($this->is_current_page()) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr($type),
                    esc_html($message)
                );
            }
        });
    }
}
