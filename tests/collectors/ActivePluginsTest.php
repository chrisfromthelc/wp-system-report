<?php
/**
 * Tests for the Active_Plugins collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Active Plugins collector tests.
 */
class ActivePluginsTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Active_Plugins
	 */
	private \SystemReport\Collectors\Active_Plugins $collector;

	/**
	 * The original value of the active_plugins option, restored in tear_down.
	 *
	 * @var array<string>
	 */
	private array $original_active_plugins;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->collector = new \SystemReport\Collectors\Active_Plugins();

		// Snapshot the current active_plugins so we can restore it.
		$this->original_active_plugins = (array) get_option( 'active_plugins', array() );

		// Clear any cached data from previous test runs.
		delete_transient( sr_versioned_cache_key( 'sr_active_plugins' ) );
	}

	/**
	 * Tear down after each test.
	 *
	 * Restore the active_plugins option to avoid cross-test contamination,
	 * delete the update_plugins site transient, and purge the collector cache.
	 */
	public function tear_down(): void {
		update_option( 'active_plugins', $this->original_active_plugins );
		delete_site_transient( 'update_plugins' );
		delete_transient( sr_versioned_cache_key( 'sr_active_plugins' ) );
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
		$this->assertSame( 'active_plugins', $this->collector->get_id() );
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
	 * Test that the collector returns priority 60.
	 *
	 * @return void
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 60, $this->collector->get_priority() );
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
	// Active plugin listing tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector lists active plugins when at least one is active.
	 *
	 * The WP test suite loads the plugin under test via muplugins_loaded,
	 * which does NOT add it to the active_plugins option. We must explicitly
	 * activate a known plugin for this test.
	 *
	 * @return void
	 */
	public function test_active_plugins_listed(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		if ( empty( $all_plugins ) ) {
			$this->markTestSkipped( 'No plugins installed; cannot test active plugin listing.' );
		}

		// Activate the first installed plugin explicitly.
		reset( $all_plugins );
		$plugin_path = key( $all_plugins );
		update_option( 'active_plugins', array( $plugin_path ) );

		delete_transient( sr_versioned_cache_key( 'sr_active_plugins' ) );
		$fields = $this->collector->collect();

		// There must be at least one field.
		$this->assertNotEmpty(
			$fields,
			'Expected at least one field when a plugin is explicitly activated.'
		);

		// None of the fields should be the "No Active Plugins" placeholder.
		$labels = array_map(
			static function ( Field $field ): string {
				return $field->label;
			},
			$fields
		);

		$this->assertNotContains(
			'No Active Plugins',
			$labels,
			'Did not expect "No Active Plugins" placeholder when a plugin is active.'
		);
	}

	/**
	 * Test that plugin field values contain the expected "by ... - version ..." pattern.
	 *
	 * We make all installed plugins inactive so we get real plugin metadata
	 * and verify the value format against the first field.
	 *
	 * @return void
	 */
	public function test_plugin_value_contains_author_and_version(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		if ( empty( $all_plugins ) ) {
			$this->markTestSkipped( 'No plugins installed; cannot test value format.' );
		}

		// Make the first plugin the only active one so we get a known field.
		reset( $all_plugins );
		$plugin_path = key( $all_plugins );
		update_option( 'active_plugins', array( $plugin_path ) );

		delete_transient( sr_versioned_cache_key( 'sr_active_plugins' ) );
		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields );

		// The single field must not be the placeholder.
		$field = $fields[0];
		$this->assertNotSame( 'No Active Plugins', $field->label );

		// Value must match "by X - version Y".
		$this->assertStringContainsString(
			'by ',
			$field->value,
			"Field value must contain 'by '. Got: '{$field->value}'."
		);
		$this->assertStringContainsString(
			'version ',
			$field->value,
			"Field value must contain 'version '. Got: '{$field->value}'."
		);
	}

	// -------------------------------------------------------
	// Empty active plugins path tests.
	// -------------------------------------------------------

	/**
	 * Test that collect() returns a "No Active Plugins" field when no plugins are active.
	 *
	 * We set the active_plugins option to an empty array so the collector
	 * takes the empty-collection branch and emits the placeholder field.
	 *
	 * @return void
	 */
	public function test_no_active_plugins_message(): void {
		// Deactivate all plugins.
		update_option( 'active_plugins', array() );

		delete_transient( sr_versioned_cache_key( 'sr_active_plugins' ) );
		$fields = $this->collector->collect();

		$this->assertCount(
			1,
			$fields,
			'Expected exactly one placeholder field when no plugins are active.'
		);

		$field = $fields[0];
		$this->assertInstanceOf( Field::class, $field );

		$this->assertSame(
			'No Active Plugins',
			$field->label,
			"Expected label 'No Active Plugins' for the placeholder field."
		);
		$this->assertSame(
			Status::Info,
			$field->status,
			'Expected Status::Info on the "No Active Plugins" placeholder.'
		);
	}

	// -------------------------------------------------------
	// Alphabetical sort tests.
	// -------------------------------------------------------

	/**
	 * Test that active plugin fields are sorted alphabetically by plugin name.
	 *
	 * We activate all installed plugins (giving the maximum set), filter out
	 * any placeholder, and verify the resulting labels are in strcmp order.
	 *
	 * @return void
	 */
	public function test_plugins_sorted_alphabetically(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		if ( count( $all_plugins ) < 2 ) {
			$this->markTestSkipped( 'Fewer than 2 plugins installed; cannot test sort order.' );
		}

		// Activate all installed plugins.
		update_option( 'active_plugins', array_keys( $all_plugins ) );

		delete_transient( sr_versioned_cache_key( 'sr_active_plugins' ) );
		$fields = $this->collector->collect();

		// Filter out any placeholder field.
		$plugin_fields = array_values(
			array_filter(
				$fields,
				static function ( Field $f ): bool {
					return 'No Active Plugins' !== $f->label;
				}
			)
		);

		if ( count( $plugin_fields ) < 2 ) {
			$this->markTestSkipped( 'Fewer than 2 active plugin fields returned; cannot test sort order.' );
		}

		$labels = array_map(
			static function ( Field $f ): string {
				return $f->label;
			},
			$plugin_fields
		);

		$sorted = $labels;
		usort( $sorted, 'strcmp' );

		$this->assertSame(
			$sorted,
			$labels,
			'Active plugin fields must be sorted alphabetically by label.'
		);
	}

	// -------------------------------------------------------
	// Update available status tests.
	// -------------------------------------------------------

	/**
	 * Test that a plugin with an available update receives Status::Warning.
	 *
	 * Strategy:
	 * 1. Activate the first installed plugin.
	 * 2. Prime the "update_plugins" site transient with a mock response that
	 *    marks the same plugin as having a new version available.
	 * 3. Run collect() and assert that the field carries Status::Warning and
	 *    that the value contains "(update available: ...)".
	 *
	 * get_plugin_updates() reads the "update_plugins" site transient and
	 * returns an array keyed by plugin file with ->update->new_version set.
	 *
	 * @return void
	 */
	public function test_plugin_update_available_warning_status(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$all_plugins = get_plugins();

		if ( empty( $all_plugins ) ) {
			$this->markTestSkipped( 'No plugins installed; cannot test update-available status.' );
		}

		// Use the first plugin as our test subject.
		reset( $all_plugins );
		$plugin_path = key( $all_plugins );

		// Activate only this plugin.
		update_option( 'active_plugins', array( $plugin_path ) );

		// Prime the update_plugins site transient so get_plugin_updates()
		// returns our fake update for the plugin under test.
		$updates = (object) array(
			'response' => array(
				$plugin_path => (object) array(
					'new_version' => '2.0.0',
				),
			),
		);
		set_site_transient( 'update_plugins', $updates );

		delete_transient( sr_versioned_cache_key( 'sr_active_plugins' ) );
		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields );

		$updated_field = $fields[0];
		$this->assertSame(
			Status::Warning,
			$updated_field->status,
			'Expected Status::Warning for a plugin with an available update.'
		);
		$this->assertStringContainsString(
			'update available:',
			$updated_field->value,
			"Field value must contain 'update available:' when an update is present."
		);
		$this->assertStringContainsString(
			'2.0.0',
			$updated_field->value,
			"Field value must contain the new version number '2.0.0'."
		);
	}

	// -------------------------------------------------------
	// Caching tests.
	// -------------------------------------------------------

	/**
	 * Test that get_cached_data() stores results in the "sr_active_plugins" transient.
	 *
	 * After the first call the transient must exist, and a second call must
	 * return the same data.
	 *
	 * @return void
	 */
	public function test_caching_works(): void {
		// Ensure no stale cache exists before the test.
		delete_transient( sr_versioned_cache_key( 'sr_active_plugins' ) );
		$this->assertFalse(
			get_transient( sr_versioned_cache_key( 'sr_active_plugins' ) ),
			'Transient "sr_active_plugins" should not exist before the first get_cached_data() call.'
		);

		// First call: should populate the transient.
		$first_result = $this->collector->get_cached_data();

		$cached = get_transient( sr_versioned_cache_key( 'sr_active_plugins' ) );
		$this->assertNotFalse(
			$cached,
			'Transient "sr_active_plugins" should be set after get_cached_data().'
		);
		$this->assertIsArray( $cached );

		// Second call: should return the same data from cache.
		$second_result = $this->collector->get_cached_data();

		$this->assertEquals(
			$first_result,
			$second_result,
			'Second call to get_cached_data() should return the same data as the first.'
		);

		// Clean up.
		delete_transient( sr_versioned_cache_key( 'sr_active_plugins' ) );
	}

	/**
	 * Test that get_cached_data() returns a pre-seeded transient value.
	 *
	 * We prime the transient with a known sentinel and verify that
	 * get_cached_data() returns it without re-running collect().
	 *
	 * @return void
	 */
	public function test_caching_returns_transient_value(): void {
		$sentinel = array(
			new Field( 'Sentinel Plugin', '999' ),
		);

		set_transient( sr_versioned_cache_key( 'sr_active_plugins' ), $sentinel, HOUR_IN_SECONDS );

		$result = $this->collector->get_cached_data();

		$this->assertCount( 1, $result );
		$this->assertSame( 'Sentinel Plugin', $result[0]->label );
		$this->assertSame( '999', $result[0]->value );
	}
}
