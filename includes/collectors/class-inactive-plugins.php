<?php
/**
 * Inactive Plugins collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects installed but inactive plugins.
 */
class Inactive_Plugins extends Abstract_Collector {

	/**
	 * Get the collector ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'inactive_plugins';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Inactive Plugins', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Installed but inactive plugins.', 'system-report' );
	}

	/**
	 * Get the collector priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 70;
	}

	/**
	 * Get the transient cache key.
	 *
	 * @return string
	 */
	protected function get_cache_key() {
		return 'sr_inactive_plugins';
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

		// Filter out active plugins to get inactive ones.
		$inactive_plugins = array_diff_key( $all_plugins, array_flip( $active_plugins ) );

		// Sort inactive plugins alphabetically by name.
		uasort(
			$inactive_plugins,
			function ( $a, $b ) {
				return strcmp( $a['Name'], $b['Name'] );
			}
		);

		// Build fields for each inactive plugin.
		foreach ( $inactive_plugins as $plugin_path => $plugin_data ) {
			$version     = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : __( 'Unknown', 'system-report' );
			$author      = ! empty( $plugin_data['Author'] ) ? wp_strip_all_tags( $plugin_data['Author'] ) : __( 'Unknown', 'system-report' );
			$plugin_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : basename( $plugin_path, '.php' );

			// Build value string.
			$value = sprintf(
				/* translators: 1: Plugin author, 2: Plugin version */
				__( 'by %1$s - version %2$s', 'system-report' ),
				$author,
				$version
			);

			$description = ! empty( $plugin_data['PluginURI'] ) ? $plugin_data['PluginURI'] : '';

			$fields[] = $this->make_field(
				$plugin_name,
				$value,
				array(
					'export_label' => $plugin_name,
					'description'  => $description,
					'status'       => 'info',
				)
			);
		}

		// If no inactive plugins found.
		if ( empty( $fields ) ) {
			$fields[] = $this->make_field(
				__( 'No Inactive Plugins', 'system-report' ),
				__( 'All installed plugins are active.', 'system-report' ),
				array( 'status' => 'info' )
			);
		}

		return $fields;
	}
}
