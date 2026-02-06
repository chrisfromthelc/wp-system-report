<?php
/**
 * Plugin Name: System Report
 * Plugin URI:  https://github.com/chrisfromthelc/wp-system-report
 * Description: Comprehensive WordPress system status report with AI-optimized export.
 * Version:     1.0.0
 * Author:      Christopher Smith
 * Author URI:  https://github.com/chrisfromthelc
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: system-report
 * Requires at least: 6.2
 * Requires PHP: 7.4
 *
 * @package SystemReport
 */

defined( 'ABSPATH' ) || exit;

define( 'SYSTEM_REPORT_VERSION', '1.0.0' );
define( 'SYSTEM_REPORT_FILE', __FILE__ );
define( 'SYSTEM_REPORT_DIR', plugin_dir_path( __FILE__ ) );
define( 'SYSTEM_REPORT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader for SystemReport classes.
 *
 * Maps SystemReport namespace to the includes/ directory using WordPress
 * file naming conventions (class-{name}.php, interface-{name}.php).
 *
 * @param string $class_name The fully qualified class name.
 */
function system_report_autoloader( $class_name ) {
	$namespace = 'SystemReport\\';

	if ( 0 !== strpos( $class_name, $namespace ) ) {
		return;
	}

	$relative_class = substr( $class_name, strlen( $namespace ) );
	$parts          = explode( '\\', $relative_class );
	$class_file     = array_pop( $parts );

	// Build directory path from namespace parts.
	$path = SYSTEM_REPORT_DIR . 'includes/';
	foreach ( $parts as $part ) {
		$path .= strtolower( str_replace( '_', '-', $part ) ) . '/';
	}

	// Determine file prefix based on type.
	$reflection_name = strtolower( str_replace( '_', '-', $class_file ) );

	// Check for interface first, then class.
	$interface_file = $path . 'interface-' . $reflection_name . '.php';
	$class_file     = $path . 'class-' . $reflection_name . '.php';

	if ( file_exists( $interface_file ) ) {
		require_once $interface_file;
	} elseif ( file_exists( $class_file ) ) {
		require_once $class_file;
	}
}
spl_autoload_register( 'system_report_autoloader' );

/**
 * Get the main plugin instance.
 *
 * @return \SystemReport\Plugin
 */
function system_report() {
	return \SystemReport\Plugin::get_instance();
}

// Initialize the plugin.
system_report();
