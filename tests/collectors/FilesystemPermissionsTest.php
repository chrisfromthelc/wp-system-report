<?php
/**
 * Tests for the Filesystem_Permissions collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Filesystem Permissions collector tests.
 *
 * The WordPress test environment runs against a real filesystem, so
 * wp_is_writable() returns genuine results.  The uploads directory is
 * always writable in the standard test scaffold, which makes the
 * Status::Good branch of the uploads field reliably exercisable without
 * mocking.
 */
class FilesystemPermissionsTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Filesystem_Permissions
	 */
	private \SystemReport\Collectors\Filesystem_Permissions $collector;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->collector = new \SystemReport\Collectors\Filesystem_Permissions();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
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
		$this->assertSame( 'filesystem_permissions', $this->collector->get_id() );
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
	 * Test that the collector returns priority 110.
	 *
	 * @return void
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 110, $this->collector->get_priority() );
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
	// Directory presence tests.
	// -------------------------------------------------------

	/**
	 * Test that at least 5 expected directory fields are present.
	 *
	 * The five mandatory fields are: WordPress Root, wp-content Directory,
	 * Uploads Directory, Plugins Directory, and Themes Directory.  A sixth
	 * MU Plugins Directory field is emitted only when WPMU_PLUGIN_DIR is
	 * defined, so it is not counted here.
	 *
	 * @return void
	 */
	public function test_expected_directories_present(): void {
		$fields = $this->collector->collect();

		$this->assertGreaterThanOrEqual(
			5,
			count( $fields ),
			'Expected at least 5 directory fields (root, content, uploads, plugins, themes).'
		);

		$labels = array_map(
			static function ( Field $field ): string {
				return $field->label;
			},
			$fields
		);

		$expected_labels = array(
			'WordPress Root',
			'wp-content Directory',
			'Uploads Directory',
			'Plugins Directory',
			'Themes Directory',
		);

		foreach ( $expected_labels as $expected ) {
			$this->assertContains(
				$expected,
				$labels,
				"Expected field with label \"{$expected}\" to be present."
			);
		}
	}

	// -------------------------------------------------------
	// Status logic tests.
	// -------------------------------------------------------

	/**
	 * Test that the "Uploads Directory" field has Status::Good when writable.
	 *
	 * The standard WordPress test environment always creates a writable uploads
	 * directory, so this assertion exercises the Good branch reliably.
	 *
	 * @return void
	 */
	public function test_uploads_directory_writable_is_good(): void {
		$upload_dir   = wp_upload_dir();
		$uploads_path = $upload_dir['basedir'];

		if ( ! wp_is_writable( $uploads_path ) ) {
			$this->markTestSkipped( 'Uploads directory is not writable in this environment; skipping Good-status assertion.' );
		}

		$fields = $this->collector->collect();

		$uploads_field = null;
		foreach ( $fields as $field ) {
			if ( 'Uploads Directory' === $field->label ) {
				$uploads_field = $field;
				break;
			}
		}

		$this->assertNotNull( $uploads_field, 'Expected an "Uploads Directory" field.' );
		$this->assertSame(
			Status::Good,
			$uploads_field->status,
			'Uploads Directory must have Status::Good when the directory is writable.'
		);
	}

	/**
	 * Test that the "WordPress Root" field always carries Status::Info.
	 *
	 * The WordPress root directory status is informational regardless of
	 * whether it is writable or not.
	 *
	 * @return void
	 */
	public function test_wordpress_root_is_info(): void {
		$fields = $this->collector->collect();

		$root_field = null;
		foreach ( $fields as $field ) {
			if ( 'WordPress Root' === $field->label ) {
				$root_field = $field;
				break;
			}
		}

		$this->assertNotNull( $root_field, 'Expected a "WordPress Root" field.' );
		$this->assertSame(
			Status::Info,
			$root_field->status,
			'"WordPress Root" field must always carry Status::Info.'
		);
	}

	/**
	 * Test that the "Plugins Directory" field always carries Status::Info.
	 *
	 * @return void
	 */
	public function test_plugins_directory_is_info(): void {
		$fields = $this->collector->collect();

		$plugins_field = null;
		foreach ( $fields as $field ) {
			if ( 'Plugins Directory' === $field->label ) {
				$plugins_field = $field;
				break;
			}
		}

		$this->assertNotNull( $plugins_field, 'Expected a "Plugins Directory" field.' );
		$this->assertSame(
			Status::Info,
			$plugins_field->status,
			'"Plugins Directory" field must always carry Status::Info.'
		);
	}

	/**
	 * Test that the "Themes Directory" field always carries Status::Info.
	 *
	 * @return void
	 */
	public function test_themes_directory_is_info(): void {
		$fields = $this->collector->collect();

		$themes_field = null;
		foreach ( $fields as $field ) {
			if ( 'Themes Directory' === $field->label ) {
				$themes_field = $field;
				break;
			}
		}

		$this->assertNotNull( $themes_field, 'Expected a "Themes Directory" field.' );
		$this->assertSame(
			Status::Info,
			$themes_field->status,
			'"Themes Directory" field must always carry Status::Info.'
		);
	}

	// -------------------------------------------------------
	// Field value format tests.
	// -------------------------------------------------------

	/**
	 * Test that every field value is "Writable", "Not Writable", or "World-writable".
	 *
	 * The collector maps wp_is_writable() to "Writable" or "Not Writable" for
	 * directory fields, and emits "World-writable" for the conditional
	 * WordPress Root Permissions field when the root is world-writable.
	 * In an en_US test environment the strings are untranslated and match exactly.
	 *
	 * @return void
	 */
	public function test_field_values_are_writable_or_not_writable(): void {
		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			$this->assertContains(
				$field->value,
				array( 'Writable', 'Not Writable', 'World-writable' ),
				"Field '{$field->label}' value must be 'Writable', 'Not Writable', or 'World-writable'; got '{$field->value}'."
			);
		}
	}

	// -------------------------------------------------------
	// Debug property tests.
	// -------------------------------------------------------

	/**
	 * Test that each directory field's debug property contains a filesystem path.
	 *
	 * The collector sets 'debug' => $path for every directory field, so the
	 * debug value must be a non-empty string that starts with a directory
	 * separator (absolute path).
	 *
	 * The "WordPress Root Permissions" field (emitted only when the root is
	 * world-writable) stores an octal permission string in its debug property,
	 * not a filesystem path, and is therefore excluded from this assertion.
	 *
	 * @return void
	 */
	public function test_debug_contains_path(): void {
		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			// The world-writable permissions field stores an octal string in debug,
			// not a filesystem path, so skip it for this assertion.
			if ( 'WordPress Root Permissions' === $field->label ) {
				continue;
			}

			$this->assertIsString(
				$field->debug,
				"Field '{$field->label}' debug property must be a string path."
			);
			$this->assertNotEmpty(
				$field->debug,
				"Field '{$field->label}' debug property must not be empty."
			);
			// An absolute path must begin with '/' on Unix or a drive letter on Windows.
			// The DIRECTORY_SEPARATOR check keeps the test portable.
			$this->assertStringStartsWith(
				DIRECTORY_SEPARATOR,
				$field->debug,
				"Field '{$field->label}' debug property must be an absolute filesystem path."
			);
		}
	}

	// -------------------------------------------------------
	// No-caching contract.
	// -------------------------------------------------------

	/**
	 * Test that the collector has no cache key and writes no transient.
	 *
	 * Filesystem_Permissions does not override get_cache_key(), so the
	 * base-class default of null is inherited.  get_cached_data() must
	 * return valid data without storing any transient.
	 *
	 * @return void
	 */
	public function test_no_caching(): void {
		$data = $this->collector->get_cached_data();

		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		// Confirm via reflection that the class truly exposes no cache key.
		$reflection = new ReflectionMethod( $this->collector, 'get_cache_key' );
		$reflection->setAccessible( true );
		$cache_key = $reflection->invoke( $this->collector );

		$this->assertNull(
			$cache_key,
			'Filesystem_Permissions::get_cache_key() must return null (no caching).'
		);
	}
}
