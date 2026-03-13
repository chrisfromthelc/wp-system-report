<?php
/**
 * Site Health collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

use SystemReport\Status;

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
		return __( 'Site Health', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'WordPress Site Health test results and recommendations.', 'wp-system-report' );
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
						$test_result = $this->run_site_health_test( $site_health, $test_config['test'] );

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
							'good'        => Status::Good,
							'recommended' => Status::Warning,
							'critical'    => Status::Critical,
						);

						$field_status = $status_map[ $test_result['status'] ] ?? Status::Info;

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

					} catch ( \Throwable $e ) {
						// Skip tests that throw exceptions or errors (e.g. TypeError on PHP 8.x).
						continue;
					}
				}
			}

			// Add summary field at the beginning.
			array_unshift(
				$fields,
				$this->make_field(
					__( 'Site Health Summary', 'wp-system-report' ),
					sprintf(
						// translators: %1$d: Good count, %2$d: Recommended count, %3$d: Critical count.
						__( '%1$d good, %2$d recommended, %3$d critical', 'wp-system-report' ),
						$good_count,
						$recommended_count,
						$critical_count
					),
					array(
						'status' => $critical_count > 0 ? Status::Critical : ( $recommended_count > 0 ? Status::Warning : Status::Good ),
						'debug'  => array(
							'good'        => $good_count,
							'recommended' => $recommended_count,
							'critical'    => $critical_count,
						),
					)
				)
			);

		} catch ( \Throwable $e ) {
			// If Site Health fails completely, add error field.
			$fields[] = $this->make_field(
				__( 'Site Health Status', 'wp-system-report' ),
				__( 'Unable to retrieve Site Health data', 'wp-system-report' ),
				array(
					'status'      => Status::Warning,
					'description' => $e->getMessage(),
				)
			);
		}

		return $fields;
	}

	/**
	 * Execute a single Site Health test callback.
	 *
	 * WordPress core registers most tests with a short string name (e.g.
	 * 'wordpress_version') that maps to a method on the WP_Site_Health instance
	 * via the pattern get_test_{$name}(). Third-party tests may register a
	 * callable directly. This method mirrors the resolution logic found in
	 * WP_Site_Health::get_page_data(), where string tests are first resolved
	 * to an instance method before falling back to a global callable check.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Site_Health $site_health The Site Health instance.
	 * @param string|callable $test        The test callback or method suffix.
	 * @return array|null The test result array, or null on failure.
	 */
	private function run_site_health_test( \WP_Site_Health $site_health, string|callable $test ): ?array {
		if ( is_string( $test ) ) {
			// String tests: first try the core convention get_test_{$name}().
			$method = sprintf( 'get_test_%s', $test );

			if ( method_exists( $site_health, $method ) && is_callable( array( $site_health, $method ) ) ) {
				$result = $site_health->{$method}();
			} elseif ( is_callable( $test ) ) {
				// Fall back to treating the string as a global callable.
				$result = call_user_func( $test );
			} else {
				return null;
			}
		} else {
			// Non-string callables (arrays, closures, invocable objects).
			$result = call_user_func( $test );
		}

		/** This filter is documented in wp-admin/includes/class-wp-site-health.php */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter.
		return is_array( $result ) ? apply_filters( 'site_status_test_result', $result ) : null;
	}
}
