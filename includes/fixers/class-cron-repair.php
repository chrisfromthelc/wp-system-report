<?php
/**
 * Cron repair fixer.
 *
 * @package SystemReport
 */

namespace SystemReport\Fixers;

use SystemReport\Fixer;
use SystemReport\Fix_Result;
use SystemReport\Risk_Level;

defined( 'ABSPATH' ) || exit;

/**
 * Repairs WP-Cron by cleaning up problematic scheduled events.
 *
 * Detects and remediates common cron issues:
 *
 * - Orphaned events: hooks with no registered callback that will never fire.
 * - Stuck cron lock: a stale `doing_cron` transient that prevents new runs.
 * - Overdue recurring events: recurring schedules that missed their window
 *   and need rescheduling to the next appropriate time.
 *
 * All changes are reversible: orphaned events can be re-registered by their
 * original plugin, the cron lock regenerates naturally, and rescheduled
 * events continue on their original interval.
 */
class Cron_Repair implements Fixer {

	/**
	 * Maximum age in seconds for the `doing_cron` transient before it is
	 * considered stuck. WordPress core uses a 60-second lock timeout, so
	 * anything older than 10 minutes is almost certainly stale.
	 *
	 * @var int
	 */
	private const LOCK_STALE_THRESHOLD = 600;

	/**
	 * Minimum number of seconds a recurring event must be overdue before
	 * it is rescheduled. Prevents rescheduling events that are only a few
	 * seconds late due to normal timing jitter.
	 *
	 * @var int
	 */
	private const OVERDUE_GRACE_PERIOD = 300;

	/**
	 * Get the unique slug identifier.
	 *
	 * @return string Fixer ID.
	 */
	public function get_id(): string {
		return 'cron_repair';
	}

	/**
	 * Get the human-readable label.
	 *
	 * @return string Translated label.
	 */
	public function get_label(): string {
		return __( 'Cron Repair', 'wp-system-report' );
	}

	/**
	 * Get the fixer description.
	 *
	 * @return string Translated description.
	 */
	public function get_description(): string {
		return __( 'Removes orphaned cron events, clears stuck cron locks, and reschedules overdue recurring events.', 'wp-system-report' );
	}

	/**
	 * Get the risk level.
	 *
	 * @return Risk_Level Risk level.
	 */
	public function get_risk_level(): Risk_Level {
		return Risk_Level::Medium;
	}

	/**
	 * Get the category.
	 *
	 * @return string Category slug.
	 */
	public function get_category(): string {
		return 'cron';
	}

	/**
	 * Check if any cron repairs can be applied.
	 *
	 * @return bool True when at least one issue is detected.
	 */
	public function can_fix(): bool {
		if ( $this->has_stuck_lock() ) {
			return true;
		}

		if ( ! empty( $this->get_orphaned_events() ) ) {
			return true;
		}

		return ! empty( $this->get_overdue_recurring_events() );
	}

	/**
	 * Execute the cron repair.
	 *
	 * @return Fix_Result Result with before/after snapshots.
	 */
	public function fix(): Fix_Result {
		$before = $this->capture_state();

		if ( ! $before['has_stuck_lock'] && empty( $before['orphaned_events'] ) && empty( $before['overdue_recurring'] ) ) {
			return Fix_Result::success(
				__( 'WP-Cron is healthy. No repairs needed.', 'wp-system-report' )
			);
		}

		$applied = array();

		// Step 1: Clear stuck cron lock.
		if ( $before['has_stuck_lock'] ) {
			$this->clear_stuck_lock();
			$applied[] = __( 'Cleared stuck cron lock', 'wp-system-report' );
		}

		// Step 2: Remove orphaned events.
		$orphaned = $before['orphaned_events'];
		if ( ! empty( $orphaned ) ) {
			$removed_count = $this->remove_orphaned_events( $orphaned );
			$applied[]     = sprintf(
				/* translators: %d: number of orphaned events removed */
				__( 'Removed %d orphaned cron event(s)', 'wp-system-report' ),
				$removed_count
			);
		}

		// Step 3: Reschedule overdue recurring events.
		$overdue = $before['overdue_recurring'];
		if ( ! empty( $overdue ) ) {
			$rescheduled_count = $this->reschedule_overdue_events( $overdue );
			$applied[]         = sprintf(
				/* translators: %d: number of events rescheduled */
				__( 'Rescheduled %d overdue recurring event(s)', 'wp-system-report' ),
				$rescheduled_count
			);
		}

		$after = $this->capture_state();

		$message = ! empty( $applied )
			? implode( '; ', $applied ) . '.'
			: __( 'Cron repair complete.', 'wp-system-report' );

		return Fix_Result::success( $message, $before, $after );
	}

	/**
	 * Capture the current cron state.
	 *
	 * @return array<string, mixed> State snapshot.
	 */
	private function capture_state(): array {
		$cron_array = $this->get_cron_array();
		$orphaned   = $this->get_orphaned_events();
		$overdue    = $this->get_overdue_recurring_events();

		$total_events = 0;
		foreach ( $cron_array as $cron_events ) {
			$total_events += count( $cron_events );
		}

		return array(
			'total_events'      => $total_events,
			'has_stuck_lock'    => $this->has_stuck_lock(),
			'lock_age'          => $this->get_lock_age(),
			'orphaned_events'   => $orphaned,
			'orphaned_count'    => count( $orphaned ),
			'overdue_recurring' => $overdue,
			'overdue_count'     => count( $overdue ),
		);
	}

	/**
	 * Get the cron array safely.
	 *
	 * @return array<int, array<string, array<string, array<string, mixed>>>> Cron array.
	 */
	private function get_cron_array(): array {
		$cron_array = _get_cron_array();

		return is_array( $cron_array ) ? $cron_array : array();
	}

	/**
	 * Check if the cron lock is stuck.
	 *
	 * The `doing_cron` transient is set when WordPress begins executing
	 * cron events. If it persists beyond the stale threshold, something
	 * went wrong during execution and the lock is blocking new runs.
	 *
	 * @return bool True if the lock is stale.
	 */
	private function has_stuck_lock(): bool {
		$lock_age = $this->get_lock_age();

		return null !== $lock_age && $lock_age > self::LOCK_STALE_THRESHOLD;
	}

	/**
	 * Get the age of the cron lock in seconds.
	 *
	 * @return int|null Age in seconds, or null if no lock exists.
	 */
	private function get_lock_age(): ?int {
		$doing_cron = get_transient( 'doing_cron' );

		if ( false === $doing_cron || ! is_numeric( $doing_cron ) ) {
			return null;
		}

		return time() - (int) $doing_cron;
	}

	/**
	 * Get orphaned cron events (hooks with no registered callback).
	 *
	 * An event is considered orphaned when its hook name has no callbacks
	 * registered via `add_action()`. This typically happens when a plugin
	 * is deactivated but its scheduled events are not cleaned up.
	 *
	 * WordPress core hooks and known system hooks are excluded.
	 *
	 * @return array<string, int> Hook names mapped to event counts.
	 */
	private function get_orphaned_events(): array {
		$cron_array = $this->get_cron_array();
		$orphaned   = array();

		foreach ( $cron_array as $cron_events ) {
			foreach ( $cron_events as $hook => $hook_events ) {
				// Skip WordPress core hooks — they may be registered later.
				if ( $this->is_core_hook( $hook ) ) {
					continue;
				}

				// Check if any callback is registered for this hook.
				if ( ! has_action( $hook ) ) {
					if ( ! isset( $orphaned[ $hook ] ) ) {
						$orphaned[ $hook ] = 0;
					}
					$orphaned[ $hook ] += count( $hook_events );
				}
			}
		}

		return $orphaned;
	}

	/**
	 * Get overdue recurring events that need rescheduling.
	 *
	 * Finds recurring events whose scheduled time is in the past by more
	 * than the grace period. These events missed their window and should
	 * be rescheduled to the next occurrence based on their interval.
	 *
	 * @return array<int, array{hook: string, timestamp: int, schedule: string, interval: int, overdue_by: int}> Overdue events.
	 */
	private function get_overdue_recurring_events(): array {
		$cron_array   = $this->get_cron_array();
		$current_time = time();
		$overdue      = array();

		foreach ( $cron_array as $timestamp => $cron_events ) {
			$overdue_by = $current_time - $timestamp;

			// Must be overdue by more than the grace period.
			if ( $overdue_by <= self::OVERDUE_GRACE_PERIOD ) {
				continue;
			}

			foreach ( $cron_events as $hook => $hook_events ) {
				foreach ( $hook_events as $event ) {
					// Only recurring events (those with a schedule) can be rescheduled.
					if ( empty( $event['schedule'] ) ) {
						continue;
					}

					$overdue[] = array(
						'hook'       => $hook,
						'timestamp'  => $timestamp,
						'schedule'   => $event['schedule'],
						'interval'   => $event['interval'],
						'overdue_by' => $overdue_by,
					);
				}
			}
		}

		return $overdue;
	}

	/**
	 * Check if a hook is a WordPress core cron hook.
	 *
	 * Core hooks are excluded from orphan detection because they may
	 * be registered later in the WordPress bootstrap or conditionally.
	 *
	 * @param string $hook Hook name to check.
	 * @return bool True if the hook is a known core hook.
	 */
	private function is_core_hook( string $hook ): bool {
		$core_hooks = array(
			'wp_version_check',
			'wp_update_plugins',
			'wp_update_themes',
			'wp_scheduled_delete',
			'wp_scheduled_auto_draft_delete',
			'wp_privacy_delete_old_export_files',
			'delete_expired_transients',
			'recovery_mode_clean_expired_keys',
			'wp_site_health_scheduled_check',
			'wp_https_detection',
			'wp_delete_temp_updater_backups',
		);

		/**
		 * Filter the list of core cron hooks excluded from orphan detection.
		 *
		 * @param string[] $core_hooks Array of hook names considered core.
		 */
		return in_array( $hook, apply_filters( 'wp_system_report_core_cron_hooks', $core_hooks ), true );
	}

	/**
	 * Clear the stuck cron lock.
	 */
	private function clear_stuck_lock(): void {
		delete_transient( 'doing_cron' );
	}

	/**
	 * Remove orphaned cron events.
	 *
	 * @param array<string, int> $orphaned Hook names to remove.
	 * @return int Number of events removed.
	 */
	private function remove_orphaned_events( array $orphaned ): int {
		$cron_array = $this->get_cron_array();
		$removed    = 0;

		foreach ( $cron_array as $timestamp => $cron_events ) {
			foreach ( $cron_events as $hook => $hook_events ) {
				if ( ! isset( $orphaned[ $hook ] ) ) {
					continue;
				}

				foreach ( $hook_events as $event ) {
					$args = $event['args'] ?? array();
					wp_unschedule_event( $timestamp, $hook, $args );
					++$removed;
				}
			}
		}

		return $removed;
	}

	/**
	 * Reschedule overdue recurring events.
	 *
	 * Each event is unscheduled from its old timestamp and rescheduled
	 * to the next occurrence based on its interval.
	 *
	 * @param array<int, array{hook: string, timestamp: int, schedule: string, interval: int, overdue_by: int}> $overdue Events to reschedule.
	 * @return int Number of events rescheduled.
	 */
	private function reschedule_overdue_events( array $overdue ): int {
		$rescheduled  = 0;
		$current_time = time();

		foreach ( $overdue as $event ) {
			$cron_array = $this->get_cron_array();

			// The event may have already been removed or rescheduled.
			if ( ! isset( $cron_array[ $event['timestamp'] ][ $event['hook'] ] ) ) {
				continue;
			}

			$hook_events = $cron_array[ $event['timestamp'] ][ $event['hook'] ];

			foreach ( $hook_events as $event_data ) {
				$args = $event_data['args'] ?? array();

				// Unschedule the old event.
				wp_unschedule_event( $event['timestamp'], $event['hook'], $args );

				// Calculate the next run time based on the interval.
				$next_run = $current_time + $event['interval'];

				// Reschedule.
				wp_schedule_event( $next_run, $event['schedule'], $event['hook'], $args );
				++$rescheduled;
			}
		}

		return $rescheduled;
	}
}
