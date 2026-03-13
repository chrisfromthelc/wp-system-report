<?php
/**
 * Site Health collector tests.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Test the Site_Health collector output and status logic.
 */
class SiteHealthTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Site_Health
	 */
	private \SystemReport\Collectors\Site_Health $collector;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_transient( 'sr_site_health' );
		$this->collector = new \SystemReport\Collectors\Site_Health();
	}

	/**
	 * Remove the cache transient after each test to avoid cross-test pollution.
	 */
	public function tear_down(): void {
		delete_transient( 'sr_site_health' );
		parent::tear_down();
	}

	// -------------------------------------------------------
	// Metadata tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector ID is 'site_health'.
	 */
	public function test_collector_id(): void {
		$this->assertSame( 'site_health', $this->collector->get_id() );
	}

	/**
	 * Test that the collector label is not empty.
	 */
	public function test_collector_label(): void {
		$this->assertNotEmpty( $this->collector->get_label() );
		$this->assertIsString( $this->collector->get_label() );
	}

	/**
	 * Test that the collector priority is 120.
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 120, $this->collector->get_priority() );
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
	// Summary field tests.
	// -------------------------------------------------------

	/**
	 * Test that the first field is the 'Site Health Summary' field.
	 */
	public function test_summary_field_is_first(): void {
		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields );

		$first = reset( $fields );
		$this->assertInstanceOf( Field::class, $first );
		$this->assertSame( 'Site Health Summary', $first->label );
	}

	/**
	 * Test that the summary field value contains good, recommended, and critical counts.
	 *
	 * The format is "%d good, %d recommended, %d critical".
	 */
	public function test_summary_field_has_count_info(): void {
		$fields  = $this->collector->collect();
		$summary = reset( $fields );

		$this->assertNotNull( $summary );
		$this->assertSame( 'Site Health Summary', $summary->label );

		$value_lower = strtolower( $summary->value );
		$this->assertStringContainsString( 'good', $value_lower );
		$this->assertStringContainsString( 'recommended', $value_lower );
		$this->assertStringContainsString( 'critical', $value_lower );
	}

	// -------------------------------------------------------
	// Individual test field status tests.
	// -------------------------------------------------------

	/**
	 * Test that all non-summary fields have a valid Status enum value.
	 */
	public function test_individual_tests_have_valid_status(): void {
		$fields          = $this->collector->collect();
		$non_summary     = array_slice( $fields, 1 );
		$valid_statuses  = array( Status::Good, Status::Warning, Status::Critical );

		if ( empty( $non_summary ) ) {
			$this->assertIsArray( $non_summary, 'No individual site health test fields to validate.' );
			return;
		}

		foreach ( $non_summary as $index => $field ) {
			$this->assertInstanceOf( Field::class, $field );
			$this->assertContains(
				$field->status,
				$valid_statuses,
				"Field at index {$index} ('{$field->label}') should have Good, Warning, or Critical status."
			);
		}
	}

	/**
	 * Test that each non-summary field value is a ucfirst status string.
	 *
	 * The collector stores ucfirst( $test_result['status'] ) as the value,
	 * so valid values are 'Good', 'Recommended', or 'Critical'.
	 */
	public function test_field_values_are_ucfirst_status(): void {
		$fields      = $this->collector->collect();
		$non_summary = array_slice( $fields, 1 );
		$valid_values = array( 'Good', 'Recommended', 'Critical' );

		if ( empty( $non_summary ) ) {
			$this->assertIsArray( $non_summary, 'No individual site health test fields to validate.' );
			return;
		}

		foreach ( $non_summary as $index => $field ) {
			$this->assertInstanceOf( Field::class, $field );
			$this->assertContains(
				$field->value,
				$valid_values,
				"Field at index {$index} ('{$field->label}') value should be 'Good', 'Recommended', or 'Critical'. Got: '{$field->value}'."
			);
		}
	}

	// -------------------------------------------------------
	// Summary status reflection tests.
	// -------------------------------------------------------

	/**
	 * Test that the summary status reflects the worst individual test result.
	 *
	 * If any field is Critical, summary is Critical.
	 * Else if any field is Warning, summary is Warning.
	 * Otherwise summary is Good.
	 */
	public function test_summary_status_reflects_worst(): void {
		$fields      = $this->collector->collect();
		$summary     = reset( $fields );
		$non_summary = array_slice( $fields, 1 );

		$this->assertNotNull( $summary );
		$this->assertSame( 'Site Health Summary', $summary->label );

		$has_critical = false;
		$has_warning  = false;

		foreach ( $non_summary as $field ) {
			if ( Status::Critical === $field->status ) {
				$has_critical = true;
			} elseif ( Status::Warning === $field->status ) {
				$has_warning = true;
			}
		}

		if ( $has_critical ) {
			$this->assertSame(
				Status::Critical,
				$summary->status,
				'Summary should be Critical when at least one test is Critical.'
			);
		} elseif ( $has_warning ) {
			$this->assertSame(
				Status::Warning,
				$summary->status,
				'Summary should be Warning when at least one test is Warning (and none Critical).'
			);
		} else {
			$this->assertSame(
				Status::Good,
				$summary->status,
				'Summary should be Good when all tests pass.'
			);
		}
	}

	// -------------------------------------------------------
	// Caching tests.
	// -------------------------------------------------------

	/**
	 * Test that get_cached_data() populates the 'sr_site_health' transient.
	 */
	public function test_caching_works(): void {
		// Ensure the transient is not already set.
		delete_transient( 'sr_site_health' );

		$data = $this->collector->get_cached_data();
		$this->assertIsArray( $data );

		// The transient should now be populated.
		$cached = get_transient( 'sr_site_health' );
		$this->assertNotFalse( $cached, "'sr_site_health' transient should be set after get_cached_data()." );
	}

	/**
	 * Test that get_cached_data() returns the value stored in the transient.
	 *
	 * Prime the transient with a known sentinel value and confirm it is
	 * returned without executing collect().
	 */
	public function test_caching_returns_transient_value(): void {
		$sentinel = array( 'sentinel' => true );
		set_transient( 'sr_site_health', $sentinel, HOUR_IN_SECONDS );

		$data = $this->collector->get_cached_data();

		$this->assertSame( $sentinel, $data, 'get_cached_data() should return the cached transient value.' );
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
