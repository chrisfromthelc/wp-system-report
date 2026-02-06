<?php
/**
 * Filesystem Permissions collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects directory writability and file permission status.
 */
class Filesystem_Permissions extends Abstract_Collector {

	/**
	 * Get the collector ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'filesystem_permissions';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Filesystem Permissions', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Directory writability and file permission status.', 'system-report' );
	}

	/**
	 * Get the collector priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 110;
	}

	/**
	 * Collect the data.
	 *
	 * @return array
	 */
	public function collect() {
		$fields = array();

		// Check WordPress root directory.
		$wp_root_path     = ABSPATH;
		$wp_root_writable = wp_is_writable( $wp_root_path );
		$fields[]         = $this->make_field(
			__( 'WordPress Root', 'system-report' ),
			$wp_root_writable ? __( 'Writable', 'system-report' ) : __( 'Not Writable', 'system-report' ),
			array(
				'debug'       => $wp_root_path,
				'status'      => 'info',
				'description' => $wp_root_path,
			)
		);

		// Check wp-content directory.
		$content_dir_path     = WP_CONTENT_DIR;
		$content_dir_writable = wp_is_writable( $content_dir_path );
		$fields[]             = $this->make_field(
			__( 'wp-content Directory', 'system-report' ),
			$content_dir_writable ? __( 'Writable', 'system-report' ) : __( 'Not Writable', 'system-report' ),
			array(
				'debug'       => $content_dir_path,
				'status'      => 'info',
				'description' => $content_dir_path,
			)
		);

		// Check uploads directory.
		$upload_dir       = wp_upload_dir();
		$uploads_path     = $upload_dir['basedir'];
		$uploads_writable = wp_is_writable( $uploads_path );
		$fields[]         = $this->make_field(
			__( 'Uploads Directory', 'system-report' ),
			$uploads_writable ? __( 'Writable', 'system-report' ) : __( 'Not Writable', 'system-report' ),
			array(
				'debug'       => $uploads_path,
				'status'      => $uploads_writable ? 'good' : 'critical',
				'description' => $uploads_path,
			)
		);

		// Check plugins directory.
		$plugins_path     = WP_PLUGIN_DIR;
		$plugins_writable = wp_is_writable( $plugins_path );
		$fields[]         = $this->make_field(
			__( 'Plugins Directory', 'system-report' ),
			$plugins_writable ? __( 'Writable', 'system-report' ) : __( 'Not Writable', 'system-report' ),
			array(
				'debug'       => $plugins_path,
				'status'      => 'info',
				'description' => $plugins_path,
			)
		);

		// Check themes directory.
		$themes_path     = get_theme_root();
		$themes_writable = wp_is_writable( $themes_path );
		$fields[]        = $this->make_field(
			__( 'Themes Directory', 'system-report' ),
			$themes_writable ? __( 'Writable', 'system-report' ) : __( 'Not Writable', 'system-report' ),
			array(
				'debug'       => $themes_path,
				'status'      => 'info',
				'description' => $themes_path,
			)
		);

		// Check MU plugins directory if defined.
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$mu_plugins_path     = WPMU_PLUGIN_DIR;
			$mu_plugins_writable = wp_is_writable( $mu_plugins_path );
			$fields[]            = $this->make_field(
				__( 'MU Plugins Directory', 'system-report' ),
				$mu_plugins_writable ? __( 'Writable', 'system-report' ) : __( 'Not Writable', 'system-report' ),
				array(
					'debug'       => $mu_plugins_path,
					'status'      => 'info',
					'description' => $mu_plugins_path,
				)
			);
		}

		return $fields;
	}
}
