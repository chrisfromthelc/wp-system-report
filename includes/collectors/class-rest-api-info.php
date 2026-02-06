<?php
/**
 * REST API collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects REST API availability and registered namespaces.
 */
class REST_API_Info extends Abstract_Collector {

	/**
	 * Get the collector ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'rest_api_info';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'REST API', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'REST API availability and registered namespaces.', 'system-report' );
	}

	/**
	 * Get the collector priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 140;
	}

	/**
	 * Collect the data.
	 *
	 * @return array
	 */
	public function collect() {
		$fields = array();

		// REST API URL.
		$fields[] = $this->make_field(
			__( 'REST API URL', 'system-report' ),
			rest_url()
		);

		// REST Prefix.
		$fields[] = $this->make_field(
			__( 'REST Prefix', 'system-report' ),
			rest_get_url_prefix()
		);

		// Get registered namespaces.
		$rest_server = rest_get_server();
		$namespaces  = $rest_server ? $rest_server->get_namespaces() : array();

		// Registered Namespaces (comma-separated list).
		$fields[] = $this->make_field(
			__( 'Registered Namespaces', 'system-report' ),
			! empty( $namespaces ) ? implode( ', ', $namespaces ) : __( 'None', 'system-report' )
		);

		// Total Namespaces count.
		$fields[] = $this->make_field(
			__( 'Total Namespaces', 'system-report' ),
			count( $namespaces )
		);

		return $fields;
	}
}
