<?php
/**
 * Fixer interface.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Interface for automated fixers.
 *
 * Each fixer addresses a specific diagnostic issue identified by a
 * collector field. Fixers are registered with the Fixer_Registry and
 * can be invoked from the admin UI or the REST API.
 */
interface Fixer {

	/**
	 * Get the unique slug identifier for this fixer.
	 *
	 * Must be lowercase, hyphen-separated, and globally unique across
	 * all registered fixers. Example: 'autoload_optimizer'.
	 *
	 * @return string Unique fixer slug.
	 */
	public function get_id();

	/**
	 * Get the human-readable display label for this fixer.
	 *
	 * @return string Translated display name.
	 */
	public function get_label();

	/**
	 * Get a description of what this fixer does.
	 *
	 * Should explain the problem being fixed and the action taken,
	 * in plain language suitable for display in the admin UI.
	 *
	 * @return string Translated description.
	 */
	public function get_description();

	/**
	 * Get the risk level of this fixer operation.
	 *
	 * Used by the UI to determine whether a confirmation prompt is
	 * required before executing the fix.
	 *
	 * @return Risk_Level Risk level enum case.
	 */
	public function get_risk_level();

	/**
	 * Get the category this fixer belongs to.
	 *
	 * Used for grouping fixers in the admin UI. Common values include
	 * 'performance', 'security', and 'database'.
	 *
	 * @return string Category slug.
	 */
	public function get_category();

	/**
	 * Determine whether this fix is applicable to the current system state.
	 *
	 * Called before displaying the fixer in the UI and before executing
	 * the fix. Should perform a lightweight check without making changes.
	 *
	 * @return bool True if the fix can be applied; false if already resolved
	 *              or not applicable to this environment.
	 */
	public function can_fix();

	/**
	 * Execute the fix operation.
	 *
	 * Implementations should capture a before-state snapshot, apply the
	 * change, capture an after-state snapshot, and return a Fix_Result.
	 * Should not throw exceptions; errors must be returned via
	 * Fix_Result::failure().
	 *
	 * @return Fix_Result Result of the fix operation.
	 */
	public function fix();
}
