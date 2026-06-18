<?php
/**
 * Plugin Name: OVHcloud Video Center
 * Plugin URI:  https://www.ovhcloud.com/fr/video-center/
 * Description: Manage videos via the OVHcloud Video Center API. Upload, list, edit, delete videos and embed them using shortcodes or the Gutenberg block editor.
 * Version:     0.0.1
 * Author:      The OVHcloud video team
 * Author URI:  https://ovhcloud.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ovhcloud-video-center
 *
 * @package OVHcloud_VideoCenter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants.
 */
define( 'OVHCLOUD_VC_VERSION', '0.0.1' );
define( 'OVHCLOUD_VC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OVHCLOUD_VC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OVHCLOUD_VC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'OVHCLOUD_VC_DEFAULT_API_ENDPOINT', 'https://api.eu.videocenter.ovh.net' );

/**
 * Autoload plugin classes.
 */
require_once OVHCLOUD_VC_PLUGIN_DIR . 'includes/class-api-handler.php';
require_once OVHCLOUD_VC_PLUGIN_DIR . 'includes/class-settings.php';
require_once OVHCLOUD_VC_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once OVHCLOUD_VC_PLUGIN_DIR . 'includes/class-shortcodes.php';

/**
 * Main plugin class.
 *
 * Bootstraps the plugin, registers hooks and initialises components.
 *
 * @since 0.0.1
 */
class OVHcloud_VideoCenter {

    /**
     * Singleton instance.
     *
     * @var OVHcloud_VideoCenter|null
     */
    private static $instance = null;

    /**
     * API handler instance.
     *
     * @var OVHcloud_VC_API_Handler
     */
    public $api;

    /**
     * Settings page instance.
     *
     * @var OVHcloud_VC_Settings
     */
    public $settings;

    /**
     * Admin page instance.
     *
     * @var OVHcloud_VC_Admin_Page
     */
    public $admin;

    /**
     * Shortcodes instance.
     *
     * @var OVHcloud_VC_Shortcodes
     */
    public $shortcodes;

    /**
     * Returns the singleton instance.
     *
     * @return OVHcloud_VideoCenter
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor – wire everything up.
     */
    private function __construct() {
        $this->api        = new OVHcloud_VC_API_Handler();
        $this->settings   = new OVHcloud_VC_Settings();
        $this->admin      = new OVHcloud_VC_Admin_Page( $this->api );
        $this->shortcodes = new OVHcloud_VC_Shortcodes( $this->api );

        // Enqueue admin assets.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Enqueue front-end assets.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );

        // Register Gutenberg block.
        add_action( 'init', array( $this, 'register_block' ) );

        // Register TinyMCE button for classic editor.
        add_action( 'admin_init', array( $this, 'register_tinymce_plugin' ) );

        // Add settings link on plugins page.
        add_filter( 'plugin_action_links_' . OVHCLOUD_VC_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
    }

    /**
     * Enqueue admin CSS and JS on plugin pages only.
     *
     * @param string $hook_suffix The current admin page hook suffix.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Only load on our plugin pages.
        $plugin_pages = array(
            'toplevel_page_ovhcloud-video-center',
            'ovhcloud-video-center_page_ovhcloud-vc-add',
            'ovhcloud-video-center_page_ovhcloud-vc-settings',
        );

        if ( ! in_array( $hook_suffix, $plugin_pages, true ) ) {
            return;
        }

        wp_enqueue_style(
            'ovhcloud-vc-admin',
            OVHCLOUD_VC_PLUGIN_URL . 'assets/css/ovh-vc-admin.css',
            array(),
            OVHCLOUD_VC_VERSION
        );

        wp_enqueue_script(
            'ovhcloud-vc-admin',
            OVHCLOUD_VC_PLUGIN_URL . 'assets/js/ovh-vc-admin.js',
            array( 'jquery' ),
            OVHCLOUD_VC_VERSION,
            true
        );

        wp_localize_script( 'ovhcloud-vc-admin', 'ovhcloudVC', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ovhcloud_vc_nonce' ),
            'embedHost' => $this->api->get_embed_host(),
            'i18n'     => array(
                'confirmDelete' => __( 'Are you sure you want to delete this video? This action cannot be undone.', 'ovhcloud-video-center' ),
                'uploading'     => __( 'Uploading…', 'ovhcloud-video-center' ),
                'uploadSuccess' => __( 'Video uploaded successfully!', 'ovhcloud-video-center' ),
                'uploadError'   => __( 'Upload failed. Please try again.', 'ovhcloud-video-center' ),
                'saving'        => __( 'Saving…', 'ovhcloud-video-center' ),
                'saved'         => __( 'Changes saved.', 'ovhcloud-video-center' ),
                'testSuccess'   => __( 'Connection successful!', 'ovhcloud-video-center' ),
                'testError'     => __( 'Connection failed.', 'ovhcloud-video-center' ),
            ),
        ) );
    }

    /**
     * Enqueue front-end CSS and JS.
     */
    public function enqueue_front_assets() {
        wp_enqueue_style(
            'ovhcloud-vc-front',
            OVHCLOUD_VC_PLUGIN_URL . 'assets/css/ovh-vc-front.css',
            array(),
            OVHCLOUD_VC_VERSION
        );

        wp_enqueue_script(
            'ovhcloud-vc-front',
            OVHCLOUD_VC_PLUGIN_URL . 'assets/js/ovh-vc-front.js',
            array(),
            OVHCLOUD_VC_VERSION,
            true
        );
    }

    /**
     * Register the Gutenberg block (server-side rendered).
     */
    public function register_block() {
        $block_dir = OVHCLOUD_VC_PLUGIN_DIR . 'blocks/ovhcloud-video-center';

        if ( ! file_exists( $block_dir . '/block.json' ) ) {
            return;
        }

        register_block_type( $block_dir, array(
            'render_callback' => array( $this->shortcodes, 'render_block' ),
        ) );

        // Localize data for the block editor script.
        // The editor script handle is auto-generated from block.json as 'ovhcloud-video-center-video-editor-script'.
        // We use wp_add_inline_script on 'wp-block-editor' to ensure it's available globally.
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_data' ) );
    }

    /**
     * Enqueue data needed by the block editor (AJAX URL + nonce).
     */
    public function enqueue_block_editor_data() {
        wp_add_inline_script(
            'wp-block-editor',
            'window.ovhcloudVCBlock = ' . wp_json_encode( array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'ovhcloud_vc_nonce' ),
            ) ) . ';',
            'before'
        );
    }

    /**
     * Register TinyMCE button for classic editor.
     */
    public function register_tinymce_plugin() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        add_filter( 'mce_buttons', array( $this, 'add_tinymce_button' ) );
        add_filter( 'mce_external_plugins', array( $this, 'add_tinymce_plugin_js' ) );
    }

    /**
     * Add OVHcloud Video Center button to TinyMCE toolbar.
     *
     * @param array $buttons Existing buttons.
     * @return array
     */
    public function add_tinymce_button( $buttons ) {
        $buttons[] = 'ovhcloud_videocenter';
        return $buttons;
    }

    /**
     * Register the TinyMCE plugin JavaScript file.
     *
     * @param array $plugins Existing plugins.
     * @return array
     */
    public function add_tinymce_plugin_js( $plugins ) {
        $plugins['ovhcloud_videocenter'] = OVHCLOUD_VC_PLUGIN_URL . 'assets/js/ovh-vc-tinymce.js';
        return $plugins;
    }

    /**
     * Add a settings link on the Plugins list page.
     *
     * @param array $links Existing links.
     * @return array
     */
    public function plugin_action_links( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=ovhcloud-vc-settings' ) ) . '">' . __( 'Settings', 'ovhcloud-video-center' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
}

/**
 * Boot the plugin.
 */
add_action( 'plugins_loaded', array( 'OVHcloud_VideoCenter', 'get_instance' ) );
