<?php
/**
 * Tests for the Dropins_MU_Plugins collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Tests for the Drop-ins & Must-Use Plugins collector.
 *
 * In a standard test environment neither drop-ins nor MU plugins are
 * present, so the "empty" message path is the primary exercised path.
 * Structural contracts (label format, status) are verified for that case
 * and for the code branches that handle actual items.
 */
class DropinsMuPluginsTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Dropins_MU_Plugins
	 */
	private $collector;

	/**
	 * Set up the collector instance before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->collector = new \SystemReport\Collectors\Dropins_MU_Plugins();
		// Clear any cached data from a previous run.
		delete_transient( sr_versioned_cache_key( 'sr_dropins_mu_plugins' ) );
	}

	/**
	 * Remove the transient after each test to avoid cross-test pollution.
	 */
	public function tear_down() {
		delete_transient( sr_versioned_cache_key( 'sr_dropins_mu_plugins' ) );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Identity contracts
	// -------------------------------------------------------------------------

	/**
	 * The collector must identify itself with the canonical ID string.
	 */
	public function test_collector_id() {
		$this->assertSame( 'dropins_mu_plugins', $this->collector->get_id() );
	}

	/**
	 * The collector must return a non-empty human-readable label.
	 */
	public function test_collector_label() {
		$label = $this->collector->get_label();
		$this->assertIsString( $label );
		$this->assertNotEmpty( $label );
	}

	/**
	 * The collector priority must be 80.
	 */
	public function test_collector_priority() {
		$this->assertSame( 80, $this->collector->get_priority() );
	}

	// -------------------------------------------------------------------------
	// collect() return type
	// -------------------------------------------------------------------------

	/**
	 * collect() must always return an array.
	 */
	public function test_collect_returns_array() {
		$fields = $this->collector->collect();
		$this->assertIsArray( $fields );
	}

	// -------------------------------------------------------------------------
	// Empty environment (default test-suite state)
	// -------------------------------------------------------------------------

	/**
	 * When no drop-ins or MU plugins are present the collector must return
	 * a single informational placeholder field.
	 *
	 * The test environment has no drop-ins and no MU plugins by design, so
	 * this exercises the empty-collection branch of the source.
	 */
	public function test_no_dropins_or_mu_plugins_shows_message() {
		// Confirm there are no drop-ins or MU plugins in the test environment.
		if ( ! function_exists( 'get_dropins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$dropins    = get_dropins();
		$mu_plugins = get_mu_plugins();

		// Only assert the message if the environment is genuinely empty.
		if ( ! empty( $dropins ) || ! empty( $mu_plugins ) ) {
			$this->markTestSkipped( 'Test environment has drop-ins or MU plugins; skipping empty-state assertion.' );
		}

		$fields = $this->collector->collect();

		$this->assertCount( 1, $fields, 'Exactly one placeholder field expected when nothing is installed.' );

		/** @var Field $field */
		$field = $fields[0];
		$this->assertInstanceOf( Field::class, $field );
		$this->assertSame( 'No Drop-ins or MU Plugins', $field->label );
		$this->assertSame( 'No drop-ins or must-use plugins installed.', $field->value );
	}

	// -------------------------------------------------------------------------
	// Status contract
	// -------------------------------------------------------------------------

	/**
	 * Every field returned by the collector must carry Status::Info.
	 *
	 * This holds for both the placeholder field (empty case) and any real
	 * drop-in / MU-plugin fields.
	 */
	public function test_all_fields_have_info_status() {
		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields, 'Collector must return at least one field.' );

		foreach ( $fields as $index => $field ) {
			$this->assertInstanceOf(
				Field::class,
				$field,
				"Field at index $index must be a Field instance."
			);
			$this->assertSame(
				Status::Info,
				$field->status,
				"Field at index $index must carry Status::Info."
			);
		}
	}

	// -------------------------------------------------------------------------
	// Label-format contracts (verified against the source's sprintf patterns)
	// -------------------------------------------------------------------------

	/**
	 * When drop-ins are present every label for a drop-in must begin with
	 * "Drop-in:".
	 *
	 * If the test environment has no drop-ins the assertion is skipped rather
	 * than artificially creating filesystem state.
	 */
	public function test_dropin_label_format() {
		if ( ! function_exists( 'get_dropins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$dropins = get_dropins();

		if ( empty( $dropins ) ) {
			$this->markTestSkipped( 'No drop-ins present in test environment; label-format assertion skipped.' );
		}

		$fields = $this->collector->collect();

		foreach ( $fields as $field ) {
			// Only inspect fields that represent a drop-in filename key.
			if ( isset( $dropins[ $field->export_label ] ) ) {
				$this->assertStringStartsWith(
					'Drop-in:',
					$field->label,
					"Drop-in field label must start with 'Drop-in:'."
				);
			}
		}
	}

	/**
	 * When MU plugins are present every label for an MU plugin must begin
	 * with "MU Plugin:".
	 *
	 * If the test environment has no MU plugins the assertion is skipped.
	 */
	public function test_mu_plugin_label_format() {
		if ( ! function_exists( 'get_mu_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$mu_plugins = get_mu_plugins();

		if ( empty( $mu_plugins ) ) {
			$this->markTestSkipped( 'No MU plugins present in test environment; label-format assertion skipped.' );
		}

		$fields = $this->collector->collect();

		// MU-plugin fields use the plugin name as export_label (not the file
		// path), so check for the label prefix directly.
		$mu_labels = array_filter(
			array_column( $fields, 'label' ),
			static function ( string $label ): bool {
				return str_starts_with( $label, 'MU Plugin:' );
			}
		);

		$this->assertNotEmpty(
			$mu_labels,
			'At least one field must have a label starting with "MU Plugin:" when MU plugins are present.'
		);
	}

	// -------------------------------------------------------------------------
	// Caching contract
	// -------------------------------------------------------------------------

	/**
	 * get_cached_data() must store results in a transient keyed
	 * 'sr_dropins_mu_plugins' and return identical data on a second call.
	 */
	public function test_caching() {
		$cache_key = sr_versioned_cache_key( 'sr_dropins_mu_plugins' );

		// Ensure no stale cache exists before the test.
		delete_transient( $cache_key );
		$this->assertFalse(
			get_transient( $cache_key ),
			'Transient should not exist before the first get_cached_data() call.'
		);

		// First call populates the cache.
		$data_first = $this->collector->get_cached_data();
		$this->assertIsArray( $data_first );

		// The transient must now be set.
		$cached = get_transient( $cache_key );
		$this->assertNotFalse( $cached, "Transient '$cache_key' must be set after get_cached_data()." );
		$this->assertIsArray( $cached );

		// A second call must return the same data (served from cache).
		$data_second = $this->collector->get_cached_data();
		$this->assertEquals(
			$data_first,
			$data_second,
			'Second get_cached_data() call must return the same data as the first.'
		);

		// Clean up.
		delete_transient( $cache_key );
	}
}
