<?php
/**
 * Report history storage.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and retrieves historical report snapshots.
 *
 * Reports are stored in a custom database table with compressed JSON data.
 * Each snapshot includes the health score and grade computed at generation
 * time, enabling trend analysis without decompressing the full report.
 */
class Report_History {

	/**
	 * Custom table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE_NAME = 'sr_report_history';

	/**
	 * Database schema version for upgrade tracking.
	 *
	 * @var string
	 */
	const SCHEMA_VERSION = '1.0.3';

	/**
	 * Option key for tracking installed schema version.
	 *
	 * @var string
	 */
	const SCHEMA_OPTION = 'sr_report_history_schema_version';

	/**
	 * Maximum number of snapshots to retain by default.
	 *
	 * @var int
	 */
	const DEFAULT_RETENTION_LIMIT = 90;

	/**
	 * Report generator instance.
	 */
	private Report_Generator $report_generator;

	/**
	 * Health score calculator instance.
	 */
	private Health_Score $health_score;

	/**
	 * Constructor.
	 *
	 * @param Report_Generator $report_generator Report generator instance.
	 * @param Health_Score     $health_score     Health score calculator.
	 */
	public function __construct( Report_Generator $report_generator, Health_Score $health_score ) {
		$this->report_generator = $report_generator;
		$this->health_score     = $health_score;
	}

	/**
	 * Get the full table name including the WordPress prefix.
	 *
	 * @return string Full table name.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create or update the custom database table.
	 *
	 * Uses dbDelta() following the same pattern as WordPress core table
	 * creation in wp-admin/includes/schema.php. The schema uses two spaces
	 * after PRIMARY KEY (a dbDelta requirement) and KEY for secondary indexes.
	 *
	 * No-ops when the report history feature gate is disabled, keeping
	 * call sites simple (activation hook, admin_init) and avoiding
	 * accidental table creation in Free tier.
	 *
	 * Should be called on plugin activation and checked via
	 * needs_schema_update() on admin_init.
	 */
	public static function create_table(): void {
		if ( ! Features::has_report_history() ) {
			return;
		}

		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from constant, charset from $wpdb.
		dbDelta(
			"CREATE TABLE $table_name (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 score smallint(3) unsigned NOT NULL DEFAULT 0,
 grade varchar(2) NOT NULL DEFAULT 'F',
 summary_good smallint(5) unsigned NOT NULL DEFAULT 0,
 summary_warning smallint(5) unsigned NOT NULL DEFAULT 0,
 summary_critical smallint(5) unsigned NOT NULL DEFAULT 0,
 report_data longblob NOT NULL,
 created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY  (id),
 KEY idx_created_at (created_at),
 KEY idx_score (score)
) $charset_collate;"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Drop the custom table.
	 *
	 * Should be called on plugin uninstall (not deactivation).
	 */
	public static function drop_table(): void {
		global $wpdb;
		$table_name = self::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from a class constant.
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		delete_option( self::SCHEMA_OPTION );
	}

	/**
	 * Check whether the schema needs to be created or upgraded.
	 *
	 * Returns false when the report history feature gate is disabled,
	 * so call sites can remain simple.
	 *
	 * @return bool True if the schema needs updating.
	 */
	public static function needs_schema_update(): bool {
		if ( ! Features::has_report_history() ) {
			return false;
		}

		$installed = get_option( self::SCHEMA_OPTION, '' );
		return self::SCHEMA_VERSION !== $installed;
	}

	/**
	 * Register hooks for automatic snapshot creation.
	 */
	public function register_hooks(): void {
		add_action( 'wp_system_report_generated', array( $this, 'maybe_save_snapshot' ) );
	}

	/**
	 * Conditionally save a report snapshot.
	 *
	 * Limits automatic snapshots to once per hour to avoid flooding the table
	 * when reports are generated frequently (e.g. by MCP agents).
	 *
	 * @param array $report The complete report data.
	 */
	public function maybe_save_snapshot( array $report ): void {
		$last_saved = get_transient( 'sr_last_snapshot_time' );

		/**
		 * Filter the minimum interval between automatic snapshots.
		 *
		 * @param int $interval Minimum seconds between snapshots. Default 3600 (1 hour).
		 */
		$min_interval = (int) apply_filters( 'wp_system_report_snapshot_interval', HOUR_IN_SECONDS );

		if ( false !== $last_saved && ( time() - (int) $last_saved ) < $min_interval ) {
			return;
		}

		$this->save_snapshot( $report );
		set_transient( 'sr_last_snapshot_time', time(), $min_interval );
	}

	/**
	 * Save a report snapshot to the history table.
	 *
	 * @param array|null $report Optional report data. If null, generates a fresh report.
	 * @return int|false The snapshot ID on success, false on failure.
	 */
	public function save_snapshot( ?array $report = null ): false|int {
		global $wpdb;

		if ( null === $report ) {
			$report = $this->report_generator->generate();
		}

		$score_data = $this->health_score->calculate( $report );

		$json = wp_json_encode( $report );

		if ( false === $json ) {
			return false;
		}

		if ( function_exists( 'gzcompress' ) ) {
			$compressed = gzcompress( $json, 6 );

			if ( false === $compressed ) {
				return false;
			}

			// Base64-encode the binary data so it survives UTF-8 storage.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding binary gzcompress output for safe DB storage, not obfuscation.
			$compressed = base64_encode( $compressed );
		} else {
			// Store raw JSON when zlib is not available.
			$compressed = $json;
		}

		$result = $wpdb->insert(
			self::get_table_name(),
			array(
				'score'            => $score_data['score'],
				'grade'            => $score_data['grade'],
				'summary_good'     => $score_data['summary']['good'] ?? 0,
				'summary_warning'  => $score_data['summary']['warnings'] ?? 0,
				'summary_critical' => $score_data['summary']['criticals'] ?? 0,
				'report_data'      => $compressed,
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$snapshot_id = (int) $wpdb->insert_id;

		$this->enforce_retention_limit();

		/**
		 * Fires after a report snapshot is saved.
		 *
		 * @param int   $snapshot_id The new snapshot ID.
		 * @param array $score_data  The health score data for the snapshot.
		 */
		do_action( 'wp_system_report_snapshot_saved', $snapshot_id, $score_data );

		return $snapshot_id;
	}

	/**
	 * Retrieve a list of snapshots (metadata only, no report data).
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type int    $per_page Number of results per page. Default 20.
	 *     @type int    $page     Page number. Default 1.
	 *     @type string $order    Sort order: 'ASC' or 'DESC'. Default 'DESC'.
	 *     @type string $after    Only return snapshots after this datetime (ISO 8601).
	 *     @type string $before   Only return snapshots before this datetime (ISO 8601).
	 * }
	 * @return array{items: array, total: int, pages: int} Paginated results.
	 */
	public function list_snapshots( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'order'    => 'DESC',
			'after'    => '',
			'before'   => '',
		);

		$args     = wp_parse_args( $args, $defaults );
		$per_page = max( 1, min( 100, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;
		$order    = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$table  = self::get_table_name();
		$where  = array();
		$values = array();

		if ( '' !== $args['after'] ) {
			$after_ts = strtotime( $args['after'] );
			if ( false !== $after_ts ) {
				$where[]  = 'created_at > %s';
				$values[] = gmdate( 'Y-m-d H:i:s', $after_ts );
			}
		}

		if ( '' !== $args['before'] ) {
			$before_ts = strtotime( $args['before'] );
			if ( false !== $before_ts ) {
				$where[]  = 'created_at < %s';
				$values[] = gmdate( 'Y-m-d H:i:s', $before_ts );
			}
		}

		$where_clause = '';
		if ( ! empty( $where ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		// Count total items.
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name is a constant; WHERE clause contains user-supplied placeholders.
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_clause}", ...$values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a constant; no user input.
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		// Fetch items.
		$select_values   = $values;
		$select_values[] = $per_page;
		$select_values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constant; ORDER direction is validated.
		$query = "SELECT id, score, grade, summary_good, summary_warning, summary_critical, created_at FROM {$table} {$where_clause} ORDER BY created_at {$order}, id {$order} LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is constructed above with interpolated constants.
		$items = $wpdb->get_results( $wpdb->prepare( $query, ...$select_values ), ARRAY_A );

		if ( null === $items ) {
			$items = array();
		}

		// Cast numeric values.
		$items = array_map(
			static fn( array $item ): array => array(
				'id'               => (int) $item['id'],
				'score'            => (int) $item['score'],
				'grade'            => $item['grade'],
				'summary_good'     => (int) $item['summary_good'],
				'summary_warning'  => (int) $item['summary_warning'],
				'summary_critical' => (int) $item['summary_critical'],
				'created_at'       => $item['created_at'],
			),
			$items
		);

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Retrieve a single snapshot with full report data.
	 *
	 * @param int $id Snapshot ID.
	 * @return array|null Snapshot data with decompressed report, or null if not found.
	 */
	public function get_snapshot( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::get_table_name(), $id ),
			ARRAY_A
		);

		if ( null === $row ) {
			return null;
		}

		$report = $this->decompress_report( $row['report_data'] );

		return array(
			'id'               => (int) $row['id'],
			'score'            => (int) $row['score'],
			'grade'            => $row['grade'],
			'summary_good'     => (int) $row['summary_good'],
			'summary_warning'  => (int) $row['summary_warning'],
			'summary_critical' => (int) $row['summary_critical'],
			'report'           => $report,
			'created_at'       => $row['created_at'],
		);
	}

	/**
	 * Get trending data for health scores over time.
	 *
	 * Returns score and summary data points for charting.
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type int    $days  Number of days of history to include. Default 30.
	 *     @type string $after ISO 8601 start date.
	 * }
	 * @return array Array of data points with score, grade, summary, and timestamp.
	 */
	public function get_trend( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'days'  => 30,
			'after' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		if ( '' !== $args['after'] ) {
			$after_ts = strtotime( $args['after'] );
			$since    = false !== $after_ts
				? gmdate( 'Y-m-d H:i:s', $after_ts )
				: gmdate( 'Y-m-d H:i:s', strtotime( "-{$args['days']} days" ) );
		} else {
			$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$args['days']} days" ) );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, score, grade, summary_good, summary_warning, summary_critical, created_at FROM %i WHERE created_at >= %s ORDER BY created_at ASC, id ASC',
				self::get_table_name(),
				$since
			),
			ARRAY_A
		);

		if ( null === $rows ) {
			return array();
		}

		return array_map(
			static fn( array $row ): array => array(
				'id'         => (int) $row['id'],
				'score'      => (int) $row['score'],
				'grade'      => $row['grade'],
				'summary'    => array(
					'good'     => (int) $row['summary_good'],
					'warning'  => (int) $row['summary_warning'],
					'critical' => (int) $row['summary_critical'],
				),
				'created_at' => $row['created_at'],
			),
			$rows
		);
	}

	/**
	 * Delete a specific snapshot.
	 *
	 * @param int $id Snapshot ID.
	 * @return bool True if deleted, false otherwise.
	 */
	public function delete_snapshot( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			self::get_table_name(),
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Delete all snapshots.
	 *
	 * @return int Number of rows deleted.
	 */
	public function delete_all(): int {
		global $wpdb;

		return (int) $wpdb->query(
			$wpdb->prepare( 'DELETE FROM %i', self::get_table_name() )
		);
	}

	/**
	 * Enforce the retention limit by deleting oldest snapshots.
	 */
	private function enforce_retention_limit(): void {
		global $wpdb;

		/**
		 * Filter the maximum number of report snapshots to retain.
		 *
		 * @param int $limit Maximum snapshot count. Default 90.
		 */
		$limit = (int) apply_filters( 'wp_system_report_retention_limit', self::DEFAULT_RETENTION_LIMIT );
		$limit = max( 1, $limit );

		$table = self::get_table_name();
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
		);

		if ( $count <= $limit ) {
			return;
		}

		$excess = $count - $limit;

		// Delete the oldest snapshots.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i ORDER BY created_at ASC LIMIT %d',
				$table,
				$excess
			)
		);
	}

	/**
	 * Decompress a stored report blob.
	 *
	 * Falls back to treating data as raw JSON when zlib is unavailable
	 * or when decompression fails (e.g. data was stored uncompressed).
	 *
	 * @param string $data Compressed (or raw JSON) report data.
	 * @return array|null Decoded report array, or null on failure.
	 */
	private function decompress_report( string $data ): ?array {
		if ( function_exists( 'gzuncompress' ) ) {
			// Try base64-decoded gzuncompress first (current storage format).
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding base64-encoded gzcompress output from DB storage, not obfuscation.
			$decoded_b64 = base64_decode( $data, true );

			if ( false !== $decoded_b64 ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- gzuncompress emits E_WARNING on malformed data; suppressed to allow fallback.
				$decompressed = @gzuncompress( $decoded_b64 );

				if ( false !== $decompressed ) {
					$decoded = json_decode( $decompressed, true );
					if ( is_array( $decoded ) ) {
						return $decoded;
					}
				}
			}

			// Legacy fallback: try raw binary gzuncompress (pre-fix storage).
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- gzuncompress emits E_WARNING on malformed data; suppressed to allow JSON fallback.
			$decompressed = @gzuncompress( $data );

			if ( false !== $decompressed ) {
				$decoded = json_decode( $decompressed, true );
				return is_array( $decoded ) ? $decoded : null;
			}
		}

		// Fallback: try decoding as raw JSON (uncompressed storage).
		$decoded = json_decode( $data, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Get the most recent snapshot metadata.
	 *
	 * @return array|null Most recent snapshot (without report data), or null.
	 */
	public function get_latest(): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, score, grade, summary_good, summary_warning, summary_critical, created_at FROM %i ORDER BY created_at DESC, id DESC LIMIT 1',
				self::get_table_name()
			),
			ARRAY_A
		);

		if ( null === $row ) {
			return null;
		}

		return array(
			'id'               => (int) $row['id'],
			'score'            => (int) $row['score'],
			'grade'            => $row['grade'],
			'summary_good'     => (int) $row['summary_good'],
			'summary_warning'  => (int) $row['summary_warning'],
			'summary_critical' => (int) $row['summary_critical'],
			'created_at'       => $row['created_at'],
		);
	}
}
