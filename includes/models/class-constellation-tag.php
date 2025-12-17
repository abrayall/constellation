<?php
/**
 * Tag Model
 *
 * Represents a tag for categorizing clients.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Constellation_Tag extends Constellation_Model {

    /**
     * Tag name
     *
     * @var string
     */
    protected $name;

    /**
     * URL-friendly slug
     *
     * @var string
     */
    protected $slug;

    /**
     * Tag color (hex)
     *
     * @var string
     */
    protected $color;

    /**
     * Tag description
     *
     * @var string
     */
    protected $description;

    /**
     * Default colors for tags
     */
    const DEFAULT_COLORS = array(
        '#3b82f6', // blue
        '#10b981', // green
        '#f59e0b', // amber
        '#ef4444', // red
        '#8b5cf6', // violet
        '#ec4899', // pink
        '#06b6d4', // cyan
        '#f97316', // orange
    );

    /**
     * Get fields that are stored as columns
     *
     * @return array
     */
    public static function get_indexed_fields() {
        return array( 'id', 'name', 'slug', 'color', 'description', 'created_at' );
    }

    /**
     * Get tag name
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Set tag name
     *
     * @param string $name
     * @return $this
     */
    public function set_name( $name ) {
        $this->name = sanitize_text_field( $name );
        return $this;
    }

    /**
     * Get tag slug
     *
     * @return string
     */
    public function get_slug() {
        return $this->slug;
    }

    /**
     * Set tag slug
     *
     * @param string $slug
     * @return $this
     */
    public function set_slug( $slug ) {
        $this->slug = sanitize_title( $slug );
        return $this;
    }

    /**
     * Generate slug from name if not set
     *
     * @return $this
     */
    public function generate_slug() {
        if ( empty( $this->slug ) && ! empty( $this->name ) ) {
            $this->slug = sanitize_title( $this->name );
        }
        return $this;
    }

    /**
     * Get tag color
     *
     * @return string|null
     */
    public function get_color() {
        return $this->color;
    }

    /**
     * Set tag color
     *
     * @param string $color Hex color code
     * @return $this
     */
    public function set_color( $color ) {
        // Validate hex color
        if ( preg_match( '/^#[a-fA-F0-9]{6}$/', $color ) ) {
            $this->color = strtolower( $color );
        }
        return $this;
    }

    /**
     * Get tag description
     *
     * @return string|null
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Set tag description
     *
     * @param string $description
     * @return $this
     */
    public function set_description( $description ) {
        $this->description = sanitize_textarea_field( $description );
        return $this;
    }

    /**
     * Get a random default color
     *
     * @return string
     */
    public static function get_random_color() {
        return self::DEFAULT_COLORS[ array_rand( self::DEFAULT_COLORS ) ];
    }

    /**
     * Convert model to array
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'color'       => $this->color,
            'description' => $this->description,
            'created_at'  => $this->created_at,
        );
    }

    /**
     * Get array representation for database row
     *
     * @return array
     */
    public function to_row() {
        return array(
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'color'       => $this->color,
            'description' => $this->description,
            'created_at'  => $this->created_at,
        );
    }

    /**
     * Create model from database row
     *
     * @param object|array $row
     * @return static
     */
    public static function from_row( $row ) {
        return new static( (array) $row );
    }
}
