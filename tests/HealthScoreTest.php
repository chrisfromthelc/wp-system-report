<?php
/**
 * Health Score tests.
 *
 * @package SystemReport
 */

use SystemReport\Health_Score;
use SystemReport\Report_Generator;
use SystemReport\Field;
use SystemReport\Status;
use SystemReport\Collectors\Collector;

/**
 * Test the Health_Score class.
 */
class HealthScoreTest extends WP_UnitTestCase {

	/**
	 * Health score instance.
	 *
	 * @var Health_Score
	 */
	private Health_Score $health_score;

	/**
	 * Report generator instance.
	 *
	 * @var Report_Generator
	 */
	private Report_Generator $generator;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->generator    = new Report_Generator();
		$this->health_score = new Health_Score( $this->generator );
	}

	/**
	 * Test that a perfect report yields a perfect score.
	 */
	public function test_all_good_returns_perfect_score(): void {
		$report = $this->build_report( array(
			'security' => array(
				$this->make_field( 'Test', 'OK', Status::Good ),
				$this->make_field( 'Test 2', 'OK', Status::Good ),
			),
		) );

		$result = $this->health_score->calculate( $report );

		$this->assertSame( 100, $result['score'] );
		$this->assertSame( 'A+', $result['grade'] );
	}

	/**
	 * Test that all critical fields yield score of 0.
	 */
	public function test_all_critical_returns_zero_score(): void {
		$report = $this->build_report( array(
			'security' => array(
				$this->make_field( 'Vuln', 'Bad', Status::Critical ),
				$this->make_field( 'Vuln 2', 'Bad', Status::Critical ),
			),
		) );

		$result = $this->health_score->calculate( $report );

		$this->assertSame( 0, $result['score'] );
		$this->assertSame( 'F', $result['grade'] );
	}

	/**
	 * Test that a mix of statuses produces a weighted result.
	 */
	public function test_mixed_statuses_produce_weighted_score(): void {
		$report = $this->build_report( array(
			'security' => array(
				$this->make_field( 'Good', 'OK', Status::Good ),
				$this->make_field( 'Bad', 'Issue', Status::Warning ),
			),
		) );

		$result = $this->health_score->calculate( $report );

		// (100 + 40) / 2 = 70.
		$this->assertSame( 70, $result['score'] );
		$this->assertSame( 'B', $result['grade'] );
	}

	/**
	 * Test that info fields are excluded from scoring.
	 */
	public function test_info_fields_excluded_from_scoring(): void {
		$report = $this->build_report( array(
			'security' => array(
				$this->make_field( 'Good', 'OK', Status::Good ),
				$this->make_field( 'Version', '1.0', Status::Info ),
			),
		) );

		$result = $this->health_score->calculate( $report );

		// Only the Good field is scored → 100.
		$this->assertSame( 100, $result['score'] );
		$this->assertSame( 1, $result['summary']['info'] );
	}

	/**
	 * Test that empty report returns 100.
	 */
	public function test_empty_report_returns_perfect_score(): void {
		$result = $this->health_score->calculate( array() );

		$this->assertSame( 100, $result['score'] );
		$this->assertSame( 'A+', $result['grade'] );
	}

	/**
	 * Test that category weighting affects the score.
	 */
	public function test_category_weighting(): void {
		// Security (weight 3.0) all good, performance (weight 2.5) all critical.
		$report = $this->build_report( array(
			'security'    => array(
				$this->make_field( 'Sec', 'OK', Status::Good ),
			),
			'performance' => array(
				$this->make_field( 'Perf', 'Slow', Status::Critical ),
			),
		) );

		$result = $this->health_score->calculate( $report );

		// Weighted: (100 * 3.0 + 0 * 2.5) / (3.0 + 2.5) = 300 / 5.5 ≈ 55.
		$this->assertSame( 55, $result['score'] );
	}

	/**
	 * Test that the breakdown includes correct per-section data.
	 */
	public function test_breakdown_structure(): void {
		$report = $this->build_report( array(
			'security' => array(
				$this->make_field( 'A', 'OK', Status::Good ),
				$this->make_field( 'B', 'Warn', Status::Warning ),
				$this->make_field( 'C', 'Bad', Status::Critical ),
			),
		) );

		$result = $this->health_score->calculate( $report );

		$this->assertArrayHasKey( 'security', $result['breakdown'] );

		$section = $result['breakdown']['security'];
		$this->assertSame( 3, $section['field_count'] );
		$this->assertSame( 1, $section['warnings'] );
		$this->assertSame( 1, $section['criticals'] );
		$this->assertSame( 3.0, $section['weight'] );
	}

	/**
	 * Test that the summary aggregates correctly across sections.
	 */
	public function test_summary_counts(): void {
		$report = $this->build_report( array(
			'security' => array(
				$this->make_field( 'A', 'OK', Status::Good ),
				$this->make_field( 'B', 'Info', Status::Info ),
			),
			'performance' => array(
				$this->make_field( 'C', 'Warn', Status::Warning ),
				$this->make_field( 'D', 'Bad', Status::Critical ),
			),
		) );

		$result = $this->health_score->calculate( $report );

		$this->assertSame( 4, $result['summary']['total_fields'] );
		$this->assertSame( 1, $result['summary']['good'] );
		$this->assertSame( 1, $result['summary']['warnings'] );
		$this->assertSame( 1, $result['summary']['criticals'] );
		$this->assertSame( 1, $result['summary']['info'] );
	}

	/**
	 * Test grade boundaries.
	 */
	public function test_grade_boundaries(): void {
		$this->assertSame( 'A+', Health_Score::score_to_grade( 100 ) );
		$this->assertSame( 'A+', Health_Score::score_to_grade( 95 ) );
		$this->assertSame( 'A', Health_Score::score_to_grade( 94 ) );
		$this->assertSame( 'A', Health_Score::score_to_grade( 80 ) );
		$this->assertSame( 'B', Health_Score::score_to_grade( 79 ) );
		$this->assertSame( 'B', Health_Score::score_to_grade( 65 ) );
		$this->assertSame( 'C', Health_Score::score_to_grade( 64 ) );
		$this->assertSame( 'C', Health_Score::score_to_grade( 50 ) );
		$this->assertSame( 'D', Health_Score::score_to_grade( 49 ) );
		$this->assertSame( 'D', Health_Score::score_to_grade( 35 ) );
		$this->assertSame( 'F', Health_Score::score_to_grade( 34 ) );
		$this->assertSame( 'F', Health_Score::score_to_grade( 0 ) );
	}

	/**
	 * Test the health score filter.
	 */
	public function test_score_filter(): void {
		add_filter( 'wp_system_report_health_score', function () {
			return 42;
		} );

		$report = $this->build_report( array(
			'security' => array(
				$this->make_field( 'A', 'OK', Status::Good ),
			),
		) );

		$result = $this->health_score->calculate( $report );

		$this->assertSame( 42, $result['score'] );

		remove_all_filters( 'wp_system_report_health_score' );
	}

	/**
	 * Test the category weights filter.
	 */
	public function test_weights_filter(): void {
		// Make security weight 0 and performance weight 10.
		add_filter( 'wp_system_report_health_score_weights', function ( $weights ) {
			$weights['security']    = 0.0;
			$weights['performance'] = 10.0;
			return $weights;
		} );

		$report = $this->build_report( array(
			'security'    => array(
				$this->make_field( 'Sec', 'Bad', Status::Critical ),
			),
			'performance' => array(
				$this->make_field( 'Perf', 'OK', Status::Good ),
			),
		) );

		$result = $this->health_score->calculate( $report );

		// Security has weight 0 so doesn't count.
		// Only performance: 100 * 10 / 10 = 100.
		$this->assertSame( 100, $result['score'] );

		remove_all_filters( 'wp_system_report_health_score_weights' );
	}

	/**
	 * Test that unknown status values are treated as non-scoring (like info).
	 *
	 * An unrecognised status should not inflate the score by defaulting to 100 pts.
	 */
	public function test_unknown_status_treated_as_non_scoring(): void {
		$report = $this->build_report( array(
			'security' => array(
				$this->make_field( 'Good', 'OK', Status::Good ),
				// Inject a raw-array field with an unrecognised status string.
				array(
					'label'  => 'Unknown',
					'value'  => 'mystery',
					'status' => 'future_status',
				),
			),
		) );

		$result = $this->health_score->calculate( $report );

		// Only the Good field scores; unknown is treated as info.
		$this->assertSame( 100, $result['score'] );
		$this->assertSame( 1, $result['summary']['info'] );
	}

	/**
	 * Test that sections with only info fields are excluded from breakdown.
	 */
	public function test_info_only_sections_excluded_from_breakdown(): void {
		$report = $this->build_report( array(
			'post_type_counts' => array(
				$this->make_field( 'Posts', '42', Status::Info ),
				$this->make_field( 'Pages', '10', Status::Info ),
			),
		) );

		$result = $this->health_score->calculate( $report );

		// Section has no scored fields so is excluded from breakdown.
		$this->assertArrayNotHasKey( 'post_type_counts', $result['breakdown'] );
		// But info fields are counted in summary.
		$this->assertSame( 2, $result['summary']['info'] );
	}

	/**
	 * Build a mock report structure.
	 *
	 * @param array<string, Field[]> $sections Map of section_id => fields.
	 * @return array Report data in the standard format.
	 */
	private function build_report( array $sections ): array {
		$report = array();

		foreach ( $sections as $id => $fields ) {
			$report[ $id ] = array(
				'id'          => $id,
				'label'       => ucfirst( str_replace( '_', ' ', $id ) ),
				'description' => "Test section: {$id}",
				'fields'      => $fields,
			);
		}

		return $report;
	}

	/**
	 * Create a Field instance.
	 *
	 * @param string $label  Field label.
	 * @param string $value  Field value.
	 * @param Status $status Field status.
	 * @return Field Field instance.
	 */
	private function make_field( string $label, string $value, Status $status ): Field {
		return new Field(
			label:  $label,
			value:  $value,
			status: $status,
		);
	}
}
