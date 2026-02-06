<?php
/**
 * Drop-ins & Must-Use Plugins collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects drop-in replacements and must-use plugins.
 */
class Dropins_MU_Plugins extends Abstract_Collector {

	/**
	 * Get the collector ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'dropins_mu_plugins';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Drop-ins & Must-Use Plugins', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Drop-in replacements and must-use plugins.', 'system-report' );
	}

	/**
	 * Get the collector priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 80;
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
