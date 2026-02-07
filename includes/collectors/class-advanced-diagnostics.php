<?php
/**
 * Advanced Diagnostics collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects autoloaded options, disk usage, and error log information.
 */
class Advanced_Diagnostics extends Abstract_Collector {

	/**
 * Get the collector ID.
 */
	public function get_id(): string {
		return 'advanced_diagnostics';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Advanced Diagnostics', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Autoloaded options, disk usage, and error log information.', 'wp-system-report' );
	}

	/**
 * Get the collector priority.
 */
	public function get_priority(): int {
		return 170;
	}

	/**
 * Collect the data.
 */
	public function collect(): array {
		global $wpdb;

		$fields = array();

		// Autoloaded Options Count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$autoload_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload IN ('yes', 'on')"
		);

		$fields[] = $this->make_field(
			__( 'Autoloaded Options Count', 'wp-system-report' ),
			$autoload_count ? absint( $autoload_count ) : 0
		);

		// Autoloaded Options Size.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$autoload_size = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes', 'on')"
		);

		$autoload_size = $autoload_size ? absint( $autoload_size ) : 0;
		$status        = 'good';
		$recommended   = '';

		if ( $autoload_size > 1572864 ) { // 1.5 MB.
			$status      = 'critical';
			$recommended = __( '< 800 KB', 'wp-system-report' );
		} elseif ( $autoload_size > 819200 ) { // 800 KB.
			$status      = 'warning';
			$recommended = __( '< 800 KB', 'wp-system-report' );
		}

		$fields[] = $this->make_field(
			__( 'Autoloaded Options Size', 'wp-system-report' ),
			$this->format_size( $autoload_size ),
			array(
				'status'      => $status,
				'recommended' => $recommended,
			)
		);

		// Uploads Directory Size.
		$upload_dir      = wp_upload_dir();
		$uploads_dirsize = 0;

		if ( ! empty( $upload_dir['basedir'] ) && is_dir( $upload_dir['basedir'] ) ) {
			// Try get_dirsize() first (available in WP 6.5+) with fallback to recurse_dirsize().
			if ( function_exists( 'get_dirsize' ) ) {
				$uploads_dirsize = get_dirsize( $upload_dir['basedir'] );
			} elseif ( function_exists( 'recurse_dirsize' ) ) {
				$uploads_dirsize = recurse_dirsize( $upload_dir['basedir'] );
			}
		}

		$fields[] = $this->make_field(
			__( 'Uploads Directory Size', 'wp-system-report' ),
			$uploads_dirsize ? $this->format_size( $uploads_dirsize ) : __( 'Unable to calculate', 'wp-system-report' )
		);

		// Plugins Directory Size.
		$plugins_dirsize = 0;

		if ( defined( 'WP_PLUGIN_DIR' ) && is_dir( WP_PLUGIN_DIR ) ) {
			if ( function_exists( 'get_dirsize' ) ) {
				$plugins_dirsize = get_dirsize( WP_PLUGIN_DIR );
			} elseif ( function_exists( 'recurse_dirsize' ) ) {
				$plugins_dirsize = recurse_dirsize( WP_PLUGIN_DIR );
			}
		}

		$fields[] = $this->make_field(
			__( 'Plugins Directory Size', 'wp-system-report' ),
			$plugins_dirsize ? $this->format_size( $plugins_dirsize ) : __( 'Unable to calculate', 'wp-system-report' )
		);

		// Themes Directory Size.
		$themes_root    = get_theme_root();
		$themes_dirsize = 0;

		if ( ! empty( $themes_root ) && is_dir( $themes_root ) ) {
			if ( function_exists( 'get_dirsize' ) ) {
				$themes_dirsize = get_dirsize( $themes_root );
			} elseif ( function_exists( 'recurse_dirsize' ) ) {
				$themes_dirsize = recurse_dirsize( $themes_root );
			}
		}

		$fields[] = $this->make_field(
			__( 'Themes Directory Size', 'wp-system-report' ),
			$themes_dirsize ? $this->format_size( $themes_dirsize ) : __( 'Unable to calculate', 'wp-system-report' )
		);

		// Rewrite Rules Count.
		$rewrite_rules = get_option( 'rewrite_rules' );
		$rules_count   = is_array( $rewrite_rules ) ? count( $rewrite_rules ) : 0;

		$fields[] = $this->make_field(
			__( 'Rewrite Rules Count', 'wp-system-report' ),
			$rules_count,
			array(
				'status' => $rules_count > 500 ? 'warning' : 'good',
			)
		);

		// PHP Error Log.
		$error_log = ini_get( 'error_log' );

		if ( $error_log && file_exists( $error_log ) && is_readable( $error_log ) ) {
			$error_log_size = filesize( $error_log );
			$log_value      = sprintf(
				/* translators: 1: file path, 2: file size */
				__( '%1$s (%2$s)', 'wp-system-report' ),
				$error_log,
				$this->format_size( $error_log_size )
			);

			// Try to read last 5 lines if file has content.
			if ( $error_log_size > 0 ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$lines = file( $error_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

				if ( is_array( $lines ) && ! empty( $lines ) ) {
					$last_lines = array_slice( $lines, -5 );
					$log_value  = sprintf(
						/* translators: 1: file path, 2: file size, 3: last log lines */
						__( '%1$s (%2$s) - Last entries: %3$s', 'wp-system-report' ),
						$error_log,
						$this->format_size( $error_log_size ),
						implode( ' | ', $last_lines )
					);
				}
			}

			$fields[] = $this->make_field(
				__( 'PHP Error Log', 'wp-system-report' ),
				$log_value
			);
		} else {
			$fields[] = $this->make_field(
				__( 'PHP Error Log', 'wp-system-report' ),
				$error_log ? __( 'Not accessible', 'wp-system-report' ) : __( 'Not configured', 'wp-system-report' )
			);
		}

		// .htaccess Present.
		$htaccess_exists = file_exists( ABSPATH . '.htaccess' );

		$fields[] = $this->make_field(
			__( '.htaccess Present', 'wp-system-report' ),
			$this->format_boolean( $htaccess_exists )
		);

		return $fields;
	}
}
