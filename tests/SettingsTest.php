<?php
/**
 * Settings tests.
 *
 * @package SystemReport
 */

/**
 * Test the Settings class.
 */
class SettingsTest extends WP_UnitTestCase {

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		parent::tear_down();
		delete_option( SystemReport\Settings::OPTION_NAME );
	}

	/**
	 * Test default value is returned when option does not exist.
	 */
	public function test_get_returns_default_when_option_missing(): void {
		$this->assertSame( 100, SystemReport\Settings::get( 'error_log_lines' ) );
	}

	/**
	 * Test custom default is returned for unknown key.
	 */
	public function test_get_returns_custom_default_for_unknown_key(): void {
		$this->assertSame( 'fallback', SystemReport\Settings::get( 'nonexistent', 'fallback' ) );
	}

	/**
	 * Test null is returned for unknown key without default.
	 */
	public function test_get_returns_null_for_unknown_key_without_default(): void {
		$this->assertNull( SystemReport\Settings::get( 'nonexistent' ) );
	}

	/**
	 * Test update stores value.
	 */
	public function test_update_stores_value(): void {
		SystemReport\Settings::update( 'error_log_lines', 250 );
		$this->assertSame( 250, SystemReport\Settings::get( 'error_log_lines' ) );
	}

	/**
	 * Test update returns true on success.
	 */
	public function test_update_returns_true(): void {
		$result = SystemReport\Settings::update( 'error_log_lines', 500 );
		$this->assertTrue( $result );
	}

	/**
	 * Test get_all returns merged defaults.
	 */
	public function test_get_all_returns_defaults(): void {
		$all = SystemReport\Settings::get_all();
		$this->assertArrayHasKey( 'error_log_lines', $all );
		$this->assertSame( 100, $all['error_log_lines'] );
	}

	/**
	 * Test get_all reflects stored values.
	 */
	public function test_get_all_reflects_stored_values(): void {
		SystemReport\Settings::update( 'error_log_lines', 750 );
		$all = SystemReport\Settings::get_all();
		$this->assertSame( 750, $all['error_log_lines'] );
	}

	/**
	 * Test delete removes option.
	 */
	public function test_delete_removes_option(): void {
		SystemReport\Settings::update( 'error_log_lines', 200 );
		SystemReport\Settings::delete();
		// Should fall back to default after delete.
		$this->assertSame( 100, SystemReport\Settings::get( 'error_log_lines' ) );
	}

	/**
	 * Test sanitize clamps error_log_lines minimum.
	 */
	public function test_sanitize_clamps_minimum(): void {
		$this->assertSame( 1, SystemReport\Settings::sanitize( 'error_log_lines', 0 ) );
		// absint(-5) = 5, so negative values become their absolute value before clamping.
		$this->assertSame( 5, SystemReport\Settings::sanitize( 'error_log_lines', -5 ) );
	}

	/**
	 * Test sanitize clamps error_log_lines maximum.
	 */
	public function test_sanitize_clamps_maximum(): void {
		$this->assertSame( 10000, SystemReport\Settings::sanitize( 'error_log_lines', 50000 ) );
		$this->assertSame( 10000, SystemReport\Settings::sanitize( 'error_log_lines', 10001 ) );
	}

	/**
	 * Test sanitize accepts valid values.
	 */
	public function test_sanitize_accepts_valid_values(): void {
		$this->assertSame( 1, SystemReport\Settings::sanitize( 'error_log_lines', 1 ) );
		$this->assertSame( 500, SystemReport\Settings::sanitize( 'error_log_lines', 500 ) );
		$this->assertSame( 10000, SystemReport\Settings::sanitize( 'error_log_lines', 10000 ) );
	}

	/**
	 * Test sanitize converts string to integer.
	 */
	public function test_sanitize_converts_string(): void {
		$this->assertSame( 200, SystemReport\Settings::sanitize( 'error_log_lines', '200' ) );
	}

	/**
	 * Test update applies sanitization.
	 */
	public function test_update_applies_sanitization(): void {
		SystemReport\Settings::update( 'error_log_lines', 99999 );
		$this->assertSame( 10000, SystemReport\Settings::get( 'error_log_lines' ) );
	}

	/**
	 * Test get_default returns known defaults.
	 */
	public function test_get_default_returns_known_default(): void {
		$this->assertSame( 100, SystemReport\Settings::get_default( 'error_log_lines' ) );
	}

	/**
	 * Test get_default returns null for unknown key.
	 */
	public function test_get_default_returns_null_for_unknown(): void {
		$this->assertNull( SystemReport\Settings::get_default( 'nonexistent' ) );
	}

	/**
	 * Test get handles corrupted option gracefully.
	 */
	public function test_get_handles_corrupted_option(): void {
		update_option( SystemReport\Settings::OPTION_NAME, 'not_an_array' );
		$this->assertSame( 100, SystemReport\Settings::get( 'error_log_lines' ) );
	}

	/**
	 * Test get_all handles corrupted option gracefully.
	 */
	public function test_get_all_handles_corrupted_option(): void {
		update_option( SystemReport\Settings::OPTION_NAME, 'not_an_array' );
		$all = SystemReport\Settings::get_all();
		$this->assertSame( 100, $all['error_log_lines'] );
	}

	/**
	 * Test update handles corrupted option gracefully.
	 */
	public function test_update_handles_corrupted_option(): void {
		update_option( SystemReport\Settings::OPTION_NAME, 'not_an_array' );
		SystemReport\Settings::update( 'error_log_lines', 300 );
		$this->assertSame( 300, SystemReport\Settings::get( 'error_log_lines' ) );
	}
}
