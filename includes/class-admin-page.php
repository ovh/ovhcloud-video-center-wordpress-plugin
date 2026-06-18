<?php
/**
 * Admin pages for OVH VideoCenter.
 *
 * Registers the top-level menu "OVH VideoCenter" and its submenus:
 * - All Videos (card grid with CRUD)
 * - Add Video (upload form)
 * - API Settings (delegated to OVHcloud_VC_Settings)
 *
 * @package OVHcloud_VideoCenter
 * @since   0.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OVHcloud_VC_Admin_Page
 *
 * @since 0.0.1
 */
class OVHcloud_VC_Admin_Page {

    /**
     * API handler instance.
     *
     * @var OVHcloud_VC_API_Handler
     */
    private $api;

    /**
     * Constructor.
     *
     * @param OVHcloud_VC_API_Handler $api API handler instance.
     */
    public function __construct( OVHcloud_VC_API_Handler $api ) {
        $this->api = $api;

        add_action( 'admin_menu', array( $this, 'register_menus' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_ovhcloud_vc_list_videos', array( $this, 'ajax_list_videos' ) );
        add_action( 'wp_ajax_ovhcloud_vc_delete_video', array( $this, 'ajax_delete_video' ) );
        add_action( 'wp_ajax_ovhcloud_vc_update_video', array( $this, 'ajax_update_video' ) );
        add_action( 'wp_ajax_ovhcloud_vc_create_video', array( $this, 'ajax_create_video' ) );
        add_action( 'wp_ajax_ovhcloud_vc_get_video', array( $this, 'ajax_get_video' ) );
    }

    /**
     * Register admin menus.
     */
    public function register_menus() {
        // Top-level menu.
        add_menu_page(
            __( 'OVHcloud Video Center', 'ovhcloud-video-center' ),
            __( 'OVHcloud Video Center', 'ovhcloud-video-center' ),
            'edit_posts',
            'ovhcloud-video-center',
            array( $this, 'render_all_videos_page' ),
            'dashicons-video-alt3',
            26
        );

        // Submenu: All Videos (same as parent).
        add_submenu_page(
            'ovhcloud-video-center',
            __( 'All Videos', 'ovhcloud-video-center' ),
            __( 'All Videos', 'ovhcloud-video-center' ),
            'manage_options',
            'ovhcloud-video-center',
            array( $this, 'render_all_videos_page' )
        );

        // Submenu: Add Video.
        add_submenu_page(
            'ovhcloud-video-center',
            __( 'Add Video', 'ovhcloud-video-center' ),
            __( 'Add Video', 'ovhcloud-video-center' ),
            'edit_posts',
            'ovhcloud-vc-add',
            array( $this, 'render_add_video_page' )
        );

        // Submenu: API Settings.
        add_submenu_page(
            'ovhcloud-video-center',
            __( 'API Settings', 'ovhcloud-video-center' ),
            __( 'API Settings', 'ovhcloud-video-center' ),
            'manage_options',
            'ovhcloud-vc-settings',
            array( OVHcloud_VideoCenter::get_instance()->settings, 'render_page' )
        );
    }

    // ------------------------------------------------------------------
    // Page renderers
    // ------------------------------------------------------------------

    /**
     * Render the "All Videos" page.
     */
    public function render_all_videos_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovhcloud-video-center' ) );
        }
        ?>
        <div class="wrap ovh-vc-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'All Videos', 'ovhcloud-video-center' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ovhcloud-vc-add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'ovhcloud-video-center' ); ?></a>
            <hr class="wp-header-end" />

            <!-- Search / Filter bar -->
            <div class="ovh-vc-filter-bar">
                <input type="text" id="ovh-vc-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search by title or tags…', 'ovhcloud-video-center' ); ?>" />
                <button type="button" id="ovh-vc-search-btn" class="button"><?php esc_html_e( 'Search', 'ovhcloud-video-center' ); ?></button>
                <p class="description ovh-vc-search-hint"><?php esc_html_e( 'Title: partial match — Tags: exact match.', 'ovhcloud-video-center' ); ?></p>
            </div>

            <!-- Video cards container -->
            <div id="ovh-vc-videos-grid" class="ovh-vc-videos-grid">
                <div class="ovh-vc-loading">
                    <span class="spinner is-active"></span>
                    <?php esc_html_e( 'Loading videos…', 'ovhcloud-video-center' ); ?>
                </div>
            </div>

            <!-- Pagination -->
            <div id="ovh-vc-pagination" class="ovh-vc-pagination"></div>

            <!-- Edit modal -->
            <div id="ovh-vc-edit-modal" class="ovh-vc-modal" style="display:none;">
                <div class="ovh-vc-modal-overlay"></div>
                <div class="ovh-vc-modal-content">
                    <h2><?php esc_html_e( 'Edit Video', 'ovhcloud-video-center' ); ?></h2>
                    <form id="ovh-vc-edit-form">
                        <input type="hidden" id="ovh-vc-edit-vod-id" name="vod_id" />
                        <p>
                            <label for="ovh-vc-edit-title"><?php esc_html_e( 'Title', 'ovhcloud-video-center' ); ?></label><br />
                            <input type="text" id="ovh-vc-edit-title" name="title" class="regular-text widefat" required />
                        </p>
                        <p>
                            <label for="ovh-vc-edit-tags"><?php esc_html_e( 'Tags (comma-separated)', 'ovhcloud-video-center' ); ?></label><br />
                            <input type="text" id="ovh-vc-edit-tags" name="tags" class="regular-text widefat" />
                        </p>
                        <p class="ovh-vc-modal-actions">
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'ovhcloud-video-center' ); ?></button>
                            <button type="button" class="button ovh-vc-modal-close"><?php esc_html_e( 'Cancel', 'ovhcloud-video-center' ); ?></button>
                        </p>
                        <div id="ovh-vc-edit-result" class="ovh-vc-notice" style="display:none;"></div>
                    </form>
                </div>
            </div>

            <!-- Preview modal -->
            <div id="ovh-vc-preview-modal" class="ovh-vc-modal" style="display:none;">
                <div class="ovh-vc-modal-overlay"></div>
                <div class="ovh-vc-modal-content ovh-vc-modal-preview">
                    <button type="button" class="ovh-vc-modal-close ovh-vc-modal-close-btn" aria-label="<?php esc_attr_e( 'Close', 'ovhcloud-video-center' ); ?>">&times;</button>
                    <div id="ovh-vc-preview-player" class="ovh-vc-responsive-embed"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the "Add Video" page.
     */
    public function render_add_video_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovhcloud-video-center' ) );
        }
        ?>
        <div class="wrap ovh-vc-wrap">
            <h1><?php esc_html_e( 'Add New Video', 'ovhcloud-video-center' ); ?></h1>

            <form id="ovh-vc-upload-form" enctype="multipart/form-data">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="ovh-vc-video-title"><?php esc_html_e( 'Title', 'ovhcloud-video-center' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ovh-vc-video-title" name="title" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ovh-vc-video-tags"><?php esc_html_e( 'Tags (comma-separated)', 'ovhcloud-video-center' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ovh-vc-video-tags" name="tags" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'e.g. sport, music, tutorial', 'ovhcloud-video-center' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ovh-vc-video-file"><?php esc_html_e( 'Video File', 'ovhcloud-video-center' ); ?></label>
                        </th>
                        <td>
                            <input type="file" id="ovh-vc-video-file" name="video_file" accept="video/*" required />
                            <p class="description"><?php esc_html_e( 'Supported formats: MP4, WebM, MOV, AVI, MKV.', 'ovhcloud-video-center' ); ?></p>
                        </td>
                    </tr>
                </table>

                <div id="ovh-vc-upload-progress" class="ovh-vc-upload-progress" style="display:none;">
                    <div class="ovh-vc-progress-bar">
                        <div class="ovh-vc-progress-fill" id="ovh-vc-progress-fill"></div>
                    </div>
                    <span id="ovh-vc-progress-text">0%</span>
                </div>

                <div id="ovh-vc-upload-result" class="ovh-vc-notice" style="display:none;"></div>

                <?php submit_button( __( 'Upload Video', 'ovhcloud-video-center' ), 'primary', 'ovh-vc-submit-upload' ); ?>
            </form>
        </div>
        <?php
    }

    // ------------------------------------------------------------------
    // AJAX handlers
    // ------------------------------------------------------------------

    /**
     * AJAX: list videos (paginated, filterable).
     */
    public function ajax_list_videos() {
        check_ajax_referer( 'ovhcloud_vc_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ovhcloud-video-center' ) ) );
        }

        $page     = isset( $_GET['page_num'] ) ? absint( $_GET['page_num'] ) : 1;
        $per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 12;
        $search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

        if ( ! empty( $search ) ) {
            // The API applies title and tags as AND filters, but the user expects
            // OR behaviour (find videos matching by title OR by tag).
            // Strategy: make two API calls and merge unique results.

            // 1. Search by title (partial match).
            $by_title = $this->api->list_vods( $page, $per_page, $search, '' );

            // 2. Search by tag (exact match).
            $by_tag = $this->api->list_vods( $page, $per_page, '', $search );

            // Merge results, de-duplicating by vodId.
            $seen  = array();
            $items = array();

            foreach ( array( $by_title, $by_tag ) as $r ) {
                if ( is_wp_error( $r ) || empty( $r['items'] ) ) {
                    continue;
                }
                foreach ( $r['items'] as $vod ) {
                    if ( ! isset( $seen[ $vod['vodId'] ] ) ) {
                        $seen[ $vod['vodId'] ] = true;
                        $items[] = $vod;
                    }
                }
            }

            // Use pagination from the title query (primary), fall back to tag query.
            $pagination = array( 'pageSize' => $per_page, 'pageNumber' => $page, 'totalItems' => count( $items ) );
            if ( ! is_wp_error( $by_title ) && isset( $by_title['pagination'] ) ) {
                $pagination = $by_title['pagination'];
                // Adjust totalItems to reflect merged count when tag results add extras.
                if ( ! is_wp_error( $by_tag ) && isset( $by_tag['pagination']['totalItems'] ) ) {
                    $pagination['totalItems'] = max( $pagination['totalItems'], $by_tag['pagination']['totalItems'] );
                }
            }

            $result = array( 'items' => $items, 'pagination' => $pagination );
        } else {
            // No search term — return all videos.
            $result = $this->api->list_vods( $page, $per_page, '', '' );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Enrich items with poster and embed URLs.
        if ( ! empty( $result['items'] ) ) {
            foreach ( $result['items'] as &$vod ) {
                $vod['_posterUrl'] = $this->api->get_poster_url( $vod );
                $vod['_embedUrl']  = $this->api->get_embed_url( $vod );
            }
            unset( $vod );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: delete a video.
     */
    public function ajax_delete_video() {
        check_ajax_referer( 'ovhcloud_vc_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ovhcloud-video-center' ) ) );
        }

        $vod_id = isset( $_POST['vod_id'] ) ? sanitize_text_field( wp_unslash( $_POST['vod_id'] ) ) : '';

        if ( empty( $vod_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing video ID.', 'ovhcloud-video-center' ) ) );
        }

        $result = $this->api->delete_vod( $vod_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Video deleted successfully.', 'ovhcloud-video-center' ) ) );
    }

    /**
     * AJAX: update a video (title / tags).
     */
    public function ajax_update_video() {
        check_ajax_referer( 'ovhcloud_vc_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ovhcloud-video-center' ) ) );
        }

        $vod_id = isset( $_POST['vod_id'] ) ? sanitize_text_field( wp_unslash( $_POST['vod_id'] ) ) : '';
        $title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $tags   = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';

        if ( empty( $vod_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing video ID.', 'ovhcloud-video-center' ) ) );
        }

        $data = array();
        if ( ! empty( $title ) ) {
            $data['title'] = $title;
        }

        // Parse comma-separated tags.
        $tags_array = array_filter( array_map( 'trim', explode( ',', $tags ) ) );
        $data['tags'] = $tags_array;

        $result = $this->api->update_vod( $vod_id, $data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Video updated successfully.', 'ovhcloud-video-center' ),
            'video'   => $result,
        ) );
    }

    /**
     * AJAX: create a video (Step 1 – returns upload URL for client-side upload).
     */
    public function ajax_create_video() {
        check_ajax_referer( 'ovhcloud_vc_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ovhcloud-video-center' ) ) );
        }

        $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $tags  = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';

        if ( empty( $title ) ) {
            wp_send_json_error( array( 'message' => __( 'Title is required.', 'ovhcloud-video-center' ) ) );
        }

        $tags_array = array_filter( array_map( 'trim', explode( ',', $tags ) ) );

        $result = $this->api->create_vod( $title, $tags_array );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Extract upload URL.
        $upload_url = '';
        if ( isset( $result['ingest']['uploadUrl'] ) ) {
            $upload_url = $result['ingest']['uploadUrl'];
        }

        wp_send_json_success( array(
            'message'   => __( 'Video created. Uploading file…', 'ovhcloud-video-center' ),
            'vodId'     => isset( $result['vodId'] ) ? $result['vodId'] : '',
            'uploadUrl' => $upload_url,
        ) );
    }

    /**
     * AJAX: get a single video by ID.
     */
    public function ajax_get_video() {
        check_ajax_referer( 'ovhcloud_vc_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ovhcloud-video-center' ) ) );
        }

        $vod_id = isset( $_GET['vod_id'] ) ? sanitize_text_field( wp_unslash( $_GET['vod_id'] ) ) : '';

        if ( empty( $vod_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing video ID.', 'ovhcloud-video-center' ) ) );
        }

        $result = $this->api->get_vod( $vod_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $result['_posterUrl'] = $this->api->get_poster_url( $result );
        $result['_embedUrl']  = $this->api->get_embed_url( $result );

        wp_send_json_success( $result );
    }
}
