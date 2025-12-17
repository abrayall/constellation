<?php
/**
 * Tags Management Page
 *
 * Admin page for managing client tags.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Constellation_Tags_Page {

    /**
     * Tag repository
     *
     * @var Constellation_Tag_Repository
     */
    private $repository;

    /**
     * Constructor
     */
    public function __construct() {
        $this->repository = Constellation_Tag_Repository::instance();
    }

    /**
     * Render the page
     */
    public function render() {
        $search_query = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';

        if ( $search_query ) {
            $tags = $this->repository->search( $search_query );
            // Load counts for search results
            foreach ( $tags as $tag ) {
                $tag->set_data_value( '_client_count', 0 );
            }
        } else {
            $tags = $this->repository->find_with_counts();
        }

        echo Mosaic::page_start( 'Tags' );

        // Show any messages
        settings_errors( 'constellation_messages' );

        // Toolbar with search and Add button
        echo '<div class="constellation-toolbar mosaic-mb-4">';

        // Search form
        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        echo '<input type="hidden" name="page" value="constellation-tags">';
        echo '<div class="mosaic-search-box">';
        echo '<input type="search" name="search" class="mosaic-input" placeholder="Search..." value="' . esc_attr( $search_query ) . '">';
        echo Mosaic::button( 'Search', array( 'type' => 'submit', 'variant' => 'secondary' ) );
        echo '</div>';
        echo '</form>';

        echo Mosaic::button( 'Tag', array(
            'variant' => 'primary',
            'icon'    => 'plus-alt',
            'class'   => 'constellation-add-tag-btn',
        ) );
        echo '</div>';

        // Tags table
        $is_filtered = ! empty( $search_query );
        $this->render_tags_table( $tags, $is_filtered );

        // Modal for add/edit
        $this->render_modal();

        echo Mosaic::page_end();
    }

    /**
     * Render tags table
     *
     * @param array $tags Array of tag objects
     * @param bool  $is_filtered Whether results are filtered
     */
    private function render_tags_table( $tags, $is_filtered = false ) {
        $table = new Mosaic_Data_Table();

        $table->set_columns( array(
            'name'         => 'Tag',
            'description'  => 'Description',
            'client_count' => 'Clients',
            'created_at'   => 'Created',
        ) );

        $table->enable_edit( function( $row ) {
            return '#';
        } );

        $table->enable_delete( function( $row ) {
            return wp_nonce_url(
                admin_url( 'admin.php?page=constellation-tags&action=delete&id=' . $row['id'] ),
                'delete_tag_' . $row['id']
            );
        } );

        if ( $is_filtered ) {
            $table->set_empty_state(
                'No tags found',
                'No tags match the current search.',
                'tag'
            );
        } else {
            $table->set_empty_state(
                'No tags yet',
                'Create your first tag to start organizing clients.',
                'tag',
                Mosaic::button( 'Tag', array(
                    'variant' => 'primary',
                    'size'    => 'sm',
                    'class'   => 'constellation-add-tag-btn',
                ) )
            );
        }

        foreach ( $tags as $tag ) {
            $color = $tag->get_color() ?: '#3b82f6';
            $name_html = sprintf(
                '<span class="constellation-tag" style="background-color: %s;">%s</span>',
                esc_attr( $color ),
                esc_html( $tag->get_name() )
            );

            $count = $tag->get_data_value( '_client_count', 0 );

            $description = $tag->get_description();
            $table->add_row( array(
                'id'           => $tag->get_id(),
                'name'         => $name_html,
                'description'  => $description ? esc_html( $description ) : '<span class="mosaic-text-muted">—</span>',
                'client_count' => $count > 0 ? $count : '0',
                'created_at'   => esc_html( date_i18n( get_option( 'date_format' ), strtotime( $tag->get_created_at() ) ) ),
                '_name'        => $tag->get_name(),
                '_color'       => $color,
            ), array(
                'attrs' => array(
                    'data-id'          => $tag->get_id(),
                    'data-description' => $description ?: '',
                ),
            ) );
        }

        $table->set_footer( '', sprintf( '%d tags', count( $tags ) ) );
        $table->display();
    }

    /**
     * Render add/edit modal
     */
    private function render_modal() {
        $colors = Constellation_Tag::DEFAULT_COLORS;

        $color_html = '<div class="constellation-color-picker">';
        foreach ( $colors as $color ) {
            $color_html .= sprintf(
                '<label class="constellation-color-option">
                    <input type="radio" name="tag_color" value="%s">
                    <span style="background-color: %s;"></span>
                </label>',
                esc_attr( $color ),
                esc_attr( $color )
            );
        }
        $color_html .= '</div>';

        ?>
        <div id="constellation-tag-modal" style="display: none;">
            <form id="constellation-tag-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=constellation-tags' ) ); ?>">
                <?php wp_nonce_field( 'constellation_save_tag', 'constellation_tag_nonce' ); ?>
                <input type="hidden" name="action" value="save_tag">
                <input type="hidden" name="tag_id" id="tag-id" value="">

                <div class="mosaic-form-group">
                    <label class="mosaic-label" for="tag-name">Name</label>
                    <input type="text" name="tag_name" id="tag-name" class="mosaic-input" placeholder="Enter tag name" required>
                </div>

                <div class="mosaic-form-group">
                    <label class="mosaic-label" for="tag-description">Description</label>
                    <textarea name="tag_description" id="tag-description" class="mosaic-textarea" rows="3" placeholder="Optional description"></textarea>
                </div>

                <div class="mosaic-form-group">
                    <label class="mosaic-label">Color</label>
                    <?php echo $color_html; ?>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Handle form submission
     */
    public function handle_save() {
        if ( ! isset( $_POST['constellation_tag_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['constellation_tag_nonce'], 'constellation_save_tag' ) ) {
            wp_die( 'Security check failed.' );
        }

        $tag_id = isset( $_POST['tag_id'] ) ? sanitize_text_field( $_POST['tag_id'] ) : '';
        $is_new = empty( $tag_id );

        $name = isset( $_POST['tag_name'] ) ? sanitize_text_field( $_POST['tag_name'] ) : '';
        $description = isset( $_POST['tag_description'] ) ? sanitize_textarea_field( $_POST['tag_description'] ) : '';
        $color = isset( $_POST['tag_color'] ) ? sanitize_text_field( $_POST['tag_color'] ) : '';

        if ( empty( $name ) ) {
            add_settings_error(
                'constellation_messages',
                'name_required',
                __( 'Tag name is required.', 'constellation' ),
                'error'
            );
            return;
        }

        try {
            if ( $is_new ) {
                $tag = new Constellation_Tag( array(
                    'name'        => $name,
                    'description' => $description,
                    'color'       => $color,
                ) );
            } else {
                $tag = $this->repository->find_by_id( $tag_id );
                if ( ! $tag ) {
                    throw new Exception( 'Tag not found.' );
                }
                $tag->set_name( $name );
                $tag->set_description( $description );
                if ( $color ) {
                    $tag->set_color( $color );
                }
            }

            $this->repository->save( $tag );

            wp_redirect( admin_url( 'admin.php?page=constellation-tags' ) );
            exit;

        } catch ( Exception $e ) {
            add_settings_error(
                'constellation_messages',
                'save_failed',
                $e->getMessage(),
                'error'
            );
        }
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

        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_tag_' . $id ) ) {
            wp_die( 'Security check failed.' );
        }

        $this->repository->delete( $id );

        wp_redirect( admin_url( 'admin.php?page=constellation-tags' ) );
        exit;
    }
}
