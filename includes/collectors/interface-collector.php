<?php
/**
 * Collector interface.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Interface for data collectors.
 *
 * Each collector gathers a specific category of system information.
 */
interface Collector {

	/**
	 * Get the unique identifier for this collector.
	 *
	 * @return string Slug identifier, e.g., 'wordpress_environment'.
	 */
	public function get_id();

	/**
	 * Get the display label for this collector's section.
	 *
	 * @return string Human-readable label, e.g., 'WordPress Environment'.
	 */
	public function get_label();

	/**
	 * Get a contextual description for AI export.
	 *
	 * @return string Description explaining what this section covers.
	 */
	public function get_description();

	/**
	 * Get the sort priority for this collector.
	 *
	 * Lower numbers appear first in the report.
	 *
	 * @return int Priority value.
	 */
	public function get_priority();

	/**
	 * Collect and return the data fields.
	 *
	 * Each field is an associative array with keys:
	 * - 'label'        (string) Display label.
	 * - 'value'        (string) Formatted display value.
	 * - 'debug'        (mixed)  Raw value for machine-readable export.
	 * - 'private'      (bool)   Whether to exclude from exports. Default false.
	 * - 'status'       (string) One of: 'good', 'warning', 'critical', 'info'. Default 'info'.
	 * - 'description'  (string) Contextual description for AI. Default ''.
	 * - 'recommended'  (string) Recommended value/range for AI. Default ''.
	 * - 'export_label' (string) Compact label for text export. Default same as 'label'.
	 *
	 * @return array Array of field arrays.
	 */
	public function collect();
}
