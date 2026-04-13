<?php
/**
 * Agent guidance for MCP abilities.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Provides environment-aware guidance, rules, and thresholds for AI agents.
 *
 * Static helper class (same pattern as Settings) that returns structured data
 * for the get-agent-context ability and _environment enrichment. All data is
 * distilled from the wp-intelligence.md reference document.
 */
class Agent_Guidance {

	/**
	 * Get the full agent context for the get-agent-context ability.
	 *
	 * @return array Complete guidance context.
	 */
	public static function get_full_context(): array {
		$context = array(
			'environment'    => self::get_environment_context(),
			'rules'          => self::get_agent_rules(),
			'thresholds'     => self::get_thresholds(),
			'php_lifecycle'  => self::get_php_lifecycle(),
			'ability_hints'  => self::get_all_ability_hints(),
			'plugin_version' => WP_SYSTEM_REPORT_VERSION,
			'generated_at'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
		);

		if ( self::is_woocommerce_active() ) {
			$context['woocommerce'] = self::get_woocommerce_context();
		}

		return $context;
	}

	/**
	 * Get environment context snapshot.
	 *
	 * Lightweight site context included in every ability response via _environment.
	 *
	 * @return array Environment data.
	 */
	public static function get_environment_context(): array {
		$environment = self::detect_environment_type();

		return array(
			'environment_type'    => $environment,
			'is_production'       => 'production' === $environment,
			'is_local'            => 'local' === $environment,
			'is_development'      => 'development' === $environment,
			'is_staging'          => 'staging' === $environment,
			'hosting_provider'    => self::detect_hosting_provider(),
			'is_multisite'        => is_multisite(),
			'is_block_theme'      => ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) ? true : false,
			'has_woocommerce'     => self::is_woocommerce_active(),
			'has_object_cache'    => (bool) wp_using_ext_object_cache(),
			'active_plugin_count' => count( get_option( 'active_plugins', array() ) ),
			'php_version'         => PHP_VERSION,
			'wp_version'          => get_bloginfo( 'version' ),
		);
	}

	/**
	 * Get agent behavioral rules.
	 *
	 * Three groups: safety rules the agent must follow, actions it must never
	 * recommend, and environment-specific severity overrides.
	 *
	 * @return array Rule groups.
	 */
	public static function get_agent_rules(): array {
		return array(
			'safety_rules'          => array(
				__( 'Always call wp-system-report/get-agent-context BEFORE analyzing reports or making recommendations.', 'wp-system-report' ),
				__( 'Check environment_type before recommending security hardening. Local/development sites have relaxed rules.', 'wp-system-report' ),
				__( 'Trust the severity levels in issue responses. Do not upgrade warnings to critical or vice versa.', 'wp-system-report' ),
				__( 'When recommending database changes, always require explicit user confirmation and a confirmed backup.', 'wp-system-report' ),
				__( 'For plugin or theme changes, explain the impact before recommending activation or deletion.', 'wp-system-report' ),
				__( 'If WP_DEBUG is enabled on production, recommend disabling but ensure WP_DEBUG_LOG has an alternative.', 'wp-system-report' ),
				__( 'Inactive plugins are cleanup candidates, not security emergencies. Recommend removal, do not alarm.', 'wp-system-report' ),
				__( 'Object cache is a performance optimization. Missing object cache is informational, not a problem.', 'wp-system-report' ),
				__( 'Always verify wp-config.php backup before recommending modifications.', 'wp-system-report' ),
				__( 'Prefer WP-CLI commands over direct database queries when suggesting fixes.', 'wp-system-report' ),
			),
			'never_recommend'       => array(
				__( 'Never recommend chmod 777 or world-writable permissions on any file or directory.', 'wp-system-report' ),
				__( 'Never recommend disabling HTTPS or SSL verification.', 'wp-system-report' ),
				__( 'Never recommend editing WordPress core files directly.', 'wp-system-report' ),
				__( 'Never recommend WP_DEBUG_DISPLAY = true on production.', 'wp-system-report' ),
				__( 'Never recommend running DROP, TRUNCATE, or DELETE queries without explicit user confirmation.', 'wp-system-report' ),
				__( 'Never recommend deactivating security plugins without an alternative protection plan.', 'wp-system-report' ),
				__( 'Never recommend using the MySQL root account for the WordPress database user.', 'wp-system-report' ),
				__( 'Never recommend deleting xmlrpc.php — disable via filter or server config instead.', 'wp-system-report' ),
				__( 'Never recommend running multiple WAF or firewall plugins simultaneously.', 'wp-system-report' ),
				__( 'Never recommend changing the database table prefix on an existing production site.', 'wp-system-report' ),
				__( 'Never recommend caching plugins on managed hosting that provides built-in caching (WP Engine, Kinsta, Pantheon).', 'wp-system-report' ),
				__( 'Never output, display, or suggest specific API keys, passwords, or security tokens.', 'wp-system-report' ),
			),
			'environment_overrides' => array(
				__( 'HTTPS issues on local/development are informational only. On staging/production, they are critical.', 'wp-system-report' ),
				__( 'WP_DEBUG enabled is expected and good on local/development. On production, it is critical.', 'wp-system-report' ),
				__( 'File permissions 777 on local is informational. On production, it is critical.', 'wp-system-report' ),
				__( 'No object cache on local/development is OK. On production with 15+ plugins, it is a warning.', 'wp-system-report' ),
				__( 'Search engine discouragement (blog_public=0) is expected on staging/development. On production, verify intent.', 'wp-system-report' ),
				__( 'Database root user is common on local development. On production, it is critical.', 'wp-system-report' ),
				__( 'Outdated PHP on local is informational. On production, past-EOL PHP is critical.', 'wp-system-report' ),
				__( 'DISALLOW_FILE_EDIT not set is OK on local/development. On production, it is a warning.', 'wp-system-report' ),
			),
		);
	}

	/**
	 * Get performance and configuration thresholds.
	 *
	 * Values sourced from WordPress core Site Health checks, hosting provider
	 * documentation, and the wp-intelligence.md reference.
	 *
	 * @return array Threshold definitions.
	 */
	public static function get_thresholds(): array {
		return array(
			'php_version_minimum'         => '8.1',
			'php_version_recommended'     => '8.3',
			'php_memory_limit_minimum'    => '256M',
			'wp_memory_limit_minimum'     => '128M',
			'wp_memory_limit_recommended' => '256M',
			'max_execution_time_minimum'  => 30,
			'autoload_size_ok_kb'         => 400,
			'autoload_size_warning_kb'    => 800,
			'autoload_size_critical_kb'   => 2048,
			'database_size_warning_mb'    => 1024,
			'rewrite_rules_warning'       => 500,
			'rewrite_rules_critical'      => 2000,
			'cron_events_warning'         => 100,
			'cron_events_critical'        => 200,
			'queries_per_page_warning'    => 100,
			'queries_per_page_critical'   => 200,
		);
	}

	/**
	 * Get PHP version lifecycle data.
	 *
	 * Current through April 2026. EOL dates aligned to the PHP release
	 * cycle (2 years active + 2 years security-only, December 31 cutoffs).
	 *
	 * @return array PHP version lifecycle entries.
	 */
	public static function get_php_lifecycle(): array {
		return array(
			array(
				'version'            => '7.4',
				'active_support_end' => '2021-11-28',
				'security_end'       => '2022-11-28',
				'status'             => 'end-of-life',
				'classification'     => 'critical',
			),
			array(
				'version'            => '8.0',
				'active_support_end' => '2022-11-26',
				'security_end'       => '2023-11-26',
				'status'             => 'end-of-life',
				'classification'     => 'critical',
			),
			array(
				'version'            => '8.1',
				'active_support_end' => '2023-11-25',
				'security_end'       => '2025-12-31',
				'status'             => 'end-of-life',
				'classification'     => 'critical',
			),
			array(
				'version'            => '8.2',
				'active_support_end' => '2024-12-31',
				'security_end'       => '2026-12-31',
				'status'             => 'security-only',
				'classification'     => 'warning',
			),
			array(
				'version'            => '8.3',
				'active_support_end' => '2025-12-31',
				'security_end'       => '2027-12-31',
				'status'             => 'security-only',
				'classification'     => 'info',
			),
			array(
				'version'            => '8.4',
				'active_support_end' => '2026-12-31',
				'security_end'       => '2028-12-31',
				'status'             => 'active',
				'classification'     => 'ok',
			),
			array(
				'version'            => '8.5',
				'active_support_end' => '2027-12-31',
				'security_end'       => '2029-12-31',
				'status'             => 'active',
				'classification'     => 'ok',
			),
		);
	}

	/**
	 * Get contextual hints for a specific ability.
	 *
	 * @param string $ability_id Ability slug without namespace (e.g., 'get-issues').
	 * @return array Hint strings, or empty array for unknown abilities.
	 */
	public static function get_ability_hints( string $ability_id ): array {
		$hints = array(
			'get-issues'        => array(
				__( 'Prioritize critical issues before warnings.', 'wp-system-report' ),
				__( 'Cross-reference PHP errors with get-error-log for stack traces.', 'wp-system-report' ),
				__( 'Check _environment.is_production to calibrate severity language.', 'wp-system-report' ),
				__( 'On local/development, many security warnings are informational only.', 'wp-system-report' ),
			),
			'get-report'        => array(
				__( 'Use format=markdown for analysis, format=json for programmatic access.', 'wp-system-report' ),
				__( 'The full report can be large; prefer get-section for targeted investigation.', 'wp-system-report' ),
				__( 'Compare values against thresholds from get-agent-context.', 'wp-system-report' ),
			),
			'get-section'       => array(
				__( 'Use the available_sections list in error responses to discover valid IDs.', 'wp-system-report' ),
				__( 'Combine with get-agent-context thresholds for environment-aware assessment.', 'wp-system-report' ),
			),
			'get-error-log'     => array(
				__( 'If no log exists, suggest enabling debug logging via toggle-debug.', 'wp-system-report' ),
				__( 'Look for recurring patterns and timestamps, not just individual errors.', 'wp-system-report' ),
				__( 'Fatal errors and warnings are highest priority for investigation.', 'wp-system-report' ),
				__( 'Sensitive data (paths, credentials) is already redacted.', 'wp-system-report' ),
			),
			'get-debug-status'  => array(
				__( 'WP_DEBUG_DISPLAY should always be false on production.', 'wp-system-report' ),
				__( 'If can_modify is false, the host restricts wp-config.php changes.', 'wp-system-report' ),
			),
			'toggle-debug'      => array(
				__( 'Always confirm with the user before toggling debug on production.', 'wp-system-report' ),
				__( 'On production, always disable debug logging after investigation.', 'wp-system-report' ),
				__( 'Enabling debug creates a log file that may contain sensitive information.', 'wp-system-report' ),
			),
			'get-agent-context' => array(
				__( 'This ability should be called first before any other wp-system-report ability.', 'wp-system-report' ),
				__( 'Use environment_type to determine appropriate severity for findings.', 'wp-system-report' ),
				__( 'The never_recommend list contains absolute prohibitions.', 'wp-system-report' ),
			),
		);

		return $hints[ $ability_id ] ?? array();
	}

	/**
	 * Get WooCommerce-specific guidance context.
	 *
	 * Only called when WooCommerce is active. Includes HPOS status,
	 * resource requirements, Action Scheduler thresholds, and caching rules.
	 *
	 * @return array WooCommerce guidance data.
	 */
	public static function get_woocommerce_context(): array {
		return array(
			'is_active'                   => true,
			'hpos_status'                 => self::detect_hpos_status(),
			'memory_minimum'              => '256M',
			'memory_recommended'          => '512M',
			'max_input_vars_minimum'      => 2000,
			'max_input_vars_recommended'  => 5000,
			'action_scheduler_thresholds' => array(
				'pending_warning'  => 100,
				'pending_critical' => 1000,
				'failed_warning'   => 1,
				'failed_critical'  => 10,
			),
			'session_table_thresholds'    => array(
				'size_warning_mb'  => 10,
				'size_critical_mb' => 50,
				'max_age_hours'    => 48,
			),
			'cart_fragments_note'         => __( 'Cart fragments (wc-ajax=get_refreshed_fragments) fire on every page load. On slow hosts, dequeue on non-WooCommerce pages or delay until user interaction.', 'wp-system-report' ),
			'caching_exclusions'          => array(
				'pages'   => array( 'cart', 'checkout', 'my-account' ),
				'cookies' => array( 'woocommerce_cart_hash', 'woocommerce_items_in_cart', 'wp_woocommerce_session_' ),
			),
		);
	}

	/**
	 * Get all ability hints keyed by ability ID.
	 *
	 * @return array All hints keyed by ability slug.
	 */
	private static function get_all_ability_hints(): array {
		$ability_ids = array(
			'get-issues',
			'get-report',
			'get-section',
			'get-error-log',
			'get-debug-status',
			'toggle-debug',
			'get-agent-context',
		);

		$all = array();
		foreach ( $ability_ids as $id ) {
			$all[ $id ] = self::get_ability_hints( $id );
		}

		return $all;
	}

	/**
	 * Detect the hosting provider via constants and hostname patterns.
	 *
	 * @return string Provider identifier or 'unknown'.
	 */
	private static function detect_hosting_provider(): string {
		if ( defined( 'IS_WPE' ) ) {
			return 'wpengine';
		}
		if ( defined( 'KINSTAMU_VERSION' ) ) {
			return 'kinsta';
		}
		if ( defined( 'PANTHEON_ENVIRONMENT' ) ) {
			return 'pantheon';
		}
		if ( defined( 'IS_PRESSABLE' ) ) {
			return 'pressable';
		}
		if ( defined( 'GD_SYSTEM_PLUGIN_DIR' ) ) {
			return 'godaddy';
		}
		if ( defined( 'IS_FLYWHEEL' ) ) {
			return 'flywheel';
		}
		if ( defined( 'STARTER_CONFIG' ) && defined( 'STARTER_PAGE_CACHE' ) ) {
			return 'developer_starter';
		}

		return 'unknown';
	}

	/**
	 * Detect the WordPress environment type with hostname fallback.
	 *
	 * Uses wp_get_environment_type() as primary source. When it returns
	 * 'production' (the default), checks hostname patterns to infer
	 * local development environments.
	 *
	 * @return string Environment type: 'local', 'development', 'staging', or 'production'.
	 */
	private static function detect_environment_type(): string {
		$type = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

		// If explicitly set to non-production, trust it.
		if ( 'production' !== $type ) {
			return $type;
		}

		// Infer local environment from hostname when WP_ENVIRONMENT_TYPE is not set.
		$home_url = get_option( 'home', '' );
		$host     = wp_parse_url( $home_url, PHP_URL_HOST );

		if ( empty( $host ) ) {
			return $type;
		}

		$host = strtolower( $host );

		// Local TLDs and patterns.
		$local_patterns = array( '.local', '.test', '.dev', '.localhost', '.invalid' );
		foreach ( $local_patterns as $pattern ) {
			if ( substr( $host, -strlen( $pattern ) ) === $pattern ) {
				return 'local';
			}
		}

		// Localhost and private IPs.
		if ( 'localhost' === $host || '127.0.0.1' === $host ) {
			return 'local';
		}
		if ( preg_match( '/^(192\.168\.|10\.|172\.(1[6-9]|2\d|3[01])\.)/', $host ) ) {
			return 'local';
		}

		// Staging hostname patterns.
		$staging_prefixes = array( 'staging.', 'stage.', 'dev.', 'test.' );
		foreach ( $staging_prefixes as $prefix ) {
			if ( 0 === strpos( $host, $prefix ) ) {
				return 'staging';
			}
		}

		// Managed hosting staging patterns.
		if ( false !== strpos( $host, '.wpengine.com' ) ) {
			return 'staging';
		}
		if ( false !== strpos( $host, '.kinsta.cloud' ) ) {
			return 'staging';
		}

		return $type;
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool True if WooCommerce is loaded.
	 */
	private static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Detect WooCommerce HPOS (High-Performance Order Storage) status.
	 *
	 * @return string One of: 'hpos_active', 'hpos_sync', 'legacy_posts', 'legacy_sync', 'unknown'.
	 */
	private static function detect_hpos_status(): string {
		if ( ! self::is_woocommerce_active() ) {
			return 'unknown';
		}

		if ( ! function_exists( 'wc_get_container' ) ) {
			return 'unknown';
		}

		try {
			$container = wc_get_container();

			// Check if the CustomOrdersTableController class exists and is accessible.
			if ( ! class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
				return 'legacy_posts';
			}

			$controller = $container->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );

			if ( ! method_exists( $controller, 'custom_orders_table_usage_is_enabled' ) ) {
				return 'unknown';
			}

			$hpos_enabled = $controller->custom_orders_table_usage_is_enabled();

			// Check sync status.
			if ( class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer' ) ) {
				$synchronizer = $container->get( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class );
				$sync_enabled = method_exists( $synchronizer, 'data_sync_is_enabled' ) && $synchronizer->data_sync_is_enabled();

				if ( $hpos_enabled && $sync_enabled ) {
					return 'hpos_sync';
				}
				if ( $hpos_enabled ) {
					return 'hpos_active';
				}
				if ( $sync_enabled ) {
					return 'legacy_sync';
				}
			}

			return $hpos_enabled ? 'hpos_active' : 'legacy_posts';
		} catch ( \Exception $e ) {
			return 'unknown';
		}
	}
}
