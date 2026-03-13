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
	 *
	 * @var \SystemReport\Report_Generator
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

		if ( Features::has_interactivity() ) {
			$this->enqueue_interactivity_assets( $current_tab );
		} else {
			$this->enqueue_vanilla_assets( $current_tab );
		}
	}

	/**
	 * Render the admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( $this->get_capability() ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-system-report' ) );
		}

		// Flush all collector caches so the admin always sees fresh data.
		$this->report_generator->flush_all_caches();

		$sr_current_tab = $this->get_current_tab();

		// Only generate the full report when on the report tab to avoid
		// running all 23 collectors on every admin page load.
		$report = ( 'report' === $sr_current_tab )
			? $this->report_generator->generate()
			: array();

		if ( Features::has_interactivity() ) {
			$this->init_interactivity_state( $sr_current_tab );
		}

		include WP_SYSTEM_REPORT_DIR . 'templates/admin-page.php';
	}

	/**
	 * Enqueue Interactivity API script modules for the current tab.
	 *
	 * @param string $current_tab Active tab slug.
	 */
	private function enqueue_interactivity_assets( string $current_tab ): void {
		// Script Modules API (WP 6.5+) uses array-of-arrays format, not flat strings.
		// See https://developer.wordpress.org/reference/functions/wp_enqueue_script_module/.
		$iapi_dep = array( array( 'id' => '@wordpress/interactivity' ) );

		if ( 'report' === $current_tab ) {
			wp_enqueue_script_module(
				'@wp-system-report/store-report',
				WP_SYSTEM_REPORT_URL . 'assets/js/modules/store-report.js',
				$iapi_dep,
				WP_SYSTEM_REPORT_VERSION
			);
		}

		if ( 'error-log' === $current_tab ) {
			wp_enqueue_script_module(
				'@wp-system-report/store-error-log',
				WP_SYSTEM_REPORT_URL . 'assets/js/modules/store-error-log.js',
				$iapi_dep,
				WP_SYSTEM_REPORT_VERSION
			);
		}

		if ( 'fixes' === $current_tab && Features::has_fixers() ) {
			wp_enqueue_script_module(
				'@wp-system-report/store-fixes',
				WP_SYSTEM_REPORT_URL . 'assets/js/modules/store-fixes.js',
				$iapi_dep,
				WP_SYSTEM_REPORT_VERSION
			);
		}

		// WP 6.7+ automatically prints Interactivity API state in admin_footer,
		// so the manual hook is only needed for WP 6.5–6.6.x.
		// print_client_interactivity_data() was deprecated in WP 6.7.0 (#96).
		if ( function_exists( 'wp_interactivity' )
			&& version_compare( $GLOBALS['wp_version'], '6.7', '<' )
		) {
			add_action( 'admin_footer', array( wp_interactivity(), 'print_client_interactivity_data' ), 8 );
		}
	}

	/**
	 * Enqueue vanilla JavaScript for the current tab (fallback for WP < 6.5).
	 *
	 * @param string $current_tab Active tab slug.
	 */
	private function enqueue_vanilla_assets( string $current_tab ): void {
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
	 * Initialize Interactivity API state for the current tab.
	 *
	 * Merges tab-specific configuration, i18n, and default state
	 * into the 'wp-system-report' namespace via wp_interactivity_state().
	 *
	 * @param string $tab Active tab slug.
	 */
	private function init_interactivity_state( string $tab ): void {
		$rest_nonce = wp_create_nonce( 'wp_rest' );

		$state = array(
			'config' => array(
				'restNonce' => $rest_nonce,
			),
			'i18n'   => array(
				'copied'     => __( 'Copied!', 'wp-system-report' ),
				'copyFailed' => __( 'Copying to clipboard failed. Please press Ctrl/Cmd+C to copy.', 'wp-system-report' ),
				'loading'    => __( 'Loading...', 'wp-system-report' ),
			),
		);

		if ( 'report' === $tab ) {
			$state['config']['restUrl'] = rest_url( 'wp-system-report/v1/report' );
			$state['i18n']              = array_merge(
				$state['i18n'],
				array(
					'generating' => __( 'Generating...', 'wp-system-report' ),
					'downloadAi' => __( 'Download for AI analysis', 'wp-system-report' ),
					'aiFailed'   => __( 'Failed to generate AI report. Please try again.', 'wp-system-report' ),
				)
			);
			$state['reportGenerated']   = false;
			$state['aiGenerating']      = false;
			$state['aiError']           = '';
			$state['copyError']         = false;
			$state['reportText']        = '';
		}

		if ( 'error-log' === $tab ) {
			$state['config']['statusUrl'] = rest_url( 'wp-system-report/v1/error-log/status' );
			$state['config']['logUrl']    = rest_url( 'wp-system-report/v1/error-log' );
			$state['config']['toggleUrl'] = rest_url( 'wp-system-report/v1/error-log/toggle' );
			$state['config']['reportUrl'] = rest_url( 'wp-system-report/v1/report' );
			$state['i18n']                = array_merge(
				$state['i18n'],
				array(
					'loadLog'         => __( 'Load error log', 'wp-system-report' ),
					'toggleSuccess'   => __( 'Debug settings updated. Changes will take effect on the next page load.', 'wp-system-report' ),
					'toggleFailed'    => __( 'Failed to update debug settings.', 'wp-system-report' ),
					'loadFailed'      => __( 'Failed to load error log.', 'wp-system-report' ),
					'enabled'         => __( 'Enabled', 'wp-system-report' ),
					'disabled'        => __( 'Disabled', 'wp-system-report' ),
					'notSet'          => __( 'Not set', 'wp-system-report' ),
					'reportHeading'   => __( 'WP SYSTEM REPORT', 'wp-system-report' ),
					'errorLogHeading' => __( 'ERROR LOG', 'wp-system-report' ),
				)
			);
			$state['errorLog']            = array(
				'statusLoaded'   => false,
				'logLoaded'      => false,
				'isLoading'      => false,
				'isToggling'     => false,
				'canModify'      => false,
				'wpDebug'        => null,
				'wpDebugLog'     => null,
				'wpDebugDisplay' => null,
				'fileExists'     => false,
				'filePath'       => '',
				'fileSize'       => '',
				'lines'          => 100,
				'logContent'     => '',
				'hasLines'       => false,
				'includeReport'  => false,
				'noticeMessage'  => '',
				'noticeType'     => '',
			);
			$state['copyError']           = false;
		}

		if ( 'fixes' === $tab && Features::has_fixers() ) {
			$state['config']['fixesUrl'] = rest_url( 'wp-system-report/v1/fixes' );
			$state['i18n']               = array_merge(
				$state['i18n'],
				array(
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
					'riskLow'        => __( 'Low Risk', 'wp-system-report' ),
					'riskMedium'     => __( 'Medium Risk', 'wp-system-report' ),
					'riskHigh'       => __( 'High Risk', 'wp-system-report' ),
					'before'         => __( 'Before', 'wp-system-report' ),
					'after'          => __( 'After', 'wp-system-report' ),
					'resultDetails'  => __( 'Result Details', 'wp-system-report' ),
				)
			);
			$state['fixes']              = array(
				'isLoading'           => true,
				'loaded'              => false,
				'hasError'            => false,
				'errorMessage'        => '',
				'hasFixers'           => false,
				'categories'          => array(),
				'modalOpen'           => false,
				'modalTitle'          => '',
				'modalMessage'        => '',
				'modalDescription'    => '',
				'pendingFixId'        => null,
				'lastFocusedSelector' => null,
			);
		}

		wp_interactivity_state( 'wp-system-report', $state );
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
