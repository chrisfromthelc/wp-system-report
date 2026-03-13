<?php
/**
 * Tests for the Performance collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Performance collector tests.
 *
 * The Performance collector queries wp_options directly via $wpdb and inspects
 * the PHP OPcache extension.  The WordPress test environment provides a real
 * database, so row-count and size fields return genuine values.  OPcache
 * availability is determined at runtime and handled by the collector itself,
 * so no mocking is required.
 */
class PerformanceTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Performance
	 */
	private \SystemReport\Collectors\Performance $collector;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->collector = new \SystemReport\Collectors\Performance();

		// Ensure no stale transient from a previous run contaminates results.
		delete_transient( 'sr_performance' );
	}

	/**
	 * Tear down after each test.
	 *
	 * Purge the transient so that subsequent tests always start with a clean
	 * cache state.
	 */
	public function tear_down(): void {
		delete_transient( 'sr_performance' );
		parent::tear_down();
	}

	// -------------------------------------------------------
	// Metadata tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector returns the expected ID.
	 *
	 * @return void
	 */
	public function test_collector_id(): void {
		$this->assertSame( 'performance', $this->collector->get_id() );
	}

	/**
	 * Test that the collector returns a non-empty label.
	 *
	 * @return void
	 */
	public function test_collector_label_not_empty(): void {
		$label = $this->collector->get_label();

		$this->assertIsString( $label );
		$this->assertNotEmpty( $label );
	}

	/**
	 * Test that the collector returns priority 200.
	 *
	 * @return void
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 200, $this->collector->get_priority() );
	}

	// -------------------------------------------------------
	// Return type and structure tests.
	// -------------------------------------------------------

	/**
	 * Test that collect() returns an array where every element is a Field instance.
	 *
	 * @return void
	 */
	public function test_collect_returns_field_objects(): void {
		$fields = $this->collector->collect();

		$this->assertIsArray( $fields );
		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			$this->assertInstanceOf(
				Field::class,
				$field,
				'Every item returned by collect() must be an instance of Field.'
			);
		}
	}

	/**
	 * Test that collect() returns exactly 10 fields.
	 *
	 * The Performance collector always emits a fixed set of 10 fields:
	 * Object Cache Backend, Object Cache Drop-in, Page Cache Plugin,
	 * OPcache, Total wp_options Rows, wp_options Table Size,
	 * Expired Transients, Database Overhead, Top Autoloaded Options,
	 * and Persistent Object Cache.
	 *
	 * @return void
	 */
	public function test_field_count(): void {
		$fields = $this->collector->collect();

		$this->assertCount(
			10,
			$fields,
			'Performance collector must return exactly 10 fields.'
		);
	}

	/**
	 * Test that every field carries a valid Status enum case.
	 *
	 * The Status enum defines four cases: Good, Warning, Critical, Info.
	 * The collector must assign one of these to every field it emits.
	 *
	 * @return void
	 */
	public function test_all_fields_have_valid_status(): void {
		$valid_cases = Status::cases();
		$fields      = $this->collector->collect();

		if ( empty( $fields ) ) {
			$this->assertIsArray( $fields, 'collect() must return an array even when empty.' );
			return;
		}

		foreach ( $fields as $field ) {
			$this->assertInstanceOf( Field::class, $field );
			$this->assertContains(
				$field->status,
				$valid_cases,
				"Field '{$field->label}' must carry a valid Status enum case."
			);
		}
	}

	// -------------------------------------------------------
	// Individual field presence tests.
	// -------------------------------------------------------

	/**
	 * Test that an "Object Cache Backend" field is present in the results.
	 *
	 * @return void
	 */
	public function test_object_cache_backend_field_present(): void {
		$field = $this->find_field_by_label(
			$this->collector->collect(),
			'Object Cache Backend'
		);

		$this->assertNotNull(
			$field,
			'Expected an "Object Cache Backend" field to be present in the results.'
		);
		$this->assertNotEmpty(
			$field->value,
			'"Object Cache Backend" field value must not be empty.'
		);
	}

	/**
	 * Test that an "OPcache" field is present in the results.
	 *
	 * The field is emitted regardless of whether OPcache is available;
	 * the value and status change to reflect the runtime state.
	 *
	 * @return void
	 */
	public function test_opcache_field_present(): void {
		$field = $this->find_field_by_label(
			$this->collector->collect(),
			'OPcache'
		);

		$this->assertNotNull(
			$field,
			'Expected an "OPcache" field to be present in the results.'
		);
		$this->assertNotEmpty(
			$field->value,
			'"OPcache" field value must not be empty.'
		);
	}

	/**
	 * Test that a "Page Cache Plugin" field is present in the results.
	 *
	 * In the CI test environment no known cache plugin is active, so the
	 * value will be the "None detected" string.  The field itself must
	 * always be emitted.
	 *
	 * @return void
	 */
	public function test_page_cache_field_present(): void {
		$field = $this->find_field_by_label(
			$this->collector->collect(),
			'Page Cache Plugin'
		);

		$this->assertNotNull(
			$field,
			'Expected a "Page Cache Plugin" field to be present in the results.'
		);
		$this->assertNotEmpty(
			$field->value,
			'"Page Cache Plugin" field value must not be empty.'
		);
	}

	/**
	 * Test that an "Expired Transients" field is present in the results.
	 *
	 * The value is the formatted integer count of expired transient rows.
	 *
	 * @return void
	 */
	public function test_expired_transients_field_present(): void {
		$field = $this->find_field_by_label(
			$this->collector->collect(),
			'Expired Transients'
		);

		$this->assertNotNull(
			$field,
			'Expected an "Expired Transients" field to be present in the results.'
		);
		$this->assertNotEmpty(
			$field->value,
			'"Expired Transients" field value must not be empty.'
		);
	}

	// -------------------------------------------------------
	// Privacy / sensitive-data tests.
	// -------------------------------------------------------

	/**
	 * Test that the "Top Autoloaded Options" field is marked as private.
	 *
	 * Option names are internal WordPress data that should not appear in
	 * public exports.  The collector must set $field->private === true.
	 *
	 * @return void
	 */
	public function test_top_autoloaded_options_is_private(): void {
		$field = $this->find_field_by_label(
			$this->collector->collect(),
			'Top Autoloaded Options'
		);

		$this->assertNotNull(
			$field,
			'Expected a "Top Autoloaded Options" field to be present in the results.'
		);
		$this->assertTrue(
			$field->private,
			'"Top Autoloaded Options" field must be marked as private (private === true).'
		);
	}

	// -------------------------------------------------------
	// Caching tests.
	// -------------------------------------------------------

	/**
	 * Test that get_cached_data() stores results and returns the same data on
	 * the second call.
	 *
	 * The Performance collector uses the "sr_performance" transient.  After
	 * the first call the transient must exist and the second call must return
	 * an equal data set without re-running collect().
	 *
	 * @return void
	 */
	public function test_caching_returns_same_data(): void {
		// Guarantee no pre-existing transient.
		delete_transient( 'sr_performance' );

		$first_result = $this->collector->get_cached_data();

		$this->assertNotFalse(
			get_transient( 'sr_performance' ),
			'Transient "sr_performance" must be set after the first get_cached_data() call.'
		);

		$second_result = $this->collector->get_cached_data();

		$this->assertEquals(
			$first_result,
			$second_result,
			'Second call to get_cached_data() must return the same data as the first call.'
		);
	}

	// -------------------------------------------------------
	// Helper methods.
	// -------------------------------------------------------

	/**
	 * Find a Field in the given array by its label.
	 *
	 * @param array  $fields Array of Field objects to search.
	 * @param string $label  The label to match.
	 * @return Field|null The matching Field object, or null if not found.
	 */
	private function find_field_by_label( array $fields, string $label ): ?Field {
		foreach ( $fields as $field ) {
			if ( $field instanceof Field && $field->label === $label ) {
				return $field;
			}
		}

		return null;
	}
}
