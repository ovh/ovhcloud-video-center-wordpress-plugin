<?php
/**
 * Shortcodes for OVH VideoCenter.
 *
 * Provides:
 * - [ovhcloud_vc_gallery]  – Displays a grid of video thumbnails with modal player.
 * - [ovhcloud_vc_video]     – Displays a single video player.
 *
 * Also provides the Gutenberg block server-side render callback.
 *
 * @package OVHcloud_VideoCenter
 * @since   0.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OVHcloud_VC_Shortcodes
 *
 * @since 0.0.1
 */
class OVHcloud_VC_Shortcodes {

    /**
     * API handler instance.
     *
     * @var OVHcloud_VC_API_Handler
     */
    private $api;

    /**
     * Default aspect ratios for orientation.
     *
     * @var array
     */
    private $aspect_ratios = array(
        'horizontal' => array( 16, 9 ),
        'vertical'   => array( 9, 16 ),
    );

    /**
     * Constructor – register shortcodes.
     *
     * @param OVHcloud_VC_API_Handler $api API handler instance.
     */
    public function __construct( OVHcloud_VC_API_Handler $api ) {
        $this->api = $api;

        add_shortcode( 'ovhcloud_vc_gallery', array( $this, 'render_video_gallery' ) );
        add_shortcode( 'ovhcloud_vc_video', array( $this, 'render_single_video' ) );
    }

    /**
     * Parse a size attribute into CSS inline styles.
     *
     * Supported formats:
     * - "100%"   → uses full container width, aspect ratio from orientation.
     * - "50%"    → uses 50% of container width, aspect ratio from orientation.
     * - "240x360" → explicit pixel dimensions (width x height).
     *
     * @param string $size        The size attribute value.
     * @param string $orientation Either 'horizontal' or 'vertical'.
     * @return array { 'wrapper_style' => string, 'iframe_style' => string, 'use_aspect' => bool }
     */
    private function parse_size( $size, $orientation = 'horizontal' ) {
        $size = sanitize_text_field( trim( $size ) );
        $orientation = in_array( $orientation, array( 'horizontal', 'vertical' ), true ) ? $orientation : 'horizontal';
        $ratio = $this->aspect_ratios[ $orientation ];
        $padding_top = ( $ratio[1] / $ratio[0] ) * 100;

        // Percentage format: "100%", "50%", etc.
        if ( preg_match( '/^(\d+)%$/', $size, $m ) ) {
            $pct = intval( $m[1] );
            return array(
                'wrapper_style' => 'width:' . $pct . '%;padding-top:' . round( $pct * $padding_top / 100, 4 ) . '%;',
                'iframe_style'  => '',
                'use_aspect'    => true,
            );
        }

        // Pixel format: "240x360", "1920x1080", etc.
        if ( preg_match( '/^(\d+)x(\d+)$/i', $size, $m ) ) {
            $w = intval( $m[1] );
            $h = intval( $m[2] );
            return array(
                'wrapper_style' => 'width:' . $w . 'px;height:' . $h . 'px;padding-top:0;',
                'iframe_style'  => 'width:' . $w . 'px;height:' . $h . 'px;',
                'use_aspect'    => false,
            );
        }

        // Default: 100% with aspect ratio.
        return array(
            'wrapper_style' => 'width:100%;padding-top:' . round( $padding_top, 4 ) . '%;',
            'iframe_style'  => '',
            'use_aspect'    => true,
        );
    }

    /**
     * Shortcode: [ovhcloud_vc_gallery]
     *
     * Displays a YouTube-style mosaic of video poster thumbnails.
     * Clicking a thumbnail opens a modal with the embedded player.
     *
     * Attributes:
     * - tags        (string) Comma-separated tag filter (max 5).
     * - size        (string) Thumbnail size: "WxH" in pixels (e.g. 320x180). Default: empty (CSS auto).
     * - columns     (int)    Number of columns in the grid. Default: 4.
     * - rows        (int)    Number of rows in the grid. Default: 2.
     * - show_title  (string) "true"/"false" — show titles under thumbnails. Default: "false".
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_video_gallery( $atts ) {
        $atts = shortcode_atts( array(
            'tags'       => '',
            'size'       => '',
            'columns'    => '4',
            'rows'       => '2',
            'show_title' => 'false',
        ), $atts, 'ovhcloud_vc_gallery' );

        $tags       = sanitize_text_field( $atts['tags'] );
        $columns    = max( 1, intval( $atts['columns'] ) );
        $rows       = max( 1, intval( $atts['rows'] ) );
        $show_title = ( 'true' === sanitize_text_field( $atts['show_title'] ) );
        $max_items  = $columns * $rows;
        $size_raw   = sanitize_text_field( trim( $atts['size'] ) );

        // Parse thumbnail size.
        $thumb_w = 0;
        $thumb_h = 0;
        if ( preg_match( '/^(\d+)x(\d+)$/i', $size_raw, $m ) ) {
            $thumb_w = intval( $m[1] );
            $thumb_h = intval( $m[2] );
        }

        // Fetch videos (API max pageSize is 50).
        $per_page = min( $max_items, 50 );
        $result   = $this->api->list_vods( 1, $per_page, '', $tags );

        if ( is_wp_error( $result ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                return '<p class="ovh-vc-error">' . esc_html( $result->get_error_message() ) . '</p>';
            }
            return '<p class="ovh-vc-no-videos">' . esc_html__( 'Videos are currently unavailable.', 'ovhcloud-video-center' ) . '</p>';
        }

        if ( empty( $result['items'] ) ) {
            return '<p class="ovh-vc-no-videos">' . esc_html__( 'No videos found.', 'ovhcloud-video-center' ) . '</p>';
        }

        // Inline grid style.
        $grid_style = 'grid-template-columns:repeat(' . $columns . ',1fr);';
        if ( $thumb_w > 0 && $thumb_h > 0 ) {
            $grid_style .= 'grid-template-columns:repeat(' . $columns . ',' . $thumb_w . 'px);';
        }

        // Thumbnail dimensions when explicit WxH is provided.
        $thumb_style = '';
        if ( $thumb_w > 0 && $thumb_h > 0 ) {
            $thumb_style = 'width:' . $thumb_w . 'px;height:' . $thumb_h . 'px;padding-top:0;';
        }

        $output = '<div class="ovh-vc-video-gallery" style="' . esc_attr( $grid_style ) . '">';

        $count = 0;
        foreach ( $result['items'] as $vod ) {
            if ( $count >= $max_items ) {
                break;
            }

            $embed  = esc_url( $this->api->get_embed_url( $vod ) );
            $poster = esc_url( $this->api->get_poster_url( $vod ) );
            $title  = isset( $vod['title'] ) ? esc_attr( $vod['title'] ) : '';

            $output .= '<div class="ovh-vc-gallery-item">';
            $output .= '<button type="button" class="ovh-vc-video-thumb" data-embed-url="' . $embed . '" title="' . $title . '"';
            if ( ! empty( $thumb_style ) ) {
                $output .= ' style="' . esc_attr( $thumb_style ) . '"';
            }
            $output .= '>';
            if ( ! empty( $poster ) ) {
                $output .= '<img src="' . $poster . '" alt="' . $title . '" loading="lazy" />';
            }
            $output .= '<span class="ovh-vc-play-overlay">&#9654;</span>';
            $output .= '</button>';
            if ( $show_title && ! empty( $title ) ) {
                $output .= '<div class="ovh-vc-gallery-title">' . esc_html( $vod['title'] ) . '</div>';
            }
            $output .= '</div>';

            $count++;
        }

        $output .= '</div>'; // .ovh-vc-video-gallery

        // Modal container for the player (opened by front JS on thumbnail click).
        $output .= '<div class="ovh-vc-front-modal" style="display:none;">';
        $output .= '<div class="ovh-vc-front-modal-overlay"></div>';
        $output .= '<div class="ovh-vc-front-modal-content">';
        $output .= '<button type="button" class="ovh-vc-front-modal-close" aria-label="' . esc_attr__( 'Close', 'ovhcloud-video-center' ) . '">&times;</button>';
        $output .= '<div class="ovh-vc-front-player"></div>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Shortcode: [ovhcloud_vc_video id="vod_xxx"]
     *
     * Displays a single video player via iframe embed.
     *
     * Attributes:
     * - id          (string) Required. The VOD ID.
     * - size        (string) Player size: "100%", "50%", "640x360", etc. Default: "100%".
     * - orientation (string) Player orientation: "horizontal" or "vertical". Default: "horizontal".
     * - loop        (string) "true"/"false" – loop playback. Default: "false".
     * - autoplay    (string) "true"/"false" – autoplay video. Default: "false".
     * - hide_title  (string) "true"/"false" – hide the title overlay. Default: "false".
     * - mute        (string) "true"/"false" – mute the player. Default: "false".
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_single_video( $atts ) {
        $atts = shortcode_atts( array(
            'id'          => '',
            'size'        => '100%',
            'orientation' => 'horizontal',
            'loop'        => 'false',
            'autoplay'    => 'false',
            'hide_title'    => 'false',
            'hide_controls' => 'false',
            'mute'          => 'false',
        ), $atts, 'ovhcloud_vc_video' );

        $vod_id = sanitize_text_field( $atts['id'] );

        if ( empty( $vod_id ) ) {
            return '<p class="ovh-vc-error">' . esc_html__( 'Video ID is required.', 'ovhcloud-video-center' ) . '</p>';
        }

        $vod = $this->api->get_vod( $vod_id );

        if ( is_wp_error( $vod ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                return '<p class="ovh-vc-error">' . esc_html( $vod->get_error_message() ) . '</p>';
            }
            return '<p class="ovh-vc-error">' . esc_html__( 'This video is currently unavailable.', 'ovhcloud-video-center' ) . '</p>';
        }

        $embed_url = $this->api->get_embed_url( $vod );
        $title     = isset( $vod['title'] ) ? esc_attr( $vod['title'] ) : '';

        if ( empty( $embed_url ) ) {
            return '<p class="ovh-vc-error">' . esc_html__( 'No embed URL available for this video.', 'ovhcloud-video-center' ) . '</p>';
        }

        // Build embed query parameters for player options.
        $embed_params = array();
        if ( 'true' === sanitize_text_field( $atts['autoplay'] ) ) {
            $embed_params[] = 'a';
        }
        if ( 'true' === sanitize_text_field( $atts['loop'] ) ) {
            $embed_params[] = 'l';
        }
        if ( 'true' === sanitize_text_field( $atts['hide_title'] ) ) {
            $embed_params[] = 'ht';
        }
        if ( 'true' === sanitize_text_field( $atts['hide_controls'] ) ) {
            $embed_params[] = 'hc';
        }
        if ( 'true' === sanitize_text_field( $atts['mute'] ) ) {
            $embed_params[] = 'm';
        }
        if ( ! empty( $embed_params ) ) {
            $embed_url .= '?' . implode( '&', $embed_params );
        }

        $embed = esc_url( $embed_url );

        $orientation = sanitize_text_field( $atts['orientation'] );
        $size_data   = $this->parse_size( $atts['size'], $orientation );

        $aspect_class = $size_data['use_aspect'] ? ' ovh-vc-responsive-embed' : '';
        $iframe_style = $size_data['iframe_style'] ? ' style="' . esc_attr( $size_data['iframe_style'] ) . '"' : '';

        $output  = '<div class="ovh-vc-single-video' . $aspect_class . '" style="' . esc_attr( $size_data['wrapper_style'] ) . '">';
        $output .= '<iframe src="' . $embed . '" title="' . $title . '"' . $iframe_style . ' frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Gutenberg block render callback.
     *
     * Delegates to the appropriate shortcode based on block attributes.
     *
     * @param array $attributes Block attributes.
     * @return string HTML output.
     */
    public function render_block( $attributes ) {
        $mode        = isset( $attributes['mode'] ) ? $attributes['mode'] : 'single';
        $vod_id      = isset( $attributes['vodId'] ) ? $attributes['vodId'] : '';
        $tags        = isset( $attributes['tags'] ) ? $attributes['tags'] : '';
        $size        = isset( $attributes['size'] ) ? $attributes['size'] : '100%';
        $orientation = isset( $attributes['orientation'] ) ? $attributes['orientation'] : 'horizontal';

        if ( 'list' === $mode ) {
            $columns    = isset( $attributes['columns'] ) ? $attributes['columns'] : '4';
            $rows_attr  = isset( $attributes['rows'] ) ? $attributes['rows'] : '2';
            $show_title = isset( $attributes['showTitle'] ) ? ( $attributes['showTitle'] ? 'true' : 'false' ) : 'false';

            return $this->render_video_gallery( array(
                'tags'       => $tags,
                'size'       => $size,
                'columns'    => $columns,
                'rows'       => $rows_attr,
                'show_title' => $show_title,
            ) );
        }

        $loop       = isset( $attributes['loop'] ) ? ( $attributes['loop'] ? 'true' : 'false' ) : 'false';
        $autoplay   = isset( $attributes['autoplay'] ) ? ( $attributes['autoplay'] ? 'true' : 'false' ) : 'false';
        $hide_title    = isset( $attributes['hideTitle'] ) ? ( $attributes['hideTitle'] ? 'true' : 'false' ) : 'false';
        $hide_controls = isset( $attributes['hideControls'] ) ? ( $attributes['hideControls'] ? 'true' : 'false' ) : 'false';
        $mute          = isset( $attributes['mute'] ) ? ( $attributes['mute'] ? 'true' : 'false' ) : 'false';

        return $this->render_single_video( array(
            'id'             => $vod_id,
            'size'           => $size,
            'orientation'    => $orientation,
            'loop'           => $loop,
            'autoplay'       => $autoplay,
            'hide_title'     => $hide_title,
            'hide_controls'  => $hide_controls,
            'mute'           => $mute,
        ) );
    }
}
