<?php
/**
 * Admin page handler.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the admin menu page and asset enqueuing.
 */
class Admin_Page {

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'wp-system-report';

	/**
	 * Valid tab identifiers.
	 *
	 * @var array<int, string>
	 */
	private const VALID_TABS = array( 'report', 'error-log', 'fixes' );

	/**
	 * Report generator instance.
	 */
	private \SystemReport\Report_Generator $report_generator;

	/**
	 * Constructor.
	 *
	 * @param Report_Generator $report_generator Report generator instance.
	 */
	public function __construct( Report_Generator $report_generator ) {
		$this->report_generator = $report_generator;
	}

	/**
	 * Register the admin menu page under Tools.
	 */
	public function register_menu(): void {
		$capability = $this->get_capability();

		add_management_page(
			__( 'WP System Report', 'wp-system-report' ),
			__( 'WP System Report', 'wp-system-report' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets on the plugin page only.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ): void {
		if ( 'tools_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		$current_tab = $this->get_current_tab();

		wp_enqueue_style(
			'wp-system-report-admin',
			WP_SYSTEM_REPORT_URL . 'assets/css/wp-system-report-admin.css',
			array(),
			WP_SYSTEM_REPORT_VERSION
		);

		if ( 'report' === $current_tab ) {
			wp_enqueue_script(
				'wp-system-report-admin',
				WP_SYSTEM_REPORT_URL . 'assets/js/wp-system-report-admin.js',
				array(),
				WP_SYSTEM_REPORT_VERSION,
				true
			);

			wp_localize_script(
				'wp-system-report-admin',
				'systemReportAdmin',
				array(
					'restUrl'   => rest_url( 'wp-system-report/v1/report' ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
					'i18n'      => array(
						'copied'     => __( 'Copied!', 'wp-system-report' ),
						'copyFailed' => __( 'Copying to clipboard failed. Please press Ctrl/Cmd+C to copy.', 'wp-system-report' ),
						'generating' => __( 'Generating...', 'wp-system-report' ),
						'downloadAi' => __( 'Download for AI analysis', 'wp-system-report' ),
						'aiFailed'   => __( 'Failed to generate AI report. Please try again.', 'wp-system-report' ),
					),
				)
			);
		}

		if ( 'fixes' === $current_tab && Features::has_fixers() ) {
			wp_enqueue_script(
				'wp-system-report-fixes',
				WP_SYSTEM_REPORT_URL . 'assets/js/wp-system-report-fixes.js',
				array(),
				WP_SYSTEM_REPORT_VERSION,
				true
			);

			wp_localize_script(
				'wp-system-report-fixes',
				'systemReportFixes',
				array(
					'fixesUrl'  => rest_url( 'wp-system-report/v1/fixes' ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
					'i18n'      => array(
						'loading'        => __( 'Loading...', 'wp-system-report' ),
						'loadFailed'     => __( 'Failed to load fixers.', 'wp-system-report' ),
						'running'        => __( 'Running...', 'wp-system-report' ),
						'runFix'         => __( 'Run Fix', 'wp-system-report' ),
						'confirmTitle'   => __( 'Confirm Fix', 'wp-system-report' ),
						'confirmMessage' => __( 'This operation may modify your site. Are you sure you want to proceed?', 'wp-system-report' ),
						'confirmRun'     => __( 'Yes, run fix', 'wp-system-report' ),
						'cancel'         => __( 'Cancel', 'wp-system-report' ),
						'success'        => __( 'Success', 'wp-system-report' ),
						'failed'         => __( 'Failed', 'wp-system-report' ),
						'nothingToFix'   => __( 'No issues detected', 'wp-system-report' ),
						'executeFailed'  => __( 'Failed to execute fix.', 'wp-system-report' ),
						'noFixesFound'   => __( 'No fixers are available.', 'wp-system-report' ),
						'riskLow'        => __( 'Low Risk', 'wp-system-report' ),
						'riskMedium'     => __( 'Medium Risk', 'wp-system-report' ),
						'riskHigh'       => __( 'High Risk', 'wp-system-report' ),
						'issuesDetected' => __( 'Issues detected', 'wp-system-report' ),
						'noIssues'       => __( 'All clear', 'wp-system-report' ),
						'before'         => __( 'Before', 'wp-system-report' ),
						'after'          => __( 'After', 'wp-system-report' ),
						'resultDetails'  => __( 'Result Details', 'wp-system-report' ),
					),
				)
			);
		}

		if ( 'error-log' === $current_tab ) {
			wp_enqueue_script(
				'wp-system-report-error-log',
				WP_SYSTEM_REPORT_URL . 'assets/js/wp-system-report-error-log.js',
				array(),
				WP_SYSTEM_REPORT_VERSION,
				true
			);

			wp_localize_script(
				'wp-system-report-error-log',
				'systemReportErrorLog',
				array(
					'statusUrl' => rest_url( 'wp-system-report/v1/error-log/status' ),
					'logUrl'    => rest_url( 'wp-system-report/v1/error-log' ),
					'toggleUrl' => rest_url( 'wp-system-report/v1/error-log/toggle' ),
					'reportUrl' => rest_url( 'wp-system-report/v1/report' ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
					'i18n'      => array(
						'copied'        => __( 'Copied!', 'wp-system-report' ),
						'copyFailed'    => __( 'Copying to clipboard failed. Please press Ctrl/Cmd+C to copy.', 'wp-system-report' ),
						'loading'       => __( 'Loading...', 'wp-system-report' ),
						'loadLog'       => __( 'Load error log', 'wp-system-report' ),
						'refresh'       => __( 'Refresh', 'wp-system-report' ),
						'download'      => __( 'Download', 'wp-system-report' ),
						'copyClipboard' => __( 'Copy to clipboard', 'wp-system-report' ),
						'noLogFile'     => __( 'No error log file found.', 'wp-system-report' ),
						'logEmpty'      => __( 'Error log is empty.', 'wp-system-report' ),
						'toggleSuccess' => __( 'Debug settings updated. Changes will take effect on the next page load.', 'wp-system-report' ),
						'toggleFailed'  => __( 'Failed to update debug settings.', 'wp-system-report' ),
						'loadFailed'    => __( 'Failed to load error log.', 'wp-system-report' ),
						'enabled'       => __( 'Enabled', 'wp-system-report' ),
						'disabled'      => __( 'Disabled', 'wp-system-report' ),
						'notSet'        => __( 'Not set', 'wp-system-report' ),
						'readOnly'      => __( 'Read-only', 'wp-system-report' ),
					),
				)
			);
		}
	}

	/**
	 * Render the admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( $this->get_capability() ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-system-report' ) );
		}

		$sr_current_tab = $this->get_current_tab();
		$report         = $this->report_generator->generate();

		include WP_SYSTEM_REPORT_DIR . 'templates/admin-page.php';
	}

	/**
	 * Get the current active tab.
	 *
	 * @return string Tab identifier: 'report', 'error-log', or 'fixes'.
	 */
	private function get_current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation, no state change.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'report';

		if ( ! in_array( $tab, self::VALID_TABS, true ) ) {
			return 'report';
		}

		// The fixes tab requires the feature gate.
		if ( 'fixes' === $tab && ! Features::has_fixers() ) {
			return 'report';
		}

		return $tab;
	}

	/**
	 * Get the required capability to view the report.
	 *
	 * @return string WordPress capability.
	 */
	private function get_capability(): string {
		/**
		 * Filter the required capability for viewing the WP System Report.
		 *
		 * @param string $capability WordPress capability. Default 'manage_options'.
		 */
		$capability = apply_filters( 'wp_system_report_capability', 'manage_options' );

		if ( ! is_string( $capability ) || '' === $capability ) {
			return 'manage_options';
		}

		return $capability;
	}
}
