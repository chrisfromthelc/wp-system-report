<?php
/**
 * Active Plugins collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects currently active plugins with version and update information.
 */
class Active_Plugins extends Abstract_Collector {

	/**
	 * Get the collector ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'active_plugins';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Active Plugins', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Currently active plugins with version and update information.', 'system-report' );
	}

	/**
	 * Get the collector priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 60;
	}

	/**
	 * Get the transient cache key.
	 *
	 * @return string
	 */
	protected function get_cache_key() {
		return 'sr_active_plugins';
	}

	/**
	 * Collect the data.
	 *
	 * @return array
	 */
	public function collect() {
		// Require plugin functions if not available.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$fields = array();

		// Get active plugins.
		$active_plugins = get_option( 'active_plugins', array() );

		// For multisite, also get network active plugins.
		if ( is_multisite() ) {
			$network_active = get_site_option( 'active_sitewide_plugins', array() );
			// Network active plugins are stored as array( plugin_path => timestamp ).
			$active_plugins = array_merge( $active_plugins, array_keys( $network_active ) );
			$active_plugins = array_unique( $active_plugins );
		}

		// Get all installed plugins.
		$all_plugins = get_plugins();

		// Get available updates.
		$plugin_updates = get_plugin_updates();

		// Sort active plugins alphabetically by name.
		$active_plugin_data = array();
		foreach ( $active_plugins as $plugin_path ) {
			if ( isset( $all_plugins[ $plugin_path ] ) ) {
				$active_plugin_data[ $plugin_path ] = $all_plugins[ $plugin_path ];
			}
		}
		uasort( $active_plugin_data, function( $a, $b ) {
			return strcmp( $a['Name'], $b['Name'] );
		});

		// Build fields for each active plugin.
		foreach ( $active_plugin_data as $plugin_path => $plugin_data ) {
			$version     = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : __( 'Unknown', 'system-report' );
			$author      = ! empty( $plugin_data['Author'] ) ? strip_tags( $plugin_data['Author'] ) : __( 'Unknown', 'system-report' );
			$plugin_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : basename( $plugin_path, '.php' );

			// Build value string.
			$value = sprintf(
				/* translators: 1: Plugin author, 2: Plugin version */
				__( 'by %1$s - version %2$s', 'system-report' ),
				$author,
				$version
			);

			$status      = 'info';
			$description = ! empty( $plugin_data['PluginURI'] ) ? $plugin_data['PluginURI'] : '';

			// Check for available updates.
			if ( isset( $plugin_updates[ $plugin_path ] ) ) {
				$update_info = $plugin_updates[ $plugin_path ];
				if ( isset( $update_info->update->new_version ) ) {
					$new_version = $update_info->update->new_version;
					$value      .= sprintf(
						/* translators: %s: New version number */
						__( ' (update available: %s)', 'system-report' ),
						$new_version
					);
					$status = 'warning';
				}
			}

			$fields[] = $this->make_field(
				$plugin_name,
				$value,
				array(
					'export_label' => $plugin_name,
					'description'  => $description,
					'status'       => $status,
				)
			);
		}

		// If no active plugins found.
		if ( empty( $fields ) ) {
			$fields[] = $this->make_field(
				__( 'No Active Plugins', 'system-report' ),
				__( 'No active plugins installed.', 'system-report' ),
				array( 'status' => 'info' )
			);
		}

		return $fields;
	}
}
