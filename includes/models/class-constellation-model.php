<?php
/**
 * Base Model Class
 *
 * Abstract base class for all Constellation models.
 * Implements the hybrid schema pattern with indexed fields + JSON data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Constellation_Model {

    /**
     * Model ID (UUID)
     *
     * @var string
     */
    protected $id;

    /**
     * Created timestamp
     *
     * @var string
     */
    protected $created_at;

    /**
     * Updated timestamp
     *
     * @var string
     */
    protected $updated_at;

    /**
     * Extended data (stored as JSON)
     *
     * @var array
     */
    protected $data = array();

    /**
     * Constructor
     *
     * @param array $attributes Optional attributes to set
     */
    public function __construct( $attributes = array() ) {
        if ( ! empty( $attributes ) ) {
            $this->fill( $attributes );
        }
    }

    /**
     * Generate a UUID v4
     *
     * @return string
     */
    public static function generate_uuid() {
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            return wp_generate_uuid4();
        }

        // Fallback for older WordPress versions
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff )
        );
    }

    /**
     * Get the model ID
     *
     * @return string
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Set the model ID
     *
     * @param string $id
     * @return $this
     */
    public function set_id( $id ) {
        $this->id = $id;
        return $this;
    }

    /**
     * Get created timestamp
     *
     * @return string
     */
    public function get_created_at() {
        return $this->created_at;
    }

    /**
     * Set created timestamp
     *
     * @param string $created_at
     * @return $this
     */
    public function set_created_at( $created_at ) {
        $this->created_at = $created_at;
        return $this;
    }

    /**
     * Get updated timestamp
     *
     * @return string
     */
    public function get_updated_at() {
        return $this->updated_at;
    }

    /**
     * Set updated timestamp
     *
     * @param string $updated_at
     * @return $this
     */
    public function set_updated_at( $updated_at ) {
        $this->updated_at = $updated_at;
        return $this;
    }

    /**
     * Get extended data array
     *
     * @return array
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Set extended data array
     *
     * @param array $data
     * @return $this
     */
    public function set_data( $data ) {
        $this->data = is_array( $data ) ? $data : array();
        return $this;
    }

    /**
     * Get a value from extended data
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get_data_value( $key, $default = null ) {
        return isset( $this->data[ $key ] ) ? $this->data[ $key ] : $default;
    }

    /**
     * Set a value in extended data
     *
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function set_data_value( $key, $value ) {
        $this->data[ $key ] = $value;
        return $this;
    }

    /**
     * Remove a value from extended data
     *
     * @param string $key
     * @return $this
     */
    public function remove_data_value( $key ) {
        unset( $this->data[ $key ] );
        return $this;
    }

    /**
     * Check if model is new (not yet persisted)
     *
     * @return bool
     */
    public function is_new() {
        return empty( $this->id );
    }

    /**
     * Fill model with attributes
     *
     * @param array $attributes
     * @return $this
     */
    public function fill( $attributes ) {
        foreach ( $attributes as $key => $value ) {
            $method = 'set_' . $key;
            if ( method_exists( $this, $method ) ) {
                $this->$method( $value );
            }
        }
        return $this;
    }

    /**
     * Get fields that are stored as columns (not in JSON data)
     *
     * @return array
     */
    abstract public static function get_indexed_fields();

    /**
     * Convert model to array
     *
     * @return array
     */
    abstract public function to_array();

    /**
     * Create model from array
     *
     * @param array $data
     * @return static
     */
    public static function from_array( $data ) {
        return new static( $data );
    }

    /**
     * Convert model to JSON string
     *
     * @return string
     */
    public function to_json() {
        return wp_json_encode( $this->to_array() );
    }
}
