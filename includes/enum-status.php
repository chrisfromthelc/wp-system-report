<?php
/**
 * Status enum.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Diagnostic field status indicator.
 *
 * Replaces the legacy string-based status values ('good', 'warning',
 * 'critical', 'info') with a type-safe backed enum.
 */
enum Status: string {

	case Good     = 'good';
	case Warning  = 'warning';
	case Critical = 'critical';
	case Info     = 'info';

	/**
 * Create a Status from a legacy string value.
 *
 * Falls back to Info for unrecognised values, preserving backward
 * compatibility with third-party code that passes arbitrary strings.
 *
 * @param string $value Legacy status string.
 */
	public static function from_legacy( string $value ): self {
		return self::tryFrom( $value ) ?? self::Info;
	}

	/**
	 * Whether this status represents an actionable issue.
	 *
	 * @return bool True for Warning and Critical statuses.
	 */
	public function is_actionable(): bool {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- Valid in PHP 8.1 enums.
		return self::Warning === $this || self::Critical === $this;
	}
}
