<?php
/**
 * Cron Health collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects scheduled events, overdue jobs, and WP-Cron status.
 */
class Cron_Health extends Abstract_Collector {

	/**
 * Get the collector ID.
 */
	public function get_id(): string {
		return 'cron_health';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Cron Health', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Scheduled events, overdue jobs, and WP-Cron status.', 'wp-system-report' );
	}

	/**
 * Get the collector priority.
 */
	public function get_priority(): int {
		return 130;
	}

	/**
 * Collect the data.
 */
	public function collect(): array {
		$fields = array();

		// Check if WP-Cron is disabled.
		$cron_disabled = $this->get_constant_value( 'DISABLE_WP_CRON', false );
		$fields[]      = $this->make_field(
			__( 'WP-Cron Disabled', 'wp-system-report' ),
			$this->format_boolean( $cron_disabled ),
			array(
				'status' => $cron_disabled ? 'warning' : 'good',
				'debug'  => $cron_disabled,
			)
		);

		// Get all scheduled events.
		$cron_array = _get_cron_array();

		if ( ! is_array( $cron_array ) ) {
			$cron_array = array();
		}

		// Count total scheduled events.
		$total_events = 0;
		foreach ( $cron_array as $timestamp => $cron_events ) {
			$total_events += count( $cron_events );
		}

		$fields[] = $this->make_field(
			__( 'Total Scheduled Events', 'wp-system-report' ),
			$total_events,
			array(
				'debug' => $total_events,
			)
		);

		// Get next cron run time.
		$next_run_timestamp = null;
		if ( ! empty( $cron_array ) ) {
			$timestamps         = array_keys( $cron_array );
			$next_run_timestamp = min( $timestamps );
		}

		if ( $next_run_timestamp ) {
			$time_diff = $next_run_timestamp - time();
			if ( $time_diff > 0 ) {
				$next_run_display = sprintf(
					// translators: %s: Human-readable time difference.
					__( 'In %s', 'wp-system-report' ),
					human_time_diff( time(), $next_run_timestamp )
				);
			} else {
				$next_run_display = sprintf(
					// translators: %s: Human-readable time difference.
					__( '%s ago', 'wp-system-report' ),
					human_time_diff( $next_run_timestamp, time() )
				);
			}

			$fields[] = $this->make_field(
				__( 'Next Cron Run', 'wp-system-report' ),
				$next_run_display,
				array(
					'debug' => gmdate( 'Y-m-d H:i:s', $next_run_timestamp ),
				)
			);
		} else {
			$fields[] = $this->make_field(
				__( 'Next Cron Run', 'wp-system-report' ),
				__( 'No scheduled events', 'wp-system-report' ),
				array(
					'debug' => null,
				)
			);
		}

		// Check for overdue events.
		$current_time  = time();
		$overdue_count = 0;
		$overdue_hooks = array();

		foreach ( $cron_array as $timestamp => $cron_events ) {
			if ( $timestamp < $current_time ) {
				foreach ( $cron_events as $hook => $hook_events ) {
					++$overdue_count;
					$overdue_hooks[] = $hook;
				}
			}
		}

		$overdue_status = 'good';
		if ( $overdue_count > 5 ) {
			$overdue_status = 'critical';
		} elseif ( $overdue_count > 0 ) {
			$overdue_status = 'warning';
		}

		$fields[] = $this->make_field(
			__( 'Overdue Events', 'wp-system-report' ),
			$overdue_count,
			array(
				'status' => $overdue_status,
				'debug'  => $overdue_count,
			)
		);

		// List overdue events if any exist.
		if ( $overdue_count > 0 ) {
			$overdue_hooks_unique = array_unique( $overdue_hooks );
			$fields[]             = $this->make_field(
				__( 'Overdue Event Hooks', 'wp-system-report' ),
				implode( ', ', array_slice( $overdue_hooks_unique, 0, 10 ) ),
				array(
					'status'      => 'info',
					'debug'       => $overdue_hooks_unique,
					'description' => $overdue_count > 10 ?
						sprintf(
							// translators: %d: Number of additional overdue hooks.
							__( 'Showing first 10 of %d overdue hooks', 'wp-system-report' ),
							$overdue_count
						) : '',
				)
			);
		}

		// Check for last cron run via doing_cron transient.
		$doing_cron = get_transient( 'doing_cron' );

		if ( $doing_cron ) {
			$last_run_display = sprintf(
				// translators: %s: Human-readable time difference.
				__( '%s ago (currently running)', 'wp-system-report' ),
				human_time_diff( $doing_cron, time() )
			);
			$fields[] = $this->make_field(
				__( 'Last Cron Run', 'wp-system-report' ),
				$last_run_display,
				array(
					'debug' => gmdate( 'Y-m-d H:i:s', $doing_cron ),
				)
			);
		} else {
			$fields[] = $this->make_field(
				__( 'Last Cron Run', 'wp-system-report' ),
				__( 'Unknown', 'wp-system-report' ),
				array(
					'debug'       => null,
					'description' => __( 'No recent execution detected', 'wp-system-report' ),
				)
			);
		}

		return $fields;
	}
}
