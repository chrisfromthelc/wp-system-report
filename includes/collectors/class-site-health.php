<?php
/**
 * Site Health collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects WordPress Site Health test results and recommendations.
 */
class Site_Health extends Abstract_Collector {

	/**
 * Get the collector ID.
 */
	public function get_id(): string {
		return 'site_health';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Site Health', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'WordPress Site Health test results and recommendations.', 'system-report' );
	}

	/**
 * Get the collector priority.
 */
	public function get_priority(): int {
		return 120;
	}

	/**
 * Get the transient cache key.
 */
	protected function get_cache_key(): string {
		return 'sr_site_health';
	}

	/**
 * Collect the data.
 */
	public function collect(): array {
		// Load required WordPress files.
		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$fields = array();

		try {
			$site_health = \WP_Site_Health::get_instance();
			$tests       = $site_health->get_tests();

			$good_count        = 0;
			$recommended_count = 0;
			$critical_count    = 0;

			// Process direct tests only.
			if ( isset( $tests['direct'] ) && is_array( $tests['direct'] ) ) {
				foreach ( $tests['direct'] as $test_id => $test_config ) {
					// Skip if no test callback.
					if ( empty( $test_config['test'] ) ) {
						continue;
					}

					try {
						// Execute the test callback.
						$test_result = null;

						if ( is_callable( $test_config['test'] ) ) {
							$test_result = call_user_func( $test_config['test'] );
						}
						// Skip if test failed or returned invalid result.
						if ( ! is_array( $test_result ) ) {
							continue;
						}
						if ( empty( $test_result['status'] ) ) {
							continue;
						}

						// Count by status.
						switch ( $test_result['status'] ) {
							case 'good':
								++$good_count;
								break;
							case 'recommended':
								++$recommended_count;
								break;
							case 'critical':
								++$critical_count;
								break;
						}

						// Map Site Health status to our status.
						$status_map = array(
							'good'        => 'good',
							'recommended' => 'warning',
							'critical'    => 'critical',
						);

						$field_status = isset( $status_map[ $test_result['status'] ] ) ?
							$status_map[ $test_result['status'] ] : 'info';

						// Add the test result as a field.
						$fields[] = $this->make_field(
							isset( $test_result['label'] ) ? $test_result['label'] : $test_id,
							ucfirst( $test_result['status'] ),
							array(
								'status'      => $field_status,
								'description' => isset( $test_result['description'] ) ?
									wp_strip_all_tags( $test_result['description'] ) : '',
								'debug'       => $test_result,
							)
						);

					} catch ( \Exception $e ) {
						// Skip tests that throw exceptions.
						continue;
					}
				}
			}

			// Add summary field at the beginning.
			array_unshift(
				$fields,
				$this->make_field(
					__( 'Site Health Summary', 'system-report' ),
					sprintf(
						// translators: %1$d: Good count, %2$d: Recommended count, %3$d: Critical count.
						__( '%1$d good, %2$d recommended, %3$d critical', 'system-report' ),
						$good_count,
						$recommended_count,
						$critical_count
					),
					array(
						'status' => $critical_count > 0 ? 'critical' : ( $recommended_count > 0 ? 'warning' : 'good' ),
						'debug'  => array(
							'good'        => $good_count,
							'recommended' => $recommended_count,
							'critical'    => $critical_count,
						),
					)
				)
			);

		} catch ( \Exception $e ) {
			// If Site Health fails completely, add error field.
			$fields[] = $this->make_field(
				__( 'Site Health Status', 'system-report' ),
				__( 'Unable to retrieve Site Health data', 'system-report' ),
				array(
					'status'      => 'warning',
					'description' => $e->getMessage(),
				)
			);
		}

		return $fields;
	}
}
