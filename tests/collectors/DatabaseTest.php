<?php
/**
 * Tests for the Database collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Database collector tests.
 */
class DatabaseTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Database
	 */
	private \SystemReport\Collectors\Database $collector;

	/**
	 * Original wpdb prefix, restored after long-prefix tests.
	 *
	 * @var string
	 */
	private string $original_prefix;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->collector = new \SystemReport\Collectors\Database();

		global $wpdb;
		$this->original_prefix = $wpdb->prefix;

		// Clear any cached data from previous test runs.
		delete_transient( 'sr_database' );
	}

	/**
	 * Tear down after each test.
	 *
	 * Restore the original wpdb prefix and purge the transient cache.
	 */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->prefix = $this->original_prefix;

		delete_transient( 'sr_database' );
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
		$this->assertSame( 'database', $this->collector->get_id() );
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
	 * Test that the collector returns priority 30.
	 *
	 * @return void
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 30, $this->collector->get_priority() );
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
	// Field presence tests.
	// -------------------------------------------------------

	/**
	 * Test that the first field is "Database Prefix".
	 *
	 * The collector always emits Database Prefix as the first field.
	 *
	 * @return void
	 */
	public function test_database_prefix_field_present(): void {
		$fields = $this->collector->collect();

		$this->assertNotEmpty( $fields );
		$this->assertSame( 'Database Prefix', $fields[0]->label );
	}

	/**
	 * Test that a "Database Charset" field is present in the results.
	 *
	 * @return void
	 */
	public function test_charset_field_present(): void {
		$fields = $this->collector->collect();

		$labels = array_map(
			static function ( Field $field ): string {
				return $field->label;
			},
			$fields
		);

		$this->assertContains(
			'Database Charset',
			$labels,
			'Expected a "Database Charset" field in the results.'
		);
	}

	/**
	 * Test that a "Total Database Size" field is present in the results.
	 *
	 * @return void
	 */
	public function test_total_database_size_field_present(): void {
		$fields = $this->collector->collect();

		$labels = array_map(
			static function ( Field $field ): string {
				return $field->label;
			},
			$fields
		);

		$this->assertContains(
			'Total Database Size',
			$labels,
			'Expected a "Total Database Size" field in the results.'
		);
	}

	// -------------------------------------------------------
	// Status tests.
	// -------------------------------------------------------

	/**
	 * Test that "Database Prefix" carries Status::Good when prefix is normal length.
	 *
	 * The default WordPress test-suite prefix is short (e.g. "wptests_"),
	 * so the collector must assign Status::Good.
	 *
	 * @return void
	 */
	public function test_database_prefix_normal_is_good(): void {
		global $wpdb;

		// Guarantee the prefix is within the 20-character threshold.
		$wpdb->prefix = 'wptests_';

		delete_transient( 'sr_database' );
		$fields = $this->collector->collect();

		$prefix_field = $fields[0];
		$this->assertSame( 'Database Prefix', $prefix_field->label );
		$this->assertSame(
			Status::Good,
			$prefix_field->status,
			'Expected Status::Good for a prefix of 8 characters.'
		);
	}

	/**
	 * Test that "Database Prefix" carries Status::Warning when prefix exceeds 20 chars.
	 *
	 * The collector checks strlen( $wpdb->prefix ) > 20 and upgrades the
	 * status to Status::Warning.  We temporarily override $wpdb->prefix to a
	 * 21-character string to exercise that branch, then restore the original
	 * value in tear_down().
	 *
	 * @return void
	 */
	public function test_database_prefix_long_is_warning(): void {
		global $wpdb;

		// Use a prefix that is exactly 21 characters — one over the threshold.
		$wpdb->prefix = 'this_prefix_is_toolong';

		delete_transient( 'sr_database' );
		$fields = $this->collector->collect();

		$prefix_field = $fields[0];
		$this->assertSame( 'Database Prefix', $prefix_field->label );
		$this->assertSame(
			Status::Warning,
			$prefix_field->status,
			'Expected Status::Warning for a prefix longer than 20 characters.'
		);
	}

	// -------------------------------------------------------
	// Core table presence tests.
	// -------------------------------------------------------

	/**
	 * Test that at least some core WordPress table names appear in the results.
	 *
	 * The collector queries information_schema and builds one field per table.
	 * We verify that well-known core table names (with the current prefix)
	 * are represented as field labels.
	 *
	 * @return void
	 */
	public function test_core_tables_present(): void {
		global $wpdb;

		$fields = $this->collector->collect();

		$labels = array_map(
			static function ( Field $field ): string {
				return $field->label;
			},
			$fields
		);

		// At least the posts table must be present in any WP install.
		$this->assertContains(
			$wpdb->posts,
			$labels,
			sprintf(
				'Expected the "%s" table to appear in the collected fields.',
				$wpdb->posts
			)
		);
	}

	// -------------------------------------------------------
	// Field value format tests.
	// -------------------------------------------------------

	/**
	 * Test that per-table field values contain "Engine:", "Rows:", and "Size:".
	 *
	 * The collector formats each table value using the pattern
	 * "Engine: X | Rows: Y | Size: Z". We verify this against the posts table
	 * field, which is always present.
	 *
	 * @return void
	 */
	public function test_table_info_format(): void {
		global $wpdb;

		$fields = $this->collector->collect();

		// Find the field for the posts table.
		$posts_field = null;
		foreach ( $fields as $field ) {
			if ( $wpdb->posts === $field->label ) {
				$posts_field = $field;
				break;
			}
		}

		$this->assertNotNull(
			$posts_field,
			sprintf( 'Expected a field for the "%s" table.', $wpdb->posts )
		);

		$this->assertStringContainsString(
			'Engine:',
			$posts_field->value,
			'Table field value must contain "Engine:".'
		);
		$this->assertStringContainsString(
			'Rows:',
			$posts_field->value,
			'Table field value must contain "Rows:".'
		);
		$this->assertStringContainsString(
			'Size:',
			$posts_field->value,
			'Table field value must contain "Size:".'
		);
	}

	// -------------------------------------------------------
	// Caching tests.
	// -------------------------------------------------------

	/**
	 * Test that get_cached_data() stores results in the expected transient.
	 *
	 * After the first call the "sr_database" transient must exist, and a
	 * second call must return the same data.
	 *
	 * @return void
	 */
	public function test_caching_works(): void {
		// Ensure no stale cache exists before the test.
		delete_transient( 'sr_database' );
		$this->assertFalse(
			get_transient( 'sr_database' ),
			'Transient "sr_database" should not exist before the first get_cached_data() call.'
		);

		// First call: should populate the transient.
		$first_result = $this->collector->get_cached_data();

		$cached = get_transient( 'sr_database' );
		$this->assertNotFalse(
			$cached,
			'Transient "sr_database" should be set after get_cached_data().'
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
		delete_transient( 'sr_database' );
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
			new Field( 'Sentinel', 'db-sentinel' ),
		);

		set_transient( 'sr_database', $sentinel, HOUR_IN_SECONDS );

		$result = $this->collector->get_cached_data();

		$this->assertCount( 1, $result );
		$this->assertSame( 'Sentinel', $result[0]->label );
		$this->assertSame( 'db-sentinel', $result[0]->value );
	}
}
