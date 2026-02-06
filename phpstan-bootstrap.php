<?php
/**
 * PHPStan bootstrap file.
 *
 * Defines constants that are normally defined at runtime.
 *
 * @package SystemReport
 */

// Plugin constants.
define( 'SYSTEM_REPORT_VERSION', '1.0.0' );
define( 'SYSTEM_REPORT_FILE', __DIR__ . '/system-report.php' );
define( 'SYSTEM_REPORT_DIR', __DIR__ . '/' );
define( 'SYSTEM_REPORT_URL', 'https://example.com/wp-content/plugins/system-report/' );

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
