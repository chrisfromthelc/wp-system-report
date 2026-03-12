<?php
/**
 * Tests for the fixer infrastructure and individual fixers.
 *
 * @package SystemReport
 */

use SystemReport\Fix_Result;
use SystemReport\Fixer_Registry;
use SystemReport\Fixers\Autoload_Optimizer;
use SystemReport\Risk_Level;

/**
 * Fixer tests.
 */
class FixersTest extends WP_UnitTestCase {

	/**
	 * Fixer registry instance.
	 *
	 * @var Fixer_Registry
	 */
	private Fixer_Registry $registry;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->registry = new Fixer_Registry();
	}

	// -------------------------------------------------------
	// Fix_Result value object tests.
	// -------------------------------------------------------

	/**
	 * Test Fix_Result::success() factory method.
	 */
	public function test_fix_result_success() {
		$result = Fix_Result::success(
			'All good',
			array( 'count' => 5 ),
			array( 'count' => 0 )
		);

		$this->assertTrue( $result->success );
		$this->assertSame( 'All good', $result->message );
		$this->assertSame( array( 'count' => 5 ), $result->before );
		$this->assertSame( array( 'count' => 0 ), $result->after );
		$this->assertSame( array(), $result->errors );
	}

	/**
	 * Test Fix_Result::failure() factory method.
	 */
	public function test_fix_result_failure() {
		$result = Fix_Result::failure(
			'Something failed',
			array( 'Database error' )
		);

		$this->assertFalse( $result->success );
		$this->assertSame( 'Something failed', $result->message );
		$this->assertSame( array( 'Database error' ), $result->errors );
		$this->assertSame( array(), $result->before );
		$this->assertSame( array(), $result->after );
	}

	/**
	 * Test Fix_Result JSON serialization.
	 */
	public function test_fix_result_json_serializable() {
		$result = Fix_Result::success( 'Done', array( 'a' => 1 ), array( 'a' => 0 ) );
		$json   = wp_json_encode( $result );
		$data   = json_decode( $json, true );

		$this->assertTrue( $data['success'] );
		$this->assertSame( 'Done', $data['message'] );
		$this->assertArrayHasKey( 'before', $data );
		$this->assertArrayHasKey( 'after', $data );
	}

	/**
	 * Test Fix_Result to_array excludes empty arrays.
	 */
	public function test_fix_result_to_array_excludes_empty() {
		$result = Fix_Result::success( 'Clean' );
		$data   = $result->to_array();

		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertArrayNotHasKey( 'before', $data );
		$this->assertArrayNotHasKey( 'after', $data );
		$this->assertArrayNotHasKey( 'errors', $data );
	}

	// -------------------------------------------------------
	// Fixer_Registry tests.
	// -------------------------------------------------------

	/**
	 * Test registry starts empty.
	 */
	public function test_registry_starts_empty() {
		$this->assertSame( array(), $this->registry->get_all() );
	}

	/**
	 * Test registering and retrieving a fixer.
	 */
	public function test_registry_register_and_get() {
		$fixer = new Autoload_Optimizer();
		$this->registry->register( $fixer );

		$this->assertTrue( $this->registry->has( 'autoload_optimizer' ) );
		$this->assertSame( $fixer, $this->registry->get( 'autoload_optimizer' ) );
	}

	/**
	 * Test retrieving a non-existent fixer returns null.
	 */
	public function test_registry_get_returns_null_for_unknown() {
		$this->assertNull( $this->registry->get( 'nonexistent' ) );
	}

	/**
	 * Test has() returns false for unregistered fixer.
	 */
	public function test_registry_has_returns_false_for_unknown() {
		$this->assertFalse( $this->registry->has( 'nonexistent' ) );
	}

	/**
	 * Test get_by_category filters correctly.
	 */
	public function test_registry_get_by_category() {
		$fixer = new Autoload_Optimizer();
		$this->registry->register( $fixer );

		$performance = $this->registry->get_by_category( 'performance' );
		$security    = $this->registry->get_by_category( 'security' );

		$this->assertCount( 1, $performance );
		$this->assertSame( $fixer, $performance['autoload_optimizer'] );
		$this->assertSame( array(), $security );
	}

	/**
	 * Test the wp_system_report_fixers filter.
	 */
	public function test_registry_filter() {
		$fixer = new Autoload_Optimizer();
		$this->registry->register( $fixer );

		add_filter(
			'wp_system_report_fixers',
			function ( array $fixers ): array {
				unset( $fixers['autoload_optimizer'] );
				return $fixers;
			}
		);

		$this->assertSame( array(), $this->registry->get_all() );

		// Clean up.
		remove_all_filters( 'wp_system_report_fixers' );
	}

	// -------------------------------------------------------
	// Autoload_Optimizer fixer metadata tests.
	// -------------------------------------------------------

	/**
	 * Test autoload optimizer metadata.
	 */
	public function test_autoload_optimizer_metadata() {
		$fixer = new Autoload_Optimizer();

		$this->assertSame( 'autoload_optimizer', $fixer->get_id() );
		$this->assertNotEmpty( $fixer->get_label() );
		$this->assertNotEmpty( $fixer->get_description() );
		$this->assertSame( 'performance', $fixer->get_category() );
		$this->assertSame( Risk_Level::Medium, $fixer->get_risk_level() );
	}

	/**
	 * Test autoload optimizer default threshold.
	 */
	public function test_autoload_optimizer_default_threshold() {
		$fixer = new Autoload_Optimizer();
		$this->assertSame( 100 * 1024, $fixer->get_threshold() );
	}

	/**
	 * Test autoload optimizer custom threshold.
	 */
	public function test_autoload_optimizer_custom_threshold() {
		$fixer = new Autoload_Optimizer( 50 * 1024 );
		$this->assertSame( 50 * 1024, $fixer->get_threshold() );
	}

	/**
	 * Test autoload optimizer threshold filter.
	 */
	public function test_autoload_optimizer_threshold_filter() {
		$fixer = new Autoload_Optimizer();

		add_filter( 'wp_system_report_autoload_threshold', function (): int {
			return 200 * 1024;
		} );

		$this->assertSame( 200 * 1024, $fixer->get_threshold() );

		remove_all_filters( 'wp_system_report_autoload_threshold' );
	}

	// -------------------------------------------------------
	// Autoload_Optimizer functional tests.
	// -------------------------------------------------------

	/**
	 * Test can_fix returns false when no bloated options exist.
	 */
	public function test_autoload_optimizer_can_fix_false_when_clean() {
		$fixer = new Autoload_Optimizer();
		// Default options in a fresh WP install are well under 100 KB.
		$this->assertFalse( $fixer->can_fix() );
	}

	/**
	 * Test can_fix returns true when bloated option exists.
	 */
	public function test_autoload_optimizer_can_fix_true_when_bloated() {
		global $wpdb;

		// Insert a large autoloaded option (150 KB).
		$large_value = str_repeat( 'x', 150 * 1024 );
		update_option( 'sr_test_bloated_option', $large_value, 'yes' );

		$fixer = new Autoload_Optimizer();
		$this->assertTrue( $fixer->can_fix() );

		delete_option( 'sr_test_bloated_option' );
	}

	/**
	 * Test fix() succeeds and disables autoload for bloated options.
	 */
	public function test_autoload_optimizer_fix_disables_autoload() {
		global $wpdb;

		// Insert a large autoloaded option.
		$large_value = str_repeat( 'y', 150 * 1024 );
		update_option( 'sr_test_large_opt', $large_value, 'yes' );

		$fixer  = new Autoload_Optimizer();
		$result = $fixer->fix();

		$this->assertTrue( $result->success );
		$this->assertNotEmpty( $result->before );
		$this->assertNotEmpty( $result->after );

		// Verify the option was switched to no-autoload.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test assertion query.
		$autoload = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
				'sr_test_large_opt'
			)
		);
		$this->assertSame( 'no', $autoload );

		// The before snapshot should include the option.
		$this->assertArrayHasKey( 'sr_test_large_opt', $result->before['bloated_options'] );

		// The after snapshot should confirm optimization.
		$this->assertContains( 'sr_test_large_opt', $result->after['optimized_options'] );

		delete_option( 'sr_test_large_opt' );
	}

	/**
	 * Test fix() returns success with nothing to do when clean.
	 */
	public function test_autoload_optimizer_fix_noop_when_clean() {
		$fixer  = new Autoload_Optimizer();
		$result = $fixer->fix();

		$this->assertTrue( $result->success );
		$this->assertStringContainsString( 'Nothing to optimize', $result->message );
	}

	/**
	 * Test fix() does not modify protected WordPress core options.
	 */
	public function test_autoload_optimizer_protects_core_options() {
		global $wpdb;

		// The 'rewrite_rules' option is often large and is protected.
		// We'll use a low threshold to catch it.
		$fixer = new Autoload_Optimizer( 1 ); // 1-byte threshold catches everything.

		$result = $fixer->fix();

		// Even with a 1-byte threshold, core options like siteurl must remain autoloaded.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test assertion query.
		$autoload = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
				'siteurl'
			)
		);
		$this->assertNotSame( 'no', $autoload );
	}

	/**
	 * Test the protected option filter.
	 */
	public function test_autoload_optimizer_protected_filter() {
		global $wpdb;

		// Insert a large option.
		$large_value = str_repeat( 'z', 150 * 1024 );
		update_option( 'sr_test_protected_opt', $large_value, 'yes' );

		// Mark it as protected via filter.
		add_filter(
			'wp_system_report_autoload_protected',
			function ( bool $protected, string $name ): bool {
				if ( 'sr_test_protected_opt' === $name ) {
					return true;
				}
				return $protected;
			},
			10,
			2
		);

		$fixer = new Autoload_Optimizer();
		// It should not be fixable because the only bloated option is protected.
		$this->assertFalse( $fixer->can_fix() );

		remove_all_filters( 'wp_system_report_autoload_protected' );
		delete_option( 'sr_test_protected_opt' );
	}

	/**
	 * Test fix() handles multiple bloated options.
	 */
	public function test_autoload_optimizer_fixes_multiple_options() {
		// Insert two large autoloaded options.
		update_option( 'sr_test_multi_a', str_repeat( 'a', 150 * 1024 ), 'yes' );
		update_option( 'sr_test_multi_b', str_repeat( 'b', 200 * 1024 ), 'yes' );

		$fixer  = new Autoload_Optimizer();
		$result = $fixer->fix();

		$this->assertTrue( $result->success );
		$this->assertSame( 2, $result->after['optimized_count'] );
		$this->assertContains( 'sr_test_multi_a', $result->after['optimized_options'] );
		$this->assertContains( 'sr_test_multi_b', $result->after['optimized_options'] );

		// Verify the total autoload size decreased.
		$this->assertLessThan(
			$result->before['total_autoload_size'],
			$result->after['total_autoload_size']
		);

		delete_option( 'sr_test_multi_a' );
		delete_option( 'sr_test_multi_b' );
	}

	/**
	 * Test that the fixer is registered in the default plugin fixers.
	 */
	public function test_autoload_optimizer_registered_in_plugin() {
		$plugin   = \SystemReport\Plugin::get_instance();
		$registry = $plugin->get_fixer_registry();

		$this->assertTrue( $registry->has( 'autoload_optimizer' ) );
		$this->assertInstanceOf( Autoload_Optimizer::class, $registry->get( 'autoload_optimizer' ) );
	}

	/**
	 * Test Risk_Level enum labels.
	 */
	public function test_risk_level_labels() {
		$this->assertNotEmpty( Risk_Level::Low->get_label() );
		$this->assertNotEmpty( Risk_Level::Medium->get_label() );
		$this->assertNotEmpty( Risk_Level::High->get_label() );
	}

	/**
	 * Test Risk_Level confirmation requirements.
	 */
	public function test_risk_level_requires_confirmation() {
		$this->assertFalse( Risk_Level::Low->requires_confirmation() );
		$this->assertTrue( Risk_Level::Medium->requires_confirmation() );
		$this->assertTrue( Risk_Level::High->requires_confirmation() );
	}
}
