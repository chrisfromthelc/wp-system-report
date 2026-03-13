<?php
/**
 * Update Health collector tests.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Test the Update_Health collector output and status logic.
 */
class UpdateHealthTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Update_Health
	 */
	private \SystemReport\Collectors\Update_Health $collector;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_transient( sr_versioned_cache_key( 'sr_update_health' ) );
		$this->collector = new \SystemReport\Collectors\Update_Health();
	}

	/**
	 * Remove the cache transient after each test to avoid cross-test pollution.
	 */
	public function tear_down(): void {
		delete_transient( sr_versioned_cache_key( 'sr_update_health' ) );
		parent::tear_down();
	}

	// -------------------------------------------------------
	// Metadata tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector ID is 'update_health'.
	 */
	public function test_collector_id(): void {
		$this->assertSame( 'update_health', $this->collector->get_id() );
	}

	/**
	 * Test that the collector label is a non-empty string.
	 */
	public function test_collector_label_not_empty(): void {
		$label = $this->collector->get_label();

		$this->assertIsString( $label );
		$this->assertNotEmpty( $label );
	}

	/**
	 * Test that the collector priority is 210.
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 210, $this->collector->get_priority() );
	}

	// -------------------------------------------------------
	// Return type tests.
	// -------------------------------------------------------

	/**
	 * Test that collect() returns an array containing only Field instances.
	 */
	public function test_collect_returns_field_objects(): void {
		$fields = $this->collector->collect();

		$this->assertIsArray( $fields );
		$this->assertNotEmpty( $fields );

		foreach ( $fields as $index => $field ) {
			$this->assertInstanceOf(
				Field::class,
				$field,
				"Item at index {$index} should be a Field instance."
			);
		}
	}

	/**
	 * Test that collect() returns exactly 10 fields.
	 */
	public function test_field_count(): void {
		$fields = $this->collector->collect();

		$this->assertCount( 10, $fields, 'Update_Health collector should return exactly 10 fields.' );
	}

	// -------------------------------------------------------
	// Status validity tests.
	// -------------------------------------------------------

	/**
	 * Test that every field carries a valid Status enum value.
	 */
	public function test_all_fields_have_valid_status(): void {
		$fields          = $this->collector->collect();
		$valid_statuses  = array( Status::Good, Status::Warning, Status::Critical, Status::Info );

		if ( empty( $fields ) ) {
			$this->assertIsArray( $fields, 'Collector returned no fields to validate.' );
			return;
		}

		foreach ( $fields as $index => $field ) {
			$this->assertInstanceOf( Field::class, $field );
			$this->assertContains(
				$field->status,
				$valid_statuses,
				"Field at index {$index} ('{$field->label}') must have a valid Status enum value."
			);
		}
	}

	// -------------------------------------------------------
	// Individual field presence tests.
	// -------------------------------------------------------

	/**
	 * Test that the "Core Update Status" field is present.
	 */
	public function test_core_update_status_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Core Update Status' );

		$this->assertNotNull( $field, '"Core Update Status" field should be present.' );
		$this->assertNotEmpty( $field->value );
	}

	/**
	 * Test that the "Core Update Channel" field is present with a non-empty value.
	 */
	public function test_core_update_channel_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Core Update Channel' );

		$this->assertNotNull( $field, '"Core Update Channel" field should be present.' );
		$this->assertNotEmpty( $field->value, '"Core Update Channel" value should not be empty.' );
	}

	/**
	 * Test that the "Core Auto-Updates" field is present.
	 */
	public function test_core_auto_updates_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Core Auto-Updates' );

		$this->assertNotNull( $field, '"Core Auto-Updates" field should be present.' );
		$this->assertNotEmpty( $field->value );
	}

	/**
	 * Test that "Failed Updates" reports "None" in a clean test environment.
	 *
	 * The test environment has no failed core updates and no failed auto-update
	 * history, so the value should contain "None" and the status should be Good.
	 */
	public function test_failed_updates_none_by_default(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Failed Updates' );

		$this->assertNotNull( $field, '"Failed Updates" field should be present.' );
		$this->assertStringContainsString(
			'None',
			$field->value,
			'"Failed Updates" value should contain "None" when no failed updates exist.'
		);
		$this->assertSame(
			Status::Good,
			$field->status,
			'"Failed Updates" status should be Good when there are no failures.'
		);
	}

	/**
	 * Test that the "Translation Updates" field is present.
	 */
	public function test_translation_updates_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Translation Updates' );

		$this->assertNotNull( $field, '"Translation Updates" field should be present.' );
		$this->assertNotEmpty( $field->value );
	}

	// -------------------------------------------------------
	// Caching tests.
	// -------------------------------------------------------

	/**
	 * Test that get_cached_data() returns equal results on successive calls.
	 *
	 * The first call populates the transient; the second call reads from it.
	 * Both results must be identical arrays.
	 */
	public function test_caching_returns_same_data(): void {
		delete_transient( sr_versioned_cache_key( 'sr_update_health' ) );

		$first  = $this->collector->get_cached_data();
		$second = $this->collector->get_cached_data();

		$this->assertIsArray( $first );
		$this->assertIsArray( $second );
		$this->assertEquals(
			$first,
			$second,
			'get_cached_data() should return equal results on successive calls.'
		);
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
