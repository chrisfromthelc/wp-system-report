<?php
/**
 * Collector tests.
 *
 * @package SystemReport
 */

use SystemReport\Collectors\Abstract_Collector;

/**
 * Test collector output structure and behavior.
 */
class CollectorsTest extends WP_UnitTestCase {

	/**
	 * All registered collectors from the plugin.
	 *
	 * @var \SystemReport\Collectors\Collector[]
	 */
	private $collectors;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		$plugin           = SystemReport\Plugin::get_instance();
		$generator        = $plugin->get_report_generator();
		$this->collectors = $generator->get_collectors();
	}

	/**
	 * Test that all default collectors are registered.
	 */
	public function test_default_collectors_registered() {
		$expected_ids = array(
			'wordpress_environment',
			'server_environment',
			'database',
			'post_type_counts',
			'security',
			'active_plugins',
			'inactive_plugins',
			'dropins_mu_plugins',
			'theme_info',
			'wordpress_constants',
			'filesystem_permissions',
			'site_health',
			'cron_health',
			'rest_api_info',
			'custom_content_types',
			'wordpress_configuration',
			'advanced_diagnostics',
		);

		foreach ( $expected_ids as $id ) {
			$this->assertArrayHasKey( $id, $this->collectors, "Collector '$id' should be registered." );
		}
	}

	/**
	 * Test that all collectors have unique IDs.
	 */
	public function test_collector_ids_are_unique() {
		$ids = array();
		foreach ( $this->collectors as $collector ) {
			$id = $collector->get_id();
			$this->assertNotContains( $id, $ids, "Duplicate collector ID: $id" );
			$ids[] = $id;
		}
	}

	/**
	 * Test that all collectors have non-empty labels.
	 */
	public function test_collector_labels_are_non_empty() {
		foreach ( $this->collectors as $collector ) {
			$label = $collector->get_label();
			$this->assertNotEmpty( $label, "Collector '{$collector->get_id()}' should have a non-empty label." );
			$this->assertIsString( $label );
		}
	}

	/**
	 * Test that all collectors have descriptions.
	 */
	public function test_collector_descriptions_are_non_empty() {
		foreach ( $this->collectors as $collector ) {
			$description = $collector->get_description();
			$this->assertNotEmpty( $description, "Collector '{$collector->get_id()}' should have a description." );
			$this->assertIsString( $description );
		}
	}

	/**
	 * Test that all collectors return integer priorities.
	 */
	public function test_collector_priorities_are_integers() {
		foreach ( $this->collectors as $collector ) {
			$priority = $collector->get_priority();
			$this->assertIsInt( $priority, "Collector '{$collector->get_id()}' priority should be an integer." );
		}
	}

	/**
	 * Test that collect() returns an array for all collectors.
	 */
	public function test_collect_returns_array() {
		foreach ( $this->collectors as $collector ) {
			$data = $collector->collect();
			$this->assertIsArray( $data, "Collector '{$collector->get_id()}' should return an array." );
		}
	}

	/**
	 * Test that collected fields have required keys.
	 */
	public function test_collected_fields_have_required_keys() {
		foreach ( $this->collectors as $collector ) {
			$fields = $collector->collect();
			foreach ( $fields as $index => $field ) {
				$context = "Collector '{$collector->get_id()}', field index $index";
				$this->assertArrayHasKey( 'label', $field, "$context should have a 'label' key." );
				$this->assertArrayHasKey( 'value', $field, "$context should have a 'value' key." );
			}
		}
	}

	/**
	 * Test that fields from Abstract_Collector have all default keys.
	 */
	public function test_abstract_collector_fields_have_all_defaults() {
		$expected_keys = array( 'label', 'value', 'debug', 'private', 'status', 'description', 'recommended', 'export_label' );

		foreach ( $this->collectors as $collector ) {
			if ( ! $collector instanceof Abstract_Collector ) {
				continue;
			}
			$fields = $collector->collect();
			foreach ( $fields as $index => $field ) {
				foreach ( $expected_keys as $key ) {
					$this->assertArrayHasKey(
						$key,
						$field,
						"Collector '{$collector->get_id()}', field $index should have '$key' key."
					);
				}
			}
		}
	}

	/**
	 * Test that field status values are valid.
	 */
	public function test_field_status_values_are_valid() {
		$valid_statuses = array( 'good', 'warning', 'critical', 'info' );

		foreach ( $this->collectors as $collector ) {
			if ( ! $collector instanceof Abstract_Collector ) {
				continue;
			}
			$fields = $collector->collect();
			foreach ( $fields as $index => $field ) {
				if ( isset( $field['status'] ) ) {
					$this->assertContains(
						$field['status'],
						$valid_statuses,
						"Collector '{$collector->get_id()}', field $index has invalid status '{$field['status']}'."
					);
				}
			}
		}
	}

	/**
	 * Test that field values are strings.
	 */
	public function test_field_values_are_strings() {
		foreach ( $this->collectors as $collector ) {
			if ( ! $collector instanceof Abstract_Collector ) {
				continue;
			}
			$fields = $collector->collect();
			foreach ( $fields as $index => $field ) {
				$this->assertIsString(
					$field['value'],
					"Collector '{$collector->get_id()}', field $index 'value' should be a string."
				);
			}
		}
	}

	/**
	 * Test that the WordPress Environment collector returns expected fields.
	 */
	public function test_wordpress_environment_has_expected_fields() {
		$collector = $this->collectors['wordpress_environment'];
		$fields    = $collector->collect();
		$labels    = wp_list_pluck( $fields, 'label' );

		$this->assertContains( 'Home URL', $labels );
		$this->assertContains( 'Site URL', $labels );
		$this->assertContains( 'WordPress Version', $labels );
	}

	/**
	 * Test that the Security collector returns expected fields.
	 */
	public function test_security_has_expected_fields() {
		$collector = $this->collectors['security'];
		$fields    = $collector->collect();
		$labels    = wp_list_pluck( $fields, 'label' );

		$this->assertContains( 'Secure Connection (HTTPS)', $labels );
		$this->assertContains( 'File Editing Disabled', $labels );
	}

	/**
	 * Test that the Server Environment collector returns PHP version.
	 */
	public function test_server_environment_includes_php_version() {
		$collector = $this->collectors['server_environment'];
		$fields    = $collector->collect();

		$php_field = null;
		foreach ( $fields as $field ) {
			if ( 'PHP Version' === $field['label'] ) {
				$php_field = $field;
				break;
			}
		}

		$this->assertNotNull( $php_field, 'Server Environment should include PHP Version field.' );
		$this->assertSame( phpversion(), $php_field['value'] );
	}

	/**
	 * Test that caching works for collectors with cache keys.
	 */
	public function test_collector_caching() {
		$collector = $this->collectors['active_plugins'];

		// Clear any existing cache.
		delete_transient( 'sr_active_plugins' );

		// First call should set the cache.
		$data1 = $collector->get_cached_data();
		$this->assertIsArray( $data1 );

		// Cache should now be set.
		$cached = get_transient( 'sr_active_plugins' );
		$this->assertNotFalse( $cached );

		// Second call should return cached data.
		$data2 = $collector->get_cached_data();
		$this->assertEquals( $data1, $data2 );

		// Cleanup.
		delete_transient( 'sr_active_plugins' );
	}

	/**
	 * Test that the cache TTL filter works.
	 */
	public function test_cache_ttl_filter() {
		$collector = $this->collectors['active_plugins'];
		delete_transient( 'sr_active_plugins' );

		$custom_ttl = null;
		add_filter(
			'wp_system_report_cache_ttl',
			function ( $ttl, $key ) use ( &$custom_ttl ) {
				$custom_ttl = 300;
				return $custom_ttl;
			},
			10,
			2
		);

		$collector->get_cached_data();
		$this->assertSame( 300, $custom_ttl );

		delete_transient( 'sr_active_plugins' );
	}

	/**
	 * Test that the Cron_Health collector handles float-string timestamps without
	 * triggering a PHP 8.1+ deprecation notice for implicit float-to-int conversion.
	 *
	 * WordPress stores cron timestamps as floats in some environments. The
	 * `doing_cron` transient in particular is a microtime float-string such as
	 * "1773429002.8655860424041748046875". Passing that raw string value to
	 * gmdate() or human_time_diff() would trigger:
	 *   PHP Deprecated: Implicit conversion from float-string "..." to int loses precision
	 *
	 * @see https://github.com/chrisfromthelc/wp-system-report/issues/95
	 */
	public function test_cron_health_handles_float_string_timestamps() {
		$collector = $this->collectors['cron_health'];

		// Simulate the doing_cron transient with a float-string microtime value,
		// exactly as WordPress core sets it via microtime( true ).
		$float_timestamp = '1773429002.8655860424041748046875';
		set_transient( 'doing_cron', $float_timestamp );

		// Capture deprecation notices — PHPUnit converts them to errors on PHP 8.1+
		// when running with a strict error handler. The test will fail if the
		// collector issues a deprecation notice during collect().
		$fields = $collector->collect();

		// Verify collect() ran successfully and returned an array.
		$this->assertIsArray( $fields );

		// Verify that a Last Cron Run field was produced (meaning the truthy
		// float-string transient value was processed without fatal or deprecation).
		$labels = wp_list_pluck( $fields, 'label' );
		$this->assertContains( 'Last Cron Run', $labels, 'Cron_Health should produce a Last Cron Run field when doing_cron transient is set.' );

		// The debug value for Last Cron Run should be a formatted date string,
		// confirming that gmdate() received a valid int (not a raw float-string).
		$last_run_field = null;
		foreach ( $fields as $field ) {
			if ( 'Last Cron Run' === $field['label'] ) {
				$last_run_field = $field;
				break;
			}
		}
		$this->assertNotNull( $last_run_field );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			$last_run_field['debug'],
			'Last Cron Run debug value should be a Y-m-d H:i:s formatted date string.'
		);

		// Cleanup.
		delete_transient( 'doing_cron' );
	}

	/**
	 * Test that the Cron_Health collector handles float-string keys in the cron
	 * array (next run timestamp) without triggering PHP 8.1+ deprecation notices.
	 *
	 * @see https://github.com/chrisfromthelc/wp-system-report/issues/95
	 */
	public function test_cron_health_handles_float_string_cron_array_keys() {
		$collector = $this->collectors['cron_health'];

		// Build a synthetic cron array with a float-string timestamp key.
		// This mirrors what _get_cron_array() can return in some WordPress installs.
		$float_timestamp = '9999999999.123456789';
		$fake_cron       = array(
			$float_timestamp => array(
				'my_future_hook' => array(
					md5( '' ) => array(
						'schedule' => 'hourly',
						'args'     => array(),
						'interval' => HOUR_IN_SECONDS,
					),
				),
			),
		);

		// Temporarily filter _get_cron_array() to return the synthetic array.
		add_filter(
			'pre_option_cron',
			static function () use ( $fake_cron ) {
				return $fake_cron;
			}
		);

		$fields = $collector->collect();

		remove_all_filters( 'pre_option_cron' );

		$this->assertIsArray( $fields );

		// The Next Cron Run field must be present and must not be "No scheduled events".
		$next_run_field = null;
		foreach ( $fields as $field ) {
			if ( 'Next Cron Run' === $field['label'] ) {
				$next_run_field = $field;
				break;
			}
		}
		$this->assertNotNull( $next_run_field );
		$this->assertNotSame(
			'No scheduled events',
			$next_run_field['value'],
			'Next Cron Run should not be "No scheduled events" when a future float-key event exists.'
		);

		// The debug value should be a properly formatted Y-m-d H:i:s date string,
		// confirming that gmdate() received an int, not a raw float-string.
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			$next_run_field['debug'],
			'Next Cron Run debug value should be a Y-m-d H:i:s formatted date string.'
		);
	}
}
