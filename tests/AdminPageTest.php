<?php
/**
 * Tests for Admin_Page Interactivity API hook gating.
 *
 * Verifies that the deprecated print_client_interactivity_data hook is only
 * registered on WordPress < 6.7, where WordPress does not automatically print
 * Interactivity API state in admin_footer.
 *
 * @package SystemReport
 * @since 1.0.0
 */

use SystemReport\Admin_Page;
use SystemReport\Report_Generator;

/**
 * Admin_Page tests.
 */
class AdminPageTest extends WP_UnitTestCase {

	/**
	 * Original WordPress version restored after each test.
	 *
	 * @var string
	 */
	private string $original_wp_version;

	/**
	 * Save the original WP version before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->original_wp_version = $GLOBALS['wp_version'];
	}

	/**
	 * Restore the original WP version and clean up any hooks after each test.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_version'] = $this->original_wp_version;
		remove_all_actions( 'admin_footer' );
		parent::tear_down();
	}

	/**
	 * Invoke the private enqueue_interactivity_assets() method via reflection.
	 *
	 * @param Admin_Page $page     The Admin_Page instance under test.
	 * @param string     $tab      Active tab slug to pass to the method.
	 */
	private function invoke_enqueue_interactivity_assets( Admin_Page $page, string $tab = 'report' ): void {
		$reflection = new ReflectionClass( $page );
		$method     = $reflection->getMethod( 'enqueue_interactivity_assets' );
		$method->setAccessible( true );
		$method->invoke( $page, $tab );
	}

	/**
	 * Build an Admin_Page instance with a mocked Report_Generator.
	 *
	 * @return Admin_Page
	 */
	private function make_admin_page(): Admin_Page {
		$generator = $this->createMock( Report_Generator::class );
		return new Admin_Page( $generator );
	}

	// -------------------------------------------------------
	// print_client_interactivity_data hook-gating tests.
	// -------------------------------------------------------

	/**
	 * On WP 6.7+, the deprecated hook must NOT be registered.
	 *
	 * WordPress 6.7 introduced native admin_footer support for Interactivity API
	 * state, making print_client_interactivity_data() unnecessary and deprecated.
	 *
	 * @ticket 96
	 */
	public function test_deprecated_hook_not_added_on_wp_67_and_above(): void {
		if ( ! function_exists( 'wp_interactivity' ) ) {
			$this->markTestSkipped( 'wp_interactivity() not available — WP < 6.5.' );
		}

		$GLOBALS['wp_version'] = '6.7.0';
		$page                  = $this->make_admin_page();

		$this->invoke_enqueue_interactivity_assets( $page );

		$this->assertFalse(
			has_action( 'admin_footer', array( wp_interactivity(), 'print_client_interactivity_data' ) ),
			'Deprecated print_client_interactivity_data hook must not be added on WP 6.7+.'
		);
	}

	/**
	 * On WP 6.9 (the site's current version), the deprecated hook must NOT be registered.
	 *
	 * @ticket 96
	 */
	public function test_deprecated_hook_not_added_on_wp_69(): void {
		if ( ! function_exists( 'wp_interactivity' ) ) {
			$this->markTestSkipped( 'wp_interactivity() not available — WP < 6.5.' );
		}

		$GLOBALS['wp_version'] = '6.9.4';
		$page                  = $this->make_admin_page();

		$this->invoke_enqueue_interactivity_assets( $page );

		$this->assertFalse(
			has_action( 'admin_footer', array( wp_interactivity(), 'print_client_interactivity_data' ) ),
			'Deprecated print_client_interactivity_data hook must not be added on WP 6.9.'
		);
	}

	/**
	 * On WP < 6.7, the hook MUST be registered so Interactivity API state
	 * is still printed in admin_footer (WordPress does not handle this natively).
	 *
	 * @ticket 96
	 */
	public function test_deprecated_hook_is_added_on_wp_66(): void {
		if ( ! function_exists( 'wp_interactivity' ) ) {
			$this->markTestSkipped( 'wp_interactivity() not available — WP < 6.5.' );
		}

		$GLOBALS['wp_version'] = '6.6.9';
		$page                  = $this->make_admin_page();

		$this->invoke_enqueue_interactivity_assets( $page );

		$this->assertNotFalse(
			has_action( 'admin_footer', array( wp_interactivity(), 'print_client_interactivity_data' ) ),
			'print_client_interactivity_data hook must be added on WP < 6.7 to print Interactivity API state.'
		);
	}

	/**
	 * On WP 6.5 (initial Interactivity API stable release), the hook MUST be registered.
	 *
	 * @ticket 96
	 */
	public function test_deprecated_hook_is_added_on_wp_65(): void {
		if ( ! function_exists( 'wp_interactivity' ) ) {
			$this->markTestSkipped( 'wp_interactivity() not available — WP < 6.5.' );
		}

		$GLOBALS['wp_version'] = '6.5.0';
		$page                  = $this->make_admin_page();

		$this->invoke_enqueue_interactivity_assets( $page );

		$this->assertNotFalse(
			has_action( 'admin_footer', array( wp_interactivity(), 'print_client_interactivity_data' ) ),
			'print_client_interactivity_data hook must be added on WP 6.5 to print Interactivity API state.'
		);
	}

	/**
	 * The version boundary must be exactly 6.7: 6.6.x adds the hook, 6.7.0 does not.
	 *
	 * @ticket 96
	 */
	public function test_version_boundary_at_67(): void {
		if ( ! function_exists( 'wp_interactivity' ) ) {
			$this->markTestSkipped( 'wp_interactivity() not available — WP < 6.5.' );
		}

		// Just below the boundary — hook expected.
		$GLOBALS['wp_version'] = '6.6.99';
		$page                  = $this->make_admin_page();
		$this->invoke_enqueue_interactivity_assets( $page );

		$this->assertNotFalse(
			has_action( 'admin_footer', array( wp_interactivity(), 'print_client_interactivity_data' ) ),
			'Hook must be registered on WP 6.6.99 (below the 6.7 boundary).'
		);

		// Clean up and test the exact boundary — hook NOT expected.
		remove_all_actions( 'admin_footer' );

		$GLOBALS['wp_version'] = '6.7.0';
		$page                  = $this->make_admin_page();
		$this->invoke_enqueue_interactivity_assets( $page );

		$this->assertFalse(
			has_action( 'admin_footer', array( wp_interactivity(), 'print_client_interactivity_data' ) ),
			'Hook must NOT be registered on WP 6.7.0 (at the 6.7 boundary).'
		);
	}
}
