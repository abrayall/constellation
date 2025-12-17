<?php
/**
 * Client Model
 *
 * Represents a client in the system.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Constellation_Client extends Constellation_Model {

    /**
     * Client name
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
     * Client status
     *
     * @var string
     */
    protected $status = 'active';

    /**
     * Valid status values
     */
    const STATUS_ACTIVE   = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_PROSPECT = 'prospect';
    const STATUS_ARCHIVED = 'archived';

    /**
     * Get fields that are stored as columns (not in JSON data)
     *
     * @return array
     */
    public static function get_indexed_fields() {
        return array( 'id', 'name', 'slug', 'status', 'created_at', 'updated_at' );
    }

    /**
     * Get valid status values
     *
     * @return array
     */
    public static function get_statuses() {
        return array(
            self::STATUS_ACTIVE   => __( 'Active', 'constellation' ),
            self::STATUS_INACTIVE => __( 'Inactive', 'constellation' ),
            self::STATUS_PROSPECT => __( 'Prospect', 'constellation' ),
            self::STATUS_ARCHIVED => __( 'Archived', 'constellation' ),
        );
    }

    /**
     * Get client name
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Set client name
     *
     * @param string $name
     * @return $this
     */
    public function set_name( $name ) {
        $this->name = sanitize_text_field( $name );
        return $this;
    }

    /**
     * Get client slug
     *
     * @return string
     */
    public function get_slug() {
        return $this->slug;
    }

    /**
     * Set client slug
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
     * Get client status
     *
     * @return string
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Set client status
     *
     * @param string $status
     * @return $this
     */
    public function set_status( $status ) {
        $valid_statuses = array_keys( self::get_statuses() );
        if ( in_array( $status, $valid_statuses, true ) ) {
            $this->status = $status;
        }
        return $this;
    }

    /**
     * Get status label
     *
     * @return string
     */
    public function get_status_label() {
        $statuses = self::get_statuses();
        return isset( $statuses[ $this->status ] ) ? $statuses[ $this->status ] : $this->status;
    }

    /**
     * Check if client is active
     *
     * @return bool
     */
    public function is_active() {
        return $this->status === self::STATUS_ACTIVE;
    }

    // Extended data convenience methods

    /**
     * Get primary contact email
     *
     * @return string|null
     */
    public function get_email() {
        return $this->get_data_value( 'email' );
    }

    /**
     * Set primary contact email
     *
     * @param string $email
     * @return $this
     */
    public function set_email( $email ) {
        return $this->set_data_value( 'email', sanitize_email( $email ) );
    }

    /**
     * Get phone number
     *
     * @return string|null
     */
    public function get_phone() {
        return $this->get_data_value( 'phone' );
    }

    /**
     * Set phone number
     *
     * @param string $phone
     * @return $this
     */
    public function set_phone( $phone ) {
        return $this->set_data_value( 'phone', sanitize_text_field( $phone ) );
    }

    /**
     * Get website URL
     *
     * @return string|null
     */
    public function get_website() {
        return $this->get_data_value( 'website' );
    }

    /**
     * Set website URL
     *
     * @param string $url
     * @return $this
     */
    public function set_website( $url ) {
        return $this->set_data_value( 'website', esc_url_raw( $url ) );
    }

    /**
     * Get industry
     *
     * @return string|null
     */
    public function get_industry() {
        return $this->get_data_value( 'industry' );
    }

    /**
     * Set industry
     *
     * @param string $industry
     * @return $this
     */
    public function set_industry( $industry ) {
        return $this->set_data_value( 'industry', sanitize_text_field( $industry ) );
    }

    /**
     * Get address
     *
     * @return array|null
     */
    public function get_address() {
        return $this->get_data_value( 'address' );
    }

    /**
     * Set address
     *
     * @param array $address
     * @return $this
     */
    public function set_address( $address ) {
        if ( is_array( $address ) ) {
            $sanitized = array();
            foreach ( $address as $key => $value ) {
                $sanitized[ sanitize_key( $key ) ] = sanitize_text_field( $value );
            }
            $this->set_data_value( 'address', $sanitized );
        }
        return $this;
    }

    /**
     * Get contacts array
     *
     * @return array
     */
    public function get_contacts() {
        return $this->get_data_value( 'contacts', array() );
    }

    /**
     * Set contacts array
     *
     * @param array $contacts
     * @return $this
     */
    public function set_contacts( $contacts ) {
        return $this->set_data_value( 'contacts', $contacts );
    }

    /**
     * Add a contact
     *
     * @param array $contact
     * @return $this
     */
    public function add_contact( $contact ) {
        $contacts = $this->get_contacts();
        $contacts[] = $contact;
        return $this->set_contacts( $contacts );
    }

    /**
     * Get notes
     *
     * @return string|null
     */
    public function get_notes() {
        return $this->get_data_value( 'notes' );
    }

    /**
     * Set notes
     *
     * @param string $notes
     * @return $this
     */
    public function set_notes( $notes ) {
        return $this->set_data_value( 'notes', wp_kses_post( $notes ) );
    }

    /**
     * Get logo attachment ID
     *
     * @return int|null
     */
    public function get_logo() {
        return $this->get_data_value( 'logo' );
    }

    /**
     * Set logo attachment ID
     *
     * @param int $logo_id
     * @return $this
     */
    public function set_logo( $logo_id ) {
        return $this->set_data_value( 'logo', absint( $logo_id ) );
    }

    /**
     * Get description
     *
     * @return string|null
     */
    public function get_description() {
        return $this->get_data_value( 'description' );
    }

    /**
     * Set description
     *
     * @param string $description
     * @return $this
     */
    public function set_description( $description ) {
        return $this->set_data_value( 'description', wp_kses_post( $description ) );
    }

    /**
     * Convert model to array
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'status'     => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'data'       => $this->data,
        );
    }

    /**
     * Get array representation for database row
     *
     * @return array
     */
    public function to_row() {
        return array(
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'status'     => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'data'       => wp_json_encode( $this->data ),
        );
    }

    /**
     * Create model from database row
     *
     * @param object|array $row
     * @return static
     */
    public static function from_row( $row ) {
        $row = (array) $row;

        // Decode JSON data
        if ( isset( $row['data'] ) && is_string( $row['data'] ) ) {
            $row['data'] = json_decode( $row['data'], true );
            if ( ! is_array( $row['data'] ) ) {
                $row['data'] = array();
            }
        }

        return new static( $row );
    }
}
