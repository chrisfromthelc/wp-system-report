<?php
/**
 * WP-CLI commands for WP System Report.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Manage WordPress system diagnostics and health.
 *
 * ## EXAMPLES
 *
 *     # Generate a full system report in JSON format.
 *     $ wp system-report generate --format=json
 *
 *     # Export report as markdown.
 *     $ wp system-report export --format=md
 *
 *     # Check cron health.
 *     $ wp system-report cron-check
 *
 *     # Run a specific fixer.
 *     $ wp system-report fix autoload_optimizer
 *
 *     # Show health score.
 *     $ wp system-report health
 */
class CLI_Command extends \WP_CLI_Command {

	/**
	 * Generate a full system report.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - plain
	 *   - github
	 *   - ai
	 *   - mcp
	 * ---
	 *
	 * [--section=<section>]
	 * : Only show a specific collector section.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp system-report generate
	 *     $ wp system-report generate --format=json
	 *     $ wp system-report generate --section=security
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function generate( $args, $assoc_args ): void {
		$format  = $assoc_args['format'] ?? 'table';
		$section = $assoc_args['section'] ?? null;
		$plugin  = Plugin::get_instance();
		$gen     = $plugin->get_report_generator();

		if ( null !== $section ) {
			$data = $gen->generate_section( $section );
			if ( null === $data ) {
				\WP_CLI::error( "Unknown collector section: {$section}" );
			}
			$report = array( $section => $data );
		} else {
			$report = $gen->generate();
		}

		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		// Use plugin formatters for text-based formats.
		if ( in_array( $format, array( 'plain', 'github', 'ai', 'mcp' ), true ) ) {
			$this->output_formatted( $report, $format );
			return;
		}

		// Table format: iterate sections and display as tables.
		$this->output_table( $report );
	}

	/**
	 * Export the system report to a file.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Export format.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - csv
	 *   - md
	 * ---
	 *
	 * [--output=<file>]
	 * : Output file path. Defaults to stdout.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp system-report export --format=json --output=report.json
	 *     $ wp system-report export --format=csv > report.csv
	 *     $ wp system-report export --format=md
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function export( $args, $assoc_args ): void {
		$format = $assoc_args['format'] ?? 'json';
		$output = $assoc_args['output'] ?? null;
		$plugin = Plugin::get_instance();
		$report = $plugin->get_report_generator()->generate();

		$content = match ( $format ) {
			'json' => wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'csv'  => $this->report_to_csv( $report ),
			'md'   => ( new Formatters\GitHub_Formatter() )->format( $report ),
			default => null,
		};

		if ( null === $content ) {
			\WP_CLI::error( "Unsupported export format: {$format}. Supported formats: json, csv, md." );
		}

		if ( null !== $output ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI context, no WP filesystem needed.
			$result = file_put_contents( $output, $content );

			if ( false === $result ) {
				\WP_CLI::error( "Failed to write to: {$output}" );
			}

			\WP_CLI::success( "Report exported to: {$output}" );
			return;
		}

		\WP_CLI::line( $content );
	}

	/**
	 * Run a fixer to remediate a detected issue.
	 *
	 * ## OPTIONS
	 *
	 * <fix_id>
	 * : The fixer identifier to run.
	 *
	 * [--dry-run]
	 * : Show what would be fixed without making changes.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp system-report fix autoload_optimizer
	 *     $ wp system-report fix database_optimizer --dry-run
	 *     $ wp system-report fix security_hardener --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function fix( $args, $assoc_args ): void {
		if ( ! Features::has_fixers() ) {
			\WP_CLI::error( 'Fixers are not available in this installation.' );
		}

		$fix_id  = $args[0];
		$dry_run = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$plugin  = Plugin::get_instance();
		$fixer   = $plugin->get_fixer_registry()->get( $fix_id );

		if ( null === $fixer ) {
			\WP_CLI::error( "Unknown fixer: {$fix_id}" );
		}

		\WP_CLI::line( "Fixer: {$fixer->get_label()}" );
		\WP_CLI::line( "Description: {$fixer->get_description()}" );
		\WP_CLI::line( "Risk level: {$fixer->get_risk_level()->value}" );

		if ( ! $fixer->can_fix() ) {
			\WP_CLI::warning( 'No issues detected — nothing to fix.' );
			return;
		}

		if ( $dry_run ) {
			\WP_CLI::success( 'Dry run complete. Issues were detected that can be fixed.' );
			return;
		}

		\WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false )
			|| \WP_CLI::confirm( 'Are you sure you want to run this fix?' );

		$result = $fixer->fix();

		if ( $result->success ) {
			\WP_CLI::success( $result->message );
		} else {
			\WP_CLI::error( $result->message );
		}

		if ( ! empty( $result->before ) || ! empty( $result->after ) ) {
			\WP_CLI::line( '' );

			if ( ! empty( $result->before ) ) {
				\WP_CLI::line( 'Before:' );
				\WP_CLI::line( '  ' . wp_json_encode( $result->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}

			if ( ! empty( $result->after ) ) {
				\WP_CLI::line( 'After:' );
				\WP_CLI::line( '  ' . wp_json_encode( $result->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		}

		if ( ! empty( $result->errors ) ) {
			\WP_CLI::line( '' );
			\WP_CLI::line( 'Errors:' );
			foreach ( $result->errors as $error ) {
				if ( is_array( $error ) || is_object( $error ) ) {
					\WP_CLI::line( '  ' . wp_json_encode( $error ) );
				} else {
					\WP_CLI::line( "  {$error}" );
				}
			}
		}
	}

	/**
	 * List available fixers.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp system-report fixes
	 *     $ wp system-report fixes --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function fixes( $args, $assoc_args ): void {
		if ( ! Features::has_fixers() ) {
			\WP_CLI::error( 'Fixers are not available in this installation.' );
		}

		$plugin = Plugin::get_instance();
		$fixers = $plugin->get_fixer_registry()->get_all();

		if ( empty( $fixers ) ) {
			\WP_CLI::warning( 'No fixers registered.' );
			return;
		}

		$rows = array();
		foreach ( $fixers as $id => $fixer ) {
			$rows[] = array(
				'id'          => $id,
				'label'       => $fixer->get_label(),
				'risk'        => $fixer->get_risk_level()->value,
				'has_issues'  => $fixer->can_fix() ? 'yes' : 'no',
				'description' => $fixer->get_description(),
			);
		}

		$format = $assoc_args['format'] ?? 'table';

		\WP_CLI\Utils\format_items(
			$format,
			$rows,
			array( 'id', 'label', 'risk', 'has_issues', 'description' )
		);
	}

	/**
	 * Check cron health.
	 *
	 * Reports on WordPress cron status, overdue events, orphaned hooks,
	 * and the doing_cron lock status.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp system-report cron-check
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cron_check( $args, $assoc_args ): void {
		$plugin = Plugin::get_instance();
		$gen    = $plugin->get_report_generator();
		$data   = $gen->generate_section( 'cron_health' );

		if ( null === $data ) {
			\WP_CLI::error( 'Cron health collector not found.' );
		}

		$fields = $data['fields'] ?? array();

		if ( empty( $fields ) ) {
			\WP_CLI::warning( 'No cron health data collected.' );
			return;
		}

		$has_issues = false;

		foreach ( $fields as $field ) {
			$status = $this->get_field_status_string( $field );
			$label  = $this->get_field_label( $field );
			$value  = $this->get_field_value( $field );

			$color = match ( $status ) {
				'critical' => '%R',
				'warning'  => '%Y',
				'good'     => '%G',
				default    => '%_',
			};

			\WP_CLI::line( \WP_CLI::colorize( "{$color}{$label}:%n {$value}" ) );

			if ( in_array( $status, array( 'warning', 'critical' ), true ) ) {
				$has_issues = true;
			}
		}

		if ( $has_issues ) {
			\WP_CLI::warning( 'Cron health issues detected. Consider running: wp system-report fix cron_repair' );
		} else {
			\WP_CLI::success( 'Cron health looks good.' );
		}
	}

	/**
	 * Display the site health score.
	 *
	 * Shows the aggregate health score (0–100), letter grade, and
	 * optional per-section breakdown.
	 *
	 * The summary line (score, grade, and field counts) is always
	 * rendered as plain text regardless of the --format value.
	 * Passing --format=table only affects the per-section breakdown
	 * table rendered when --breakdown is also supplied; without
	 * --breakdown the flag has no effect on output.
	 *
	 * ## OPTIONS
	 *
	 * [--breakdown]
	 * : Show per-section score breakdown.
	 *
	 * [--format=<format>]
	 * : Controls the layout of the per-section breakdown table.
	 * Only applies when --breakdown is also passed. The summary
	 * is always displayed as text regardless of this option.
	 * ---
	 * default: text
	 * options:
	 *   - text
	 *   - json
	 *   - table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp system-report health
	 *     $ wp system-report health --breakdown
	 *     $ wp system-report health --breakdown --format=table
	 *     $ wp system-report health --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function health( $args, $assoc_args ): void {
		$plugin       = Plugin::get_instance();
		$health_score = $plugin->get_health_score();
		$result       = $health_score->calculate();
		$format       = $assoc_args['format'] ?? 'text';
		$breakdown    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'breakdown', false );

		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		// Color the grade.
		$grade_color = match ( true ) {
			$result['score'] >= 80 => '%G',
			$result['score'] >= 50 => '%Y',
			default                => '%R',
		};

		\WP_CLI::line( '' );
		\WP_CLI::line( \WP_CLI::colorize( "%_Health Score:%n {$grade_color}{$result['score']}/100 ({$result['grade']})%n" ) );
		\WP_CLI::line( '' );

		$summary = $result['summary'];
		\WP_CLI::line( \WP_CLI::colorize( "%_Summary:%n {$summary['total_fields']} fields checked" ) );
		\WP_CLI::line( \WP_CLI::colorize( "  %GGood:%n {$summary['good']}  %YWarnings:%n {$summary['warnings']}  %RCritical:%n {$summary['criticals']}  Info: {$summary['info']}" ) );

		if ( $breakdown && 'table' === $format ) {
			$rows = array();
			foreach ( $result['breakdown'] as $section ) {
				$rows[] = array(
					'section'   => $section['label'],
					'score'     => $section['score'],
					'weight'    => $section['weight'],
					'fields'    => $section['field_count'],
					'warnings'  => $section['warnings'],
					'criticals' => $section['criticals'],
				);
			}

			// Sort by score ascending (worst first).
			usort( $rows, fn( $a, $b ): int => $a['score'] <=> $b['score'] );

			\WP_CLI::line( '' );
			\WP_CLI\Utils\format_items(
				'table',
				$rows,
				array( 'section', 'score', 'weight', 'fields', 'warnings', 'criticals' )
			);
		} elseif ( $breakdown ) {
			\WP_CLI::line( '' );
			\WP_CLI::line( \WP_CLI::colorize( '%_Per-Section Breakdown:%n' ) );

			// Sort by score ascending (worst first).
			$sections = $result['breakdown'];
			uasort( $sections, fn( $a, $b ): int => $a['score'] <=> $b['score'] );

			foreach ( $sections as $section ) {
				$color = match ( true ) {
					$section['score'] >= 80 => '%G',
					$section['score'] >= 50 => '%Y',
					default                 => '%R',
				};

				\WP_CLI::line(
					\WP_CLI::colorize(
						"  {$color}{$section['score']}%n  {$section['label']} ({$section['field_count']} fields, weight: {$section['weight']}x)"
					)
				);
			}
		}

		\WP_CLI::line( '' );
	}

	/**
	 * List available collector sections.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp system-report collectors
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function collectors( $args, $assoc_args ): void {
		$plugin     = Plugin::get_instance();
		$collectors = $plugin->get_report_generator()->get_collectors();
		$rows       = array();

		foreach ( $collectors as $id => $collector ) {
			$rows[] = array(
				'id'          => $id,
				'label'       => $collector->get_label(),
				'priority'    => $collector->get_priority(),
				'description' => $collector->get_description(),
			);
		}

		$format = $assoc_args['format'] ?? 'table';

		\WP_CLI\Utils\format_items(
			$format,
			$rows,
			array( 'id', 'label', 'priority', 'description' )
		);
	}

	/**
	 * Output report using a plugin formatter.
	 *
	 * @param array  $report Report data.
	 * @param string $format Formatter identifier.
	 */
	private function output_formatted( array $report, string $format ): void {
		if ( 'mcp' === $format ) {
			$mcp = new Formatters\MCP_Formatter();
			\WP_CLI::line( wp_json_encode( $mcp->format_array( $report ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		$formatter = match ( $format ) {
			'plain'  => new Formatters\Plain_Text_Formatter(),
			'github' => new Formatters\GitHub_Formatter(),
			'ai'     => new Formatters\AI_Formatter(),
			default  => null,
		};

		if ( null === $formatter ) {
			\WP_CLI::error(
				sprintf(
					/* translators: %s: requested format name */
					__( 'Unsupported format: %s', 'wp-system-report' ),
					$format
				)
			);
		}

		\WP_CLI::line( $formatter->format( $report ) );
	}

	/**
	 * Output report sections as tables.
	 *
	 * @param array $report Report data.
	 */
	private function output_table( array $report ): void {
		foreach ( $report as $section ) {
			\WP_CLI::line( '' );
			\WP_CLI::line( \WP_CLI::colorize( "%_=== {$section['label']} ===%n" ) );

			$fields = $section['fields'] ?? array();
			if ( empty( $fields ) ) {
				\WP_CLI::line( '  No data.' );
				continue;
			}

			$rows = array();
			foreach ( $fields as $field ) {
				$status = $this->get_field_status_string( $field );
				$label  = $this->get_field_label( $field );
				$value  = $this->get_field_value( $field );

				$rows[] = array(
					'field'  => $label,
					'value'  => $value,
					'status' => $status,
				);
			}

			\WP_CLI\Utils\format_items( 'table', $rows, array( 'field', 'value', 'status' ) );
		}
	}

	/**
	 * Convert report data to CSV format.
	 *
	 * @param array $report Report data.
	 * @return string CSV content.
	 */
	private function report_to_csv( array $report ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream, no filesystem access.
		$handle = fopen( 'php://temp', 'r+' );

		if ( false === $handle ) {
			return '';
		}

		// Header row.
		fputcsv( $handle, array( 'section', 'field', 'value', 'status' ) );

		foreach ( $report as $section_id => $section ) {
			$label  = $section['label'] ?? $section_id;
			$fields = $section['fields'] ?? array();

			foreach ( $fields as $field ) {
				fputcsv(
					$handle,
					array(
						$label,
						$this->get_field_label( $field ),
						$this->get_field_value( $field ),
						$this->get_field_status_string( $field ),
					)
				);
			}
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream, no filesystem access.
		fclose( $handle );

		return false !== $csv ? $csv : '';
	}

	/**
	 * Get the status string from a field.
	 *
	 * Delegates to Field::get_status_string() to avoid duplicating
	 * the Field-or-array branching logic.
	 *
	 * @param Field|array $field A field value object or associative array.
	 * @return string Status string.
	 */
	private function get_field_status_string( $field ): string {
		return Field::get_status_string( $field );
	}

	/**
	 * Get the label from a field.
	 *
	 * @param Field|array $field A field value object or associative array.
	 * @return string Field label.
	 */
	private function get_field_label( $field ): string {
		if ( $field instanceof Field ) {
			return $field->label;
		}

		return is_array( $field ) ? (string) ( $field['label'] ?? '' ) : '';
	}

	/**
	 * Get the value from a field as a string.
	 *
	 * Non-scalar values (arrays, objects) are JSON-encoded so that
	 * consumers such as CSV export receive machine-readable output
	 * rather than an unhelpful "Array" or "Object" cast.
	 *
	 * @param Field|array $field A field value object or associative array.
	 * @return string Field value.
	 */
	private function get_field_value( $field ): string {
		if ( $field instanceof Field ) {
			$value = $field->value;
		} else {
			$value = is_array( $field ) ? ( $field['value'] ?? '' ) : '';
		}

		if ( ! is_scalar( $value ) && null !== $value ) {
			return (string) wp_json_encode( $value );
		}

		return (string) $value;
	}
}
