<?php
/**
 * Filesystem Permissions collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

use SystemReport\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Collects directory writability and file permission status.
 */
class Filesystem_Permissions extends Abstract_Collector {

	/**
	 * Get the collector ID.
	 */
	public function get_id(): string {
		return 'filesystem_permissions';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Filesystem Permissions', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Directory writability and file permission status.', 'wp-system-report' );
	}

	/**
	 * Get the collector priority.
	 */
	public function get_priority(): int {
		return 110;
	}

	/**
	 * Collect the data.
	 */
	public function collect(): array {
		$fields = array();

		// Check WordPress root directory.
		$wp_root_path     = ABSPATH;
		$wp_root_writable = wp_is_writable( $wp_root_path );
		$fields[]         = $this->make_field(
			__( 'WordPress Root', 'wp-system-report' ),
			$wp_root_writable ? __( 'Writable', 'wp-system-report' ) : __( 'Not Writable', 'wp-system-report' ),
			array(
				'debug'       => $wp_root_path,
				'status'      => Status::Info,
				'description' => $wp_root_path,
			)
		);

		/*
		 * Check whether the web root is world-writable (permission mode 0o002 set).
		 * A world-writable web root allows any system user to write files into the
		 * WordPress installation directory, which is a serious security risk. Even
		 * if the web server process cannot exploit this directly, it can indicate
		 * dangerously permissive server configuration.
		 *
		 * fileperms() returns the full mode integer (e.g. 0o40755). Masking with
		 * 0002 isolates the "others write" bit.
		 *
		 * Some hosts restrict stat operations on certain paths, which can cause
		 * fileperms() to emit warnings. Guard with is_readable() to avoid noisy logs.
		 */
		if ( is_readable( $wp_root_path ) ) {
			$wp_root_perms = fileperms( $wp_root_path );
			if ( false !== $wp_root_perms && ( $wp_root_perms & 0002 ) ) {
				$fields[] = $this->make_field(
					__( 'WordPress Root Permissions', 'wp-system-report' ),
					__( 'World-writable', 'wp-system-report' ),
					array(
						'debug'       => decoct( $wp_root_perms & 0777 ),
						'status'      => Status::Critical,
						'description' => __( 'The WordPress root directory is world-writable (others have write permission). This is a serious security risk and should be corrected immediately.', 'wp-system-report' ),
						'recommended' => sprintf(
						/* translators: %s: current permission mode (e.g. 0777) */
							__( 'Remove world-write (others write) permission appropriate for your server\'s owner/group configuration. Current permissions: %s', 'wp-system-report' ),
							decoct( $wp_root_perms & 0777 )
						),
					)
				);
			}
		}

		// Check wp-content directory.
		$content_dir_path     = WP_CONTENT_DIR;
		$content_dir_writable = wp_is_writable( $content_dir_path );
		$fields[]             = $this->make_field(
			__( 'wp-content Directory', 'wp-system-report' ),
			$content_dir_writable ? __( 'Writable', 'wp-system-report' ) : __( 'Not Writable', 'wp-system-report' ),
			array(
				'debug'       => $content_dir_path,
				'status'      => Status::Info,
				'description' => $content_dir_path,
			)
		);

		// Check uploads directory.
		$upload_dir       = wp_upload_dir();
		$uploads_path     = $upload_dir['basedir'];
		$uploads_writable = wp_is_writable( $uploads_path );
		$fields[]         = $this->make_field(
			__( 'Uploads Directory', 'wp-system-report' ),
			$uploads_writable ? __( 'Writable', 'wp-system-report' ) : __( 'Not Writable', 'wp-system-report' ),
			array(
				'debug'       => $uploads_path,
				'status'      => $uploads_writable ? Status::Good : Status::Critical,
				'description' => $uploads_path,
			)
		);

		// Check plugins directory.
		$plugins_path     = WP_PLUGIN_DIR;
		$plugins_writable = wp_is_writable( $plugins_path );
		$fields[]         = $this->make_field(
			__( 'Plugins Directory', 'wp-system-report' ),
			$plugins_writable ? __( 'Writable', 'wp-system-report' ) : __( 'Not Writable', 'wp-system-report' ),
			array(
				'debug'       => $plugins_path,
				'status'      => Status::Info,
				'description' => $plugins_path,
			)
		);

		// Check themes directory.
		$themes_path     = get_theme_root();
		$themes_writable = wp_is_writable( $themes_path );
		$fields[]        = $this->make_field(
			__( 'Themes Directory', 'wp-system-report' ),
			$themes_writable ? __( 'Writable', 'wp-system-report' ) : __( 'Not Writable', 'wp-system-report' ),
			array(
				'debug'       => $themes_path,
				'status'      => Status::Info,
				'description' => $themes_path,
			)
		);

		// Check MU plugins directory if defined.
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$mu_plugins_path     = WPMU_PLUGIN_DIR;
			$mu_plugins_writable = wp_is_writable( $mu_plugins_path );
			$fields[]            = $this->make_field(
				__( 'MU Plugins Directory', 'wp-system-report' ),
				$mu_plugins_writable ? __( 'Writable', 'wp-system-report' ) : __( 'Not Writable', 'wp-system-report' ),
				array(
					'debug'       => $mu_plugins_path,
					'status'      => Status::Info,
					'description' => $mu_plugins_path,
				)
			);
		}

		return $fields;
	}
}
