<?php
/**
 * Unit tests for the WordPress Constants collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Tests for \SystemReport\Collectors\WordPress_Constants.
 *
 * Covers collector identity, field presence, privacy flags, undefined-constant
 * display, field count, the wp_system_report_constants filter, status correctness,
 * and boolean-constant display formatting.
 *
 * Constants already defined by the WordPress test bootstrap (ABSPATH, WP_DEBUG,
 * WP_CONTENT_DIR, etc.) are tested as-is. Constants that are not defined in the
 * test environment are verified to produce a "Not defined" display value.
 */
class WordPressConstantsTest extends WP_UnitTestCase {

	/**
	 * The collector under test.
	 *
	 * @var \SystemReport\Collectors\WordPress_Constants
	 */
	private \SystemReport\Collectors\WordPress_Constants $collector;

	/**
	 * Named callback tag used when attaching the extra-constant filter.
	 *
	 * Stored so it can be reliably removed in tear_down().
	 *
	 * @var string
	 */
	private string $constants_filter_tag = 'wp_system_report_constants';

	/**
	 * Set up the test fixture before each test method.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->collector = new \SystemReport\Collectors\WordPress_Constants();
	}

	/**
	 * Remove any filters registered by individual tests.
	 */
	public function tear_down(): void {
		remove_filter(
			$this->constants_filter_tag,
			array( $this, 'add_extra_constant_to_list' )
		);
		parent::tear_down();
	}

	// -------------------------------------------------------
	// Collector identity tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector reports the correct ID.
	 */
	public function test_collector_id(): void {
		$this->assertSame( 'wordpress_constants', $this->collector->get_id() );
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
	 * Test that the collector priority is 100.
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 100, $this->collector->get_priority() );
	}

	// -------------------------------------------------------
	// collect() return type tests.
	// -------------------------------------------------------

	/**
	 * Test that collect() returns an array of Field objects.
	 */
	public function test_collect_returns_field_objects(): void {
		$fields = $this->collector->collect();

		$this->assertIsArray( $fields );

		if ( empty( $fields ) ) {
			$this->assertIsArray( $fields );
			return;
		}

		foreach ( $fields as $index => $field ) {
			$this->assertInstanceOf(
				Field::class,
				$field,
				"Field at index $index should be a Field instance."
			);
		}
	}

	// -------------------------------------------------------
	// ABSPATH field tests.
	// -------------------------------------------------------

	/**
	 * Test that the ABSPATH field is present.
	 *
	 * ABSPATH is always defined in the WordPress test bootstrap.
	 */
	public function test_abspath_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'ABSPATH' );

		$this->assertInstanceOf(
			Field::class,
			$field,
			'WordPress Constants collector should include an ABSPATH field.'
		);
	}

	/**
	 * Test that the ABSPATH field is marked as private.
	 *
	 * The ABSPATH value contains the filesystem path to WordPress, which
	 * should not appear in shared exports.
	 */
	public function test_abspath_field_is_private(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'ABSPATH' );

		$this->assertInstanceOf(
			Field::class,
			$field,
			'ABSPATH field must exist before checking its private flag.'
		);
		$this->assertTrue(
			$field->private,
			'ABSPATH field should be marked as private.'
		);
	}

	// -------------------------------------------------------
	// Undefined constant display test.
	// -------------------------------------------------------

	/**
	 * Test that undefined constants display "Not defined".
	 *
	 * Several constants in the tracked list (e.g. WP_HOME, WP_SITEURL,
	 * DISABLE_WP_CRON, FORCE_SSL_ADMIN) are typically not defined in the
	 * WordPress test bootstrap.  The collector should display "Not defined"
	 * for any constant that is absent at runtime.
	 */
	public function test_undefined_constant_shows_not_defined(): void {
		$fields = $this->collector->collect();

		// Build a map of label => field for easy lookup.
		$undefined_candidates = array(
			'WP_HOME',
			'WP_SITEURL',
			'DISABLE_WP_CRON',
			'FORCE_SSL_ADMIN',
			'AUTOSAVE_INTERVAL',
			'WP_POST_REVISIONS',
		);

		$found_undefined = false;

		foreach ( $undefined_candidates as $constant_name ) {
			if ( defined( $constant_name ) ) {
				// Skip constants that happen to be defined in this environment.
				continue;
			}

			$field = $this->find_field_by_label( $fields, $constant_name );

			$this->assertInstanceOf(
				Field::class,
				$field,
				"A field for the undefined constant '$constant_name' should still be present."
			);
			$this->assertSame(
				'Not defined',
				$field->value,
				"Undefined constant '$constant_name' should display \"Not defined\"."
			);

			$found_undefined = true;
			break; // One confirmed case is sufficient.
		}

		if ( ! $found_undefined ) {
			// All candidates happened to be defined — assert the field array is valid.
			$this->assertIsArray( $fields );
		}
	}

	// -------------------------------------------------------
	// Field count test.
	// -------------------------------------------------------

	/**
	 * Test that collect() returns exactly 23 fields by default.
	 *
	 * The default constant list hard-codes 23 entries. No filter is applied
	 * during this test.
	 */
	public function test_field_count_matches_constants(): void {
		$fields = $this->collector->collect();

		$this->assertCount(
			23,
			$fields,
			'WordPress Constants collector should return exactly 23 fields by default.'
		);
	}

	// -------------------------------------------------------
	// wp_system_report_constants filter test.
	// -------------------------------------------------------

	/**
	 * Named filter callback that appends a single extra constant entry.
	 *
	 * Defined as a public method so it can be added and removed by name,
	 * satisfying the project rule against anonymous filter callbacks that
	 * cannot be individually removed.
	 *
	 * @param array $constants Existing constants array.
	 * @return array Modified constants array with one extra entry.
	 */
	public function add_extra_constant_to_list( array $constants ): array {
		$constants['PHP_INT_MAX'] = array(
			'label' => 'PHP_INT_MAX',
		);
		return $constants;
	}

	/**
	 * Test that the wp_system_report_constants filter increases the field count.
	 *
	 * Adding one constant via the filter should yield 24 fields.
	 */
	public function test_constants_filter(): void {
		add_filter(
			$this->constants_filter_tag,
			array( $this, 'add_extra_constant_to_list' )
		);

		$fields = $this->collector->collect();

		remove_filter(
			$this->constants_filter_tag,
			array( $this, 'add_extra_constant_to_list' )
		);

		$this->assertCount(
			24,
			$fields,
			'Adding one constant via the filter should yield 24 fields total.'
		);
	}

	// -------------------------------------------------------
	// Status correctness tests.
	// -------------------------------------------------------

	/**
	 * Test that every field carries a valid Status enum instance.
	 */
	public function test_all_fields_have_valid_status(): void {
		$fields = $this->collector->collect();

		if ( empty( $fields ) ) {
			$this->assertIsArray( $fields );
			return;
		}

		$valid_cases = array_column( Status::cases(), 'value' );

		foreach ( $fields as $index => $field ) {
			$this->assertInstanceOf(
				Status::class,
				$field->status,
				"Field at index $index should have a Status enum value."
			);
			$this->assertContains(
				$field->status->value,
				$valid_cases,
				"Field at index $index has an unrecognised status '{$field->status->value}'."
			);
		}
	}

	// -------------------------------------------------------
	// WP_DEBUG field test.
	// -------------------------------------------------------

	/**
	 * Test that a WP_DEBUG field is present.
	 *
	 * WP_DEBUG is defined in the WordPress test bootstrap, so this field
	 * should always appear with a non-empty value.
	 */
	public function test_wp_debug_field_present(): void {
		$this->assertTrue(
			defined( 'WP_DEBUG' ),
			'WP_DEBUG must be defined in the test bootstrap for this test to be meaningful.'
		);

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'WP_DEBUG' );

		$this->assertInstanceOf(
			Field::class,
			$field,
			'WordPress Constants collector should include a WP_DEBUG field.'
		);
		$this->assertNotEmpty(
			$field->value,
			'WP_DEBUG field should have a non-empty display value.'
		);
	}

	// -------------------------------------------------------
	// Boolean constant format test.
	// -------------------------------------------------------

	/**
	 * Test that defined boolean constants display "Yes" or "No".
	 *
	 * The collector formats boolean constants via format_boolean(), which must
	 * return either the translated "Yes" or "No" string.  This test inspects
	 * every boolean-typed constant that is actually defined at runtime.
	 */
	public function test_boolean_constants_format(): void {
		$boolean_constants = array(
			'WP_DEBUG',
			'WP_DEBUG_DISPLAY',
			'WP_DEBUG_LOG',
			'SCRIPT_DEBUG',
			'WP_CACHE',
			'CONCATENATE_SCRIPTS',
			'COMPRESS_SCRIPTS',
			'COMPRESS_CSS',
			'DISALLOW_FILE_EDIT',
			'DISALLOW_FILE_MODS',
			'DISABLE_WP_CRON',
			'FORCE_SSL_ADMIN',
		);

		$fields        = $this->collector->collect();
		$checked_count = 0;

		foreach ( $boolean_constants as $constant_name ) {
			if ( ! defined( $constant_name ) ) {
				continue;
			}

			$field = $this->find_field_by_label( $fields, $constant_name );

			if ( null === $field ) {
				continue;
			}

			$this->assertContains(
				$field->value,
				array( 'Yes', 'No' ),
				"Defined boolean constant '$constant_name' should display \"Yes\" or \"No\", got \"{$field->value}\"."
			);

			++$checked_count;
		}

		// Guarantee that at least one boolean constant was verified (WP_DEBUG is
		// always defined in the test bootstrap).
		$this->assertGreaterThanOrEqual(
			1,
			$checked_count,
			'At least one boolean constant should be defined and checked.'
		);
	}

	// -------------------------------------------------------
	// Helper methods.
	// -------------------------------------------------------

	/**
	 * Find a field in an array by its label.
	 *
	 * @param Field[] $fields Collection of Field objects.
	 * @param string  $label  The label to search for.
	 * @return Field|null The first matching Field, or null if not found.
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
