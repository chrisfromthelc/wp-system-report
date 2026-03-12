<?php
/**
 * MCP-optimized formatter.
 *
 * @package SystemReport
 */

namespace SystemReport\Formatters;

use SystemReport\Features;

defined( 'ABSPATH' ) || exit;

/**
 * Formats the report as structured JSON optimized for AI/MCP ability responses.
 *
 * Designed for consumption by AI agents via the Model Context Protocol.
 * Produces compact, semantically structured JSON with:
 *
 * - Site identity and metadata for context.
 * - Health score and severity-scored issue list for quick triage.
 * - Token-efficient section summaries with only non-good fields highlighted.
 * - Actionable fix references linking issues to available fixers.
 * - Machine-readable statuses and types throughout.
 *
 * Unlike the AI_Formatter (which outputs markdown), this formatter returns
 * a JSON string that can be decoded into a structured array or returned
 * directly from an MCP ability execute callback.
 */
class MCP_Formatter implements Formatter {

	/**
	 * Severity weight for critical issues.
	 *
	 * @var int
	 */
	private const WEIGHT_CRITICAL = 10;

	/**
	 * Severity weight for warning issues.
	 *
	 * @var int
	 */
	private const WEIGHT_WARNING = 5;

	/**
	 * Maximum number of fields per section before truncation.
	 *
	 * Token-efficiency optimisation: sections with many "good" fields are
	 * summarised rather than listed in full.
	 *
	 * @var int
	 */
	private const SECTION_FIELD_LIMIT = 50;

	/**
	 * Maximum character length for compact description values.
	 *
	 * Prevents non-scalar field values (JSON-encoded arrays/objects) from
	 * producing excessively long strings that defeat token-efficiency goals.
	 *
	 * @var int
	 */
	private const MAX_DESCRIPTION_VALUE_LENGTH = 200;

	/**
	 * Format the report data as structured MCP JSON.
	 *
	 * @param array $report_data Full report data from Report_Generator::generate().
	 * @return string JSON-encoded report.
	 */
	public function format( array $report_data ): string {
		$payload = $this->build_payload( $report_data );

		return (string) wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Get the content type.
	 *
	 * @return string MIME type for JSON.
	 */
	public function get_content_type(): string {
		return 'application/json; charset=utf-8';
	}

	/**
	 * Get the file extension.
	 *
	 * @return string File extension.
	 */
	public function get_file_extension(): string {
		return 'json';
	}

	/**
	 * Format and return the report as a decoded PHP array.
	 *
	 * Convenience method for MCP ability callbacks that need the data
	 * as an array rather than a JSON string.
	 *
	 * @param array $report_data Full report data from Report_Generator::generate().
	 * @return array Structured report payload.
	 */
	public function format_array( array $report_data ): array {
		return $this->build_payload( $report_data );
	}

	/**
	 * Build the full report payload.
	 *
	 * Shared builder used by both format() and format_array() to ensure
	 * the JSON and array variants stay in sync.
	 *
	 * @param array $report_data Full report data from Report_Generator::generate().
	 * @return array Structured report payload.
	 */
	private function build_payload( array $report_data ): array {
		$issues = $this->extract_issues( $report_data );

		$payload = array(
			'site'     => $this->build_site_identity(),
			'health'   => $this->build_health_summary( $report_data, $issues ),
			'issues'   => $this->build_issues_list( $issues ),
			'sections' => $this->build_sections( $report_data ),
		);

		/**
		 * Filter the full MCP formatter payload before encoding.
		 *
		 * @param array $payload     Structured report payload.
		 * @param array $report_data Raw report data from Report_Generator.
		 */
		return (array) apply_filters( 'wp_system_report_mcp_payload', $payload, $report_data );
	}

	/**
	 * Build site identity metadata.
	 *
	 * Provides essential context for AI agents to understand which site
	 * the report belongs to and its baseline configuration.
	 *
	 * @return array Site identity data.
	 */
	private function build_site_identity(): array {
		$site_url  = get_option( 'home' );
		$multisite = is_multisite();

		$identity = array(
			'url'            => $site_url,
			'name'           => get_option( 'blogname' ),
			'wordpress'      => get_bloginfo( 'version' ),
			'php'            => phpversion(),
			'multisite'      => $multisite,
			'plugin_version' => defined( 'WP_SYSTEM_REPORT_VERSION' ) ? WP_SYSTEM_REPORT_VERSION : 'unknown',
			'generated_at'   => gmdate( 'c' ),
		);

		/**
		 * Filter the MCP site identity block.
		 *
		 * @param array $identity Site identity data.
		 */
		return (array) apply_filters( 'wp_system_report_mcp_site_identity', $identity );
	}

	/**
	 * Build the health summary with score and issue counts.
	 *
	 * @param array $report_data Full report data.
	 * @param array $issues      Extracted issues.
	 * @return array Health summary data.
	 */
	private function build_health_summary( array $report_data, array $issues ): array {
		$critical_count = 0;
		$warning_count  = 0;
		$total_penalty  = 0;

		foreach ( $issues as $issue ) {
			if ( 'critical' === $issue['severity'] ) {
				++$critical_count;
				$total_penalty += $issue['weight'];
			} elseif ( 'warning' === $issue['severity'] ) {
				++$warning_count;
				$total_penalty += $issue['weight'];
			}
		}

		$score = max( 0, 100 - $total_penalty );

		// Determine rating.
		if ( $score >= 90 ) {
			$rating = 'excellent';
		} elseif ( $score >= 70 ) {
			$rating = 'good';
		} elseif ( $score >= 50 ) {
			$rating = 'fair';
		} else {
			$rating = 'needs_attention';
		}

		// Count sections and visible (non-private) checks.
		$section_count = 0;
		$check_count   = 0;
		foreach ( $report_data as $section ) {
			if ( ! empty( $section['fields'] ) ) {
				++$section_count;
				foreach ( $section['fields'] as $field ) {
					if ( empty( $field['private'] ) ) {
						++$check_count;
					}
				}
			}
		}

		return array(
			'score'           => $score,
			'rating'          => $rating,
			'critical_count'  => $critical_count,
			'warning_count'   => $warning_count,
			'section_count'   => $section_count,
			'check_count'     => $check_count,
			'fixes_available' => Features::has_fixers(),
		);
	}

	/**
	 * Build the prioritised issues list.
	 *
	 * Returns issues sorted by weight (highest first) with actionable
	 * fix references where available.
	 *
	 * @param array $issues Extracted issues.
	 * @return array Sorted issues with fix_id references.
	 */
	private function build_issues_list( array $issues ): array {
		// Sort by weight descending.
		usort(
			$issues,
			static function ( array $a, array $b ): int {
				return $b['weight'] <=> $a['weight'];
			}
		);

		$output = array();

		foreach ( $issues as $issue ) {
			$item = array(
				'severity'    => $issue['severity'],
				'weight'      => $issue['weight'],
				'category'    => $issue['category'],
				'title'       => $issue['title'],
				'description' => $issue['description'],
				'section'     => $issue['section'],
			);

			if ( ! empty( $issue['fix_id'] ) ) {
				$item['fix_id'] = $issue['fix_id'];
			}

			if ( ! empty( $issue['recommended'] ) ) {
				$item['recommended'] = $issue['recommended'];
			}

			$output[] = $item;
		}

		return $output;
	}

	/**
	 * Build compact section summaries.
	 *
	 * Token-efficiency strategy: only includes fields that are not "good"
	 * or "info" status in the detailed fields list. Good/info fields are
	 * counted but not enumerated to save context window space.
	 *
	 * @param array $report_data Full report data.
	 * @return array Section summaries.
	 */
	private function build_sections( array $report_data ): array {
		$sections = array();

		foreach ( $report_data as $section_id => $section ) {
			if ( empty( $section['fields'] ) ) {
				continue;
			}

			$fields         = $section['fields'];
			$total_count    = count( $fields );
			$good_count     = 0;
			$notable_fields = array();

			foreach ( $fields as $field ) {
				if ( ! empty( $field['private'] ) ) {
					--$total_count;
					continue;
				}

				$status = ! empty( $field['status'] ) ? $field['status'] : 'info';

				if ( 'good' === $status || 'info' === $status ) {
					++$good_count;
					continue;
				}

				// Only include non-good/non-info fields in detail.
				$notable = array(
					'label'  => $field['label'],
					'value'  => $field['value'],
					'status' => $status,
				);

				if ( ! empty( $field['description'] ) ) {
					$notable['hint'] = $field['description'];
				}

				if ( ! empty( $field['recommended'] ) ) {
					$notable['recommended'] = $field['recommended'];
				}

				if ( ! empty( $field['fix_id'] ) ) {
					$notable['fix_id'] = $field['fix_id'];
				}

				$notable_fields[] = $notable;
			}

			$section_data = array(
				'label'        => $section['label'],
				'total_checks' => $total_count,
				'passing'      => $good_count,
			);

			if ( ! empty( $notable_fields ) ) {
				// Truncate if exceeding limit (safety valve for huge sections).
				if ( count( $notable_fields ) > self::SECTION_FIELD_LIMIT ) {
					$section_data['notable_fields']       = array_slice( $notable_fields, 0, self::SECTION_FIELD_LIMIT );
					$section_data['truncated']            = true;
					$section_data['total_notable_fields'] = count( $notable_fields );
				} else {
					$section_data['notable_fields'] = $notable_fields;
				}
			}

			$sections[ $section_id ] = $section_data;
		}

		return $sections;
	}

	/**
	 * Extract issues from the report data.
	 *
	 * Scans all fields for warning and critical statuses, assigns weights,
	 * categories, and includes fix references.
	 *
	 * @param array $report_data Full report data.
	 * @return array Array of issue data.
	 */
	private function extract_issues( array $report_data ): array {
		$issues = array();

		foreach ( $report_data as $section_id => $section ) {
			if ( empty( $section['fields'] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field ) {
				$status = ! empty( $field['status'] ) ? $field['status'] : 'info';

				if ( 'warning' !== $status && 'critical' !== $status ) {
					continue;
				}

				if ( ! empty( $field['private'] ) ) {
					continue;
				}

				$weight = 'critical' === $status ? self::WEIGHT_CRITICAL : self::WEIGHT_WARNING;

				$issue = array(
					'severity'    => $status,
					'weight'      => $weight,
					'title'       => $field['label'],
					'description' => $this->compact_description( $field ),
					'category'    => $this->categorise( $field['label'], $section_id ),
					'section'     => $section_id,
				);

				if ( ! empty( $field['fix_id'] ) ) {
					$issue['fix_id'] = $field['fix_id'];
				}

				if ( ! empty( $field['recommended'] ) ) {
					$issue['recommended'] = $field['recommended'];
				}

				$issues[] = $issue;
			}
		}

		return $issues;
	}

	/**
	 * Build a compact description from a field.
	 *
	 * Produces a single-line summary suitable for token-limited contexts.
	 *
	 * @param array|\ArrayAccess $field Field data.
	 * @return string Compact description.
	 */
	private function compact_description( array|\ArrayAccess $field ): string {
		$value = $field['value'] ?? '';
		$value = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );

		if ( strlen( $value ) > self::MAX_DESCRIPTION_VALUE_LENGTH ) {
			$value = substr( $value, 0, self::MAX_DESCRIPTION_VALUE_LENGTH ) . '...';
		}

		$parts = array( $value );

		if ( ! empty( $field['description'] ) && is_scalar( $field['description'] ) ) {
			$parts[] = (string) $field['description'];
		}

		return implode( ' — ', $parts );
	}

	/**
	 * Categorise an issue by label keywords and section ID.
	 *
	 * @param string $label      Field label.
	 * @param string $section_id Section identifier.
	 * @return string Category name.
	 */
	private function categorise( string $label, string $section_id ): string {
		$lower = strtolower( $label );

		$keyword_map = array(
			'security'     => 'security',
			'ssl'          => 'security',
			'https'        => 'security',
			'permission'   => 'security',
			'debug'        => 'security',
			'file editor'  => 'security',
			'xml-rpc'      => 'security',
			'performance'  => 'performance',
			'cache'        => 'performance',
			'autoload'     => 'performance',
			'memory'       => 'performance',
			'object cache' => 'performance',
			'block types'  => 'performance',
			'update'       => 'updates',
			'version'      => 'updates',
			'email'        => 'email',
			'smtp'         => 'email',
			'upload'       => 'media',
			'media'        => 'media',
			'cron'         => 'cron',
			'loopback'     => 'connectivity',
			'connectivity' => 'connectivity',
			'rest api'     => 'rest_api',
		);

		foreach ( $keyword_map as $keyword => $category ) {
			if ( str_contains( $lower, $keyword ) ) {
				return $category;
			}
		}

		// Fall back to section-based categorisation.
		$section_map = array(
			'security'             => 'security',
			'performance'          => 'performance',
			'database'             => 'database',
			'update_health'        => 'updates',
			'email_delivery'       => 'email',
			'media_uploads'        => 'media',
			'cron_health'          => 'cron',
			'network_connectivity' => 'connectivity',
			'block_editor'         => 'editor',
			'rest_api_info'        => 'rest_api',
		);

		return $section_map[ $section_id ] ?? 'general';
	}
}
