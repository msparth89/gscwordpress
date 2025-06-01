<?php
/**
 * Admin Menu Handler Class
 * 
 * Manages the admin menu structure and page registration
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GSC_Admin_Menu {
    /**
     * Parent menu slug
     */
    const PARENT_SLUG = 'gscwordpress';

    /**
     * Admin pages
     * 
     * @var GSC_Admin_Page[]
     */
    private $pages = array();

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Register admin pages
     * 
     * @param GSC_Admin_Page $page
     */
    public function add_page($page) {
        if (!$page instanceof GSC_Admin_Page) {
            throw new InvalidArgumentException('Page must be an instance of GSC_Admin_Page');
        }
        $this->pages[] = $page;
        $page->init();
    }

    /**
     * Register the admin menu
     */
    public function register_menu() {
        // Add main menu
        add_menu_page(
            __('GSC WordPress', 'gscwordpress'),
            __('GSC WordPress', 'gscwordpress'),
            'manage_options',
            self::PARENT_SLUG,
            array($this, 'render_dashboard'),
            'dashicons-store',
            25
        );

        // Register all pages
        foreach ($this->pages as $page) {
            $page->register_page(self::PARENT_SLUG);
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts() {
        // Enqueue common admin assets here
        
        // Let each page enqueue its own scripts
        foreach ($this->pages as $page) {
            $page->enqueue_scripts();
        }
    }

    /**
     * Render the main dashboard
     * This is a placeholder until we create the dashboard page class
     */
    public function render_dashboard() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('GSC WordPress Dashboard', 'gscwordpress') . '</h1>';
        echo '<p>' . esc_html__('Welcome to GSC WordPress Plugin', 'gscwordpress') . '</p>';
        echo '</div>';
    }
}
