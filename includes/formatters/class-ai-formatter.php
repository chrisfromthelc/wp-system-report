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
 * Produces a detailed report with an executive summary, severity-scored issues,
 * contextual descriptions, status indicators, recommended values, and a
 * prioritized issues list. Designed for consumption by AI agents like Claude,
 * ChatGPT, or similar LLMs.
 */
class AI_Formatter implements Formatter {

	/**
	 * Severity score for critical issues.
	 *
	 * @var int
	 */
	private const SEVERITY_CRITICAL = 10;

	/**
	 * Severity score for warning issues.
	 *
	 * @var int
	 */
	private const SEVERITY_WARNING = 5;

	/**
	 * Issue category mappings.
	 *
	 * Maps field labels/keywords to categories for grouping in the issues summary.
	 *
	 * @var array<string, string>
	 */
	private const CATEGORY_KEYWORDS = array(
		'security'      => 'Security',
		'ssl'           => 'Security',
		'https'         => 'Security',
		'permission'    => 'Security',
		'debug'         => 'Security',
		'file editor'   => 'Security',
		'xml-rpc'       => 'Security',
		'performance'   => 'Performance',
		'cache'         => 'Performance',
		'autoload'      => 'Performance',
		'memory'        => 'Performance',
		'object cache'  => 'Performance',
		'innodb'        => 'Performance',
		'block types'   => 'Performance',
		'dynamic block' => 'Performance',
		'update'        => 'Updates',
		'version'       => 'Updates',
		'php'           => 'Configuration',
		'mysql'         => 'Configuration',
		'email'         => 'Email',
		'smtp'          => 'Email',
		'upload'        => 'Media',
		'media'         => 'Media',
		'cron'          => 'Cron & Scheduling',
		'loopback'      => 'Connectivity',
		'connectivity'  => 'Connectivity',
		'rest api'      => 'REST API',
	);

	/**
	 * Format the report data as AI-optimized markdown.
	 *
	 * @param array $report_data Full report data.
	 * @return string Formatted markdown report.
	 */
	public function format( array $report_data ): string {
		$issues = $this->detect_issues( $report_data );

		/**
		 * Filter the detected issues for the AI report.
		 *
		 * @param array $issues      Array of issue arrays with 'severity', 'title', 'description', 'score', 'category' keys.
		 * @param array $report_data The full report data.
		 */
		$issues = apply_filters( 'wp_system_report_ai_issues', $issues, $report_data );

		$output  = $this->build_header();
		$output .= $this->build_executive_summary( $report_data, $issues );
		$output .= $this->build_issues_summary( $issues );

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
	private function build_header(): string {
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
		return apply_filters(
			'wp_system_report_ai_header',
			"# WP System Report for {$host}\n"
			. "Generated: {$now} | WP {$wp_ver} | PHP {$php_ver}\n"
			. 'Report Version: ' . WP_SYSTEM_REPORT_VERSION . "\n\n"
			. "---\n\n"
		);
	}

	/**
	 * Build the executive summary for non-technical stakeholders.
	 *
	 * Provides a high-level overview of site health with a numerical score,
	 * issue counts by severity, and the top three priorities.
	 *
	 * @param array $report_data Full report data.
	 * @param array $issues      Detected issues.
	 * @return string Executive summary markdown.
	 */
	private function build_executive_summary( array $report_data, array $issues ): string {
		$critical_count = 0;
		$warning_count  = 0;

		foreach ( $issues as $issue ) {
			if ( 'critical' === $issue['severity'] ) {
				++$critical_count;
			} elseif ( 'warning' === $issue['severity'] ) {
				++$warning_count;
			}
		}

		/*
		 * Delegate health score calculation to Health_Score so that the
		 * executive summary always reflects the same score as the REST API
		 * and admin dashboard. Previously this method used a simplified
		 * penalty-based formula (100 - 10*criticals - 5*warnings) that could
		 * diverge significantly from Health_Score's weighted-average approach.
		 *
		 * Health_Score::calculate() requires a Report_Generator instance, so we
		 * replicate its field-level scoring logic here directly from $report_data
		 * (good = 100 pts, warning = 40 pts, critical = 0 pts, info = excluded)
		 * as an unweighted average, which is the correct per-section algorithm.
		 * The grade label is delegated to the static Health_Score::score_to_grade()
		 * so both code paths share the same A+/A/B/C/D/F thresholds.
		 */
		$total_points  = 0;
		$scored_fields = 0;

		foreach ( $report_data as $section ) {
			if ( empty( $section['fields'] ) ) {
				continue;
			}
			foreach ( $section['fields'] as $field ) {
				$status = ! empty( $field['status'] ) ? $field['status'] : 'info';
				if ( 'good' === $status ) {
					$total_points += 100;
					++$scored_fields;
				} elseif ( 'warning' === $status ) {
					$total_points += 40;
					++$scored_fields;
				} elseif ( 'critical' === $status ) {
					// 0 points — do not add, just count.
					++$scored_fields;
				}
				// 'info' fields are excluded from scoring (neutral).
			}
		}

		$health_score = $scored_fields > 0
			? (int) round( $total_points / $scored_fields )
			: 100;

		// Clamp to 0-100.
		$health_score = max( 0, min( 100, $health_score ) );

		// Use the same grade thresholds as Health_Score::score_to_grade().
		$rating = \SystemReport\Health_Score::score_to_grade( $health_score );

		// Count total sections and fields for context.
		$section_count = 0;
		$field_count   = 0;
		foreach ( $report_data as $section ) {
			if ( ! empty( $section['fields'] ) ) {
				++$section_count;
				$field_count += count( $section['fields'] );
			}
		}

		$output  = "## Executive Summary\n\n";
		$output .= "**Health Score: {$health_score}/100 ({$rating})**\n\n";

		$output .= sprintf(
			/* translators: 1: number of critical issues, 2: number of warning issues, 3: number of sections, 4: number of fields */
			__( 'Found %1$d critical issue(s) and %2$d warning(s) across %3$d diagnostic sections (%4$d checks).', 'wp-system-report' ),
			$critical_count,
			$warning_count,
			$section_count,
			$field_count
		) . "\n\n";

		// Top priorities (up to 3).
		if ( ! empty( $issues ) ) {
			$sorted = $issues;
			usort(
				$sorted,
				function ( array $a, array $b ): int {
					return ( $b['score'] ?? 0 ) <=> ( $a['score'] ?? 0 );
				}
			);

			$output .= "**Top Priorities:**\n";
			$top     = array_slice( $sorted, 0, 3 );
			$i       = 1;
			foreach ( $top as $issue ) {
				$icon    = 'critical' === $issue['severity'] ? '🔴' : '🟡';
				$output .= "{$i}. {$icon} {$issue['title']}\n";
				++$i;
			}
			$output .= "\n";
		}

		/**
		 * Filter the executive summary section.
		 *
		 * @param string $output      The executive summary markdown.
		 * @param int    $health_score Computed health score (0-100).
		 * @param array  $issues       All detected issues.
		 * @param array  $report_data  Full report data.
		 */
		$output = apply_filters( 'wp_system_report_ai_executive_summary', $output, $health_score, $issues, $report_data );

		return $output . "---\n\n";
	}

	/**
	 * Build the issues summary section.
	 *
	 * Produces a severity-scored, categorized, prioritized list of all
	 * detected issues at the top of the report.
	 *
	 * @param array $issues Detected issues.
	 * @return string Issues summary markdown.
	 */
	private function build_issues_summary( array $issues ): string {
		if ( empty( $issues ) ) {
			return "## Potential Issues Detected\n\nNo issues detected. The site appears to be well-configured.\n\n---\n\n";
		}

		$output = "## Potential Issues Detected\n\n";

		// Sort by score descending (highest priority first).
		usort(
			$issues,
			function ( array $a, array $b ): int {
				return ( $b['score'] ?? 0 ) <=> ( $a['score'] ?? 0 );
			}
		);

		// Group by category.
		$categorized = array();
		foreach ( $issues as $issue ) {
			$category                   = $issue['category'] ?? __( 'General', 'wp-system-report' );
			$categorized[ $category ][] = $issue;
		}

		// Output categorized issues.
		foreach ( $categorized as $category => $cat_issues ) {
			$output .= "### {$category}\n\n";
			foreach ( $cat_issues as $issue ) {
				$icon    = 'critical' === $issue['severity'] ? '🔴' : '🟡';
				$score   = $issue['score'] ?? 0;
				$output .= "- {$icon} **{$issue['title']}** (severity: {$score}) — {$issue['description']}";

				if ( ! empty( $issue['fix_id'] ) ) {
					$output .= " `[fix: {$issue['fix_id']}]`";
				}

				$output .= "\n";
			}
			$output .= "\n";
		}

		return $output . "---\n\n";
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
	 * @param array|\ArrayAccess $field Field data.
	 * @return string Status indicator.
	 */
	private function format_status( array|\ArrayAccess $field ): string {
		$status = ! empty( $field['status'] ) ? $field['status'] : 'info';

		return match ( $status ) {
			'good'     => 'OK',
			'warning'  => 'WARNING',
			'critical' => 'CRITICAL',
			default    => '-',
		};
	}

	/**
	 * Escape markdown special characters in a string.
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	private function escape_markdown( string $text ): string {
		// Replace pipe characters which break table formatting.
		$text = str_replace( '|', '\\|', $text );
		// Replace newlines with spaces for table cells.
		return str_replace( array( "\r\n", "\r", "\n" ), ' ', $text );
	}

	/**
	 * Detect issues from the report data.
	 *
	 * Scans all fields for warnings and critical statuses, assigns severity
	 * scores and categories, and runs additional heuristic checks.
	 *
	 * @param array $report_data Full report data.
	 * @return array Array of issue arrays with 'severity', 'title', 'description', 'score', 'category', and optional 'fix_id' keys.
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

				$severity = $field['status'];
				$score    = 'critical' === $severity ? self::SEVERITY_CRITICAL : self::SEVERITY_WARNING;

				$issue = array(
					'severity'    => $severity,
					'title'       => $field['label'],
					'description' => $this->build_issue_description( $field ),
					'score'       => $score,
					'category'    => $this->categorize_issue( $field['label'], $section['id'] ?? '' ),
				);

				if ( ! empty( $field['fix_id'] ) ) {
					$issue['fix_id'] = $field['fix_id'];
				}

				$issues[] = $issue;
			}
		}

		// Run additional heuristic checks.
		return array_merge( $issues, $this->run_heuristic_checks( $report_data ) );
	}

	/**
	 * Categorize an issue based on its label and section.
	 *
	 * @param string $label      Field label.
	 * @param string $section_id Section ID.
	 * @return string Issue category.
	 */
	private function categorize_issue( string $label, string $section_id ): string {
		$lower_label = strtolower( $label );

		foreach ( self::CATEGORY_KEYWORDS as $keyword => $category ) {
			if ( str_contains( $lower_label, $keyword ) ) {
				return $category;
			}
		}

		// Fall back to section-based categorization.
		$section_map = array(
			'security'             => 'Security',
			'performance'          => 'Performance',
			'database'             => 'Database',
			'update_health'        => 'Updates',
			'email_delivery'       => 'Email',
			'media_uploads'        => 'Media',
			'cron_health'          => 'Cron & Scheduling',
			'network_connectivity' => 'Connectivity',
			'block_editor'         => 'Block Editor',
			'rest_api_info'        => 'REST API',
		);

		return $section_map[ $section_id ] ?? __( 'General', 'wp-system-report' );
	}

	/**
	 * Build a descriptive issue message from a field.
	 *
	 * @param array|\ArrayAccess $field Field data.
	 * @return string Issue description.
	 */
	private function build_issue_description( array|\ArrayAccess $field ): string {
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
	 * Includes checks for PHP EOL, object cache usage, non-InnoDB tables,
	 * and cross-section diagnostics from newer collectors.
	 *
	 * @param array $report_data Full report data.
	 * @return array Additional issues found.
	 */
	private function run_heuristic_checks( array $report_data ): array {
		$issues = array();

		$issues = array_merge( $issues, $this->check_php_eol() );
		$issues = array_merge( $issues, $this->check_object_cache() );
		$issues = array_merge( $issues, $this->check_non_innodb_tables( $report_data ) );
		$issues = array_merge( $issues, $this->check_email_configuration( $report_data ) );
		$issues = array_merge( $issues, $this->check_update_posture( $report_data ) );
		return array_merge( $issues, $this->check_editor_bloat( $report_data ) );
	}

	/**
	 * Check for end-of-life PHP version.
	 *
	 * @return array Issues found.
	 */
	private function check_php_eol(): array {
		$php_version = phpversion();
		if ( version_compare( $php_version, '8.1', '<' ) ) {
			return array(
				array(
					'severity'    => 'critical',
					'title'       => 'PHP version ' . $php_version . ' is end-of-life',
					'description' => 'This PHP version no longer receives security updates. Upgrade to PHP 8.1 or newer.',
					'score'       => self::SEVERITY_CRITICAL,
					'category'    => 'Configuration',
				),
			);
		}
		return array();
	}

	/**
	 * Check for missing object cache with many active plugins.
	 *
	 * @return array Issues found.
	 */
	private function check_object_cache(): array {
		if ( wp_using_ext_object_cache() ) {
			return array();
		}

		$active_plugins = get_option( 'active_plugins', array() );
		if ( count( $active_plugins ) > 15 ) {
			return array(
				array(
					'severity'    => 'warning',
					'title'       => 'No external object cache with ' . count( $active_plugins ) . ' active plugins',
					'description' => 'Sites with many active plugins benefit significantly from an object cache (Redis, Memcached).',
					'score'       => self::SEVERITY_WARNING,
					'category'    => 'Performance',
				),
			);
		}
		return array();
	}

	/**
	 * Check for non-InnoDB database tables.
	 *
	 * @param array $report_data Full report data.
	 * @return array Issues found.
	 */
	private function check_non_innodb_tables( array $report_data ): array {
		$database_section = $report_data['database'] ?? null;
		if ( ! $database_section ) {
			return array();
		}

		$non_innodb_count = 0;
		foreach ( $database_section['fields'] as $field ) {
			if ( str_contains( $field['value'], 'Engine:' ) && ! str_contains( $field['value'], 'InnoDB' ) ) {
				++$non_innodb_count;
			}
		}

		if ( $non_innodb_count > 0 ) {
			return array(
				array(
					'severity'    => 'warning',
					'title'       => $non_innodb_count . ' database table(s) not using InnoDB engine',
					'description' => 'InnoDB is the recommended storage engine for WordPress. Non-InnoDB tables may have performance or reliability issues.',
					'score'       => self::SEVERITY_WARNING,
					'category'    => 'Database',
				),
			);
		}
		return array();
	}

	/**
	 * Check email delivery configuration from the Email Delivery collector.
	 *
	 * @param array $report_data Full report data.
	 * @return array Issues found.
	 */
	private function check_email_configuration( array $report_data ): array {
		$section = $report_data['email_delivery'] ?? null;
		if ( ! $section ) {
			return array();
		}

		$issues = array();

		foreach ( $section['fields'] as $field ) {
			// Detect default PHP mailer without SMTP plugin.
			if ( 'Mail Transport' === $field['label'] && str_contains( strtolower( $field['value'] ), 'php mail' ) ) {
				$issues[] = array(
					'severity'    => 'warning',
					'title'       => 'Email using default PHP mail()',
					'description' => 'Default PHP mail() is unreliable. Consider using an SMTP plugin (WP Mail SMTP, FluentSMTP) for better deliverability.',
					'score'       => self::SEVERITY_WARNING,
					'category'    => 'Email',
				);
			}
		}

		return $issues;
	}

	/**
	 * Check update posture from the Update Health collector.
	 *
	 * @param array $report_data Full report data.
	 * @return array Issues found.
	 */
	private function check_update_posture( array $report_data ): array {
		$section = $report_data['update_health'] ?? null;
		if ( ! $section ) {
			return array();
		}

		$issues = array();

		foreach ( $section['fields'] as $field ) {
			// Flag sites with many pending plugin updates.
			if ( 'Plugin Updates Available' === $field['label'] ) {
				$count = (int) $field['debug'];
				if ( $count >= 5 ) {
					$issues[] = array(
						'severity'    => 'warning',
						'title'       => $count . ' plugin updates pending',
						'description' => 'Multiple pending updates increase security risk. Review and apply updates regularly.',
						'score'       => self::SEVERITY_WARNING,
						'category'    => 'Updates',
					);
				}
			}
		}

		return $issues;
	}

	/**
	 * Check for editor bloat from the Block Editor collector.
	 *
	 * @param array $report_data Full report data.
	 * @return array Issues found.
	 */
	private function check_editor_bloat( array $report_data ): array {
		$section = $report_data['block_editor'] ?? null;
		if ( ! $section ) {
			return array();
		}

		$issues = array();

		foreach ( $section['fields'] as $field ) {
			// Flag excessive block registrations.
			if ( 'Registered Block Types' === $field['label'] ) {
				$count = (int) str_replace( ',', '', $field['debug'] );
				if ( $count > 500 ) {
					$issues[] = array(
						'severity'    => 'warning',
						'title'       => $count . ' block types registered',
						'description' => 'An excessive number of registered block types can degrade editor performance. Review installed block plugins.',
						'score'       => self::SEVERITY_WARNING,
						'category'    => 'Performance',
					);
				}
			}
		}

		return $issues;
	}
}
