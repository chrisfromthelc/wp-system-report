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
		$this->assertStringContainsString( '# System Report for', $output );
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

		$this->assertStringContainsString( 'Report Version: ' . SYSTEM_REPORT_VERSION, $output );
	}

	/**
	 * Test AI issues filter works.
	 */
	public function test_ai_issues_filter() {
		add_filter(
			'system_report_ai_issues',
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

		$this->assertStringContainsString( 'No issues detected', $output );
	}
}
