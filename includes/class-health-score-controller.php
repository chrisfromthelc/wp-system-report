<?php
/**
 * Health score REST controller.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * REST API controller for the health score endpoint.
 *
 * Provides:
 * - GET /wp-system-report/v1/health-score — full score with breakdown.
 */
class Health_Score_Controller extends \WP_REST_Controller {

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
	protected $rest_base = 'health-score';

	/**
	 * Health score calculator instance.
	 */
	private Health_Score $health_score;

	/**
	 * Constructor.
	 *
	 * @param Health_Score $health_score Health score calculator instance.
	 */
	public function __construct( Health_Score $health_score ) {
		$this->health_score = $health_score;
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
					'callback'            => array( $this, 'get_health_score' ),
					'permission_callback' => array( $this, 'get_health_score_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Check permissions for viewing the health score.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if permitted, WP_Error otherwise.
	 */
	public function get_health_score_permissions_check( $request ) {
		/** This filter is documented in includes/class-rest-controller.php */
		$capability = apply_filters( 'wp_system_report_capability', 'manage_options' );

		if ( ! is_string( $capability ) || '' === $capability ) {
			$capability = 'manage_options';
		}

		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'wp_system_report_rest_forbidden',
				__( 'Sorry, you are not allowed to view the health score.', 'wp-system-report' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Get the health score.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response Health score response.
	 */
	public function get_health_score( $request ): \WP_REST_Response {
		$result = $this->health_score->calculate();

		return REST_Envelope::success(
			$result,
			array( 'endpoint' => 'health-score' )
		);
	}

	/**
	 * Get the schema for the health score endpoint.
	 *
	 * @return array JSON Schema for the endpoint.
	 */
	public function get_public_item_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'health-score',
			'type'       => 'object',
			'properties' => array(
				'score'     => array(
					'description' => __( 'Aggregate health score (0-100).', 'wp-system-report' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'maximum'     => 100,
				),
				'grade'     => array(
					'description' => __( 'Letter grade derived from the score.', 'wp-system-report' ),
					'type'        => 'string',
					'enum'        => array( 'A+', 'A', 'B', 'C', 'D', 'F' ),
				),
				'breakdown' => array(
					'description' => __( 'Per-section score breakdown.', 'wp-system-report' ),
					'type'        => 'object',
				),
				'summary'   => array(
					'description' => __( 'Overall field status summary.', 'wp-system-report' ),
					'type'        => 'object',
					'properties'  => array(
						'total_fields' => array( 'type' => 'integer' ),
						'good'         => array( 'type' => 'integer' ),
						'warnings'     => array( 'type' => 'integer' ),
						'criticals'    => array( 'type' => 'integer' ),
						'info'         => array( 'type' => 'integer' ),
					),
				),
			),
		);
	}
}
