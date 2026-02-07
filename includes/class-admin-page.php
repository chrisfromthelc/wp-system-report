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

		wp_enqueue_style(
			'wp-system-report-admin',
			WP_SYSTEM_REPORT_URL . 'assets/css/wp-system-report-admin.css',
			array(),
			WP_SYSTEM_REPORT_VERSION
		);

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
				),
			)
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( $this->get_capability() ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-system-report' ) );
		}

		$report = $this->report_generator->generate();

		include WP_SYSTEM_REPORT_DIR . 'templates/admin-page.php';
	}

	/**
	 * Get the required capability to view the report.
	 *
	 * @return string WordPress capability.
	 */
	private function get_capability() {
		/**
		 * Filter the required capability for viewing the WP System Report.
		 *
		 * @param string $capability WordPress capability. Default 'manage_options'.
		 */
		return apply_filters( 'wp_system_report_capability', 'manage_options' );
	}
}
