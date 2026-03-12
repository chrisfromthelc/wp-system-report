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
		$this->admin_page           = new Admin_Page( $this->report_generator );
		$this->rest_controller      = new REST_Controller( $this->report_generator );
		$this->error_log_reader     = new Error_Log_Reader();
		$this->debug_toggle         = new Debug_Toggle();
		$this->error_log_controller = new Error_Log_Controller( $this->error_log_reader, $this->debug_toggle );
		$this->github_updater       = new GitHub_Updater( WP_SYSTEM_REPORT_FILE );

		$this->register_default_collectors();
		$this->register_hooks();
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
			new Collectors\Update_Health(),
		);

		foreach ( $collectors as $collector ) {
			$this->report_generator->register_collector( $collector );
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
		add_action( 'admin_enqueue_scripts', array( $this->admin_page, 'enqueue_assets' ) );

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
