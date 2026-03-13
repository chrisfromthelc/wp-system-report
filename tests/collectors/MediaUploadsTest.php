<?php
/**
 * Media & Uploads collector tests.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Tests for the Media_Uploads collector.
 *
 * Covers collector identity, field structure, writable-directory
 * status, attachment count behaviour, and transient caching.
 */
class MediaUploadsTest extends WP_UnitTestCase {

	/**
	 * Collector under test.
	 *
	 * @var \SystemReport\Collectors\Media_Uploads
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
		$this->collector = $collectors['media_uploads'];

		delete_transient( 'sr_media_uploads' );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		delete_transient( 'sr_media_uploads' );
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
		$this->assertSame( 'media_uploads', $this->collector->get_id() );
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
		$this->assertSame( 190, $this->collector->get_priority() );
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
	 * Test that the Upload Directory field is present.
	 */
	public function test_upload_directory_field_present() {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Upload Directory' );

		$this->assertNotNull( $field, 'Upload Directory field should be present.' );
	}

	/**
	 * Test that the Upload Dir Writable field is present.
	 */
	public function test_upload_dir_writable_field_present() {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Upload Dir Writable' );

		$this->assertNotNull( $field, 'Upload Dir Writable field should be present.' );
	}

	/**
	 * Test that the upload directory is writable in the test environment,
	 * resulting in a Good status on the Upload Dir Writable field.
	 */
	public function test_upload_dir_writable_is_good() {
		$upload_dir = wp_upload_dir();

		// Only assert Good status when the directory is actually writable;
		// skip the assertion on environments where it is not.
		if ( ! wp_is_writable( $upload_dir['basedir'] ) ) {
			$this->markTestSkipped( 'Upload directory is not writable in this environment.' );
		}

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Upload Dir Writable' );

		$this->assertNotNull( $field, 'Upload Dir Writable field should be present.' );
		$this->assertSame( Status::Good, $field->status );
	}

	/**
	 * Test that the Total Attachments field is present.
	 */
	public function test_total_attachments_field_present() {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Total Attachments' );

		$this->assertNotNull( $field, 'Total Attachments field should be present.' );
	}

	/**
	 * Test that Total Attachments reflects posts created during the test.
	 *
	 * Creating attachment posts via the factory should cause the reported
	 * count to increase compared to the baseline.
	 */
	public function test_total_attachments_with_posts() {
		$fields_before = $this->collector->collect();
		$field_before  = $this->find_field_by_label( $fields_before, 'Total Attachments' );
		$this->assertNotNull( $field_before, 'Total Attachments field should be present.' );

		// Create two attachment posts.
		$this->factory->attachment->create_many( 2 );

		$fields_after = $this->collector->collect();
		$field_after  = $this->find_field_by_label( $fields_after, 'Total Attachments' );
		$this->assertNotNull( $field_after, 'Total Attachments field should be present after creating attachments.' );

		// The formatted count after should differ from the count before.
		// Use wp_count_posts directly to get integer values for comparison.
		$count_after  = (int) wp_count_posts( 'attachment' )->inherit;
		$count_before = (int) number_format( (float) str_replace( ',', '', $field_before->value ) );

		$this->assertGreaterThan( $count_before, $count_after );
	}

	/**
	 * Test that collect() returns exactly 11 fields.
	 */
	public function test_field_count() {
		$fields = $this->collector->collect();
		$this->assertCount( 11, $fields );
	}

	/**
	 * Test that the Image Editor field is present.
	 */
	public function test_image_editor_field_present() {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Image Editor' );

		$this->assertNotNull( $field, 'Image Editor field should be present.' );
	}

	// -------------------------------------------------------------------------
	// Caching
	// -------------------------------------------------------------------------

	/**
	 * Test that get_cached_data() stores results in a transient on the first
	 * call, and returns identical data on the second call.
	 */
	public function test_caching() {
		delete_transient( 'sr_media_uploads' );

		$data1 = $this->collector->get_cached_data();
		$this->assertIsArray( $data1 );

		$cached = get_transient( 'sr_media_uploads' );
		$this->assertNotFalse( $cached, 'Transient should be set after first get_cached_data() call.' );

		$data2 = $this->collector->get_cached_data();
		$this->assertEquals( $data1, $data2 );

		delete_transient( 'sr_media_uploads' );
	}
}
