<?php
/**
 * Feature flags.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Central feature gate for Pro-ready capability separation.
 *
 * All feature checks in the plugin should route through this class
 * so that the Pro/Free split can be introduced in a single place
 * without touching individual feature implementations.
 *
 * When the Pro split happens, is_pro() will be updated to validate
 * a license key rather than unconditionally returning true.
 */
class Features {

	/**
	 * Whether the current installation has Pro capabilities.
	 *
	 * Always returns true in the current release. When the Pro split
	 * is introduced, this will perform a license key check.
	 *
	 * Filterable via 'wp_system_report_is_pro' to allow automated testing
	 * and third-party integrations to control feature availability.
	 *
	 * @return bool True when Pro features are available.
	 */
	public static function is_pro(): bool {
		/**
		 * Filter whether Pro features are available.
		 *
		 * @param bool $is_pro Whether the current installation is Pro.
		 */
		return (bool) apply_filters( 'wp_system_report_is_pro', true );
	}

	/**
	 * Whether the fixer interface is available.
	 *
	 * Fixers allow the plugin to automatically remediate issues identified
	 * by collectors and are gated behind the Pro tier.
	 *
	 * @return bool True when fixers are available.
	 */
	public static function has_fixers(): bool {
		return self::is_pro();
	}

	/**
	 * Whether MCP (Model Context Protocol) integration is available.
	 *
	 * MCP integration exposes system report data to AI development tools
	 * and is gated behind the Pro tier.
	 *
	 * @return bool True when MCP integration is available.
	 */
	public static function has_mcp(): bool {
		return self::is_pro();
	}

	/**
	 * Whether the health score feature is available.
	 *
	 * Health score aggregates all collector results into a single
	 * 0–100 score with a letter grade. Gated behind the Pro tier.
	 *
	 * @return bool True when health score is available.
	 */
	public static function has_health_score(): bool {
		return self::is_pro();
	}

	/**
	 * Whether the report history feature is available.
	 *
	 * Report history stores timestamped snapshots of system report data
	 * for trending and comparison. Gated behind the Pro tier.
	 *
	 * @return bool True when report history is available.
	 */
	public static function has_report_history(): bool {
		return self::is_pro();
	}

	/**
	 * Whether Abilities API integration is available.
	 *
	 * Abilities API integration exposes plugin capabilities as structured
	 * abilities discoverable by AI agents via the WordPress Abilities API
	 * and MCP Adapter. Gated behind the Pro tier and requires the
	 * Abilities API to be present (WordPress 6.9+).
	 *
	 * @return bool True when Abilities API integration is available.
	 */
	public static function has_abilities(): bool {
		return self::is_pro()
			&& class_exists( Abilities_Provider::class )
			&& Abilities_Provider::is_abilities_api_available();
	}
}
