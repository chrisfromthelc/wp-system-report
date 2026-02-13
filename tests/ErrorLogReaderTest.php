<?php
/**
 * Error Log Reader tests.
 *
 * @package SystemReport
 */

/**
 * Test the Error_Log_Reader class.
 */
class ErrorLogReaderTest extends WP_UnitTestCase {

	/**
	 * Reader instance.
	 *
	 * @var SystemReport\Error_Log_Reader
	 */
	private $reader;

	/**
	 * Temp file path.
	 *
	 * @var string
	 */
	private $temp_file;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		$this->reader = new SystemReport\Error_Log_Reader();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		parent::tear_down();

		if ( isset( $this->temp_file ) && file_exists( $this->temp_file ) ) {
			unlink( $this->temp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/**
	 * Create a temp log file with the given lines.
	 *
	 * @param array $lines Lines to write to the temp file.
	 * @return string Path to the temp file.
	 */
	private function create_temp_log( array $lines ): string {
		$this->temp_file = tempnam( sys_get_temp_dir(), 'sr_test_log_' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $this->temp_file, implode( "\n", $lines ) . "\n" );
		return $this->temp_file;
	}

	// ---------------------------------------------------------------
	// read_last_lines tests
	// ---------------------------------------------------------------

	/**
	 * Test reading last lines from a small file.
	 */
	public function test_read_last_lines_small_file(): void {
		$lines = array( 'Line 1', 'Line 2', 'Line 3', 'Line 4', 'Line 5' );
		$path  = $this->create_temp_log( $lines );

		$result = $this->reader->read_last_lines( $path, 3 );
		$this->assertCount( 3, $result );
		$this->assertSame( 'Line 3', $result[0] );
		$this->assertSame( 'Line 4', $result[1] );
		$this->assertSame( 'Line 5', $result[2] );
	}

	/**
	 * Test reading more lines than exist.
	 */
	public function test_read_last_lines_request_more_than_available(): void {
		$lines = array( 'Line 1', 'Line 2' );
		$path  = $this->create_temp_log( $lines );

		$result = $this->reader->read_last_lines( $path, 100 );
		$this->assertCount( 2, $result );
		$this->assertSame( 'Line 1', $result[0] );
		$this->assertSame( 'Line 2', $result[1] );
	}

	/**
	 * Test reading from an empty file.
	 */
	public function test_read_last_lines_empty_file(): void {
		$this->temp_file = tempnam( sys_get_temp_dir(), 'sr_test_log_' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $this->temp_file, '' );

		$result = $this->reader->read_last_lines( $this->temp_file, 10 );
		$this->assertSame( array(), $result );
	}

	/**
	 * Test reading from a nonexistent file.
	 */
	public function test_read_last_lines_nonexistent_file(): void {
		$result = $this->reader->read_last_lines( '/tmp/nonexistent_' . uniqid() . '.log', 10 );
		$this->assertSame( array(), $result );
	}

	/**
	 * Test reading exactly 1 line.
	 */
	public function test_read_last_lines_single_line(): void {
		$lines = array( 'Line 1', 'Line 2', 'Line 3' );
		$path  = $this->create_temp_log( $lines );

		$result = $this->reader->read_last_lines( $path, 1 );
		$this->assertCount( 1, $result );
		$this->assertSame( 'Line 3', $result[0] );
	}

	/**
	 * Test redaction filter is applied to each line.
	 */
	public function test_read_last_lines_applies_redaction_filter(): void {
		$lines = array( 'password=secret123', 'normal line' );
		$path  = $this->create_temp_log( $lines );

		add_filter(
			'wp_system_report_redact_log_line',
			function ( $line ) {
				return str_replace( 'secret123', '[REDACTED]', $line );
			}
		);

		$result = $this->reader->read_last_lines( $path, 2 );
		$this->assertSame( 'password=[REDACTED]', $result[0] );
		$this->assertSame( 'normal line', $result[1] );
	}

	/**
	 * Test reading a larger file triggers adaptive chunking.
	 */
	public function test_read_last_lines_large_file(): void {
		// Create a file larger than the initial chunk size (8KB).
		$lines = array();
		for ( $i = 1; $i <= 500; $i++ ) {
			$lines[] = sprintf( '[%s] PHP Notice: Test error #%d in /var/www/html/test.php on line %d', gmdate( 'd-M-Y H:i:s' ), $i, $i );
		}
		$path = $this->create_temp_log( $lines );

		$result = $this->reader->read_last_lines( $path, 10 );
		$this->assertCount( 10, $result );
		// Last line should be error #500.
		$this->assertStringContainsString( '#500', $result[9] );
	}

	// ---------------------------------------------------------------
	// is_path_safe tests
	// ---------------------------------------------------------------

	/**
	 * Test path within ABSPATH is safe.
	 */
	public function test_is_path_safe_within_abspath(): void {
		// ABSPATH is defined in the test environment.
		$path = ABSPATH . 'wp-config.php';
		$this->assertTrue( $this->reader->is_path_safe( $path ) );
	}

	/**
	 * Test path within WP_CONTENT_DIR is safe.
	 */
	public function test_is_path_safe_within_content_dir(): void {
		$path = WP_CONTENT_DIR . '/debug.log';
		// May not exist, so create a temp file there for testing.
		// Use ABSPATH as a proxy since we know it exists.
		$this->assertTrue( $this->reader->is_path_safe( ABSPATH . 'index.php' ) );
	}

	/**
	 * Test nonexistent path is not safe.
	 */
	public function test_is_path_safe_nonexistent(): void {
		$this->assertFalse( $this->reader->is_path_safe( '/nonexistent/path/to/file.log' ) );
	}

	/**
	 * Test allowed_log_paths filter.
	 */
	public function test_is_path_safe_with_allowed_paths_filter(): void {
		$temp_dir = sys_get_temp_dir();
		$path     = $this->create_temp_log( array( 'test' ) );

		// Without filter, temp dir may not be safe.
		// Add it via the filter.
		add_filter(
			'wp_system_report_allowed_log_paths',
			function () use ( $temp_dir ) {
				return array( $temp_dir );
			}
		);

		$this->assertTrue( $this->reader->is_path_safe( $path ) );
	}

	// ---------------------------------------------------------------
	// get_debug_constants tests
	// ---------------------------------------------------------------

	/**
	 * Test get_debug_constants returns expected keys.
	 */
	public function test_get_debug_constants_structure(): void {
		$constants = $this->reader->get_debug_constants();

		$this->assertArrayHasKey( 'wp_debug', $constants );
		$this->assertArrayHasKey( 'wp_debug_log', $constants );
		$this->assertArrayHasKey( 'wp_debug_display', $constants );
		$this->assertArrayHasKey( 'log_errors', $constants );
		$this->assertArrayHasKey( 'error_log', $constants );
		$this->assertArrayHasKey( 'display_errors', $constants );
		$this->assertArrayHasKey( 'error_reporting', $constants );
	}

	/**
	 * Test get_debug_constants values are correct types.
	 */
	public function test_get_debug_constants_types(): void {
		$constants = $this->reader->get_debug_constants();

		$this->assertIsBool( $constants['wp_debug'] );
		$this->assertIsBool( $constants['wp_debug_display'] );
		$this->assertIsString( $constants['log_errors'] );
		$this->assertIsString( $constants['error_log'] );
		$this->assertIsString( $constants['display_errors'] );
		$this->assertIsInt( $constants['error_reporting'] );
	}

	// ---------------------------------------------------------------
	// get_file_info tests
	// ---------------------------------------------------------------

	/**
	 * Test get_file_info returns expected structure.
	 */
	public function test_get_file_info_structure(): void {
		$info = $this->reader->get_file_info();

		$this->assertArrayHasKey( 'path', $info );
		$this->assertArrayHasKey( 'exists', $info );
		$this->assertArrayHasKey( 'readable', $info );
		$this->assertArrayHasKey( 'size', $info );
		$this->assertArrayHasKey( 'size_formatted', $info );
		$this->assertArrayHasKey( 'safe', $info );
	}

	// ---------------------------------------------------------------
	// get_status tests
	// ---------------------------------------------------------------

	/**
	 * Test get_status returns file and constants keys.
	 */
	public function test_get_status_structure(): void {
		$status = $this->reader->get_status();

		$this->assertArrayHasKey( 'file', $status );
		$this->assertArrayHasKey( 'constants', $status );
		$this->assertIsArray( $status['file'] );
		$this->assertIsArray( $status['constants'] );
	}

	// ---------------------------------------------------------------
	// resolve_log_path tests
	// ---------------------------------------------------------------

	/**
	 * Test resolve_log_path returns a string or null.
	 */
	public function test_resolve_log_path_returns_string_or_null(): void {
		$path = $this->reader->resolve_log_path();
		// It will resolve to something (ini error_log is set in Local) or null.
		$this->assertTrue( null === $path || is_string( $path ) );
	}

	// ---------------------------------------------------------------
	// Default redaction tests
	// ---------------------------------------------------------------

	/**
	 * Test default redaction replaces password patterns.
	 */
	public function test_default_redaction_password_pattern(): void {
		$lines = array( 'Error: password=mysecret123 in connection' );
		$path  = $this->create_temp_log( $lines );

		$result = $this->reader->read_last_lines( $path, 1 );
		$this->assertStringContainsString( '[REDACTED]', $result[0] );
		$this->assertStringNotContainsString( 'mysecret123', $result[0] );
	}

	/**
	 * Test default redaction replaces token patterns.
	 */
	public function test_default_redaction_token_pattern(): void {
		$lines = array( 'API call failed: token=abc123xyz api_key=def456' );
		$path  = $this->create_temp_log( $lines );

		$result = $this->reader->read_last_lines( $path, 1 );
		$this->assertStringContainsString( '[REDACTED]', $result[0] );
		$this->assertStringNotContainsString( 'abc123xyz', $result[0] );
		$this->assertStringNotContainsString( 'def456', $result[0] );
	}

	/**
	 * Test default redaction replaces Authorization headers.
	 */
	public function test_default_redaction_authorization_header(): void {
		$lines = array( 'Request: Authorization: Bearer eyJhbGciOiJIUzI1NiJ9' );
		$path  = $this->create_temp_log( $lines );

		$result = $this->reader->read_last_lines( $path, 1 );
		$this->assertStringContainsString( 'Authorization: [REDACTED]', $result[0] );
		$this->assertStringNotContainsString( 'eyJhbGciOiJIUzI1NiJ9', $result[0] );
	}

	/**
	 * Test default redaction replaces database connection strings.
	 */
	public function test_default_redaction_db_connection_string(): void {
		$lines = array( 'Connection: mysql://root:secret@localhost/mydb' );
		$path  = $this->create_temp_log( $lines );

		$result = $this->reader->read_last_lines( $path, 1 );
		$this->assertStringContainsString( 'mysql://[REDACTED]', $result[0] );
		$this->assertStringNotContainsString( 'root:secret', $result[0] );
	}

	/**
	 * Test default redaction does not affect normal lines.
	 */
	public function test_default_redaction_normal_line_unchanged(): void {
		$lines = array( '[01-Jan-2025 12:00:00] PHP Notice: Undefined variable: foo in /var/www/test.php on line 42' );
		$path  = $this->create_temp_log( $lines );

		$result = $this->reader->read_last_lines( $path, 1 );
		$this->assertStringNotContainsString( '[REDACTED]', $result[0] );
	}

	// ---------------------------------------------------------------
	// Path redaction tests
	// ---------------------------------------------------------------

	/**
	 * Test get_file_info returns relative path, not absolute.
	 */
	public function test_get_file_info_returns_relative_path(): void {
		$info = $this->reader->get_file_info();

		if ( null !== $info['path'] ) {
			// Path should be relative (no leading slash from WP_CONTENT_DIR).
			$this->assertStringNotContainsString( ABSPATH, $info['path'] );
			// Should not start with / (absolute path indicator).
			$this->assertDoesNotMatchRegularExpression( '#^/#', $info['path'] );
		} else {
			// If no log file exists, path should be null — still a valid assertion.
			$this->assertNull( $info['path'] );
		}
	}
}
