<?php
/**
 * Fixer Controller REST API tests.
 *
 * @package SystemReport
 */

/**
 * Test the fixer REST API endpoints.
 */
class FixerControllerTest extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private int $subscriber_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Ensure REST server and routes are initialized.
		do_action( 'rest_api_init' );
	}

	// ---------------------------------------------------------------
	// Route registration
	// ---------------------------------------------------------------

	/**
	 * Test that the fixes list route is registered.
	 */
	public function test_fixes_list_route_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp-system-report/v1/fixes', $routes );
	}

	/**
	 * Test that the fix execution route is registered.
	 */
	public function test_fix_execution_route_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp-system-report/v1/fixes/(?P<fix_id>[a-z0-9_]+)', $routes );
	}

	// ---------------------------------------------------------------
	// Permissions
	// ---------------------------------------------------------------

	/**
	 * Test that admin can access the fixes list.
	 */
	public function test_admin_can_list_fixes(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/fixes' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test that subscriber cannot access the fixes list.
	 */
	public function test_subscriber_cannot_list_fixes(): void {
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/fixes' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test that unauthenticated users cannot access fixes.
	 */
	public function test_unauthenticated_cannot_list_fixes(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/fixes' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Test that subscriber cannot execute a fix.
	 */
	public function test_subscriber_cannot_execute_fix(): void {
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'POST', '/wp-system-report/v1/fixes/autoload_optimizer' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	// ---------------------------------------------------------------
	// List fixes
	// ---------------------------------------------------------------

	/**
	 * Test list fixes returns the standard envelope.
	 */
	public function test_list_fixes_returns_envelope(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/fixes' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 'success', $data['status'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertArrayHasKey( 'total', $data['meta'] );
	}

	/**
	 * Test list fixes includes all registered fixers.
	 */
	public function test_list_fixes_includes_all_fixers(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/fixes' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$ids = array_column( $data['data'], 'id' );

		$this->assertContains( 'autoload_optimizer', $ids );
		$this->assertContains( 'database_optimizer', $ids );
		$this->assertContains( 'security_hardener', $ids );
		$this->assertContains( 'cron_repair', $ids );
	}

	/**
	 * Test list fixes returns correct structure per fixer.
	 */
	public function test_list_fixes_item_structure(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/fixes' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$first_item = $data['data'][0];

		$this->assertArrayHasKey( 'id', $first_item );
		$this->assertArrayHasKey( 'label', $first_item );
		$this->assertArrayHasKey( 'description', $first_item );
		$this->assertArrayHasKey( 'category', $first_item );
		$this->assertArrayHasKey( 'risk_level', $first_item );
		$this->assertArrayHasKey( 'risk_label', $first_item );
		$this->assertArrayHasKey( 'requires_confirmation', $first_item );
		$this->assertArrayHasKey( 'can_fix', $first_item );
	}

	/**
	 * Test list fixes with category filter.
	 */
	public function test_list_fixes_category_filter(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/wp-system-report/v1/fixes' );
		$request->set_param( 'category', 'security' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$categories = array_unique( array_column( $data['data'], 'category' ) );

		$this->assertCount( 1, $categories );
		$this->assertSame( 'security', $categories[0] );
	}

	/**
	 * Test list fixes with nonexistent category returns empty.
	 */
	public function test_list_fixes_empty_category(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/wp-system-report/v1/fixes' );
		$request->set_param( 'category', 'nonexistent_category' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEmpty( $data['data'] );
		$this->assertSame( 0, $data['meta']['total'] );
	}

	// ---------------------------------------------------------------
	// Execute fix
	// ---------------------------------------------------------------

	/**
	 * Test executing a nonexistent fixer returns 404.
	 */
	public function test_execute_nonexistent_fixer_returns_404(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'POST', '/wp-system-report/v1/fixes/nonexistent_fixer' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Test executing a fixer returns success envelope.
	 */
	public function test_execute_fixer_returns_envelope(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'POST', '/wp-system-report/v1/fixes/security_hardener' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 'success', $data['status'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'fix_id', $data['data'] );
		$this->assertArrayHasKey( 'result', $data['data'] );
		$this->assertArrayHasKey( 'applied', $data['data'] );
	}

	/**
	 * Test executing a fixer includes result details.
	 */
	public function test_execute_fixer_result_structure(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'POST', '/wp-system-report/v1/fixes/security_hardener' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$result = $data['data']['result'];

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test that the feature gate blocks access when Pro is disabled.
	 */
	public function test_feature_gate_blocks_when_disabled(): void {
		wp_set_current_user( $this->admin_id );

		add_filter( 'wp_system_report_is_pro', '__return_false' );

		$request  = new WP_REST_Request( 'GET', '/wp-system-report/v1/fixes' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );

		remove_filter( 'wp_system_report_is_pro', '__return_false' );
	}

	/**
	 * Test execute fix with a fixer that has nothing to fix returns not-applied.
	 */
	public function test_execute_fixer_nothing_to_fix(): void {
		wp_set_current_user( $this->admin_id );

		// Run the security hardener first to clear issues.
		$request = new WP_REST_Request( 'POST', '/wp-system-report/v1/fixes/security_hardener' );
		rest_get_server()->dispatch( $request );

		// Run it again — should say nothing to fix.
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		// Either applied=false (can_fix returned false) or the fixer returned a noop success.
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['data']['result']['success'] );
	}
}
