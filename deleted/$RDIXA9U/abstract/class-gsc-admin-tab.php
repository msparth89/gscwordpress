<?php
/**
 * Abstract Admin Tab Class
 * 
 * Base class for admin page tabs
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

abstract class GSC_Admin_Tab {
    /**
     * Get tab ID
     * 
     * @return string
     */
    abstract public function get_id();

    /**
     * Get tab title
     * 
     * @return string
     */
    abstract public function get_title();

    /**
     * Render tab content
     */
    abstract public function render();

    /**
     * Initialize tab
     * Override this in child classes to add hooks, etc.
     */
    public function init() {
        // Add any tab-specific initialization
    }

    /**
     * Check if this is the active tab
     * 
     * @param string $current_tab Current tab ID
     * @return bool
     */
    public function is_active($current_tab) {
        return $current_tab === $this->get_id();
    }

    /**
     * Get tab URL
     * 
     * @param string $page_url Base page URL
     * @return string
     */
    public function get_url($page_url) {
        return add_query_arg('tab', $this->get_id(), $page_url);
    }

    /**
     * Register settings for this tab
     * Override this in child classes that need settings
     */
    public function register_settings() {
        // Register tab-specific settings
    }

    /**
     * Enqueue tab-specific scripts and styles
     * Override this in child classes that need assets
     */
    public function enqueue_scripts() {
        // Add tab-specific scripts and styles
    }
}
