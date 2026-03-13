<?php
/**
 * Formatter tests.
 *
 * @package SystemReport
 */

use SystemReport\Formatters\Plain_Text_Formatter;
use SystemReport\Formatters\GitHub_Formatter;
use SystemReport\Formatters\AI_Formatter;

/**
 * Test formatter output.
 */
class FormattersTest extends WP_UnitTestCase {

	/**
	 * Sample report data for testing.
	 *
	 * @var array
	 */
	private $sample_report;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->sample_report = array(
			'test_section' => array(
				'id'          => 'test_section',
				'label'       => 'Test Section',
				'description' => 'A section for testing.',
				'fields'      => array(
					array(
						'label'        => 'WordPress Version',
						'value'        => '6.9.1',
						'debug'        => '6.9.1',
						'private'      => false,
						'status'       => 'good',
						'description'  => '',
						'recommended'  => '>= 6.4',
						'export_label' => 'WP Version',
					),
					array(
						'label'        => 'PHP Memory Limit',
						'value'        => '256M',
						'debug'        => '256M',
						'private'      => false,
						'status'       => 'good',
						'description'  => '',
						'recommended'  => '>= 128M',
						'export_label' => 'PHP Memory',
					),
					array(
						'label'        => 'HTTPS',
						'value'        => 'No',
						'debug'        => false,
						'private'      => false,
						'status'       => 'critical',
						'description'  => 'Site is not using HTTPS.',
						'recommended'  => 'Yes',
						'export_label' => 'HTTPS',
					),
					array(
						'label'        => 'Secret Key',
						'value'        => 'abc123',
						'debug'        => 'abc123',
						'private'      => true,
						'status'       => 'info',
						'description'  => '',
						'recommended'  => '',
						'export_label' => 'Secret Key',
					),
				),
			),
			'empty_section' => array(
				'id'          => 'empty_section',
				'label'       => 'Empty Section',
				'description' => 'This section has no fields.',
				'fields'      => array(),
			),
		);
	}

	// ---- Plain Text Formatter ----

	/**
	 * Test plain text format contains section headers.
	 */
	public function test_plain_text_contains_section_headers() {
		$formatter = new Plain_Text_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringContainsString( '### Test Section ###', $output );
	}

	/**
	 * Test plain text format contains field labels and values.
	 */
	public function test_plain_text_contains_fields() {
		$formatter = new Plain_Text_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringContainsString( 'WP Version:', $output );
		$this->assertStringContainsString( '6.9.1', $output );
	}

	/**
	 * Test plain text uses export_label when available.
	 */
	public function test_plain_text_uses_export_label() {
		$formatter = new Plain_Text_Formatter();
		$output    = $formatter->format( $this->sample_report );

		// Should use export_label "WP Version" instead of "WordPress Version".
		$this->assertStringContainsString( 'WP Version:', $output );
		$this->assertStringNotContainsString( 'WordPress Version:', $output );
	}

	/**
	 * Test plain text excludes private fields.
	 */
	public function test_plain_text_excludes_private_fields() {
		$formatter = new Plain_Text_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringNotContainsString( 'Secret Key', $output );
		$this->assertStringNotContainsString( 'abc123', $output );
	}

	/**
	 * Test plain text skips empty sections.
	 */
	public function test_plain_text_skips_empty_sections() {
		$formatter = new Plain_Text_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringNotContainsString( '### Empty Section ###', $output );
	}

	/**
	 * Test plain text content type.
	 */
	public function test_plain_text_content_type() {
		$formatter = new Plain_Text_Formatter();
		$this->assertSame( 'text/plain; charset=utf-8', $formatter->get_content_type() );
	}

	/**
	 * Test plain text file extension.
	 */
	public function test_plain_text_file_extension() {
		$formatter = new Plain_Text_Formatter();
		$this->assertSame( 'txt', $formatter->get_file_extension() );
	}

	/**
	 * Test plain text shows status indicators.
	 */
	public function test_plain_text_shows_status_indicators() {
		$formatter = new Plain_Text_Formatter();
		$output    = $formatter->format( $this->sample_report );

		// Good status should show checkmark.
		$this->assertStringContainsString( "\xE2\x9C\x94", $output );
		// Critical status should show X.
		$this->assertStringContainsString( "\xE2\x9D\x8C", $output );
	}

	// ---- GitHub Formatter ----

	/**
	 * Test GitHub format wraps in details tags.
	 */
	public function test_github_wraps_in_details_tags() {
		$formatter = new GitHub_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringStartsWith( '<details>', $output );
		$this->assertStringContainsString( '</details>', $output );
		$this->assertStringContainsString( '<summary>System Status Report</summary>', $output );
	}

	/**
	 * Test GitHub format wraps content in code block.
	 */
	public function test_github_wraps_in_code_block() {
		$formatter = new GitHub_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringContainsString( '```', $output );
	}

	/**
	 * Test GitHub format content type.
	 */
	public function test_github_content_type() {
		$formatter = new GitHub_Formatter();
		$this->assertSame( 'text/plain; charset=utf-8', $formatter->get_content_type() );
	}

	/**
	 * Test GitHub format file extension.
	 */
	public function test_github_file_extension() {
		$formatter = new GitHub_Formatter();
		$this->assertSame( 'txt', $formatter->get_file_extension() );
	}

	/**
	 * Test GitHub format applies URL redactions.
	 */
	public function test_github_applies_url_redactions() {
		// Create report data with Home URL and Site URL.
		$report = array(
			'wp_env' => array(
				'id'          => 'wp_env',
				'label'       => 'WordPress Environment',
				'description' => '',
				'fields'      => array(
					array(
						'label'        => 'Home URL',
						'value'        => 'https://example.com',
						'debug'        => 'https://example.com',
						'private'      => false,
						'status'       => 'info',
						'description'  => '',
						'recommended'  => '',
						'export_label' => 'Home URL',
					),
					array(
						'label'        => 'Site URL',
						'value'        => 'https://example.com',
						'debug'        => 'https://example.com',
						'private'      => false,
						'status'       => 'info',
						'description'  => '',
						'recommended'  => '',
						'export_label' => 'Site URL',
					),
				),
			),
		);

		$formatter = new GitHub_Formatter();
		$output    = $formatter->format( $report );

		$this->assertStringContainsString( '[Redacted]', $output );
		$this->assertStringNotContainsString( 'https://example.com', $output );
	}

	// ---- AI Formatter ----

	/**
	 * Test AI format produces markdown.
	 */
	public function test_ai_format_produces_markdown() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		// Should start with a header.
		$this->assertStringContainsString( '# WP System Report for', $output );
	}

	/**
	 * Test AI format includes issues summary.
	 */
	public function test_ai_format_includes_issues_summary() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringContainsString( '## Potential Issues Detected', $output );
	}

	/**
	 * Test AI format detects critical issues.
	 */
	public function test_ai_format_detects_critical_issues() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		// The HTTPS field is critical, should appear in issues.
		$this->assertStringContainsString( 'HTTPS', $output );
		$this->assertStringContainsString( 'CRITICAL', $output );
	}

	/**
	 * Test AI format includes section tables.
	 */
	public function test_ai_format_includes_section_tables() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		// Should have table headers.
		$this->assertStringContainsString( '| Setting | Value |', $output );
		// Should have section description as blockquote.
		$this->assertStringContainsString( '> A section for testing.', $output );
	}

	/**
	 * Test AI format has recommended column when fields have recommendations.
	 */
	public function test_ai_format_includes_recommended_column() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringContainsString( '| Recommended |', $output );
		$this->assertStringContainsString( '>= 6.4', $output );
	}

	/**
	 * Test AI format content type.
	 */
	public function test_ai_content_type() {
		$formatter = new AI_Formatter();
		$this->assertSame( 'text/markdown; charset=utf-8', $formatter->get_content_type() );
	}

	/**
	 * Test AI format file extension.
	 */
	public function test_ai_file_extension() {
		$formatter = new AI_Formatter();
		$this->assertSame( 'md', $formatter->get_file_extension() );
	}

	/**
	 * Test AI format excludes private fields.
	 */
	public function test_ai_format_excludes_private_fields() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringNotContainsString( 'Secret Key', $output );
	}

	/**
	 * Test AI format skips empty sections.
	 */
	public function test_ai_format_skips_empty_sections() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringNotContainsString( '## Empty Section', $output );
	}

	/**
	 * Test AI format includes report version.
	 */
	public function test_ai_format_includes_report_version() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringContainsString( 'Report Version: ' . WP_SYSTEM_REPORT_VERSION, $output );
	}

	/**
	 * Test AI issues filter works.
	 */
	public function test_ai_issues_filter() {
		add_filter(
			'wp_system_report_ai_issues',
			function ( $issues ) {
				$issues[] = array(
					'severity'    => 'warning',
					'title'       => 'Custom Issue',
					'description' => 'A custom issue from a filter.',
				);
				return $issues;
			}
		);

		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringContainsString( 'Custom Issue', $output );
	}

	/**
	 * Test that no issues produces appropriate message.
	 *
	 * On PHP < 8.1 the heuristic check flags the running PHP version as
	 * end-of-life, so the "no issues" message won't appear. We assert the
	 * correct behaviour for each PHP range instead.
	 */
	public function test_ai_no_issues_message() {
		$clean_report = array(
			'clean' => array(
				'id'          => 'clean',
				'label'       => 'Clean Section',
				'description' => 'All good.',
				'fields'      => array(
					array(
						'label'        => 'Status',
						'value'        => 'OK',
						'debug'        => 'ok',
						'private'      => false,
						'status'       => 'good',
						'description'  => '',
						'recommended'  => '',
						'export_label' => 'Status',
					),
				),
			),
		);

		$formatter = new AI_Formatter();
		$output    = $formatter->format( $clean_report );

		if ( version_compare( phpversion(), '8.1', '<' ) ) {
			$this->assertStringContainsString( 'end-of-life', $output );
			$this->assertStringNotContainsString( 'No issues detected', $output );
		} else {
			$this->assertStringContainsString( 'No issues detected', $output );
		}
	}

	/**
	 * Test AI format includes executive summary.
	 */
	public function test_ai_format_includes_executive_summary() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringContainsString( '## Executive Summary', $output );
		$this->assertStringContainsString( 'Health Score:', $output );
	}

	/**
	 * Test AI executive summary health score reflects issues.
	 */
	public function test_ai_executive_summary_health_score() {
		// Report with 1 critical field: field scoring gives 0 pts / 1 scored field = 0/100.
		$report = array(
			'section' => array(
				'id'          => 'section',
				'label'       => 'Section',
				'description' => '',
				'fields'      => array(
					array(
						'label'   => 'HTTPS',
						'value'   => 'No',
						'debug'   => false,
						'private' => false,
						'status'  => 'critical',
					),
				),
			),
		);

		$formatter = new AI_Formatter();
		$output    = $formatter->format( $report );

		$this->assertStringContainsString( 'Health Score: 0/100', $output );
	}

	/**
	 * Test AI format perfect health score for clean report.
	 */
	public function test_ai_perfect_health_score() {
		$clean_report = array(
			'clean' => array(
				'id'          => 'clean',
				'label'       => 'Clean',
				'description' => '',
				'fields'      => array(
					array(
						'label'   => 'Status',
						'value'   => 'OK',
						'debug'   => 'ok',
						'private' => false,
						'status'  => 'good',
					),
				),
			),
		);

		$formatter = new AI_Formatter();
		$output    = $formatter->format( $clean_report );

		// 1 good field = 100 pts / 1 scored field = 100/100 (A+).
		$this->assertStringContainsString( 'Health Score: 100/100', $output );
		$this->assertStringContainsString( 'A+', $output );
	}

	/**
	 * Test AI format includes severity scores in issues.
	 */
	public function test_ai_format_includes_severity_scores() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		// The HTTPS critical issue should have severity: 10.
		$this->assertStringContainsString( '(severity: 10)', $output );
	}

	/**
	 * Test AI format categorizes issues.
	 */
	public function test_ai_format_categorizes_issues() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		// HTTPS is categorized under Security.
		$this->assertStringContainsString( '### Security', $output );
	}

	/**
	 * Test AI format includes top priorities.
	 */
	public function test_ai_format_includes_top_priorities() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringContainsString( 'Top Priorities:', $output );
	}

	/**
	 * Test AI format shows fix_id when present.
	 */
	public function test_ai_format_shows_fix_id() {
		$report = array(
			'section' => array(
				'id'          => 'section',
				'label'       => 'Section',
				'description' => '',
				'fields'      => array(
					array(
						'label'   => 'File Editor',
						'value'   => 'Enabled',
						'debug'   => true,
						'private' => false,
						'status'  => 'warning',
						'fix_id'  => 'disable_file_editor',
					),
				),
			),
		);

		$formatter = new AI_Formatter();
		$output    = $formatter->format( $report );

		$this->assertStringContainsString( '[fix: disable_file_editor]', $output );
	}

	/**
	 * Test AI executive summary filter.
	 */
	public function test_ai_executive_summary_filter() {
		add_filter(
			'wp_system_report_ai_executive_summary',
			function ( $output ) {
				return $output . "Custom summary note.\n";
			}
		);

		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		$this->assertStringContainsString( 'Custom summary note.', $output );
	}

	/**
	 * Test AI format includes issue counts in executive summary.
	 */
	public function test_ai_executive_summary_issue_counts() {
		$formatter = new AI_Formatter();
		$output    = $formatter->format( $this->sample_report );

		// Should report 1 critical issue (HTTPS).
		$this->assertStringContainsString( '1 critical issue(s)', $output );
	}

	/**
	 * Test AI format email heuristic detects PHP mail.
	 */
	public function test_ai_email_heuristic_detects_php_mail() {
		$report = array(
			'email_delivery' => array(
				'id'          => 'email_delivery',
				'label'       => 'Email Delivery',
				'description' => '',
				'fields'      => array(
					array(
						'label'   => 'Mail Transport',
						'value'   => 'PHP mail()',
						'debug'   => 'php_mail',
						'private' => false,
						'status'  => 'good',
					),
				),
			),
		);

		$formatter = new AI_Formatter();
		$output    = $formatter->format( $report );

		$this->assertStringContainsString( 'default PHP mail()', $output );
		$this->assertStringContainsString( 'SMTP plugin', $output );
	}

	/**
	 * Test AI format block editor heuristic detects excessive blocks.
	 */
	public function test_ai_editor_bloat_heuristic() {
		$report = array(
			'block_editor' => array(
				'id'          => 'block_editor',
				'label'       => 'Block Editor',
				'description' => '',
				'fields'      => array(
					array(
						'label'   => 'Registered Block Types',
						'value'   => '550',
						'debug'   => 550,
						'private' => false,
						'status'  => 'good',
					),
				),
			),
		);

		$formatter = new AI_Formatter();
		$output    = $formatter->format( $report );

		$this->assertStringContainsString( '550 block types registered', $output );
	}
}
