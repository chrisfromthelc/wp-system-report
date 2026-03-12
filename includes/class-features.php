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
	 * Filterable via 'system_report_is_pro' to allow automated testing
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
}
