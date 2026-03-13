<?php
/**
 * Block Editor collector tests.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Tests for the Block_Editor collector.
 *
 * Covers collector identity, field structure, registered block type
 * count, classic-editor override status, and transient caching.
 */
class BlockEditorTest extends WP_UnitTestCase {

	/**
	 * Collector under test.
	 *
	 * @var \SystemReport\Collectors\Block_Editor
	 */
	private $collector;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$plugin          = SystemReport\Plugin::get_instance();
		$generator       = $plugin->get_report_generator();
		$collectors      = $generator->get_collectors();
		$this->collector = $collectors['block_editor'];

		delete_transient( 'sr_block_editor' );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		delete_transient( 'sr_block_editor' );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Find a Field in a fields array by its label.
	 *
	 * @param Field[] $fields Array of Field objects returned by collect().
	 * @param string  $label  The label to search for.
	 * @return Field|null The matching Field, or null if not found.
	 */
	private function find_field_by_label( array $fields, string $label ): ?Field {
		foreach ( $fields as $field ) {
			if ( $field instanceof Field && $label === $field->label ) {
				return $field;
			}
		}
		return null;
	}

	// -------------------------------------------------------------------------
	// Identity
	// -------------------------------------------------------------------------

	/**
	 * Test that the collector has the expected ID.
	 */
	public function test_collector_id() {
		$this->assertSame( 'block_editor', $this->collector->get_id() );
	}

	/**
	 * Test that the collector label is a non-empty string.
	 */
	public function test_collector_label_not_empty() {
		$label = $this->collector->get_label();
		$this->assertIsString( $label );
		$this->assertNotEmpty( $label );
	}

	/**
	 * Test that the collector has the expected priority.
	 */
	public function test_collector_priority() {
		$this->assertSame( 230, $this->collector->get_priority() );
	}

	// -------------------------------------------------------------------------
	// collect() structure
	// -------------------------------------------------------------------------

	/**
	 * Test that collect() returns an array of Field objects.
	 */
	public function test_collect_returns_field_objects() {
		$fields = $this->collector->collect();

		$this->assertIsArray( $fields );

		if ( empty( $fields ) ) {
			$this->assertIsArray( $fields );
			return;
		}

		foreach ( $fields as $field ) {
			$this->assertInstanceOf( Field::class, $field );
		}
	}

	/**
	 * Test that the Block Theme (FSE) field is present.
	 */
	public function test_block_theme_field_present() {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Block Theme (FSE)' );

		$this->assertNotNull( $field, 'Block Theme (FSE) field should be present.' );
	}

	/**
	 * Test that the Registered Block Types field is present.
	 */
	public function test_registered_block_types_field_present() {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Registered Block Types' );

		$this->assertNotNull( $field, 'Registered Block Types field should be present.' );
	}

	/**
	 * Test that the registered block type count reflects at least the core blocks
	 * that WordPress always ships with.
	 *
	 * The count is exposed as a formatted integer string via number_format_i18n().
	 * Stripping commas and casting to int gives a comparable integer.
	 */
	public function test_registered_block_types_count() {
		$registry = WP_Block_Type_Registry::get_instance();
		$count    = count( $registry->get_all_registered() );

		// WordPress core ships with a large number of blocks; always > 0.
		$this->assertGreaterThan( 0, $count );

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Registered Block Types' );

		$this->assertNotNull( $field, 'Registered Block Types field should be present.' );

		$reported = (int) str_replace( ',', '', $field->value );
		$this->assertSame( $count, $reported );
	}

	/**
	 * Test that the Block Sources field is present.
	 */
	public function test_block_sources_field_present() {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Block Sources' );

		$this->assertNotNull( $field, 'Block Sources field should be present.' );
	}

	/**
	 * Test that the Registered Block Patterns field is present.
	 */
	public function test_registered_patterns_field_present() {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Registered Block Patterns' );

		$this->assertNotNull( $field, 'Registered Block Patterns field should be present.' );
	}

	/**
	 * Test that when the Classic Editor plugin is not active, the
	 * Classic Editor Override field has a Good status.
	 *
	 * In the test environment no Classic Editor plugin is loaded,
	 * so the field value should be "Not active" with Status::Good.
	 */
	public function test_classic_editor_not_active_is_good() {
		// Ensure neither classic-editor plugin is active in the test environment.
		$classic_active   = is_plugin_active( 'classic-editor/classic-editor.php' );
		$gutenberg_active = is_plugin_active( 'disable-gutenberg/disable-gutenberg.php' );

		if ( $classic_active || $gutenberg_active ) {
			$this->markTestSkipped( 'A classic editor override plugin is active in this environment.' );
		}

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Classic Editor Override' );

		$this->assertNotNull( $field, 'Classic Editor Override field should be present.' );
		$this->assertSame( Status::Good, $field->status );
	}

	/**
	 * Test that collect() returns exactly 10 fields.
	 */
	public function test_field_count() {
		$fields = $this->collector->collect();
		$this->assertCount( 10, $fields );
	}

	// -------------------------------------------------------------------------
	// Caching
	// -------------------------------------------------------------------------

	/**
	 * Test that get_cached_data() stores results in a transient on the first
	 * call, and returns identical data on the second call.
	 */
	public function test_caching() {
		delete_transient( 'sr_block_editor' );

		$data1 = $this->collector->get_cached_data();
		$this->assertIsArray( $data1 );

		$cached = get_transient( 'sr_block_editor' );
		$this->assertNotFalse( $cached, 'Transient should be set after first get_cached_data() call.' );

		$data2 = $this->collector->get_cached_data();
		$this->assertEquals( $data1, $data2 );

		delete_transient( 'sr_block_editor' );
	}
}
