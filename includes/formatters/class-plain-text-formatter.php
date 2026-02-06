<?php
/**
 * Plain text formatter.
 *
 * @package SystemReport
 */

namespace SystemReport\Formatters;

defined( 'ABSPATH' ) || exit;

/**
 * Formats the report as plain text using the WooCommerce-style format.
 *
 * Output format:
 * ### Section Name ###
 *
 * Label: Value
 */
class Plain_Text_Formatter implements Formatter {

	/**
	 * Format the report data as plain text.
	 *
	 * @param array $report_data Full report data.
	 * @return string Formatted plain text report.
	 */
	public function format( array $report_data ) {
		$output = '';

		foreach ( $report_data as $section ) {
			if ( empty( $section['fields'] ) ) {
				continue;
			}

			$output .= "\n### " . $section['label'] . " ###\n\n";

			foreach ( $section['fields'] as $field ) {
				if ( ! empty( $field['private'] ) ) {
					continue;
				}

				$label = ! empty( $field['export_label'] ) ? $field['export_label'] : $field['label'];
				$value = $this->format_value( $field );

				$output .= $label . ': ' . $value . "\n";
			}
		}

		return $output;
	}

	/**
	 * Get the content type.
	 *
	 * @return string
	 */
	public function get_content_type() {
		return 'text/plain; charset=utf-8';
	}

	/**
	 * Get the file extension.
	 *
	 * @return string
	 */
	public function get_file_extension() {
		return 'txt';
	}

	/**
	 * Format a field value for plain text output.
	 *
	 * Converts status indicators to unicode symbols.
	 *
	 * @param array $field Field data.
	 * @return string Formatted value.
	 */
	private function format_value( $field ) {
		$value  = $field['value'];
		$status = ! empty( $field['status'] ) ? $field['status'] : 'info';

		// Prepend status symbol for non-info statuses.
		if ( 'good' === $status ) {
			$value = "\xE2\x9C\x94 " . $value; // ✔
		} elseif ( 'critical' === $status || 'warning' === $status ) {
			$value = "\xE2\x9D\x8C " . $value; // ❌
		}

		return $value;
	}
}
