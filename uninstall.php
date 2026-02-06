<?php
/**
 * Uninstall handler.
 *
 * Cleans up all transients and options when the plugin is deleted.
 *
 * @package SystemReport
 */

// Exit if not called by WordPress uninstaller.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all plugin transients.
$transients = array(
	'sr_active_plugins',
	'sr_inactive_plugins',
	'sr_dropins_mu_plugins',
	'sr_theme_info',
	'sr_site_health',
);

foreach ( $transients as $transient ) {
	delete_transient( $transient );
}
