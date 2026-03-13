<?php
/**
 * Tests for the Inactive_Plugins collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Inactive Plugins collector tests.
 */
class InactivePluginsTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Inactive_Plugins
	 */
	private \SystemReport\Collectors\Inactive_Plugins $collector;

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

		$this->collector = new \SystemReport\Collectors\Inactive_Plugins();

		// Snapshot the current active_plugins so we can restore it.
		$this->original_active_plugins = (array) get_option( 'active_plugins', array() );

		// Clear any cached data from previous test runs.
		delete_transient( 'sr_inactive_plugins' );
	}

	/**
	 * Tear down after each test.
	 *
	 * Restore the active_plugins option to avoid cross-test contamination and
	 * purge the transient cache.
	 */
	public function tear_down(): void {
		update_option( 'active_plugins', $this->original_active_plugins );
		delete_transient( 'sr_inactive_plugins' );
		parent::tear_down();
	}

	// -------------------------------------------------------
	// Metadata tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector returns the expected ID.
	 */
	public function test_collector_id(): void {
		$this->assertSame( 'inactive_plugins', $this->collector->get_id() );
	}

	/**
	 * Test that the collector returns a non-empty label.
	 */
	public function test_collector_label(): void {
		$this->assertNotEmpty( $this->collector->get_label() );
	}

	/**
	 * Test that the collector returns priority 70.
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 70, $this->collector->get_priority() );
	}

	/**
	 * Test that get_cache_key() returns the expected transient key.
	 */
	public function test_cache_key(): void {
		// get_cached_data() calls the protected get_cache_key() internally;
		// we verify it uses the right key by priming the transient and
		// asserting get_cached_data() reads from it.
		$sentinel = array( new Field( 'Cache Key Sentinel', '1' ) );
		set_transient( 'sr_inactive_plugins', $sentinel, HOUR_IN_SECONDS );

		$result = $this->collector->get_cached_data();

		$this->assertCount( 1, $result );
		$this->assertSame( 'Cache Key Sentinel', $result[0]->label );
	}

	// -------------------------------------------------------
	// Return type tests.
	// -------------------------------------------------------

	/**
	 * Test that collect() always returns an array.
	 */
	public function test_collect_returns_array(): void {
		$result = $this->collector->collect();
		$this->assertIsArray( $result );
	}

	/**
	 * Test that every item returned by collect() is a Field instance.
	 */
	public function test_collect_returns_field_instances(): void {
		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			$this->assertInstanceOf( Field::class, $field );
		}
	}

	// -------------------------------------------------------
	// Status tests.
	// -------------------------------------------------------

	/**
	 * Test that all returned fields carry Status::Info.
	 *
	 * The collector explicitly passes Status::Info for every field, including
	 * the "No Inactive Plugins" placeholder.
	 */
	public function test_all_fields_have_info_status(): void {
		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			$this->assertSame(
				Status::Info,
				$field->status,
				"Expected Status::Info on field '{$field->label}', got '{$field->status->value}'."
			);
		}
	}

	// -------------------------------------------------------
	// "No inactive plugins" path tests.
	// -------------------------------------------------------

	/**
	 * Test that when all installed plugins are marked active, the collector
	 * returns exactly one field with the "No Inactive Plugins" message.
	 *
	 * Strategy: set active_plugins to contain every key returned by
	 * get_plugins() so that no plugin is left inactive.
	 */
	public function test_no_inactive_shows_message(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = array_keys( get_plugins() );

		// Mark every installed plugin as active.
		update_option( 'active_plugins', $all_plugins );

		delete_transient( 'sr_inactive_plugins' );
		$fields = $this->collector->collect();

		$this->assertCount(
			1,
			$fields,
			'Expected exactly one placeholder field when all plugins are active.'
		);

		$field = $fields[0];
		$this->assertInstanceOf( Field::class, $field );

		// The label should be the "No Inactive Plugins" string.
		$this->assertStringContainsString(
			'No Inactive Plugins',
			$field->label,
			"Expected label to contain 'No Inactive Plugins'."
		);

		// The value should confirm all plugins are active.
		$this->assertStringContainsString(
			'All installed plugins are active',
			$field->value,
			"Expected value to contain 'All installed plugins are active'."
		);

		$this->assertSame( Status::Info, $field->status );
	}

	// -------------------------------------------------------
	// Inactive plugin field format tests.
	// -------------------------------------------------------

	/**
	 * Test that an inactive plugin produces a field with the expected
	 * "by Author - version X.Y" value format.
	 *
	 * Strategy: set active_plugins to an empty array so every installed plugin
	 * is considered inactive, then inspect the fields produced.
	 *
	 * This exercises the real get_plugins() filesystem call and the value
	 * formatting logic in the collector.
	 */
	public function test_field_value_contains_version_and_author(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		if ( empty( $all_plugins ) ) {
			$this->markTestSkipped( 'No plugins installed; cannot test field value format.' );
		}

		// Make all plugins inactive.
		update_option( 'active_plugins', array() );

		delete_transient( 'sr_inactive_plugins' );
		$fields = $this->collector->collect();

		// With at least one plugin inactive we expect more than one field OR
		// the single-plugin case; either way every real plugin field must
		// contain "version" in the value.
		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			// Skip the placeholder field if somehow no plugins are inactive
			// (should not happen here, but guard for robustness).
			if ( 'No Inactive Plugins' === $field->label ) {
				continue;
			}

			$this->assertStringContainsString(
				'version',
				$field->value,
				"Field value for '{$field->label}' should contain 'version'. Got: '{$field->value}'."
			);

			// Also assert the "by " prefix is present.
			$this->assertStringContainsString(
				'by ',
				$field->value,
				"Field value for '{$field->label}' should start with 'by '. Got: '{$field->value}'."
			);
		}
	}

	/**
	 * Test the exact value format against a specific plugin's metadata.
	 *
	 * We pick the first entry from get_plugins(), make it inactive, and
	 * reconstruct the expected value string to compare against.
	 */
	public function test_field_value_format_matches_plugin_metadata(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		if ( empty( $all_plugins ) ) {
			$this->markTestSkipped( 'No plugins installed; cannot test field value format.' );
		}

		// Use the first plugin in the list as our test subject.
		reset( $all_plugins );
		$plugin_path = key( $all_plugins );
		$plugin_data = current( $all_plugins );

		// Make only this plugin inactive by marking everything else as active.
		$other_plugins = array_keys( array_diff_key( $all_plugins, array( $plugin_path => true ) ) );
		update_option( 'active_plugins', $other_plugins );

		delete_transient( 'sr_inactive_plugins' );
		$fields = $this->collector->collect();

		// Build the expected value string the same way the collector does.
		$version  = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : 'Unknown';
		$author   = ! empty( $plugin_data['Author'] )
			? wp_strip_all_tags( $plugin_data['Author'] )
			: 'Unknown';
		$expected_value = sprintf( 'by %1$s - version %2$s', $author, $version );

		$plugin_name = ! empty( $plugin_data['Name'] )
			? $plugin_data['Name']
			: basename( $plugin_path, '.php' );

		// Find the matching field.
		$target_field = null;
		foreach ( $fields as $field ) {
			if ( $field->label === $plugin_name ) {
				$target_field = $field;
				break;
			}
		}

		$this->assertNotNull(
			$target_field,
			"Expected a field with label '{$plugin_name}' for plugin '{$plugin_path}'."
		);
		$this->assertSame( $expected_value, $target_field->value );
		$this->assertSame( Status::Info, $target_field->status );
	}

	/**
	 * Test that the export_label matches the plugin name.
	 *
	 * The collector passes 'export_label' => $plugin_name in $options so the
	 * export label mirrors the field label exactly.
	 */
	public function test_field_export_label_matches_plugin_name(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		if ( empty( $all_plugins ) ) {
			$this->markTestSkipped( 'No plugins installed.' );
		}

		// Make all plugins inactive.
		update_option( 'active_plugins', array() );

		delete_transient( 'sr_inactive_plugins' );
		$fields = $this->collector->collect();

		foreach ( $fields as $field ) {
			if ( 'No Inactive Plugins' === $field->label ) {
				continue;
			}

			$this->assertSame(
				$field->label,
				$field->export_label,
				"export_label should equal label for '{$field->label}'."
			);
		}
	}

	// -------------------------------------------------------
	// Alphabetical sort tests.
	// -------------------------------------------------------

	/**
	 * Test that inactive plugin fields are sorted alphabetically by plugin name.
	 *
	 * We make all plugins inactive (giving us the full list) and verify that
	 * the resulting fields are in ascending alphabetical order by label.
	 */
	public function test_inactive_plugins_sorted_alphabetically(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		if ( count( $all_plugins ) < 2 ) {
			$this->markTestSkipped( 'Fewer than 2 plugins installed; cannot test sort order.' );
		}

		update_option( 'active_plugins', array() );

		delete_transient( 'sr_inactive_plugins' );
		$fields = $this->collector->collect();

		// Filter out the placeholder.
		$plugin_fields = array_values(
			array_filter(
				$fields,
				static function ( Field $f ): bool {
					return 'No Inactive Plugins' !== $f->label;
				}
			)
		);

		if ( count( $plugin_fields ) < 2 ) {
			$this->markTestSkipped( 'Fewer than 2 inactive plugin fields returned; cannot test sort order.' );
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
			'Inactive plugin fields should be sorted alphabetically by label.'
		);
	}

	// -------------------------------------------------------
	// Caching tests.
	// -------------------------------------------------------

	/**
	 * Test that get_cached_data() stores results in the "sr_inactive_plugins" transient.
	 */
	public function test_get_cached_data_sets_transient(): void {
		delete_transient( 'sr_inactive_plugins' );

		$this->collector->get_cached_data();

		$cached = get_transient( 'sr_inactive_plugins' );
		$this->assertNotFalse(
			$cached,
			'Transient "sr_inactive_plugins" should be set after get_cached_data().'
		);
	}

	/**
	 * Test that a second call to get_cached_data() returns the same data as the first.
	 */
	public function test_get_cached_data_consistent_results(): void {
		delete_transient( 'sr_inactive_plugins' );

		$first  = $this->collector->get_cached_data();
		$second = $this->collector->get_cached_data();

		$this->assertEquals(
			$first,
			$second,
			'Successive calls to get_cached_data() should return equal results.'
		);
	}
}
