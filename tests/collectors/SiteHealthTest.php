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
		delete_transient( sr_versioned_cache_key( 'sr_site_health' ) );
		$this->collector = new \SystemReport\Collectors\Site_Health();
	}

	/**
	 * Remove the cache transient and filter hooks after each test.
	 */
	public function tear_down(): void {
		delete_transient( sr_versioned_cache_key( 'sr_site_health' ) );
		remove_all_filters( 'site_status_tests' );
		remove_all_filters( 'site_status_test_result' );
		parent::tear_down();
	}

	// -------------------------------------------------------
	// Test helper methods.
	// -------------------------------------------------------

	/**
	 * Register direct tests via the site_status_tests filter.
	 *
	 * Replaces all Site Health tests with the provided direct test
	 * configurations. Each entry should be a test config array with
	 * at minimum a 'test' key (string or callable).
	 *
	 * @param array<string, array> $tests Keyed by test ID.
	 */
	private function register_direct_tests( array $tests ): void {
		add_filter(
			'site_status_tests',
			static function () use ( $tests ) {
				return array(
					'direct' => $tests,
					'async'  => array(),
				);
			}
		);
	}

	/**
	 * Build a mock callable test that returns a fixed result.
	 *
	 * @param string $status      The Site Health status string ('good', 'recommended', 'critical').
	 * @param string $label       Optional label override. Defaults to ucfirst of status.
	 * @param string $test_id     Optional test identifier.
	 * @param string $description Optional description (may contain HTML for testing stripping).
	 * @return callable The callable that returns a Site Health test result array.
	 */
	private function make_mock_test( string $status, string $label = '', string $test_id = '', string $description = '' ): callable {
		$label   = '' !== $label ? $label : ucfirst( $status ) . ' Test';
		$test_id = '' !== $test_id ? $test_id : 'mock_' . $status;

		return static function () use ( $label, $status, $test_id, $description ) {
			$result = array(
				'label'  => $label,
				'status' => $status,
				'badge'  => array(
					'label' => 'Performance',
					'color' => 'blue',
				),
				'test'   => $test_id,
			);
			if ( '' !== $description ) {
				$result['description'] = $description;
			}
			return $result;
		};
	}

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
		$fields         = $this->collector->collect();
		$non_summary    = array_slice( $fields, 1 );
		$valid_statuses = array( Status::Good, Status::Warning, Status::Critical );

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
		$fields       = $this->collector->collect();
		$non_summary  = array_slice( $fields, 1 );
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
		delete_transient( sr_versioned_cache_key( 'sr_site_health' ) );

		$data = $this->collector->get_cached_data();
		$this->assertIsArray( $data );

		// The transient should now be populated.
		$cached = get_transient( sr_versioned_cache_key( 'sr_site_health' ) );
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
		set_transient( sr_versioned_cache_key( 'sr_site_health' ), $sentinel, HOUR_IN_SECONDS );

		$data = $this->collector->get_cached_data();

		$this->assertSame( $sentinel, $data, 'get_cached_data() should return the cached transient value.' );
	}

	/**
	 * Test that stale cache from a previous plugin version is ignored.
	 *
	 * This is the primary regression test for the bug where an old
	 * transient (set by a broken collector before a fix was deployed)
	 * kept serving stale "0 issues" data even after the code was fixed.
	 *
	 * The versioned cache key ensures that a version bump automatically
	 * invalidates all collector transients.
	 */
	public function test_stale_cache_from_old_version_is_ignored(): void {
		// Simulate stale data stored under a different plugin version key.
		$stale_key  = 'sr_site_health_0_0_0';
		$stale_data = array( 'stale' => true );
		set_transient( $stale_key, $stale_data, HOUR_IN_SECONDS );

		// The collector should NOT return the stale data because the current
		// versioned key does not match the old one.
		$data = $this->collector->get_cached_data();

		$this->assertIsArray( $data );
		$this->assertNotSame( $stale_data, $data, 'Stale cache from a different version should be ignored.' );

		// Verify the fresh data is stored under the current versioned key.
		$current_cached = get_transient( sr_versioned_cache_key( 'sr_site_health' ) );
		$this->assertNotFalse( $current_cached, 'Fresh data should be stored under the current versioned key.' );

		// Clean up.
		delete_transient( $stale_key );
	}

	/**
	 * Test that the versioned cache key includes the plugin version.
	 *
	 * The transient key must differ from the base key, proving that
	 * version information is embedded in the actual storage key.
	 */
	public function test_cache_key_includes_plugin_version(): void {
		$versioned = sr_versioned_cache_key( 'sr_site_health' );

		$this->assertNotSame( 'sr_site_health', $versioned, 'Versioned key should differ from the base key.' );
		$this->assertStringStartsWith( 'sr_site_health_', $versioned, 'Versioned key should start with the base key.' );
		$this->assertStringContainsString(
			str_replace( '.', '_', WP_SYSTEM_REPORT_VERSION ),
			$versioned,
			'Versioned key should contain the plugin version.'
		);
	}

	/**
	 * Test that the cache TTL filter receives the base (non-versioned) key.
	 *
	 * External code filtering by cache key should not need to know about
	 * the internal version suffix.
	 */
	public function test_cache_ttl_filter_receives_base_key(): void {
		$received_key = null;
		add_filter(
			'wp_system_report_cache_ttl',
			function ( $ttl, $key ) use ( &$received_key ) {
				$received_key = $key;
				return $ttl;
			},
			10,
			2
		);

		delete_transient( sr_versioned_cache_key( 'sr_site_health' ) );
		$this->collector->get_cached_data();

		$this->assertSame( 'sr_site_health', $received_key, 'TTL filter should receive the base cache key, not the versioned one.' );
	}

	// -------------------------------------------------------
	// String-based test callback resolution (regression tests).
	// -------------------------------------------------------

	/**
	 * Test that string-based test callbacks are resolved and executed.
	 *
	 * This is the primary regression test for the bug where
	 * WP_Site_Health methods registered as plain strings (e.g.
	 * 'wordpress_version') were silently skipped because
	 * is_callable('wordpress_version') returns false.
	 *
	 * The collector must prepend 'get_test_' to the string and call
	 * the resulting method on the WP_Site_Health instance, matching
	 * the resolution logic in WP_Site_Health::get_page_data().
	 */
	public function test_string_test_callbacks_are_resolved(): void {
		$this->register_direct_tests(
			array(
				'wordpress_version' => array(
					'label' => 'WordPress Version',
					'test'  => 'wordpress_version',
				),
			)
		);

		$fields = $this->collector->collect();

		// Summary + at least the wordpress_version test.
		$this->assertGreaterThanOrEqual( 2, count( $fields ), 'String-based test should produce at least one field beyond the summary.' );

		$summary = $fields[0];
		$this->assertSame( 'Site Health Summary', $summary->label );

		// The summary should show a non-zero total (good + recommended + critical > 0).
		$debug = $summary->debug;
		$this->assertIsArray( $debug );
		$total = ( $debug['good'] ?? 0 ) + ( $debug['recommended'] ?? 0 ) + ( $debug['critical'] ?? 0 );
		$this->assertGreaterThan( 0, $total, 'At least one test should have been executed and counted.' );
	}

	/**
	 * Test that callable-based test callbacks still work.
	 *
	 * Third-party tests may register a callable (closure, array, etc.)
	 * instead of a string. Ensure those are still executed correctly.
	 */
	public function test_callable_test_callbacks_are_executed(): void {
		$this->register_direct_tests(
			array(
				'custom_test' => array(
					'label' => 'Custom Callable Test',
					'test'  => $this->make_mock_test( 'good', 'Custom Callable Test', 'custom_test', 'This is a custom callable test.' ),
				),
			)
		);

		$fields = $this->collector->collect();

		$this->assertGreaterThanOrEqual( 2, count( $fields ) );

		$test_field = $fields[1];
		$this->assertInstanceOf( Field::class, $test_field );
		$this->assertSame( 'Custom Callable Test', $test_field->label );
		$this->assertSame( Status::Good, $test_field->status );
		$this->assertSame( 'Good', $test_field->value );
	}

	/**
	 * Test that a mix of string and callable tests are all processed.
	 */
	public function test_mixed_string_and_callable_tests(): void {
		$this->register_direct_tests(
			array(
				'wordpress_version' => array(
					'label' => 'WordPress Version',
					'test'  => 'wordpress_version',
				),
				'custom_callable'   => array(
					'label' => 'Custom Check',
					'test'  => $this->make_mock_test( 'recommended', 'Custom Check', 'custom_callable', 'Custom recommendation.' ),
				),
			)
		);

		$fields = $this->collector->collect();

		// Summary + wordpress_version + custom_callable = 3 fields.
		$this->assertGreaterThanOrEqual( 3, count( $fields ), 'Both string-based and callable tests should produce fields.' );

		// Verify the custom callable result is included.
		$custom = $this->find_field_by_label( $fields, 'Custom Check' );
		$this->assertNotNull( $custom, 'Custom callable test field should be present.' );
		$this->assertSame( Status::Warning, $custom->status, 'Recommended status should map to Warning.' );
		$this->assertSame( 'Recommended', $custom->value );
	}

	/**
	 * Test that an unresolvable string test is silently skipped.
	 *
	 * If a string does not correspond to a get_test_*() method on
	 * WP_Site_Health, the collector should skip it without error.
	 */
	public function test_unresolvable_string_test_is_skipped(): void {
		$this->register_direct_tests(
			array(
				'nonexistent_method' => array(
					'label' => 'Nonexistent',
					'test'  => 'nonexistent_method_that_does_not_exist',
				),
			)
		);

		$fields = $this->collector->collect();

		// Only the summary field should be present.
		$this->assertCount( 1, $fields, 'Unresolvable string test should not produce a field.' );
		$this->assertSame( 'Site Health Summary', $fields[0]->label );
		$this->assertSame( '0 good, 0 recommended, 0 critical', $fields[0]->value );
	}

	// -------------------------------------------------------
	// Status counting and mapping tests.
	// -------------------------------------------------------

	/**
	 * Test correct counting with known critical test result.
	 */
	public function test_critical_test_counted_correctly(): void {
		$this->register_direct_tests(
			array(
				'mock_critical' => array(
					'label' => 'Critical Issue',
					'test'  => $this->make_mock_test( 'critical', 'Critical Issue', 'mock_critical', 'Something is critically wrong.' ),
				),
			)
		);

		$fields  = $this->collector->collect();
		$summary = $fields[0];

		$this->assertSame( Status::Critical, $summary->status, 'Summary should be Critical when a critical test exists.' );
		$this->assertStringContainsString( '1 critical', $summary->value );
		$this->assertSame( 1, $summary->debug['critical'] );
		$this->assertSame( 0, $summary->debug['good'] );
		$this->assertSame( 0, $summary->debug['recommended'] );

		// The critical field itself.
		$critical_field = $fields[1];
		$this->assertSame( Status::Critical, $critical_field->status );
		$this->assertSame( 'Critical', $critical_field->value );
	}

	/**
	 * Test correct counting with known recommended test result.
	 */
	public function test_recommended_test_counted_correctly(): void {
		$this->register_direct_tests(
			array(
				'mock_recommended' => array(
					'label' => 'Recommended Fix',
					'test'  => $this->make_mock_test( 'recommended', 'Recommended Fix', 'mock_recommended', 'This could be improved.' ),
				),
			)
		);

		$fields  = $this->collector->collect();
		$summary = $fields[0];

		$this->assertSame( Status::Warning, $summary->status, 'Summary should be Warning when a recommended test exists.' );
		$this->assertStringContainsString( '1 recommended', $summary->value );
		$this->assertSame( 1, $summary->debug['recommended'] );

		// The recommended field maps to Warning status.
		$rec_field = $fields[1];
		$this->assertSame( Status::Warning, $rec_field->status );
		$this->assertSame( 'Recommended', $rec_field->value );
	}

	/**
	 * Test correct counting with all-good test results.
	 */
	public function test_all_good_tests_produce_good_summary(): void {
		$this->register_direct_tests(
			array(
				'mock_good_1' => array(
					'label' => 'Good Test 1',
					'test'  => $this->make_mock_test( 'good', 'Good Test 1', 'mock_good_1' ),
				),
				'mock_good_2' => array(
					'label' => 'Good Test 2',
					'test'  => $this->make_mock_test( 'good', 'Good Test 2', 'mock_good_2' ),
				),
			)
		);

		$fields  = $this->collector->collect();
		$summary = $fields[0];

		$this->assertSame( Status::Good, $summary->status );
		$this->assertSame( 2, $summary->debug['good'] );
		$this->assertSame( 0, $summary->debug['recommended'] );
		$this->assertSame( 0, $summary->debug['critical'] );
	}

	/**
	 * Test that mixed statuses produce the correct worst-case summary.
	 */
	public function test_mixed_statuses_produce_correct_summary(): void {
		$this->register_direct_tests(
			array(
				'mock_good'        => array(
					'label' => 'Good Test',
					'test'  => $this->make_mock_test( 'good', 'Good Test', 'mock_good' ),
				),
				'mock_recommended' => array(
					'label' => 'Recommended Test',
					'test'  => $this->make_mock_test( 'recommended', 'Recommended Test', 'mock_recommended' ),
				),
				'mock_critical'    => array(
					'label' => 'Critical Test',
					'test'  => $this->make_mock_test( 'critical', 'Critical Test', 'mock_critical' ),
				),
			)
		);

		$fields  = $this->collector->collect();
		$summary = $fields[0];

		$this->assertSame( Status::Critical, $summary->status, 'Summary should be Critical when any test is critical.' );
		$this->assertSame( 1, $summary->debug['good'] );
		$this->assertSame( 1, $summary->debug['recommended'] );
		$this->assertSame( 1, $summary->debug['critical'] );

		// Verify total field count: summary + 3 test fields.
		$this->assertCount( 4, $fields );
	}

	// -------------------------------------------------------
	// Edge cases and error handling.
	// -------------------------------------------------------

	/**
	 * Test that a test returning null is silently skipped.
	 */
	public function test_test_returning_null_is_skipped(): void {
		$this->register_direct_tests(
			array(
				'bad_return' => array(
					'label' => 'Bad Return',
					'test'  => static function () {
						return null;
					},
				),
			)
		);

		$fields = $this->collector->collect();

		$this->assertCount( 1, $fields, 'Test returning null should not produce a field.' );
		$this->assertSame( 'Site Health Summary', $fields[0]->label );
	}

	/**
	 * Test that a test returning an array without 'status' is skipped.
	 */
	public function test_test_without_status_key_is_skipped(): void {
		$this->register_direct_tests(
			array(
				'no_status' => array(
					'label' => 'No Status',
					'test'  => static function () {
						return array(
							'label'       => 'No Status',
							'description' => 'Missing status key.',
						);
					},
				),
			)
		);

		$fields = $this->collector->collect();

		$this->assertCount( 1, $fields, 'Test without status key should not produce a field.' );
	}

	/**
	 * Test that a test throwing an exception is silently skipped.
	 */
	public function test_throwing_test_is_skipped(): void {
		$this->register_direct_tests(
			array(
				'good_test'     => array(
					'label' => 'Good Test',
					'test'  => $this->make_mock_test( 'good', 'Good Test', 'good_test' ),
				),
				'throwing_test' => array(
					'label' => 'Throwing Test',
					'test'  => static function () {
						throw new \RuntimeException( 'Test failure' );
					},
				),
			)
		);

		$fields = $this->collector->collect();

		// Summary + the good test; throwing test should be skipped.
		$this->assertCount( 2, $fields );
		$this->assertSame( 1, $fields[0]->debug['good'] );
	}

	/**
	 * Test that empty test config (no 'test' key) is skipped.
	 */
	public function test_empty_test_config_is_skipped(): void {
		$this->register_direct_tests(
			array(
				'empty_config' => array(
					'label' => 'Empty Config',
					// No 'test' key at all.
				),
			)
		);

		$fields = $this->collector->collect();

		$this->assertCount( 1, $fields, 'Empty test config should not produce a field.' );
	}

	/**
	 * Test that no direct tests section results in just the summary.
	 */
	public function test_no_direct_tests_produces_only_summary(): void {
		add_filter(
			'site_status_tests',
			static function () {
				return array(
					'async' => array(),
				);
			}
		);

		$fields = $this->collector->collect();

		$this->assertCount( 1, $fields );
		$this->assertSame( 'Site Health Summary', $fields[0]->label );
		$this->assertSame( Status::Good, $fields[0]->status );
		$this->assertSame( '0 good, 0 recommended, 0 critical', $fields[0]->value );
	}

	/**
	 * Test that the site_status_test_result filter is applied to results.
	 */
	public function test_site_status_test_result_filter_is_applied(): void {
		$this->register_direct_tests(
			array(
				'filterable_test' => array(
					'label' => 'Filterable',
					'test'  => $this->make_mock_test( 'good', 'Filterable', 'filterable_test' ),
				),
			)
		);

		// Override the test result via the core filter.
		add_filter(
			'site_status_test_result',
			static function ( array $result ): array {
				if ( isset( $result['test'] ) && 'filterable_test' === $result['test'] ) {
					$result['status'] = 'critical';
					$result['label']  = 'Overridden by Filter';
				}
				return $result;
			}
		);

		$fields = $this->collector->collect();

		$this->assertCount( 2, $fields );

		$filtered_field = $fields[1];
		$this->assertSame( 'Overridden by Filter', $filtered_field->label );
		$this->assertSame( Status::Critical, $filtered_field->status );
		$this->assertSame( 'Critical', $filtered_field->value );

		// Summary should reflect the filtered critical status.
		$this->assertSame( Status::Critical, $fields[0]->status );
		$this->assertSame( 1, $fields[0]->debug['critical'] );
	}

	/**
	 * Test that unknown status values map to Status::Info.
	 */
	public function test_unknown_status_maps_to_info(): void {
		$this->register_direct_tests(
			array(
				'unknown_status' => array(
					'label' => 'Unknown Status',
					'test'  => $this->make_mock_test( 'informational', 'Unknown Status', 'unknown_status' ),
				),
			)
		);

		$fields = $this->collector->collect();

		$this->assertCount( 2, $fields );

		$unknown_field = $fields[1];
		$this->assertSame( Status::Info, $unknown_field->status, 'Unknown status values should map to Info.' );
		$this->assertSame( 'Informational', $unknown_field->value );
	}

	/**
	 * Test that the field description is stripped of HTML tags.
	 */
	public function test_field_description_is_stripped(): void {
		$this->register_direct_tests(
			array(
				'html_desc' => array(
					'label' => 'HTML Description',
					'test'  => $this->make_mock_test( 'good', 'HTML Description', 'html_desc', '<p>This is <strong>HTML</strong> content.</p>' ),
				),
			)
		);

		$fields = $this->collector->collect();
		$field  = $fields[1];

		$this->assertStringNotContainsString( '<p>', $field->description );
		$this->assertStringNotContainsString( '<strong>', $field->description );
		$this->assertStringContainsString( 'HTML', $field->description );
	}

	/**
	 * Test that fields use the test label when available, falling back to test ID.
	 */
	public function test_field_label_fallback_to_test_id(): void {
		$this->register_direct_tests(
			array(
				'no_label_test' => array(
					'label' => 'Config Label',
					'test'  => static function () {
						return array(
							// No 'label' in the result — should use the array key.
							'status' => 'good',
							'badge'  => array(
								'label' => 'Performance',
								'color' => 'blue',
							),
							'test'   => 'no_label_test',
						);
					},
				),
			)
		);

		$fields = $this->collector->collect();
		$field  = $fields[1];

		// When the test result has no 'label' key, the collector uses the $test_id.
		$this->assertSame( 'no_label_test', $field->label );
	}
}
