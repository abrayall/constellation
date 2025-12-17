<?php
/**
 * Abstract MySQL Repository
 *
 * Base class for MySQL-backed repository implementations.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Constellation_MySQL_Repository implements Constellation_Repository {

    /**
     * WordPress database instance
     *
     * @var wpdb
     */
    protected $db;

    /**
     * Table name (without prefix)
     *
     * @var string
     */
    protected $table;

    /**
     * Full table name (with prefix)
     *
     * @var string
     */
    protected $table_name;

    /**
     * Model class name
     *
     * @var string
     */
    protected $model_class;

    /**
     * Whether database supports native JSON
     *
     * @var bool
     */
    protected $supports_json;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_name = $wpdb->prefix . $this->table;
        $this->supports_json = (bool) get_option( 'constellation_db_supports_json', false );
    }

    /**
     * Get the table name
     *
     * @return string
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /**
     * Find entities matching criteria
     *
     * @param array $criteria
     * @param array $order_by
     * @param int   $limit
     * @param int   $offset
     * @return array
     */
    public function find( $criteria = array(), $order_by = array(), $limit = 0, $offset = 0 ) {
        $sql = "SELECT * FROM {$this->table_name}";
        $values = array();

        // Build WHERE clause
        if ( ! empty( $criteria ) ) {
            $where_parts = array();
            foreach ( $criteria as $field => $value ) {
                if ( is_array( $value ) ) {
                    $placeholders = implode( ', ', array_fill( 0, count( $value ), '%s' ) );
                    $where_parts[] = "`{$field}` IN ({$placeholders})";
                    $values = array_merge( $values, $value );
                } elseif ( $value === null ) {
                    $where_parts[] = "`{$field}` IS NULL";
                } else {
                    $where_parts[] = "`{$field}` = %s";
                    $values[] = $value;
                }
            }
            $sql .= ' WHERE ' . implode( ' AND ', $where_parts );
        }

        // Build ORDER BY clause
        if ( ! empty( $order_by ) ) {
            $order_parts = array();
            foreach ( $order_by as $field => $direction ) {
                $direction = strtoupper( $direction ) === 'DESC' ? 'DESC' : 'ASC';
                $order_parts[] = "`{$field}` {$direction}";
            }
            $sql .= ' ORDER BY ' . implode( ', ', $order_parts );
        }

        // Build LIMIT clause
        if ( $limit > 0 ) {
            $sql .= ' LIMIT %d';
            $values[] = $limit;

            if ( $offset > 0 ) {
                $sql .= ' OFFSET %d';
                $values[] = $offset;
            }
        }

        // Execute query
        if ( ! empty( $values ) ) {
            $sql = $this->db->prepare( $sql, $values );
        }

        $rows = $this->db->get_results( $sql );

        return $this->rows_to_models( $rows );
    }

    /**
     * Find a single entity by ID
     *
     * @param string $id
     * @return Constellation_Model|null
     */
    public function find_by_id( $id ) {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %s LIMIT 1",
            $id
        );

        $row = $this->db->get_row( $sql );

        return $row ? $this->row_to_model( $row ) : null;
    }

    /**
     * Find a single entity by slug
     *
     * @param string $slug
     * @return Constellation_Model|null
     */
    public function find_by_slug( $slug ) {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table_name} WHERE slug = %s LIMIT 1",
            $slug
        );

        $row = $this->db->get_row( $sql );

        return $row ? $this->row_to_model( $row ) : null;
    }

    /**
     * Save an entity (insert or update)
     *
     * @param Constellation_Model $entity
     * @return Constellation_Model
     * @throws Exception
     */
    public function save( $entity ) {
        $now = current_time( 'mysql' );

        if ( $entity->is_new() ) {
            // Insert
            $entity->set_id( Constellation_Model::generate_uuid() );
            $entity->set_created_at( $now );
            $entity->set_updated_at( $now );

            // Generate slug if needed
            if ( method_exists( $entity, 'generate_slug' ) ) {
                $entity->generate_slug();
                $entity->set_slug( $this->ensure_unique_slug( $entity->get_slug() ) );
            }

            $data = $entity->to_row();
            $result = $this->db->insert( $this->table_name, $data );

            if ( $result === false ) {
                throw new Exception( 'Failed to insert entity: ' . $this->db->last_error );
            }
        } else {
            // Update
            $entity->set_updated_at( $now );

            $data = $entity->to_row();
            $id = $data['id'];
            unset( $data['id'], $data['created_at'] );

            $result = $this->db->update(
                $this->table_name,
                $data,
                array( 'id' => $id )
            );

            if ( $result === false ) {
                throw new Exception( 'Failed to update entity: ' . $this->db->last_error );
            }
        }

        return $entity;
    }

    /**
     * Delete an entity by ID
     *
     * @param string $id
     * @return bool
     */
    public function delete( $id ) {
        $result = $this->db->delete(
            $this->table_name,
            array( 'id' => $id ),
            array( '%s' )
        );

        return $result !== false;
    }

    /**
     * Search entities by query string
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
            $where_parts[] = "`{$field}` LIKE %s";
            $values[] = $search_term;
        }

        // Search in JSON data (works with both JSON and LONGTEXT)
        $where_parts[] = "data LIKE %s";
        $values[] = $search_term;

        $sql = "SELECT * FROM {$this->table_name} WHERE " . implode( ' OR ', $where_parts );

        // Add ORDER BY
        $sql .= " ORDER BY name ASC";

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
     * Count entities matching criteria
     *
     * @param array $criteria
     * @return int
     */
    public function count( $criteria = array() ) {
        $sql = "SELECT COUNT(*) FROM {$this->table_name}";
        $values = array();

        if ( ! empty( $criteria ) ) {
            $where_parts = array();
            foreach ( $criteria as $field => $value ) {
                if ( is_array( $value ) ) {
                    $placeholders = implode( ', ', array_fill( 0, count( $value ), '%s' ) );
                    $where_parts[] = "`{$field}` IN ({$placeholders})";
                    $values = array_merge( $values, $value );
                } elseif ( $value === null ) {
                    $where_parts[] = "`{$field}` IS NULL";
                } else {
                    $where_parts[] = "`{$field}` = %s";
                    $values[] = $value;
                }
            }
            $sql .= ' WHERE ' . implode( ' AND ', $where_parts );
        }

        if ( ! empty( $values ) ) {
            $sql = $this->db->prepare( $sql, $values );
        }

        return (int) $this->db->get_var( $sql );
    }

    /**
     * Check if an entity exists by ID
     *
     * @param string $id
     * @return bool
     */
    public function exists( $id ) {
        $sql = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE id = %s",
            $id
        );

        return (int) $this->db->get_var( $sql ) > 0;
    }

    /**
     * Ensure a slug is unique by appending a number if necessary
     *
     * @param string $slug
     * @param string $exclude_id Optional ID to exclude from check
     * @return string
     */
    protected function ensure_unique_slug( $slug, $exclude_id = null ) {
        $original_slug = $slug;
        $counter = 1;

        while ( true ) {
            $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE slug = %s";
            $values = array( $slug );

            if ( $exclude_id ) {
                $sql .= " AND id != %s";
                $values[] = $exclude_id;
            }

            $count = (int) $this->db->get_var( $this->db->prepare( $sql, $values ) );

            if ( $count === 0 ) {
                break;
            }

            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Convert a database row to a model
     *
     * @param object $row
     * @return Constellation_Model
     */
    protected function row_to_model( $row ) {
        $class = $this->model_class;
        return $class::from_row( $row );
    }

    /**
     * Convert multiple database rows to models
     *
     * @param array $rows
     * @return array
     */
    protected function rows_to_models( $rows ) {
        return array_map( array( $this, 'row_to_model' ), $rows );
    }

    /**
     * Get fields that are searchable (override in subclass)
     *
     * @return array
     */
    protected function get_searchable_fields() {
        return array( 'name' );
    }
}
