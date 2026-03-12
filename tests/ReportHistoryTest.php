<?php
/**
 * Report History tests.
 *
 * @package SystemReport
 */

use SystemReport\Report_Generator;
use SystemReport\Report_History;
use SystemReport\Health_Score;
use SystemReport\Field;
use SystemReport\Status;

/**
 * Test the Report_History class.
 */
class ReportHistoryTest extends WP_UnitTestCase {

	/**
	 * Report generator instance.
	 *
	 * @var Report_Generator
	 */
	private Report_Generator $generator;

	/**
	 * Health score instance.
	 *
	 * @var Health_Score
	 */
	private Health_Score $health_score;

	/**
	 * Report history instance.
	 *
	 * @var Report_History
	 */
	private Report_History $history;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->generator    = new Report_Generator();
		$this->health_score = new Health_Score( $this->generator );
		$this->history      = new Report_History( $this->generator, $this->health_score );

		// Remove the Plugin singleton's hook to prevent it from saving
		// extra snapshots (consuming auto-increment IDs) when other
		// tests trigger report generation.
		remove_all_actions( 'wp_system_report_generated' );

		// Clear all rows between tests.  The table is created once by
		// the test bootstrap (outside any transaction) so we only need
		// to empty it here.  Using DELETE (DML) instead of TRUNCATE or
		// DROP/CREATE (DDL) so it participates in the test transaction
		// and doesn't cause an implicit commit.
		$this->history->delete_all();
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		$this->history->delete_all();
		parent::tear_down();
	}

	/**
	 * Create a sample report for testing.
	 *
	 * @return array Sample report data.
	 */
	private function create_sample_report(): array {
		return array(
			'security' => array(
				'id'          => 'security',
				'label'       => 'Security',
				'description' => 'Security checks',
				'fields'      => array(
					new Field( 'SSL', 'Enabled', null, Status::Good ),
					new Field( 'File Editor', 'Enabled', null, Status::Warning ),
				),
			),
			'performance' => array(
				'id'          => 'performance',
				'label'       => 'Performance',
				'description' => 'Performance checks',
				'fields'      => array(
					new Field( 'Object Cache', 'Active', null, Status::Good ),
				),
			),
		);
	}

	/**
	 * Test that the table exists (created by the test bootstrap).
	 */
	public function test_create_table(): void {
		global $wpdb;

		$table = Report_History::get_table_name();

		// Use information_schema with exact match instead of SHOW TABLES LIKE,
		// which is unreliable in MySQL 8.0 under the WP test suite's transaction
		// isolation (autocommit = 0).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				$table
			)
		);

		$this->assertSame( $table, $exists );
	}

	/**
	 * Test schema version tracking.
	 */
	public function test_schema_version_tracking(): void {
		$this->assertSame(
			Report_History::SCHEMA_VERSION,
			get_option( Report_History::SCHEMA_OPTION )
		);
	}

	/**
	 * Test needs_schema_update returns false after creation.
	 */
	public function test_needs_schema_update_false_after_create(): void {
		$this->assertFalse( Report_History::needs_schema_update() );
	}

	/**
	 * Test needs_schema_update returns true when version differs.
	 */
	public function test_needs_schema_update_true_when_outdated(): void {
		update_option( Report_History::SCHEMA_OPTION, '0.0.1' );
		$this->assertTrue( Report_History::needs_schema_update() );
	}

	/**
	 * Test saving a snapshot.
	 */
	public function test_save_snapshot(): void {
		$report = $this->create_sample_report();
		$id     = $this->history->save_snapshot( $report );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Test retrieving a snapshot.
	 */
	public function test_get_snapshot(): void {
		$report = $this->create_sample_report();
		$id     = $this->history->save_snapshot( $report );

		$snapshot = $this->history->get_snapshot( $id );

		$this->assertNotNull( $snapshot );
		$this->assertSame( $id, $snapshot['id'] );
		$this->assertIsInt( $snapshot['score'] );
		$this->assertIsString( $snapshot['grade'] );
		$this->assertIsArray( $snapshot['report'] );
		$this->assertArrayHasKey( 'security', $snapshot['report'] );
		$this->assertArrayHasKey( 'performance', $snapshot['report'] );
		$this->assertNotEmpty( $snapshot['created_at'] );
	}

	/**
	 * Test retrieving a non-existent snapshot returns null.
	 */
	public function test_get_nonexistent_snapshot(): void {
		$this->assertNull( $this->history->get_snapshot( 99999 ) );
	}

	/**
	 * Test listing snapshots.
	 */
	public function test_list_snapshots(): void {
		$report = $this->create_sample_report();
		$this->history->save_snapshot( $report );
		$this->history->save_snapshot( $report );
		$this->history->save_snapshot( $report );

		$result = $this->history->list_snapshots();

		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'pages', $result );
		$this->assertCount( 3, $result['items'] );
		$this->assertSame( 3, $result['total'] );
		$this->assertSame( 1, $result['pages'] );
	}

	/**
	 * Test listing snapshots respects pagination.
	 */
	public function test_list_snapshots_pagination(): void {
		$report = $this->create_sample_report();

		for ( $i = 0; $i < 5; $i++ ) {
			$this->history->save_snapshot( $report );
		}

		$result = $this->history->list_snapshots( array( 'per_page' => 2 ) );

		$this->assertCount( 2, $result['items'] );
		$this->assertSame( 5, $result['total'] );
		$this->assertSame( 3, $result['pages'] );

		// Get page 2.
		$page2 = $this->history->list_snapshots(
			array(
				'per_page' => 2,
				'page'     => 2,
			)
		);

		$this->assertCount( 2, $page2['items'] );
	}

	/**
	 * Test listing snapshots default order is DESC.
	 */
	public function test_list_snapshots_order_desc(): void {
		$report = $this->create_sample_report();
		$id1    = $this->history->save_snapshot( $report );
		$id2    = $this->history->save_snapshot( $report );

		$result = $this->history->list_snapshots();
		$items  = $result['items'];

		// DESC: most recent first.
		$this->assertSame( $id2, $items[0]['id'] );
		$this->assertSame( $id1, $items[1]['id'] );
	}

	/**
	 * Test listing snapshots with ASC order.
	 */
	public function test_list_snapshots_order_asc(): void {
		$report = $this->create_sample_report();
		$id1    = $this->history->save_snapshot( $report );
		$id2    = $this->history->save_snapshot( $report );

		$result = $this->history->list_snapshots( array( 'order' => 'ASC' ) );
		$items  = $result['items'];

		$this->assertSame( $id1, $items[0]['id'] );
		$this->assertSame( $id2, $items[1]['id'] );
	}

	/**
	 * Test snapshot metadata does not include report data.
	 */
	public function test_list_snapshots_excludes_report_data(): void {
		$report = $this->create_sample_report();
		$this->history->save_snapshot( $report );

		$result = $this->history->list_snapshots();
		$item   = $result['items'][0];

		$this->assertArrayNotHasKey( 'report', $item );
		$this->assertArrayNotHasKey( 'report_data', $item );
		$this->assertArrayHasKey( 'score', $item );
		$this->assertArrayHasKey( 'grade', $item );
		$this->assertArrayHasKey( 'summary_good', $item );
		$this->assertArrayHasKey( 'summary_warning', $item );
		$this->assertArrayHasKey( 'summary_critical', $item );
	}

	/**
	 * Test deleting a snapshot.
	 */
	public function test_delete_snapshot(): void {
		$report = $this->create_sample_report();
		$id     = $this->history->save_snapshot( $report );

		$this->assertTrue( $this->history->delete_snapshot( $id ) );
		$this->assertNull( $this->history->get_snapshot( $id ) );
	}

	/**
	 * Test deleting a non-existent snapshot.
	 */
	public function test_delete_nonexistent_snapshot(): void {
		$this->assertFalse( $this->history->delete_snapshot( 99999 ) );
	}

	/**
	 * Test deleting all snapshots.
	 */
	public function test_delete_all(): void {
		$report = $this->create_sample_report();
		$this->history->save_snapshot( $report );
		$this->history->save_snapshot( $report );
		$this->history->save_snapshot( $report );

		$deleted = $this->history->delete_all();

		$this->assertSame( 3, $deleted );

		$result = $this->history->list_snapshots();
		$this->assertSame( 0, $result['total'] );
	}

	/**
	 * Test get_latest returns most recent snapshot.
	 */
	public function test_get_latest(): void {
		$report = $this->create_sample_report();
		$this->history->save_snapshot( $report );
		$id2 = $this->history->save_snapshot( $report );

		$latest = $this->history->get_latest();

		$this->assertNotNull( $latest );
		$this->assertSame( $id2, $latest['id'] );
	}

	/**
	 * Test get_latest returns null when empty.
	 */
	public function test_get_latest_empty(): void {
		$this->assertNull( $this->history->get_latest() );
	}

	/**
	 * Test get_trend returns data points.
	 */
	public function test_get_trend(): void {
		$report = $this->create_sample_report();
		$this->history->save_snapshot( $report );
		$this->history->save_snapshot( $report );

		$trend = $this->history->get_trend( array( 'days' => 30 ) );

		$this->assertCount( 2, $trend );
		$this->assertArrayHasKey( 'id', $trend[0] );
		$this->assertArrayHasKey( 'score', $trend[0] );
		$this->assertArrayHasKey( 'grade', $trend[0] );
		$this->assertArrayHasKey( 'summary', $trend[0] );
		$this->assertArrayHasKey( 'created_at', $trend[0] );
	}

	/**
	 * Test trend data is ordered ascending.
	 */
	public function test_get_trend_ascending_order(): void {
		$report = $this->create_sample_report();
		$id1    = $this->history->save_snapshot( $report );
		$id2    = $this->history->save_snapshot( $report );

		$trend = $this->history->get_trend();

		$this->assertSame( $id1, $trend[0]['id'] );
		$this->assertSame( $id2, $trend[1]['id'] );
	}

	/**
	 * Test retention limit enforcement.
	 */
	public function test_retention_limit(): void {
		// Set a low retention limit for testing.
		add_filter( 'wp_system_report_retention_limit', fn() => 3 );

		$report = $this->create_sample_report();

		for ( $i = 0; $i < 5; $i++ ) {
			$this->history->save_snapshot( $report );
		}

		$result = $this->history->list_snapshots();
		$this->assertSame( 3, $result['total'] );
	}

	/**
	 * Test maybe_save_snapshot respects interval.
	 */
	public function test_maybe_save_snapshot_interval(): void {
		$report = $this->create_sample_report();

		// First save should succeed.
		$this->history->maybe_save_snapshot( $report );
		$result1 = $this->history->list_snapshots();
		$this->assertSame( 1, $result1['total'] );

		// Second save immediately should be throttled.
		$this->history->maybe_save_snapshot( $report );
		$result2 = $this->history->list_snapshots();
		$this->assertSame( 1, $result2['total'] );
	}

	/**
	 * Test snapshot stores correct summary counts.
	 */
	public function test_snapshot_summary_counts(): void {
		$report = $this->create_sample_report();
		$id     = $this->history->save_snapshot( $report );

		$snapshot = $this->history->get_snapshot( $id );

		// The sample report has 2 good, 1 warning, 0 critical.
		$this->assertSame( 2, $snapshot['summary_good'] );
		$this->assertSame( 1, $snapshot['summary_warning'] );
		$this->assertSame( 0, $snapshot['summary_critical'] );
	}

	/**
	 * Test compressed data round-trips correctly.
	 */
	public function test_data_compression_roundtrip(): void {
		$report = $this->create_sample_report();
		$id     = $this->history->save_snapshot( $report );

		$snapshot = $this->history->get_snapshot( $id );
		$restored = $snapshot['report'];

		// The report should have the same structure.
		$this->assertArrayHasKey( 'security', $restored );
		$this->assertArrayHasKey( 'performance', $restored );
		$this->assertSame( 'Security', $restored['security']['label'] );
	}

	/**
	 * Test snapshot_saved action fires.
	 */
	public function test_snapshot_saved_action(): void {
		$fired = false;

		add_action(
			'wp_system_report_snapshot_saved',
			function ( $id, $score_data ) use ( &$fired ) {
				$fired = true;
				$this->assertIsInt( $id );
				$this->assertArrayHasKey( 'score', $score_data );
				$this->assertArrayHasKey( 'grade', $score_data );
			},
			10,
			2
		);

		$report = $this->create_sample_report();
		$this->history->save_snapshot( $report );

		$this->assertTrue( $fired );
	}
}
