<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package SystemReport
 */

// Composer autoloader for test dependencies.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/*
 * Determine the WordPress tests directory.
 *
 * First check for a WP_TESTS_DIR environment variable.
 * Then check for a WP_DEVELOP_DIR environment variable.
 * Finally fall back to a standard path.
 */
if ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	$_tests_dir = getenv( 'WP_TESTS_DIR' );
} elseif ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$_tests_dir = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} else {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/wp-system-report.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

/**
 * Build the versioned transient cache key used by collectors.
 *
 * Mirrors the logic in Abstract_Collector::build_versioned_cache_key()
 * so that tests can interact with the correct transient.
 *
 * @param string $base_key The base cache key (e.g. 'sr_site_health').
 * @return string The versioned cache key.
 */
function sr_versioned_cache_key( string $base_key ): string {
	$version = defined( 'WP_SYSTEM_REPORT_VERSION' ) ? WP_SYSTEM_REPORT_VERSION : '0.0.0';
	return $base_key . '_' . str_replace( '.', '_', $version );
}

// Create plugin tables that normally rely on register_activation_hook()
// or admin_init, neither of which fire during the test bootstrap.
\SystemReport\Report_History::create_table();
