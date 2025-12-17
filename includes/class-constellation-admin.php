<?php
/**
 * Constellation Admin
 *
 * Handles admin menu registration and page routing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Constellation_Admin {

    /**
     * Singleton instance
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Page instances
     *
     * @var array
     */
    private $pages = array();

    /**
     * Get instance
     *
     * @return self
     */
    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();

        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    /**
     * Load page class dependencies
     */
    private function load_dependencies() {
        require_once CONSTELLATION_PATH . 'includes/admin/class-constellation-clients-page.php';
        require_once CONSTELLATION_PATH . 'includes/admin/class-constellation-client-edit-page.php';
        require_once CONSTELLATION_PATH . 'includes/admin/class-constellation-tags-page.php';
    }

    /**
     * Add admin menu
     */
    public function add_menu() {
        // Main menu
        add_menu_page(
            __( 'Clients', 'constellation' ),
            __( 'Clients', 'constellation' ),
            'manage_options',
            'constellation-clients',
            array( $this, 'render_clients_page' ),
            'dashicons-groups',
            30
        );

        // Clients submenu (same as main)
        add_submenu_page(
            'constellation-clients',
            __( 'All Clients', 'constellation' ),
            __( 'All Clients', 'constellation' ),
            'manage_options',
            'constellation-clients',
            array( $this, 'render_clients_page' )
        );

        // Add/Edit Client (hidden from menu)
        add_submenu_page(
            null, // Hidden
            __( 'Edit Client', 'constellation' ),
            __( 'Edit Client', 'constellation' ),
            'manage_options',
            'constellation-client-edit',
            array( $this, 'render_client_edit_page' )
        );

        // Tags submenu
        add_submenu_page(
            'constellation-clients',
            __( 'Tags', 'constellation' ),
            __( 'Tags', 'constellation' ),
            'manage_options',
            'constellation-tags',
            array( $this, 'render_tags_page' )
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_assets( $hook ) {
        // Only load on our pages
        $our_pages = array(
            'toplevel_page_constellation-clients',
            'clients_page_constellation-tags',
            'admin_page_constellation-client-edit',
        );

        if ( ! in_array( $hook, $our_pages, true ) ) {
            return;
        }

        // Enqueue WordPress media library for logo selector
        wp_enqueue_media();

        // Enqueue Mosaic assets
        $mosaic_url = CONSTELLATION_URL . 'mosaic/';
        wp_enqueue_style(
            'mosaic',
            $mosaic_url . Mosaic::css(),
            array(),
            Mosaic::version()
        );
        wp_enqueue_script(
            'mosaic',
            $mosaic_url . Mosaic::js(),
            array( 'jquery' ),
            Mosaic::version(),
            true
        );

        // Enqueue Mosaic modules
        wp_enqueue_script(
            'mosaic-tabs',
            $mosaic_url . 'assets/js/modules/tabs.js',
            array( 'mosaic' ),
            Mosaic::version(),
            true
        );
        wp_enqueue_script(
            'mosaic-media',
            $mosaic_url . 'assets/js/modules/media.js',
            array( 'mosaic', 'jquery' ),
            Mosaic::version(),
            true
        );
        wp_enqueue_script(
            'mosaic-tags',
            $mosaic_url . 'assets/js/modules/tags.js',
            array( 'mosaic' ),
            Mosaic::version(),
            true
        );
        wp_enqueue_script(
            'mosaic-dialog',
            $mosaic_url . 'assets/js/modules/dialog.js',
            array( 'mosaic' ),
            Mosaic::version(),
            true
        );

        // Enqueue our custom styles
        wp_enqueue_style(
            'constellation-admin',
            CONSTELLATION_URL . 'assets/css/constellation-admin.css',
            array(),
            CONSTELLATION_VERSION
        );

        // Enqueue our custom scripts
        wp_enqueue_script(
            'constellation-admin',
            CONSTELLATION_URL . 'assets/js/constellation-admin.js',
            array( 'jquery' ),
            CONSTELLATION_VERSION,
            true
        );

        // Localize script
        wp_localize_script( 'constellation-admin', 'constellationAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'constellation_admin' ),
        ) );
    }

    /**
     * Handle admin actions
     */
    public function handle_actions() {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

        switch ( $page ) {
            case 'constellation-clients':
                $this->get_page( 'clients' )->handle_actions();
                break;

            case 'constellation-client-edit':
                $this->get_page( 'client-edit' )->handle_save();
                break;

            case 'constellation-tags':
                $this->get_page( 'tags' )->handle_actions();
                $this->get_page( 'tags' )->handle_save();
                break;
        }
    }

    /**
     * Render clients page
     */
    public function render_clients_page() {
        $this->get_page( 'clients' )->render();
    }

    /**
     * Render client edit page
     */
    public function render_client_edit_page() {
        $this->get_page( 'client-edit' )->render();
    }

    /**
     * Render tags page
     */
    public function render_tags_page() {
        $this->get_page( 'tags' )->render();
    }

    /**
     * Get or create a page instance
     *
     * @param string $key Page key
     * @return object Page instance
     */
    private function get_page( $key ) {
        if ( ! isset( $this->pages[ $key ] ) ) {
            switch ( $key ) {
                case 'clients':
                    $this->pages[ $key ] = new Constellation_Clients_Page();
                    break;

                case 'client-edit':
                    $this->pages[ $key ] = new Constellation_Client_Edit_Page();
                    break;

                case 'tags':
                    $this->pages[ $key ] = new Constellation_Tags_Page();
                    break;
            }
        }

        return $this->pages[ $key ];
    }
}
