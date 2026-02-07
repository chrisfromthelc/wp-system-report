<?php
/**
 * Drop-ins & Must-Use Plugins collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects drop-in replacements and must-use plugins.
 */
class Dropins_MU_Plugins extends Abstract_Collector {

	/**
 * Get the collector ID.
 */
	public function get_id(): string {
		return 'dropins_mu_plugins';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Drop-ins & Must-Use Plugins', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Drop-in replacements and must-use plugins.', 'wp-system-report' );
	}

	/**
 * Get the collector priority.
 */
	public function get_priority(): int {
		return 80;
	}

	/**
 * Get the transient cache key.
 */
	protected function get_cache_key(): string {
		return 'sr_dropins_mu_plugins';
	}

	/**
 * Collect the data.
 */
	public function collect(): array {
		// Require plugin functions if not available.
		if ( ! function_exists( 'get_dropins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$fields = array();

		// Collect drop-ins.
		$dropins = get_dropins();
		if ( ! empty( $dropins ) ) {
			// Sort drop-ins by filename.
			ksort( $dropins );

			foreach ( $dropins as $dropin_file => $dropin_data ) {
				$dropin_name = ! empty( $dropin_data['Name'] ) ? $dropin_data['Name'] : $dropin_file;
				$description = ! empty( $dropin_data['Description'] ) ? $dropin_data['Description'] : '';

				$fields[] = $this->make_field(
					sprintf(
						/* translators: %s: Drop-in filename */
						__( 'Drop-in: %s', 'wp-system-report' ),
						$dropin_file
					),
					$dropin_name,
					array(
						'export_label' => $dropin_file,
						'description'  => $description,
						'status'       => 'info',
					)
				);
			}
		}

		// Collect must-use plugins.
		$mu_plugins = get_mu_plugins();
		if ( ! empty( $mu_plugins ) ) {
			// Sort MU plugins by name.
			uasort(
				$mu_plugins,
				function ( array $a, array $b ): int {
					return strcmp( $a['Name'], $b['Name'] );
				}
			);

			foreach ( $mu_plugins as $mu_plugin_path => $mu_plugin_data ) {
				$plugin_name = ! empty( $mu_plugin_data['Name'] ) ? $mu_plugin_data['Name'] : basename( $mu_plugin_path, '.php' );
				$version     = ! empty( $mu_plugin_data['Version'] ) ? $mu_plugin_data['Version'] : __( 'Unknown', 'wp-system-report' );
				$author      = ! empty( $mu_plugin_data['Author'] ) ? wp_strip_all_tags( $mu_plugin_data['Author'] ) : __( 'Unknown', 'wp-system-report' );

				// Build value string.
				$value = sprintf(
					/* translators: 1: Plugin author, 2: Plugin version */
					__( 'by %1$s - version %2$s', 'wp-system-report' ),
					$author,
					$version
				);

				$description = ! empty( $mu_plugin_data['PluginURI'] ) ? $mu_plugin_data['PluginURI'] : '';

				$fields[] = $this->make_field(
					sprintf(
						/* translators: %s: MU Plugin name */
						__( 'MU Plugin: %s', 'wp-system-report' ),
						$plugin_name
					),
					$value,
					array(
						'export_label' => $plugin_name,
						'description'  => $description,
						'status'       => 'info',
					)
				);
			}
		}

		// If no drop-ins or MU plugins found.
		if ( empty( $fields ) ) {
			$fields[] = $this->make_field(
				__( 'No Drop-ins or MU Plugins', 'wp-system-report' ),
				__( 'No drop-ins or must-use plugins installed.', 'wp-system-report' ),
				array( 'status' => 'info' )
			);
		}

		return $fields;
	}
}
