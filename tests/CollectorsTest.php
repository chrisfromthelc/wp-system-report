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
			'email_delivery',
			'media_uploads',
			'performance',
			'update_health',
			'block_editor',
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
	 * Test that the Email Delivery collector returns expected fields.
	 */
	public function test_email_delivery_has_expected_fields() {
		$collector = $this->collectors['email_delivery'];
		$fields    = $collector->collect();
		$labels    = wp_list_pluck( $fields, 'label' );

		$this->assertContains( 'Admin Email', $labels );
		$this->assertContains( 'From Address', $labels );
		$this->assertContains( 'From Name', $labels );
		$this->assertContains( 'Mail Transport', $labels );
		$this->assertContains( 'SMTP Host', $labels );
		$this->assertContains( 'SMTP Port', $labels );
		$this->assertContains( 'SMTP Encryption', $labels );
		$this->assertContains( 'Mail Plugin', $labels );
		$this->assertContains( 'PHPMailer Override', $labels );
		$this->assertContains( 'Sendmail Path', $labels );
		$this->assertContains( 'PHP mail() Disabled', $labels );
	}

	/**
	 * Test that the Email Delivery collector marks admin email as private.
	 */
	public function test_email_delivery_admin_email_is_private() {
		$collector = $this->collectors['email_delivery'];
		$fields    = $collector->collect();

		$admin_field = null;
		foreach ( $fields as $field ) {
			if ( 'Admin Email' === $field['label'] ) {
				$admin_field = $field;
				break;
			}
		}

		$this->assertNotNull( $admin_field, 'Email Delivery should include Admin Email field.' );
		$this->assertTrue( $admin_field['private'], 'Admin Email should be marked as private.' );
	}

	/**
	 * Test that the Email Delivery collector caching works.
	 */
	public function test_email_delivery_caching() {
		$collector = $this->collectors['email_delivery'];

		delete_transient( 'sr_email_delivery' );

		$data1 = $collector->get_cached_data();
		$this->assertIsArray( $data1 );

		$cached = get_transient( 'sr_email_delivery' );
		$this->assertNotFalse( $cached );

		delete_transient( 'sr_email_delivery' );
	}

	/**
	 * Test that the Media & Uploads collector returns expected fields.
	 */
	public function test_media_uploads_has_expected_fields() {
		$collector = $this->collectors['media_uploads'];
		$fields    = $collector->collect();
		$labels    = wp_list_pluck( $fields, 'label' );

		$this->assertContains( 'Upload Directory', $labels );
		$this->assertContains( 'Upload Dir Writable', $labels );
		$this->assertContains( 'Upload Directory Size', $labels );
		$this->assertContains( 'Total Attachments', $labels );
		$this->assertContains( 'Media by Type', $labels );
		$this->assertContains( 'Orphaned Attachments', $labels );
		$this->assertContains( 'Upload / Post Max Alignment', $labels );
		$this->assertContains( 'WP Max Upload Size', $labels );
		$this->assertContains( 'Image Editor', $labels );
		$this->assertContains( 'Registered Image Sizes', $labels );
		$this->assertContains( 'Big Image Threshold', $labels );
	}

	/**
	 * Test that the Media & Uploads collector reports correct upload directory.
	 */
	public function test_media_uploads_upload_directory_matches() {
		$collector  = $this->collectors['media_uploads'];
		$fields     = $collector->collect();
		$upload_dir = wp_upload_dir();

		$dir_field = null;
		foreach ( $fields as $field ) {
			if ( 'Upload Directory' === $field['label'] ) {
				$dir_field = $field;
				break;
			}
		}

		$this->assertNotNull( $dir_field, 'Media & Uploads should include Upload Directory field.' );
		$this->assertSame( $upload_dir['basedir'], $dir_field['value'] );
	}

	/**
	 * Test that the Media & Uploads collector caching works.
	 */
	public function test_media_uploads_caching() {
		$collector = $this->collectors['media_uploads'];

		delete_transient( 'sr_media_uploads' );

		$data1 = $collector->get_cached_data();
		$this->assertIsArray( $data1 );

		$cached = get_transient( 'sr_media_uploads' );
		$this->assertNotFalse( $cached );

		delete_transient( 'sr_media_uploads' );
	}

	/**
	 * Test that the Performance collector returns expected fields.
	 */
	public function test_performance_has_expected_fields() {
		$collector = $this->collectors['performance'];
		$fields    = $collector->collect();
		$labels    = wp_list_pluck( $fields, 'label' );

		$this->assertContains( 'Object Cache Backend', $labels );
		$this->assertContains( 'Object Cache Drop-in', $labels );
		$this->assertContains( 'Page Cache Plugin', $labels );
		$this->assertContains( 'OPcache', $labels );
		$this->assertContains( 'Total wp_options Rows', $labels );
		$this->assertContains( 'wp_options Table Size', $labels );
		$this->assertContains( 'Expired Transients', $labels );
		$this->assertContains( 'Database Overhead', $labels );
		$this->assertContains( 'Top Autoloaded Options', $labels );
		$this->assertContains( 'Persistent Object Cache', $labels );
	}

	/**
	 * Test that the Performance collector marks Top Autoloaded Options as private.
	 */
	public function test_performance_autoloaded_options_is_private() {
		$collector = $this->collectors['performance'];
		$fields    = $collector->collect();

		$target_field = null;
		foreach ( $fields as $field ) {
			if ( 'Top Autoloaded Options' === $field['label'] ) {
				$target_field = $field;
				break;
			}
		}

		$this->assertNotNull( $target_field, 'Performance should include Top Autoloaded Options field.' );
		$this->assertTrue( $target_field['private'], 'Top Autoloaded Options should be marked as private.' );
	}

	/**
	 * Test that the Performance collector caching works.
	 */
	public function test_performance_caching() {
		$collector = $this->collectors['performance'];

		delete_transient( 'sr_performance' );

		$data1 = $collector->get_cached_data();
		$this->assertIsArray( $data1 );

		$cached = get_transient( 'sr_performance' );
		$this->assertNotFalse( $cached );

		delete_transient( 'sr_performance' );
	}

	/**
	 * Test that the Update Health collector returns expected fields.
	 */
	public function test_update_health_has_expected_fields() {
		$collector = $this->collectors['update_health'];
		$fields    = $collector->collect();
		$labels    = wp_list_pluck( $fields, 'label' );

		$this->assertContains( 'Core Update Status', $labels );
		$this->assertContains( 'Core Update Channel', $labels );
		$this->assertContains( 'Core Auto-Updates', $labels );
		$this->assertContains( 'Plugin Updates Available', $labels );
		$this->assertContains( 'Plugin Auto-Updates', $labels );
		$this->assertContains( 'Theme Updates Available', $labels );
		$this->assertContains( 'Theme Auto-Updates', $labels );
		$this->assertContains( 'Last Update Check', $labels );
		$this->assertContains( 'Failed Updates', $labels );
		$this->assertContains( 'Translation Updates', $labels );
	}

	/**
	 * Test that the Update Health collector caching works.
	 */
	public function test_update_health_caching() {
		$collector = $this->collectors['update_health'];

		delete_transient( 'sr_update_health' );

		$data1 = $collector->get_cached_data();
		$this->assertIsArray( $data1 );

		$cached = get_transient( 'sr_update_health' );
		$this->assertNotFalse( $cached );

		delete_transient( 'sr_update_health' );
	}

	/**
	 * Test that the Block Editor collector returns expected fields.
	 */
	public function test_block_editor_has_expected_fields() {
		$collector = $this->collectors['block_editor'];
		$fields    = $collector->collect();
		$labels    = wp_list_pluck( $fields, 'label' );

		$this->assertContains( 'Block Theme (FSE)', $labels );
		$this->assertContains( 'Registered Block Types', $labels );
		$this->assertContains( 'Block Sources', $labels );
		$this->assertContains( 'Registered Block Patterns', $labels );
		$this->assertContains( 'Pattern Categories', $labels );
		$this->assertContains( 'Template Parts', $labels );
		$this->assertContains( 'Global Styles (theme.json)', $labels );
		$this->assertContains( 'Remote Block Patterns', $labels );
		$this->assertContains( 'Editor Performance', $labels );
		$this->assertContains( 'Classic Editor Override', $labels );
	}

	/**
	 * Test that the Block Editor collector caching works.
	 */
	public function test_block_editor_caching() {
		$collector = $this->collectors['block_editor'];

		delete_transient( 'sr_block_editor' );

		$data1 = $collector->get_cached_data();
		$this->assertIsArray( $data1 );

		$cached = get_transient( 'sr_block_editor' );
		$this->assertNotFalse( $cached );

		delete_transient( 'sr_block_editor' );
	}
}
