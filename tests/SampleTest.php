<?php
/**
 * Sample test case.
 *
 * @package SystemReport
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/**
 * Sample test class to verify the test framework is working.
 */
class SampleTest extends WP_UnitTestCase {

	/**
	 * Verify the plugin is loaded.
	 */
	public function test_plugin_loaded(): void {
		$this->assertTrue( defined( 'WP_SYSTEM_REPORT_VERSION' ) );
	}

	/**
	 * Verify the plugin singleton.
	 */
	public function test_plugin_singleton(): void {
		$instance = SystemReport\Plugin::get_instance();
		$this->assertInstanceOf( SystemReport\Plugin::class, $instance );
	}
}
