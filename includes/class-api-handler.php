<?php
/**
 * API Handler for OVHcloud Video Center.
 *
 * Wraps all HTTP calls to the OVHcloud Video Center REST API using
 * WordPress HTTP API (wp_remote_*).
 *
 * @package OVHcloud_VideoCenter
 * @since   0.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OVHcloud_VC_API_Handler
 *
 * Provides methods for every Video Center API endpoint used by the plugin.
 *
 * Authentication: Basic auth where the username is the raw API key and the
 * password is empty – i.e. `Authorization: Basic base64( <apiKey> . ':' )`.
 *
 * @since 0.0.1
 */
class OVHcloud_VC_API_Handler {

    /**
     * Build the base URL for API calls.
     *
     * Uses the stored option if set, otherwise falls back to the default constant.
     *
     * @return string
     */
    private function get_base_url() {
        $stored = get_option( 'ovhcloud_vc_api_endpoint', '' );
        return rtrim( ! empty( $stored ) ? $stored : OVHCLOUD_VC_DEFAULT_API_ENDPOINT, '/' );
    }

    /**
     * Derive the embed host from the configured API endpoint.
     *
     * Replaces the "api." subdomain with "embed.".
     * e.g. https://api.eu.videocenter.ovh.net → https://embed.eu.videocenter.ovh.net
     *
     * @return string
     */
    public function get_embed_host() {
        return rtrim( str_replace( '://api.', '://embed.', $this->get_base_url() ), '/' );
    }

    /**
     * Derive the VOD host from the configured API endpoint.
     *
     * Replaces the "api." subdomain with "vod.".
     * e.g. https://api.eu.videocenter.ovh.net → https://vod.eu.videocenter.ovh.net
     *
     * @return string
     */
    private function get_vod_host() {
        return rtrim( str_replace( '://api.', '://vod.', $this->get_base_url() ), '/' );
    }

    /**
     * Get the configured space ID.
     *
     * @return string
     */
    private function get_space_id() {
        return get_option( 'ovhcloud_vc_space_id', '' );
    }

    /**
     * Build the Authorization header value.
     *
     * Uses Basic auth: base64( apiKey + ":" ).
     *
     * @return string|WP_Error The header value or WP_Error if not configured.
     */
    private function get_auth_header() {
        $api_key = get_option( 'ovhcloud_vc_api_key', '' );

        if ( empty( $api_key ) ) {
            return new \WP_Error( 'ovhcloud_vc_no_api_key', __( 'API key is not configured. Please set it in OVHcloud Video Center → Settings.', 'ovhcloud-video-center' ) );
        }

        // Basic auth: username = apiKey, password = empty.
        return 'Basic ' . base64_encode( $api_key . ':' );
    }

    /**
     * Perform an HTTP request to the API.
     *
     * @param string $method   HTTP method (GET, POST, PATCH, DELETE, PUT).
     * @param string $path     API path (e.g. /space/{spaceId}/vod).
     * @param array  $body     Optional request body (will be JSON-encoded).
     * @param array  $query    Optional query parameters.
     * @param array  $headers  Optional extra headers.
     * @param string $full_url Optional full URL (overrides base_url + path).
     * @return array|WP_Error  Decoded response body or WP_Error.
     */
    public function request( $method, $path = '', $body = array(), $query = array(), $headers = array(), $full_url = '' ) {
        $auth = $this->get_auth_header();
        if ( is_wp_error( $auth ) ) {
            return $auth;
        }

        if ( $full_url ) {
            $url = $full_url;
        } else {
            $url = $this->get_base_url() . $path;
        }

        if ( ! empty( $query ) ) {
            // Handle repeated query parameters (e.g. tags=foo&tags=bar).
            // WordPress add_query_arg serializes arrays as tags[0]=foo which
            // the OVH API does not understand. Build manually.
            $repeated = array();
            $simple   = array();

            foreach ( $query as $key => $value ) {
                if ( is_array( $value ) ) {
                    foreach ( $value as $v ) {
                        $repeated[] = rawurlencode( $key ) . '=' . rawurlencode( $v );
                    }
                } else {
                    $simple[ $key ] = $value;
                }
            }

            if ( ! empty( $simple ) ) {
                $url = add_query_arg( $simple, $url );
            }

            if ( ! empty( $repeated ) ) {
                $glue = ( strpos( $url, '?' ) !== false ) ? '&' : '?';
                $url .= $glue . implode( '&', $repeated );
            }
        }

        $args = array(
            'method'  => strtoupper( $method ),
            'timeout' => 60,
            'headers' => array_merge(
                array(
                    'Authorization' => $auth,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                $headers
            ),
        );

        if ( ! empty( $body ) && in_array( $method, array( 'POST', 'PATCH', 'PUT' ), true ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body_raw = wp_remote_retrieve_body( $response );
        $decoded  = json_decode( $body_raw, true );

        // Handle HTTP errors.
        if ( $code >= 400 ) {
            $message = isset( $decoded['message'] ) ? $decoded['message'] : __( 'An unknown API error occurred.', 'ovhcloud-video-center' );
            $class   = isset( $decoded['class'] ) ? $decoded['class'] : '';

            return new \WP_Error(
                'ovhcloud_vc_api_error',
                $this->translate_error( $class, $message ),
                array( 'status' => $code, 'class' => $class, 'details' => isset( $decoded['details'] ) ? $decoded['details'] : array() )
            );
        }

        // 204 No Content (e.g. DELETE success).
        if ( 204 === $code || empty( $body_raw ) ) {
            return true;
        }

        return $decoded;
    }

    /**
     * Translate known API error classes to user-friendly messages.
     *
     * @param string $class   Error class from API.
     * @param string $message Original error message.
     * @return string
     */
    private function translate_error( $class, $message ) {
        $map = array(
            'Client::BadRequest::VodCountCapacityExceeded' => __( 'Video quota exceeded. Please delete some videos before adding new ones.', 'ovhcloud-video-center' ),
            'Client::BadRequest::MaxVodTagsExceeded'       => __( 'Too many tags on this video. Please remove some tags.', 'ovhcloud-video-center' ),
            'Client::BadRequest::MaxSpaceTagsExceeded'     => __( 'Too many unique tags in your space. Please reuse existing tags.', 'ovhcloud-video-center' ),
            'Client::NotFound::VodNotFound'                => __( 'Video not found. It may have been deleted.', 'ovhcloud-video-center' ),
            'Client::BadRequest::Validation'               => __( 'Validation error: ', 'ovhcloud-video-center' ) . $message,
        );

        return isset( $map[ $class ] ) ? $map[ $class ] : $message;
    }

    // ------------------------------------------------------------------
    // VOD endpoints
    // ------------------------------------------------------------------

    /**
     * List VODs in the configured space.
     *
     * @param int    $page     Page number (1-based).
     * @param int    $per_page Items per page.
     * @param string $title    Optional title filter.
     * @param string $tags     Optional comma-separated tags filter.
     * @return array|WP_Error  { items: [], pagination: {} }
     */
    public function list_vods( $page = 1, $per_page = 12, $title = '', $tags = '' ) {
        $space_id = $this->get_space_id();
        if ( empty( $space_id ) ) {
            return new \WP_Error( 'ovhcloud_vc_no_space', __( 'Space ID is not configured.', 'ovhcloud-video-center' ) );
        }

        // API max pageSize is 50.
        $per_page = min( intval( $per_page ), 50 );

        $query = array(
            'pageNumber' => intval( $page ),
            'pageSize'   => $per_page,
        );

        if ( ! empty( $title ) ) {
            $query['title'] = sanitize_text_field( $title );
        }

        // Tags is a repeated query parameter: ?tags=foo&tags=bar
        // WordPress add_query_arg supports arrays for this.
        if ( ! empty( $tags ) ) {
            $tag_array = array_map( 'trim', explode( ',', $tags ) );
            $tag_array = array_filter( array_map( 'sanitize_text_field', $tag_array ) );
            if ( ! empty( $tag_array ) ) {
                $query['tags'] = array_values( array_slice( $tag_array, 0, 5 ) ); // API max 5 tags
            }
        }

        return $this->request( 'GET', '/space/' . $space_id . '/vod', array(), $query );
    }

    /**
     * Get a single VOD by ID.
     *
     * @param string $vod_id The VOD identifier.
     * @return array|WP_Error
     */
    public function get_vod( $vod_id ) {
        $space_id = $this->get_space_id();
        if ( empty( $space_id ) ) {
            return new \WP_Error( 'ovhcloud_vc_no_space', __( 'Space ID is not configured.', 'ovhcloud-video-center' ) );
        }

        $vod_id = sanitize_text_field( $vod_id );

        return $this->request( 'GET', '/space/' . $space_id . '/vod/' . $vod_id );
    }

    /**
     * Create a new VOD (Step 1 of the upload flow).
     *
     * Returns the VOD object including `ingest.uploadUrl`.
     *
     * @param string $title Video title.
     * @param array  $tags  Array of tag strings.
     * @return array|WP_Error
     */
    public function create_vod( $title, $tags = array() ) {
        $space_id = $this->get_space_id();
        if ( empty( $space_id ) ) {
            return new \WP_Error( 'ovhcloud_vc_no_space', __( 'Space ID is not configured.', 'ovhcloud-video-center' ) );
        }

        $body = array(
            'title' => sanitize_text_field( $title ),
        );

        if ( ! empty( $tags ) ) {
            $body['tags'] = array_map( 'sanitize_text_field', $tags );
        }

        return $this->request( 'POST', '/space/' . $space_id . '/vod', $body );
    }

    /**
     * Update an existing VOD (title and/or tags).
     *
     * @param string $vod_id VOD identifier.
     * @param array  $data   Associative array with 'title' and/or 'tags'.
     * @return array|WP_Error
     */
    public function update_vod( $vod_id, $data ) {
        $space_id = $this->get_space_id();
        if ( empty( $space_id ) ) {
            return new \WP_Error( 'ovhcloud_vc_no_space', __( 'Space ID is not configured.', 'ovhcloud-video-center' ) );
        }

        $vod_id = sanitize_text_field( $vod_id );
        $body   = array();

        if ( isset( $data['title'] ) ) {
            $body['title'] = sanitize_text_field( $data['title'] );
        }

        if ( isset( $data['tags'] ) ) {
            $body['tags'] = array_map( 'sanitize_text_field', (array) $data['tags'] );
        }

        return $this->request( 'PATCH', '/space/' . $space_id . '/vod/' . $vod_id, $body );
    }

    /**
     * Delete a VOD.
     *
     * @param string $vod_id VOD identifier.
     * @return true|WP_Error
     */
    public function delete_vod( $vod_id ) {
        $space_id = $this->get_space_id();
        if ( empty( $space_id ) ) {
            return new \WP_Error( 'ovhcloud_vc_no_space', __( 'Space ID is not configured.', 'ovhcloud-video-center' ) );
        }

        $vod_id = sanitize_text_field( $vod_id );

        return $this->request( 'DELETE', '/space/' . $space_id . '/vod/' . $vod_id );
    }

    // ------------------------------------------------------------------
    // Upload (Step 2)
    // ------------------------------------------------------------------

    /**
     * Upload a file to the pre-signed upload URL.
     *
     * This is Step 2 of the upload flow. The upload URL is obtained from
     * the VOD creation response (`ingest.uploadUrl`). This method is used
     * server-side as a fallback; the preferred path is client-side upload
     * via JavaScript for progress tracking and to avoid PHP memory limits.
     *
     * @param string $upload_url  The pre-signed upload URL.
     * @param string $file_path   Local filesystem path to the file.
     * @param string $file_name   Original file name.
     * @param string $mime_type   MIME type of the file.
     * @return true|WP_Error
     */
    public function upload_file( $upload_url, $file_path, $file_name, $mime_type ) {
        if ( ! file_exists( $file_path ) ) {
            return new \WP_Error( 'ovhcloud_vc_file_not_found', __( 'Temporary file not found.', 'ovhcloud-video-center' ) );
        }

        $file_content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        if ( false === $file_content ) {
            return new \WP_Error( 'ovhcloud_vc_file_read_error', __( 'Could not read the uploaded file.', 'ovhcloud-video-center' ) );
        }

        $response = wp_remote_request( $upload_url, array(
            'method'  => 'POST',
            'timeout' => 300,
            'headers' => array(
                'Content-Type' => $mime_type,
            ),
            'body'    => $file_content,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code >= 400 ) {
            return new \WP_Error( 'ovhcloud_vc_upload_error', __( 'File upload to storage failed.', 'ovhcloud-video-center' ) );
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Tags
    // ------------------------------------------------------------------

    /**
     * List tags in the configured space.
     *
     * @param string $value Optional filter by tag value.
     * @return array|WP_Error
     */
    public function list_tags( $value = '' ) {
        $space_id = $this->get_space_id();
        if ( empty( $space_id ) ) {
            return new \WP_Error( 'ovhcloud_vc_no_space', __( 'Space ID is not configured.', 'ovhcloud-video-center' ) );
        }

        $query = array();
        if ( ! empty( $value ) ) {
            $query['value'] = sanitize_text_field( $value );
        }

        return $this->request( 'GET', '/space/' . $space_id . '/tag', array(), $query );
    }

    // ------------------------------------------------------------------
    // Space / Connection test
    // ------------------------------------------------------------------

    /**
     * Get space info – used as a connectivity check.
     *
     * @return array|WP_Error
     */
    public function get_space() {
        $space_id = $this->get_space_id();
        if ( empty( $space_id ) ) {
            return new \WP_Error( 'ovhcloud_vc_no_space', __( 'Space ID is not configured.', 'ovhcloud-video-center' ) );
        }

        return $this->request( 'GET', '/space/' . $space_id );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Get the poster URL for a VOD.
     *
     * Poster URL format: https://vod.eu.videocenter.ovh.net/vod/{vodId}/poster.jpg
     *
     * @param array $vod VOD data from API.
     * @return string Poster URL or empty string.
     */
    public function get_poster_url( $vod ) {
        if ( empty( $vod['vodId'] ) ) {
            return '';
        }

        return $this->get_vod_host() . '/vod/' . $vod['vodId'] . '/poster.jpg';
    }

    /**
     * Get the embed/player URL for a VOD.
     *
     * Embed URL format: https://embed.eu.videocenter.ovh.net/vod/{vodId}
     *
     * @param array $vod VOD data from API.
     * @return string Embed URL or empty string.
     */
    public function get_embed_url( $vod ) {
        if ( empty( $vod['vodId'] ) ) {
            return '';
        }

        return $this->get_embed_host() . '/vod/' . $vod['vodId'];
    }
}
