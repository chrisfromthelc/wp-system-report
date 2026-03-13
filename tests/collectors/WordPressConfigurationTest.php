<?php
/**
 * Tests for the WordPress_Configuration collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * WordPress Configuration collector tests.
 *
 * These tests exercise the twelve fields emitted by the collector and
 * verify the only status-logic branch: permalink structure empty produces
 * Status::Warning, while a non-empty value produces Status::Good.
 */
class WordPressConfigurationTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\WordPress_Configuration
	 */
	private \SystemReport\Collectors\WordPress_Configuration $collector;

	/**
	 * The original permalink_structure option value, restored in tear_down.
	 *
	 * @var string
	 */
	private string $original_permalink_structure;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->collector = new \SystemReport\Collectors\WordPress_Configuration();

		// Snapshot the current permalink structure so we can restore it.
		$this->original_permalink_structure = (string) get_option( 'permalink_structure', '' );
	}

	/**
	 * Tear down after each test.
	 *
	 * Restore permalink_structure to avoid cross-test contamination.
	 */
	public function tear_down(): void {
		update_option( 'permalink_structure', $this->original_permalink_structure );
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
		$this->assertSame( 'wordpress_configuration', $this->collector->get_id() );
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
	 * Test that the collector returns priority 160.
	 *
	 * @return void
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 160, $this->collector->get_priority() );
	}

	// -------------------------------------------------------
	// Return type and field count tests.
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

	/**
	 * Test that the collector emits exactly 12 fields.
	 *
	 * The collector defines a fixed set of 12 configuration fields. Any
	 * deviation from this count indicates an unintended change to the
	 * collector's output contract.
	 *
	 * @return void
	 */
	public function test_field_count(): void {
		$fields = $this->collector->collect();

		$this->assertCount(
			12,
			$fields,
			'WordPress Configuration collector must return exactly 12 fields.'
		);
	}

	// -------------------------------------------------------
	// Permalink Structure status tests.
	// -------------------------------------------------------

	/**
	 * Test that a non-empty permalink_structure produces Status::Good.
	 *
	 * @return void
	 */
	public function test_permalink_structure_good(): void {
		update_option( 'permalink_structure', '/%postname%/' );

		$fields = $this->collector->collect();

		$permalink_field = null;
		foreach ( $fields as $field ) {
			if ( 'Permalink Structure' === $field->label ) {
				$permalink_field = $field;
				break;
			}
		}

		$this->assertNotNull( $permalink_field, 'Expected a "Permalink Structure" field.' );
		$this->assertSame(
			Status::Good,
			$permalink_field->status,
			'"Permalink Structure" must be Status::Good when a structure is set.'
		);
		$this->assertSame(
			'/%postname%/',
			$permalink_field->value,
			'"Permalink Structure" value must equal the set option value.'
		);
	}

	/**
	 * Test that an empty permalink_structure produces Status::Warning.
	 *
	 * An empty string means plain (default) permalinks are in use, which
	 * is considered sub-optimal and flagged with Status::Warning.  The
	 * displayed value should contain "Plain" to communicate the mode.
	 *
	 * @return void
	 */
	public function test_permalink_structure_empty_is_warning(): void {
		update_option( 'permalink_structure', '' );

		$fields = $this->collector->collect();

		$permalink_field = null;
		foreach ( $fields as $field ) {
			if ( 'Permalink Structure' === $field->label ) {
				$permalink_field = $field;
				break;
			}
		}

		$this->assertNotNull( $permalink_field, 'Expected a "Permalink Structure" field.' );
		$this->assertSame(
			Status::Warning,
			$permalink_field->status,
			'"Permalink Structure" must be Status::Warning when the structure is empty.'
		);
		$this->assertStringContainsString(
			'Plain',
			$permalink_field->value,
			'"Permalink Structure" value must contain "Plain" when the structure is empty.'
		);
	}

	// -------------------------------------------------------
	// Individual field presence tests.
	// -------------------------------------------------------

	/**
	 * Test that a "User Registration Enabled" field is present.
	 *
	 * @return void
	 */
	public function test_user_registration_field(): void {
		$fields = $this->collector->collect();

		$target_field = null;
		foreach ( $fields as $field ) {
			if ( 'User Registration Enabled' === $field->label ) {
				$target_field = $field;
				break;
			}
		}

		$this->assertNotNull(
			$target_field,
			'Expected a "User Registration Enabled" field.'
		);
		$this->assertContains(
			$target_field->value,
			array( 'Yes', 'No' ),
			'"User Registration Enabled" value must be "Yes" or "No".'
		);
		$this->assertSame(
			Status::Info,
			$target_field->status,
			'"User Registration Enabled" must carry Status::Info.'
		);
	}

	/**
	 * Test that the "Default Role" field contains a valid WordPress role name.
	 *
	 * WordPress ships with a default_role of 'subscriber', and the test
	 * environment does not change this.  We verify that the field value is
	 * a non-empty string matching a role registered with WP.
	 *
	 * @return void
	 */
	public function test_default_role_field(): void {
		$fields = $this->collector->collect();

		$role_field = null;
		foreach ( $fields as $field ) {
			if ( 'Default Role' === $field->label ) {
				$role_field = $field;
				break;
			}
		}

		$this->assertNotNull( $role_field, 'Expected a "Default Role" field.' );
		$this->assertNotEmpty(
			$role_field->value,
			'"Default Role" value must not be empty.'
		);

		// The value must correspond to a role that WordPress knows about.
		$registered_roles = array_keys( wp_roles()->roles );
		$this->assertContains(
			$role_field->value,
			$registered_roles,
			"\"Default Role\" value '{$role_field->value}' must be a registered WordPress role."
		);
	}

	/**
	 * Test that the "User Roles" field value contains "Administrator".
	 *
	 * WordPress always registers the Administrator role, so the value
	 * produced by the collector must contain it regardless of the user count.
	 *
	 * @return void
	 */
	public function test_user_roles_field_contains_administrator(): void {
		$fields = $this->collector->collect();

		$roles_field = null;
		foreach ( $fields as $field ) {
			if ( 'User Roles' === $field->label ) {
				$roles_field = $field;
				break;
			}
		}

		$this->assertNotNull( $roles_field, 'Expected a "User Roles" field.' );
		$this->assertStringContainsString(
			'Administrator',
			$roles_field->value,
			'"User Roles" value must contain "Administrator".'
		);
	}

	/**
	 * Test that a "Timezone" field is present with a non-empty value.
	 *
	 * @return void
	 */
	public function test_timezone_field_present(): void {
		$fields = $this->collector->collect();

		$tz_field = null;
		foreach ( $fields as $field ) {
			if ( 'Timezone' === $field->label ) {
				$tz_field = $field;
				break;
			}
		}

		$this->assertNotNull( $tz_field, 'Expected a "Timezone" field.' );
		$this->assertNotEmpty(
			$tz_field->value,
			'"Timezone" field value must not be empty.'
		);
		$this->assertSame(
			Status::Info,
			$tz_field->status,
			'"Timezone" field must carry Status::Info.'
		);
	}

	/**
	 * Test that a "Max Upload Size" field is present with a non-empty value.
	 *
	 * The value is produced by size_format( wp_max_upload_size() ), which
	 * always returns a non-empty formatted string (e.g., "2 MB") as long as
	 * the upload size limit is greater than zero.
	 *
	 * @return void
	 */
	public function test_max_upload_size_field(): void {
		$fields = $this->collector->collect();

		$upload_field = null;
		foreach ( $fields as $field ) {
			if ( 'Max Upload Size' === $field->label ) {
				$upload_field = $field;
				break;
			}
		}

		$this->assertNotNull( $upload_field, 'Expected a "Max Upload Size" field.' );
		$this->assertNotEmpty(
			$upload_field->value,
			'"Max Upload Size" field value must not be empty.'
		);
		$this->assertSame(
			Status::Info,
			$upload_field->status,
			'"Max Upload Size" field must carry Status::Info.'
		);
	}

	// -------------------------------------------------------
	// All-Info status test.
	// -------------------------------------------------------

	/**
	 * Test that non-permalink fields all carry Status::Info.
	 *
	 * Only "Permalink Structure" varies between Status::Good and
	 * Status::Warning.  Every other field must always be Status::Info.
	 *
	 * @return void
	 */
	public function test_non_permalink_fields_have_info_status(): void {
		// Set a valid permalink structure so the permalink field itself is Good
		// and we can skip it cleanly without it polluting the assertion.
		update_option( 'permalink_structure', '/%postname%/' );

		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			if ( 'Permalink Structure' === $field->label ) {
				// Permalink field has its own status logic; exclude from bulk check.
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
	// No-caching contract.
	// -------------------------------------------------------

	/**
	 * Test that the collector has no cache key and writes no transient.
	 *
	 * WordPress_Configuration does not override get_cache_key(), so the
	 * base-class default of null is inherited.  get_cached_data() must
	 * return valid data without storing any transient.
	 *
	 * @return void
	 */
	public function test_no_caching(): void {
		$data = $this->collector->get_cached_data();

		$this->assertIsArray( $data );
		$this->assertCount(
			12,
			$data,
			'get_cached_data() must return all 12 configuration fields.'
		);

		// Confirm via reflection that the class truly exposes no cache key.
		$reflection = new ReflectionMethod( $this->collector, 'get_cache_key' );
		$reflection->setAccessible( true );
		$cache_key = $reflection->invoke( $this->collector );

		$this->assertNull(
			$cache_key,
			'WordPress_Configuration::get_cache_key() must return null (no caching).'
		);
	}
}
