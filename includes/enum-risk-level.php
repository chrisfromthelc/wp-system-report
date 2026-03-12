<?php
/**
 * Risk level enum.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Fixer operation risk level indicator.
 *
 * Categorises the potential impact of a fixer operation so that
 * the UI can prompt for appropriate user confirmation before
 * executing destructive or irreversible changes.
 */
enum RiskLevel: string {

	/**
	 * Safe, easily reversible operation.
	 */
	case Low = 'low';

	/**
	 * Requires caution; a backup is recommended before proceeding.
	 */
	case Medium = 'medium';

	/**
	 * Potentially destructive; requires explicit user confirmation.
	 */
	case High = 'high';

	/**
	 * Get the human-readable label for this risk level.
	 *
	 * @return string Translated label.
	 */
	public function get_label(): string {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- Valid in PHP 8.1 enums.
		return match ( $this ) {
			self::Low    => __( 'Low', 'wp-system-report' ),
			self::Medium => __( 'Medium', 'wp-system-report' ),
			self::High   => __( 'High', 'wp-system-report' ),
		};
	}

	/**
	 * Whether this risk level requires a confirmation prompt before execution.
	 *
	 * @return bool True for Medium and High risk levels.
	 */
	public function requires_confirmation(): bool {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- Valid in PHP 8.1 enums.
		return self::Low !== $this;
	}
}
