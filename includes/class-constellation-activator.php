<?php
/**
 * Constellation Activator
 *
 * Handles plugin activation, database table creation, and MySQL version detection.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Constellation_Activator {

    /**
     * Minimum MySQL version for native JSON support
     */
    const MYSQL_JSON_MIN_VERSION = '5.7.0';

    /**
     * Minimum MariaDB version for native JSON support
     */
    const MARIADB_JSON_MIN_VERSION = '10.2.0';

    /**
     * Cached JSON support flag
     */
    private static $supports_json = null;

    /**
     * Run activation tasks
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
    }

    /**
     * Check if the database supports native JSON type
     *
     * @return bool
     */
    public static function supports_json() {
        if ( self::$supports_json !== null ) {
            return self::$supports_json;
        }

        global $wpdb;

        $version_info = self::get_db_version_info();

        if ( $version_info['is_mariadb'] ) {
            self::$supports_json = version_compare( $version_info['version'], self::MARIADB_JSON_MIN_VERSION, '>=' );
        } else {
            self::$supports_json = version_compare( $version_info['version'], self::MYSQL_JSON_MIN_VERSION, '>=' );
        }

        return self::$supports_json;
    }

    /**
     * Get database version information
     *
     * @return array Contains 'version' and 'is_mariadb' keys
     */
    public static function get_db_version_info() {
        global $wpdb;

        $version_string = $wpdb->get_var( 'SELECT VERSION()' );
        $is_mariadb = stripos( $version_string, 'mariadb' ) !== false;

        // Extract version number
        if ( preg_match( '/^(\d+\.\d+\.\d+)/', $version_string, $matches ) ) {
            $version = $matches[1];
        } else {
            $version = '0.0.0';
        }

        return array(
            'version'    => $version,
            'is_mariadb' => $is_mariadb,
            'full'       => $version_string,
        );
    }

    /**
     * Get the appropriate data type for JSON storage
     *
     * @return string SQL data type
     */
    public static function get_json_column_type() {
        return self::supports_json() ? 'JSON' : 'LONGTEXT';
    }

    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $json_type = self::get_json_column_type();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Client table
        $table_client = $wpdb->prefix . 'constellation_client';
        $sql_client = "CREATE TABLE {$table_client} (
            id char(36) NOT NULL,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            data {$json_type},
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY name (name),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql_client );

        // Tag table
        $table_tag = $wpdb->prefix . 'constellation_tag';
        $sql_tag = "CREATE TABLE {$table_tag} (
            id char(36) NOT NULL,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            color varchar(7) DEFAULT NULL,
            description text DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY name (name)
        ) {$charset_collate};";

        dbDelta( $sql_tag );

        // Add description column if it doesn't exist (migration for existing installs)
        $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_tag} LIKE 'description'" );
        if ( empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_tag} ADD COLUMN description text DEFAULT NULL AFTER color" );
        }

        // Client-Tag junction table
        $table_client_tag = $wpdb->prefix . 'constellation_client_tag';
        $sql_client_tag = "CREATE TABLE {$table_client_tag} (
            client_id char(36) NOT NULL,
            tag_id char(36) NOT NULL,
            PRIMARY KEY  (client_id, tag_id),
            KEY tag_id (tag_id)
        ) {$charset_collate};";

        dbDelta( $sql_client_tag );

        // Store JSON support info
        update_option( 'constellation_db_supports_json', self::supports_json() ? '1' : '0' );
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        add_option( 'constellation_settings', array(
            'default_client_status' => 'active',
        ) );
    }

    /**
     * Run deactivation tasks
     */
    public static function deactivate() {
        // Clean up any scheduled tasks, transients, etc.
        // Note: We don't drop tables on deactivation to preserve data
    }

    /**
     * Run uninstall tasks (called from uninstall.php)
     */
    public static function uninstall() {
        global $wpdb;

        // Drop tables
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}constellation_client_tag" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}constellation_tag" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}constellation_client" );

        // Remove options
        delete_option( 'constellation_version' );
        delete_option( 'constellation_db_supports_json' );
        delete_option( 'constellation_settings' );
    }
}
