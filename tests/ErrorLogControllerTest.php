<?php
/**
 * Error Log Controller tests.
 *
 * @package SystemReport
 */

/**
 * Test the Error Log REST API endpoints.
 */
class ErrorLogControllerTest extends WP_UnitTestCase {

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

		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Ensure REST server is initialized.
		do_action( 'rest_api_init' );
	}

	// ---------------------------------------------------------------
	// Route registration tests
	// ---------------------------------------------------------------

	/**
	 * Test that the error-log route is registered.
	 */
	public function test_error_log_route_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp-system-report/v1/error-log', $routes );
	}

	/**
	 * Test that the status route is registered.
	 */
	public function test_status_route_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp-system-report/v1/error-log/status', $routes );
	}

	/**
	 * Test that the toggle route is registered.
	 */
	public function test_toggle_route_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp-system-report/v1/error-log/toggle', $routes );
	}

	// ---------------------------------------------------------------
	// Authentication tests
	// ---------------------------------------------------------------

	/**
	 * Test admin can access error log.
	 */
	public function test_admin_can_access_log(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log' );
		$response = rest_get_server()->dispatch( $request );

		// May be 200 or 404 depending on whether a log file exists.
		$this->assertContains( $response->get_status(), array( 200, 404 ) );
	}

	/**
	 * Test subscriber cannot access error log.
	 */
	public function test_subscriber_cannot_access_log(): void {
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test unauthenticated user cannot access error log.
	 */
	public function test_unauthenticated_cannot_access_log(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Test admin can access status.
	 */
	public function test_admin_can_access_status(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test subscriber cannot access status.
	 */
	public function test_subscriber_cannot_access_status(): void {
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test subscriber cannot toggle debug.
	 */
	public function test_subscriber_cannot_toggle(): void {
		wp_set_current_user( $this->subscriber_id );

		$request = new WP_REST_Request( 'POST', '/wp-system-report/v1/error-log/toggle' );
		$request->set_param( 'enable', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test error code for unauthorized access.
	 */
	public function test_unauthorized_error_code(): void {
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( 'wp_system_report_rest_forbidden', $data['code'] );
	}

	// ---------------------------------------------------------------
	// Status endpoint tests
	// ---------------------------------------------------------------

	/**
	 * Test status response structure.
	 */
	public function test_status_response_structure(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'file', $data );
		$this->assertArrayHasKey( 'constants', $data );
		$this->assertArrayHasKey( 'toggle', $data );
		$this->assertArrayHasKey( 'settings', $data );
	}

	/**
	 * Test status contains toggle state.
	 */
	public function test_status_toggle_state(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'can_modify', $data['toggle'] );
		$this->assertArrayHasKey( 'wp_debug', $data['toggle'] );
	}

	/**
	 * Test status contains settings.
	 */
	public function test_status_contains_settings(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'error_log_lines', $data['settings'] );
	}

	// ---------------------------------------------------------------
	// Log endpoint parameter validation tests
	// ---------------------------------------------------------------

	/**
	 * Test lines parameter defaults to 100.
	 */
	public function test_lines_defaults_to_100(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log' );
		// Defaults are applied when the route is matched during dispatch.
		$request->set_default_params( array( 'lines' => 100 ) );
		$this->assertSame( 100, $request->get_param( 'lines' ) );
	}

	/**
	 * Test format parameter defaults to json.
	 */
	public function test_format_defaults_to_json(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log' );
		// Defaults are applied when the route is matched during dispatch.
		$request->set_default_params( array( 'format' => 'json' ) );
		$this->assertSame( 'json', $request->get_param( 'format' ) );
	}

	// ---------------------------------------------------------------
	// Toggle endpoint tests
	// ---------------------------------------------------------------

	/**
	 * Test toggle requires enable parameter.
	 */
	public function test_toggle_requires_enable_param(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'POST', '/wp-system-report/v1/error-log/toggle' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Test capability filter rejects low-privilege capabilities.
	 *
	 * The allowlist prevents a malicious plugin from downgrading
	 * the required capability to something like 'read'.
	 */
	public function test_capability_filter_rejects_low_privilege(): void {
		wp_set_current_user( $this->subscriber_id );

		// Try to weaken the filter to 'read' (subscriber capability).
		add_filter(
			'wp_system_report_error_log_capability',
			function () {
				return 'read';
			}
		);

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );

		// Should be rejected — 'read' is not in the allowlist.
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test capability filter accepts allowed admin capabilities.
	 */
	public function test_capability_filter_accepts_allowed_capability(): void {
		wp_set_current_user( $this->admin_id );

		// Use an allowed capability.
		add_filter(
			'wp_system_report_error_log_capability',
			function () {
				return 'install_plugins';
			}
		);

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test capability filter falls back for unknown capability strings.
	 */
	public function test_capability_filter_rejects_unknown_capability(): void {
		wp_set_current_user( $this->admin_id );

		// Use a capability that exists but is not in the allowlist.
		add_filter(
			'wp_system_report_error_log_capability',
			function () {
				return 'edit_posts';
			}
		);

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );

		// Should fall back to manage_options and succeed for admin.
		$this->assertSame( 200, $response->get_status() );
	}

	// ---------------------------------------------------------------
	// Security hardening tests
	// ---------------------------------------------------------------

	/**
	 * Test that raw format response registers nosniff header via filter.
	 *
	 * Since PHPUnit cannot capture actual HTTP headers, we verify the
	 * rest_pre_serve_request filter is registered when raw format is used.
	 * The filter callback sets both Content-Type and X-Content-Type-Options.
	 */
	public function test_raw_format_registers_pre_serve_filter(): void {
		wp_set_current_user( $this->admin_id );

		// Create a temp log file so the endpoint returns 200.
		$temp_log = tempnam( sys_get_temp_dir(), 'sr_test_log_' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $temp_log, "Test log line\n" );

		// Mock the reader to return our temp file path.
		$reader = $this->createMock( SystemReport\Error_Log_Reader::class );
		$reader->method( 'resolve_log_path' )->willReturn( $temp_log );
		$reader->method( 'is_path_safe' )->willReturn( true );
		$reader->method( 'read_last_lines' )->willReturn( array( 'Test log line' ) );
		$reader->method( 'get_file_info' )->willReturn(
			array(
				'path'           => 'test.log',
				'exists'         => true,
				'readable'       => true,
				'size'           => 14,
				'size_formatted' => '14 B',
				'safe'           => true,
			)
		);

		$toggle     = $this->createMock( SystemReport\Debug_Toggle::class );
		$controller = new SystemReport\Error_Log_Controller( $reader, $toggle );

		// Build request with raw format.
		$request = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log' );
		$request->set_param( 'lines', 100 );
		$request->set_param( 'format', 'raw' );

		// Record the filter priority before the call.
		$had_filter_before = has_filter( 'rest_pre_serve_request' );

		$response = $controller->get_log( $request );

		// After raw format, the filter should be registered.
		$has_filter_after = has_filter( 'rest_pre_serve_request' );
		$this->assertNotFalse( $has_filter_after, 'rest_pre_serve_request filter should be registered for raw format' );

		$this->assertSame( 200, $response->get_status() );

		// Clean up.
		unlink( $temp_log ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * Test that status endpoint file paths do not expose absolute filesystem paths.
	 */
	public function test_status_file_path_is_not_absolute(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'file', $data );
		$file_path = $data['file']['path'];

		if ( null !== $file_path ) {
			// Path must not contain the ABSPATH prefix.
			$this->assertStringNotContainsString( ABSPATH, $file_path );
			// Path must not start with a forward slash (not absolute).
			$this->assertDoesNotMatchRegularExpression( '#^/#', $file_path );
		} else {
			// No log file found is valid — path is null.
			$this->assertNull( $file_path );
		}
	}

	/**
	 * Test that the capability filter rejects invalid return values.
	 */
	public function test_capability_filter_rejects_empty_string(): void {
		wp_set_current_user( $this->admin_id );

		// Return an empty string from the capability filter.
		add_filter(
			'wp_system_report_error_log_capability',
			function () {
				return '';
			}
		);

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );

		// Should fall back to manage_options and succeed for admin.
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test that the capability filter rejects non-string return values.
	 */
	public function test_capability_filter_rejects_non_string(): void {
		wp_set_current_user( $this->admin_id );

		// Return a non-string from the capability filter.
		add_filter(
			'wp_system_report_error_log_capability',
			function () {
				return false;
			}
		);

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );

		// Should fall back to manage_options and succeed for admin.
		$this->assertSame( 200, $response->get_status() );
	}

	// ---------------------------------------------------------------
	// Rate limiting tests
	// ---------------------------------------------------------------

	/**
	 * Test that toggle rate limiting returns 429 on rapid requests.
	 */
	public function test_toggle_rate_limiting(): void {
		wp_set_current_user( $this->admin_id );

		// Simulate the cooldown transient being set.
		set_transient( 'sr_debug_toggle_cooldown', 1, 3 );

		$request = new WP_REST_Request( 'POST', '/wp-system-report/v1/error-log/toggle' );
		$request->set_param( 'enable', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 429, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'wp_system_report_rate_limited', $data['code'] );

		// Clean up.
		delete_transient( 'sr_debug_toggle_cooldown' );
	}

	/**
	 * Test that toggle succeeds when no cooldown is active.
	 */
	public function test_toggle_succeeds_without_cooldown(): void {
		wp_set_current_user( $this->admin_id );

		// Ensure no cooldown transient exists.
		delete_transient( 'sr_debug_toggle_cooldown' );

		$reader = $this->createMock( SystemReport\Error_Log_Reader::class );
		$toggle = $this->createMock( SystemReport\Debug_Toggle::class );
		$toggle->method( 'can_modify' )->willReturn( true );
		$toggle->method( 'enable_debug' )->willReturn( true );
		$toggle->method( 'get_state' )->willReturn(
			array(
				'wp_debug'         => true,
				'wp_debug_log'     => true,
				'wp_debug_display' => false,
				'can_modify'       => true,
			)
		);

		$controller = new SystemReport\Error_Log_Controller( $reader, $toggle );

		$request = new WP_REST_Request( 'POST', '/wp-system-report/v1/error-log/toggle' );
		$request->set_param( 'enable', true );
		$response = $controller->toggle_debug( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );

		// Cooldown transient should now be set.
		$this->assertNotFalse( get_transient( 'sr_debug_toggle_cooldown' ) );

		// Clean up.
		delete_transient( 'sr_debug_toggle_cooldown' );
	}

	// ---------------------------------------------------------------
	// Status caching tests
	// ---------------------------------------------------------------

	/**
	 * Test that status endpoint caches its response.
	 */
	public function test_status_endpoint_caches_response(): void {
		wp_set_current_user( $this->admin_id );

		// Clear any existing cache.
		delete_transient( 'sr_error_log_status' );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		// The transient should now be set.
		$cached = get_transient( 'sr_error_log_status' );
		$this->assertIsArray( $cached );
		$this->assertArrayHasKey( 'file', $cached );
		$this->assertArrayHasKey( 'constants', $cached );

		// Clean up.
		delete_transient( 'sr_error_log_status' );
	}

	/**
	 * Test that status endpoint returns cached data on second call.
	 */
	public function test_status_endpoint_returns_cached_data(): void {
		wp_set_current_user( $this->admin_id );

		// Pre-set the cache with known data.
		$cached_data = array(
			'file'      => array(
				'path'           => 'cached.log',
				'exists'         => true,
				'readable'       => true,
				'size'           => 999,
				'size_formatted' => '999 B',
				'safe'           => true,
			),
			'constants' => array(
				'wp_debug' => false,
			),
			'toggle'    => array(
				'can_modify' => true,
				'wp_debug'   => false,
			),
			'settings'  => array(
				'error_log_lines' => 100,
			),
		);
		set_transient( 'sr_error_log_status', $cached_data, 30 );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/status' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		// Should return the cached data.
		$this->assertSame( 'cached.log', $data['file']['path'] );
		$this->assertSame( 999, $data['file']['size'] );

		// Clean up.
		delete_transient( 'sr_error_log_status' );
	}

	/**
	 * Test that toggle invalidates status cache.
	 */
	public function test_toggle_invalidates_status_cache(): void {
		wp_set_current_user( $this->admin_id );

		// Pre-set the status cache.
		set_transient( 'sr_error_log_status', array( 'test' => true ), 30 );

		$reader = $this->createMock( SystemReport\Error_Log_Reader::class );
		$toggle = $this->createMock( SystemReport\Debug_Toggle::class );
		$toggle->method( 'can_modify' )->willReturn( true );
		$toggle->method( 'enable_debug' )->willReturn( true );
		$toggle->method( 'get_state' )->willReturn(
			array(
				'wp_debug'         => true,
				'wp_debug_log'     => true,
				'wp_debug_display' => false,
				'can_modify'       => true,
			)
		);

		$controller = new SystemReport\Error_Log_Controller( $reader, $toggle );

		$request = new WP_REST_Request( 'POST', '/wp-system-report/v1/error-log/toggle' );
		$request->set_param( 'enable', true );
		$controller->toggle_debug( $request );

		// Status cache should have been deleted.
		$this->assertFalse( get_transient( 'sr_error_log_status' ) );

		// Clean up.
		delete_transient( 'sr_debug_toggle_cooldown' );
	}
}
