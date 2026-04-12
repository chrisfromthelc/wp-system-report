<?php
/**
 * Issue detector.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Detects issues from report data by scanning fields and running heuristic checks.
 *
 * Extracted from AI_Formatter so the logic can be reused by the Abilities API
 * provider and any other consumer that needs issue detection without formatting.
 */
class Issue_Detector {

	/**
	 * Detect issues from the report data.
	 *
	 * Scans all fields for warnings and critical statuses, and runs
	 * additional heuristic checks for common problems.
	 *
	 * @param array $report_data Full report data keyed by collector ID.
	 * @return array Array of issue arrays with 'severity', 'title', 'description' keys.
	 */
	public function detect( array $report_data ): array {
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
	private function build_issue_description( array $field ): string {
		$desc = sprintf(
			/* translators: %s: current field value */
			__( 'Current value: %s.', 'wp-system-report' ),
			$field['value']
		);

		if ( ! empty( $field['recommended'] ) ) {
			$desc .= ' ' . sprintf(
				/* translators: %s: recommended value */
				__( 'Recommended: %s.', 'wp-system-report' ),
				$field['recommended']
			);
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
				'title'       => sprintf(
					/* translators: %s: PHP version number */
					__( 'PHP version %s is end-of-life', 'wp-system-report' ),
					$php_version
				),
				'description' => __( 'This PHP version no longer receives security updates. Upgrade to PHP 8.1 or newer.', 'wp-system-report' ),
			);
		}

		// Check if object cache is in use when many plugins are active.
		if ( ! wp_using_ext_object_cache() ) {
			$active_plugins = get_option( 'active_plugins', array() );
			if ( count( $active_plugins ) > 15 ) {
				$issues[] = array(
					'severity'    => 'warning',
					'title'       => sprintf(
						/* translators: %d: number of active plugins */
						__( 'No external object cache with %d active plugins', 'wp-system-report' ),
						count( $active_plugins )
					),
					'description' => __( 'Sites with many active plugins benefit significantly from an object cache (Redis, Memcached).', 'wp-system-report' ),
				);
			}
		}

		// Autoloaded options size is already reported by the collector with field-level
		// status. The detect() method captures it automatically — no duplicate needed.

		// Check for non-InnoDB tables from existing report data (Database section).
		$database_section = $this->find_section_by_id( $report_data, 'database' );
		if ( $database_section && ! empty( $database_section['fields'] ) && is_array( $database_section['fields'] ) ) {
			$non_innodb_count = 0;
			foreach ( $database_section['fields'] as $field ) {
				if ( false !== strpos( $field['value'], 'Engine:' ) && false === strpos( $field['value'], 'InnoDB' ) ) {
					++$non_innodb_count;
				}
			}
			if ( $non_innodb_count > 0 ) {
				$issues[] = array(
					'severity'    => 'warning',
					'title'       => sprintf(
						/* translators: %d: number of non-InnoDB tables */
						__( '%d database table(s) not using InnoDB engine', 'wp-system-report' ),
						$non_innodb_count
					),
					'description' => __( 'InnoDB is the recommended storage engine for WordPress. Non-InnoDB tables may have performance or reliability issues.', 'wp-system-report' ),
				);
			}
		}

		return $issues;
	}

	/**
	 * Find a section by ID.
	 *
	 * @param array  $report_data Full report data.
	 * @param string $section_id  Section ID to find.
	 * @return array|null Section data or null.
	 */
	private function find_section_by_id( array $report_data, string $section_id ) {
		return $report_data[ $section_id ] ?? null;
	}
}
