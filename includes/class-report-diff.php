<?php
/**
 * Report diff engine.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Compares two report snapshots and produces a structured diff.
 *
 * The diff identifies:
 * - Sections added or removed between snapshots.
 * - Fields added or removed within sections.
 * - Changed field values and status transitions.
 * - Overall summary of improvements, degradations, and unchanged items.
 *
 * Status transitions are classified as:
 * - **Improved**: status moved toward 'good' (e.g. critical → warning, warning → good).
 * - **Degraded**: status moved away from 'good' (e.g. good → warning, warning → critical).
 * - **Changed**: value changed but status remained the same.
 */
class Report_Diff {

	/**
	 * Numeric weight for each status, higher = better.
	 *
	 * @var array<string, int>
	 */
	private const STATUS_RANK = array(
		'critical' => 0,
		'warning'  => 1,
		'info'     => 2,
		'good'     => 3,
	);

	/**
	 * Compare two reports and produce a structured diff.
	 *
	 * @param array  $before  The older report data (keyed by section ID).
	 * @param array  $after   The newer report data (keyed by section ID).
	 * @param string $before_label Optional label for the before snapshot (e.g. date).
	 * @param string $after_label  Optional label for the after snapshot (e.g. date).
	 * @return array{
	 *     sections: array<string, array{
	 *         label: string,
	 *         change_type: string,
	 *         fields: array
	 *     }>,
	 *     summary: array{
	 *         total_changes: int,
	 *         improved: int,
	 *         degraded: int,
	 *         added: int,
	 *         removed: int,
	 *         changed: int,
	 *         unchanged_sections: int
	 *     },
	 *     labels: array{before: string, after: string}
	 * }
	 */
	public function compare( array $before, array $after, string $before_label = '', string $after_label = '' ): array {
		$sections = array();
		$summary  = array(
			'total_changes'      => 0,
			'improved'           => 0,
			'degraded'           => 0,
			'added'              => 0,
			'removed'            => 0,
			'changed'            => 0,
			'unchanged_sections' => 0,
		);

		$all_section_ids = array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) );

		foreach ( $all_section_ids as $section_id ) {
			$in_before = isset( $before[ $section_id ] );
			$in_after  = isset( $after[ $section_id ] );

			if ( $in_before && ! $in_after ) {
				// Section removed.
				$label                     = $before[ $section_id ]['label'] ?? $section_id;
				$field_count               = count( $before[ $section_id ]['fields'] ?? array() );
				$sections[ $section_id ]   = array(
					'label'       => $label,
					'change_type' => 'removed',
					'fields'      => array(),
				);
				$summary['removed']       += $field_count;
				$summary['total_changes'] += $field_count;
				continue;
			}

			if ( ! $in_before && $in_after ) {
				// Section added.
				$label                     = $after[ $section_id ]['label'] ?? $section_id;
				$field_count               = count( $after[ $section_id ]['fields'] ?? array() );
				$sections[ $section_id ]   = array(
					'label'       => $label,
					'change_type' => 'added',
					'fields'      => array(),
				);
				$summary['added']         += $field_count;
				$summary['total_changes'] += $field_count;
				continue;
			}

			// Section exists in both — compare fields.
			$section_diff = $this->diff_section(
				$before[ $section_id ],
				$after[ $section_id ],
				$summary
			);

			if ( empty( $section_diff['fields'] ) ) {
				++$summary['unchanged_sections'];
				continue;
			}

			$sections[ $section_id ] = $section_diff;
		}

		/**
		 * Filter the computed report diff.
		 *
		 * @param array $diff     The full diff result.
		 * @param array $before   The older report data.
		 * @param array $after    The newer report data.
		 */
		$result = array(
			'sections' => $sections,
			'summary'  => $summary,
			'labels'   => array(
				'before' => $before_label,
				'after'  => $after_label,
			),
		);

		return apply_filters( 'wp_system_report_diff', $result, $before, $after );
	}

	/**
	 * Diff a single section's fields.
	 *
	 * @param array $before_section  The older section data.
	 * @param array $after_section   The newer section data.
	 * @param array $summary         Reference to the summary counters (modified in place).
	 * @return array{label: string, change_type: string, fields: array}
	 */
	private function diff_section( array $before_section, array $after_section, array &$summary ): array {
		$label         = $after_section['label'] ?? $before_section['label'] ?? '';
		$before_fields = $this->index_fields( $before_section['fields'] ?? array() );
		$after_fields  = $this->index_fields( $after_section['fields'] ?? array() );
		$all_keys      = array_unique( array_merge( array_keys( $before_fields ), array_keys( $after_fields ) ) );
		$field_diffs   = array();

		foreach ( $all_keys as $key ) {
			$in_before = isset( $before_fields[ $key ] );
			$in_after  = isset( $after_fields[ $key ] );

			if ( $in_before && ! $in_after ) {
				$field_diffs[] = array(
					'label'       => $this->get_field_label( $before_fields[ $key ] ),
					'change_type' => 'removed',
					'before'      => $this->extract_field_summary( $before_fields[ $key ] ),
					'after'       => null,
				);
				++$summary['removed'];
				++$summary['total_changes'];
				continue;
			}

			if ( ! $in_before && $in_after ) {
				$field_diffs[] = array(
					'label'       => $this->get_field_label( $after_fields[ $key ] ),
					'change_type' => 'added',
					'before'      => null,
					'after'       => $this->extract_field_summary( $after_fields[ $key ] ),
				);
				++$summary['added'];
				++$summary['total_changes'];
				continue;
			}

			// Both exist — check for changes.
			$change = $this->diff_field( $before_fields[ $key ], $after_fields[ $key ] );

			if ( null !== $change ) {
				$field_diffs[] = $change;

				switch ( $change['change_type'] ) {
					case 'improved':
						++$summary['improved'];
						break;
					case 'degraded':
						++$summary['degraded'];
						break;
					default:
						++$summary['changed'];
						break;
				}
				++$summary['total_changes'];
			}
		}

		return array(
			'label'       => $label,
			'change_type' => 'modified',
			'fields'      => $field_diffs,
		);
	}

	/**
	 * Compare two individual fields.
	 *
	 * @param Field|array $before The older field.
	 * @param Field|array $after  The newer field.
	 * @return array|null Diff entry or null if unchanged.
	 */
	private function diff_field( $before, $after ): ?array {
		$before_value  = $this->get_field_value( $before );
		$after_value   = $this->get_field_value( $after );
		$before_status = Field::get_status_string( $before );
		$after_status  = Field::get_status_string( $after );

		// No change.
		if ( $before_value === $after_value && $before_status === $after_status ) {
			return null;
		}

		$change_type = 'changed';
		if ( $before_status !== $after_status ) {
			$change_type = $this->classify_status_change( $before_status, $after_status );
		}

		return array(
			'label'       => $this->get_field_label( $after ),
			'change_type' => $change_type,
			'before'      => $this->extract_field_summary( $before ),
			'after'       => $this->extract_field_summary( $after ),
		);
	}

	/**
	 * Classify a status transition as improved or degraded.
	 *
	 * @param string $before_status The older status.
	 * @param string $after_status  The newer status.
	 * @return string 'improved', 'degraded', or 'changed'.
	 */
	private function classify_status_change( string $before_status, string $after_status ): string {
		$before_rank = self::STATUS_RANK[ $before_status ] ?? 2;
		$after_rank  = self::STATUS_RANK[ $after_status ] ?? 2;

		if ( $after_rank > $before_rank ) {
			return 'improved';
		}

		if ( $after_rank < $before_rank ) {
			return 'degraded';
		}

		return 'changed';
	}

	/**
	 * Index an array of fields by their label for comparison.
	 *
	 * @param array $fields Array of Field objects or arrays.
	 * @return array<string, Field|array> Fields indexed by label.
	 */
	private function index_fields( array $fields ): array {
		$indexed = array();

		foreach ( $fields as $field ) {
			$key = $this->get_field_label( $field );

			// Use label as the key; skip duplicates (keep first).
			if ( ! isset( $indexed[ $key ] ) ) {
				$indexed[ $key ] = $field;
			}
		}

		return $indexed;
	}

	/**
	 * Get the label from a field.
	 *
	 * @param Field|array $field A Field value object or associative array.
	 * @return string The field label.
	 */
	private function get_field_label( $field ): string {
		if ( $field instanceof Field ) {
			return $field->label;
		}

		return (string) ( $field['label'] ?? '' );
	}

	/**
	 * Get the display value from a field.
	 *
	 * @param Field|array $field A Field value object or associative array.
	 * @return string The field value.
	 */
	private function get_field_value( $field ): string {
		if ( $field instanceof Field ) {
			return $field->value;
		}

		return (string) ( $field['value'] ?? '' );
	}

	/**
	 * Extract a compact summary of a field for diff output.
	 *
	 * @param Field|array $field A Field value object or associative array.
	 * @return array{value: string, status: string}
	 */
	private function extract_field_summary( $field ): array {
		return array(
			'value'  => $this->get_field_value( $field ),
			'status' => Field::get_status_string( $field ),
		);
	}
}
