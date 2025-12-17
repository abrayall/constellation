<?php
/**
 * Client Repository
 *
 * MySQL repository implementation for Client entities.
 * Supports pluggable storage via filter.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Constellation_Client_Repository extends Constellation_MySQL_Repository {

    /**
     * Table name (without prefix)
     *
     * @var string
     */
    protected $table = 'constellation_client';

    /**
     * Model class name
     *
     * @var string
     */
    protected $model_class = 'Constellation_Client';

    /**
     * Singleton instance
     *
     * @var Constellation_Repository
     */
    private static $instance = null;

    /**
     * Get the repository instance
     *
     * Allows for pluggable storage via the 'constellation_client_repository' filter.
     *
     * @return Constellation_Repository
     */
    public static function instance() {
        if ( self::$instance === null ) {
            $repository = new self();

            /**
             * Filter the client repository instance
             *
             * Allows plugins to provide a custom storage implementation.
             *
             * @param Constellation_Repository $repository The repository instance
             */
            self::$instance = apply_filters( 'constellation_client_repository', $repository );
        }

        return self::$instance;
    }

    /**
     * Reset the singleton instance (useful for testing)
     */
    public static function reset_instance() {
        self::$instance = null;
    }

    /**
     * Get searchable fields
     *
     * @return array
     */
    protected function get_searchable_fields() {
        return array( 'name', 'slug' );
    }

    /**
     * Search clients including tag names
     *
     * @param string $query
     * @param array  $fields
     * @param int    $limit
     * @param int    $offset
     * @return array
     */
    public function search( $query, $fields = array(), $limit = 0, $offset = 0 ) {
        if ( empty( $fields ) ) {
            $fields = $this->get_searchable_fields();
        }

        $search_term = '%' . $this->db->esc_like( $query ) . '%';
        $where_parts = array();
        $values = array();

        // Search in indexed fields
        foreach ( $fields as $field ) {
            $where_parts[] = "c.`{$field}` LIKE %s";
            $values[] = $search_term;
        }

        // Search in JSON data
        $where_parts[] = "c.data LIKE %s";
        $values[] = $search_term;

        // Search in tag names
        $where_parts[] = "t.name LIKE %s";
        $values[] = $search_term;

        $sql = "SELECT DISTINCT c.* FROM {$this->table_name} c
                LEFT JOIN {$this->db->prefix}constellation_client_tag ct ON c.id = ct.client_id
                LEFT JOIN {$this->db->prefix}constellation_tag t ON ct.tag_id = t.id
                WHERE " . implode( ' OR ', $where_parts );

        // Add ORDER BY
        $sql .= " ORDER BY c.name ASC";

        // Add LIMIT
        if ( $limit > 0 ) {
            $sql .= " LIMIT %d";
            $values[] = $limit;

            if ( $offset > 0 ) {
                $sql .= " OFFSET %d";
                $values[] = $offset;
            }
        }

        $sql = $this->db->prepare( $sql, $values );
        $rows = $this->db->get_results( $sql );

        return $this->rows_to_models( $rows );
    }

    /**
     * Find clients by status
     *
     * @param string $status
     * @param array  $order_by
     * @param int    $limit
     * @param int    $offset
     * @return array
     */
    public function find_by_status( $status, $order_by = array(), $limit = 0, $offset = 0 ) {
        return $this->find( array( 'status' => $status ), $order_by, $limit, $offset );
    }

    /**
     * Find active clients
     *
     * @param array $order_by
     * @param int   $limit
     * @param int   $offset
     * @return array
     */
    public function find_active( $order_by = array(), $limit = 0, $offset = 0 ) {
        return $this->find_by_status( Constellation_Client::STATUS_ACTIVE, $order_by, $limit, $offset );
    }

    /**
     * Get clients with their tags
     *
     * @param array $criteria
     * @param array $order_by
     * @param int   $limit
     * @param int   $offset
     * @return array
     */
    public function find_with_tags( $criteria = array(), $order_by = array(), $limit = 0, $offset = 0 ) {
        $clients = $this->find( $criteria, $order_by, $limit, $offset );

        if ( empty( $clients ) ) {
            return $clients;
        }

        // Get all client IDs
        $client_ids = array_map( function( $client ) {
            return $client->get_id();
        }, $clients );

        // Load tags for all clients in one query
        $tags_by_client = $this->load_tags_for_clients( $client_ids );

        // Attach tags to clients
        foreach ( $clients as $client ) {
            $client_id = $client->get_id();
            $tags = isset( $tags_by_client[ $client_id ] ) ? $tags_by_client[ $client_id ] : array();
            $client->set_data_value( '_tags', $tags );
        }

        return $clients;
    }

    /**
     * Load tags for multiple clients
     *
     * @param array $client_ids
     * @return array Associative array of client_id => array of tags
     */
    public function load_tags_for_clients( $client_ids ) {
        if ( empty( $client_ids ) ) {
            return array();
        }

        $placeholders = implode( ', ', array_fill( 0, count( $client_ids ), '%s' ) );

        $sql = $this->db->prepare(
            "SELECT ct.client_id, t.*
             FROM {$this->db->prefix}constellation_client_tag ct
             INNER JOIN {$this->db->prefix}constellation_tag t ON ct.tag_id = t.id
             WHERE ct.client_id IN ({$placeholders})
             ORDER BY t.name ASC",
            $client_ids
        );

        $rows = $this->db->get_results( $sql );

        $tags_by_client = array();
        foreach ( $rows as $row ) {
            $client_id = $row->client_id;
            unset( $row->client_id );

            if ( ! isset( $tags_by_client[ $client_id ] ) ) {
                $tags_by_client[ $client_id ] = array();
            }

            $tags_by_client[ $client_id ][] = Constellation_Tag::from_row( $row );
        }

        return $tags_by_client;
    }

    /**
     * Find clients by tag
     *
     * @param string $tag_id
     * @param array  $order_by
     * @param int    $limit
     * @param int    $offset
     * @return array
     */
    public function find_by_tag( $tag_id, $order_by = array(), $limit = 0, $offset = 0 ) {
        $sql = "SELECT c.* FROM {$this->table_name} c
                INNER JOIN {$this->db->prefix}constellation_client_tag ct ON c.id = ct.client_id
                WHERE ct.tag_id = %s";

        $values = array( $tag_id );

        // Build ORDER BY
        if ( ! empty( $order_by ) ) {
            $order_parts = array();
            foreach ( $order_by as $field => $direction ) {
                $direction = strtoupper( $direction ) === 'DESC' ? 'DESC' : 'ASC';
                $order_parts[] = "c.`{$field}` {$direction}";
            }
            $sql .= ' ORDER BY ' . implode( ', ', $order_parts );
        }

        // Build LIMIT
        if ( $limit > 0 ) {
            $sql .= ' LIMIT %d';
            $values[] = $limit;

            if ( $offset > 0 ) {
                $sql .= ' OFFSET %d';
                $values[] = $offset;
            }
        }

        $sql = $this->db->prepare( $sql, $values );
        $rows = $this->db->get_results( $sql );

        return $this->rows_to_models( $rows );
    }

    /**
     * Add a tag to a client
     *
     * @param string $client_id
     * @param string $tag_id
     * @return bool
     */
    public function add_tag( $client_id, $tag_id ) {
        $result = $this->db->insert(
            $this->db->prefix . 'constellation_client_tag',
            array(
                'client_id' => $client_id,
                'tag_id'    => $tag_id,
            ),
            array( '%s', '%s' )
        );

        return $result !== false;
    }

    /**
     * Remove a tag from a client
     *
     * @param string $client_id
     * @param string $tag_id
     * @return bool
     */
    public function remove_tag( $client_id, $tag_id ) {
        $result = $this->db->delete(
            $this->db->prefix . 'constellation_client_tag',
            array(
                'client_id' => $client_id,
                'tag_id'    => $tag_id,
            ),
            array( '%s', '%s' )
        );

        return $result !== false;
    }

    /**
     * Set tags for a client (replaces existing)
     *
     * @param string $client_id
     * @param array  $tag_ids
     * @return bool
     */
    public function set_tags( $client_id, $tag_ids ) {
        // Remove all existing tags
        $this->db->delete(
            $this->db->prefix . 'constellation_client_tag',
            array( 'client_id' => $client_id ),
            array( '%s' )
        );

        // Add new tags
        foreach ( $tag_ids as $tag_id ) {
            $this->add_tag( $client_id, $tag_id );
        }

        return true;
    }

    /**
     * Get tag IDs for a client
     *
     * @param string $client_id
     * @return array
     */
    public function get_tag_ids( $client_id ) {
        $sql = $this->db->prepare(
            "SELECT tag_id FROM {$this->db->prefix}constellation_client_tag WHERE client_id = %s",
            $client_id
        );

        return $this->db->get_col( $sql );
    }

    /**
     * Delete a client and its tag associations
     *
     * @param string $id
     * @return bool
     */
    public function delete( $id ) {
        // Remove tag associations first
        $this->db->delete(
            $this->db->prefix . 'constellation_client_tag',
            array( 'client_id' => $id ),
            array( '%s' )
        );

        return parent::delete( $id );
    }
}
