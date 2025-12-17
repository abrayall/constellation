<?php
/**
 * Client Edit Page
 *
 * Admin page for adding and editing clients.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Constellation_Client_Edit_Page {

    /**
     * Client service
     *
     * @var Constellation_Client_Service
     */
    private $service;

    /**
     * Tag repository
     *
     * @var Constellation_Tag_Repository
     */
    private $tag_repository;

    /**
     * Constructor
     */
    public function __construct() {
        $this->service = Constellation_Client_Service::instance();
        $this->tag_repository = Constellation_Tag_Repository::instance();
    }

    /**
     * Render the page
     */
    public function render() {
        $client_id = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '';
        $client = null;
        $is_new = empty( $client_id );

        if ( ! $is_new ) {
            $client = $this->service->get( $client_id );
            if ( ! $client ) {
                echo Mosaic::alert( 'Client not found.', 'error' );
                return;
            }
        }

        $title = $is_new ? 'Add New Client' : 'Edit Client';

        echo Mosaic::page_start( $title );

        // Show any messages
        settings_errors( 'constellation_messages' );

        // Render form
        $this->render_form( $client, $is_new );

        echo Mosaic::page_end();
    }

    /**
     * Render the client form
     *
     * @param Constellation_Client|null $client Client object or null for new
     * @param bool $is_new Whether this is a new client
     */
    private function render_form( $client, $is_new ) {
        $all_tags = $this->tag_repository->find( array(), array( 'name' => 'ASC' ) );
        $client_tag_ids = array();

        if ( $client ) {
            $client_tag_ids = $this->service->get_repository()->get_tag_ids( $client->get_id() );
        }

        ?>
        <form id="constellation-client-form" method="post">
            <?php wp_nonce_field( 'constellation_save_client', 'constellation_client_nonce' ); ?>
            <?php if ( $client ) : ?>
                <input type="hidden" name="client_id" value="<?php echo esc_attr( $client->get_id() ); ?>">
            <?php endif; ?>

            <div class="mosaic-grid mosaic-grid-cols-3 mosaic-gap-4">
                <div class="mosaic-col-span-2">
                    <?php $this->render_tabbed_form( $client, $is_new, $all_tags, $client_tag_ids ); ?>
                </div>
                <div>
                    <?php $this->render_sidebar( $client, $is_new ); ?>
                </div>
            </div>
        </form>
        <?php
    }

    /**
     * Render tabbed form
     *
     * @param Constellation_Client|null $client
     * @param bool $is_new
     * @param array $all_tags
     * @param array $client_tag_ids
     */
    private function render_tabbed_form( $client, $is_new, $all_tags, $client_tag_ids ) {
        $tabs = new Mosaic_Tabs( array( 'hash_navigation' => true ) );

        // General tab
        $tabs->add_tab( 'general', 'General', $this->render_general_tab( $client, $all_tags, $client_tag_ids ), array( 'active' => true ) );

        // Contact tab
        $tabs->add_tab( 'contact', 'Contact', $this->render_contact_tab( $client ) );

        // Address tab
        $tabs->add_tab( 'address', 'Address', $this->render_address_tab( $client ) );

        // Notes tab
        $tabs->add_tab( 'notes', 'Notes', $this->render_notes_tab( $client ) );

        // Render tabs
        $tabs->display();

        // Render action buttons
        echo '<div class="constellation-form-actions">';
        printf(
            '<button type="submit" class="mosaic-btn mosaic-btn-primary">%s</button>',
            $is_new ? 'Add' : 'Save'
        );
        printf(
            '<a href="%s" class="mosaic-btn mosaic-btn-secondary">Cancel</a>',
            esc_url( admin_url( 'admin.php?page=constellation-clients' ) )
        );
        echo '</div>';
    }

    /**
     * Render general tab content
     *
     * @param Constellation_Client|null $client
     * @param array $all_tags
     * @param array $client_tag_ids
     * @return string HTML
     */
    private function render_general_tab( $client, $all_tags = array(), $client_tag_ids = array() ) {
        $html = '<div class="constellation-form-narrow">';

        // Logo
        $html .= Mosaic::media_selector( 'logo', array(
            'value'       => $client ? $client->get_logo() : '',
            'label'       => 'Logo',
            'button_text' => 'Select Logo',
        ) );

        $form = new Mosaic_Form();

        $form->add_select( 'status', 'Status', Constellation_Client::get_statuses(), array(
            'value' => $client ? $client->get_status() : 'active',
        ) );

        $form->add_text( 'name', 'Name', array(
            'value'       => $client ? $client->get_name() : '',
            'placeholder' => 'Enter name',
            'required'    => true,
        ) );

        $form->add_textarea( 'description', 'Description', array(
            'value'       => $client ? $client->get_description() : '',
            'placeholder' => 'Brief description of the client...',
            'rows'        => 3,
        ) );

        $form->add_text( 'industry', 'Industry', array(
            'value'       => $client ? $client->get_industry() : '',
            'placeholder' => 'e.g. Technology, Healthcare, Finance',
        ) );

        $html .= $form->render();

        // Tags
        $html .= $this->render_tags_field( $all_tags, $client_tag_ids );

        $html .= '</div>';

        return $html;
    }

    /**
     * Render tags field
     *
     * @param array $all_tags
     * @param array $selected_ids
     * @return string HTML
     */
    private function render_tags_field( $all_tags, $selected_ids ) {
        // Get selected tag names
        $selected_names = array();
        $all_tag_names = array();

        foreach ( $all_tags as $tag ) {
            $all_tag_names[] = $tag->get_name();
            if ( in_array( $tag->get_id(), $selected_ids, true ) ) {
                $selected_names[] = $tag->get_name();
            }
        }

        $html = '<div class="mosaic-form-group">';
        $html .= '<label class="mosaic-label">Tags</label>';
        $html .= sprintf(
            '<div data-mosaic-tags data-name="tags" data-tags="%s" data-suggestions="%s" data-placeholder="Add a tag..." data-show-all-on-focus="true"></div>',
            esc_attr( implode( ',', $selected_names ) ),
            esc_attr( implode( ',', $all_tag_names ) )
        );
        $html .= '</div>';

        return $html;
    }

    /**
     * Render contact tab content
     *
     * @param Constellation_Client|null $client
     * @return string HTML
     */
    private function render_contact_tab( $client ) {
        $html = '<div class="constellation-form-narrow">';

        $form = new Mosaic_Form();

        $form->add_email( 'email', 'Email', array(
            'value'       => $client ? $client->get_email() : '',
            'placeholder' => 'contact@example.com',
        ) );

        $form->add_text( 'phone', 'Phone', array(
            'value'       => $client ? $client->get_phone() : '',
            'placeholder' => '+1 (555) 123-4567',
        ) );

        $form->add_text( 'website', 'Website', array(
            'value'       => $client ? $client->get_website() : '',
            'placeholder' => 'https://example.com',
        ) );

        $html .= $form->render();
        $html .= '</div>';

        return $html;
    }

    /**
     * Render address tab content
     *
     * @param Constellation_Client|null $client
     * @return string HTML
     */
    private function render_address_tab( $client ) {
        $address = $client ? $client->get_address() : array();

        $html = '<div class="constellation-form-narrow">';

        $form = new Mosaic_Form();

        $form->add_text( 'address[street]', 'Street', array(
            'value' => isset( $address['street'] ) ? $address['street'] : '',
        ) );

        $form->add_text( 'address[city]', 'City', array(
            'value' => isset( $address['city'] ) ? $address['city'] : '',
        ) );

        $form->add_text( 'address[state]', 'State / Province', array(
            'value' => isset( $address['state'] ) ? $address['state'] : '',
        ) );

        $form->add_text( 'address[zip]', 'ZIP / Postal Code', array(
            'value' => isset( $address['zip'] ) ? $address['zip'] : '',
        ) );

        $form->add_text( 'address[country]', 'Country', array(
            'value' => isset( $address['country'] ) ? $address['country'] : '',
        ) );

        $html .= $form->render();
        $html .= '</div>';

        return $html;
    }

    /**
     * Render notes tab content
     *
     * @param Constellation_Client|null $client
     * @return string HTML
     */
    private function render_notes_tab( $client ) {
        $html = '<div class="constellation-notes-tab">';
        $html .= sprintf(
            '<textarea name="notes" class="mosaic-textarea" placeholder="Add notes...">%s</textarea>',
            esc_textarea( $client ? $client->get_notes() : '' )
        );
        $html .= '</div>';

        return $html;
    }

    /**
     * Render sidebar
     *
     * @param Constellation_Client|null $client
     * @param bool $is_new
     */
    private function render_sidebar( $client, $is_new ) {
        // Info card for existing clients
        if ( ! $is_new && $client ) {
            echo '<div class="constellation-sidebar-details">';

            $info_html = '<dl class="constellation-meta-list">';
            $info_html .= sprintf( '<dt>ID</dt><dd>%s</dd>', esc_html( $client->get_id() ) );
            $info_html .= sprintf(
                '<dt>Created</dt><dd>%s</dd>',
                esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $client->get_created_at() ) ) )
            );
            $info_html .= sprintf(
                '<dt>Last Updated</dt><dd>%s</dd>',
                esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $client->get_updated_at() ) ) )
            );
            $info_html .= '</dl>';

            $info_card = new Mosaic_Card();
            $info_card->set_header( 'History' );
            $info_card->set_body( $info_html );
            $info_card->display();

            echo '</div>';
        }
    }

    /**
     * Handle form submission
     */
    public function handle_save() {
        if ( ! isset( $_POST['constellation_client_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['constellation_client_nonce'], 'constellation_save_client' ) ) {
            wp_die( 'Security check failed.' );
        }

        $client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( $_POST['client_id'] ) : '';
        $is_new = empty( $client_id );

        // Build tags array from comma-separated string (from Mosaic tag editor)
        $tags_string = isset( $_POST['tags'] ) ? sanitize_text_field( $_POST['tags'] ) : '';
        $tags = array_filter( array_map( 'trim', explode( ',', $tags_string ) ) );

        // Build data array
        $data = array(
            'name'        => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
            'status'      => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'active',
            'logo'        => isset( $_POST['logo'] ) ? absint( $_POST['logo'] ) : 0,
            'description' => isset( $_POST['description'] ) ? wp_kses_post( $_POST['description'] ) : '',
            'email'       => isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '',
            'phone'       => isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '',
            'website'     => isset( $_POST['website'] ) ? esc_url_raw( $_POST['website'] ) : '',
            'industry'    => isset( $_POST['industry'] ) ? sanitize_text_field( $_POST['industry'] ) : '',
            'notes'       => isset( $_POST['notes'] ) ? wp_kses_post( $_POST['notes'] ) : '',
            'tags'        => $tags,
        );

        // Handle address
        if ( isset( $_POST['address'] ) && is_array( $_POST['address'] ) ) {
            $address = array();
            foreach ( $_POST['address'] as $key => $value ) {
                $address[ sanitize_key( $key ) ] = sanitize_text_field( $value );
            }
            $data['address'] = $address;
        }

        // Create or update
        if ( $is_new ) {
            $result = $this->service->create( $data );
        } else {
            $result = $this->service->update( $client_id, $data );
        }

        if ( is_wp_error( $result ) ) {
            add_settings_error(
                'constellation_messages',
                'save_failed',
                $result->get_error_message(),
                'error'
            );
            return;
        }

        // Redirect back to clients list
        wp_redirect( admin_url( 'admin.php?page=constellation-clients' ) );
        exit;
    }
}
