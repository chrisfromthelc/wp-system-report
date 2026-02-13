<?php
/**
 * Error Log REST API controller.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * REST API controller for error log reading and debug toggle.
 */
class Error_Log_Controller extends \WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wp-system-report/v1';

	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'error-log';

	/**
	 * Error log reader instance.
	 *
	 * @var Error_Log_Reader
	 */
	private Error_Log_Reader $reader;

	/**
	 * Debug toggle instance.
	 *
	 * @var Debug_Toggle
	 */
	private Debug_Toggle $toggle;

	/**
	 * Constructor.
	 *
	 * @param Error_Log_Reader $reader Error log reader instance.
	 * @param Debug_Toggle     $toggle Debug toggle instance.
	 */
	public function __construct( Error_Log_Reader $reader, Debug_Toggle $toggle ) {
		$this->reader = $reader;
		$this->toggle = $toggle;
	}

	/**
	 * Register the REST routes.
	 */
	public function register_routes(): void {
		// GET /error-log — Read log lines.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_log' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'lines'  => array(
							'description'       => __( 'Number of lines to return.', 'wp-system-report' ),
							'type'              => 'integer',
							'minimum'           => 1,
							'maximum'           => 1000,
							'default'           => 100,
							'sanitize_callback' => 'absint',
						),
						'format' => array(
							'description'       => __( 'Output format.', 'wp-system-report' ),
							'type'              => 'string',
							'enum'              => array( 'json', 'raw' ),
							'default'           => 'json',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// GET /error-log/status — Debug constants and file info.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		// POST /error-log/toggle — Enable or disable debug logging.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/toggle',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'toggle_debug' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'enable' => array(
							'description'       => __( 'Whether to enable or disable debug logging.', 'wp-system-report' ),
							'type'              => 'boolean',
							'required'          => true,
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
					),
				),
			)
		);
	}

	/**
	 * Check permissions for error log operations.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if permitted, WP_Error otherwise.
	 */
	public function permissions_check( $request ) {
		/**
		 * Filter the required capability for error log access.
		 *
		 * @param string $capability WordPress capability. Default 'manage_options'.
		 */
		$capability = apply_filters( 'wp_system_report_error_log_capability', 'manage_options' );

		if ( ! is_string( $capability ) || '' === $capability ) {
			$capability = 'manage_options';
		}

		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'wp_system_report_rest_forbidden',
				__( 'Sorry, you are not allowed to access the error log.', 'wp-system-report' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Get error log lines.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_log( $request ) {
		$lines  = $request->get_param( 'lines' );
		$format = $request->get_param( 'format' );
		$path   = $this->reader->resolve_log_path();

		if ( null === $path ) {
			return new \WP_Error(
				'wp_system_report_no_log',
				__( 'No error log file found.', 'wp-system-report' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->reader->is_path_safe( $path ) ) {
			return new \WP_Error(
				'wp_system_report_unsafe_path',
				__( 'The error log path is outside the allowed directory boundary.', 'wp-system-report' ),
				array( 'status' => 403 )
			);
		}

		$log_lines = $this->reader->read_last_lines( $path, $lines );

		if ( 'raw' === $format ) {
			$output = implode( "\n", $log_lines );

			add_filter(
				'rest_pre_serve_request',
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by filter signature.
				function ( $served ) use ( $output ): bool {
					if ( ! headers_sent() ) {
						header( 'Content-Type: text/plain; charset=utf-8' );
					}
					echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw log output for download.
					return true;
				}
			);

			return new \WP_REST_Response( null, 200 );
		}

		return rest_ensure_response(
			array(
				'lines' => $log_lines,
				'count' => count( $log_lines ),
				'file'  => $this->reader->get_file_info(),
			)
		);
	}

	/**
	 * Get error log status.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_status( $request ) {
		$status               = $this->reader->get_status();
		$status['toggle']     = $this->toggle->get_state();
		$status['settings']   = Settings::get_all();

		return rest_ensure_response( $status );
	}

	/**
	 * Toggle debug logging.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function toggle_debug( $request ) {
		$enable = $request->get_param( 'enable' );

		if ( ! $this->toggle->can_modify() ) {
			return new \WP_Error(
				'wp_system_report_cannot_modify',
				__( 'wp-config.php is not writable or file modifications are disabled.', 'wp-system-report' ),
				array( 'status' => 403 )
			);
		}

		$result = $enable ? $this->toggle->enable_debug() : $this->toggle->disable_debug();

		if ( true !== $result ) {
			return new \WP_Error(
				'wp_system_report_toggle_failed',
				$result,
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'enabled' => $enable,
				'state'   => $this->toggle->get_state(),
			)
		);
	}
}
