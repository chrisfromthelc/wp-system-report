<?php
/**
 * PHPStan bootstrap file.
 *
 * Defines constants that are normally defined at runtime.
 *
 * @package SystemReport
 */

// Plugin constants.
define( 'WP_SYSTEM_REPORT_VERSION', '1.2.0' );
define( 'WP_SYSTEM_REPORT_FILE', __DIR__ . '/wp-system-report.php' );
define( 'WP_SYSTEM_REPORT_DIR', __DIR__ . '/' );
define( 'WP_SYSTEM_REPORT_URL', 'https://example.com/wp-content/plugins/wp-system-report/' );

// WordPress database constants.
if ( ! defined( 'DB_NAME' ) ) {
	define( 'DB_NAME', 'wordpress' );
}
if ( ! defined( 'DB_CHARSET' ) ) {
	define( 'DB_CHARSET', 'utf8mb4' );
}
if ( ! defined( 'DB_COLLATE' ) ) {
	define( 'DB_COLLATE', '' );
}
