<?php
/**
 * Server Environment collector tests.
 *
 * @package SystemReport
 */

use SystemReport\Collectors\Server_Environment;
use SystemReport\Field;
use SystemReport\Status;

/**
 * Test the Server_Environment collector.
 */
class ServerEnvironmentTest extends WP_UnitTestCase {

	/**
	 * Collector under test.
	 *
	 * @var Server_Environment
	 */
	private Server_Environment $collector;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->collector = new Server_Environment();
	}

	// -------------------------------------------------------
	// Collector identity tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector returns the correct ID.
	 */
	public function test_collector_id(): void {
		$this->assertSame( 'server_environment', $this->collector->get_id() );
	}

	/**
	 * Test that the collector returns a non-empty label.
	 */
	public function test_collector_label(): void {
		$this->assertNotEmpty( $this->collector->get_label() );
		$this->assertIsString( $this->collector->get_label() );
	}

	/**
	 * Test that the collector returns the correct priority.
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 20, $this->collector->get_priority() );
	}

	// -------------------------------------------------------
	// collect() return type tests.
	// -------------------------------------------------------

	/**
	 * Test that collect() returns an array of Field objects.
	 */
	public function test_collect_returns_array_of_field_objects(): void {
		$fields = $this->collector->collect();

		$this->assertIsArray( $fields );
		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			$this->assertInstanceOf( Field::class, $field );
		}
	}

	/**
	 * Test that collect() returns at least 14 fields.
	 */
	public function test_field_count(): void {
		$fields = $this->collector->collect();

		$this->assertGreaterThanOrEqual( 14, count( $fields ), 'Server_Environment should return at least 14 fields.' );
	}

	// -------------------------------------------------------
	// PHP version field tests.
	// -------------------------------------------------------

	/**
	 * Test that the "PHP Version" field is present and contains the running version.
	 */
	public function test_php_version_field_present(): void {
		$field = $this->find_field( 'PHP Version' );

		$this->assertNotNull( $field, 'Expected "PHP Version" field to be present.' );
		$this->assertSame( phpversion(), $field->value );
	}

	/**
	 * Test that the "PHP Version" status matches the running PHP version.
	 *
	 * Status is Good for PHP >= 8.1 (the plugin's minimum), Warning below.
	 */
	public function test_php_version_status(): void {
		$field = $this->find_field( 'PHP Version' );

		$this->assertNotNull( $field, 'Expected "PHP Version" field to be present.' );

		$expected_status = version_compare( phpversion(), '8.1', '>=' ) ? Status::Good : Status::Warning;

		$this->assertSame( $expected_status, $field->status, 'PHP Version status should reflect the running PHP version.' );
	}

	// -------------------------------------------------------
	// Database field tests.
	// -------------------------------------------------------

	/**
	 * Test that the "MySQL Version" field is present and has a non-empty value.
	 */
	public function test_mysql_version_field_present(): void {
		$field = $this->find_field( 'MySQL Version' );

		$this->assertNotNull( $field, 'Expected "MySQL Version" field to be present.' );
		$this->assertNotEmpty( $field->value );
	}

	// -------------------------------------------------------
	// Extension / function status tests.
	// -------------------------------------------------------

	/**
	 * Test that "cURL Version" is Status::Good when cURL is available.
	 */
	public function test_curl_version_status(): void {
		$field = $this->find_field( 'cURL Version' );

		$this->assertNotNull( $field, 'Expected "cURL Version" field to be present.' );

		if ( function_exists( 'curl_version' ) ) {
			$this->assertSame( Status::Good, $field->status, 'cURL Version should be Good when cURL is available.' );
		} else {
			$this->assertSame( Status::Warning, $field->status, 'cURL Version should be Warning when cURL is unavailable.' );
		}
	}

	/**
	 * Test that "DOMDocument" is Status::Good when the class is available.
	 */
	public function test_domdocument_status(): void {
		$field = $this->find_field( 'DOMDocument' );

		$this->assertNotNull( $field, 'Expected "DOMDocument" field to be present.' );

		if ( class_exists( 'DOMDocument' ) ) {
			$this->assertSame( Status::Good, $field->status, 'DOMDocument should be Good when the class exists.' );
		} else {
			$this->assertSame( Status::Warning, $field->status, 'DOMDocument should be Warning when the class is missing.' );
		}
	}

	/**
	 * Test that "GZip" is Status::Good when gzopen is callable.
	 */
	public function test_gzip_status(): void {
		$field = $this->find_field( 'GZip' );

		$this->assertNotNull( $field, 'Expected "GZip" field to be present.' );

		if ( is_callable( 'gzopen' ) ) {
			$this->assertSame( Status::Good, $field->status, 'GZip should be Good when gzopen is callable.' );
		} else {
			$this->assertSame( Status::Warning, $field->status, 'GZip should be Warning when gzopen is not callable.' );
		}
	}

	/**
	 * Test that "Multibyte String" is Status::Good when the mbstring extension is loaded.
	 */
	public function test_mbstring_status(): void {
		$field = $this->find_field( 'Multibyte String' );

		$this->assertNotNull( $field, 'Expected "Multibyte String" field to be present.' );

		if ( extension_loaded( 'mbstring' ) ) {
			$this->assertSame( Status::Good, $field->status, 'Multibyte String should be Good when mbstring is loaded.' );
		} else {
			$this->assertSame( Status::Warning, $field->status, 'Multibyte String should be Warning when mbstring is missing.' );
		}
	}

	/**
	 * Test that "OpenSSL" is Status::Good when the openssl extension is loaded.
	 */
	public function test_openssl_status(): void {
		$field = $this->find_field( 'OpenSSL' );

		$this->assertNotNull( $field, 'Expected "OpenSSL" field to be present.' );

		if ( extension_loaded( 'openssl' ) ) {
			$this->assertSame( Status::Good, $field->status, 'OpenSSL should be Good when openssl is loaded.' );
		} else {
			$this->assertSame( Status::Warning, $field->status, 'OpenSSL should be Warning when openssl is missing.' );
		}
	}

	/**
	 * Test that "Imagick" is always Status::Info regardless of availability.
	 */
	public function test_imagick_is_info(): void {
		$field = $this->find_field( 'Imagick' );

		$this->assertNotNull( $field, 'Expected "Imagick" field to be present.' );
		$this->assertSame( Status::Info, $field->status, 'Imagick should always be Status::Info.' );
	}

	// -------------------------------------------------------
	// PHP configuration field tests.
	// -------------------------------------------------------

	/**
	 * Test that the "PHP Memory Limit" field is present and has a non-empty value.
	 */
	public function test_php_memory_limit_field(): void {
		$field = $this->find_field( 'PHP Memory Limit' );

		$this->assertNotNull( $field, 'Expected "PHP Memory Limit" field to be present.' );
		$this->assertNotEmpty( $field->value );
	}

	// -------------------------------------------------------
	// Caching tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector has no cache key (caching disabled).
	 */
	public function test_no_caching(): void {
		$reflection = new ReflectionMethod( $this->collector, 'get_cache_key' );
		$reflection->setAccessible( true );

		$cache_key = $reflection->invoke( $this->collector );

		$this->assertNull( $cache_key, 'Server_Environment collector should not define a cache key.' );
	}

	// -------------------------------------------------------
	// Helper methods.
	// -------------------------------------------------------

	/**
	 * Find a field by label in a freshly collected result set.
	 *
	 * @param string $label The field label to search for.
	 * @return Field|null The matching Field object, or null if not found.
	 */
	private function find_field( string $label ): ?Field {
		foreach ( $this->collector->collect() as $field ) {
			if ( $label === $field->label ) {
				return $field;
			}
		}

		return null;
	}
}
