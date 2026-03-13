<?php
/**
 * Tests for the Theme_Info collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Theme Info collector tests.
 */
class ThemeInfoTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Theme_Info
	 */
	private \SystemReport\Collectors\Theme_Info $collector;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->collector = new \SystemReport\Collectors\Theme_Info();

		// Clear any cached data from previous test runs.
		delete_transient( sr_versioned_cache_key( 'sr_theme_info' ) );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		delete_transient( sr_versioned_cache_key( 'sr_theme_info' ) );
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
		$this->assertSame( 'theme_info', $this->collector->get_id() );
	}

	/**
	 * Test that the collector returns a non-empty label.
	 *
	 * @return void
	 */
	public function test_collector_label(): void {
		$this->assertNotEmpty( $this->collector->get_label() );
	}

	/**
	 * Test that the collector returns priority 90.
	 *
	 * @return void
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 90, $this->collector->get_priority() );
	}

	// -------------------------------------------------------
	// Return type and structure tests.
	// -------------------------------------------------------

	/**
	 * Test that collect() returns an array of Field objects.
	 *
	 * @return void
	 */
	public function test_collect_returns_array_of_field_objects(): void {
		$fields = $this->collector->collect();

		$this->assertIsArray( $fields );
		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			$this->assertInstanceOf( Field::class, $field );
		}
	}

	// -------------------------------------------------------
	// Individual field presence tests.
	// -------------------------------------------------------

	/**
	 * Test that the first field is "Theme Name" with a non-empty value.
	 *
	 * The collector always appends Theme Name as the first field, so the
	 * index-0 assertion is safe for any standard WordPress test environment.
	 *
	 * @return void
	 */
	public function test_theme_name_field_present(): void {
		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields );

		$first_field = $fields[0];

		$this->assertInstanceOf( Field::class, $first_field );
		$this->assertSame(
			'Theme Name',
			$first_field->label,
			'First field label must be "Theme Name".'
		);
		$this->assertNotEmpty(
			$first_field->value,
			'"Theme Name" field value must not be empty.'
		);
	}

	/**
	 * Test that a "Theme Version" field is present in the collected data.
	 *
	 * @return void
	 */
	public function test_theme_version_field_present(): void {
		$fields = $this->collector->collect();

		$labels = array_map(
			static function ( Field $field ): string {
				return $field->label;
			},
			$fields
		);

		$this->assertContains(
			'Theme Version',
			$labels,
			'Expected a "Theme Version" field in collected data.'
		);
	}

	/**
	 * Test that an "Is Child Theme" field is present with a "Yes" or "No" value.
	 *
	 * @return void
	 */
	public function test_is_child_theme_field_present(): void {
		$fields = $this->collector->collect();

		$target_field = null;
		foreach ( $fields as $field ) {
			if ( 'Is Child Theme' === $field->label ) {
				$target_field = $field;
				break;
			}
		}

		$this->assertNotNull(
			$target_field,
			'Expected an "Is Child Theme" field in collected data.'
		);

		$this->assertContains(
			$target_field->value,
			array( 'Yes', 'No' ),
			'"Is Child Theme" value must be "Yes" or "No".'
		);
	}

	/**
	 * Test that an "Is Block Theme" field is present with a "Yes" or "No" value.
	 *
	 * @return void
	 */
	public function test_is_block_theme_field_present(): void {
		$fields = $this->collector->collect();

		$target_field = null;
		foreach ( $fields as $field ) {
			if ( 'Is Block Theme' === $field->label ) {
				$target_field = $field;
				break;
			}
		}

		$this->assertNotNull(
			$target_field,
			'Expected an "Is Block Theme" field in collected data.'
		);

		$this->assertContains(
			$target_field->value,
			array( 'Yes', 'No' ),
			'"Is Block Theme" value must be "Yes" or "No".'
		);
	}

	// -------------------------------------------------------
	// Status tests.
	// -------------------------------------------------------

	/**
	 * Test that every collected field carries a valid Status enum instance.
	 *
	 * The collector assigns Status::Info to most fields and Status::Warning to
	 * the version field only when an update is available.  Regardless of which
	 * status is set, it must always be a Status enum instance (not a string or
	 * null).
	 *
	 * @return void
	 */
	public function test_all_fields_have_valid_status(): void {
		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields );

		$valid_statuses = array(
			Status::Good,
			Status::Warning,
			Status::Critical,
			Status::Info,
		);

		foreach ( $fields as $field ) {
			$this->assertContains(
				$field->status,
				$valid_statuses,
				"Field '{$field->label}' must carry a valid Status enum instance."
			);
		}
	}

	/**
	 * Test that non-version fields all carry Status::Info.
	 *
	 * In the standard test environment no theme update is pending, so only
	 * the version field could potentially differ, but all others are always
	 * Status::Info by the collector's explicit assignment.
	 *
	 * @return void
	 */
	public function test_non_version_fields_have_info_status(): void {
		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			if ( 'Theme Version' === $field->label ) {
				// Version field may be Warning if an update is available; skip it.
				continue;
			}

			$this->assertSame(
				Status::Info,
				$field->status,
				"Expected Status::Info on field '{$field->label}', got '{$field->status->value}'."
			);
		}
	}

	// -------------------------------------------------------
	// Theme Name and Author content tests.
	// -------------------------------------------------------

	/**
	 * Test that the "Theme Author" field is present in the collected data.
	 *
	 * The collector always emits this field, and the test environment's active
	 * theme must have a non-empty Author header.
	 *
	 * @return void
	 */
	public function test_theme_author_field_present(): void {
		$fields = $this->collector->collect();

		$target_field = null;
		foreach ( $fields as $field ) {
			if ( 'Theme Author' === $field->label ) {
				$target_field = $field;
				break;
			}
		}

		$this->assertNotNull(
			$target_field,
			'Expected a "Theme Author" field in collected data.'
		);
		$this->assertSame(
			Status::Info,
			$target_field->status,
			'"Theme Author" field must carry Status::Info.'
		);
	}

	// -------------------------------------------------------
	// Caching tests.
	// -------------------------------------------------------

	/**
	 * Test that get_cached_data() stores results in the expected transient.
	 *
	 * @return void
	 */
	public function test_caching_works(): void {
		// First call: should populate the transient.
		$first_result = $this->collector->get_cached_data();

		$cached = get_transient( sr_versioned_cache_key( 'sr_theme_info' ) );
		$this->assertNotFalse(
			$cached,
			'Transient "sr_theme_info" should be set after get_cached_data().'
		);

		// Second call: should return the same data from cache.
		$second_result = $this->collector->get_cached_data();

		$this->assertEquals(
			$first_result,
			$second_result,
			'Second call to get_cached_data() should return the same data as the first.'
		);
	}

	/**
	 * Test that get_cached_data() returns the transient value when pre-primed.
	 *
	 * We prime the transient with a known sentinel value and verify that
	 * get_cached_data() returns it without re-running collect().
	 *
	 * @return void
	 */
	public function test_caching_returns_transient_value(): void {
		$sentinel = array(
			new Field( 'Sentinel', '42' ),
		);

		set_transient( sr_versioned_cache_key( 'sr_theme_info' ), $sentinel, HOUR_IN_SECONDS );

		$result = $this->collector->get_cached_data();

		$this->assertCount( 1, $result );
		$this->assertSame( 'Sentinel', $result[0]->label );
		$this->assertSame( '42', $result[0]->value );
	}

	// -------------------------------------------------------
	// Theme Name value matching active theme.
	// -------------------------------------------------------

	/**
	 * Test that the "Theme Name" field value matches the active theme's name.
	 *
	 * @return void
	 */
	public function test_theme_name_value_matches_active_theme(): void {
		$active_theme = wp_get_theme();
		$fields       = $this->collector->collect();

		$name_field = null;
		foreach ( $fields as $field ) {
			if ( 'Theme Name' === $field->label ) {
				$name_field = $field;
				break;
			}
		}

		$this->assertNotNull( $name_field, 'Expected a "Theme Name" field.' );
		$this->assertSame(
			$active_theme->get( 'Name' ),
			$name_field->value,
			'"Theme Name" value must match wp_get_theme()->get("Name").'
		);
	}
}
