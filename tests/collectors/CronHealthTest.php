<?php
/**
 * Cron Health collector tests.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Test the Cron_Health collector output and status logic.
 */
class CronHealthTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Cron_Health
	 */
	private \SystemReport\Collectors\Cron_Health $collector;

	/**
	 * Snapshot of the cron array before each test.
	 *
	 * @var array|false
	 */
	private $original_cron_array;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->original_cron_array = _get_cron_array();
		$this->collector           = new \SystemReport\Collectors\Cron_Health();
	}

	/**
	 * Restore the cron array and clean up transients after each test.
	 */
	public function tear_down(): void {
		_set_cron_array( $this->original_cron_array );
		delete_transient( 'doing_cron' );
		parent::tear_down();
	}

	// -------------------------------------------------------
	// Metadata tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector ID is 'cron_health'.
	 */
	public function test_collector_id(): void {
		$this->assertSame( 'cron_health', $this->collector->get_id() );
	}

	/**
	 * Test that the collector label is not empty.
	 */
	public function test_collector_label(): void {
		$this->assertNotEmpty( $this->collector->get_label() );
		$this->assertIsString( $this->collector->get_label() );
	}

	/**
	 * Test that the collector priority is 130.
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 130, $this->collector->get_priority() );
	}

	// -------------------------------------------------------
	// Return type tests.
	// -------------------------------------------------------

	/**
	 * Test that collect() returns an array of Field objects.
	 */
	public function test_collect_returns_array_of_field_objects(): void {
		$fields = $this->collector->collect();

		$this->assertIsArray( $fields );
		$this->assertNotEmpty( $fields );

		foreach ( $fields as $index => $field ) {
			$this->assertInstanceOf(
				Field::class,
				$field,
				"Field at index {$index} should be a Field instance."
			);
		}
	}

	// -------------------------------------------------------
	// WP-Cron Disabled field tests.
	// -------------------------------------------------------

	/**
	 * Test that the 'WP-Cron Disabled' field is present.
	 */
	public function test_cron_disabled_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'WP-Cron Disabled' );

		$this->assertNotNull( $field, "'WP-Cron Disabled' field should be present." );
	}

	/**
	 * Test that the 'WP-Cron Disabled' field is Status::Good when DISABLE_WP_CRON is not set.
	 *
	 * The test environment does not define DISABLE_WP_CRON, so the status
	 * must be Good and the value must reflect the false/disabled state.
	 */
	public function test_cron_not_disabled_is_good(): void {
		// Verify the test environment does not have DISABLE_WP_CRON set to true.
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		if ( $cron_disabled ) {
			$this->markTestSkipped( 'DISABLE_WP_CRON is true in this test environment.' );
		}

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'WP-Cron Disabled' );

		$this->assertNotNull( $field );
		$this->assertSame( Status::Good, $field->status );
	}

	// -------------------------------------------------------
	// Total Scheduled Events field tests.
	// -------------------------------------------------------

	/**
	 * Test that the 'Total Scheduled Events' field is present with a numeric value.
	 */
	public function test_total_events_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Total Scheduled Events' );

		$this->assertNotNull( $field, "'Total Scheduled Events' field should be present." );
		$this->assertIsNumeric( $field->value );
	}

	// -------------------------------------------------------
	// Next Cron Run field tests.
	// -------------------------------------------------------

	/**
	 * Test that the 'Next Cron Run' field is present.
	 */
	public function test_next_cron_run_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Next Cron Run' );

		$this->assertNotNull( $field, "'Next Cron Run' field should be present." );
		$this->assertNotEmpty( $field->value );
	}

	// -------------------------------------------------------
	// Overdue Events field status tests.
	// -------------------------------------------------------

	/**
	 * Test that 'Overdue Events' is Status::Good when all events are in the future.
	 */
	public function test_overdue_events_zero_is_good(): void {
		// Start with an empty cron array and schedule only future events.
		_set_cron_array( array() );
		wp_schedule_single_event( time() + 3600, 'sr_test_future_hook_1' );
		wp_schedule_single_event( time() + 7200, 'sr_test_future_hook_2' );

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Overdue Events' );

		$this->assertNotNull( $field, "'Overdue Events' field should be present." );
		$this->assertSame( Status::Good, $field->status );
		$this->assertSame( '0', $field->value );
	}

	/**
	 * Test that 'Overdue Events' is Status::Warning when 1–5 events are overdue.
	 */
	public function test_overdue_events_few_is_warning(): void {
		// Start with an empty cron array, then add 3 past-timestamp events.
		_set_cron_array( array() );
		wp_schedule_single_event( time() - 3600, 'sr_test_overdue_hook_1' );
		wp_schedule_single_event( time() - 3600, 'sr_test_overdue_hook_2' );
		wp_schedule_single_event( time() - 3600, 'sr_test_overdue_hook_3' );

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Overdue Events' );

		$this->assertNotNull( $field, "'Overdue Events' field should be present." );
		$this->assertSame( Status::Warning, $field->status );
		$this->assertGreaterThan( 0, (int) $field->value );
		$this->assertLessThanOrEqual( 5, (int) $field->value );
	}

	/**
	 * Test that 'Overdue Events' is Status::Critical when more than 5 events are overdue.
	 */
	public function test_overdue_events_many_is_critical(): void {
		// Start with an empty cron array, then add 6 past-timestamp events.
		_set_cron_array( array() );
		wp_schedule_single_event( time() - 3600, 'sr_test_overdue_hook_1' );
		wp_schedule_single_event( time() - 3600, 'sr_test_overdue_hook_2' );
		wp_schedule_single_event( time() - 3600, 'sr_test_overdue_hook_3' );
		wp_schedule_single_event( time() - 3600, 'sr_test_overdue_hook_4' );
		wp_schedule_single_event( time() - 3600, 'sr_test_overdue_hook_5' );
		wp_schedule_single_event( time() - 3600, 'sr_test_overdue_hook_6' );

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Overdue Events' );

		$this->assertNotNull( $field, "'Overdue Events' field should be present." );
		$this->assertSame( Status::Critical, $field->status );
		$this->assertGreaterThan( 5, (int) $field->value );
	}

	// -------------------------------------------------------
	// Overdue Event Hooks field tests.
	// -------------------------------------------------------

	/**
	 * Test that 'Overdue Event Hooks' field appears when overdue events exist.
	 */
	public function test_overdue_hooks_listed_when_present(): void {
		// Start with an empty cron array, then add overdue events.
		_set_cron_array( array() );
		wp_schedule_single_event( time() - 3600, 'sr_test_overdue_hook_1' );
		wp_schedule_single_event( time() - 3600, 'sr_test_overdue_hook_2' );

		$fields       = $this->collector->collect();
		$overdue_hook = $this->find_field_by_label( $fields, 'Overdue Event Hooks' );

		$this->assertNotNull( $overdue_hook, "'Overdue Event Hooks' field should be present when overdue events exist." );
		$this->assertSame( Status::Info, $overdue_hook->status );
		$this->assertNotEmpty( $overdue_hook->value );
		$this->assertStringContainsString( 'sr_test_overdue_hook', $overdue_hook->value );
	}

	/**
	 * Test that 'Overdue Event Hooks' field is absent when no events are overdue.
	 */
	public function test_overdue_hooks_absent_when_no_overdue(): void {
		// Start with an empty cron array and schedule only future events.
		_set_cron_array( array() );
		wp_schedule_single_event( time() + 3600, 'sr_test_future_hook_1' );

		$fields       = $this->collector->collect();
		$overdue_hook = $this->find_field_by_label( $fields, 'Overdue Event Hooks' );

		$this->assertNull( $overdue_hook, "'Overdue Event Hooks' field should not be present when no events are overdue." );
	}

	// -------------------------------------------------------
	// Last Cron Run field tests.
	// -------------------------------------------------------

	/**
	 * Test 'Last Cron Run' shows 'Unknown' when the doing_cron transient is absent.
	 */
	public function test_last_cron_run_unknown_without_transient(): void {
		delete_transient( 'doing_cron' );

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Last Cron Run' );

		$this->assertNotNull( $field, "'Last Cron Run' field should be present." );
		$this->assertSame( Status::Info, $field->status );

		// Without the transient, value should indicate no recent execution.
		$value_lower = strtolower( $field->value );
		$this->assertTrue(
			false !== strpos( $value_lower, 'unknown' ) || false !== strpos( $value_lower, 'no recent' ),
			"'Last Cron Run' value should contain 'Unknown' or 'No recent' when transient is absent. Got: {$field->value}"
		);
	}

	/**
	 * Test 'Last Cron Run' shows time info when the doing_cron transient is set.
	 */
	public function test_last_cron_run_with_transient(): void {
		// Simulate a cron run 5 minutes ago.
		set_transient( 'doing_cron', time() - 300 );

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Last Cron Run' );

		$this->assertNotNull( $field, "'Last Cron Run' field should be present." );
		$this->assertSame( Status::Info, $field->status );
		// The value should contain a time-relative description.
		$this->assertNotEmpty( $field->value );
		$this->assertStringNotEqualsIgnoringCase( 'Unknown', $field->value );
	}

	// -------------------------------------------------------
	// Helper methods.
	// -------------------------------------------------------

	/**
	 * Find a field in the collected array by its label.
	 *
	 * @param Field[] $fields Array of collected Field objects.
	 * @param string  $label  The label to search for.
	 * @return Field|null The matching field, or null if not found.
	 */
	private function find_field_by_label( array $fields, string $label ): ?Field {
		foreach ( $fields as $field ) {
			if ( $field instanceof Field && $label === $field->label ) {
				return $field;
			}
		}
		return null;
	}
}
