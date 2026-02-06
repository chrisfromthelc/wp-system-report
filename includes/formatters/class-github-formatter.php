<?php
/**
 * GitHub formatter.
 *
 * @package SystemReport
 */

namespace SystemReport\Formatters;

defined( 'ABSPATH' ) || exit;

/**
 * Formats the report for GitHub issues with redactions and details wrapper.
 *
 * Wraps the plain text report in HTML <details> tags and redacts
 * sensitive information like URLs and database details.
 */
class GitHub_Formatter implements Formatter {

	/**
	 * Format the report data for GitHub.
	 *
	 * @param array $report_data Full report data.
	 * @return string Formatted GitHub report.
	 */
	public function format( array $report_data ): string {
		$plain_formatter = new Plain_Text_Formatter();
		$report          = $plain_formatter->format( $report_data );

		// Apply redactions.
		$report = $this->apply_redactions( $report );

		// Wrap in GitHub details tags.
		return '<details><summary>System Status Report</summary>' . "\n\n"
			. '```' . "\n" . $report . '```' . "\n"
			. '</details>';
	}

	/**
 * Get the content type.
 */
	public function get_content_type(): string {
		return 'text/plain; charset=utf-8';
	}

	/**
 * Get the file extension.
 */
	public function get_file_extension(): string {
		return 'txt';
	}

	/**
	 * Apply redactions to the report text.
	 *
	 * Redacts URLs and database information for privacy.
	 *
	 * @param string $report Raw report text.
	 * @return string Redacted report text.
	 */
	private function apply_redactions( $report ) {
		$redactions = array(
			array(
				'pattern'     => '/(Home URL:)[^\n]*/',
				'replacement' => '$1 [Redacted]',
			),
			array(
				'pattern'     => '/(Site URL:)[^\n]*/',
				'replacement' => '$1 [Redacted]',
			),
			array(
				'pattern'     => '/(### Database ###\n)([\s\S]*?)(\n### )/',
				'replacement' => "$1\n[REDACTED]\n$3",
			),
		);

		/**
		 * Filter the redaction patterns for GitHub export.
		 *
		 * Each redaction is an array with 'pattern' and 'replacement' keys.
		 *
		 * @param array $redactions Array of redaction rules.
		 */
		$redactions = apply_filters( 'system_report_redactions', $redactions );

		foreach ( $redactions as $redaction ) {
			$report = preg_replace( $redaction['pattern'], $redaction['replacement'], $report );
		}

		return $report;
	}
}
