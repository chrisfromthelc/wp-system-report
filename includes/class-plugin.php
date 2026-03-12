<?php
/**
 * Main plugin orchestrator.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin class.
 *
 * Handles initialization, collector registration, and hooking into WordPress.
 */
class Plugin {

	/**
 * Singleton instance.
 */
	private static ?\SystemReport\Plugin $instance = null;

	/**
 * Report generator instance.
 */
	private \SystemReport\Report_Generator $report_generator;

	/**
 * Admin page instance.
 */
	private \SystemReport\Admin_Page $admin_page;

	/**
 * REST controller instance.
 */
	private \SystemReport\REST_Controller $rest_controller;

	/**
	 * Fixer registry instance.
	 */
	private \SystemReport\Fixer_Registry $fixer_registry;

	/**
	 * Error log reader instance.
	 */
	private \SystemReport\Error_Log_Reader $error_log_reader;

	/**
	 * Debug toggle instance.
	 */
	private \SystemReport\Debug_Toggle $debug_toggle;

	/**
	 * Error log controller instance.
	 */
	private \SystemReport\Error_Log_Controller $error_log_controller;

	/**
	 * Fixer controller instance.
	 */
	private \SystemReport\Fixer_Controller $fixer_controller;

	/**
	 * Health score calculator instance.
	 */
	private \SystemReport\Health_Score $health_score;

	/**
	 * AI context file generator instance.
	 */
	private \SystemReport\AI_Context_Generator $ai_context_generator;

	/**
	 * Health score calculator instance.
	 */
	private \SystemReport\Health_Score $health_score;

	/**
	 * Health score controller instance.
	 *
	 * Null when the health score feature flag is disabled.
	 */
	private ?\SystemReport\Health_Score_Controller $health_score_controller = null;

	/**
	 * Notification manager instance.
	 */
	private \SystemReport\Notification_Manager $notification_manager;

	/**
	 * Notification controller instance.
	 */
	private \SystemReport\Notification_Controller $notification_controller;

	/**
	 * Abilities API provider instance.
	 *
	 * Stored to prevent garbage collection; hooks are registered in the constructor.
	 */
	private ?\SystemReport\Abilities_Provider $abilities_provider = null;

	/**
	 * GitHub updater instance.
	 *
	 * Stored to prevent garbage collection; hooks are registered in the constructor.
	 *
	 * @phpstan-ignore property.onlyWritten
	 */
	private \SystemReport\GitHub_Updater $github_updater;

	/**
 * Get the singleton instance.
 */
	public static function get_instance(): \SystemReport\Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent cloning of the singleton.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization of the singleton.
	 *
	 * @throws \RuntimeException Always.
	 */
	public function __wakeup() {
		throw new \RuntimeException( 'Cannot unserialize singleton.' );
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->report_generator     = new Report_Generator();
		$this->fixer_registry       = new Fixer_Registry();
		$this->admin_page           = new Admin_Page( $this->report_generator );
		$this->rest_controller      = new REST_Controller( $this->report_generator );
		$this->error_log_reader     = new Error_Log_Reader();
		$this->debug_toggle         = new Debug_Toggle();
		$this->error_log_controller = new Error_Log_Controller( $this->error_log_reader, $this->debug_toggle );
		$this->fixer_controller     = new Fixer_Controller( $this->fixer_registry );
		$this->health_score         = new Health_Score( $this->report_generator );

		if ( Features::has_health_score() ) {
			$this->health_score_controller = new Health_Score_Controller( $this->health_score );
		}

		$this->ai_context_generator    = new AI_Context_Generator( $this->report_generator );
		$webhook_dispatcher            = new Webhook_Dispatcher();
		$this->notification_manager    = new Notification_Manager( $webhook_dispatcher );
		$this->notification_controller = new Notification_Controller( $webhook_dispatcher );
		$this->github_updater          = new GitHub_Updater( WP_SYSTEM_REPORT_FILE );

		$this->register_default_collectors();
		$this->register_default_fixers();
		$this->register_hooks();
		$this->ai_context_generator->register_hooks();
		$this->notification_manager->register_hooks();
		$this->maybe_register_abilities();
	}

	/**
	 * Register all default collectors.
	 */
	private function register_default_collectors(): void {
		$collectors = array(
			new Collectors\WordPress_Environment(),
			new Collectors\Server_Environment(),
			new Collectors\Database(),
			new Collectors\Post_Type_Counts(),
			new Collectors\Security(),
			new Collectors\Active_Plugins(),
			new Collectors\Inactive_Plugins(),
			new Collectors\Dropins_MU_Plugins(),
			new Collectors\Theme_Info(),
			new Collectors\WordPress_Constants(),
			new Collectors\Filesystem_Permissions(),
			new Collectors\Site_Health(),
			new Collectors\Cron_Health(),
			new Collectors\REST_API_Info(),
			new Collectors\Custom_Content_Types(),
			new Collectors\WordPress_Configuration(),
			new Collectors\Advanced_Diagnostics(),
			new Collectors\Email_Delivery(),
			new Collectors\Media_Uploads(),
			new Collectors\Performance(),
			new Collectors\Update_Health(),
			new Collectors\Block_Editor(),
			new Collectors\Network_Connectivity(),
		);

		foreach ( $collectors as $collector ) {
			$this->report_generator->register_collector( $collector );
		}
	}

	/**
	 * Register all default fixers.
	 */
	private function register_default_fixers(): void {
		$fixers = array(
			new Fixers\Autoload_Optimizer(),
			new Fixers\Database_Optimizer(),
			new Fixers\Security_Hardener(),
			new Fixers\Cron_Repair(),
		);

		foreach ( $fixers as $fixer ) {
			$this->fixer_registry->register( $fixer );
		}
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register_hooks(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this->admin_page, 'register_menu' ) );
		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->error_log_controller, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->fixer_controller, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->notification_controller, 'register_routes' ) );

		if ( null !== $this->health_score_controller ) {
			add_action( 'rest_api_init', array( $this->health_score_controller, 'register_routes' ) );
		}
		add_action( 'admin_enqueue_scripts', array( $this->admin_page, 'enqueue_assets' ) );

		// Apply security hardening measures stored from previous fixer runs.
		Fixers\Security_Hardener::apply_runtime_hardening();

		// MCP Adapter recommendation notice.
		if ( Features::has_abilities() ) {
			add_action( 'admin_notices', array( $this, 'maybe_show_mcp_adapter_notice' ) );
		}

		// Cache invalidation hooks.
		add_action( 'switch_theme', array( $this, 'clear_theme_cache' ) );
		add_action( 'activate_plugin', array( $this, 'clear_plugin_cache' ) );
		add_action( 'deactivate_plugin', array( $this, 'clear_plugin_cache' ) );
		add_action(
			'upgrader_process_complete',
			array( $this, 'clear_upgrade_cache' ),
			10,
			2
		);
	}

	/**
	 * Load the plugin text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-system-report',
			false,
			dirname( plugin_basename( WP_SYSTEM_REPORT_FILE ) ) . '/languages'
		);
	}

	/**
 * Get the report generator.
 */
	public function get_report_generator(): \SystemReport\Report_Generator {
		return $this->report_generator;
	}

	/**
	 * Get the fixer registry.
	 *
	 * @return Fixer_Registry The fixer registry instance.
	 */
	public function get_fixer_registry(): Fixer_Registry {
		return $this->fixer_registry;
	}

	/**
	 * Get the health score calculator.
	 *
	 * @return Health_Score The health score calculator instance.
	 */
	public function get_health_score(): Health_Score {
		return $this->health_score;
	}

	/**
	 * Conditionally initialize the Abilities API provider.
	 *
	 * Only creates the provider when the Abilities API is available
	 * (WordPress 6.9+) and the feature gate is enabled.
	 */
	private function maybe_register_abilities(): void {
		if ( ! Features::has_abilities() ) {
			return;
		}

		$this->abilities_provider = new Abilities_Provider(
			$this->report_generator,
			$this->error_log_reader,
			$this->fixer_registry
		);

		$this->abilities_provider->register_hooks();
	}

	/**
	 * Show an admin notice recommending the MCP Adapter plugin.
	 *
	 * Displayed only on the WP System Report admin page when the
	 * MCP Adapter is not active and the notice has not been dismissed.
	 */
	public function maybe_show_mcp_adapter_notice(): void {
		// Only show on our plugin page.
		$screen = get_current_screen();
		if ( null === $screen || 'tools_page_' . Admin_Page::MENU_SLUG !== $screen->id ) {
			return;
		}

		// Don't show if the MCP Adapter is already active.
		if ( Abilities_Provider::is_mcp_adapter_active() ) {
			return;
		}

		// Allow permanent dismissal via user meta.
		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, 'sr_dismiss_mcp_notice', true ) ) {
			return;
		}

		// Handle dismissal via query parameter with nonce verification.
		if ( isset( $_GET['sr_dismiss_mcp_notice'], $_GET['_wpnonce'] )
			&& '1' === $_GET['sr_dismiss_mcp_notice']
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sr_dismiss_mcp_notice' )
		) {
			update_user_meta( $user_id, 'sr_dismiss_mcp_notice', '1' );
			return;
		}

		include WP_SYSTEM_REPORT_DIR . 'templates/mcp-adapter-notice.php';
	}

	/**
	 * Clear theme-related caches.
	 */
	public function clear_theme_cache(): void {
		delete_transient( 'sr_theme_info' );
		delete_transient( 'sr_site_health' );
	}

	/**
	 * Clear plugin-related caches.
	 */
	public function clear_plugin_cache(): void {
		delete_transient( 'sr_active_plugins' );
		delete_transient( 'sr_inactive_plugins' );
		delete_transient( 'sr_dropins_mu_plugins' );
		delete_transient( 'sr_site_health' );
	}

	/**
	 * Clear caches after upgrades.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $extra    Extra data about the upgrade.
	 */
	public function clear_upgrade_cache( $upgrader, $extra ): void {
		if ( empty( $extra ) || empty( $extra['type'] ) ) {
			return;
		}

		if ( 'plugin' === $extra['type'] ) {
			$this->clear_plugin_cache();
			$this->clear_theme_cache();
		} elseif ( 'theme' === $extra['type'] ) {
			$this->clear_theme_cache();
		}
	}
}
