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
 */
	private Error_Log_Reader $reader;

	/**
 * Debug toggle instance.
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
							'maximum'           => 10000,
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
	 * Minimum-privilege capabilities allowed for error log access.
	 *
	 * The capability filter can only return one of these values.
	 * This prevents a malicious plugin from weakening access control
	 * by returning a low-privilege capability like 'read'.
	 *
	 * @var string[]
	 */
	private const ALLOWED_CAPABILITIES = array(
		'manage_options',
		'manage_network',
		'install_plugins',
		'edit_plugins',
		'update_plugins',
		'delete_plugins',
		'manage_network_plugins',
	);

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
		 * Only admin-level capabilities are accepted to prevent privilege
		 * escalation. See Error_Log_Controller::ALLOWED_CAPABILITIES for
		 * the full list.
		 *
		 * @param string $capability WordPress capability. Default 'manage_options'.
		 */
		$capability = apply_filters( 'wp_system_report_error_log_capability', 'manage_options' );

		if ( ! is_string( $capability ) || '' === $capability || ! in_array( $capability, self::ALLOWED_CAPABILITIES, true ) ) {
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
						header( 'X-Content-Type-Options: nosniff' );
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
	 * Transient name for status endpoint cache.
	 *
	 * @var string
	 */
	private const STATUS_CACHE_KEY = 'sr_error_log_status';

	/**
	 * How long to cache the status response (in seconds).
	 *
	 * @var int
	 */
	private const STATUS_CACHE_TTL = 30;

	/**
	 * Get error log status.
	 *
	 * Caches the response in a transient to avoid repeated filesystem
	 * reads and wp-config.php parsing on rapid polling.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_status( $request ) {
		$cached = get_transient( self::STATUS_CACHE_KEY );

		if ( false !== $cached && is_array( $cached ) ) {
			return rest_ensure_response( $cached );
		}

		$status             = $this->reader->get_status();
		$status['toggle']   = $this->toggle->get_state();
		$status['settings'] = Settings::get_all();

		set_transient( self::STATUS_CACHE_KEY, $status, self::STATUS_CACHE_TTL );

		return rest_ensure_response( $status );
	}

	/**
	 * Minimum seconds between debug toggle operations.
	 *
	 * @var int
	 */
	private const TOGGLE_COOLDOWN_SECONDS = 3;

	/**
	 * Transient name for toggle rate limiting.
	 *
	 * @var string
	 */
	private const TOGGLE_RATE_LIMIT_KEY = 'sr_debug_toggle_cooldown';

	/**
	 * Toggle debug logging.
	 *
	 * Enforces a cooldown period between toggle operations to prevent
	 * rapid repeated modifications to wp-config.php.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function toggle_debug( $request ) {
		$enable = $request->get_param( 'enable' );

		// Rate limiting: reject requests during cooldown period.
		if ( get_transient( self::TOGGLE_RATE_LIMIT_KEY ) ) {
			return new \WP_Error(
				'wp_system_report_rate_limited',
				sprintf(
					/* translators: %d: cooldown seconds */
					__( 'Please wait %d seconds between debug toggle operations.', 'wp-system-report' ),
					self::TOGGLE_COOLDOWN_SECONDS
				),
				array( 'status' => 429 )
			);
		}

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

		// Set cooldown transient after successful toggle.
		set_transient( self::TOGGLE_RATE_LIMIT_KEY, 1, self::TOGGLE_COOLDOWN_SECONDS );

		// Invalidate status cache after toggle.
		delete_transient( self::STATUS_CACHE_KEY );

		return rest_ensure_response(
			array(
				'success' => true,
				'enabled' => $enable,
				'state'   => $this->toggle->get_state(),
			)
		);
	}
}
