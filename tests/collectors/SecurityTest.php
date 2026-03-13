<?php
/**
 * Unit tests for the Security collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Tests for \SystemReport\Collectors\Security.
 *
 * Covers collector identity, field presence, status logic, and field count.
 * Constants that are not defined in the test bootstrap (e.g. DISALLOW_FILE_EDIT,
 * DISALLOW_FILE_MODS) cannot be redefined in a single-process test run, so their
 * presence is verified structurally rather than by forcing specific constant values.
 */
class SecurityTest extends WP_UnitTestCase {

	/**
	 * The collector under test.
	 *
	 * @var \SystemReport\Collectors\Security
	 */
	private \SystemReport\Collectors\Security $collector;

	/**
	 * Set up the test fixture before each test method.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->collector = new \SystemReport\Collectors\Security();
	}

	/**
	 * Tear down any state after each test method.
	 */
	public function tear_down(): void {
		parent::tear_down();
	}

	// -------------------------------------------------------
	// Collector identity tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector reports the correct ID.
	 */
	public function test_collector_id(): void {
		$this->assertSame( 'security', $this->collector->get_id() );
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
	 * Test that the collector priority is 50.
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 50, $this->collector->get_priority() );
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
	// Field presence tests.
	// -------------------------------------------------------

	/**
	 * Test that a "Secure Connection (HTTPS)" field is present.
	 */
	public function test_https_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Secure Connection (HTTPS)' );

		$this->assertInstanceOf(
			Field::class,
			$field,
			'Security collector should include a "Secure Connection (HTTPS)" field.'
		);
	}

	/**
	 * Test that a "Hide Errors from Visitors" field is present.
	 */
	public function test_hide_errors_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Hide Errors from Visitors' );

		$this->assertInstanceOf(
			Field::class,
			$field,
			'Security collector should include a "Hide Errors from Visitors" field.'
		);
	}

	/**
	 * Test that a "File Editing Disabled" field is present.
	 */
	public function test_file_editing_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'File Editing Disabled' );

		$this->assertInstanceOf(
			Field::class,
			$field,
			'Security collector should include a "File Editing Disabled" field.'
		);
	}

	/**
	 * Test that an "Application Passwords" field is present.
	 */
	public function test_application_passwords_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Application Passwords' );

		$this->assertInstanceOf(
			Field::class,
			$field,
			'Security collector should include an "Application Passwords" field.'
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
				"Field at index $index has an unrecognised status value '{$field->status->value}'."
			);
		}
	}

	// -------------------------------------------------------
	// Field count test.
	// -------------------------------------------------------

	/**
	 * Test that collect() returns exactly 5 fields.
	 *
	 * Expected fields: Secure Connection (HTTPS), Hide Errors from Visitors,
	 * File Editing Disabled, File Modifications Disabled, Application Passwords.
	 */
	public function test_field_count(): void {
		$fields = $this->collector->collect();

		$this->assertCount(
			5,
			$fields,
			'Security collector should return exactly 5 fields.'
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
