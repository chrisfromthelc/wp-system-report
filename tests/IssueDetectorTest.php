<?php
/**
 * Issue Detector tests.
 *
 * @package SystemReport
 */

use SystemReport\Issue_Detector;

/**
 * Test issue detection logic.
 */
class IssueDetectorTest extends WP_UnitTestCase {

	/**
	 * Issue detector instance.
	 *
	 * @var Issue_Detector
	 */
	private $detector;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		$this->detector = new Issue_Detector();
	}

	// ---------------------------------------------------------------
	// Helper to build report data
	// ---------------------------------------------------------------

	/**
	 * Build a minimal report section with a single field.
	 *
	 * @param array $field_overrides Field array overrides.
	 * @return array Report data with one section.
	 */
	private function make_report( array $field_overrides = array() ): array {
		$field = array_merge(
			array(
				'label'        => 'Test Field',
				'value'        => 'test_value',
				'debug'        => 'test_value',
				'private'      => false,
				'status'       => 'info',
				'description'  => '',
				'recommended'  => '',
				'export_label' => 'Test Field',
			),
			$field_overrides
		);

		return array(
			'test_section' => array(
				'id'          => 'test_section',
				'label'       => 'Test Section',
				'description' => 'A test section.',
				'fields'      => array( $field ),
			),
		);
	}

	// ---------------------------------------------------------------
	// Detection behavior
	// ---------------------------------------------------------------

	/**
	 * Test that critical status fields are detected as issues.
	 */
	public function test_detects_critical_status_fields(): void {
		$report = $this->make_report( array( 'status' => 'critical' ) );
		$issues = $this->detector->detect( $report );

		$field_issues = array_filter(
			$issues,
			function ( $issue ) {
				return 'Test Field' === $issue['title'];
			}
		);

		$this->assertNotEmpty( $field_issues );
		$issue = array_values( $field_issues )[0];
		$this->assertSame( 'critical', $issue['severity'] );
	}

	/**
	 * Test that warning status fields are detected as issues.
	 */
	public function test_detects_warning_status_fields(): void {
		$report = $this->make_report( array( 'status' => 'warning' ) );
		$issues = $this->detector->detect( $report );

		$field_issues = array_filter(
			$issues,
			function ( $issue ) {
				return 'Test Field' === $issue['title'];
			}
		);

		$this->assertNotEmpty( $field_issues );
		$issue = array_values( $field_issues )[0];
		$this->assertSame( 'warning', $issue['severity'] );
	}

	/**
	 * Test that good status fields are NOT detected as issues.
	 */
	public function test_skips_good_status_fields(): void {
		$report = $this->make_report( array( 'status' => 'good' ) );
		$issues = $this->detector->detect( $report );

		$field_issues = array_filter(
			$issues,
			function ( $issue ) {
				return 'Test Field' === $issue['title'];
			}
		);

		$this->assertEmpty( $field_issues );
	}

	/**
	 * Test that info status fields are NOT detected as issues.
	 */
	public function test_skips_info_status_fields(): void {
		$report = $this->make_report( array( 'status' => 'info' ) );
		$issues = $this->detector->detect( $report );

		$field_issues = array_filter(
			$issues,
			function ( $issue ) {
				return 'Test Field' === $issue['title'];
			}
		);

		$this->assertEmpty( $field_issues );
	}

	/**
	 * Test that private fields are excluded from issues.
	 */
	public function test_skips_private_fields(): void {
		$report = $this->make_report(
			array(
				'status'  => 'critical',
				'private' => true,
			)
		);
		$issues = $this->detector->detect( $report );

		$field_issues = array_filter(
			$issues,
			function ( $issue ) {
				return 'Test Field' === $issue['title'];
			}
		);

		$this->assertEmpty( $field_issues );
	}

	/**
	 * Test that fields with no status key are excluded.
	 */
	public function test_skips_empty_status_fields(): void {
		$report = array(
			'test_section' => array(
				'id'          => 'test_section',
				'label'       => 'Test Section',
				'description' => '',
				'fields'      => array(
					array(
						'label'   => 'No Status Field',
						'value'   => 'test',
						'debug'   => 'test',
						'private' => false,
					),
				),
			),
		);

		$issues       = $this->detector->detect( $report );
		$field_issues = array_filter(
			$issues,
			function ( $issue ) {
				return 'No Status Field' === $issue['title'];
			}
		);

		$this->assertEmpty( $field_issues );
	}

	// ---------------------------------------------------------------
	// Issue structure
	// ---------------------------------------------------------------

	/**
	 * Test that each issue has the required keys.
	 */
	public function test_issue_has_required_keys(): void {
		$report = $this->make_report( array( 'status' => 'critical' ) );
		$issues = $this->detector->detect( $report );

		$field_issues = array_filter(
			$issues,
			function ( $issue ) {
				return 'Test Field' === $issue['title'];
			}
		);

		$this->assertNotEmpty( $field_issues );
		$issue = array_values( $field_issues )[0];

		$this->assertArrayHasKey( 'severity', $issue );
		$this->assertArrayHasKey( 'title', $issue );
		$this->assertArrayHasKey( 'description', $issue );
	}

	/**
	 * Test that issue description includes the current value.
	 */
	public function test_issue_description_includes_current_value(): void {
		$report = $this->make_report(
			array(
				'status' => 'warning',
				'value'  => 'my_test_value',
			)
		);
		$issues = $this->detector->detect( $report );

		$field_issues = array_filter(
			$issues,
			function ( $issue ) {
				return 'Test Field' === $issue['title'];
			}
		);

		$issue = array_values( $field_issues )[0];
		$this->assertStringContainsString( 'my_test_value', $issue['description'] );
	}

	/**
	 * Test that issue description includes recommended value when present.
	 */
	public function test_issue_description_includes_recommended_when_present(): void {
		$report = $this->make_report(
			array(
				'status'      => 'warning',
				'recommended' => '>= 8.1',
			)
		);
		$issues = $this->detector->detect( $report );

		$field_issues = array_filter(
			$issues,
			function ( $issue ) {
				return 'Test Field' === $issue['title'];
			}
		);

		$issue = array_values( $field_issues )[0];
		$this->assertStringContainsString( '>= 8.1', $issue['description'] );
	}

	/**
	 * Test that issue description includes field description.
	 */
	public function test_issue_description_includes_field_description(): void {
		$report = $this->make_report(
			array(
				'status'      => 'critical',
				'description' => 'This is a known problem.',
			)
		);
		$issues = $this->detector->detect( $report );

		$field_issues = array_filter(
			$issues,
			function ( $issue ) {
				return 'Test Field' === $issue['title'];
			}
		);

		$issue = array_values( $field_issues )[0];
		$this->assertStringContainsString( 'This is a known problem.', $issue['description'] );
	}

	// ---------------------------------------------------------------
	// Heuristic checks
	// ---------------------------------------------------------------

	/**
	 * Test PHP EOL heuristic check.
	 *
	 * On PHP < 8.1 this should detect a critical issue.
	 * On PHP >= 8.1 this check should not trigger.
	 */
	public function test_heuristic_php_eol_on_old_version(): void {
		$report = array(); // Empty report — heuristics still run.
		$issues = $this->detector->detect( $report );

		$php_issues = array_filter(
			$issues,
			function ( $issue ) {
				return false !== strpos( $issue['title'], 'end-of-life' );
			}
		);

		if ( version_compare( phpversion(), '8.1', '<' ) ) {
			$this->assertNotEmpty( $php_issues, 'PHP < 8.1 should trigger EOL warning' );
			$issue = array_values( $php_issues )[0];
			$this->assertSame( 'critical', $issue['severity'] );
		} else {
			$this->assertEmpty( $php_issues, 'PHP >= 8.1 should not trigger EOL warning' );
		}
	}

	/**
	 * Test object cache heuristic when many plugins are active.
	 */
	public function test_heuristic_no_object_cache_many_plugins(): void {
		// Simulate >15 active plugins.
		$plugins = array_fill( 0, 20, 'fake-plugin/fake-plugin.php' );
		update_option( 'active_plugins', $plugins );

		$report = array();
		$issues = $this->detector->detect( $report );

		$cache_issues = array_filter(
			$issues,
			function ( $issue ) {
				return false !== strpos( $issue['title'], 'object cache' );
			}
		);

		// Only expect the warning if external object cache is NOT in use.
		if ( ! wp_using_ext_object_cache() ) {
			$this->assertNotEmpty( $cache_issues );
			$issue = array_values( $cache_issues )[0];
			$this->assertSame( 'warning', $issue['severity'] );
		} else {
			$this->assertEmpty( $cache_issues );
		}
	}

	/**
	 * Test non-InnoDB tables heuristic.
	 */
	public function test_heuristic_non_innodb_tables(): void {
		$report = array(
			'database' => array(
				'id'          => 'database',
				'label'       => 'Database',
				'description' => '',
				'fields'      => array(
					array(
						'label'   => 'wp_options',
						'value'   => 'Rows: 500 | Size: 2 MB | Engine: MyISAM',
						'debug'   => '',
						'private' => false,
						'status'  => 'info',
					),
				),
			),
		);

		$issues = $this->detector->detect( $report );

		$innodb_issues = array_filter(
			$issues,
			function ( $issue ) {
				return false !== strpos( $issue['title'], 'InnoDB' );
			}
		);

		$this->assertNotEmpty( $innodb_issues );
		$issue = array_values( $innodb_issues )[0];
		$this->assertSame( 'warning', $issue['severity'] );
		$this->assertStringContainsString( '1', $issue['title'] );
	}

	// ---------------------------------------------------------------
	// Edge cases
	// ---------------------------------------------------------------

	/**
	 * Test empty report returns no field-level issues.
	 *
	 * Heuristic checks may still produce issues depending on the environment.
	 */
	public function test_empty_report_returns_no_field_issues(): void {
		$report = array();
		$issues = $this->detector->detect( $report );

		// Filter out heuristic issues to check field-level only.
		$field_issues = array_filter(
			$issues,
			function ( $issue ) {
				// Heuristic issues have specific titles we can exclude.
				return false === strpos( $issue['title'], 'end-of-life' )
					&& false === strpos( $issue['title'], 'object cache' )
					&& false === strpos( $issue['title'], 'InnoDB' );
			}
		);

		$this->assertEmpty( $field_issues );
	}

	/**
	 * Test InnoDB heuristic handles database section without fields key.
	 */
	public function test_heuristic_innodb_skips_section_without_fields(): void {
		$report = array(
			'database' => array(
				'id'          => 'database',
				'label'       => 'Database',
				'description' => '',
				// No 'fields' key at all.
			),
		);

		$issues = $this->detector->detect( $report );

		$innodb_issues = array_filter(
			$issues,
			function ( $issue ) {
				return false !== strpos( $issue['title'], 'InnoDB' );
			}
		);

		$this->assertEmpty( $innodb_issues, 'Should not crash or produce InnoDB issues when fields key is missing' );
	}

	/**
	 * Test InnoDB heuristic handles fields with non-string value.
	 */
	public function test_heuristic_innodb_skips_non_string_field_value(): void {
		$report = array(
			'database' => array(
				'id'          => 'database',
				'label'       => 'Database',
				'description' => '',
				'fields'      => array(
					array(
						'label'   => 'Broken Field',
						'value'   => array( 'not', 'a', 'string' ),
						'debug'   => '',
						'private' => false,
						'status'  => 'info',
					),
					array(
						'label'   => 'Empty Value Field',
						'value'   => '',
						'debug'   => '',
						'private' => false,
						'status'  => 'info',
					),
				),
			),
		);

		$issues = $this->detector->detect( $report );

		$innodb_issues = array_filter(
			$issues,
			function ( $issue ) {
				return false !== strpos( $issue['title'], 'InnoDB' );
			}
		);

		$this->assertEmpty( $innodb_issues, 'Should skip fields with non-string or empty values without errors' );
	}

	/**
	 * Test multiple issues from the same section are all detected.
	 */
	public function test_multiple_issues_from_same_section(): void {
		$report = array(
			'test_section' => array(
				'id'          => 'test_section',
				'label'       => 'Test Section',
				'description' => '',
				'fields'      => array(
					array(
						'label'   => 'Issue A',
						'value'   => 'bad',
						'debug'   => 'bad',
						'private' => false,
						'status'  => 'critical',
					),
					array(
						'label'   => 'Issue B',
						'value'   => 'not great',
						'debug'   => 'not great',
						'private' => false,
						'status'  => 'warning',
					),
					array(
						'label'   => 'Fine',
						'value'   => 'ok',
						'debug'   => 'ok',
						'private' => false,
						'status'  => 'good',
					),
				),
			),
		);

		$issues = $this->detector->detect( $report );

		$titles = array_column( $issues, 'title' );
		$this->assertContains( 'Issue A', $titles );
		$this->assertContains( 'Issue B', $titles );
		$this->assertNotContains( 'Fine', $titles );
	}
}
