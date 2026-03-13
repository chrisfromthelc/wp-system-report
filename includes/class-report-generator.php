<?php
/**
 * Report generator.
 *
 * @package SystemReport
 */

namespace SystemReport;

use SystemReport\Collectors\Collector;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates data collection from all registered collectors.
 */
class Report_Generator {

	/**
	 * Registered collectors.
	 *
	 * @var Collector[]
	 */
	private array $collectors = array();

	/**
	 * Register a collector.
	 *
	 * @param Collector $collector Collector instance.
	 */
	public function register_collector( Collector $collector ): void {
		$this->collectors[ $collector->get_id() ] = $collector;
	}

	/**
	 * Get all registered collectors, sorted by priority.
	 *
	 * Applies the 'wp_system_report_collectors' filter to allow third-party
	 * plugins to add, remove, or reorder collectors.
	 *
	 * @return Collector[] Sorted array of collectors.
	 */
	public function get_collectors() {
		/**
		 * Filter the registered collectors.
		 *
		 * @param Collector[] $collectors Associative array of collector ID => Collector instance.
		 */
		$collectors = apply_filters( 'wp_system_report_collectors', $this->collectors );

		// Sort by priority.
		uasort(
			$collectors,
			function ( Collector $a, Collector $b ): int {
				return $a->get_priority() <=> $b->get_priority();
			}
		);

		return $collectors;
	}

	/**
	 * Generate the full report from all collectors.
	 *
	 * @return array Report data keyed by collector ID, each containing:
	 *               - 'id'          (string) Collector ID.
	 *               - 'label'       (string) Section label.
	 *               - 'description' (string) Section description for AI.
	 *               - 'fields'      (array)  Collected field data.
	 */
	public function generate(): array {
		$report     = array();
		$collectors = $this->get_collectors();

		foreach ( $collectors as $id => $collector ) {
			// Use cached data for collectors that extend Abstract_Collector.
			if ( $collector instanceof Collectors\Abstract_Collector ) {
				$fields = $collector->get_cached_data();
			} else {
				$fields = $collector->collect();
			}

			/**
			 * Filter the fields for a specific collector.
			 *
			 * @param array     $fields    Collected field data.
			 * @param Collector $collector The collector instance.
			 */
			$fields = apply_filters( "wp_system_report_fields_{$id}", $fields, $collector );

			$report[ $id ] = array(
				'id'          => $id,
				'label'       => $collector->get_label(),
				'description' => $collector->get_description(),
				'fields'      => $fields,
			);
		}

		/**
		 * Fires after a full system report is generated.
		 *
		 * Allows other components (e.g. the AI context file generator) to
		 * react to report generation without modifying the report data.
		 *
		 * @param array $report The complete report data.
		 */
		do_action( 'wp_system_report_generated', $report );

		return $report;
	}

	/**
	 * Flush all collector caches.
	 *
	 * Iterates every registered collector that extends Abstract_Collector
	 * and deletes its transient so the next generate() call returns fresh data.
	 *
	 * Called on the System Report admin page load to guarantee the admin
	 * always sees current information.
	 *
	 * @since 2.0.0
	 */
	public function flush_all_caches(): void {
		foreach ( $this->collectors as $collector ) {
			if ( $collector instanceof Collectors\Abstract_Collector ) {
				$collector->flush_cache();
			}
		}
	}

	/**
	 * Generate data for a single collector section.
	 *
	 * @param string $id Collector ID.
	 * @return array|null Section data array, or null if collector not found.
	 */
	public function generate_section( $id ): ?array {
		$collectors = $this->get_collectors();

		if ( ! isset( $collectors[ $id ] ) ) {
			return null;
		}

		$collector = $collectors[ $id ];

		if ( $collector instanceof Collectors\Abstract_Collector ) {
			$fields = $collector->get_cached_data();
		} else {
			$fields = $collector->collect();
		}

		/** This filter is documented in includes/class-report-generator.php */
		$fields = apply_filters( "wp_system_report_fields_{$id}", $fields, $collector );

		return array(
			'id'          => $id,
			'label'       => $collector->get_label(),
			'description' => $collector->get_description(),
			'fields'      => $fields,
		);
	}
}
