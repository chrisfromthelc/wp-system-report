<?php
/**
 * Formatter interface.
 *
 * @package SystemReport
 */

namespace SystemReport\Formatters;

defined( 'ABSPATH' ) || exit;

/**
 * Interface for report formatters.
 *
 * Each formatter transforms the structured report data into a specific
 * output format (plain text, GitHub markdown, AI-optimized markdown, etc.).
 */
interface Formatter {

	/**
	 * Format the report data into the target format.
	 *
	 * @param array $report_data Full report data from Report_Generator::generate().
	 * @return string Formatted report output.
	 */
	public function format( array $report_data );

	/**
	 * Get the MIME content type for the formatted output.
	 *
	 * @return string Content type, e.g., 'text/plain', 'text/markdown'.
	 */
	public function get_content_type();

	/**
	 * Get the file extension for downloads.
	 *
	 * @return string File extension, e.g., 'txt', 'md'.
	 */
	public function get_file_extension();
}
