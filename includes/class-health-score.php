<?php
/**
 * Health score calculator.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Computes an aggregate health score (0–100) from report data.
 *
 * The score is weighted by collector category importance:
 *
 * - Critical categories (security, performance, updates) carry more weight.
 * - Each field contributes based on its status: Good = 100, Warning = 40, Critical = 0, Info = neutral.
 * - Info fields are excluded from scoring as they are purely informational.
 *
 * The final score is a weighted average across all scored categories.
 */
class Health_Score {

	/**
	 * Category weights for scoring.
	 *
	 * Higher weight = more impact on the final score.
	 * Collectors not listed here receive the default weight.
	 *
	 * @var array<string, float>
	 */
	private const CATEGORY_WEIGHTS = array(
		'security'                => 3.0,
		'update_health'           => 2.5,
		'performance'             => 2.5,
		'wordpress_environment'   => 2.0,
		'server_environment'      => 2.0,
		'database'                => 2.0,
		'cron_health'             => 1.5,
		'email_delivery'          => 1.5,
		'network_connectivity'    => 1.5,
		'filesystem_permissions'  => 1.5,
		'site_health'             => 1.5,
		'active_plugins'          => 1.0,
		'inactive_plugins'        => 0.5,
		'theme_info'              => 1.0,
		'media_uploads'           => 1.0,
		'block_editor'            => 0.5,
		'rest_api_info'           => 1.0,
		'wordpress_constants'     => 0.5,
		'wordpress_configuration' => 0.5,
		'post_type_counts'        => 0.25,
		'custom_content_types'    => 0.25,
		'dropins_mu_plugins'      => 0.5,
		'advanced_diagnostics'    => 1.0,
	);

	/**
	 * Default weight for collectors not in CATEGORY_WEIGHTS.
	 *
	 * @var float
	 */
	private const DEFAULT_WEIGHT = 1.0;

	/**
	 * Points awarded per status.
	 *
	 * @var array<string, int>
	 */
	private const STATUS_POINTS = array(
		'good'     => 100,
		'warning'  => 40,
		'critical' => 0,
	);

	/**
	 * Report generator instance.
	 */
	private Report_Generator $report_generator;

	/**
	 * Constructor.
	 *
	 * @param Report_Generator $report_generator Report generator instance.
	 */
	public function __construct( Report_Generator $report_generator ) {
		$this->report_generator = $report_generator;
	}

	/**
	 * Calculate the health score from the full report.
	 *
	 * @param array|null $report Optional pre-generated report data. If null, generates fresh.
	 * @return array{
	 *     score: int,
	 *     grade: string,
	 *     breakdown: array<string, array{score: int, weight: float, label: string, field_count: int, warnings: int, criticals: int}>,
	 *     summary: array{total_fields: int, good: int, warnings: int, criticals: int, info: int}
	 * }
	 */
	public function calculate( ?array $report = null ): array {
		if ( null === $report ) {
			$report = $this->report_generator->generate();
		}

		$breakdown    = array();
		$total_weight = 0.0;
		$weighted_sum = 0.0;

		// Summary counters.
		$summary = array(
			'total_fields' => 0,
			'good'         => 0,
			'warnings'     => 0,
			'criticals'    => 0,
			'info'         => 0,
		);

		foreach ( $report as $section_id => $section ) {
			$fields = $section['fields'] ?? array();

			if ( empty( $fields ) ) {
				continue;
			}

			$section_result = $this->score_section( $section_id, $fields );

			// Update summary counters.
			$summary['total_fields'] += $section_result['field_count'];
			$summary['good']         += $section_result['good'];
			$summary['warnings']     += $section_result['warnings'];
			$summary['criticals']    += $section_result['criticals'];
			$summary['info']         += $section_result['info'];

			// Only include sections with scored (non-info) fields.
			if ( $section_result['scored_count'] > 0 ) {
				$weight        = $this->get_weight( $section_id );
				$total_weight += $weight;
				$weighted_sum += $section_result['score'] * $weight;

				$breakdown[ $section_id ] = array(
					'score'       => $section_result['score'],
					'weight'      => $weight,
					'label'       => $section['label'] ?? $section_id,
					'field_count' => $section_result['field_count'],
					'warnings'    => $section_result['warnings'],
					'criticals'   => $section_result['criticals'],
				);
			}
		}

		$score = $total_weight > 0
			? (int) round( $weighted_sum / $total_weight )
			: 100;

		// Clamp to 0-100.
		$score = max( 0, min( 100, $score ) );

		/**
		 * Filter the computed health score.
		 *
		 * @param int   $score     The health score (0-100).
		 * @param array $breakdown Per-section score breakdown.
		 * @param array $report    The raw report data.
		 */
		$score = (int) apply_filters( 'wp_system_report_health_score', $score, $breakdown, $report );

		return array(
			'score'     => $score,
			'grade'     => self::score_to_grade( $score ),
			'breakdown' => $breakdown,
			'summary'   => $summary,
		);
	}

	/**
	 * Score a single report section.
	 *
	 * @param string $section_id The collector ID.
	 * @param array  $fields     Array of Field objects or arrays.
	 * @return array{score: int, field_count: int, scored_count: int, good: int, warnings: int, criticals: int, info: int}
	 */
	private function score_section( string $section_id, array $fields ): array {
		$total_points = 0;
		$scored_count = 0;
		$good         = 0;
		$warnings     = 0;
		$criticals    = 0;
		$info         = 0;
		$field_count  = count( $fields );

		foreach ( $fields as $field ) {
			$status = $this->get_field_status( $field );

			if ( 'info' === $status ) {
				++$info;
				continue;
			}

			$points        = self::STATUS_POINTS[ $status ] ?? 100;
			$total_points += $points;
			++$scored_count;

			switch ( $status ) {
				case 'good':
					++$good;
					break;
				case 'warning':
					++$warnings;
					break;
				case 'critical':
					++$criticals;
					break;
			}
		}

		$score = $scored_count > 0
			? (int) round( $total_points / $scored_count )
			: 100;

		return array(
			'score'        => $score,
			'field_count'  => $field_count,
			'scored_count' => $scored_count,
			'good'         => $good,
			'warnings'     => $warnings,
			'criticals'    => $criticals,
			'info'         => $info,
		);
	}

	/**
	 * Get the status string from a field.
	 *
	 * Supports both Field objects and legacy associative arrays.
	 *
	 * @param Field|array $field A field value object or associative array.
	 * @return string Status string: 'good', 'warning', 'critical', or 'info'.
	 */
	private function get_field_status( $field ): string {
		if ( $field instanceof Field ) {
			return $field->status->value;
		}

		if ( is_array( $field ) && isset( $field['status'] ) ) {
			$status = $field['status'];

			if ( $status instanceof Status ) {
				return $status->value;
			}

			return is_string( $status ) ? $status : 'info';
		}

		return 'info';
	}

	/**
	 * Get the weight for a collector section.
	 *
	 * @param string $section_id The collector ID.
	 * @return float Weight multiplier.
	 */
	private function get_weight( string $section_id ): float {
		$weights = self::CATEGORY_WEIGHTS;

		/**
		 * Filter the category weights for health score calculation.
		 *
		 * @param array<string, float> $weights Map of section ID to weight.
		 */
		$weights = apply_filters( 'wp_system_report_health_score_weights', $weights );

		return $weights[ $section_id ] ?? self::DEFAULT_WEIGHT;
	}

	/**
	 * Convert a numeric score to a letter grade.
	 *
	 * @param int $score The health score (0-100).
	 * @return string Letter grade: A+, A, B, C, D, or F.
	 */
	public static function score_to_grade( int $score ): string {
		if ( $score >= 95 ) {
			return 'A+';
		}
		if ( $score >= 80 ) {
			return 'A';
		}
		if ( $score >= 65 ) {
			return 'B';
		}
		if ( $score >= 50 ) {
			return 'C';
		}
		if ( $score >= 35 ) {
			return 'D';
		}
		return 'F';
	}
}
