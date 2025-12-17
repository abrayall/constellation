<?php
/**
 * Tag Repository
 *
 * MySQL repository implementation for Tag entities.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Constellation_Tag_Repository extends Constellation_MySQL_Repository {

    /**
     * Table name (without prefix)
     *
     * @var string
     */
    protected $table = 'constellation_tag';

    /**
     * Model class name
     *
     * @var string
     */
    protected $model_class = 'Constellation_Tag';

    /**
     * Singleton instance
     *
     * @var Constellation_Repository
     */
    private static $instance = null;

    /**
     * Get the repository instance
     *
     * @return Constellation_Repository
     */
    public static function instance() {
        if ( self::$instance === null ) {
            $repository = new self();

            /**
             * Filter the tag repository instance
             *
             * @param Constellation_Repository $repository The repository instance
             */
            self::$instance = apply_filters( 'constellation_tag_repository', $repository );
        }

        return self::$instance;
    }

    /**
     * Reset the singleton instance
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
        return array( 'name', 'slug', 'description' );
    }

    /**
     * Search tags (override to exclude data column)
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

        foreach ( $fields as $field ) {
            $where_parts[] = "`{$field}` LIKE %s";
            $values[] = $search_term;
        }

        $sql = "SELECT * FROM {$this->table_name} WHERE " . implode( ' OR ', $where_parts );
        $sql .= " ORDER BY name ASC";

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
     * Save a tag (override to handle color default)
     *
     * @param Constellation_Model $entity
     * @return Constellation_Model
     * @throws Exception
     */
    public function save( $entity ) {
        // Set default color to blue if not set
        if ( $entity->is_new() && empty( $entity->get_color() ) ) {
            $entity->set_color( '#3b82f6' );
        }

        return parent::save( $entity );
    }

    /**
     * Find or create a tag by name
     *
     * @param string $name
     * @return Constellation_Tag
     */
    public function find_or_create( $name ) {
        $slug = sanitize_title( $name );
        $tag = $this->find_by_slug( $slug );

        if ( ! $tag ) {
            $tag = new Constellation_Tag( array(
                'name'  => $name,
                'color' => '#3b82f6', // Default blue
            ) );
            $tag = $this->save( $tag );
        }

        return $tag;
    }

    /**
     * Get tags with client counts
     *
     * @param array $order_by
     * @return array
     */
    public function find_with_counts( $order_by = array( 'name' => 'ASC' ) ) {
        $order_sql = '';
        if ( ! empty( $order_by ) ) {
            $order_parts = array();
            foreach ( $order_by as $field => $direction ) {
                $direction = strtoupper( $direction ) === 'DESC' ? 'DESC' : 'ASC';
                $order_parts[] = "t.`{$field}` {$direction}";
            }
            $order_sql = 'ORDER BY ' . implode( ', ', $order_parts );
        }

        $sql = "SELECT t.*, COUNT(ct.client_id) as client_count
                FROM {$this->table_name} t
                LEFT JOIN {$this->db->prefix}constellation_client_tag ct ON t.id = ct.tag_id
                GROUP BY t.id
                {$order_sql}";

        $rows = $this->db->get_results( $sql );

        $tags = array();
        foreach ( $rows as $row ) {
            $tag = Constellation_Tag::from_row( $row );
            $tag->set_data_value( '_client_count', (int) $row->client_count );
            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Get tags for a specific client
     *
     * @param string $client_id
     * @return array
     */
    public function find_by_client( $client_id ) {
        $sql = $this->db->prepare(
            "SELECT t.* FROM {$this->table_name} t
             INNER JOIN {$this->db->prefix}constellation_client_tag ct ON t.id = ct.tag_id
             WHERE ct.client_id = %s
             ORDER BY t.name ASC",
            $client_id
        );

        $rows = $this->db->get_results( $sql );

        return $this->rows_to_models( $rows );
    }

    /**
     * Delete a tag (also removes associations)
     *
     * @param string $id
     * @return bool
     */
    public function delete( $id ) {
        // Remove client associations first
        $this->db->delete(
            $this->db->prefix . 'constellation_client_tag',
            array( 'tag_id' => $id ),
            array( '%s' )
        );

        return parent::delete( $id );
    }

    /**
     * Merge tags (move all clients from source to target, then delete source)
     *
     * @param string $source_tag_id Tag to merge from
     * @param string $target_tag_id Tag to merge into
     * @return bool
     */
    public function merge( $source_tag_id, $target_tag_id ) {
        if ( $source_tag_id === $target_tag_id ) {
            return false;
        }

        // Get clients with source tag
        $client_ids = $this->db->get_col(
            $this->db->prepare(
                "SELECT client_id FROM {$this->db->prefix}constellation_client_tag WHERE tag_id = %s",
                $source_tag_id
            )
        );

        // Add target tag to those clients (ignore duplicates)
        foreach ( $client_ids as $client_id ) {
            $this->db->query(
                $this->db->prepare(
                    "INSERT IGNORE INTO {$this->db->prefix}constellation_client_tag (client_id, tag_id) VALUES (%s, %s)",
                    $client_id,
                    $target_tag_id
                )
            );
        }

        // Delete source tag (and its associations)
        return $this->delete( $source_tag_id );
    }
}
