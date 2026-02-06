<?php
/**
 * Sample test case.
 *
 * @package SystemReport
 */

/**
 * Sample test class to verify the test framework is working.
 */
class Test_Sample extends WP_UnitTestCase {

	/**
	 * Verify the plugin is loaded.
	 */
	public function test_plugin_loaded() {
		$this->assertTrue( defined( 'SYSTEM_REPORT_VERSION' ) );
	}
}
