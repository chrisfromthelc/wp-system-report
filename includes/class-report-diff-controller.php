<?php
/**
 * Report diff REST controller.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * REST API controller for comparing two report snapshots.
 *
 * Provides endpoints to generate a structured diff between two historical
 * snapshots (by ID) or between the current live report and a historical
 * snapshot.
 */
class Report_Diff_Controller extends \WP_REST_Controller {

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wp-system-report/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'diff';

	/**
	 * Report diff engine.
	 */
	private Report_Diff $diff;

	/**
	 * Report history instance (nullable — may not be available).
	 */
	private ?Report_History $history;

	/**
	 * Report generator instance for live reports.
	 */
	private Report_Generator $report_generator;

	/**
	 * Health score calculator.
	 */
	private Health_Score $health_score;

	/**
	 * Constructor.
	 *
	 * @param Report_Diff      $diff             Diff engine instance.
	 * @param Report_Generator $report_generator Report generator instance.
	 * @param Health_Score     $health_score     Health score calculator.
	 * @param Report_History|null $history        Report history instance (null if disabled).
	 */
	public function __construct(
		Report_Diff $diff,
		Report_Generator $report_generator,
		Health_Score $health_score,
		?Report_History $history = null
	) {
		$this->diff             = $diff;
		$this->report_generator = $report_generator;
		$this->health_score     = $health_score;
		$this->history          = $history;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'compare_snapshots' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'before' => array(
							'description' => __( 'Snapshot ID for the older report, or "current" for a live report.', 'wp-system-report' ),
							'type'        => array( 'integer', 'string' ),
							'required'    => true,
						),
						'after'  => array(
							'description' => __( 'Snapshot ID for the newer report, or "current" for a live report.', 'wp-system-report' ),
							'type'        => array( 'integer', 'string' ),
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Check permissions.
	 *
	 * @return \WP_Error|bool True if the request has permission, WP_Error otherwise.
	 */
	public function check_permission(): \WP_Error|bool {
		/** This filter is documented in includes/class-rest-controller.php */
		$capability = apply_filters( 'wp_system_report_capability', 'manage_options' );

		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to compare reports.', 'wp-system-report' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Compare two snapshots.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error The diff result or an error.
	 */
	public function compare_snapshots( \WP_REST_Request $request ) {
		$before_id = $request->get_param( 'before' );
		$after_id  = $request->get_param( 'after' );

		$before_result = $this->resolve_report( $before_id );
		if ( is_wp_error( $before_result ) ) {
			return $before_result;
		}

		$after_result = $this->resolve_report( $after_id );
		if ( is_wp_error( $after_result ) ) {
			return $after_result;
		}

		$diff = $this->diff->compare(
			$before_result['report'],
			$after_result['report'],
			$before_result['label'],
			$after_result['label']
		);

		// Add health score comparison if available.
		$diff['health_score'] = array(
			'before' => $before_result['score'] ?? null,
			'after'  => $after_result['score'] ?? null,
		);

		return REST_Envelope::success( $diff );
	}

	/**
	 * Resolve a report reference to actual report data.
	 *
	 * Accepts "current" for a live report or an integer snapshot ID.
	 *
	 * @param int|string $reference The snapshot ID or "current".
	 * @return array{report: array, label: string, score: array|null}|\WP_Error
	 */
	private function resolve_report( $reference ): array|\WP_Error {
		if ( 'current' === $reference ) {
			$report     = $this->report_generator->generate();
			$score_data = $this->health_score->calculate( $report );

			return array(
				'report' => $report,
				'label'  => __( 'Current', 'wp-system-report' ),
				'score'  => array(
					'score' => $score_data['score'],
					'grade' => $score_data['grade'],
				),
			);
		}

		if ( null === $this->history ) {
			return new \WP_Error(
				'rest_report_history_disabled',
				__( 'Report history is not available.', 'wp-system-report' ),
				array( 'status' => 400 )
			);
		}

		$snapshot_id = (int) $reference;
		if ( $snapshot_id < 1 ) {
			return new \WP_Error(
				'rest_invalid_snapshot_id',
				__( 'Invalid snapshot ID.', 'wp-system-report' ),
				array( 'status' => 400 )
			);
		}

		$snapshot = $this->history->get_snapshot( $snapshot_id );
		if ( null === $snapshot ) {
			return new \WP_Error(
				'rest_snapshot_not_found',
				/* translators: %d: snapshot ID */
				sprintf( __( 'Snapshot %d not found.', 'wp-system-report' ), $snapshot_id ),
				array( 'status' => 404 )
			);
		}

		return array(
			'report' => $snapshot['report_data'],
			'label'  => $snapshot['created_at'] ?? '',
			'score'  => array(
				'score' => $snapshot['score'] ?? null,
				'grade' => $snapshot['grade'] ?? null,
			),
		);
	}
}
