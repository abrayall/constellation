<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CONSTELLATION_VERSION', '1.0.0' );
define( 'CONSTELLATION_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONSTELLATION_URL', plugin_dir_url( __FILE__ ) );

// Load Mosaic
require_once CONSTELLATION_PATH . 'mosaic/includes/class-mosaic.php';
require_once CONSTELLATION_PATH . 'mosaic/includes/class-mosaic-table.php';
require_once CONSTELLATION_PATH . 'mosaic/includes/class-mosaic-data-table.php';
require_once CONSTELLATION_PATH . 'mosaic/includes/class-mosaic-card.php';
require_once CONSTELLATION_PATH . 'mosaic/includes/class-mosaic-accordion.php';
require_once CONSTELLATION_PATH . 'mosaic/includes/class-mosaic-tabs.php';
require_once CONSTELLATION_PATH . 'mosaic/includes/class-mosaic-form.php';

Mosaic::load( CONSTELLATION_PATH . 'mosaic' );

// Load activator
require_once CONSTELLATION_PATH . 'includes/class-constellation-activator.php';

// Load models
require_once CONSTELLATION_PATH . 'includes/models/class-constellation-model.php';
require_once CONSTELLATION_PATH . 'includes/models/class-constellation-client.php';
require_once CONSTELLATION_PATH . 'includes/models/class-constellation-tag.php';

// Load repositories
require_once CONSTELLATION_PATH . 'includes/repositories/interface-constellation-repository.php';
require_once CONSTELLATION_PATH . 'includes/repositories/class-constellation-mysql-repository.php';
require_once CONSTELLATION_PATH . 'includes/repositories/class-constellation-client-repository.php';
require_once CONSTELLATION_PATH . 'includes/repositories/class-constellation-tag-repository.php';

// Load services
require_once CONSTELLATION_PATH . 'includes/services/class-constellation-client-service.php';

// Load plugin files
require_once CONSTELLATION_PATH . 'includes/class-constellation.php';
require_once CONSTELLATION_PATH . 'includes/class-constellation-admin.php';

// Activation/Deactivation/Uninstall hooks
register_activation_hook( __FILE__, array( 'Constellation_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Constellation_Activator', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'Constellation_Activator', 'uninstall' ) );

// Initialize
add_action( 'plugins_loaded', function() {
    if ( get_option( 'constellation_version' ) !== CONSTELLATION_VERSION ) {
        Constellation_Activator::activate();
        update_option( 'constellation_version', CONSTELLATION_VERSION );
    }

    Constellation::instance();
});
