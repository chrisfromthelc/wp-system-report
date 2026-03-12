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
	 * Returns an array of Field value objects. Each field contains:
	 * - label        (string)      Display label.
	 * - value        (string)      Formatted display value.
	 * - debug        (mixed)       Raw value for machine-readable export.
	 * - status       (Status)      Status enum: Good, Warning, Critical, Info.
	 * - description  (string)      Contextual description for AI export.
	 * - recommended  (string)      Recommended value/range for AI export.
	 * - export_label (string)      Compact label for text export.
	 * - private      (bool)        Whether to exclude from exports.
	 * - fix_id       (string|null) Linked fixer identifier.
	 *
	 * @return \SystemReport\Field[] Array of Field objects.
	 */
	public function collect();
}
