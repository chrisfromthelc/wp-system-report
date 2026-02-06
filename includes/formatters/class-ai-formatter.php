<?php
/**
 * AI-optimized formatter.
 *
 * @package SystemReport
 */

namespace SystemReport\Formatters;

defined( 'ABSPATH' ) || exit;

/**
 * Formats the report as structured markdown optimized for AI analysis.
 *
 * Produces a detailed report with contextual descriptions, status indicators,
 * recommended values, and a proactive issues summary. Designed for consumption
 * by AI agents like Claude, ChatGPT, or similar LLMs.
 */
class AI_Formatter implements Formatter {

	/**
	 * Format the report data as AI-optimized markdown.
	 *
	 * @param array $report_data Full report data.
	 * @return string Formatted markdown report.
	 */
	public function format( array $report_data ): string {
		$output  = $this->build_header();
		$output .= $this->build_issues_summary( $report_data );

		return $output . $this->build_sections( $report_data );
	}

	/**
 * Get the content type.
 */
	public function get_content_type(): string {
		return 'text/markdown; charset=utf-8';
	}

	/**
 * Get the file extension.
 */
	public function get_file_extension(): string {
		return 'md';
	}

	/**
	 * Build the report header with key metadata.
	 *
	 * @return string Header markdown.
	 */
	private function build_header() {
		$site_url = get_option( 'home' );
		$host     = wp_parse_url( $site_url, PHP_URL_HOST );
		$wp_ver   = get_bloginfo( 'version' );
		$php_ver  = phpversion();
		$now      = gmdate( 'Y-m-d H:i:s' ) . ' UTC';

		/**
		 * Filter the AI report header.
		 *
		 * @param string $header The markdown header string.
		 */
		$header = apply_filters(
			'system_report_ai_header',
			"# System Report for {$host}\n"
			. "Generated: {$now} | WP {$wp_ver} | PHP {$php_ver}\n"
			. 'Report Version: ' . SYSTEM_REPORT_VERSION . "\n\n"
			. "---\n\n"
		);

		return $header;
	}

	/**
	 * Build the issues summary section.
	 *
	 * Scans all report data for warnings and critical issues, producing
	 * a prioritized list at the top of the report.
	 *
	 * @param array $report_data Full report data.
	 * @return string Issues summary markdown.
	 */
	private function build_issues_summary( array $report_data ): string {
		$issues = $this->detect_issues( $report_data );

		/**
		 * Filter the detected issues for the AI report.
		 *
		 * @param array $issues      Array of issue arrays with 'severity', 'title', 'description' keys.
		 * @param array $report_data The full report data.
		 */
		$issues = apply_filters( 'system_report_ai_issues', $issues, $report_data );

		if ( empty( $issues ) ) {
			return "## Potential Issues Detected\n\nNo issues detected. The site appears to be well-configured.\n\n---\n\n";
		}

		$output = "## Potential Issues Detected\n\n";

		// Sort by severity: critical first, then warning.
		usort(
			$issues,
			function ( array $a, array $b ): int {
				$order   = array(
					'critical' => 0,
					'warning'  => 1,
					'info'     => 2,
				);
				$a_order = isset( $order[ $a['severity'] ] ) ? $order[ $a['severity'] ] : 2;
				$b_order = isset( $order[ $b['severity'] ] ) ? $order[ $b['severity'] ] : 2;
				return $a_order - $b_order;
			}
		);

		$i = 1;
		foreach ( $issues as $issue ) {
			$icon    = 'critical' === $issue['severity'] ? '🔴' : '🟡';
			$output .= "{$i}. {$icon} **{$issue['title']}** - {$issue['description']}\n";
			++$i;
		}

		return $output . "\n---\n\n";
	}

	/**
	 * Build all report sections as structured markdown tables.
	 *
	 * @param array $report_data Full report data.
	 * @return string Sections markdown.
	 */
	private function build_sections( array $report_data ): string {
		$output = '';

		foreach ( $report_data as $section ) {
			if ( empty( $section['fields'] ) ) {
				continue;
			}

			$output .= '## ' . $section['label'] . "\n\n";

			// Add section description as blockquote for AI context.
			if ( ! empty( $section['description'] ) ) {
				$output .= '> ' . $section['description'] . "\n\n";
			}

			// Determine if any fields have recommendations.
			$has_recommended = false;
			foreach ( $section['fields'] as $field ) {
				if ( ! empty( $field['private'] ) ) {
					continue;
				}
				if ( ! empty( $field['recommended'] ) ) {
					$has_recommended = true;
					break;
				}
			}

			// Build table header.
			if ( $has_recommended ) {
				$output .= "| Setting | Value | Status | Recommended |\n";
				$output .= "|---------|-------|--------|-------------|\n";
			} else {
				$output .= "| Setting | Value | Status |\n";
				$output .= "|---------|-------|--------|\n";
			}

			// Build table rows.
			foreach ( $section['fields'] as $field ) {
				if ( ! empty( $field['private'] ) ) {
					continue;
				}

				$label  = $this->escape_markdown( $field['label'] );
				$value  = $this->escape_markdown( $field['value'] );
				$status = $this->format_status( $field );

				if ( $has_recommended ) {
					$recommended = ! empty( $field['recommended'] ) ? $this->escape_markdown( $field['recommended'] ) : '-';
					$output     .= "| {$label} | {$value} | {$status} | {$recommended} |\n";
				} else {
					$output .= "| {$label} | {$value} | {$status} |\n";
				}
			}

			$output .= "\n";
		}

		return $output;
	}

	/**
	 * Format a field's status as a readable string.
	 *
	 * @param array $field Field data.
	 * @return string Status indicator.
	 */
	private function format_status( $field ): string {
		$status = ! empty( $field['status'] ) ? $field['status'] : 'info';

		switch ( $status ) {
			case 'good':
				return 'OK';
			case 'warning':
				return 'WARNING';
			case 'critical':
				return 'CRITICAL';
			default:
				return '-';
		}
	}

	/**
	 * Escape markdown special characters in a string.
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	private function escape_markdown( $text ) {
		// Replace pipe characters which break table formatting.
		$text = str_replace( '|', '\\|', $text );
		// Replace newlines with spaces for table cells.
		$text = str_replace( array( "\r\n", "\r", "\n" ), ' ', $text );
		return $text;
	}

	/**
	 * Detect issues from the report data.
	 *
	 * Scans all fields for warnings and critical statuses, and runs
	 * additional heuristic checks for common problems.
	 *
	 * @param array $report_data Full report data.
	 * @return array Array of issue arrays with 'severity', 'title', 'description' keys.
	 */
	private function detect_issues( array $report_data ): array {
		$issues = array();

		// Scan for fields with warning/critical status.
		foreach ( $report_data as $section ) {
			if ( empty( $section['fields'] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field ) {
				if ( empty( $field['status'] ) ) {
					continue;
				}
				if ( 'info' === $field['status'] ) {
					continue;
				}
				if ( 'good' === $field['status'] ) {
					continue;
				}
				if ( ! empty( $field['private'] ) ) {
					continue;
				}

				$issues[] = array(
					'severity'    => $field['status'],
					'title'       => $field['label'],
					'description' => $this->build_issue_description( $field ),
				);
			}
		}

		// Run additional heuristic checks.
		$issues = array_merge( $issues, $this->run_heuristic_checks( $report_data ) );

		return $issues;
	}

	/**
	 * Build a descriptive issue message from a field.
	 *
	 * @param array $field Field data.
	 * @return string Issue description.
	 */
	private function build_issue_description( $field ): string {
		$desc = 'Current value: ' . $field['value'] . '.';

		if ( ! empty( $field['recommended'] ) ) {
			$desc .= ' Recommended: ' . $field['recommended'] . '.';
		}

		if ( ! empty( $field['description'] ) ) {
			$desc .= ' ' . $field['description'];
		}

		return $desc;
	}

	/**
	 * Run additional heuristic checks beyond field-level statuses.
	 *
	 * @param array $report_data Full report data.
	 * @return array Additional issues found.
	 */
	private function run_heuristic_checks( array $report_data ): array {
		$issues = array();

		// Check PHP version EOL.
		$php_version = phpversion();
		if ( version_compare( $php_version, '8.1', '<' ) ) {
			$issues[] = array(
				'severity'    => 'critical',
				'title'       => 'PHP version ' . $php_version . ' is end-of-life',
				'description' => 'This PHP version no longer receives security updates. Upgrade to PHP 8.1 or newer.',
			);
		}

		// Check if object cache is in use when many plugins are active.
		if ( ! wp_using_ext_object_cache() ) {
			$active_plugins = get_option( 'active_plugins', array() );
			if ( count( $active_plugins ) > 15 ) {
				$issues[] = array(
					'severity'    => 'warning',
					'title'       => 'No external object cache with ' . count( $active_plugins ) . ' active plugins',
					'description' => 'Sites with many active plugins benefit significantly from an object cache (Redis, Memcached).',
				);
			}
		}

		// Check autoloaded options size.
		global $wpdb;
		$autoload_size = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes', 'on')"
		);

		if ( $autoload_size && $autoload_size > 1572864 ) { // 1.5 MB.
			$issues[] = array(
				'severity'    => 'critical',
				'title'       => 'Autoloaded options exceed 1.5 MB (' . size_format( $autoload_size ) . ')',
				'description' => 'Large autoloaded options significantly slow every page load. Review and clean up unused options.',
			);
		} elseif ( $autoload_size && $autoload_size > 819200 ) { // 800 KB.
			$issues[] = array(
				'severity'    => 'warning',
				'title'       => 'Autoloaded options are ' . size_format( $autoload_size ),
				'description' => 'Exceeds the recommended 800 KB threshold. Consider auditing autoloaded options.',
			);
		}

		// Check for non-InnoDB tables.
		$non_innodb = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND ENGINE != 'InnoDB' AND ENGINE IS NOT NULL",
				DB_NAME
			)
		);

		if ( $non_innodb > 0 ) {
			$issues[] = array(
				'severity'    => 'warning',
				'title'       => $non_innodb . ' database table(s) not using InnoDB engine',
				'description' => 'InnoDB is the recommended storage engine for WordPress. Non-InnoDB tables may have performance or reliability issues.',
			);
		}

		return $issues;
	}
}
