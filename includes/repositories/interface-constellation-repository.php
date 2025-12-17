<?php
/**
 * Repository Interface
 *
 * Defines the contract for all storage implementations.
 * This allows for pluggable storage backends (MySQL, SQLite, API, etc.)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Constellation_Repository {

    /**
     * Find entities matching criteria
     *
     * @param array  $criteria  Key-value pairs to filter by
     * @param array  $order_by  Array of field => direction pairs
     * @param int    $limit     Maximum number of results
     * @param int    $offset    Number of results to skip
     * @return array Array of model objects
     */
    public function find( $criteria = array(), $order_by = array(), $limit = 0, $offset = 0 );

    /**
     * Find a single entity by ID
     *
     * @param string $id UUID
     * @return Constellation_Model|null
     */
    public function find_by_id( $id );

    /**
     * Find a single entity by slug
     *
     * @param string $slug
     * @return Constellation_Model|null
     */
    public function find_by_slug( $slug );

    /**
     * Save an entity (insert or update)
     *
     * @param Constellation_Model $entity
     * @return Constellation_Model The saved entity
     * @throws Exception On save failure
     */
    public function save( $entity );

    /**
     * Delete an entity by ID
     *
     * @param string $id UUID
     * @return bool True on success
     */
    public function delete( $id );

    /**
     * Search entities by query string
     *
     * @param string $query   Search query
     * @param array  $fields  Fields to search in
     * @param int    $limit   Maximum number of results
     * @param int    $offset  Number of results to skip
     * @return array Array of model objects
     */
    public function search( $query, $fields = array(), $limit = 0, $offset = 0 );

    /**
     * Count entities matching criteria
     *
     * @param array $criteria Key-value pairs to filter by
     * @return int
     */
    public function count( $criteria = array() );

    /**
     * Check if an entity exists by ID
     *
     * @param string $id UUID
     * @return bool
     */
    public function exists( $id );
}
