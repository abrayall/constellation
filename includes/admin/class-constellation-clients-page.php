<?php
/**
 * Clients List Page
 *
 * Admin page for listing and managing clients.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Constellation_Clients_Page {

    /**
     * Client service
     *
     * @var Constellation_Client_Service
     */
    private $service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->service = Constellation_Client_Service::instance();
    }

    /**
     * Render the page
     */
    public function render() {
        $current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $search_query = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';

        // Get counts for status filter
        $counts = $this->service->get_counts_by_status();

        // Build criteria based on filters
        $criteria = array();
        if ( $current_status && $current_status !== 'all' ) {
            $criteria['status'] = $current_status;
        }

        // Get clients
        if ( $search_query ) {
            $clients = $this->service->search( $search_query );
        } else {
            $clients = $this->service->get_all( array(
                'status'    => $current_status ?: null,
                'order_by'  => array( 'name' => 'ASC' ),
                'with_tags' => true,
            ) );
        }

        // Start page output
        echo Mosaic::page_start( 'Clients' );

        // Status filter cards
        $this->render_status_filters( $counts, $current_status );

        // Search box
        $this->render_search_box( $search_query );

        // Clients table
        $is_filtered = ! empty( $search_query ) || ( ! empty( $current_status ) && $current_status !== 'all' );
        $this->render_clients_table( $clients, $is_filtered );

        echo Mosaic::page_end();
    }

    /**
     * Render status filter cards
     *
     * @param array  $counts Current counts by status
     * @param string $current_status Currently selected status
     */
    private function render_status_filters( $counts, $current_status ) {
        $statuses = array(
            'all'      => array( 'label' => 'All Clients', 'variant' => 'primary' ),
            'active'   => array( 'label' => 'Active', 'variant' => 'success' ),
            'prospect' => array( 'label' => 'Prospects', 'variant' => 'warning' ),
            'inactive' => array( 'label' => 'Inactive', 'variant' => 'default' ),
            'archived' => array( 'label' => 'Archived', 'variant' => 'default' ),
        );

        $stats = array();
        foreach ( $statuses as $status => $config ) {
            $count = $status === 'all' ? $counts['all'] : ( isset( $counts[ $status ] ) ? $counts[ $status ] : 0 );
            $is_active = ( $current_status === $status ) || ( empty( $current_status ) && $status === 'all' );

            $stats[] = array(
                'label'     => $config['label'],
                'value'     => $count,
                'variant'   => $config['variant'],
                'clickable' => true,
                'active'    => $is_active,
                'filter'    => $status,
                'attrs'     => array(
                    'data-href' => add_query_arg( 'status', $status === 'all' ? '' : $status, admin_url( 'admin.php?page=constellation-clients' ) ),
                ),
            );
        }

        echo '<div class="mosaic-mb-4">';
        echo Mosaic_Card::stats_grid( $stats );
        echo '</div>';
    }

    /**
     * Render search box with add button
     *
     * @param string $search_query Current search query
     */
    private function render_search_box( $search_query ) {
        $add_url = admin_url( 'admin.php?page=constellation-client-edit' );

        echo '<div class="constellation-toolbar mosaic-mb-4">';

        // Search form
        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        echo '<input type="hidden" name="page" value="constellation-clients">';
        if ( isset( $_GET['status'] ) ) {
            echo '<input type="hidden" name="status" value="' . esc_attr( $_GET['status'] ) . '">';
        }
        echo '<div class="mosaic-search-box">';
        echo '<input type="search" name="search" class="mosaic-input" placeholder="Search..." value="' . esc_attr( $search_query ) . '">';
        echo Mosaic::button( 'Search', array( 'type' => 'submit', 'variant' => 'secondary' ) );
        echo '</div>';
        echo '</form>';

        // Add button
        echo Mosaic::button( 'Client', array(
            'variant' => 'primary',
            'icon'    => 'plus-alt',
            'href'    => $add_url,
        ) );

        echo '</div>';
    }

    /**
     * Render clients table
     *
     * @param array $clients Array of client objects
     * @param bool  $is_filtered Whether results are filtered
     */
    private function render_clients_table( $clients, $is_filtered = false ) {
        $table = new Mosaic_Data_Table();

        $table->set_columns( array(
            'name'   => 'Client Name',
            'status' => 'Status',
            'tags'   => 'Tags',
            'email'  => 'Email',
            'created_at' => 'Created',
        ) );

        $edit_url = admin_url( 'admin.php?page=constellation-client-edit&id={id}' );
        $delete_url = wp_nonce_url(
            admin_url( 'admin.php?page=constellation-clients&action=delete&id={id}' ),
            'delete_client_{id}'
        );

        $table->enable_edit( $edit_url );
        $table->enable_delete( function( $row ) {
            return wp_nonce_url(
                admin_url( 'admin.php?page=constellation-clients&action=delete&id=' . $row['id'] ),
                'delete_client_' . $row['id']
            );
        } );

        if ( $is_filtered ) {
            $table->set_empty_state(
                'No clients found',
                'No clients match the current filter.',
                'groups'
            );
        } else {
            $table->set_empty_state(
                'No clients found',
                'Get started by adding your first client.',
                'groups',
                Mosaic::button( 'Client', array(
                    'variant' => 'primary',
                    'size'    => 'sm',
                    'href'    => admin_url( 'admin.php?page=constellation-client-edit' ),
                ) )
            );
        }

        foreach ( $clients as $client ) {
            $tags_html = $this->render_tags( $client->get_data_value( '_tags', array() ) );
            $status_badge = $this->get_status_badge( $client->get_status() );

            $table->add_row( array(
                'id'         => $client->get_id(),
                'name'       => sprintf(
                    '<strong><a href="%s">%s</a></strong>',
                    esc_url( admin_url( 'admin.php?page=constellation-client-edit&id=' . $client->get_id() ) ),
                    esc_html( $client->get_name() )
                ),
                'status'     => $status_badge,
                'tags'       => $tags_html,
                'email'      => esc_html( $client->get_email() ?: '—' ),
                'created_at' => esc_html( date_i18n( get_option( 'date_format' ), strtotime( $client->get_created_at() ) ) ),
            ) );
        }

        $table->set_footer( '', sprintf( '%d clients', count( $clients ) ) );
        $table->display();
    }

    /**
     * Render tags as badges
     *
     * @param array $tags Array of tag objects
     * @return string HTML
     */
    private function render_tags( $tags ) {
        if ( empty( $tags ) ) {
            return '<span class="mosaic-text-muted">—</span>';
        }

        $html = '<div class="constellation-tags">';
        foreach ( $tags as $tag ) {
            $color = $tag->get_color() ?: '#3b82f6';
            $description = $tag->get_description();
            $html .= sprintf(
                '<span class="constellation-tag" style="background-color: %s;"%s>%s</span>',
                esc_attr( $color ),
                $description ? ' title="' . esc_attr( $description ) . '"' : '',
                esc_html( $tag->get_name() )
            );
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Get status badge HTML
     *
     * @param string $status Status value
     * @return string HTML
     */
    private function get_status_badge( $status ) {
        $variants = array(
            'active'   => 'success',
            'inactive' => 'default',
            'prospect' => 'warning',
            'archived' => 'default',
        );

        $labels = Constellation_Client::get_statuses();
        $variant = isset( $variants[ $status ] ) ? $variants[ $status ] : 'default';
        $label = isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );

        return Mosaic::badge( $label, $variant );
    }

    /**
     * Handle delete action
     */
    public function handle_actions() {
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'delete' ) {
            return;
        }

        if ( ! isset( $_GET['id'] ) ) {
            return;
        }

        $id = sanitize_text_field( $_GET['id'] );

        // Verify nonce
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_client_' . $id ) ) {
            wp_die( 'Security check failed.' );
        }

        $result = $this->service->delete( $id );

        if ( is_wp_error( $result ) ) {
            add_settings_error(
                'constellation_messages',
                'delete_failed',
                $result->get_error_message(),
                'error'
            );
        } else {
            add_settings_error(
                'constellation_messages',
                'deleted',
                __( 'Client deleted successfully.', 'constellation' ),
                'success'
            );
        }

        // Redirect to remove action from URL
        wp_redirect( admin_url( 'admin.php?page=constellation-clients' ) );
        exit;
    }
}
