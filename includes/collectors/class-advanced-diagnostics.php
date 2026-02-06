<?php
/**
 * Advanced Diagnostics collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects autoloaded options, disk usage, and error log information.
 */
class Advanced_Diagnostics extends Abstract_Collector {

	/**
	 * Get the collector ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'advanced_diagnostics';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Advanced Diagnostics', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Autoloaded options, disk usage, and error log information.', 'system-report' );
	}

	/**
	 * Get the collector priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 170;
	}

	/**
	 * Collect the data.
	 *
	 * @return array
	 */
	public function collect() {
		return array();
	}
}
