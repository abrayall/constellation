<?php
/**
 * Client Service
 *
 * Business logic layer for client operations.
 * Provides validation, hooks, and high-level operations.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Constellation_Client_Service {

    /**
     * Singleton instance
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Client repository
     *
     * @var Constellation_Client_Repository
     */
    private $repository;

    /**
     * Tag repository
     *
     * @var Constellation_Tag_Repository
     */
    private $tag_repository;

    /**
     * Get service instance
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
        $this->repository = Constellation_Client_Repository::instance();
        $this->tag_repository = Constellation_Tag_Repository::instance();
    }

    /**
     * Get the repository
     *
     * @return Constellation_Client_Repository
     */
    public function get_repository() {
        return $this->repository;
    }

    /**
     * Create a new client
     *
     * @param array $data Client data
     * @return Constellation_Client|WP_Error
     */
    public function create( $data ) {
        $client = new Constellation_Client( $data );

        // Validate
        $validation = $this->validate( $client );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        /**
         * Fires before a client is created
         *
         * @param Constellation_Client $client The client being created
         * @param array $data The original data
         */
        do_action( 'constellation_client_before_create', $client, $data );

        try {
            $client = $this->repository->save( $client );

            // Handle tags if provided
            if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
                $this->sync_tags( $client->get_id(), $data['tags'] );
            }

            /**
             * Fires after a client is created
             *
             * @param Constellation_Client $client The created client
             */
            do_action( 'constellation_client_created', $client );

            return $client;

        } catch ( Exception $e ) {
            return new WP_Error( 'create_failed', $e->getMessage() );
        }
    }

    /**
     * Update an existing client
     *
     * @param string $id Client ID
     * @param array  $data Updated data
     * @return Constellation_Client|WP_Error
     */
    public function update( $id, $data ) {
        $client = $this->repository->find_by_id( $id );

        if ( ! $client ) {
            return new WP_Error( 'not_found', __( 'Client not found.', 'constellation' ) );
        }

        // Store original for comparison
        $original = clone $client;

        // Update fields
        $client->fill( $data );

        // Validate
        $validation = $this->validate( $client, $id );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        /**
         * Fires before a client is updated
         *
         * @param Constellation_Client $client The client being updated
         * @param Constellation_Client $original The original client
         * @param array $data The update data
         */
        do_action( 'constellation_client_before_update', $client, $original, $data );

        try {
            $client = $this->repository->save( $client );

            // Handle tags if provided
            if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
                $this->sync_tags( $client->get_id(), $data['tags'] );
            }

            /**
             * Fires after a client is updated
             *
             * @param Constellation_Client $client The updated client
             * @param Constellation_Client $original The original client
             */
            do_action( 'constellation_client_updated', $client, $original );

            return $client;

        } catch ( Exception $e ) {
            return new WP_Error( 'update_failed', $e->getMessage() );
        }
    }

    /**
     * Delete a client
     *
     * @param string $id Client ID
     * @return bool|WP_Error
     */
    public function delete( $id ) {
        $client = $this->repository->find_by_id( $id );

        if ( ! $client ) {
            return new WP_Error( 'not_found', __( 'Client not found.', 'constellation' ) );
        }

        /**
         * Fires before a client is deleted
         *
         * @param Constellation_Client $client The client being deleted
         */
        do_action( 'constellation_client_before_delete', $client );

        $result = $this->repository->delete( $id );

        if ( $result ) {
            /**
             * Fires after a client is deleted
             *
             * @param string $id The deleted client ID
             * @param Constellation_Client $client The deleted client data
             */
            do_action( 'constellation_client_deleted', $id, $client );
        }

        return $result;
    }

    /**
     * Get a client by ID
     *
     * @param string $id
     * @return Constellation_Client|null
     */
    public function get( $id ) {
        return $this->repository->find_by_id( $id );
    }

    /**
     * Get a client by slug
     *
     * @param string $slug
     * @return Constellation_Client|null
     */
    public function get_by_slug( $slug ) {
        return $this->repository->find_by_slug( $slug );
    }

    /**
     * Get all clients
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_all( $args = array() ) {
        $defaults = array(
            'status'       => null,
            'order_by'     => array( 'name' => 'ASC' ),
            'limit'        => 0,
            'offset'       => 0,
            'with_tags'    => false,
        );

        $args = wp_parse_args( $args, $defaults );

        $criteria = array();
        if ( $args['status'] ) {
            $criteria['status'] = $args['status'];
        }

        if ( $args['with_tags'] ) {
            return $this->repository->find_with_tags( $criteria, $args['order_by'], $args['limit'], $args['offset'] );
        }

        return $this->repository->find( $criteria, $args['order_by'], $args['limit'], $args['offset'] );
    }

    /**
     * Search clients
     *
     * @param string $query Search query
     * @param array  $args  Additional arguments
     * @return array
     */
    public function search( $query, $args = array() ) {
        $defaults = array(
            'fields'    => array(),
            'limit'     => 0,
            'offset'    => 0,
            'with_tags' => true,
        );

        $args = wp_parse_args( $args, $defaults );

        $clients = $this->repository->search( $query, $args['fields'], $args['limit'], $args['offset'] );

        // Load tags for display
        if ( $args['with_tags'] && ! empty( $clients ) ) {
            $client_ids = array_map( function( $client ) {
                return $client->get_id();
            }, $clients );

            $tags_by_client = $this->repository->load_tags_for_clients( $client_ids );

            foreach ( $clients as $client ) {
                $client_id = $client->get_id();
                $tags = isset( $tags_by_client[ $client_id ] ) ? $tags_by_client[ $client_id ] : array();
                $client->set_data_value( '_tags', $tags );
            }
        }

        return $clients;
    }

    /**
     * Count clients
     *
     * @param array $criteria
     * @return int
     */
    public function count( $criteria = array() ) {
        return $this->repository->count( $criteria );
    }

    /**
     * Get count by status
     *
     * @return array
     */
    public function get_counts_by_status() {
        $statuses = Constellation_Client::get_statuses();
        $counts = array();

        foreach ( array_keys( $statuses ) as $status ) {
            $counts[ $status ] = $this->repository->count( array( 'status' => $status ) );
        }

        $counts['all'] = array_sum( $counts );

        return $counts;
    }

    /**
     * Validate a client
     *
     * @param Constellation_Client $client
     * @param string|null          $exclude_id ID to exclude from uniqueness checks
     * @return true|WP_Error
     */
    public function validate( $client, $exclude_id = null ) {
        $errors = new WP_Error();

        // Name is required
        if ( empty( $client->get_name() ) ) {
            $errors->add( 'name_required', __( 'Client name is required.', 'constellation' ) );
        }

        // Name length
        if ( strlen( $client->get_name() ) > 255 ) {
            $errors->add( 'name_too_long', __( 'Client name must be 255 characters or less.', 'constellation' ) );
        }

        // Check for duplicate slug
        if ( ! empty( $client->get_slug() ) ) {
            $existing = $this->repository->find_by_slug( $client->get_slug() );
            if ( $existing && $existing->get_id() !== $exclude_id ) {
                $errors->add( 'slug_exists', __( 'A client with this name already exists.', 'constellation' ) );
            }
        }

        // Validate email if provided
        $email = $client->get_email();
        if ( ! empty( $email ) && ! is_email( $email ) ) {
            $errors->add( 'invalid_email', __( 'Please provide a valid email address.', 'constellation' ) );
        }

        // Validate website if provided
        $website = $client->get_website();
        if ( ! empty( $website ) && ! filter_var( $website, FILTER_VALIDATE_URL ) ) {
            $errors->add( 'invalid_website', __( 'Please provide a valid website URL.', 'constellation' ) );
        }

        /**
         * Filter client validation errors
         *
         * @param WP_Error $errors Validation errors
         * @param Constellation_Client $client The client being validated
         * @param string|null $exclude_id ID being excluded from checks
         */
        $errors = apply_filters( 'constellation_client_validation_errors', $errors, $client, $exclude_id );

        if ( $errors->has_errors() ) {
            return $errors;
        }

        return true;
    }

    /**
     * Sync tags for a client
     *
     * @param string $client_id
     * @param array  $tag_ids Array of tag IDs or names
     * @return bool
     */
    public function sync_tags( $client_id, $tag_ids ) {
        // Resolve tag names to IDs
        $resolved_ids = array();
        foreach ( $tag_ids as $tag ) {
            if ( strlen( $tag ) === 36 && preg_match( '/^[a-f0-9-]+$/', $tag ) ) {
                // Looks like a UUID
                $resolved_ids[] = $tag;
            } else {
                // Treat as a name, find or create
                $tag_obj = $this->tag_repository->find_or_create( $tag );
                $resolved_ids[] = $tag_obj->get_id();
            }
        }

        return $this->repository->set_tags( $client_id, $resolved_ids );
    }

    /**
     * Get tags for a client
     *
     * @param string $client_id
     * @return array
     */
    public function get_tags( $client_id ) {
        return $this->tag_repository->find_by_client( $client_id );
    }

    /**
     * Add a tag to a client
     *
     * @param string $client_id
     * @param string $tag_id
     * @return bool
     */
    public function add_tag( $client_id, $tag_id ) {
        return $this->repository->add_tag( $client_id, $tag_id );
    }

    /**
     * Remove a tag from a client
     *
     * @param string $client_id
     * @param string $tag_id
     * @return bool
     */
    public function remove_tag( $client_id, $tag_id ) {
        return $this->repository->remove_tag( $client_id, $tag_id );
    }

    /**
     * Archive a client
     *
     * @param string $id
     * @return Constellation_Client|WP_Error
     */
    public function archive( $id ) {
        return $this->update( $id, array( 'status' => Constellation_Client::STATUS_ARCHIVED ) );
    }

    /**
     * Activate a client
     *
     * @param string $id
     * @return Constellation_Client|WP_Error
     */
    public function activate( $id ) {
        return $this->update( $id, array( 'status' => Constellation_Client::STATUS_ACTIVE ) );
    }

    /**
     * Export clients to array
     *
     * @param array $criteria
     * @return array
     */
    public function export( $criteria = array() ) {
        $clients = $this->repository->find_with_tags( $criteria, array( 'name' => 'ASC' ) );

        $export = array();
        foreach ( $clients as $client ) {
            $data = $client->to_array();
            $data['tags'] = array_map( function( $tag ) {
                return $tag->get_name();
            }, $client->get_data_value( '_tags', array() ) );
            unset( $data['data']['_tags'] );
            $export[] = $data;
        }

        return $export;
    }
}
