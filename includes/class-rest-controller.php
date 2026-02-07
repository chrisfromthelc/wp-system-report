<?php
/**
 * REST API controller.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * REST API controller for the system report endpoint.
 */
class REST_Controller extends \WP_REST_Controller {

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
	protected $rest_base = 'report';

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
	 * Register the REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_report' ),
					'permission_callback' => array( $this, 'get_report_permissions_check' ),
					'args'                => array(
						'format' => array(
							'description' => __( 'Output format.', 'wp-system-report' ),
							'type'        => 'string',
							'enum'        => array( 'json', 'plain', 'github', 'ai' ),
							'default'     => 'json',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Check permissions for viewing the report.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if permitted, WP_Error otherwise.
	 */
	public function get_report_permissions_check( $request ) {
		/**
		 * Filter the required capability for the REST endpoint.
		 *
		 * @param string $capability WordPress capability. Default 'manage_options'.
		 */
		$capability = apply_filters( 'wp_system_report_capability', 'manage_options' );

		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'wp_system_report_rest_forbidden',
				__( 'Sorry, you are not allowed to view the system report.', 'wp-system-report' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Get the system report.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_report( $request ) {
		$format = $request->get_param( 'format' );
		$report = $this->report_generator->generate();

		if ( 'json' === $format ) {
			return rest_ensure_response( $report );
		}

		$formatter = $this->get_formatter( $format );

		if ( null === $formatter ) {
			return new \WP_Error(
				'wp_system_report_invalid_format',
				__( 'Invalid report format.', 'wp-system-report' ),
				array( 'status' => 400 )
			);
		}

		$output   = $formatter->format( $report );
		$response = new \WP_REST_Response( $output );
		$response->header( 'Content-Type', $formatter->get_content_type() );

		return $response;
	}

	/**
	 * Get a formatter instance for the given format.
	 *
	 * @param string $format Format identifier.
	 * @return Formatters\Formatter|null Formatter instance or null.
	 */
	private function get_formatter( $format ) {
		switch ( $format ) {
			case 'plain':
				return new Formatters\Plain_Text_Formatter();
			case 'github':
				return new Formatters\GitHub_Formatter();
			case 'ai':
				return new Formatters\AI_Formatter();
			default:
				return null;
		}
	}
}
