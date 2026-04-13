<?php
/**
 * Abilities API provider for the WordPress MCP Adapter.
 *
 * @package SystemReport
 */

namespace SystemReport;

use SystemReport\Formatters\AI_Formatter;

defined( 'ABSPATH' ) || exit;

/**
 * Registers system report abilities with the WordPress Abilities API.
 *
 * Exposes seven abilities for AI agents to query site health, read error logs,
 * toggle debug logging, and retrieve agent guidance via the MCP adapter.
 */
class Abilities_Provider {

	/**
	 * Report generator instance.
	 */
	private Report_Generator $report_generator;

	/**
	 * Error log reader instance.
	 */
	private Error_Log_Reader $error_log_reader;

	/**
	 * Debug toggle instance.
	 */
	private Debug_Toggle $debug_toggle;

	/**
	 * Constructor.
	 *
	 * @param Report_Generator $report_generator Report generator instance.
	 * @param Error_Log_Reader $error_log_reader Error log reader instance.
	 * @param Debug_Toggle     $debug_toggle     Debug toggle instance.
	 */
	public function __construct(
		Report_Generator $report_generator,
		Error_Log_Reader $error_log_reader,
		Debug_Toggle $debug_toggle
	) {
		$this->report_generator = $report_generator;
		$this->error_log_reader = $error_log_reader;
		$this->debug_toggle     = $debug_toggle;
	}

	/**
	 * Register WordPress hooks for the Abilities API.
	 *
	 * If the Abilities API is not available (WP < 6.9 or adapter not installed),
	 * these hooks simply never fire — no function_exists() guard needed.
	 */
	public function register_hooks(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register the ability category.
	 */
	public function register_category(): void {
		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'wp-system-report' ) ) {
			return;
		}

		wp_register_ability_category(
			'wp-system-report',
			array(
				'label'       => __( 'System Report', 'wp-system-report' ),
				'description' => __( 'Query site health, read error logs, and toggle debug logging.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Register all abilities.
	 *
	 * Bails early if the category failed to register or abilities
	 * are already registered (prevents duplicate registration notices).
	 */
	public function register_abilities(): void {
		if ( function_exists( 'wp_has_ability' ) && wp_has_ability( 'wp-system-report/get-issues' ) ) {
			return;
		}

		$this->register_get_agent_context();
		$this->register_get_issues();
		$this->register_get_report();
		$this->register_get_section();
		$this->register_get_error_log();
		$this->register_get_debug_status();
		$this->register_toggle_debug();
	}

	/**
	 * Register the get-agent-context ability.
	 */
	private function register_get_agent_context(): void {
		wp_register_ability(
			'wp-system-report/get-agent-context',
			array(
				'label'               => __( 'Get Agent Context', 'wp-system-report' ),
				'description'         => __( 'Returns environment-aware guidance, rules, and thresholds for AI agents. Call this FIRST before using other wp-system-report abilities to ensure recommendations are safe and environment-appropriate.', 'wp-system-report' ),
				'category'            => 'wp-system-report',
				'execute_callback'    => array( $this, 'handle_get_agent_context' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'environment'    => array(
							'type'        => 'object',
							'description' => __( 'Detected environment context for the site.', 'wp-system-report' ),
						),
						'rules'          => array(
							'type'        => 'object',
							'description' => __( 'Safety rules, never-recommend list, and environment overrides.', 'wp-system-report' ),
						),
						'thresholds'     => array(
							'type'        => 'object',
							'description' => __( 'Performance and configuration thresholds.', 'wp-system-report' ),
						),
						'php_lifecycle'  => array(
							'type'        => 'array',
							'description' => __( 'PHP version lifecycle data with EOL dates.', 'wp-system-report' ),
						),
						'ability_hints'  => array(
							'type'        => 'object',
							'description' => __( 'Contextual guidance hints keyed by ability ID.', 'wp-system-report' ),
						),
						'woocommerce'    => array(
							'type'        => 'object',
							'description' => __( 'WooCommerce-specific guidance. Only present when WooCommerce is active.', 'wp-system-report' ),
						),
						'plugin_version' => array( 'type' => 'string' ),
						'generated_at'   => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'          => array(
						'public' => true,
					),
				),
			)
		);
	}

	/**
	 * Register the get-issues ability.
	 */
	private function register_get_issues(): void {
		wp_register_ability(
			'wp-system-report/get-issues',
			array(
				'label'               => __( 'Get System Issues', 'wp-system-report' ),
				'description'         => __( 'Returns detected warnings and critical issues from the system report. Call get-agent-context first for environment-aware severity assessment.', 'wp-system-report' ),
				'category'            => 'wp-system-report',
				'execute_callback'    => array( $this, 'handle_get_issues' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'issues'         => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'severity'    => array(
										'type' => 'string',
										'enum' => array( 'critical', 'warning' ),
									),
									'title'       => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
								),
							),
						),
						'critical_count' => array( 'type' => 'integer' ),
						'warning_count'  => array( 'type' => 'integer' ),
						'site_url'       => array( 'type' => 'string' ),
						'generated_at'   => array( 'type' => 'string' ),
						'_environment'   => array(
							'type'        => 'object',
							'description' => __( 'Detected environment context for the site.', 'wp-system-report' ),
						),
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'          => array(
						'public' => true,
					),
				),
			)
		);
	}

	/**
	 * Register the get-report ability.
	 */
	private function register_get_report(): void {
		wp_register_ability(
			'wp-system-report/get-report',
			array(
				'label'               => __( 'Get Full System Report', 'wp-system-report' ),
				'description'         => __( 'Returns the complete system report. Call get-agent-context first for environment-aware analysis. Use format=markdown for AI-optimized output or format=json for structured data.', 'wp-system-report' ),
				'category'            => 'wp-system-report',
				'execute_callback'    => array( $this, 'handle_get_report' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'format' => array(
							'type'        => 'string',
							'enum'        => array( 'json', 'markdown' ),
							'default'     => 'markdown',
							'description' => __( 'Output format: markdown (AI-optimized) or json (structured data).', 'wp-system-report' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'report'       => array(
							'description' => __( 'Full system report. Markdown string when format=markdown, structured object when format=json.', 'wp-system-report' ),
							'oneOf'       => array(
								array( 'type' => 'string' ),
								array( 'type' => 'object' ),
							),
						),
						'format'       => array( 'type' => 'string' ),
						'generated_at' => array( 'type' => 'string' ),
						'_environment' => array(
							'type'        => 'object',
							'description' => __( 'Detected environment context for the site.', 'wp-system-report' ),
						),
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'          => array(
						'public' => true,
					),
				),
			)
		);
	}

	/**
	 * Register the get-section ability.
	 */
	private function register_get_section(): void {
		wp_register_ability(
			'wp-system-report/get-section',
			array(
				'label'               => __( 'Get Report Section', 'wp-system-report' ),
				'description'         => __( 'Returns a single section of the system report by collector ID (e.g. "database", "security", "active_plugins"). Call get-agent-context first for environment-aware analysis.', 'wp-system-report' ),
				'category'            => 'wp-system-report',
				'execute_callback'    => array( $this, 'handle_get_section' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'section' => array(
							'type'        => 'string',
							'description' => __( 'Collector ID, e.g. "wordpress_environment", "database", "security", "active_plugins".', 'wp-system-report' ),
						),
					),
					'required'   => array( 'section' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'section'            => array(
							'type'       => 'object',
							'properties' => array(
								'id'          => array( 'type' => 'string' ),
								'label'       => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'fields'      => array( 'type' => 'array' ),
							),
						),
						'error'              => array(
							'type'        => 'string',
							'description' => __( 'Error message when the requested section is not found.', 'wp-system-report' ),
						),
						'available_sections' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'List of valid section IDs. Returned when the requested section is not found.', 'wp-system-report' ),
						),
						'_environment'       => array(
							'type'        => 'object',
							'description' => __( 'Detected environment context for the site.', 'wp-system-report' ),
						),
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'          => array(
						'public' => true,
					),
				),
			)
		);
	}

	/**
	 * Register the get-error-log ability.
	 */
	private function register_get_error_log(): void {
		wp_register_ability(
			'wp-system-report/get-error-log',
			array(
				'label'               => __( 'Get Error Log', 'wp-system-report' ),
				'description'         => __( 'Reads the last N lines of the PHP error log. Sensitive data is automatically redacted. Call get-agent-context first for environment-aware analysis.', 'wp-system-report' ),
				'category'            => 'wp-system-report',
				'execute_callback'    => array( $this, 'handle_get_error_log' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'lines' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 10000,
							'default'     => 100,
							'description' => __( 'Number of lines to return from the end of the log.', 'wp-system-report' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'lines'        => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'count'        => array( 'type' => 'integer' ),
						'file'         => array( 'type' => 'object' ),
						'debug_status' => array( 'type' => 'object' ),
						'error'        => array(
							'type'        => 'string',
							'description' => __( 'Error message when the log file cannot be read.', 'wp-system-report' ),
						),
						'_environment' => array(
							'type'        => 'object',
							'description' => __( 'Detected environment context for the site.', 'wp-system-report' ),
						),
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'          => array(
						'public' => true,
					),
				),
			)
		);
	}

	/**
	 * Register the get-debug-status ability.
	 */
	private function register_get_debug_status(): void {
		wp_register_ability(
			'wp-system-report/get-debug-status',
			array(
				'label'               => __( 'Get Debug Status', 'wp-system-report' ),
				'description'         => __( 'Returns the current WP_DEBUG, WP_DEBUG_LOG, and WP_DEBUG_DISPLAY state, and whether debug toggling is possible. Call get-agent-context first for environment-aware analysis.', 'wp-system-report' ),
				'category'            => 'wp-system-report',
				'execute_callback'    => array( $this, 'handle_get_debug_status' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'wp_debug'         => array( 'type' => array( 'boolean', 'null' ) ),
						'wp_debug_log'     => array(
							'type'        => array( 'boolean', 'string', 'null' ),
							'description' => __( 'True/false for enabled/disabled, or a string file path.', 'wp-system-report' ),
						),
						'wp_debug_display' => array( 'type' => array( 'boolean', 'null' ) ),
						'can_modify'       => array( 'type' => 'boolean' ),
						'log_file'         => array( 'type' => 'object' ),
						'_environment'     => array(
							'type'        => 'object',
							'description' => __( 'Detected environment context for the site.', 'wp-system-report' ),
						),
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'          => array(
						'public' => true,
					),
				),
			)
		);
	}

	/**
	 * Register the toggle-debug ability.
	 */
	private function register_toggle_debug(): void {
		wp_register_ability(
			'wp-system-report/toggle-debug',
			array(
				'label'               => __( 'Toggle Debug Logging', 'wp-system-report' ),
				'description'         => __( 'Enables or disables WP_DEBUG and WP_DEBUG_LOG by modifying wp-config.php. Call get-agent-context first to check environment. Always confirm with user before calling on production.', 'wp-system-report' ),
				'category'            => 'wp-system-report',
				'execute_callback'    => array( $this, 'handle_toggle_debug' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'enable' => array(
							'type'        => 'boolean',
							'description' => __( 'True to enable WP_DEBUG + WP_DEBUG_LOG, false to disable.', 'wp-system-report' ),
						),
					),
					'required'   => array( 'enable' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'enabled'      => array( 'type' => 'boolean' ),
						'state'        => array( 'type' => 'object' ),
						'error'        => array( 'type' => 'string' ),
						'_environment' => array(
							'type'        => 'object',
							'description' => __( 'Detected environment context for the site.', 'wp-system-report' ),
						),
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'          => array(
						'public' => true,
					),
				),
			)
		);
	}

	/**
	 * Handle the get-issues ability.
	 *
	 * @param array $input Ability input (unused).
	 * @return array Issues data.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by ability callback signature.
	public function handle_get_issues( array $input ): array {
		$report_data = $this->report_generator->generate();
		$detector    = new Issue_Detector();
		$issues      = $detector->detect( $report_data );

		/** This filter is documented in includes/formatters/class-ai-formatter.php */
		$issues = apply_filters( 'wp_system_report_ai_issues', $issues, $report_data );

		$critical_count = 0;
		$warning_count  = 0;

		foreach ( $issues as $issue ) {
			if ( 'critical' === $issue['severity'] ) {
				++$critical_count;
			} elseif ( 'warning' === $issue['severity'] ) {
				++$warning_count;
			}
		}

		return $this->enrich_response(
			array(
				'issues'         => $issues,
				'critical_count' => $critical_count,
				'warning_count'  => $warning_count,
				'site_url'       => get_option( 'home' ),
				'generated_at'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
			)
		);
	}

	/**
	 * Handle the get-report ability.
	 *
	 * @param array $input Ability input with optional 'format' key.
	 * @return array Report data.
	 */
	public function handle_get_report( array $input ): array {
		$format = isset( $input['format'] ) && 'json' === $input['format'] ? 'json' : 'markdown';

		$report_data = $this->report_generator->generate();

		if ( 'json' === $format ) {
			$report = $report_data;
		} else {
			$formatter = new AI_Formatter();
			$report    = $formatter->format( $report_data );
		}

		return $this->enrich_response(
			array(
				'report'       => $report,
				'format'       => $format,
				'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			)
		);
	}

	/**
	 * Handle the get-section ability.
	 *
	 * @param array $input Ability input with 'section' key.
	 * @return array Section data or available sections on failure.
	 */
	public function handle_get_section( array $input ): array {
		$section_id = isset( $input['section'] ) ? $input['section'] : '';
		$section    = $this->report_generator->generate_section( $section_id );

		if ( null === $section ) {
			$collectors         = $this->report_generator->get_collectors();
			$available_sections = array_keys( $collectors );

			return $this->enrich_response(
				array(
					'error'              => sprintf(
						/* translators: %s: requested section ID */
						__( 'Section "%s" not found.', 'wp-system-report' ),
						$section_id
					),
					'available_sections' => $available_sections,
				)
			);
		}

		return $this->enrich_response(
			array(
				'section' => $section,
			)
		);
	}

	/**
	 * Handle the get-error-log ability.
	 *
	 * @param array $input Ability input with optional 'lines' key.
	 * @return array Error log data.
	 */
	public function handle_get_error_log( array $input ): array {
		$num_lines = isset( $input['lines'] ) ? absint( $input['lines'] ) : 100;
		$num_lines = max( 1, min( 10000, $num_lines ) );

		$path = $this->error_log_reader->resolve_log_path();

		if ( null === $path ) {
			return $this->enrich_response(
				array(
					'error'        => __( 'No error log file found.', 'wp-system-report' ),
					'lines'        => array(),
					'count'        => 0,
					'file'         => $this->error_log_reader->get_file_info(),
					'debug_status' => $this->debug_toggle->get_state(),
				)
			);
		}

		if ( ! $this->error_log_reader->is_path_safe( $path ) ) {
			return $this->enrich_response(
				array(
					'error'        => __( 'The error log path is outside the allowed directory boundary.', 'wp-system-report' ),
					'lines'        => array(),
					'count'        => 0,
					'file'         => $this->error_log_reader->get_file_info(),
					'debug_status' => $this->debug_toggle->get_state(),
				)
			);
		}

		$log_lines = $this->error_log_reader->read_last_lines( $path, $num_lines );

		return $this->enrich_response(
			array(
				'lines'        => $log_lines,
				'count'        => count( $log_lines ),
				'file'         => $this->error_log_reader->get_file_info(),
				'debug_status' => $this->debug_toggle->get_state(),
			)
		);
	}

	/**
	 * Handle the get-debug-status ability.
	 *
	 * @param array $input Ability input (unused).
	 * @return array Debug status data.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by ability callback signature.
	public function handle_get_debug_status( array $input ): array {
		$state = $this->debug_toggle->get_state();

		return $this->enrich_response(
			array(
				'wp_debug'         => $state['wp_debug'],
				'wp_debug_log'     => $state['wp_debug_log'],
				'wp_debug_display' => $state['wp_debug_display'],
				'can_modify'       => $state['can_modify'],
				'log_file'         => $this->error_log_reader->get_file_info(),
			)
		);
	}

	/**
	 * Handle the toggle-debug ability.
	 *
	 * @param array $input Ability input with 'enable' key.
	 * @return array Toggle result.
	 */
	public function handle_toggle_debug( array $input ): array {
		$enable = ! empty( $input['enable'] );

		if ( ! $this->debug_toggle->can_modify() ) {
			return $this->enrich_response(
				array(
					'success' => false,
					'enabled' => $enable,
					'state'   => $this->debug_toggle->get_state(),
					'error'   => __( 'wp-config.php is not writable or file modifications are disabled.', 'wp-system-report' ),
				)
			);
		}

		$result = $enable ? $this->debug_toggle->enable_debug() : $this->debug_toggle->disable_debug();

		if ( true !== $result ) {
			return $this->enrich_response(
				array(
					'success' => false,
					'enabled' => $enable,
					'state'   => $this->debug_toggle->get_state(),
					'error'   => $result,
				)
			);
		}

		return $this->enrich_response(
			array(
				'success' => true,
				'enabled' => $enable,
				'state'   => $this->debug_toggle->get_state(),
			)
		);
	}

	/**
	 * Handle the get-agent-context ability.
	 *
	 * @param array $input Ability input (unused).
	 * @return array Full agent guidance context.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by ability callback signature.
	public function handle_get_agent_context( array $input ): array {
		return Agent_Guidance::get_full_context();
	}

	/**
	 * Enrich an ability response with environment context.
	 *
	 * Appends the _environment key to every response so agents always
	 * have site context available, even without calling get-agent-context.
	 *
	 * @param array $response The ability handler's response array.
	 * @return array Enriched response.
	 */
	private function enrich_response( array $response ): array {
		$response['_environment'] = Agent_Guidance::get_environment_context();
		return $response;
	}

	/**
	 * Check if the current user has manage_options capability.
	 *
	 * @return bool True if the user can manage options.
	 */
	public function check_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}
}
