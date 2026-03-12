<?php
/**
 * REST API response envelope.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Standardised envelope wrapper for all REST API JSON responses.
 *
 * Every JSON response from the plugin is wrapped in a consistent
 * structure:
 *
 *     {
 *         "status": "success",
 *         "data":   { ... },
 *         "meta":   { "generated_at": "...", "plugin_version": "..." }
 *     }
 *
 * Error responses follow the same pattern with `status: "error"` and
 * the error details nested under `data`.
 */
class REST_Envelope {

	/**
	 * Wrap a successful payload in the standard envelope.
	 *
	 * @param mixed $data Payload to return under the `data` key.
	 * @param array $meta Optional extra metadata merged into the `meta` key.
	 */
	public static function success( $data, array $meta = array() ): \WP_REST_Response {
		$envelope = array(
			'status' => 'success',
			'data'   => $data,
			'meta'   => self::build_meta( $meta ),
		);

		return rest_ensure_response( $envelope );
	}

	/**
	 * Build the meta block with default values.
	 *
	 * @param array $extra Extra keys to merge.
	 */
	private static function build_meta( array $extra = array() ): array {
		$defaults = array(
			'generated_at'   => gmdate( 'c' ),
			'plugin_version' => defined( 'WP_SYSTEM_REPORT_VERSION' ) ? WP_SYSTEM_REPORT_VERSION : 'unknown',
		);

		return array_merge( $defaults, $extra );
	}
}
