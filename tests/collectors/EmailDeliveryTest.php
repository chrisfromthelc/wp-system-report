<?php
/**
 * Email Delivery collector tests.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Tests for the Email_Delivery collector.
 *
 * Covers collector identity, field structure, privacy flags,
 * status logic driven by the wp_mail_from_name filter, and
 * transient caching behaviour.
 */
class EmailDeliveryTest extends WP_UnitTestCase {

	/**
	 * Collector under test.
	 *
	 * @var \SystemReport\Collectors\Email_Delivery
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
		$this->collector = $collectors['email_delivery'];

		delete_transient( 'sr_email_delivery' );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		delete_transient( 'sr_email_delivery' );
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
		$this->assertSame( 'email_delivery', $this->collector->get_id() );
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
		$this->assertSame( 180, $this->collector->get_priority() );
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
	 * Test that the Admin Email field is present.
	 */
	public function test_admin_email_field_present() {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Admin Email' );

		$this->assertNotNull( $field, 'Admin Email field should be present.' );
	}

	/**
	 * Test that the Admin Email field is marked as private.
	 */
	public function test_admin_email_is_private() {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Admin Email' );

		$this->assertNotNull( $field, 'Admin Email field should be present.' );
		$this->assertTrue( $field->private, 'Admin Email field should be private.' );
	}

	/**
	 * Test that a default "WordPress" from name produces a Warning status.
	 */
	public function test_from_name_default_wordpress_is_warning() {
		$callback = static function () {
			return 'WordPress';
		};
		add_filter( 'wp_mail_from_name', $callback, 99 );

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'From Name' );

		remove_filter( 'wp_mail_from_name', $callback, 99 );

		$this->assertNotNull( $field, 'From Name field should be present.' );
		$this->assertSame( Status::Warning, $field->status );
	}

	/**
	 * Test that a custom from name produces a Good status.
	 */
	public function test_from_name_custom_is_good() {
		$callback = static function () {
			return 'My Site';
		};
		add_filter( 'wp_mail_from_name', $callback, 99 );

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'From Name' );

		remove_filter( 'wp_mail_from_name', $callback, 99 );

		$this->assertNotNull( $field, 'From Name field should be present.' );
		$this->assertSame( Status::Good, $field->status );
	}

	/**
	 * Test that the SMTP Host field is present.
	 */
	public function test_smtp_host_field_present() {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'SMTP Host' );

		$this->assertNotNull( $field, 'SMTP Host field should be present.' );
	}

	/**
	 * Test that the Mail Plugin field is present.
	 */
	public function test_mail_plugin_field_present() {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'Mail Plugin' );

		$this->assertNotNull( $field, 'Mail Plugin field should be present.' );
	}

	/**
	 * Test that collect() returns exactly 11 fields.
	 */
	public function test_field_count() {
		$fields = $this->collector->collect();
		$this->assertCount( 11, $fields );
	}

	// -------------------------------------------------------------------------
	// Caching
	// -------------------------------------------------------------------------

	/**
	 * Test that get_cached_data() stores results in a transient on the first
	 * call, and returns identical data on the second call.
	 */
	public function test_caching() {
		delete_transient( 'sr_email_delivery' );

		$data1 = $this->collector->get_cached_data();
		$this->assertIsArray( $data1 );

		$cached = get_transient( 'sr_email_delivery' );
		$this->assertNotFalse( $cached, 'Transient should be set after first get_cached_data() call.' );

		$data2 = $this->collector->get_cached_data();
		$this->assertEquals( $data1, $data2 );

		delete_transient( 'sr_email_delivery' );
	}
}
