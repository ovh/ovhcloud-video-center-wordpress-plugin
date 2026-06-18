<?php
/**
 * Settings page for OVH VideoCenter.
 *
 * Registers the "Réglages API" submenu and handles the API configuration:
 * API key and Space ID.
 *
 * @package OVHcloud_VideoCenter
 * @since   0.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OVHcloud_VC_Settings
 *
 * @since 0.0.1
 */
class OVHcloud_VC_Settings {

    /**
     * Option keys.
     */
    const OPT_API_KEY       = 'ovhcloud_vc_api_key';
    const OPT_SPACE_ID      = 'ovhcloud_vc_space_id';
    const OPT_API_ENDPOINT  = 'ovhcloud_vc_api_endpoint';

    /**
     * Constructor – hook into WordPress.
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_ovhcloud_vc_test_connection', array( $this, 'ajax_test_connection' ) );
    }

    /**
     * Register settings fields with the WordPress Settings API.
     */
    public function register_settings() {
        // Register options.
        register_setting( 'ovhcloud_vc_settings_group', self::OPT_API_KEY, array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'ovhcloud_vc_settings_group', self::OPT_SPACE_ID, array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'ovhcloud_vc_settings_group', self::OPT_API_ENDPOINT, array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
        ) );

        // Section.
        add_settings_section(
            'ovhcloud_vc_main_section',
            __( 'API Configuration', 'ovhcloud-video-center' ),
            array( $this, 'render_section_description' ),
            'ovhcloud-vc-settings'
        );

        // Fields.
        add_settings_field(
            'ovhcloud_vc_api_key_field',
            __( 'API Key', 'ovhcloud-video-center' ),
            array( $this, 'render_api_key_field' ),
            'ovhcloud-vc-settings',
            'ovhcloud_vc_main_section'
        );

        add_settings_field(
            'ovhcloud_vc_space_id_field',
            __( 'Space ID', 'ovhcloud-video-center' ),
            array( $this, 'render_space_id_field' ),
            'ovhcloud-vc-settings',
            'ovhcloud_vc_main_section'
        );

        add_settings_field(
            'ovhcloud_vc_api_endpoint_field',
            __( 'API Endpoint', 'ovhcloud-video-center' ),
            array( $this, 'render_api_endpoint_field' ),
            'ovhcloud-vc-settings',
            'ovhcloud_vc_main_section'
        );
    }

    /**
     * Section description callback.
     */
    public function render_section_description() {
        echo '<p>' . esc_html__( 'Configure your OVHcloud Video Center API credentials below.', 'ovhcloud-video-center' ) . '</p>';
    }

    /**
     * Render the API Key field (password input).
     */
    public function render_api_key_field() {
        $value = get_option( self::OPT_API_KEY, '' );
        printf(
            '<input type="password" id="%1$s" name="%1$s" value="%2$s" class="regular-text" autocomplete="off" />',
            esc_attr( self::OPT_API_KEY ),
            esc_attr( $value )
        );
        echo '<p class="description">' . esc_html__( 'Your API key for authenticating with the Video Center API. This is stored securely in the database.', 'ovhcloud-video-center' ) . '</p>';
    }

    /**
     * Render the Space ID field.
     */
    public function render_space_id_field() {
        $value = get_option( self::OPT_SPACE_ID, '' );
        printf(
            '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" placeholder="space_xxxxxxxxxxxxxxxxxxxxxx" />',
            esc_attr( self::OPT_SPACE_ID ),
            esc_attr( $value )
        );
        echo '<p class="description">' . esc_html__( 'The identifier of your Video Center space.', 'ovhcloud-video-center' ) . '</p>';
    }

    /**
     * Render the API Endpoint field.
     */
    public function render_api_endpoint_field() {
        $value = get_option( self::OPT_API_ENDPOINT, '' );
        printf(
            '<input type="url" id="%1$s" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" />',
            esc_attr( self::OPT_API_ENDPOINT ),
            esc_attr( $value ),
            esc_attr( OVHCLOUD_VC_DEFAULT_API_ENDPOINT )
        );
        echo '<p class="description">' . sprintf(
            /* translators: %s: default API endpoint URL */
            esc_html__( 'Override the default API endpoint. Leave empty to use %s.', 'ovhcloud-video-center' ),
            '<code>' . esc_html( OVHCLOUD_VC_DEFAULT_API_ENDPOINT ) . '</code>'
        ) . '</p>';
    }

    /**
     * Render the settings page.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ovhcloud-video-center' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'OVHcloud Video Center – API Settings', 'ovhcloud-video-center' ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'ovhcloud_vc_settings_group' );
                do_settings_sections( 'ovhcloud-vc-settings' );
                submit_button();
                ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Test Connection', 'ovhcloud-video-center' ); ?></h2>
            <p><?php esc_html_e( 'Click the button below to verify that the API credentials are valid.', 'ovhcloud-video-center' ); ?></p>
            <button type="button" id="ovhcloud-vc-test-connection" class="button button-secondary">
                <?php esc_html_e( 'Test Connection', 'ovhcloud-video-center' ); ?>
            </button>
            <span id="ovhcloud-vc-test-result" style="margin-left: 10px;"></span>
        </div>
        <?php
    }

    /**
     * AJAX handler: test the API connection by calling GET /space/{spaceId}.
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'ovhcloud_vc_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ovhcloud-video-center' ) ) );
        }

        $api    = new OVHcloud_VC_API_Handler();
        $result = $api->get_space();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message'   => __( 'Connection successful!', 'ovhcloud-video-center' ),
            'spaceName' => isset( $result['name'] ) ? $result['name'] : '',
        ) );
    }
}
