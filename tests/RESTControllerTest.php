<?php
/**
 * REST Controller tests.
 *
 * @package SystemReport
 */

/**
 * Test the REST API endpoint.
 */
class RESTControllerTest extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private $subscriber_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		// Create test users.
		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Ensure REST server is initialized.
		do_action( 'rest_api_init' );
	}

	/**
	 * Test that the route is registered.
	 */
	public function test_route_is_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp-system-report/v1/report', $routes );
	}

	/**
	 * Test that admin can access the endpoint.
	 */
	public function test_admin_can_access() {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test that subscriber cannot access the endpoint.
	 */
	public function test_subscriber_cannot_access() {
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test that unauthenticated users cannot access the endpoint.
	 */
	public function test_unauthenticated_cannot_access() {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Test JSON format returns envelope structure.
	 */
	public function test_json_format_returns_envelope() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$request->set_param( 'format', 'json' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$envelope = $response->get_data();
		$this->assertArrayHasKey( 'status', $envelope );
		$this->assertArrayHasKey( 'data', $envelope );
		$this->assertArrayHasKey( 'meta', $envelope );
		$this->assertSame( 'success', $envelope['status'] );
		$this->assertIsArray( $envelope['data'] );
		$this->assertNotEmpty( $envelope['data'] );
	}

	/**
	 * Test JSON format contains expected sections inside data.
	 */
	public function test_json_format_contains_sections() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$request->set_param( 'format', 'json' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data()['data'];
		$this->assertArrayHasKey( 'wordpress_environment', $data );
		$this->assertArrayHasKey( 'server_environment', $data );
		$this->assertArrayHasKey( 'security', $data );
	}

	/**
	 * Test JSON section structure inside envelope data.
	 */
	public function test_json_section_structure() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$request->set_param( 'format', 'json' );
		$response = rest_get_server()->dispatch( $request );

		$data    = $response->get_data()['data'];
		$section = $data['wordpress_environment'];

		$this->assertArrayHasKey( 'id', $section );
		$this->assertArrayHasKey( 'label', $section );
		$this->assertArrayHasKey( 'description', $section );
		$this->assertArrayHasKey( 'fields', $section );
		$this->assertIsArray( $section['fields'] );
	}

	/**
	 * Test JSON envelope meta contains expected keys.
	 */
	public function test_json_envelope_meta() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$request->set_param( 'format', 'json' );
		$response = rest_get_server()->dispatch( $request );

		$meta = $response->get_data()['meta'];
		$this->assertArrayHasKey( 'generated_at', $meta );
		$this->assertArrayHasKey( 'plugin_version', $meta );
		$this->assertArrayHasKey( 'format', $meta );
		$this->assertArrayHasKey( 'collector_count', $meta );
		$this->assertArrayHasKey( 'fixes_available', $meta );
		$this->assertSame( 'json', $meta['format'] );
		$this->assertIsBool( $meta['fixes_available'] );
	}

	/**
	 * Test plain format returns text.
	 */
	public function test_plain_format_returns_text() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$request->set_param( 'format', 'plain' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		// Non-JSON formats use rest_pre_serve_request to output raw text.
		// The filter signature is: ($served, $result, $request, $server).
		ob_start();
		$served = apply_filters( 'rest_pre_serve_request', false, $response, $request, rest_get_server() );
		$output = ob_get_clean();

		$this->assertTrue( $served );
		$this->assertIsString( $output );
		$this->assertStringContainsString( '###', $output );
	}

	/**
	 * Test GitHub format returns details wrapper.
	 */
	public function test_github_format_returns_details() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$request->set_param( 'format', 'github' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		// Non-JSON formats use rest_pre_serve_request to output raw text.
		ob_start();
		$served = apply_filters( 'rest_pre_serve_request', false, $response, $request, rest_get_server() );
		$output = ob_get_clean();

		$this->assertTrue( $served );
		$this->assertStringContainsString( '<details>', $output );
	}

	/**
	 * Test AI format returns markdown.
	 */
	public function test_ai_format_returns_markdown() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$request->set_param( 'format', 'ai' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		// Non-JSON formats use rest_pre_serve_request to output raw text.
		ob_start();
		$served = apply_filters( 'rest_pre_serve_request', false, $response, $request, rest_get_server() );
		$output = ob_get_clean();

		$this->assertTrue( $served );
		$this->assertStringContainsString( '# WP System Report for', $output );
	}

	/**
	 * Test default format is JSON with envelope.
	 */
	public function test_default_format_is_json() {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$envelope = $response->get_data();
		$this->assertIsArray( $envelope );
		$this->assertArrayHasKey( 'status', $envelope );
		$this->assertSame( 'success', $envelope['status'] );
	}

	/**
	 * Test that the capability filter works.
	 */
	public function test_capability_filter() {
		wp_set_current_user( $this->subscriber_id );

		// Change required capability to 'read' so subscriber can access.
		add_filter(
			'wp_system_report_capability',
			function () {
				return 'read';
			}
		);

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test error code for unauthorized access.
	 */
	public function test_unauthorized_error_code() {
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/report' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( 'wp_system_report_rest_forbidden', $data['code'] );
	}
}
