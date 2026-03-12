<?php
/**
 * Tests for the Report_Diff class.
 *
 * @package SystemReport
 */

namespace SystemReport\Tests;

use PHPUnit\Framework\TestCase;
use SystemReport\Field;
use SystemReport\Report_Diff;
use SystemReport\Status;

/**
 * @covers \SystemReport\Report_Diff
 */
class ReportDiffTest extends TestCase {

	private Report_Diff $diff;

	protected function setUp(): void {
		parent::setUp();
		$this->diff = new Report_Diff();
	}

	/**
	 * Create a sample report with configurable sections and fields.
	 *
	 * @param array $sections Map of section_id => array of Field configs.
	 * @return array Report structure.
	 */
	private function create_report( array $sections ): array {
		$report = array();

		foreach ( $sections as $section_id => $fields ) {
			$field_objects = array();
			foreach ( $fields as $field_config ) {
				$field_objects[] = new Field(
					$field_config['label'],
					$field_config['value'],
					$field_config['value'],
					Status::from_legacy( $field_config['status'] ?? 'info' )
				);
			}

			$report[ $section_id ] = array(
				'id'          => $section_id,
				'label'       => ucfirst( str_replace( '_', ' ', $section_id ) ),
				'description' => "Description for {$section_id}.",
				'fields'      => $field_objects,
			);
		}

		return $report;
	}

	public function test_identical_reports_produce_empty_diff(): void {
		$report = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Enabled', 'status' => 'good' ),
				),
			)
		);

		$result = $this->diff->compare( $report, $report );

		$this->assertEmpty( $result['sections'] );
		$this->assertSame( 0, $result['summary']['total_changes'] );
		$this->assertSame( 1, $result['summary']['unchanged_sections'] );
	}

	public function test_added_section_detected(): void {
		$before = $this->create_report( array() );
		$after  = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Enabled', 'status' => 'good' ),
					array( 'label' => 'HSTS', 'value' => 'Missing', 'status' => 'warning' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$this->assertArrayHasKey( 'security', $result['sections'] );
		$this->assertSame( 'added', $result['sections']['security']['change_type'] );
		$this->assertSame( 2, $result['summary']['added'] );
		$this->assertSame( 2, $result['summary']['total_changes'] );
	}

	public function test_removed_section_detected(): void {
		$before = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Enabled', 'status' => 'good' ),
				),
			)
		);
		$after = $this->create_report( array() );

		$result = $this->diff->compare( $before, $after );

		$this->assertArrayHasKey( 'security', $result['sections'] );
		$this->assertSame( 'removed', $result['sections']['security']['change_type'] );
		$this->assertSame( 1, $result['summary']['removed'] );
	}

	public function test_added_field_detected(): void {
		$before = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Enabled', 'status' => 'good' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Enabled', 'status' => 'good' ),
					array( 'label' => 'HSTS', 'value' => 'Missing', 'status' => 'warning' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$this->assertArrayHasKey( 'security', $result['sections'] );
		$fields = $result['sections']['security']['fields'];
		$this->assertCount( 1, $fields );
		$this->assertSame( 'HSTS', $fields[0]['label'] );
		$this->assertSame( 'added', $fields[0]['change_type'] );
	}

	public function test_removed_field_detected(): void {
		$before = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Enabled', 'status' => 'good' ),
					array( 'label' => 'HSTS', 'value' => 'Missing', 'status' => 'warning' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Enabled', 'status' => 'good' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$fields = $result['sections']['security']['fields'];
		$this->assertCount( 1, $fields );
		$this->assertSame( 'HSTS', $fields[0]['label'] );
		$this->assertSame( 'removed', $fields[0]['change_type'] );
	}

	public function test_value_change_detected(): void {
		$before = $this->create_report(
			array(
				'server' => array(
					array( 'label' => 'PHP Version', 'value' => '8.1.0', 'status' => 'good' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'server' => array(
					array( 'label' => 'PHP Version', 'value' => '8.3.0', 'status' => 'good' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$fields = $result['sections']['server']['fields'];
		$this->assertCount( 1, $fields );
		$this->assertSame( 'changed', $fields[0]['change_type'] );
		$this->assertSame( '8.1.0', $fields[0]['before']['value'] );
		$this->assertSame( '8.3.0', $fields[0]['after']['value'] );
	}

	public function test_status_improvement_detected(): void {
		$before = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Disabled', 'status' => 'critical' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Enabled', 'status' => 'good' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$fields = $result['sections']['security']['fields'];
		$this->assertSame( 'improved', $fields[0]['change_type'] );
		$this->assertSame( 1, $result['summary']['improved'] );
	}

	public function test_status_degradation_detected(): void {
		$before = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Enabled', 'status' => 'good' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Expired', 'status' => 'critical' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$fields = $result['sections']['security']['fields'];
		$this->assertSame( 'degraded', $fields[0]['change_type'] );
		$this->assertSame( 1, $result['summary']['degraded'] );
	}

	public function test_warning_to_good_is_improved(): void {
		$before = $this->create_report(
			array(
				'perf' => array(
					array( 'label' => 'Cache', 'value' => 'File', 'status' => 'warning' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'perf' => array(
					array( 'label' => 'Cache', 'value' => 'Redis', 'status' => 'good' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$this->assertSame( 'improved', $result['sections']['perf']['fields'][0]['change_type'] );
	}

	public function test_good_to_warning_is_degraded(): void {
		$before = $this->create_report(
			array(
				'perf' => array(
					array( 'label' => 'Cache', 'value' => 'Redis', 'status' => 'good' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'perf' => array(
					array( 'label' => 'Cache', 'value' => 'File', 'status' => 'warning' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$this->assertSame( 'degraded', $result['sections']['perf']['fields'][0]['change_type'] );
	}

	public function test_labels_passed_through(): void {
		$before = $this->create_report( array() );
		$after  = $this->create_report( array() );

		$result = $this->diff->compare( $before, $after, '2024-01-01', '2024-06-01' );

		$this->assertSame( '2024-01-01', $result['labels']['before'] );
		$this->assertSame( '2024-06-01', $result['labels']['after'] );
	}

	public function test_multiple_sections_with_mixed_changes(): void {
		$before = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Enabled', 'status' => 'good' ),
					array( 'label' => 'Firewall', 'value' => 'Active', 'status' => 'good' ),
				),
				'server'   => array(
					array( 'label' => 'PHP Version', 'value' => '8.1.0', 'status' => 'warning' ),
				),
				'old_section' => array(
					array( 'label' => 'Legacy', 'value' => 'Data', 'status' => 'info' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'security' => array(
					array( 'label' => 'SSL', 'value' => 'Enabled', 'status' => 'good' ),
					array( 'label' => 'Firewall', 'value' => 'Inactive', 'status' => 'critical' ),
				),
				'server'   => array(
					array( 'label' => 'PHP Version', 'value' => '8.3.0', 'status' => 'good' ),
				),
				'new_section' => array(
					array( 'label' => 'New Check', 'value' => 'OK', 'status' => 'good' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		// Security: Firewall degraded.
		$this->assertSame( 1, $result['summary']['degraded'] );
		// Server: PHP improved.
		$this->assertSame( 1, $result['summary']['improved'] );
		// old_section removed (1 field).
		$this->assertSame( 1, $result['summary']['removed'] );
		// new_section added (1 field).
		$this->assertSame( 1, $result['summary']['added'] );
		$this->assertSame( 4, $result['summary']['total_changes'] );
	}

	public function test_legacy_array_fields_supported(): void {
		$before = array(
			'section' => array(
				'id'     => 'section',
				'label'  => 'Section',
				'fields' => array(
					array( 'label' => 'Field A', 'value' => 'old', 'status' => 'good' ),
				),
			),
		);
		$after = array(
			'section' => array(
				'id'     => 'section',
				'label'  => 'Section',
				'fields' => array(
					array( 'label' => 'Field A', 'value' => 'new', 'status' => 'warning' ),
				),
			),
		);

		$result = $this->diff->compare( $before, $after );

		$fields = $result['sections']['section']['fields'];
		$this->assertCount( 1, $fields );
		$this->assertSame( 'degraded', $fields[0]['change_type'] );
		$this->assertSame( 'old', $fields[0]['before']['value'] );
		$this->assertSame( 'new', $fields[0]['after']['value'] );
	}

	public function test_empty_reports_produce_no_diff(): void {
		$result = $this->diff->compare( array(), array() );

		$this->assertEmpty( $result['sections'] );
		$this->assertSame( 0, $result['summary']['total_changes'] );
	}

	public function test_field_summary_contains_value_and_status(): void {
		$before = $this->create_report(
			array(
				'section' => array(
					array( 'label' => 'Test', 'value' => 'before_val', 'status' => 'good' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'section' => array(
					array( 'label' => 'Test', 'value' => 'after_val', 'status' => 'warning' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$field = $result['sections']['section']['fields'][0];
		$this->assertArrayHasKey( 'value', $field['before'] );
		$this->assertArrayHasKey( 'status', $field['before'] );
		$this->assertArrayHasKey( 'value', $field['after'] );
		$this->assertArrayHasKey( 'status', $field['after'] );
		$this->assertSame( 'before_val', $field['before']['value'] );
		$this->assertSame( 'good', $field['before']['status'] );
		$this->assertSame( 'after_val', $field['after']['value'] );
		$this->assertSame( 'warning', $field['after']['status'] );
	}

	public function test_added_field_has_null_before(): void {
		$before = $this->create_report(
			array(
				'section' => array(
					array( 'label' => 'Existing', 'value' => 'Val', 'status' => 'good' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'section' => array(
					array( 'label' => 'Existing', 'value' => 'Val', 'status' => 'good' ),
					array( 'label' => 'New Field', 'value' => 'New', 'status' => 'info' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$field = $result['sections']['section']['fields'][0];
		$this->assertSame( 'added', $field['change_type'] );
		$this->assertNull( $field['before'] );
		$this->assertNotNull( $field['after'] );
	}

	public function test_removed_field_has_null_after(): void {
		$before = $this->create_report(
			array(
				'section' => array(
					array( 'label' => 'Existing', 'value' => 'Val', 'status' => 'good' ),
					array( 'label' => 'Old Field', 'value' => 'Old', 'status' => 'info' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'section' => array(
					array( 'label' => 'Existing', 'value' => 'Val', 'status' => 'good' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$field = $result['sections']['section']['fields'][0];
		$this->assertSame( 'removed', $field['change_type'] );
		$this->assertNotNull( $field['before'] );
		$this->assertNull( $field['after'] );
	}

	public function test_critical_to_warning_is_improved(): void {
		$before = $this->create_report(
			array(
				'section' => array(
					array( 'label' => 'Test', 'value' => 'Bad', 'status' => 'critical' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'section' => array(
					array( 'label' => 'Test', 'value' => 'Better', 'status' => 'warning' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$this->assertSame( 'improved', $result['sections']['section']['fields'][0]['change_type'] );
	}

	public function test_unchanged_section_not_in_output(): void {
		$before = $this->create_report(
			array(
				'unchanged' => array(
					array( 'label' => 'A', 'value' => 'V', 'status' => 'good' ),
				),
				'changed'   => array(
					array( 'label' => 'B', 'value' => 'Old', 'status' => 'warning' ),
				),
			)
		);
		$after = $this->create_report(
			array(
				'unchanged' => array(
					array( 'label' => 'A', 'value' => 'V', 'status' => 'good' ),
				),
				'changed'   => array(
					array( 'label' => 'B', 'value' => 'New', 'status' => 'good' ),
				),
			)
		);

		$result = $this->diff->compare( $before, $after );

		$this->assertArrayNotHasKey( 'unchanged', $result['sections'] );
		$this->assertArrayHasKey( 'changed', $result['sections'] );
		$this->assertSame( 1, $result['summary']['unchanged_sections'] );
	}
}
