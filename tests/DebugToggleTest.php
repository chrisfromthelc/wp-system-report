<?php
/**
 * Debug Toggle tests.
 *
 * @package SystemReport
 */

/**
 * Test the Debug_Toggle class.
 */
class DebugToggleTest extends WP_UnitTestCase {

	/**
	 * Temp config file path.
	 *
	 * @var string
	 */
	private $temp_config;

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		parent::tear_down();

		if ( isset( $this->temp_config ) ) {
			if ( file_exists( $this->temp_config ) ) {
				unlink( $this->temp_config ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}

			// Clean up backup in temp directory.
			$backup_path = get_temp_dir() . 'wp-system-report-config-' . md5( $this->temp_config ) . '.bak';
			if ( file_exists( $backup_path ) ) {
				unlink( $backup_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}

		// Clean up lock file.
		$lock_path = get_temp_dir() . 'wp-system-report-config.lock';
		if ( file_exists( $lock_path ) ) {
			unlink( $lock_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/**
	 * Create a minimal wp-config.php temp file.
	 *
	 * @param string $extra_content Additional PHP content before the stop comment.
	 * @return string Path to the temp config file.
	 */
	private function create_temp_config( string $extra_content = '' ): string {
		$this->temp_config = tempnam( sys_get_temp_dir(), 'sr_test_wpconfig_' );

		$config = "<?php\n";
		$config .= "define( 'DB_NAME', 'test_db' );\n";
		$config .= "define( 'DB_USER', 'root' );\n";
		$config .= "define( 'DB_PASSWORD', '' );\n";
		$config .= "define( 'DB_HOST', 'localhost' );\n";

		if ( '' !== $extra_content ) {
			$config .= $extra_content . "\n";
		}

		$config .= "\n/* That's all, stop editing! Happy publishing. */\n";
		$config .= "if ( ! defined( 'ABSPATH' ) ) {\n";
		$config .= "\tdefine( 'ABSPATH', __DIR__ . '/' );\n";
		$config .= "}\n";
		$config .= "require_once ABSPATH . 'wp-settings.php';\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $this->temp_config, $config );

		return $this->temp_config;
	}

	// ---------------------------------------------------------------
	// can_modify tests
	// ---------------------------------------------------------------

	/**
	 * Test can_modify returns true for a writable file.
	 */
	public function test_can_modify_writable_file(): void {
		$path   = $this->create_temp_config();
		$toggle = new SystemReport\Debug_Toggle( $path );

		$this->assertTrue( $toggle->can_modify() );
	}

	/**
	 * Test can_modify returns false for nonexistent file.
	 */
	public function test_can_modify_nonexistent_file(): void {
		$toggle = new SystemReport\Debug_Toggle( '/tmp/nonexistent_' . uniqid() . '.php' );
		$this->assertFalse( $toggle->can_modify() );
	}

	/**
	 * Test can_modify returns false for read-only file.
	 */
	public function test_can_modify_readonly_file(): void {
		$path = $this->create_temp_config();
		chmod( $path, 0444 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod

		$toggle = new SystemReport\Debug_Toggle( $path );
		$this->assertFalse( $toggle->can_modify() );

		// Restore write permission for cleanup.
		chmod( $path, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
	}

	// ---------------------------------------------------------------
	// get_state tests
	// ---------------------------------------------------------------

	/**
	 * Test get_state returns nulls when constants are not defined.
	 */
	public function test_get_state_no_constants(): void {
		$path   = $this->create_temp_config();
		$toggle = new SystemReport\Debug_Toggle( $path );
		$state  = $toggle->get_state();

		$this->assertNull( $state['wp_debug'] );
		$this->assertNull( $state['wp_debug_log'] );
		$this->assertNull( $state['wp_debug_display'] );
		$this->assertTrue( $state['can_modify'] );
	}

	/**
	 * Test get_state reads existing constants.
	 */
	public function test_get_state_with_constants(): void {
		$extra = "define( 'WP_DEBUG', true );\n";
		$extra .= "define( 'WP_DEBUG_LOG', true );\n";
		$extra .= "define( 'WP_DEBUG_DISPLAY', false );";

		$path   = $this->create_temp_config( $extra );
		$toggle = new SystemReport\Debug_Toggle( $path );
		$state  = $toggle->get_state();

		$this->assertTrue( $state['wp_debug'] );
		$this->assertTrue( $state['wp_debug_log'] );
		$this->assertFalse( $state['wp_debug_display'] );
	}

	/**
	 * Test get_state reads false constants.
	 */
	public function test_get_state_with_false_constants(): void {
		$extra = "define( 'WP_DEBUG', false );";

		$path   = $this->create_temp_config( $extra );
		$toggle = new SystemReport\Debug_Toggle( $path );
		$state  = $toggle->get_state();

		$this->assertFalse( $state['wp_debug'] );
	}

	/**
	 * Test get_state structure always has expected keys.
	 */
	public function test_get_state_structure(): void {
		$path   = $this->create_temp_config();
		$toggle = new SystemReport\Debug_Toggle( $path );
		$state  = $toggle->get_state();

		$this->assertArrayHasKey( 'wp_debug', $state );
		$this->assertArrayHasKey( 'wp_debug_log', $state );
		$this->assertArrayHasKey( 'wp_debug_display', $state );
		$this->assertArrayHasKey( 'can_modify', $state );
	}

	// ---------------------------------------------------------------
	// enable_debug tests
	// ---------------------------------------------------------------

	/**
	 * Test enable_debug adds constants to a bare config.
	 */
	public function test_enable_debug_adds_constants(): void {
		$path   = $this->create_temp_config();
		$toggle = new SystemReport\Debug_Toggle( $path );

		$result = $toggle->enable_debug();
		$this->assertTrue( $result );

		// Verify state after enabling.
		$state = $toggle->get_state();
		$this->assertTrue( $state['wp_debug'] );
		$this->assertTrue( $state['wp_debug_log'] );
		$this->assertFalse( $state['wp_debug_display'] );
	}

	/**
	 * Test enable_debug updates existing constants.
	 */
	public function test_enable_debug_updates_existing(): void {
		$extra  = "define( 'WP_DEBUG', false );";
		$path   = $this->create_temp_config( $extra );
		$toggle = new SystemReport\Debug_Toggle( $path );

		$result = $toggle->enable_debug();
		$this->assertTrue( $result );

		$state = $toggle->get_state();
		$this->assertTrue( $state['wp_debug'] );
	}

	/**
	 * Test enable_debug creates backup in temp dir and deletes it on success.
	 */
	public function test_enable_debug_backup_lifecycle(): void {
		$path        = $this->create_temp_config();
		$backup_path = get_temp_dir() . 'wp-system-report-config-' . md5( $path ) . '.bak';
		$toggle      = new SystemReport\Debug_Toggle( $path );

		$result = $toggle->enable_debug();
		$this->assertTrue( $result );

		// Backup should be cleaned up after successful operation.
		$this->assertFileDoesNotExist( $backup_path );

		// Old-style backup in webroot should NOT exist.
		$this->assertFileDoesNotExist( $path . '.bak' );
	}

	/**
	 * Test enable_debug returns error for unwritable file.
	 */
	public function test_enable_debug_unwritable_returns_error(): void {
		$path = $this->create_temp_config();
		chmod( $path, 0444 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod

		$toggle = new SystemReport\Debug_Toggle( $path );
		$result = $toggle->enable_debug();

		$this->assertIsString( $result );

		// Restore permission for cleanup.
		chmod( $path, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
	}

	// ---------------------------------------------------------------
	// disable_debug tests
	// ---------------------------------------------------------------

	/**
	 * Test disable_debug sets constants correctly.
	 */
	public function test_disable_debug_sets_constants(): void {
		$extra  = "define( 'WP_DEBUG', true );\n";
		$extra .= "define( 'WP_DEBUG_LOG', true );\n";
		$extra .= "define( 'WP_DEBUG_DISPLAY', false );";

		$path   = $this->create_temp_config( $extra );
		$toggle = new SystemReport\Debug_Toggle( $path );

		$result = $toggle->disable_debug();
		$this->assertTrue( $result );

		$state = $toggle->get_state();
		$this->assertFalse( $state['wp_debug'] );
		$this->assertFalse( $state['wp_debug_log'] );
		$this->assertTrue( $state['wp_debug_display'] );
	}

	/**
	 * Test disable_debug creates backup in temp dir and deletes it on success.
	 */
	public function test_disable_debug_backup_lifecycle(): void {
		$extra       = "define( 'WP_DEBUG', true );";
		$path        = $this->create_temp_config( $extra );
		$backup_path = get_temp_dir() . 'wp-system-report-config-' . md5( $path ) . '.bak';
		$toggle      = new SystemReport\Debug_Toggle( $path );

		$result = $toggle->disable_debug();
		$this->assertTrue( $result );

		// Backup should be cleaned up after successful operation.
		$this->assertFileDoesNotExist( $backup_path );

		// Old-style backup in webroot should NOT exist.
		$this->assertFileDoesNotExist( $path . '.bak' );
	}

	// ---------------------------------------------------------------
	// Round-trip tests
	// ---------------------------------------------------------------

	/**
	 * Test enabling then disabling produces correct state.
	 */
	public function test_enable_then_disable_roundtrip(): void {
		$path   = $this->create_temp_config();
		$toggle = new SystemReport\Debug_Toggle( $path );

		$toggle->enable_debug();
		$state = $toggle->get_state();
		$this->assertTrue( $state['wp_debug'] );

		$toggle->disable_debug();
		$state = $toggle->get_state();
		$this->assertFalse( $state['wp_debug'] );
		$this->assertFalse( $state['wp_debug_log'] );
		$this->assertTrue( $state['wp_debug_display'] );
	}

	/**
	 * Test get_config_path returns the configured path.
	 */
	public function test_get_config_path(): void {
		$path   = $this->create_temp_config();
		$toggle = new SystemReport\Debug_Toggle( $path );

		$this->assertSame( $path, $toggle->get_config_path() );
	}

	/**
	 * Test default config path uses ABSPATH.
	 */
	public function test_default_config_path(): void {
		$toggle = new SystemReport\Debug_Toggle();
		$this->assertSame( ABSPATH . 'wp-config.php', $toggle->get_config_path() );
	}

	// ---------------------------------------------------------------
	// File locking tests
	// ---------------------------------------------------------------

	/**
	 * Test that concurrent toggle operations are rejected via file locking.
	 */
	public function test_concurrent_toggle_is_rejected(): void {
		$path   = $this->create_temp_config();
		$toggle = new SystemReport\Debug_Toggle( $path );

		// Simulate a held lock by acquiring the lock file ourselves.
		$lock_path = get_temp_dir() . 'wp-system-report-config.lock';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $lock_path, 'w' );
		flock( $handle, LOCK_EX | LOCK_NB );

		// Now the toggle should fail because the lock is held.
		$result = $toggle->enable_debug();
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'lock', strtolower( $result ) );

		// Release the lock.
		flock( $handle, LOCK_UN );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		// Now it should succeed.
		$result = $toggle->enable_debug();
		$this->assertTrue( $result );
	}

	// ---------------------------------------------------------------
	// Backup security tests
	// ---------------------------------------------------------------

	/**
	 * Test that backup is never created in the webroot.
	 */
	public function test_backup_not_in_webroot(): void {
		$path   = $this->create_temp_config();
		$toggle = new SystemReport\Debug_Toggle( $path );

		$toggle->enable_debug();

		// The old-style .bak file next to wp-config.php must not exist.
		$this->assertFileDoesNotExist( $path . '.bak' );
	}
}
