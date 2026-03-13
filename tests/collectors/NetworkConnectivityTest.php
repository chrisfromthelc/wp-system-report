<?php
/**
 * Network Connectivity collector tests.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Test the Network_Connectivity collector output and status logic.
 */
class NetworkConnectivityTest extends WP_UnitTestCase {

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Network_Connectivity
	 */
	private \SystemReport\Collectors\Network_Connectivity $collector;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_transient( 'sr_network_connectivity' );
		$this->collector = new \SystemReport\Collectors\Network_Connectivity();
	}

	/**
	 * Remove the cache transient after each test to avoid cross-test pollution.
	 */
	public function tear_down(): void {
		delete_transient( 'sr_network_connectivity' );
		parent::tear_down();
	}

	// -------------------------------------------------------
	// Metadata tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector ID is 'network_connectivity'.
	 */
	public function test_collector_id(): void {
		$this->assertSame( 'network_connectivity', $this->collector->get_id() );
	}

	/**
	 * Test that the collector label is a non-empty string.
	 */
	public function test_collector_label_not_empty(): void {
		$label = $this->collector->get_label();

		$this->assertIsString( $label );
		$this->assertNotEmpty( $label );
	}

	/**
	 * Test that the collector priority is 220.
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 220, $this->collector->get_priority() );
	}

	// -------------------------------------------------------
	// Return type tests.
	// -------------------------------------------------------

	/**
	 * Test that collect() returns an array containing only Field instances.
	 */
	public function test_collect_returns_field_objects(): void {
		$fields = $this->collector->collect();

		$this->assertIsArray( $fields );
		$this->assertNotEmpty( $fields );

		foreach ( $fields as $index => $field ) {
			$this->assertInstanceOf(
				Field::class,
				$field,
				"Item at index {$index} should be a Field instance."
			);
		}
	}

	/**
	 * Test that collect() returns exactly 10 fields.
	 */
	public function test_field_count(): void {
		$fields = $this->collector->collect();

		$this->assertCount( 10, $fields, 'Network_Connectivity collector should return exactly 10 fields.' );
	}

	// -------------------------------------------------------
	// Status validity tests.
	// -------------------------------------------------------

	/**
	 * Test that every field carries a valid Status enum value.
	 */
	public function test_all_fields_have_valid_status(): void {
		$fields         = $this->collector->collect();
		$valid_statuses = array( Status::Good, Status::Warning, Status::Critical, Status::Info );

		if ( empty( $fields ) ) {
			$this->assertIsArray( $fields, 'Collector returned no fields to validate.' );
			return;
		}

		foreach ( $fields as $index => $field ) {
			$this->assertInstanceOf( Field::class, $field );
			$this->assertContains(
				$field->status,
				$valid_statuses,
				"Field at index {$index} ('{$field->label}') must have a valid Status enum value."
			);
		}
	}

	// -------------------------------------------------------
	// Individual field presence tests.
	// -------------------------------------------------------

	/**
	 * Test that the "HTTP Transport" field is present and lists cURL or Streams.
	 *
	 * In any normal PHP environment at least one transport is available,
	 * so the value must contain "cURL" or "Streams".
	 */
	public function test_http_transport_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'HTTP Transport' );

		$this->assertNotNull( $field, '"HTTP Transport" field should be present.' );

		$has_curl    = false !== strpos( $field->value, 'cURL' );
		$has_streams = false !== strpos( $field->value, 'Streams' );

		$this->assertTrue(
			$has_curl || $has_streams,
			'"HTTP Transport" value should contain "cURL" or "Streams". Got: "' . $field->value . '".'
		);
	}

	/**
	 * Test that "HTTP Proxy" reports "Not configured" when no proxy constants are set.
	 *
	 * The test environment does not define WP_PROXY_HOST, so the field value
	 * must contain "Not configured".
	 */
	public function test_http_proxy_not_configured_by_default(): void {
		if ( defined( 'WP_PROXY_HOST' ) && '' !== WP_PROXY_HOST ) {
			$this->markTestSkipped( 'WP_PROXY_HOST is defined in this test environment.' );
		}

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'HTTP Proxy' );

		$this->assertNotNull( $field, '"HTTP Proxy" field should be present.' );
		$this->assertStringContainsString(
			'Not configured',
			$field->value,
			'"HTTP Proxy" value should contain "Not configured" when WP_PROXY_HOST is not set.'
		);
	}

	/**
	 * Test that "External HTTP Blocked" is "No" with Status::Good in the default test environment.
	 *
	 * The test environment does not define WP_HTTP_BLOCK_EXTERNAL, so outbound
	 * requests are permitted, the value must be "No" and the status must be Good.
	 */
	public function test_external_http_not_blocked_by_default(): void {
		if ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL ) {
			$this->markTestSkipped( 'WP_HTTP_BLOCK_EXTERNAL is true in this test environment.' );
		}

		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'External HTTP Blocked' );

		$this->assertNotNull( $field, '"External HTTP Blocked" field should be present.' );
		$this->assertSame( 'No', $field->value, '"External HTTP Blocked" value should be "No" when WP_HTTP_BLOCK_EXTERNAL is not set.' );
		$this->assertSame( Status::Good, $field->status, '"External HTTP Blocked" status should be Good when external requests are permitted.' );
	}

	/**
	 * Test that the "cURL Version" field is present with a non-empty value.
	 *
	 * When cURL is available the value contains the version string.
	 * When cURL is unavailable the value contains "Not available".
	 * Either way the value must not be empty.
	 */
	public function test_curl_version_field_present(): void {
		$fields = $this->collector->collect();
		$field  = $this->find_field_by_label( $fields, 'cURL Version' );

		$this->assertNotNull( $field, '"cURL Version" field should be present.' );
		$this->assertNotEmpty( $field->value, '"cURL Version" value should not be empty.' );
	}

	// -------------------------------------------------------
	// HTTP mock tests.
	// -------------------------------------------------------

	/**
	 * Test that "WordPress.org API" shows "Connected" with Status::Good on a successful response.
	 *
	 * Uses the pre_http_request filter to intercept the outbound request to
	 * api.wordpress.org and return a synthetic 200 response, avoiding any
	 * real network dependency in CI.
	 */
	public function test_wordpress_org_api_with_mock_success(): void {
		$mock_http = static function ( $preempt, $args, $url ) {
			if ( false !== strpos( $url, 'api.wordpress.org' ) ) {
				return array(
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'body'     => '{"offers":[]}',
					'headers'  => array(),
					'cookies'  => array(),
				);
			}
			return $preempt;
		};

		add_filter( 'pre_http_request', $mock_http, 10, 3 );
		delete_transient( 'sr_network_connectivity' );

		$fields = $this->collector->collect();

		remove_filter( 'pre_http_request', $mock_http, 10 );

		$field = $this->find_field_by_label( $fields, 'WordPress.org API' );

		$this->assertNotNull( $field, '"WordPress.org API" field should be present.' );
		$this->assertStringContainsString(
			'Connected',
			$field->value,
			'"WordPress.org API" value should contain "Connected" for a 200 response.'
		);
		$this->assertSame(
			Status::Good,
			$field->status,
			'"WordPress.org API" status should be Good for a 200 response.'
		);
	}

	/**
	 * Test that "WordPress.org API" shows Status::Critical when the request fails.
	 *
	 * Uses the pre_http_request filter to return a WP_Error for requests to
	 * api.wordpress.org, simulating a network timeout or DNS failure.
	 */
	public function test_wordpress_org_api_with_mock_failure(): void {
		$mock_http = static function ( $preempt, $args, $url ) {
			if ( false !== strpos( $url, 'api.wordpress.org' ) ) {
				return new \WP_Error( 'http_request_failed', 'Connection timed out' );
			}
			return $preempt;
		};

		add_filter( 'pre_http_request', $mock_http, 10, 3 );
		delete_transient( 'sr_network_connectivity' );

		$fields = $this->collector->collect();

		remove_filter( 'pre_http_request', $mock_http, 10 );

		$field = $this->find_field_by_label( $fields, 'WordPress.org API' );

		$this->assertNotNull( $field, '"WordPress.org API" field should be present.' );
		$this->assertSame(
			Status::Critical,
			$field->status,
			'"WordPress.org API" status should be Critical when the HTTP request returns a WP_Error.'
		);
	}

	// -------------------------------------------------------
	// Helper methods.
	// -------------------------------------------------------

	/**
	 * Find a field in the collected array by its label.
	 *
	 * @param Field[] $fields Array of collected Field objects.
	 * @param string  $label  The label to search for.
	 * @return Field|null The matching field, or null if not found.
	 */
	private function find_field_by_label( array $fields, string $label ): ?Field {
		foreach ( $fields as $field ) {
			if ( $field instanceof Field && $label === $field->label ) {
				return $field;
			}
		}
		return null;
	}
}
