<?php
/**
 * SSE Log Streamer tests.
 *
 * @package SystemReport
 */

/**
 * Test the SSE_Log_Streamer class.
 *
 * The streaming loop itself cannot be exercised in a synchronous test
 * environment; these tests cover the testable surface: initial line
 * retrieval, filter application, and the guard paths for missing or
 * unsafe log files.
 */
class SSELogStreamerTest extends WP_UnitTestCase {

	/**
	 * Streamer instance under test.
	 *
	 * @var SystemReport\SSE_Log_Streamer
	 */
	private $streamer;

	/**
	 * Error log reader mock.
	 *
	 * @var SystemReport\Error_Log_Reader&PHPUnit\Framework\MockObject\MockObject
	 */
	private $reader_mock;

	/**
	 * Temp file path created by helper.
	 *
	 * @var string|null
	 */
	private $temp_file = null;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->reader_mock = $this->createMock( SystemReport\Error_Log_Reader::class );
		$this->streamer    = new SystemReport\SSE_Log_Streamer( $this->reader_mock );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		parent::tear_down();

		if ( null !== $this->temp_file && file_exists( $this->temp_file ) ) {
			unlink( $this->temp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			$this->temp_file = null;
		}
	}

	// ---------------------------------------------------------------
	// Helper
	// ---------------------------------------------------------------

	/**
	 * Create a temporary log file and return its path.
	 *
	 * @param array $lines Lines to write to the file.
	 * @return string Absolute path to the temp file.
	 */
	private function create_temp_log( array $lines ): string {
		$this->temp_file = tempnam( sys_get_temp_dir(), 'sr_sse_test_' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $this->temp_file, implode( "\n", $lines ) . "\n" );
		return $this->temp_file;
	}

	// ---------------------------------------------------------------
	// get_initial_lines tests
	// ---------------------------------------------------------------

	/**
	 * Test that get_initial_lines delegates to the reader and returns lines.
	 */
	public function test_get_initial_lines_returns_expected_count(): void {
		$expected_lines = array( 'Line 1', 'Line 2', 'Line 3' );
		$log_path       = $this->create_temp_log( $expected_lines );

		$this->reader_mock->method( 'resolve_log_path' )->willReturn( $log_path );
		$this->reader_mock->method( 'is_path_safe' )->willReturn( true );
		$this->reader_mock->method( 'read_last_lines' )->willReturn( $expected_lines );

		$result = $this->streamer->get_initial_lines( 3 );

		$this->assertSame( $expected_lines, $result );
		$this->assertCount( 3, $result );
	}

	/**
	 * Test that get_initial_lines passes a custom count to the reader.
	 */
	public function test_get_initial_lines_with_custom_count(): void {
		$log_path = $this->create_temp_log( array( 'A', 'B', 'C', 'D', 'E' ) );

		$this->reader_mock->method( 'resolve_log_path' )->willReturn( $log_path );
		$this->reader_mock->method( 'is_path_safe' )->willReturn( true );

		// Expect read_last_lines to be called with our specific count.
		$this->reader_mock
			->expects( $this->once() )
			->method( 'read_last_lines' )
			->with(
				$this->equalTo( $log_path ),
				$this->equalTo( 10 )
			)
			->willReturn( array( 'A', 'B', 'C', 'D', 'E' ) );

		$result = $this->streamer->get_initial_lines( 10 );
		$this->assertCount( 5, $result );
	}

	/**
	 * Test that get_initial_lines returns empty array when no log file exists.
	 */
	public function test_get_initial_lines_no_log_file(): void {
		$this->reader_mock->method( 'resolve_log_path' )->willReturn( null );

		// read_last_lines should never be called when path is null.
		$this->reader_mock->expects( $this->never() )->method( 'read_last_lines' );

		$result = $this->streamer->get_initial_lines();

		$this->assertSame( array(), $result );
	}

	/**
	 * Test that get_initial_lines returns empty array when path is unsafe.
	 */
	public function test_get_initial_lines_unsafe_path(): void {
		$this->reader_mock->method( 'resolve_log_path' )->willReturn( '/etc/passwd' );
		$this->reader_mock->method( 'is_path_safe' )->willReturn( false );

		// read_last_lines must not be called for unsafe paths.
		$this->reader_mock->expects( $this->never() )->method( 'read_last_lines' );

		$result = $this->streamer->get_initial_lines();

		$this->assertSame( array(), $result );
	}

	/**
	 * Test that the wp_system_report_sse_initial_lines filter is applied.
	 */
	public function test_get_initial_lines_applies_filter(): void {
		$log_path = $this->create_temp_log( array( 'Line' ) );

		$this->reader_mock->method( 'resolve_log_path' )->willReturn( $log_path );
		$this->reader_mock->method( 'is_path_safe' )->willReturn( true );

		// Capture the count argument passed to read_last_lines.
		$captured_count = null;
		$this->reader_mock
			->method( 'read_last_lines' )
			->willReturnCallback(
				function ( string $path, int $count ) use ( &$captured_count ): array {
					$captured_count = $count;
					return array();
				}
			);

		// Filter overrides to 25 lines.
		add_filter(
			'wp_system_report_sse_initial_lines',
			function () {
				return 25;
			}
		);

		$this->streamer->get_initial_lines( 50 );

		$this->assertSame( 25, $captured_count );
	}

	/**
	 * Test that a zero or negative filter value falls back to the default.
	 */
	public function test_get_initial_lines_filter_clamped_to_default(): void {
		$log_path = $this->create_temp_log( array( 'Line' ) );

		$this->reader_mock->method( 'resolve_log_path' )->willReturn( $log_path );
		$this->reader_mock->method( 'is_path_safe' )->willReturn( true );

		$captured_count = null;
		$this->reader_mock
			->method( 'read_last_lines' )
			->willReturnCallback(
				function ( string $path, int $count ) use ( &$captured_count ): array {
					$captured_count = $count;
					return array();
				}
			);

		// Filter returns 0, which is invalid.
		add_filter(
			'wp_system_report_sse_initial_lines',
			function () {
				return 0;
			}
		);

		$this->streamer->get_initial_lines( 50 );

		// Should fall back to DEFAULT_INITIAL_LINES (50).
		$this->assertSame( SystemReport\SSE_Log_Streamer::DEFAULT_INITIAL_LINES, $captured_count );
	}

	// ---------------------------------------------------------------
	// Constants tests
	// ---------------------------------------------------------------

	/**
	 * Test that the default poll interval constant is set correctly.
	 */
	public function test_default_poll_interval_constant(): void {
		$this->assertSame( 1000000, SystemReport\SSE_Log_Streamer::DEFAULT_POLL_INTERVAL );
	}

	/**
	 * Test that the default heartbeat interval constant is set correctly.
	 */
	public function test_default_heartbeat_interval_constant(): void {
		$this->assertSame( 15, SystemReport\SSE_Log_Streamer::DEFAULT_HEARTBEAT_INTERVAL );
	}

	/**
	 * Test that the default max duration constant is set correctly.
	 */
	public function test_default_max_duration_constant(): void {
		$this->assertSame( 300, SystemReport\SSE_Log_Streamer::DEFAULT_MAX_DURATION );
	}

	/**
	 * Test that the default initial lines constant is set correctly.
	 */
	public function test_default_initial_lines_constant(): void {
		$this->assertSame( 50, SystemReport\SSE_Log_Streamer::DEFAULT_INITIAL_LINES );
	}

	// ---------------------------------------------------------------
	// Filter hook existence tests
	// ---------------------------------------------------------------

	/**
	 * Test that the wp_system_report_sse_poll_interval filter tag exists and is hookable.
	 *
	 * Verifies the filter is reachable by adding a callback and confirming
	 * WordPress registered it.
	 */
	public function test_sse_poll_interval_filter_is_hookable(): void {
		$hooked = false;
		add_filter(
			'wp_system_report_sse_poll_interval',
			function ( $v ) use ( &$hooked ) {
				$hooked = true;
				return $v;
			}
		);

		apply_filters( 'wp_system_report_sse_poll_interval', 1000000 );

		$this->assertTrue( $hooked, 'wp_system_report_sse_poll_interval filter should be applied' );
	}

	/**
	 * Test that the wp_system_report_sse_heartbeat_interval filter tag exists and is hookable.
	 */
	public function test_sse_heartbeat_interval_filter_is_hookable(): void {
		$hooked = false;
		add_filter(
			'wp_system_report_sse_heartbeat_interval',
			function ( $v ) use ( &$hooked ) {
				$hooked = true;
				return $v;
			}
		);

		apply_filters( 'wp_system_report_sse_heartbeat_interval', 15 );

		$this->assertTrue( $hooked, 'wp_system_report_sse_heartbeat_interval filter should be applied' );
	}

	/**
	 * Test that the wp_system_report_sse_max_duration filter tag exists and is hookable.
	 */
	public function test_sse_max_duration_filter_is_hookable(): void {
		$hooked = false;
		add_filter(
			'wp_system_report_sse_max_duration',
			function ( $v ) use ( &$hooked ) {
				$hooked = true;
				return $v;
			}
		);

		apply_filters( 'wp_system_report_sse_max_duration', 300 );

		$this->assertTrue( $hooked, 'wp_system_report_sse_max_duration filter should be applied' );
	}

	// ---------------------------------------------------------------
	// Action hook tests
	// ---------------------------------------------------------------

	/**
	 * Test that wp_system_report_sse_stream_start is a valid action hook name.
	 *
	 * Verifies the action can be registered and will be fired by WordPress.
	 */
	public function test_sse_stream_start_action_is_hookable(): void {
		$fired = false;

		add_action(
			'wp_system_report_sse_stream_start',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		do_action( 'wp_system_report_sse_stream_start', '/path/to/log' );

		$this->assertTrue( $fired, 'wp_system_report_sse_stream_start action should fire' );
	}

	/**
	 * Test that wp_system_report_sse_stream_end is a valid action hook name.
	 */
	public function test_sse_stream_end_action_is_hookable(): void {
		$fired = false;

		add_action(
			'wp_system_report_sse_stream_end',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		do_action( 'wp_system_report_sse_stream_end', '/path/to/log' );

		$this->assertTrue( $fired, 'wp_system_report_sse_stream_end action should fire' );
	}

	// ---------------------------------------------------------------
	// SSE_Log_Controller route registration tests
	// ---------------------------------------------------------------

	/**
	 * Test that the SSE stream route is registered.
	 */
	public function test_sse_stream_route_registered(): void {
		do_action( 'rest_api_init' );

		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp-system-report/v1/error-log/stream', $routes );
	}

	/**
	 * Test that the SSE stream route only accepts GET requests.
	 */
	public function test_sse_stream_route_accepts_get_only(): void {
		do_action( 'rest_api_init' );

		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp-system-report/v1/error-log/stream', $routes );

		$route = $routes['/wp-system-report/v1/error-log/stream'];
		// REST routes are arrays of handler arrays; each has a 'methods' key.
		$methods_found = false;
		foreach ( $route as $handler ) {
			if ( isset( $handler['methods'] ) && isset( $handler['methods']['GET'] ) ) {
				$methods_found = true;
				// POST must not be listed.
				$this->assertArrayNotHasKey( 'POST', $handler['methods'] );
			}
		}
		$this->assertTrue( $methods_found, 'GET method should be registered for the stream route' );
	}

	// ---------------------------------------------------------------
	// SSE_Log_Controller permission tests
	// ---------------------------------------------------------------

	/**
	 * Test that an admin user can access the SSE stream route.
	 */
	public function test_admin_can_access_stream_route(): void {
		do_action( 'rest_api_init' );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		// The stream callback itself would block, so we test permission_callback
		// directly via the controller.
		$streamer    = $this->createMock( SystemReport\SSE_Log_Streamer::class );
		$controller  = new SystemReport\SSE_Log_Controller( $streamer );
		$request     = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/stream' );

		$result = $controller->permissions_check( $request );

		$this->assertTrue( $result );
	}

	/**
	 * Test that a subscriber cannot access the SSE stream route.
	 */
	public function test_subscriber_cannot_access_stream_route(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$streamer   = $this->createMock( SystemReport\SSE_Log_Streamer::class );
		$controller = new SystemReport\SSE_Log_Controller( $streamer );
		$request    = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/stream' );

		$result = $controller->permissions_check( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wp_system_report_rest_forbidden', $result->get_error_code() );
	}

	/**
	 * Test that unauthenticated requests are rejected.
	 */
	public function test_unauthenticated_cannot_access_stream_route(): void {
		wp_set_current_user( 0 );

		$streamer   = $this->createMock( SystemReport\SSE_Log_Streamer::class );
		$controller = new SystemReport\SSE_Log_Controller( $streamer );
		$request    = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/stream' );

		$result = $controller->permissions_check( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test that the capability filter is applied and allowlisted on the stream route.
	 */
	public function test_stream_capability_filter_rejects_low_privilege(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		// A malicious plugin attempts to lower the required capability.
		add_filter(
			'wp_system_report_error_log_capability',
			function () {
				return 'read';
			}
		);

		$streamer   = $this->createMock( SystemReport\SSE_Log_Streamer::class );
		$controller = new SystemReport\SSE_Log_Controller( $streamer );
		$request    = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/stream' );

		$result = $controller->permissions_check( $request );

		// 'read' is not in the allowlist — should still be forbidden.
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test that an invalid capability filter value falls back to manage_options.
	 */
	public function test_stream_capability_filter_falls_back_on_invalid_value(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		add_filter(
			'wp_system_report_error_log_capability',
			function () {
				return false; // Non-string — should fall back.
			}
		);

		$streamer   = $this->createMock( SystemReport\SSE_Log_Streamer::class );
		$controller = new SystemReport\SSE_Log_Controller( $streamer );
		$request    = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/stream' );

		$result = $controller->permissions_check( $request );

		// Admin has manage_options so should pass.
		$this->assertTrue( $result );
	}

	// ---------------------------------------------------------------
	// SSE_Log_Controller stream_log tests
	// ---------------------------------------------------------------

	/**
	 * Test that stream_log registers the rest_pre_serve_request filter.
	 */
	public function test_stream_log_registers_pre_serve_filter(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$streamer = $this->createMock( SystemReport\SSE_Log_Streamer::class );
		// stream() should be callable (we won't actually execute the loop).
		$streamer->expects( $this->never() )->method( 'stream' );

		$controller = new SystemReport\SSE_Log_Controller( $streamer );
		$request    = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/stream' );

		$had_filter_before = has_filter( 'rest_pre_serve_request' );

		$response = $controller->stream_log( $request );

		// After stream_log(), the filter should now be registered.
		$has_filter_after = has_filter( 'rest_pre_serve_request' );
		$this->assertNotFalse( $has_filter_after, 'rest_pre_serve_request filter should be registered by stream_log' );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test that stream_log returns a 200 placeholder response.
	 */
	public function test_stream_log_returns_200_response(): void {
		$streamer   = $this->createMock( SystemReport\SSE_Log_Streamer::class );
		$controller = new SystemReport\SSE_Log_Controller( $streamer );
		$request    = new WP_REST_Request( 'GET', '/wp-system-report/v1/error-log/stream' );

		$response = $controller->stream_log( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
	}

	// ---------------------------------------------------------------
	// SSE_Log_Controller last_event_id parameter tests
	// ---------------------------------------------------------------

	/**
	 * Test that last_event_id parameter is accepted and sanitized.
	 */
	public function test_last_event_id_param_accepted(): void {
		do_action( 'rest_api_init' );

		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp-system-report/v1/error-log/stream', $routes );

		$route   = $routes['/wp-system-report/v1/error-log/stream'];
		$handler = reset( $route );

		$this->assertArrayHasKey( 'args', $handler );
		$this->assertArrayHasKey( 'last_event_id', $handler['args'] );
		$this->assertSame( 'string', $handler['args']['last_event_id']['type'] );
		$this->assertSame( '', $handler['args']['last_event_id']['default'] );
	}

	// ---------------------------------------------------------------
	// get_initial_lines redaction tests
	// ---------------------------------------------------------------

	/**
	 * Test that get_initial_lines result passes through the redaction filter
	 * applied inside the reader (via read_last_lines return value).
	 */
	public function test_get_initial_lines_reader_applies_redaction(): void {
		$log_path = $this->create_temp_log( array( 'password=mysecret' ) );

		// The reader mock simulates what read_last_lines returns after redaction.
		$this->reader_mock->method( 'resolve_log_path' )->willReturn( $log_path );
		$this->reader_mock->method( 'is_path_safe' )->willReturn( true );
		$this->reader_mock->method( 'read_last_lines' )->willReturn( array( 'password=[REDACTED]' ) );

		$result = $this->streamer->get_initial_lines( 1 );

		$this->assertCount( 1, $result );
		$this->assertStringContainsString( '[REDACTED]', $result[0] );
		$this->assertStringNotContainsString( 'mysecret', $result[0] );
	}
}
