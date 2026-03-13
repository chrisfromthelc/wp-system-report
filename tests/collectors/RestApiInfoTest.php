<?php
/**
 * Tests for the REST_API_Info collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Tests for the REST API Info collector.
 *
 * The WordPress test environment boots a full WP instance, so REST
 * functions (rest_url, rest_get_url_prefix, rest_get_server) behave
 * exactly as they do in production, including the automatic registration
 * of the 'wp/v2' namespace by WP core.
 */
class RestApiInfoTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\REST_API_Info
	 */
	private $collector;

	/**
	 * Fields collected once per test class to avoid repeated expensive calls.
	 *
	 * @var Field[]
	 */
	private $fields;

	/**
	 * Set up the collector instance and run collect() once before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->collector = new \SystemReport\Collectors\REST_API_Info();
		$this->fields    = $this->collector->collect();
	}

	// -------------------------------------------------------------------------
	// Identity contracts
	// -------------------------------------------------------------------------

	/**
	 * The collector must identify itself with the canonical ID string.
	 */
	public function test_collector_id() {
		$this->assertSame( 'rest_api_info', $this->collector->get_id() );
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
	 * The collector priority must be 140.
	 */
	public function test_collector_priority() {
		$this->assertSame( 140, $this->collector->get_priority() );
	}

	// -------------------------------------------------------------------------
	// Field count contract
	// -------------------------------------------------------------------------

	/**
	 * collect() must return exactly four Field objects.
	 */
	public function test_collect_returns_exactly_four_fields() {
		$this->assertCount( 4, $this->fields, 'REST API Info collector must return exactly 4 fields.' );

		foreach ( $this->fields as $index => $field ) {
			$this->assertInstanceOf(
				Field::class,
				$field,
				"Item at index $index must be a Field instance."
			);
		}
	}

	// -------------------------------------------------------------------------
	// Individual field contracts
	// -------------------------------------------------------------------------

	/**
	 * The first field must be "REST API URL" with a value matching rest_url().
	 */
	public function test_rest_url_field() {
		$field = $this->fields[0];

		$this->assertSame(
			'REST API URL',
			$field->label,
			'First field label must be "REST API URL".'
		);
		$this->assertSame(
			rest_url(),
			$field->value,
			'First field value must match rest_url().'
		);
	}

	/**
	 * The second field must be "REST Prefix" with a value matching
	 * rest_get_url_prefix().
	 */
	public function test_rest_prefix_field() {
		$field = $this->fields[1];

		$this->assertSame(
			'REST Prefix',
			$field->label,
			'Second field label must be "REST Prefix".'
		);
		$this->assertSame(
			rest_get_url_prefix(),
			$field->value,
			'Second field value must match rest_get_url_prefix().'
		);
	}

	/**
	 * The third field must be "Registered Namespaces" and its value must
	 * contain 'wp/v2' because WP core always registers that namespace.
	 */
	public function test_namespaces_field_contains_wp_v2() {
		$field = $this->fields[2];

		$this->assertSame(
			'Registered Namespaces',
			$field->label,
			'Third field label must be "Registered Namespaces".'
		);
		$this->assertStringContainsString(
			'wp/v2',
			$field->value,
			'Registered Namespaces field value must contain "wp/v2".'
		);
	}

	/**
	 * The fourth field must be "Total Namespaces" with a positive integer value.
	 */
	public function test_total_namespaces_is_positive() {
		$field = $this->fields[3];

		$this->assertSame(
			'Total Namespaces',
			$field->label,
			'Fourth field label must be "Total Namespaces".'
		);
		$this->assertGreaterThan(
			0,
			(int) $field->value,
			'Total Namespaces value must be greater than 0.'
		);
	}

	// -------------------------------------------------------------------------
	// Status contract
	// -------------------------------------------------------------------------

	/**
	 * Every field must carry the default Status::Info status because the
	 * collector does not pass an explicit status override for any field.
	 */
	public function test_all_fields_have_info_status() {
		foreach ( $this->fields as $index => $field ) {
			$this->assertSame(
				Status::Info,
				$field->status,
				"Field at index $index must carry Status::Info."
			);
		}
	}

	// -------------------------------------------------------------------------
	// Caching contract
	// -------------------------------------------------------------------------

	/**
	 * The REST API Info collector has no cache key and must not store a
	 * transient.  get_cached_data() must still return valid data.
	 */
	public function test_no_caching() {
		// The collector returns null from get_cache_key(), so get_cached_data()
		// must call collect() directly every time without touching any transient.
		$data = $this->collector->get_cached_data();

		$this->assertIsArray( $data );
		$this->assertCount( 4, $data, 'get_cached_data() must return the same 4 fields.' );

		// No transient should have been written for this collector.  Because the
		// cache key is null the framework never calls set_transient(), so querying
		// any made-up key confirms nothing was stored under a guessed name either.
		// The real assurance is the absence of a cache_key on the class itself.
		$reflection = new ReflectionMethod( $this->collector, 'get_cache_key' );
		$reflection->setAccessible( true );
		$cache_key = $reflection->invoke( $this->collector );

		$this->assertNull(
			$cache_key,
			'REST_API_Info::get_cache_key() must return null (no caching).'
		);
	}
}
