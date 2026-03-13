<?php
/**
 * Abilities API provider.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Registers WP System Report abilities with the WordPress Abilities API.
 *
 * Exposes the plugin's diagnostics, error log, and fixer capabilities as
 * structured abilities that can be discovered by AI agents via the MCP
 * Adapter plugin or consumed by any Abilities API client.
 *
 * Abilities are registered on the `wp_abilities_api_init` hook and the
 * category on `wp_abilities_api_categories_init`, as required by WordPress.
 *
 * @since 1.2.0
 */
class Abilities_Provider {

	/**
	 * Ability category slug.
	 *
	 * @var string
	 */
	private const CATEGORY = 'wp-system-report';

	/**
	 * Ability name prefix.
	 *
	 * @var string
	 */
	private const PREFIX = 'wp-system-report/';

	/**
	 * Report generator instance.
	 */
	private Report_Generator $report_generator;

	/**
	 * Error log reader instance.
	 */
	private Error_Log_Reader $error_log_reader;

	/**
	 * Fixer registry instance.
	 */
	private Fixer_Registry $fixer_registry;

	/**
	 * Constructor.
	 *
	 * @param Report_Generator $report_generator Report generator instance.
	 * @param Error_Log_Reader $error_log_reader Error log reader instance.
	 * @param Fixer_Registry   $fixer_registry   Fixer registry instance.
	 */
	public function __construct(
		Report_Generator $report_generator,
		Error_Log_Reader $error_log_reader,
		Fixer_Registry $fixer_registry,
	) {
		$this->report_generator = $report_generator;
		$this->error_log_reader = $error_log_reader;
		$this->fixer_registry   = $fixer_registry;
	}

	/**
	 * Register hooks for the Abilities API.
	 *
	 * Safe to call even when the Abilities API is not available;
	 * the callbacks simply won't fire if the hooks don't exist.
	 */
	public function register_hooks(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register the WP System Report ability category.
	 */
	public function register_category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'WP System Report', 'wp-system-report' ),
				'description' => __( 'Diagnostic, error log, and automated fix abilities for WordPress sites.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Register all WP System Report abilities.
	 */
	public function register_abilities(): void {
		$this->register_generate_report();
		$this->register_get_collector_data();
		$this->register_get_error_log();

		if ( Features::has_fixers() ) {
			$this->register_list_fixes();
			$this->register_run_fix();
		}
	}

	/**
	 * Register the generate-report ability.
	 *
	 * Generates the full diagnostic report from all registered collectors.
	 */
	private function register_generate_report(): void {
		wp_register_ability(
			self::PREFIX . 'generate-report',
			array(
				'label'               => __( 'Generate System Report', 'wp-system-report' ),
				'description'         => __( 'Generates a comprehensive WordPress system diagnostic report from all registered collectors. Returns structured data including WordPress environment, server info, database status, plugins, themes, security checks, and more.', 'wp-system-report' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( $this, 'execute_generate_report' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'section' => array(
							'type'        => 'string',
							'description' => __( 'Optional collector ID to retrieve a single section instead of the full report. Omit for the complete report.', 'wp-system-report' ),
						),
					),
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'Report data keyed by collector ID. Each section contains id, label, description, and fields array.', 'wp-system-report' ),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the get-collector-data ability.
	 *
	 * Lists available collector IDs for targeted data retrieval.
	 */
	private function register_get_collector_data(): void {
		wp_register_ability(
			self::PREFIX . 'get-collector-data',
			array(
				'label'               => __( 'Get Collector Data', 'wp-system-report' ),
				'description'         => __( 'Lists all available diagnostic collectors with their IDs, labels, and descriptions. Use the collector IDs with the generate-report ability to retrieve specific sections.', 'wp-system-report' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( $this, 'execute_get_collector_data' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'          => array(
								'type'        => 'string',
								'description' => __( 'Unique collector identifier.', 'wp-system-report' ),
							),
							'label'       => array(
								'type'        => 'string',
								'description' => __( 'Human-readable collector label.', 'wp-system-report' ),
							),
							'description' => array(
								'type'        => 'string',
								'description' => __( 'Collector description for AI context.', 'wp-system-report' ),
							),
						),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the get-error-log ability.
	 *
	 * Retrieves the most recent error log entries with redaction.
	 */
	private function register_get_error_log(): void {
		wp_register_ability(
			self::PREFIX . 'get-error-log',
			array(
				'label'               => __( 'Get Error Log', 'wp-system-report' ),
				'description'         => __( 'Retrieves the most recent PHP error log entries. Sensitive data (passwords, tokens, API keys) is automatically redacted. Returns log lines, file info, and debug constant status.', 'wp-system-report' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( $this, 'execute_get_error_log' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'lines' => array(
							'type'        => 'integer',
							'description' => __( 'Number of log lines to return. Default 100, maximum 10000.', 'wp-system-report' ),
							'minimum'     => 1,
							'maximum'     => 10000,
							'default'     => 100,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'lines' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Array of log lines, most recent last.', 'wp-system-report' ),
						),
						'count' => array(
							'type'        => 'integer',
							'description' => __( 'Number of lines returned.', 'wp-system-report' ),
						),
						'file'  => array(
							'type'        => 'object',
							'description' => __( 'Error log file metadata.', 'wp-system-report' ),
						),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the list-fixes ability.
	 *
	 * Lists all available automated fixers with their metadata.
	 */
	private function register_list_fixes(): void {
		wp_register_ability(
			self::PREFIX . 'list-fixes',
			array(
				'label'               => __( 'List Available Fixes', 'wp-system-report' ),
				'description'         => __( 'Lists all available automated fixers with their IDs, labels, descriptions, risk levels, categories, and whether each fix is currently applicable.', 'wp-system-report' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( $this, 'execute_list_fixes' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'          => array(
								'type'        => 'string',
								'description' => __( 'Unique fixer identifier.', 'wp-system-report' ),
							),
							'label'       => array(
								'type'        => 'string',
								'description' => __( 'Human-readable fixer label.', 'wp-system-report' ),
							),
							'description' => array(
								'type'        => 'string',
								'description' => __( 'What this fixer does.', 'wp-system-report' ),
							),
							'risk_level'  => array(
								'type'        => 'string',
								'enum'        => array( 'low', 'medium', 'high' ),
								'description' => __( 'Risk level of the fix operation.', 'wp-system-report' ),
							),
							'category'    => array(
								'type'        => 'string',
								'description' => __( 'Fixer category slug.', 'wp-system-report' ),
							),
							'can_fix'     => array(
								'type'        => 'boolean',
								'description' => __( 'Whether the fix is currently applicable.', 'wp-system-report' ),
							),
						),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the run-fix ability.
	 *
	 * Executes a specific fixer by ID with confirmation semantics.
	 */
	private function register_run_fix(): void {
		wp_register_ability(
			self::PREFIX . 'run-fix',
			array(
				'label'               => __( 'Run Fix', 'wp-system-report' ),
				'description'         => __( 'Executes an automated fix by its ID. Returns the result including success status, message, and before/after snapshots. Use list-fixes first to discover available fixers and their applicability.', 'wp-system-report' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( $this, 'execute_run_fix' ),
				'permission_callback' => array( $this, 'check_manage_options' ),
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'fix_id' ),
					'properties' => array(
						'fix_id'    => array(
							'type'        => 'string',
							'description' => __( 'The unique identifier of the fixer to execute.', 'wp-system-report' ),
						),
						'confirmed' => array(
							'type'        => 'boolean',
							'description' => __( 'Required for medium and high risk fixers. Set to true to confirm execution.', 'wp-system-report' ),
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether the fix succeeded.', 'wp-system-report' ),
						),
						'message' => array(
							'type'        => 'string',
							'description' => __( 'Human-readable result summary.', 'wp-system-report' ),
						),
						'before'  => array(
							'type'        => 'object',
							'description' => __( 'State snapshot before the fix.', 'wp-system-report' ),
						),
						'after'   => array(
							'type'        => 'object',
							'description' => __( 'State snapshot after the fix.', 'wp-system-report' ),
						),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'destructive' => true,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Permission callback: require manage_options capability.
	 *
	 * @return bool True if the current user can manage options.
	 */
	public function check_manage_options(): bool {
		/**
		 * Filter the required capability for Abilities API access.
		 *
		 * @param string $capability WordPress capability. Default 'manage_options'.
		 */
		$capability = apply_filters( 'wp_system_report_capability', 'manage_options' );

		if ( ! is_string( $capability ) || '' === $capability ) {
			$capability = 'manage_options';
		}

		return current_user_can( $capability );
	}

	/**
	 * Execute: generate the full system report or a single section.
	 *
	 * @param mixed $input Input data from the ability invocation.
	 * @return array|\WP_Error Report data or error.
	 */
	public function execute_generate_report( mixed $input = null ): array|\WP_Error {
		$input = is_array( $input ) ? $input : (array) $input;

		if ( ! empty( $input['section'] ) ) {
			$section_id = sanitize_key( $input['section'] );
			$section    = $this->report_generator->generate_section( $section_id );

			if ( null === $section ) {
				return new \WP_Error(
					'wp_system_report_invalid_section',
					sprintf(
						/* translators: %s: collector ID */
						__( 'Collector "%s" not found.', 'wp-system-report' ),
						$section_id
					)
				);
			}

			return $section;
		}

		return $this->report_generator->generate();
	}

	/**
	 * Execute: list all available collectors.
	 *
	 * @return array Array of collector metadata.
	 */
	public function execute_get_collector_data(): array {
		$collectors = $this->report_generator->get_collectors();
		$result     = array();

		foreach ( $collectors as $id => $collector ) {
			$result[] = array(
				'id'          => $id,
				'label'       => $collector->get_label(),
				'description' => $collector->get_description(),
			);
		}

		return $result;
	}

	/**
	 * Execute: retrieve error log entries.
	 *
	 * @param mixed $input Input data from the ability invocation.
	 * @return array|\WP_Error Log data or error.
	 */
	public function execute_get_error_log( mixed $input = null ): array|\WP_Error {
		$input = is_array( $input ) ? $input : (array) $input;
		$lines = 100;

		if ( isset( $input['lines'] ) ) {
			$lines = max( 1, min( 10000, absint( $input['lines'] ) ) );
		}

		$path = $this->error_log_reader->resolve_log_path();

		if ( null === $path ) {
			return new \WP_Error(
				'wp_system_report_no_log',
				__( 'No error log file found.', 'wp-system-report' )
			);
		}

		if ( ! $this->error_log_reader->is_path_safe( $path ) ) {
			return new \WP_Error(
				'wp_system_report_unsafe_path',
				__( 'The error log path is outside the allowed directory boundary.', 'wp-system-report' )
			);
		}

		$log_lines = $this->error_log_reader->read_last_lines( $path, $lines );

		return array(
			'lines' => $log_lines,
			'count' => count( $log_lines ),
			'file'  => $this->error_log_reader->get_file_info(),
		);
	}

	/**
	 * Execute: list all available fixers.
	 *
	 * @return array Array of fixer metadata.
	 */
	public function execute_list_fixes(): array {
		$fixers = $this->fixer_registry->get_all();
		$result = array();

		foreach ( $fixers as $fixer ) {
			$result[] = array(
				'id'          => $fixer->get_id(),
				'label'       => $fixer->get_label(),
				'description' => $fixer->get_description(),
				'risk_level'  => $fixer->get_risk_level()->value,
				'category'    => $fixer->get_category(),
				'can_fix'     => $fixer->can_fix(),
			);
		}

		return $result;
	}

	/**
	 * Execute: run a specific fixer by ID.
	 *
	 * @param mixed $input Input data containing the fix_id.
	 * @return array|\WP_Error Fix result or error.
	 */
	public function execute_run_fix( mixed $input = null ): array|\WP_Error {
		$input = is_array( $input ) ? $input : (array) $input;

		if ( empty( $input['fix_id'] ) ) {
			return new \WP_Error(
				'wp_system_report_missing_fix_id',
				__( 'The fix_id parameter is required.', 'wp-system-report' )
			);
		}

		$fix_id = sanitize_key( $input['fix_id'] );
		$fixer  = $this->fixer_registry->get( $fix_id );

		if ( null === $fixer ) {
			return new \WP_Error(
				'wp_system_report_fixer_not_found',
				sprintf(
					/* translators: %s: fixer ID */
					__( 'Fixer "%s" not found.', 'wp-system-report' ),
					$fix_id
				)
			);
		}

		if ( ! $fixer->can_fix() ) {
			return new \WP_Error(
				'wp_system_report_nothing_to_fix',
				sprintf(
					/* translators: %s: fixer label */
					__( '%s: no issues detected, nothing to fix.', 'wp-system-report' ),
					$fixer->get_label()
				)
			);
		}

		// Require explicit confirmation for medium and high risk fixers.
		$risk_level = $fixer->get_risk_level();

		if ( $risk_level->requires_confirmation() && empty( $input['confirmed'] ) ) {
			return new \WP_Error(
				'wp_system_report_confirmation_required',
				sprintf(
					/* translators: 1: fixer label, 2: risk level */
					__( '%1$s is a %2$s risk operation. Set confirmed=true to proceed.', 'wp-system-report' ),
					$fixer->get_label(),
					$risk_level->value
				)
			);
		}

		$result = $fixer->fix();

		return $result->to_array();
	}

	/**
	 * Check whether the MCP Adapter plugin is active.
	 *
	 * @return bool True if the MCP Adapter plugin is active.
	 */
	public static function is_mcp_adapter_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Check common file paths for the MCP Adapter plugin.
		$possible_slugs = array(
			'mcp-adapter/mcp-adapter.php',
			'wordpress-mcp-adapter/mcp-adapter.php',
		);

		foreach ( $possible_slugs as $slug ) {
			if ( is_plugin_active( $slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether the Abilities API is available.
	 *
	 * @return bool True if the Abilities API functions exist.
	 */
	public static function is_abilities_api_available(): bool {
		return function_exists( 'wp_register_ability' )
			&& function_exists( 'wp_register_ability_category' );
	}
}
