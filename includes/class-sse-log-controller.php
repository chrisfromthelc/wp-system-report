<?php
/**
 * SSE log stream REST controller.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * REST API controller that initiates the Server-Sent Events log stream.
 *
 * Registers a GET endpoint at `wp-system-report/v1/error-log/stream` and
 * hands off the response to {@see SSE_Log_Streamer} via the
 * `rest_pre_serve_request` filter so the streaming loop can write SSE
 * frames directly to the output buffer, bypassing the normal REST
 * response serialisation.
 *
 * Permission model is identical to {@see Error_Log_Controller}: requires
 * `manage_options` by default, filterable via
 * `wp_system_report_error_log_capability`, restricted to a hard-coded
 * allowlist of admin-level capabilities.
 */
class SSE_Log_Controller extends \WP_REST_Controller {

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
	 * SSE log streamer instance.
	 */
	private SSE_Log_Streamer $streamer;

	/**
	 * Minimum-privilege capabilities allowed for error log access.
	 *
	 * Mirrors the allowlist in {@see Error_Log_Controller} to prevent
	 * privilege escalation via the capability filter.
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
	 * Constructor.
	 *
	 * @param SSE_Log_Streamer $streamer SSE log streamer instance.
	 */
	public function __construct( SSE_Log_Streamer $streamer ) {
		$this->streamer = $streamer;
	}

	/**
	 * Register the REST routes.
	 *
	 * Adds a single GET endpoint:
	 *   GET /wp-system-report/v1/error-log/stream
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stream',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'stream_log' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'last_event_id' => array(
							'description'       => __( 'Last SSE event ID received by the client, for reconnection.', 'wp-system-report' ),
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Check permissions for the SSE stream endpoint.
	 *
	 * Reuses the same capability filter and allowlist as
	 * {@see Error_Log_Controller::permissions_check()} to ensure
	 * consistent access control across all error log endpoints.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if permitted, WP_Error otherwise.
	 */
	public function permissions_check( $request ) {
		/**
		 * Filter the required capability for error log access.
		 *
		 * Only admin-level capabilities are accepted to prevent privilege
		 * escalation. See SSE_Log_Controller::ALLOWED_CAPABILITIES for
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
	 * Handle the SSE stream request.
	 *
	 * Takes over the HTTP response via the `rest_pre_serve_request` filter
	 * so that {@see SSE_Log_Streamer::stream()} can write SSE frames
	 * directly to PHP output. The filter returns `true` to signal to the
	 * REST server that the response has already been sent.
	 *
	 * PHP execution time limit is removed for the duration of the stream
	 * and restored when the stream ends via a shutdown function, so that
	 * normal requests are unaffected.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response A placeholder 200 response; the actual
	 *                           response is served by the filter callback.
	 */
	public function stream_log( $request ) {
		$streamer = $this->streamer;

		add_filter(
			'rest_pre_serve_request',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $served required by filter signature.
			static function ( $served ) use ( $streamer ): bool {
				// Remove the PHP time limit so long-running streams do not
				// hit the default 30-second execution limit.
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_set_time_limit -- Required for SSE long-polling.
				if ( function_exists( 'set_time_limit' ) ) {
					set_time_limit( 0 );
				}

				$streamer->stream();

				return true;
			}
		);

		return new \WP_REST_Response( null, 200 );
	}
}
