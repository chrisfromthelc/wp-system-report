<?php
/**
 * Database optimizer fixer.
 *
 * @package SystemReport
 */

namespace SystemReport\Fixers;

use SystemReport\Fixer;
use SystemReport\Fix_Result;
use SystemReport\Risk_Level;

defined( 'ABSPATH' ) || exit;

/**
 * Optimizes the WordPress database by cleaning expired transients
 * and reclaiming table overhead (fragmentation).
 *
 * Expired transients accumulate in wp_options when they are not
 * garbage-collected. Database overhead (DATA_FREE) builds up as
 * rows are inserted, updated, and deleted. This fixer cleans both.
 *
 * Note: OPTIMIZE TABLE acquires a write lock for the duration of
 * the operation. On InnoDB (the default engine) this is usually
 * fast, but very large tables may cause brief write pauses.
 * The fixer limits each run to {@see MAX_TABLES_PER_RUN} tables
 * to bound the total lock time.
 */
class Database_Optimizer implements Fixer {

	/**
	 * Maximum number of tables to optimize in a single run.
	 *
	 * Prevents OPTIMIZE TABLE from locking too many tables in one
	 * request. Remaining tables will be optimized on subsequent runs.
	 */
	private const MAX_TABLES_PER_RUN = 20;

	/**
	 * Get the unique slug identifier.
	 *
	 * @return string Fixer ID.
	 */
	public function get_id(): string {
		return 'database_optimizer';
	}

	/**
	 * Get the human-readable label.
	 *
	 * @return string Translated label.
	 */
	public function get_label(): string {
		return __( 'Database Optimizer', 'wp-system-report' );
	}

	/**
	 * Get the fixer description.
	 *
	 * @return string Translated description.
	 */
	public function get_description(): string {
		return __( 'Cleans expired transients and reclaims table overhead to reduce database bloat. OPTIMIZE TABLE briefly locks each table during optimization.', 'wp-system-report' );
	}

	/**
	 * Get the risk level.
	 *
	 * @return Risk_Level Risk level.
	 */
	public function get_risk_level(): Risk_Level {
		return Risk_Level::Low;
	}

	/**
	 * Get the category.
	 *
	 * @return string Category slug.
	 */
	public function get_category(): string {
		return 'database';
	}

	/**
	 * Check if any optimization is applicable.
	 *
	 * @return bool True when there are expired transients or table overhead.
	 */
	public function can_fix(): bool {
		if ( $this->count_expired_transients() > 0 ) {
			return true;
		}

		return $this->get_total_overhead() > 0;
	}

	/**
	 * Execute the database optimization.
	 *
	 * @return Fix_Result Result with before/after snapshots.
	 */
	public function fix(): Fix_Result {
		$expired_count     = $this->count_expired_transients();
		$overhead_before   = $this->get_total_overhead();
		$tables_with_waste = $this->get_tables_with_overhead();

		if ( 0 === $expired_count && 0 === $overhead_before ) {
			return Fix_Result::success(
				__( 'Database is already clean. Nothing to optimize.', 'wp-system-report' )
			);
		}

		$before = array(
			'expired_transients' => $expired_count,
			'total_overhead'     => $overhead_before,
			'tables_with_waste'  => count( $tables_with_waste ),
		);

		$errors = array();

		// Step 1: Clean expired transients.
		$transients_deleted = 0;
		if ( $expired_count > 0 ) {
			$transients_deleted = $this->delete_expired_transients();
		}

		// Step 2: Optimize tables with overhead (capped per run to limit lock time).
		$tables_optimized = 0;
		$tables_to_run    = array_slice( $tables_with_waste, 0, self::MAX_TABLES_PER_RUN );
		$tables_skipped   = count( $tables_with_waste ) - count( $tables_to_run );

		foreach ( $tables_to_run as $table_name ) {
			$result = $this->optimize_table( $table_name );
			if ( $result ) {
				++$tables_optimized;
			} else {
				$errors[] = sprintf(
					/* translators: %s: table name */
					__( 'Failed to optimize table: %s', 'wp-system-report' ),
					$table_name
				);
			}
		}

		$after = array(
			'expired_transients' => $this->count_expired_transients(),
			'total_overhead'     => $this->get_total_overhead(),
			'transients_deleted' => $transients_deleted,
			'tables_optimized'   => $tables_optimized,
			'tables_skipped'     => $tables_skipped,
		);

		if ( ! empty( $errors ) ) {
			return Fix_Result::failure(
				sprintf(
					/* translators: 1: transients deleted, 2: tables optimized, 3: error count */
					__( 'Partially completed: %1$d transient(s) deleted, %2$d table(s) optimized, %3$d error(s).', 'wp-system-report' ),
					$transients_deleted,
					$tables_optimized,
					count( $errors )
				),
				$errors
			);
		}

		$parts = array();
		if ( $transients_deleted > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of expired transients deleted */
				__( '%d expired transient(s) deleted', 'wp-system-report' ),
				$transients_deleted
			);
		}
		if ( $tables_optimized > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of tables optimized */
				__( '%d table(s) optimized', 'wp-system-report' ),
				$tables_optimized
			);
		}
		if ( $tables_skipped > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of tables deferred to next run */
				__( '%d table(s) deferred to next run', 'wp-system-report' ),
				$tables_skipped
			);
		}

		$message = ! empty( $parts )
			? implode( ', ', $parts ) . '.'
			: __( 'Database optimization complete.', 'wp-system-report' );

		return Fix_Result::success( $message, $before, $after );
	}

	/**
	 * Count expired transients in the options table.
	 *
	 * @return int Number of expired transient timeout rows.
	 */
	private function count_expired_transients(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic query.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			)
		);
	}

	/**
	 * Delete expired transients and their paired data rows.
	 *
	 * @return int Number of transient pairs deleted.
	 */
	private function delete_expired_transients(): int {
		global $wpdb;

		// Find expired timeout keys.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance operation.
		$expired = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_value < %d
				LIMIT 1000",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			)
		);

		if ( empty( $expired ) ) {
			return 0;
		}

		$deleted = 0;

		foreach ( $expired as $timeout_key ) {
			// Each expired timeout has a paired data key.
			$data_key = str_replace( '_transient_timeout_', '_transient_', $timeout_key );

			delete_option( $data_key );
			delete_option( $timeout_key );
			++$deleted;
		}

		return $deleted;
	}

	/**
	 * Get the total overhead (DATA_FREE) across all database tables.
	 *
	 * @return int Total overhead in bytes.
	 */
	private function get_total_overhead(): int {
		global $wpdb;

		$database_name = defined( 'DB_NAME' ) ? DB_NAME : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic query.
		$overhead = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(DATA_FREE) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s',
				$database_name
			)
		);

		return (int) $overhead;
	}

	/**
	 * Get table names with overhead exceeding the minimum threshold.
	 *
	 * Only returns tables within the WordPress prefix to avoid touching
	 * other applications sharing the same database.
	 *
	 * @return string[] Array of table names.
	 */
	private function get_tables_with_overhead(): array {
		global $wpdb;

		$database_name = defined( 'DB_NAME' ) ? DB_NAME : '';

		/**
		 * Filter the minimum overhead threshold for table optimization.
		 *
		 * @param int $threshold Minimum DATA_FREE in bytes. Default 1 MB.
		 */
		$threshold = (int) apply_filters( 'wp_system_report_optimize_overhead_threshold', MB_IN_BYTES );

		// Order by DATA_FREE descending so MAX_TABLES_PER_RUN optimises
		// the most impactful tables first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic query.
		$tables = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT TABLE_NAME FROM information_schema.TABLES
				WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME LIKE %s
				AND DATA_FREE >= %d
				ORDER BY DATA_FREE DESC',
				$database_name,
				$wpdb->esc_like( $wpdb->prefix ) . '%',
				$threshold
			)
		);

		return ! empty( $tables ) ? $tables : array();
	}

	/**
	 * Run OPTIMIZE TABLE on a single table.
	 *
	 * @param string $table_name Fully qualified table name.
	 * @return bool True on success.
	 */
	private function optimize_table( string $table_name ): bool {
		global $wpdb;

		// Validate the table name belongs to our prefix.
		if ( ! str_starts_with( $table_name, $wpdb->prefix ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name validated against prefix; identifiers cannot use prepare placeholders.
		$result = $wpdb->query( "OPTIMIZE TABLE `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return false !== $result;
	}
}
