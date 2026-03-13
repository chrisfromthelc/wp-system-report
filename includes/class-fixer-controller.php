<?php
/**
 * Fixer REST API controller.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * REST API controller for listing and executing fixers.
 *
 * Provides two endpoints:
 *
 * - `GET  /wp-system-report/v1/fixes`           — List all registered fixers with metadata.
 * - `POST /wp-system-report/v1/fixes/{fix_id}`  — Execute a specific fixer.
 *
 * Both endpoints require the `manage_options` capability (filterable via
 * `wp_system_report_capability`). Executing a fixer returns before/after
 * state snapshots so callers can verify the outcome.
 */
class Fixer_Controller extends \WP_REST_Controller {

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
	protected $rest_base = 'fixes';

	/**
	 * Fixer registry instance.
	 */
	private Fixer_Registry $registry;

	/**
	 * Constructor.
	 *
	 * @param Fixer_Registry $registry Fixer registry instance.
	 */
	public function __construct( Fixer_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Register the REST routes.
	 */
	public function register_routes(): void {
		// GET /fixes — List all available fixers.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_fixes' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'category' => array(
							'description'       => __( 'Filter fixers by category.', 'wp-system-report' ),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// POST /fixes/{fix_id} — Execute a specific fixer.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<fix_id>[a-z0-9_]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'execute_fix' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'fix_id'    => array(
							'description'       => __( 'The fixer identifier.', 'wp-system-report' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'confirmed' => array(
							'description' => __( 'Explicit confirmation flag, required for medium and high risk fixers.', 'wp-system-report' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Check permissions for fixer endpoints.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if permitted, WP_Error otherwise.
	 */
	public function permissions_check( $request ) {
		if ( ! Features::has_fixers() ) {
			return new \WP_Error(
				'wp_system_report_fixers_disabled',
				__( 'Fixer capabilities are not available.', 'wp-system-report' ),
				array( 'status' => 403 )
			);
		}

		/** This filter is documented in includes/class-rest-controller.php. */
		$capability = apply_filters( 'wp_system_report_capability', 'manage_options' );

		if ( ! is_string( $capability ) || '' === $capability ) {
			$capability = 'manage_options';
		}

		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'wp_system_report_rest_forbidden',
				__( 'Sorry, you are not allowed to manage fixers.', 'wp-system-report' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * List all available fixers.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response Response with fixer list.
	 */
	public function list_fixes( $request ): \WP_REST_Response {
		$category = $request->get_param( 'category' );
		$fixers   = $category
			? $this->registry->get_by_category( $category )
			: $this->registry->get_all();

		$items = array();
		foreach ( $fixers as $fixer ) {
			$items[] = $this->prepare_fixer_item( $fixer );
		}

		return REST_Envelope::success(
			$items,
			array(
				'total' => count( $items ),
			)
		);
	}

	/**
	 * Execute a specific fixer.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response with fix result or error.
	 */
	public function execute_fix( $request ) {
		$fix_id = $request->get_param( 'fix_id' );
		$fixer  = $this->registry->get( $fix_id );

		if ( null === $fixer ) {
			return new \WP_Error(
				'wp_system_report_fixer_not_found',
				sprintf(
					/* translators: %s: fixer ID */
					__( 'Fixer "%s" not found.', 'wp-system-report' ),
					$fix_id
				),
				array( 'status' => 404 )
			);
		}

		// Require explicit confirmation for medium and high risk fixers.
		if ( $fixer->get_risk_level()->requires_confirmation() && ! $request->get_param( 'confirmed' ) ) {
			return new \WP_Error(
				'wp_system_report_confirmation_required',
				sprintf(
					/* translators: 1: fixer ID, 2: risk level */
					__( 'Fixer "%1$s" has %2$s risk and requires explicit confirmation. Resend with confirmed=true.', 'wp-system-report' ),
					$fix_id,
					$fixer->get_risk_level()->get_label()
				),
				array( 'status' => 409 )
			);
		}

		if ( ! $fixer->can_fix() ) {
			return REST_Envelope::success(
				array(
					'fix_id'  => $fix_id,
					'result'  => array(
						'success' => true,
						'message' => __( 'No issues detected. Nothing to fix.', 'wp-system-report' ),
					),
					'applied' => false,
				)
			);
		}

		$result = $fixer->fix();

		return REST_Envelope::success(
			array(
				'fix_id'  => $fix_id,
				'result'  => $result->to_array(),
				'applied' => true,
			)
		);
	}

	/**
	 * Prepare a single fixer item for the response.
	 *
	 * @param Fixer $fixer Fixer instance.
	 * @return array<string, mixed> Fixer data for the response.
	 */
	private function prepare_fixer_item( Fixer $fixer ): array {
		return array(
			'id'                    => $fixer->get_id(),
			'label'                 => $fixer->get_label(),
			'description'           => $fixer->get_description(),
			'category'              => $fixer->get_category(),
			'risk_level'            => $fixer->get_risk_level()->value,
			'risk_label'            => $fixer->get_risk_level()->get_label(),
			'requires_confirmation' => $fixer->get_risk_level()->requires_confirmation(),
			'can_fix'               => $fixer->can_fix(),
		);
	}
}
