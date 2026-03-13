<?php
/**
 * GitHub Updater tests.
 *
 * @package SystemReport
 */

use SystemReport\GitHub_Updater;

/**
 * Test the GitHub_Updater class.
 */
class GitHubUpdaterTest extends WP_UnitTestCase {

	/**
	 * GitHub updater instance.
	 *
	 * @var GitHub_Updater
	 */
	private $updater;

	/**
	 * Mock release data.
	 *
	 * @var array
	 */
	private $mock_release;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->updater = new GitHub_Updater( WP_SYSTEM_REPORT_FILE );

		$this->mock_release = array(
			'tag_name'     => '2.0.0',
			'html_url'     => 'https://github.com/chrisfromthelc/wp-system-report/releases/tag/2.0.0',
			'published_at' => '2026-02-06T00:00:00Z',
			'body'         => '## Changelog\n\n* New feature\n* Bug fix',
			'zipball_url'  => 'https://api.github.com/repos/chrisfromthelc/wp-system-report/zipball/2.0.0',
			'assets'       => array(
				array(
					'name'                 => 'wp-system-report.zip',
					'browser_download_url' => 'https://github.com/chrisfromthelc/wp-system-report/releases/download/2.0.0/wp-system-report.zip',
				),
			),
		);
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		delete_transient( 'sr_github_update' );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	// ---- Hook Registration ----

	/**
	 * Test that the update_plugins_github.com filter is registered.
	 */
	public function test_update_uri_filter_registered(): void {
		$this->assertIsInt( has_filter( 'update_plugins_github.com', array( $this->updater, 'check_update' ) ) );
	}

	/**
	 * Test that the plugins_api filter is registered.
	 */
	public function test_plugins_api_filter_registered(): void {
		$this->assertIsInt( has_filter( 'plugins_api', array( $this->updater, 'plugin_info' ) ) );
	}

	/**
	 * Test that the upgrader_process_complete action is registered.
	 */
	public function test_clear_cache_action_registered(): void {
		$this->assertIsInt( has_action( 'upgrader_process_complete', array( $this->updater, 'clear_update_cache' ) ) );
	}

	// ---- Update Check ----

	/**
	 * Test check_update returns false when the current version is up to date.
	 */
	public function test_check_update_returns_false_when_current(): void {
		$this->mock_release['tag_name'] = WP_SYSTEM_REPORT_VERSION;
		$this->mock_github_api( $this->mock_release );

		$result = $this->updater->check_update(
			false,
			array( 'Version' => WP_SYSTEM_REPORT_VERSION ),
			plugin_basename( WP_SYSTEM_REPORT_FILE ),
			array()
		);

		$this->assertFalse( $result );
	}

	/**
	 * Test check_update returns update data when a newer version exists.
	 */
	public function test_check_update_returns_update_when_newer(): void {
		$this->mock_github_api( $this->mock_release );

		$result = $this->updater->check_update(
			false,
			array( 'Version' => '1.0.0' ),
			plugin_basename( WP_SYSTEM_REPORT_FILE ),
			array()
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'wp-system-report', $result['slug'] );
		$this->assertSame( '2.0.0', $result['version'] );
		$this->assertSame( $this->mock_release['html_url'], $result['url'] );
		$this->assertSame(
			'https://github.com/chrisfromthelc/wp-system-report/releases/download/2.0.0/wp-system-report.zip',
			$result['package']
		);
	}

	/**
	 * Test check_update passes through for other plugins.
	 */
	public function test_check_update_skips_other_plugins(): void {
		$result = $this->updater->check_update(
			false,
			array( 'Version' => '1.0.0' ),
			'some-other-plugin/some-other-plugin.php',
			array()
		);

		$this->assertFalse( $result );
	}

	/**
	 * Test check_update handles GitHub API failure gracefully.
	 */
	public function test_check_update_handles_api_failure(): void {
		$this->mock_github_api_failure();

		$result = $this->updater->check_update(
			false,
			array( 'Version' => '1.0.0' ),
			plugin_basename( WP_SYSTEM_REPORT_FILE ),
			array()
		);

		$this->assertFalse( $result );
	}

	/**
	 * Test check_update caches the GitHub API response.
	 */
	public function test_check_update_caches_response(): void {
		$request_count = 0;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$request_count ) {
				if ( false !== strpos( $url, 'api.github.com' ) ) {
					++$request_count;
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode( $this->mock_release ),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$plugin_file = plugin_basename( WP_SYSTEM_REPORT_FILE );
		$plugin_data = array( 'Version' => '1.0.0' );

		// First call hits the API.
		$this->updater->check_update( false, $plugin_data, $plugin_file, array() );
		$this->assertSame( 1, $request_count );

		// Second call should use the transient cache.
		$this->updater->check_update( false, $plugin_data, $plugin_file, array() );
		$this->assertSame( 1, $request_count );
	}

	/**
	 * Test check_update strips the 'v' prefix from tag names.
	 */
	public function test_check_update_strips_v_prefix(): void {
		$this->mock_release['tag_name'] = 'v2.0.0';
		$this->mock_github_api( $this->mock_release );

		$result = $this->updater->check_update(
			false,
			array( 'Version' => '1.0.0' ),
			plugin_basename( WP_SYSTEM_REPORT_FILE ),
			array()
		);

		$this->assertIsArray( $result );
		$this->assertSame( '2.0.0', $result['version'] );
	}

	/**
	 * Test check_update falls back to zipball URL when no asset is attached.
	 */
	public function test_check_update_falls_back_to_zipball(): void {
		$this->mock_release['assets'] = array();
		$this->mock_github_api( $this->mock_release );

		$result = $this->updater->check_update(
			false,
			array( 'Version' => '1.0.0' ),
			plugin_basename( WP_SYSTEM_REPORT_FILE ),
			array()
		);

		$this->assertIsArray( $result );
		$this->assertSame( $this->mock_release['zipball_url'], $result['package'] );
	}

	// ---- Plugin Info ----

	/**
	 * Test plugin_info returns an object for our slug.
	 */
	public function test_plugin_info_returns_object_for_our_slug(): void {
		$this->mock_github_api( $this->mock_release );

		$args       = new \stdClass();
		$args->slug = 'wp-system-report';

		$result = $this->updater->plugin_info( false, 'plugin_information', $args );

		$this->assertInstanceOf( \stdClass::class, $result );
		$this->assertSame( 'WP System Report', $result->name );
		$this->assertSame( 'wp-system-report', $result->slug );
		$this->assertSame( '2.0.0', $result->version );
		$this->assertSame( '8.1', $result->requires_php );
		$this->assertSame( '6.2', $result->requires );
		$this->assertArrayHasKey( 'description', $result->sections );
		$this->assertArrayHasKey( 'changelog', $result->sections );
		$this->assertNotEmpty( $result->download_link );
	}

	/**
	 * Test plugin_info ignores other plugin slugs.
	 */
	public function test_plugin_info_ignores_other_slugs(): void {
		$args       = new \stdClass();
		$args->slug = 'some-other-plugin';

		$result = $this->updater->plugin_info( false, 'plugin_information', $args );

		$this->assertFalse( $result );
	}

	/**
	 * Test plugin_info ignores non-plugin_information actions.
	 */
	public function test_plugin_info_ignores_other_actions(): void {
		$args       = new \stdClass();
		$args->slug = 'wp-system-report';

		$result = $this->updater->plugin_info( false, 'query_plugins', $args );

		$this->assertFalse( $result );
	}

	// ---- Cache ----

	/**
	 * Test clear_update_cache deletes the transient.
	 */
	public function test_clear_update_cache_deletes_transient(): void {
		set_transient( 'sr_github_update', $this->mock_release, HOUR_IN_SECONDS );
		$this->assertNotFalse( get_transient( 'sr_github_update' ) );

		$this->updater->clear_update_cache();
		$this->assertFalse( get_transient( 'sr_github_update' ) );
	}

	// ---- Helpers ----

	/**
	 * Mock the GitHub API to return successful release data.
	 *
	 * @param array $release_data Release data to return.
	 */
	private function mock_github_api( array $release_data ): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $release_data ) {
				if ( false !== strpos( $url, 'api.github.com' ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode( $release_data ),
					);
				}
				return $preempt;
			},
			10,
			3
		);
	}

	/**
	 * Mock the GitHub API to return a failure.
	 */
	private function mock_github_api_failure(): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( false !== strpos( $url, 'api.github.com' ) ) {
					return new \WP_Error( 'http_request_failed', 'Connection timed out.' );
				}
				return $preempt;
			},
			10,
			3
		);
	}
}
