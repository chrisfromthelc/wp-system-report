<?php
/**
 * Tests for the Advanced_Diagnostics collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Advanced_Diagnostics collector tests.
 *
 * The Advanced_Diagnostics collector queries wp_options for autoloaded data,
 * measures filesystem directory sizes, and inspects the PHP error log and
 * .htaccess file.  The WordPress test environment provides a live database and
 * real filesystem, so all assertions are exercised against genuine values
 * without filesystem or database mocking.
 */
class AdvancedDiagnosticsTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Advanced_Diagnostics
	 */
	private \SystemReport\Collectors\Advanced_Diagnostics $collector;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->collector = new \SystemReport\Collectors\Advanced_Diagnostics();

		// Ensure no stale transient from a previous run contaminates results.
		delete_transient( 'sr_advanced_diagnostics' );
	}

	/**
	 * Tear down after each test.
	 *
	 * Purge the transient so that subsequent tests always start with a clean
	 * cache state.
	 */
	public function tear_down(): void {
		delete_transient( 'sr_advanced_diagnostics' );
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
		$this->assertSame( 'advanced_diagnostics', $this->collector->get_id() );
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
	 * Test that the collector returns priority 170.
	 *
	 * @return void
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 170, $this->collector->get_priority() );
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
	 * Test that collect() returns exactly 8 fields.
	 *
	 * The Advanced_Diagnostics collector always emits a fixed set of 8 fields:
	 * Autoloaded Options Count, Autoloaded Options Size, Uploads Directory Size,
	 * Plugins Directory Size, Themes Directory Size, Rewrite Rules Count,
	 * PHP Error Log, and .htaccess Present.
	 *
	 * @return void
	 */
	public function test_field_count(): void {
		$fields = $this->collector->collect();

		$this->assertCount(
			8,
			$fields,
			'Advanced_Diagnostics collector must return exactly 8 fields.'
		);
	}

	/**
	 * Test that every field carries a valid Status enum case.
	 *
	 * The Status enum defines four cases: Good, Warning, Critical, Info.
	 * Every field emitted by the collector must carry one of these cases.
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
	 * Test that an "Autoloaded Options Count" field is present in the results.
	 *
	 * The value is the integer count of autoloaded rows, cast to a string
	 * by the Field constructor.
	 *
	 * @return void
	 */
	public function test_autoloaded_options_count_field_present(): void {
		$field = $this->find_field_by_label(
			$this->collector->collect(),
			'Autoloaded Options Count'
		);

		$this->assertNotNull(
			$field,
			'Expected an "Autoloaded Options Count" field to be present in the results.'
		);
		// The value must be a non-negative integer expressed as a string.
		$this->assertMatchesRegularExpression(
			'/^\d+$/',
			$field->value,
			'"Autoloaded Options Count" value must be a non-negative integer string.'
		);
	}

	/**
	 * Test that an "Autoloaded Options Size" field is present with a non-empty value.
	 *
	 * The value is a human-readable formatted size string (e.g. "512 B" or "1 KB").
	 *
	 * @return void
	 */
	public function test_autoloaded_options_size_field_present(): void {
		$field = $this->find_field_by_label(
			$this->collector->collect(),
			'Autoloaded Options Size'
		);

		$this->assertNotNull(
			$field,
			'Expected an "Autoloaded Options Size" field to be present in the results.'
		);
		$this->assertNotEmpty(
			$field->value,
			'"Autoloaded Options Size" field value must not be empty.'
		);
	}

	/**
	 * Test that the "Autoloaded Options Size" field is Status::Good in CI.
	 *
	 * The WordPress test environment contains minimal autoloaded data — well
	 * below the 800 KB warning threshold.  The collector must therefore
	 * assign Status::Good to this field in a clean test environment.
	 *
	 * @return void
	 */
	public function test_autoloaded_options_size_status_is_good_when_small(): void {
		// Clear any cached data to force a fresh collect() call.
		delete_transient( 'sr_advanced_diagnostics' );

		$field = $this->find_field_by_label(
			$this->collector->collect(),
			'Autoloaded Options Size'
		);

		$this->assertNotNull(
			$field,
			'Expected an "Autoloaded Options Size" field to be present in the results.'
		);

		// In CI the autoloaded payload is tiny; only assert Good when truly small.
		// We query the size directly to keep the assertion data-driven rather than
		// relying on a hard-coded threshold that could drift with WordPress updates.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test-only diagnostic query.
		$autoload_size = (int) $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes', 'on')"
		);

		if ( $autoload_size <= 819200 ) {
			$this->assertSame(
				Status::Good,
				$field->status,
				'"Autoloaded Options Size" must be Status::Good when autoloaded data is under 800 KB.'
			);
		} else {
			// The environment has a large autoloaded payload; skip the Good assertion.
			$this->markTestSkipped(
				sprintf(
					'Autoloaded options size is %d bytes, which exceeds the 800 KB threshold; skipping Status::Good assertion.',
					$autoload_size
				)
			);
		}
	}

	/**
	 * Test that a "Rewrite Rules Count" field is present with a numeric-ish value.
	 *
	 * The value is stored as an integer cast to string by the Field constructor.
	 * An empty rewrite_rules option yields "0".
	 *
	 * @return void
	 */
	public function test_rewrite_rules_count_field_present(): void {
		$field = $this->find_field_by_label(
			$this->collector->collect(),
			'Rewrite Rules Count'
		);

		$this->assertNotNull(
			$field,
			'Expected a "Rewrite Rules Count" field to be present in the results.'
		);
		// Value must be a non-negative integer expressed as a string.
		$this->assertMatchesRegularExpression(
			'/^\d+$/',
			$field->value,
			'"Rewrite Rules Count" value must be a non-negative integer string.'
		);
	}

	/**
	 * Test that a ".htaccess Present" field is present in the results.
	 *
	 * The field reflects whether ABSPATH . '.htaccess' exists.  The value
	 * will be either "Yes" or "No" (or their translated equivalents).
	 * The test environment may or may not have an .htaccess file; we only
	 * assert the field is emitted with a non-empty value.
	 *
	 * @return void
	 */
	public function test_htaccess_present_field(): void {
		$field = $this->find_field_by_label(
			$this->collector->collect(),
			'.htaccess Present'
		);

		$this->assertNotNull(
			$field,
			'Expected a ".htaccess Present" field to be present in the results.'
		);
		$this->assertNotEmpty(
			$field->value,
			'".htaccess Present" field value must not be empty.'
		);
	}

	// -------------------------------------------------------
	// Caching tests.
	// -------------------------------------------------------

	/**
	 * Test that get_cached_data() stores results and returns the same data on
	 * the second call.
	 *
	 * The Advanced_Diagnostics collector uses the "sr_advanced_diagnostics"
	 * transient.  After the first call the transient must exist, and the
	 * second call must return an equal data set without re-running collect().
	 *
	 * @return void
	 */
	public function test_caching_returns_same_data(): void {
		// Guarantee no pre-existing transient.
		delete_transient( 'sr_advanced_diagnostics' );

		$first_result = $this->collector->get_cached_data();

		$this->assertNotFalse(
			get_transient( 'sr_advanced_diagnostics' ),
			'Transient "sr_advanced_diagnostics" must be set after the first get_cached_data() call.'
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
