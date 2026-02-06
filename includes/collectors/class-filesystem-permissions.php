<?php
/**
 * Filesystem Permissions collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects directory writability and file permission status.
 */
class Filesystem_Permissions extends Abstract_Collector {

	/**
	 * Get the collector ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'filesystem_permissions';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Filesystem Permissions', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Directory writability and file permission status.', 'system-report' );
	}

	/**
	 * Get the collector priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 110;
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
