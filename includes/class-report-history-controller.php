<?php
/**
 * Report history REST API controller.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * REST API controller for report history and trending endpoints.
 */
class Report_History_Controller extends \WP_REST_Controller {

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
	protected $rest_base = 'history';

	/**
 * Report history instance.
 */
	private Report_History $history;

	/**
	 * Constructor.
	 *
	 * @param Report_History $history Report history instance.
	 */
	public function __construct( Report_History $history ) {
		$this->history = $history;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		// List snapshots.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_items' ),
					'permission_callback' => array( $this, 'delete_items_permissions_check' ),
				),
			)
		);

		// Single snapshot.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description'       => __( 'Snapshot ID.', 'wp-system-report' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_items_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description'       => __( 'Snapshot ID.', 'wp-system-report' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Trend data.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/trend',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_trend' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'days'  => array(
							'description'       => __( 'Number of days of history.', 'wp-system-report' ),
							'type'              => 'integer',
							'default'           => 30,
							'minimum'           => 1,
							'maximum'           => 365,
							'sanitize_callback' => 'absint',
						),
						'after' => array(
							'description'       => __( 'Return data after this date (ISO 8601).', 'wp-system-report' ),
							'type'              => 'string',
							'format'            => 'date-time',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Check permissions for listing snapshots.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if permitted, WP_Error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return $this->check_permission();
	}

	/**
	 * Check permissions for creating snapshots.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if permitted, WP_Error otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		return $this->check_permission();
	}

	/**
	 * Check permissions for deleting snapshots.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if permitted, WP_Error otherwise.
	 */
	public function delete_items_permissions_check( $request ) {
		return $this->check_permission();
	}

	/**
	 * List snapshots.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_items( $request ): \WP_REST_Response {
		$result = $this->history->list_snapshots(
			array(
				'per_page' => $request->get_param( 'per_page' ),
				'page'     => $request->get_param( 'page' ),
				'order'    => $request->get_param( 'order' ),
				'after'    => $request->get_param( 'after' ) ?? '',
				'before'   => $request->get_param( 'before' ) ?? '',
			)
		);

		return REST_Envelope::success(
			$result['items'],
			array(
				'total' => $result['total'],
				'pages' => $result['pages'],
			)
		);
	}

	/**
	 * Retrieve a single snapshot with full report data.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_item( $request ) {
		$snapshot = $this->history->get_snapshot( (int) $request->get_param( 'id' ) );

		if ( null === $snapshot ) {
			return new \WP_Error(
				'wp_system_report_snapshot_not_found',
				__( 'Snapshot not found.', 'wp-system-report' ),
				array( 'status' => 404 )
			);
		}

		return REST_Envelope::success( $snapshot );
	}

	/**
	 * Create a new snapshot.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function create_item( $request ) {
		$snapshot_id = $this->history->save_snapshot();

		if ( false === $snapshot_id ) {
			return new \WP_Error(
				'wp_system_report_snapshot_failed',
				__( 'Failed to create snapshot.', 'wp-system-report' ),
				array( 'status' => 500 )
			);
		}

		$snapshot = $this->history->get_snapshot( $snapshot_id );

		$response = REST_Envelope::success( $snapshot );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Delete a single snapshot.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function delete_item( $request ) {
		$id      = (int) $request->get_param( 'id' );
		$deleted = $this->history->delete_snapshot( $id );

		if ( ! $deleted ) {
			return new \WP_Error(
				'wp_system_report_snapshot_not_found',
				__( 'Snapshot not found.', 'wp-system-report' ),
				array( 'status' => 404 )
			);
		}

		return REST_Envelope::success(
			array( 'deleted' => true ),
			array( 'id' => $id )
		);
	}

	/**
	 * Delete all snapshots.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response Response object.
	 */
	public function delete_items( $request ): \WP_REST_Response {
		$count = $this->history->delete_all();

		return REST_Envelope::success(
			array( 'deleted' => $count ),
			array( 'purged' => true )
		);
	}

	/**
	 * Get trend data.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_trend( \WP_REST_Request $request ): \WP_REST_Response {
		$trend = $this->history->get_trend(
			array(
				'days'  => $request->get_param( 'days' ),
				'after' => $request->get_param( 'after' ) ?? '',
			)
		);

		$latest = $this->history->get_latest();

		return REST_Envelope::success(
			array(
				'data_points' => $trend,
				'latest'      => $latest,
				'period_days' => $request->get_param( 'days' ),
			)
		);
	}

	/**
	 * Get the query params for collection requests.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params(): array {
		return array(
			'per_page' => array(
				'description'       => __( 'Results per page.', 'wp-system-report' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'page'     => array(
				'description'       => __( 'Page number.', 'wp-system-report' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'order'    => array(
				'description'       => __( 'Sort order.', 'wp-system-report' ),
				'type'              => 'string',
				'enum'              => array( 'asc', 'desc' ),
				'default'           => 'desc',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'after'    => array(
				'description'       => __( 'Return snapshots after this date (ISO 8601).', 'wp-system-report' ),
				'type'              => 'string',
				'format'            => 'date-time',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'before'   => array(
				'description'       => __( 'Return snapshots before this date (ISO 8601).', 'wp-system-report' ),
				'type'              => 'string',
				'format'            => 'date-time',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Check whether the current user has permission.
	 *
	 * @return bool|\WP_Error True if permitted, WP_Error otherwise.
	 */
	private function check_permission(): \WP_Error|bool {
		/** This filter is documented in includes/class-rest-controller.php */
		$capability = apply_filters( 'wp_system_report_capability', 'manage_options' );

		if ( ! is_string( $capability ) || '' === $capability ) {
			$capability = 'manage_options';
		}

		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'wp_system_report_rest_forbidden',
				__( 'Sorry, you are not allowed to access report history.', 'wp-system-report' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}
}
