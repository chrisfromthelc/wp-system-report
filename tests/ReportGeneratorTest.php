<?php
/**
 * Report Generator tests.
 *
 * @package SystemReport
 */

use SystemReport\Report_Generator;
use SystemReport\Collectors\Collector;
use SystemReport\Collectors\Abstract_Collector;

/**
 * Test the Report_Generator class.
 */
class ReportGeneratorTest extends WP_UnitTestCase {

	/**
	 * Report generator instance.
	 *
	 * @var Report_Generator
	 */
	private $generator;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		$this->generator = new Report_Generator();
	}

	/**
	 * Test that a collector can be registered.
	 */
	public function test_register_collector() {
		$collector = $this->create_mock_collector( 'test_collector', 'Test', 'Description', 10 );
		$this->generator->register_collector( $collector );

		$collectors = $this->generator->get_collectors();
		$this->assertArrayHasKey( 'test_collector', $collectors );
	}

	/**
	 * Test that multiple collectors can be registered.
	 */
	public function test_register_multiple_collectors() {
		$this->generator->register_collector(
			$this->create_mock_collector( 'first', 'First', 'First desc', 10 )
		);
		$this->generator->register_collector(
			$this->create_mock_collector( 'second', 'Second', 'Second desc', 20 )
		);

		$collectors = $this->generator->get_collectors();
		$this->assertCount( 2, $collectors );
		$this->assertArrayHasKey( 'first', $collectors );
		$this->assertArrayHasKey( 'second', $collectors );
	}

	/**
	 * Test that collectors are sorted by priority.
	 */
	public function test_collectors_sorted_by_priority() {
		$this->generator->register_collector(
			$this->create_mock_collector( 'high', 'High Priority', '', 100 )
		);
		$this->generator->register_collector(
			$this->create_mock_collector( 'low', 'Low Priority', '', 1 )
		);
		$this->generator->register_collector(
			$this->create_mock_collector( 'mid', 'Mid Priority', '', 50 )
		);

		$collectors = $this->generator->get_collectors();
		$ids        = array_keys( $collectors );

		$this->assertSame( 'low', $ids[0] );
		$this->assertSame( 'mid', $ids[1] );
		$this->assertSame( 'high', $ids[2] );
	}

	/**
	 * Test that generate returns correct report structure.
	 */
	public function test_generate_returns_correct_structure() {
		$collector = $this->create_mock_collector( 'test_section', 'Test Section', 'A test section', 10 );
		$this->generator->register_collector( $collector );

		$report = $this->generator->generate();

		$this->assertArrayHasKey( 'test_section', $report );
		$this->assertSame( 'test_section', $report['test_section']['id'] );
		$this->assertSame( 'Test Section', $report['test_section']['label'] );
		$this->assertSame( 'A test section', $report['test_section']['description'] );
		$this->assertIsArray( $report['test_section']['fields'] );
	}

	/**
	 * Test that generate includes field data from collectors.
	 */
	public function test_generate_includes_field_data() {
		$fields = array(
			array(
				'label' => 'Version',
				'value' => '1.0.0',
			),
		);

		$collector = $this->create_mock_collector( 'test', 'Test', '', 10, $fields );
		$this->generator->register_collector( $collector );

		$report = $this->generator->generate();
		$this->assertCount( 1, $report['test']['fields'] );
		$this->assertSame( 'Version', $report['test']['fields'][0]['label'] );
		$this->assertSame( '1.0.0', $report['test']['fields'][0]['value'] );
	}

	/**
	 * Test that generate_section returns data for a specific collector.
	 */
	public function test_generate_section_returns_specific_section() {
		$this->generator->register_collector(
			$this->create_mock_collector( 'first', 'First', '', 10 )
		);
		$this->generator->register_collector(
			$this->create_mock_collector( 'second', 'Second', '', 20 )
		);

		$section = $this->generator->generate_section( 'first' );

		$this->assertNotNull( $section );
		$this->assertSame( 'first', $section['id'] );
		$this->assertSame( 'First', $section['label'] );
	}

	/**
	 * Test that generate_section returns null for unknown collector.
	 */
	public function test_generate_section_returns_null_for_unknown() {
		$section = $this->generator->generate_section( 'nonexistent' );
		$this->assertNull( $section );
	}

	/**
	 * Test that the wp_system_report_collectors filter works.
	 */
	public function test_collectors_filter() {
		$this->generator->register_collector(
			$this->create_mock_collector( 'original', 'Original', '', 10 )
		);

		// Add a filter to inject a new collector.
		$extra_collector = $this->create_mock_collector( 'injected', 'Injected', '', 5 );
		add_filter(
			'wp_system_report_collectors',
			function ( $collectors ) use ( $extra_collector ) {
				$collectors['injected'] = $extra_collector;
				return $collectors;
			}
		);

		$collectors = $this->generator->get_collectors();
		$this->assertArrayHasKey( 'injected', $collectors );
		$this->assertArrayHasKey( 'original', $collectors );

		// Injected should come first (priority 5 < 10).
		$ids = array_keys( $collectors );
		$this->assertSame( 'injected', $ids[0] );
	}

	/**
	 * Test that the wp_system_report_fields_{id} filter works.
	 */
	public function test_fields_filter() {
		$fields = array(
			array(
				'label' => 'Original Field',
				'value' => 'original',
			),
		);

		$collector = $this->create_mock_collector( 'test', 'Test', '', 10, $fields );
		$this->generator->register_collector( $collector );

		add_filter(
			'wp_system_report_fields_test',
			function ( $fields ) {
				$fields[] = array(
					'label' => 'Injected Field',
					'value' => 'injected',
				);
				return $fields;
			}
		);

		$report = $this->generator->generate();
		$this->assertCount( 2, $report['test']['fields'] );
		$this->assertSame( 'Injected Field', $report['test']['fields'][1]['label'] );
	}

	/**
	 * Test that registering a collector with the same ID replaces the previous one.
	 */
	public function test_duplicate_collector_id_replaces() {
		$this->generator->register_collector(
			$this->create_mock_collector( 'dupe', 'First', '', 10 )
		);
		$this->generator->register_collector(
			$this->create_mock_collector( 'dupe', 'Second', '', 20 )
		);

		$collectors = $this->generator->get_collectors();
		$this->assertCount( 1, $collectors );
		$this->assertSame( 'Second', $collectors['dupe']->get_label() );
	}

	/**
	 * Create a mock collector.
	 *
	 * @param string $id          Collector ID.
	 * @param string $label       Collector label.
	 * @param string $description Collector description.
	 * @param int    $priority    Collector priority.
	 * @param array  $fields      Fields to return from collect().
	 * @return Collector Mock collector.
	 */
	private function create_mock_collector( $id, $label, $description, $priority, $fields = array() ) {
		$mock = $this->createMock( Collector::class );
		$mock->method( 'get_id' )->willReturn( $id );
		$mock->method( 'get_label' )->willReturn( $label );
		$mock->method( 'get_description' )->willReturn( $description );
		$mock->method( 'get_priority' )->willReturn( $priority );
		$mock->method( 'collect' )->willReturn( $fields );
		return $mock;
	}
}
