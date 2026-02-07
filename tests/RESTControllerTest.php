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
		$this->assertArrayHasKey( '/system-report/v1/report', $routes );
	}

	/**
	 * Test that admin can access the endpoint.
	 */
	public function test_admin_can_access() {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/system-report/v1/report' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test that subscriber cannot access the endpoint.
	 */
	public function test_subscriber_cannot_access() {
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/system-report/v1/report' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test that unauthenticated users cannot access the endpoint.
	 */
	public function test_unauthenticated_cannot_access() {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/system-report/v1/report' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Test JSON format returns array data.
	 */
	public function test_json_format_returns_data() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/system-report/v1/report' );
		$request->set_param( 'format', 'json' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );
	}

	/**
	 * Test JSON format contains expected sections.
	 */
	public function test_json_format_contains_sections() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/system-report/v1/report' );
		$request->set_param( 'format', 'json' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'wordpress_environment', $data );
		$this->assertArrayHasKey( 'server_environment', $data );
		$this->assertArrayHasKey( 'security', $data );
	}

	/**
	 * Test JSON section structure.
	 */
	public function test_json_section_structure() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/system-report/v1/report' );
		$request->set_param( 'format', 'json' );
		$response = rest_get_server()->dispatch( $request );

		$data    = $response->get_data();
		$section = $data['wordpress_environment'];

		$this->assertArrayHasKey( 'id', $section );
		$this->assertArrayHasKey( 'label', $section );
		$this->assertArrayHasKey( 'description', $section );
		$this->assertArrayHasKey( 'fields', $section );
		$this->assertIsArray( $section['fields'] );
	}

	/**
	 * Test plain format returns text.
	 */
	public function test_plain_format_returns_text() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/system-report/v1/report' );
		$request->set_param( 'format', 'plain' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsString( $response->get_data() );
		$this->assertStringContainsString( '###', $response->get_data() );

		$headers = $response->get_headers();
		$this->assertSame( 'text/plain; charset=utf-8', $headers['Content-Type'] );
	}

	/**
	 * Test GitHub format returns details wrapper.
	 */
	public function test_github_format_returns_details() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/system-report/v1/report' );
		$request->set_param( 'format', 'github' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( '<details>', $response->get_data() );
	}

	/**
	 * Test AI format returns markdown.
	 */
	public function test_ai_format_returns_markdown() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/system-report/v1/report' );
		$request->set_param( 'format', 'ai' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( '# System Report for', $response->get_data() );

		$headers = $response->get_headers();
		$this->assertSame( 'text/markdown; charset=utf-8', $headers['Content-Type'] );
	}

	/**
	 * Test default format is JSON.
	 */
	public function test_default_format_is_json() {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/system-report/v1/report' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
	}

	/**
	 * Test that the capability filter works.
	 */
	public function test_capability_filter() {
		wp_set_current_user( $this->subscriber_id );

		// Change required capability to 'read' so subscriber can access.
		add_filter(
			'system_report_capability',
			function () {
				return 'read';
			}
		);

		$request  = new WP_REST_Request( 'GET', '/system-report/v1/report' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test error code for unauthorized access.
	 */
	public function test_unauthorized_error_code() {
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/system-report/v1/report' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( 'system_report_rest_forbidden', $data['code'] );
	}
}
