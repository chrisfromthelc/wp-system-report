<?php
/**
 * Tests for the Post_Type_Counts collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Post Type Counts collector tests.
 */
class PostTypeCountsTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Post_Type_Counts
	 */
	private \SystemReport\Collectors\Post_Type_Counts $collector;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->collector = new \SystemReport\Collectors\Post_Type_Counts();

		// Clear any cached data from previous test runs.
		delete_transient( 'sr_post_type_counts' );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		delete_transient( 'sr_post_type_counts' );
		parent::tear_down();
	}

	// -------------------------------------------------------
	// Metadata tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector returns the expected ID.
	 */
	public function test_collector_id(): void {
		$this->assertSame( 'post_type_counts', $this->collector->get_id() );
	}

	/**
	 * Test that the collector returns a non-empty label.
	 */
	public function test_collector_label(): void {
		$this->assertNotEmpty( $this->collector->get_label() );
	}

	/**
	 * Test that the collector returns priority 40.
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 40, $this->collector->get_priority() );
	}

	// -------------------------------------------------------
	// Return type and structure tests.
	// -------------------------------------------------------

	/**
	 * Test that collect() returns an array of Field objects.
	 */
	public function test_collect_returns_array_of_field_objects(): void {
		// Create a post so the table is not empty.
		$this->factory->post->create();

		$fields = $this->collector->collect();

		$this->assertIsArray( $fields );
		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			$this->assertInstanceOf( Field::class, $field );
		}
	}

	// -------------------------------------------------------
	// Post type presence tests.
	// -------------------------------------------------------

	/**
	 * Test that the default 'post' post type appears after creating posts.
	 *
	 * WP uses human-readable labels, so we look for the 'Posts' label
	 * (the plural label for the built-in post type).
	 */
	public function test_default_post_types_present(): void {
		// Create three posts so the 'post' type has a count.
		$this->factory->post->create_many( 3 );

		$fields = $this->collector->collect();

		// The collector uses get_post_type_object()->labels->name, which is 'Posts'
		// for the built-in post type.
		$labels = array_map(
			static function ( Field $field ): string {
				return $field->label;
			},
			$fields
		);

		// At least one field should correspond to the 'post' post type.
		// We check for 'Posts' (the plural label) OR 'post' (fallback ucfirst).
		$has_post_type = in_array( 'Posts', $labels, true )
			|| in_array( 'Post', $labels, true );

		$this->assertTrue(
			$has_post_type,
			'Expected a field for the built-in "post" post type; labels found: ' . implode( ', ', $labels )
		);
	}

	/**
	 * Test that a custom post type appears in results after posts are created for it.
	 */
	public function test_custom_post_type_included(): void {
		// Register a temporary custom post type for this test.
		register_post_type(
			'sr_test_cpt',
			array(
				'label'  => 'SR Test CPTs',
				'public' => true,
				'labels' => array(
					'name' => 'SR Test CPTs',
				),
			)
		);

		// Create two posts of the custom type.
		$this->factory->post->create_many(
			2,
			array( 'post_type' => 'sr_test_cpt' )
		);

		// Clear cache so the fresh query runs.
		delete_transient( 'sr_post_type_counts' );

		$fields = $this->collector->collect();

		$labels = array_map(
			static function ( Field $field ): string {
				return $field->label;
			},
			$fields
		);

		$this->assertContains(
			'SR Test CPTs',
			$labels,
			'Expected the custom post type "sr_test_cpt" to appear in results.'
		);

		// Clean up.
		unregister_post_type( 'sr_test_cpt' );
	}

	// -------------------------------------------------------
	// Field value formatting tests.
	// -------------------------------------------------------

	/**
	 * Test that field values are formatted numbers (via number_format_i18n).
	 *
	 * Creates enough posts so we get a predictable count, then verifies the
	 * value matches what number_format_i18n() would produce for that count.
	 */
	public function test_field_values_are_formatted_numbers(): void {
		// Create exactly 5 posts.
		$this->factory->post->create_many( 5 );

		$fields = $this->collector->collect();

		// Every field value must be a non-empty string that looks like a number.
		foreach ( $fields as $field ) {
			$this->assertIsString( $field->value );
			$this->assertNotEmpty( $field->value );

			// number_format_i18n() output is locale-dependent but always
			// consists of digits plus optional grouping separators.
			// Cast back to int to confirm it is numeric.
			$this->assertGreaterThan(
				0,
				(int) str_replace( array( ',', '.', ' ', "\xc2\xa0" ), '', $field->value ),
				"Field value '{$field->value}' does not look like a formatted integer."
			);
		}
	}

	/**
	 * Test that a specific count matches the number_format_i18n output exactly.
	 *
	 * We create posts under a dedicated post type to get an exact, isolated
	 * count and compare it to what number_format_i18n() returns.
	 */
	public function test_field_value_matches_number_format_i18n(): void {
		register_post_type(
			'sr_test_fmt',
			array(
				'label'  => 'SR Format',
				'public' => true,
				'labels' => array( 'name' => 'SR Format' ),
			)
		);

		$count = 7;
		$this->factory->post->create_many( $count, array( 'post_type' => 'sr_test_fmt' ) );

		delete_transient( 'sr_post_type_counts' );
		$fields = $this->collector->collect();

		$target_field = null;
		foreach ( $fields as $field ) {
			if ( 'SR Format' === $field->label ) {
				$target_field = $field;
				break;
			}
		}

		$this->assertNotNull( $target_field, 'Expected a field for "SR Format" post type.' );
		$this->assertSame( number_format_i18n( $count ), $target_field->value );

		unregister_post_type( 'sr_test_fmt' );
	}

	// -------------------------------------------------------
	// Status tests.
	// -------------------------------------------------------

	/**
	 * Test that all fields have Status::Info.
	 *
	 * The collector does not set explicit statuses, so make_field() defaults
	 * every field to Status::Info.
	 */
	public function test_all_fields_have_info_status(): void {
		$this->factory->post->create();

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
	// Caching tests.
	// -------------------------------------------------------

	/**
	 * Test that get_cached_data() stores results in the expected transient.
	 */
	public function test_caching_works(): void {
		$this->factory->post->create();

		// First call: should populate the transient.
		$first_result = $this->collector->get_cached_data();

		$cached = get_transient( 'sr_post_type_counts' );
		$this->assertNotFalse( $cached, 'Transient "sr_post_type_counts" should be set after get_cached_data().' );

		// Second call: should return the same data from cache.
		$second_result = $this->collector->get_cached_data();

		$this->assertEquals(
			$first_result,
			$second_result,
			'Second call to get_cached_data() should return the same data as the first.'
		);
	}

	/**
	 * Test that get_cached_data() uses the cached value on subsequent calls.
	 *
	 * We prime the transient with a known sentinel value and verify that
	 * get_cached_data() returns it without re-running collect().
	 */
	public function test_caching_returns_transient_value(): void {
		$sentinel = array(
			new Field( 'Sentinel', '999' ),
		);

		set_transient( 'sr_post_type_counts', $sentinel, HOUR_IN_SECONDS );

		$result = $this->collector->get_cached_data();

		$this->assertCount( 1, $result );
		$this->assertSame( 'Sentinel', $result[0]->label );
		$this->assertSame( '999', $result[0]->value );
	}

	// -------------------------------------------------------
	// Edge-case / robustness tests.
	// -------------------------------------------------------

	/**
	 * Test that collect() gracefully returns an empty array when no posts exist
	 * for any post type.
	 *
	 * wp_count_posts() is used internally and skips post types with a zero
	 * total.  We register a transient post type with no posts and verify it
	 * does not appear in the results.  This exercises the zero-total early
	 * continue path without resorting to SQL-level filtering (which would
	 * not intercept wp_count_posts() internals).
	 */
	public function test_empty_posts_table_returns_array(): void {
		register_post_type(
			'sr_test_empty',
			array(
				'label'  => 'SR Empty',
				'public' => true,
				'labels' => array( 'name' => 'SR Empty' ),
			)
		);

		// Clear the transient so collect() runs fresh.
		delete_transient( 'sr_post_type_counts' );

		$fields = $this->collector->collect();

		$labels = array_map(
			static function ( Field $field ): string {
				return $field->label;
			},
			$fields
		);

		// A post type with zero posts must not appear in the output.
		$this->assertNotContains(
			'SR Empty',
			$labels,
			'Post types with zero posts must be omitted from the results.'
		);

		// The return value must always be an array (never null/false).
		$this->assertIsArray( $fields );

		// Clean up.
		unregister_post_type( 'sr_test_empty' );
	}

	/**
	 * Test that the collector returns at least one field when posts exist.
	 *
	 * Explicitly creates a post to guarantee the table is not empty,
	 * rather than relying on default WP test-suite content.
	 */
	public function test_collect_returns_results_when_posts_exist(): void {
		$this->factory->post->create();

		$fields = $this->collector->collect();
		$this->assertIsArray( $fields );
		$this->assertNotEmpty( $fields );
	}
}
