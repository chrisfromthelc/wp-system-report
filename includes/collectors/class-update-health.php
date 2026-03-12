<?php
/**
 * Update Health collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

use SystemReport\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Collects WordPress core, plugin, and theme update status,
 * auto-update settings, and failed update history.
 */
class Update_Health extends Abstract_Collector {

	/**
	 * Get the transient cache key.
	 *
	 * @return string Cache key.
	 */
	protected function get_cache_key(): string {
		return 'sr_update_health';
	}

	/**
	 * Get the collector ID.
	 */
	public function get_id(): string {
		return 'update_health';
	}

	/**
	 * Get the collector label.
	 */
	public function get_label(): string {
		return __( 'Update Health', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 */
	public function get_description(): string {
		return __( 'WordPress core, plugin, and theme update status, auto-update settings, and failed update history.', 'wp-system-report' );
	}

	/**
	 * Get the collector priority.
	 */
	public function get_priority(): int {
		return 210;
	}

	/**
	 * Collect update health data.
	 *
	 * @return array Array of Field objects.
	 */
	public function collect(): array {
		$data = array();

		$data[] = $this->collect_core_update_status();
		$data[] = $this->collect_core_update_channel();
		$data[] = $this->collect_core_auto_updates();
		$data[] = $this->collect_plugin_updates_available();
		$data[] = $this->collect_plugin_auto_updates();
		$data[] = $this->collect_theme_updates_available();
		$data[] = $this->collect_theme_auto_updates();
		$data[] = $this->collect_last_update_check();
		$data[] = $this->collect_failed_updates();
		$data[] = $this->collect_translation_updates();

		return $data;
	}

	/**
	 * Collect WordPress core update status.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_core_update_status() {
		$updates = get_core_updates();

		if ( false === $updates ) {
			return $this->make_field(
				__( 'Core Update Status', 'wp-system-report' ),
				__( 'Unable to check', 'wp-system-report' ),
				array(
					'status'      => Status::Warning,
					'description' => __( 'Could not retrieve WordPress core update information.', 'wp-system-report' ),
				)
			);
		}

		if ( empty( $updates ) ) {
			return $this->make_field(
				__( 'Core Update Status', 'wp-system-report' ),
				__( 'Up to date', 'wp-system-report' ),
				array(
					'status'      => Status::Good,
					'description' => __( 'WordPress core is running the latest version.', 'wp-system-report' ),
				)
			);
		}

		$latest = $updates[0];

		if ( isset( $latest->response ) && 'latest' === $latest->response ) {
			return $this->make_field(
				__( 'Core Update Status', 'wp-system-report' ),
				/* translators: %s: WordPress version */
				sprintf( __( 'Up to date (%s)', 'wp-system-report' ), get_bloginfo( 'version' ) ),
				array(
					'status'      => Status::Good,
					'description' => __( 'WordPress core is running the latest version.', 'wp-system-report' ),
				)
			);
		}

		$version     = $latest->current ?? __( 'Unknown', 'wp-system-report' );
		$is_security = $this->is_security_update( $latest );

		return $this->make_field(
			__( 'Core Update Status', 'wp-system-report' ),
			/* translators: %s: available version */
			sprintf( __( 'Update available: %s', 'wp-system-report' ), $version ),
			array(
				'status'      => $is_security ? Status::Critical : Status::Warning,
				'description' => $is_security
					? __( 'A security update is available for WordPress core.', 'wp-system-report' )
					: __( 'A new version of WordPress core is available.', 'wp-system-report' ),
				'recommended' => $version,
			)
		);
	}

	/**
	 * Collect WordPress update channel (stable, beta, nightly).
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_core_update_channel() {
		$version = get_bloginfo( 'version' );

		$channel = __( 'Stable', 'wp-system-report' );
		$status  = Status::Good;

		if ( str_contains( $version, 'beta' ) || str_contains( $version, 'RC' ) ) {
			$channel = __( 'Beta/RC', 'wp-system-report' );
			$status  = Status::Warning;
		} elseif ( str_contains( $version, 'alpha' ) || str_contains( $version, '-src' ) ) {
			$channel = __( 'Development', 'wp-system-report' );
			$status  = Status::Warning;
		}

		return $this->make_field(
			__( 'Core Update Channel', 'wp-system-report' ),
			$channel,
			array(
				'status'      => $status,
				'description' => __( 'The WordPress release channel (stable, beta, or development).', 'wp-system-report' ),
				'recommended' => __( 'Stable', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect core auto-update setting.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_core_auto_updates() {
		$auto_updates_disabled = defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED;
		$wp_auto_update_core   = defined( 'WP_AUTO_UPDATE_CORE' ) ? WP_AUTO_UPDATE_CORE : 'minor';

		if ( $auto_updates_disabled ) {
			$value  = __( 'Disabled (AUTOMATIC_UPDATER_DISABLED)', 'wp-system-report' );
			$status = Status::Warning;
		} elseif ( true === $wp_auto_update_core ) {
			$value  = __( 'All updates (major + minor)', 'wp-system-report' );
			$status = Status::Good;
		} elseif ( false === $wp_auto_update_core ) {
			$value  = __( 'Disabled (WP_AUTO_UPDATE_CORE = false)', 'wp-system-report' );
			$status = Status::Warning;
		} elseif ( 'minor' === $wp_auto_update_core ) {
			$value  = __( 'Minor updates only', 'wp-system-report' );
			$status = Status::Good;
		} else {
			$value  = (string) $wp_auto_update_core;
			$status = Status::Info;
		}

		return $this->make_field(
			__( 'Core Auto-Updates', 'wp-system-report' ),
			$value,
			array(
				'status'      => $status,
				'description' => __( 'Whether WordPress automatically applies core updates.', 'wp-system-report' ),
				'recommended' => __( 'Minor updates only', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect plugin update availability.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_plugin_updates_available() {
		$update_plugins = get_site_transient( 'update_plugins' );

		if ( ! is_object( $update_plugins ) || empty( $update_plugins->response ) ) {
			return $this->make_field(
				__( 'Plugin Updates Available', 'wp-system-report' ),
				__( 'All plugins up to date', 'wp-system-report' ),
				array(
					'status'      => Status::Good,
					'description' => __( 'Number of plugins with available updates.', 'wp-system-report' ),
				)
			);
		}

		$count = count( $update_plugins->response );

		return $this->make_field(
			__( 'Plugin Updates Available', 'wp-system-report' ),
			/* translators: %d: number of plugins */
			sprintf( _n( '%d plugin', '%d plugins', $count, 'wp-system-report' ), $count ),
			array(
				'status'      => $count > 5 ? Status::Warning : Status::Info,
				'description' => __( 'Number of plugins with available updates.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect plugin auto-update summary.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_plugin_auto_updates() {
		$auto_updates = (array) get_site_option( 'auto_update_plugins', array() );
		$all_plugins  = get_plugins();

		$enabled_count  = count( $auto_updates );
		$total_count    = count( $all_plugins );
		$disabled_count = $total_count - $enabled_count;

		$value = sprintf(
			/* translators: 1: enabled count, 2: total count */
			__( '%1$d of %2$d enabled', 'wp-system-report' ),
			$enabled_count,
			$total_count
		);

		$status = Status::Info;
		if ( $disabled_count > 0 && 0 === $enabled_count ) {
			$status = Status::Warning;
		} elseif ( $total_count === $enabled_count ) {
			$status = Status::Good;
		}

		return $this->make_field(
			__( 'Plugin Auto-Updates', 'wp-system-report' ),
			$value,
			array(
				'status'      => $status,
				'description' => __( 'How many plugins have auto-updates enabled.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect theme update availability.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_theme_updates_available() {
		$update_themes = get_site_transient( 'update_themes' );

		if ( ! is_object( $update_themes ) || empty( $update_themes->response ) ) {
			return $this->make_field(
				__( 'Theme Updates Available', 'wp-system-report' ),
				__( 'All themes up to date', 'wp-system-report' ),
				array(
					'status'      => Status::Good,
					'description' => __( 'Number of themes with available updates.', 'wp-system-report' ),
				)
			);
		}

		$count = count( $update_themes->response );

		return $this->make_field(
			__( 'Theme Updates Available', 'wp-system-report' ),
			/* translators: %d: number of themes */
			sprintf( _n( '%d theme', '%d themes', $count, 'wp-system-report' ), $count ),
			array(
				'status'      => $count > 3 ? Status::Warning : Status::Info,
				'description' => __( 'Number of themes with available updates.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect theme auto-update summary.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_theme_auto_updates() {
		$auto_updates = (array) get_site_option( 'auto_update_themes', array() );
		$all_themes   = wp_get_themes();

		$enabled_count  = count( $auto_updates );
		$total_count    = count( $all_themes );
		$disabled_count = $total_count - $enabled_count;

		$value = sprintf(
			/* translators: 1: enabled count, 2: total count */
			__( '%1$d of %2$d enabled', 'wp-system-report' ),
			$enabled_count,
			$total_count
		);

		$status = Status::Info;
		if ( $disabled_count > 0 && 0 === $enabled_count ) {
			$status = Status::Warning;
		} elseif ( $total_count === $enabled_count ) {
			$status = Status::Good;
		}

		return $this->make_field(
			__( 'Theme Auto-Updates', 'wp-system-report' ),
			$value,
			array(
				'status'      => $status,
				'description' => __( 'How many themes have auto-updates enabled.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect last successful update check timestamp.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_last_update_check() {
		$update_plugins = get_site_transient( 'update_plugins' );
		$last_checked   = is_object( $update_plugins ) && isset( $update_plugins->last_checked )
			? (int) $update_plugins->last_checked
			: 0;

		if ( 0 === $last_checked ) {
			return $this->make_field(
				__( 'Last Update Check', 'wp-system-report' ),
				__( 'Never', 'wp-system-report' ),
				array(
					'status'      => Status::Warning,
					'description' => __( 'When WordPress last checked for available updates.', 'wp-system-report' ),
				)
			);
		}

		$human_time = human_time_diff( $last_checked, time() );
		$hours_ago  = ( time() - $last_checked ) / HOUR_IN_SECONDS;

		$status = Status::Good;
		if ( $hours_ago > 24 ) {
			$status = Status::Warning;
		} elseif ( $hours_ago > 12 ) {
			$status = Status::Info;
		}

		return $this->make_field(
			__( 'Last Update Check', 'wp-system-report' ),
			/* translators: %s: human-readable time difference */
			sprintf( __( '%s ago', 'wp-system-report' ), $human_time ),
			array(
				'status'      => $status,
				'description' => __( 'When WordPress last checked for available updates.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect failed update history from core update log.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_failed_updates() {
		$core_updates = get_core_updates();
		$failed       = 0;

		if ( is_array( $core_updates ) ) {
			foreach ( $core_updates as $update ) {
				if ( isset( $update->response ) && 'error' === $update->response ) {
					++$failed;
				}
			}
		}

		// Check for failed plugin updates.
		$auto_updates_history = get_site_option( 'auto_updates_complete', array() );
		$failed_auto          = 0;

		if ( is_array( $auto_updates_history ) ) {
			foreach ( $auto_updates_history as $entry ) {
				if ( isset( $entry['success'] ) && false === $entry['success'] ) {
					++$failed_auto;
				}
			}
		}

		$total_failed = $failed + $failed_auto;

		if ( 0 === $total_failed ) {
			return $this->make_field(
				__( 'Failed Updates', 'wp-system-report' ),
				__( 'None', 'wp-system-report' ),
				array(
					'status'      => Status::Good,
					'description' => __( 'Count of failed update attempts detected in WordPress update history.', 'wp-system-report' ),
				)
			);
		}

		return $this->make_field(
			__( 'Failed Updates', 'wp-system-report' ),
			(string) $total_failed,
			array(
				'status'      => $total_failed > 2 ? Status::Critical : Status::Warning,
				'description' => __( 'Count of failed update attempts detected in WordPress update history.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect translation update availability.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_translation_updates() {
		$update_translations = wp_get_translation_updates();

		if ( empty( $update_translations ) ) {
			return $this->make_field(
				__( 'Translation Updates', 'wp-system-report' ),
				__( 'All translations up to date', 'wp-system-report' ),
				array(
					'status'      => Status::Good,
					'description' => __( 'Pending translation updates for core, plugins, and themes.', 'wp-system-report' ),
				)
			);
		}

		$count = count( $update_translations );

		return $this->make_field(
			__( 'Translation Updates', 'wp-system-report' ),
			/* translators: %d: number of translation updates */
			sprintf( _n( '%d update available', '%d updates available', $count, 'wp-system-report' ), $count ),
			array(
				'status'      => Status::Info,
				'description' => __( 'Pending translation updates for core, plugins, and themes.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Determine if a core update is a security release.
	 *
	 * Security releases have the same major.minor version but a different
	 * patch number from the current installation.
	 *
	 * @param object $update Core update object.
	 * @return bool True if this appears to be a security/maintenance release.
	 */
	private function is_security_update( $update ): bool {
		if ( ! isset( $update->current ) ) {
			return false;
		}

		$current_version = get_bloginfo( 'version' );
		$current_parts   = explode( '.', $current_version );
		$update_parts    = explode( '.', $update->current );

		// Same major.minor but different patch = likely security/maintenance.
		if (
			count( $current_parts ) >= 2 &&
			count( $update_parts ) >= 2 &&
			$current_parts[0] === $update_parts[0] &&
			$current_parts[1] === $update_parts[1]
		) {
			return true;
		}

		return false;
	}
}
