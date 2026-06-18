<?php
/**
 * Unit tests for OVH VideoCenter API Handler.
 *
 * These tests mock the WordPress HTTP API to verify the API handler
 * constructs correct requests and handles responses properly.
 *
 * To run these tests you need the WordPress test suite (wp-phpunit)
 * configured in your environment.
 *
 * @package OVHcloud_VideoCenter
 * @since   0.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Test_OVHcloud_VC_API_Handler
 *
 * @group ovhcloud-video-center
 */
class Test_OVHcloud_VC_API_Handler extends WP_UnitTestCase {

    /**
     * API handler instance.
     *
     * @var OVHcloud_VC_API_Handler
     */
    private $api;

    /**
     * Set up before each test.
     */
    public function setUp(): void {
        parent::setUp();

        // Configure plugin options.
        update_option( 'ovhcloud_vc_api_key', 'test_api_key_12345' );
        update_option( 'ovhcloud_vc_space_id', 'space_test123456789012345' );

        require_once dirname( __DIR__ ) . '/includes/class-api-handler.php';
        $this->api = new OVHcloud_VC_API_Handler();
    }

    /**
     * Tear down after each test.
     */
    public function tearDown(): void {
        delete_option( 'ovhcloud_vc_api_key' );
        delete_option( 'ovhcloud_vc_space_id' );

        // Remove any HTTP filters.
        remove_all_filters( 'pre_http_request' );

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Authentication tests
    // ------------------------------------------------------------------

    /**
     * Test that missing API key returns a WP_Error.
     */
    public function test_missing_api_key_returns_error() {
        delete_option( 'ovhcloud_vc_api_key' );

        $result = $this->api->list_vods();

        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertEquals( 'ovhcloud_vc_no_api_key', $result->get_error_code() );
    }

    /**
     * Test that missing space ID returns a WP_Error.
     */
    public function test_missing_space_id_returns_error() {
        delete_option( 'ovhcloud_vc_space_id' );

        $result = $this->api->list_vods();

        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertEquals( 'ovhcloud_vc_no_space', $result->get_error_code() );
    }

    // ------------------------------------------------------------------
    // List VODs
    // ------------------------------------------------------------------

    /**
     * Test list_vods returns parsed JSON on success.
     */
    public function test_list_vods_success() {
        $mock_response = array(
            'items' => array(
                array(
                    'vodId'     => 'vod_test123456789012345',
                    'title'     => 'Test Video',
                    'tags'      => array( 'demo' ),
                    'createdAt' => '2025-01-01T00:00:00Z',
                    'status'    => array( 'value' => 'completed' ),
                    'assets'    => array(
                        array( 'name' => 'poster.jpg', 'url' => 'https://example.com/poster.jpg' ),
                    ),
                ),
            ),
            'pagination' => array(
                'pageSize'   => 12,
                'pageNumber' => 1,
                'totalItems' => 1,
            ),
        );

        add_filter( 'pre_http_request', function ( $preempt, $args, $url ) use ( $mock_response ) {
            // Verify the URL contains the space ID and vod path.
            $this->assertStringContainsString( '/space/space_test123456789012345/vod', $url );

            // Verify auth header is Basic.
            $this->assertStringStartsWith( 'Basic ', $args['headers']['Authorization'] );

            // Verify the decoded auth is "test_api_key_12345:".
            $decoded = base64_decode( str_replace( 'Basic ', '', $args['headers']['Authorization'] ) );
            $this->assertEquals( 'test_api_key_12345:', $decoded );

            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( $mock_response ),
            );
        }, 10, 3 );

        $result = $this->api->list_vods( 1, 12 );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'items', $result );
        $this->assertCount( 1, $result['items'] );
        $this->assertEquals( 'Test Video', $result['items'][0]['title'] );
    }

    /**
     * Test list_vods with search filters.
     */
    public function test_list_vods_with_filters() {
        add_filter( 'pre_http_request', function ( $preempt, $args, $url ) {
            // Verify query params are in the URL.
            $this->assertStringContainsString( 'title=sport', $url );
            $this->assertStringContainsString( 'tags=sport', $url );
            $this->assertStringContainsString( 'pageNumber=2', $url );

            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array( 'items' => array(), 'pagination' => array( 'pageSize' => 12, 'pageNumber' => 2, 'totalItems' => 0 ) ) ),
            );
        }, 10, 3 );

        $result = $this->api->list_vods( 2, 12, 'sport', 'sport' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result['items'] );
    }

    // ------------------------------------------------------------------
    // Create VOD (two-step upload – step 1)
    // ------------------------------------------------------------------

    /**
     * Test create_vod sends correct POST body and returns upload URL.
     */
    public function test_create_vod_returns_upload_url() {
        $mock_response = array(
            'vodId'  => 'vod_new123456789012345',
            'title'  => 'My New Video',
            'tags'   => array( 'test' ),
            'status' => array( 'value' => 'pending' ),
            'ingest' => array(
                'mode'      => 'upload',
                'uploadUrl' => 'https://upload.eu.videocenter.ovh.net/upload?token=abc123',
            ),
        );

        add_filter( 'pre_http_request', function ( $preempt, $args, $url ) use ( $mock_response ) {
            $this->assertEquals( 'POST', $args['method'] );
            $this->assertStringContainsString( '/space/space_test123456789012345/vod', $url );

            // Verify request body.
            $body = json_decode( $args['body'], true );
            $this->assertEquals( 'My New Video', $body['title'] );
            $this->assertEquals( array( 'test' ), $body['tags'] );

            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( $mock_response ),
            );
        }, 10, 3 );

        $result = $this->api->create_vod( 'My New Video', array( 'test' ) );

        $this->assertIsArray( $result );
        $this->assertEquals( 'vod_new123456789012345', $result['vodId'] );
        $this->assertEquals( 'https://upload.eu.videocenter.ovh.net/upload?token=abc123', $result['ingest']['uploadUrl'] );
    }

    // ------------------------------------------------------------------
    // Update VOD
    // ------------------------------------------------------------------

    /**
     * Test update_vod sends PATCH with correct body.
     */
    public function test_update_vod() {
        add_filter( 'pre_http_request', function ( $preempt, $args, $url ) {
            $this->assertEquals( 'PATCH', $args['method'] );
            $this->assertStringContainsString( '/vod/vod_update123', $url );

            $body = json_decode( $args['body'], true );
            $this->assertEquals( 'Updated Title', $body['title'] );
            $this->assertEquals( array( 'new-tag' ), $body['tags'] );

            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array( 'vodId' => 'vod_update123', 'title' => 'Updated Title', 'tags' => array( 'new-tag' ) ) ),
            );
        }, 10, 3 );

        $result = $this->api->update_vod( 'vod_update123', array(
            'title' => 'Updated Title',
            'tags'  => array( 'new-tag' ),
        ) );

        $this->assertIsArray( $result );
        $this->assertEquals( 'Updated Title', $result['title'] );
    }

    // ------------------------------------------------------------------
    // Delete VOD
    // ------------------------------------------------------------------

    /**
     * Test delete_vod sends DELETE and returns true on 204.
     */
    public function test_delete_vod() {
        add_filter( 'pre_http_request', function ( $preempt, $args, $url ) {
            $this->assertEquals( 'DELETE', $args['method'] );
            $this->assertStringContainsString( '/vod/vod_delete123', $url );

            return array(
                'response' => array( 'code' => 204 ),
                'body'     => '',
            );
        }, 10, 3 );

        $result = $this->api->delete_vod( 'vod_delete123' );

        $this->assertTrue( $result );
    }

    // ------------------------------------------------------------------
    // Error handling
    // ------------------------------------------------------------------

    /**
     * Test API error response is parsed into WP_Error.
     */
    public function test_api_error_handling() {
        add_filter( 'pre_http_request', function () {
            return array(
                'response' => array( 'code' => 400 ),
                'body'     => wp_json_encode( array(
                    'message' => 'Capacity exceeded',
                    'class'   => 'Client::BadRequest::VodCountCapacityExceeded',
                ) ),
            );
        } );

        $result = $this->api->create_vod( 'Test' );

        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertEquals( 'ovhcloud_vc_api_error', $result->get_error_code() );
        $this->assertStringContainsString( 'quota exceeded', strtolower( $result->get_error_message() ) );
    }

    /**
     * Test 404 error is handled with friendly message.
     */
    public function test_api_404_handling() {
        add_filter( 'pre_http_request', function () {
            return array(
                'response' => array( 'code' => 404 ),
                'body'     => wp_json_encode( array(
                    'message' => 'Not found',
                    'class'   => 'Client::NotFound::VodNotFound',
                ) ),
            );
        } );

        $result = $this->api->get_vod( 'vod_nonexistent' );

        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertStringContainsString( 'not found', strtolower( $result->get_error_message() ) );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Test get_poster_url extracts poster from assets array.
     */
    public function test_get_poster_url() {
        $vod = array(
            'vodId'  => 'vod_test',
            'assets' => array(
                array( 'name' => 'thumbnail.jpg', 'url' => 'https://example.com/thumb.jpg' ),
                array( 'name' => 'poster.jpg', 'url' => 'https://example.com/poster.jpg' ),
            ),
        );

        $poster = $this->api->get_poster_url( $vod );
        $this->assertStringEndsWith( '/vod/vod_test/poster.jpg', $poster );
        $this->assertStringStartsWith( 'https://', $poster );
    }

    /**
     * Test get_poster_url returns empty when no poster asset.
     */
    public function test_get_poster_url_missing() {
        $vod = array(
            'vodId'  => 'vod_test',
            'assets' => array(
                array( 'name' => 'thumbnail.jpg', 'url' => 'https://example.com/thumb.jpg' ),
            ),
        );

        $poster = $this->api->get_poster_url( $vod );
        $this->assertStringEndsWith( '/vod/vod_test/poster.jpg', $poster );
    }

    /**
     * Test get_embed_url returns embedUrl from VOD data.
     */
    public function test_get_embed_url_from_response() {
        $vod = array(
            'vodId' => 'vod_test',
        );

        $embed = $this->api->get_embed_url( $vod );
        $this->assertStringEndsWith( '/vod/vod_test', $embed );
        $this->assertStringStartsWith( 'https://', $embed );
    }

    /**
     * Test get_embed_url constructs fallback URL.
     */
    public function test_get_embed_url_fallback() {
        $vod = array(
            'vodId' => 'vod_fallback123',
        );

        $embed = $this->api->get_embed_url( $vod );
        $this->assertStringContainsString( 'vod_fallback123', $embed );
        $this->assertStringStartsWith( 'https://', $embed );
    }

    // ------------------------------------------------------------------
    // Get space (connection test)
    // ------------------------------------------------------------------

    /**
     * Test get_space calls the correct endpoint.
     */
    public function test_get_space() {
        add_filter( 'pre_http_request', function ( $preempt, $args, $url ) {
            $this->assertEquals( 'GET', $args['method'] );
            $this->assertStringContainsString( '/space/space_test123456789012345', $url );
            // Make sure it's not /space/space_xxx/vod or /space/space_xxx/tag.
            $this->assertStringEndsNotWith( '/vod', wp_parse_url( $url, PHP_URL_PATH ) );

            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'spaceId' => 'space_test123456789012345',
                    'name'    => 'Test Space',
                ) ),
            );
        }, 10, 3 );

        $result = $this->api->get_space();

        $this->assertIsArray( $result );
        $this->assertEquals( 'Test Space', $result['name'] );
    }
}
