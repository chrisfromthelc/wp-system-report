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
$wp_system_report_transients = array(
	'sr_active_plugins',
	'sr_inactive_plugins',
	'sr_dropins_mu_plugins',
	'sr_theme_info',
	'sr_site_health',
	'sr_github_update',
	'sr_github_update_failed',
	'sr_database',
	'sr_post_type_counts',
	'sr_advanced_diagnostics',
);

if ( is_multisite() ) {
	$wp_system_report_sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $wp_system_report_sites as $wp_system_report_site_id ) {
		switch_to_blog( $wp_system_report_site_id );
		foreach ( $wp_system_report_transients as $wp_system_report_transient ) {
			delete_transient( $wp_system_report_transient );
		}
		restore_current_blog();
	}
} else {
	foreach ( $wp_system_report_transients as $wp_system_report_transient ) {
		delete_transient( $wp_system_report_transient );
	}
}
