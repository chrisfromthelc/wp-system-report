<?php
/**
 * Agent Guidance tests.
 *
 * @package SystemReport
 */

use SystemReport\Agent_Guidance;

/**
 * Test the Agent_Guidance static helper.
 */
class AgentGuidanceTest extends WP_UnitTestCase {

	// ---------------------------------------------------------------
	// Environment context
	// ---------------------------------------------------------------

	/**
	 * Test get_environment_context returns all required keys.
	 */
	public function test_get_environment_context_returns_required_keys(): void {
		$context = Agent_Guidance::get_environment_context();

		$required = array(
			'environment_type',
			'is_production',
			'is_local',
			'is_development',
			'is_staging',
			'hosting_provider',
			'is_multisite',
			'is_block_theme',
			'has_woocommerce',
			'has_object_cache',
			'active_plugin_count',
			'php_version',
			'wp_version',
		);

		foreach ( $required as $key ) {
			$this->assertArrayHasKey( $key, $context, "Missing key: {$key}" );
		}
	}

	/**
	 * Test get_environment_context returns correct types.
	 */
	public function test_get_environment_context_types(): void {
		$context = Agent_Guidance::get_environment_context();

		$this->assertIsString( $context['environment_type'] );
		$this->assertIsBool( $context['is_production'] );
		$this->assertIsBool( $context['is_local'] );
		$this->assertIsBool( $context['is_development'] );
		$this->assertIsBool( $context['is_staging'] );
		$this->assertIsString( $context['hosting_provider'] );
		$this->assertIsBool( $context['is_multisite'] );
		$this->assertIsBool( $context['is_block_theme'] );
		$this->assertIsBool( $context['has_woocommerce'] );
		$this->assertIsBool( $context['has_object_cache'] );
		$this->assertIsInt( $context['active_plugin_count'] );
		$this->assertIsString( $context['php_version'] );
		$this->assertIsString( $context['wp_version'] );
	}

	/**
	 * Test PHP version matches runtime.
	 */
	public function test_get_environment_context_php_version_matches_runtime(): void {
		$context = Agent_Guidance::get_environment_context();

		$this->assertSame( PHP_VERSION, $context['php_version'] );
	}

	/**
	 * Test WordPress version matches runtime.
	 */
	public function test_get_environment_context_wp_version_matches_runtime(): void {
		$context = Agent_Guidance::get_environment_context();

		$this->assertSame( get_bloginfo( 'version' ), $context['wp_version'] );
	}

	/**
	 * Test is_multisite matches WordPress.
	 */
	public function test_get_environment_context_is_multisite_matches_wp(): void {
		$context = Agent_Guidance::get_environment_context();

		$this->assertSame( is_multisite(), $context['is_multisite'] );
	}

	// ---------------------------------------------------------------
	// Agent rules
	// ---------------------------------------------------------------

	/**
	 * Test get_agent_rules returns all required groups.
	 */
	public function test_get_agent_rules_returns_required_groups(): void {
		$rules = Agent_Guidance::get_agent_rules();

		$this->assertArrayHasKey( 'safety_rules', $rules );
		$this->assertArrayHasKey( 'never_recommend', $rules );
		$this->assertArrayHasKey( 'environment_overrides', $rules );
	}

	/**
	 * Test never_recommend is non-empty.
	 */
	public function test_get_agent_rules_never_recommend_is_non_empty(): void {
		$rules = Agent_Guidance::get_agent_rules();

		$this->assertNotEmpty( $rules['never_recommend'] );
	}

	/**
	 * Test safety_rules is non-empty.
	 */
	public function test_get_agent_rules_safety_rules_is_non_empty(): void {
		$rules = Agent_Guidance::get_agent_rules();

		$this->assertNotEmpty( $rules['safety_rules'] );
	}

	/**
	 * Test all rule values are strings.
	 */
	public function test_get_agent_rules_all_values_are_strings(): void {
		$rules = Agent_Guidance::get_agent_rules();

		foreach ( $rules as $group_name => $group ) {
			$this->assertIsArray( $group, "{$group_name} should be an array" );
			foreach ( $group as $index => $rule ) {
				$this->assertIsString( $rule, "{$group_name}[{$index}] should be a string" );
			}
		}
	}

	// ---------------------------------------------------------------
	// Thresholds
	// ---------------------------------------------------------------

	/**
	 * Test get_thresholds returns required keys.
	 */
	public function test_get_thresholds_returns_required_keys(): void {
		$thresholds = Agent_Guidance::get_thresholds();

		$required = array(
			'php_version_minimum',
			'php_version_recommended',
			'php_memory_limit_minimum',
			'wp_memory_limit_minimum',
			'autoload_size_warning_kb',
			'autoload_size_critical_kb',
			'max_execution_time_minimum',
			'database_size_warning_mb',
		);

		foreach ( $required as $key ) {
			$this->assertArrayHasKey( $key, $thresholds, "Missing threshold: {$key}" );
		}
	}

	/**
	 * Test threshold values are numeric (strings for versions, ints for sizes).
	 */
	public function test_get_thresholds_values_are_numeric(): void {
		$thresholds = Agent_Guidance::get_thresholds();

		// Numeric thresholds should be integers.
		$this->assertIsInt( $thresholds['autoload_size_warning_kb'] );
		$this->assertIsInt( $thresholds['autoload_size_critical_kb'] );
		$this->assertIsInt( $thresholds['max_execution_time_minimum'] );
		$this->assertIsInt( $thresholds['database_size_warning_mb'] );

		// Version thresholds should be strings.
		$this->assertIsString( $thresholds['php_version_minimum'] );
		$this->assertIsString( $thresholds['php_version_recommended'] );
	}

	/**
	 * Test autoload warning is less than critical.
	 */
	public function test_get_thresholds_autoload_warning_less_than_critical(): void {
		$thresholds = Agent_Guidance::get_thresholds();

		$this->assertLessThan(
			$thresholds['autoload_size_critical_kb'],
			$thresholds['autoload_size_warning_kb']
		);
	}

	// ---------------------------------------------------------------
	// PHP lifecycle
	// ---------------------------------------------------------------

	/**
	 * Test get_php_lifecycle returns non-empty array.
	 */
	public function test_get_php_lifecycle_returns_non_empty_array(): void {
		$lifecycle = Agent_Guidance::get_php_lifecycle();

		$this->assertNotEmpty( $lifecycle );
		$this->assertIsArray( $lifecycle );
	}

	/**
	 * Test each lifecycle entry has required keys.
	 */
	public function test_get_php_lifecycle_each_entry_has_required_keys(): void {
		$lifecycle = Agent_Guidance::get_php_lifecycle();

		foreach ( $lifecycle as $index => $entry ) {
			$this->assertArrayHasKey( 'version', $entry, "Entry {$index} missing version" );
			$this->assertArrayHasKey( 'status', $entry, "Entry {$index} missing status" );
			$this->assertArrayHasKey( 'classification', $entry, "Entry {$index} missing classification" );
			$this->assertArrayHasKey( 'active_support_end', $entry, "Entry {$index} missing active_support_end" );
			$this->assertArrayHasKey( 'security_end', $entry, "Entry {$index} missing security_end" );
		}
	}

	/**
	 * Test lifecycle includes an entry matching current PHP major.minor.
	 */
	public function test_get_php_lifecycle_includes_current_runtime_version(): void {
		$lifecycle   = Agent_Guidance::get_php_lifecycle();
		$current     = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
		$versions    = array_column( $lifecycle, 'version' );

		$this->assertContains( $current, $versions, "Lifecycle should include PHP {$current}" );
	}

	// ---------------------------------------------------------------
	// Ability hints
	// ---------------------------------------------------------------

	/**
	 * Test get_ability_hints returns array for all 7 abilities.
	 */
	public function test_get_ability_hints_returns_array_for_each_ability(): void {
		$ability_ids = array(
			'get-issues',
			'get-report',
			'get-section',
			'get-error-log',
			'get-debug-status',
			'toggle-debug',
			'get-agent-context',
		);

		foreach ( $ability_ids as $id ) {
			$hints = Agent_Guidance::get_ability_hints( $id );
			$this->assertNotEmpty( $hints, "Hints for {$id} should not be empty" );
			$this->assertIsArray( $hints );
		}
	}

	/**
	 * Test get_ability_hints returns empty for unknown ability.
	 */
	public function test_get_ability_hints_returns_empty_for_unknown(): void {
		$hints = Agent_Guidance::get_ability_hints( 'unknown-ability' );

		$this->assertEmpty( $hints );
		$this->assertIsArray( $hints );
	}

	/**
	 * Test all hint values are strings.
	 */
	public function test_get_ability_hints_values_are_strings(): void {
		$hints = Agent_Guidance::get_ability_hints( 'get-issues' );

		foreach ( $hints as $index => $hint ) {
			$this->assertIsString( $hint, "Hint [{$index}] should be a string" );
		}
	}

	// ---------------------------------------------------------------
	// WooCommerce context
	// ---------------------------------------------------------------

	/**
	 * Test get_woocommerce_context returns expected structure.
	 */
	public function test_get_woocommerce_context_returns_array(): void {
		$context = Agent_Guidance::get_woocommerce_context();

		$this->assertIsArray( $context );
		$this->assertArrayHasKey( 'is_active', $context );
		$this->assertArrayHasKey( 'hpos_status', $context );
		$this->assertArrayHasKey( 'memory_minimum', $context );
		$this->assertArrayHasKey( 'memory_recommended', $context );
		$this->assertArrayHasKey( 'caching_exclusions', $context );
	}

	/**
	 * Test get_woocommerce_context has threshold sub-arrays.
	 */
	public function test_get_woocommerce_context_has_thresholds(): void {
		$context = Agent_Guidance::get_woocommerce_context();

		$this->assertArrayHasKey( 'action_scheduler_thresholds', $context );
		$this->assertArrayHasKey( 'session_table_thresholds', $context );
		$this->assertArrayHasKey( 'pending_warning', $context['action_scheduler_thresholds'] );
		$this->assertArrayHasKey( 'size_warning_mb', $context['session_table_thresholds'] );
	}

	// ---------------------------------------------------------------
	// Full context
	// ---------------------------------------------------------------

	/**
	 * Test get_full_context returns all required keys.
	 */
	public function test_get_full_context_returns_required_keys(): void {
		$context = Agent_Guidance::get_full_context();

		$this->assertArrayHasKey( 'environment', $context );
		$this->assertArrayHasKey( 'rules', $context );
		$this->assertArrayHasKey( 'thresholds', $context );
		$this->assertArrayHasKey( 'php_lifecycle', $context );
		$this->assertArrayHasKey( 'ability_hints', $context );
		$this->assertArrayHasKey( 'plugin_version', $context );
		$this->assertArrayHasKey( 'generated_at', $context );
	}

	/**
	 * Test get_full_context has ISO 8601 generated_at.
	 */
	public function test_get_full_context_has_generated_at(): void {
		$context = Agent_Guidance::get_full_context();

		$this->assertMatchesRegularExpression(
			'/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/',
			$context['generated_at']
		);
	}

	/**
	 * Test ability_hints covers all 7 abilities.
	 */
	public function test_get_full_context_ability_hints_covers_all_abilities(): void {
		$context = Agent_Guidance::get_full_context();
		$hints   = $context['ability_hints'];

		$expected = array(
			'get-issues',
			'get-report',
			'get-section',
			'get-error-log',
			'get-debug-status',
			'toggle-debug',
			'get-agent-context',
		);

		foreach ( $expected as $id ) {
			$this->assertArrayHasKey( $id, $hints, "ability_hints should have key {$id}" );
		}
	}

	/**
	 * Test get_full_context includes woocommerce key when WooCommerce active.
	 */
	public function test_get_full_context_includes_woocommerce_when_active(): void {
		$context = Agent_Guidance::get_full_context();

		if ( class_exists( 'WooCommerce' ) ) {
			$this->assertArrayHasKey( 'woocommerce', $context );
		} else {
			$this->assertArrayNotHasKey( 'woocommerce', $context );
		}
	}
}
