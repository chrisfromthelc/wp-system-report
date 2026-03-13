<?php
/**
 * WordPress Environment collector tests.
 *
 * @package SystemReport
 */

use SystemReport\Collectors\WordPress_Environment;
use SystemReport\Field;
use SystemReport\Status;

/**
 * Test the WordPress_Environment collector.
 */
class WordPressEnvironmentTest extends WP_UnitTestCase {

	/**
	 * Collector under test.
	 *
	 * @var WordPress_Environment
	 */
	private WordPress_Environment $collector;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->collector = new WordPress_Environment();
	}

	// -------------------------------------------------------
	// Collector identity tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector returns the correct ID.
	 */
	public function test_collector_id(): void {
		$this->assertSame( 'wordpress_environment', $this->collector->get_id() );
	}

	/**
	 * Test that the collector returns a non-empty label.
	 */
	public function test_collector_label(): void {
		$this->assertNotEmpty( $this->collector->get_label() );
		$this->assertIsString( $this->collector->get_label() );
	}

	/**
	 * Test that the collector returns the correct priority.
	 */
	public function test_collector_priority(): void {
		$this->assertSame( 10, $this->collector->get_priority() );
	}

	// -------------------------------------------------------
	// collect() return type tests.
	// -------------------------------------------------------

	/**
	 * Test that collect() returns an array of Field objects.
	 */
	public function test_collect_returns_array_of_field_objects(): void {
		$fields = $this->collector->collect();

		$this->assertIsArray( $fields );
		$this->assertNotEmpty( $fields );

		foreach ( $fields as $field ) {
			$this->assertInstanceOf( Field::class, $field );
		}
	}

	// -------------------------------------------------------
	// Individual field tests.
	// -------------------------------------------------------

	/**
	 * Test that the "Home URL" field is present.
	 */
	public function test_home_url_field_present(): void {
		$field = $this->find_field( 'Home URL' );

		$this->assertNotNull( $field, 'Expected "Home URL" field to be present.' );
		$this->assertNotEmpty( $field->value );
	}

	/**
	 * Test that the "Home URL" field is marked as private.
	 */
	public function test_home_url_is_private(): void {
		$field = $this->find_field( 'Home URL' );

		$this->assertNotNull( $field, 'Expected "Home URL" field to be present.' );
		$this->assertTrue( $field->private, 'Home URL should be marked private.' );
	}

	/**
	 * Test that the "Site URL" field is marked as private.
	 */
	public function test_site_url_is_private(): void {
		$field = $this->find_field( 'Site URL' );

		$this->assertNotNull( $field, 'Expected "Site URL" field to be present.' );
		$this->assertTrue( $field->private, 'Site URL should be marked private.' );
	}

	/**
	 * Test that the "WordPress Version" field is present and contains a version string.
	 */
	public function test_wordpress_version_field_present(): void {
		$field = $this->find_field( 'WordPress Version' );

		$this->assertNotNull( $field, 'Expected "WordPress Version" field to be present.' );
		$this->assertNotEmpty( $field->value );
		// Value should resemble a version string (digits and dots).
		$this->assertMatchesRegularExpression( '/^\d+\.\d+/', $field->value );
	}

	/**
	 * Test that the "WordPress Version" field has a valid status.
	 *
	 * Status depends on whether the latest version is available at test time;
	 * any of Good, Warning, or Info is acceptable.
	 */
	public function test_wordpress_version_has_valid_status(): void {
		$field = $this->find_field( 'WordPress Version' );

		$this->assertNotNull( $field, 'Expected "WordPress Version" field to be present.' );
		$this->assertContains(
			$field->status,
			array( Status::Good, Status::Warning, Status::Info ),
			'WordPress Version status must be Good, Warning, or Info.'
		);
	}

	/**
	 * Test that the "WordPress Cron" field is Status::Good when DISABLE_WP_CRON is not true.
	 *
	 * In the default test environment DISABLE_WP_CRON is either undefined or
	 * false, so the collector should report cron as enabled (Good).
	 */
	public function test_wp_cron_enabled_is_good(): void {
		// Guard: only assert Good when the constant is falsy; the constant
		// cannot be redefined at runtime, so we branch on the actual value.
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

		$field = $this->find_field( 'WordPress Cron' );

		$this->assertNotNull( $field, 'Expected "WordPress Cron" field to be present.' );

		if ( $cron_disabled ) {
			$this->assertSame( Status::Warning, $field->status, 'WordPress Cron should be Warning when DISABLE_WP_CRON is true.' );
		} else {
			$this->assertSame( Status::Good, $field->status, 'WordPress Cron should be Good when DISABLE_WP_CRON is not set.' );
		}
	}

	/**
	 * Test that the "Language" field is present.
	 */
	public function test_language_field_present(): void {
		$field = $this->find_field( 'Language' );

		$this->assertNotNull( $field, 'Expected "Language" field to be present.' );
		$this->assertNotEmpty( $field->value );
	}

	/**
	 * Test that the "Environment Type" field is present.
	 */
	public function test_environment_type_field_present(): void {
		$field = $this->find_field( 'Environment Type' );

		$this->assertNotNull( $field, 'Expected "Environment Type" field to be present.' );
		$this->assertNotEmpty( $field->value );
	}

	/**
	 * Test that search engine visibility is Status::Good when allowed.
	 */
	public function test_search_engine_visibility_allowed_is_good(): void {
		$force_one = static function () {
			return '1';
		};
		add_filter( 'option_blog_public', $force_one );

		$fields = $this->collector->collect();
		$field  = $this->find_field_in( $fields, 'Search Engine Visibility' );

		remove_filter( 'option_blog_public', $force_one );

		$this->assertNotNull( $field, 'Expected "Search Engine Visibility" field to be present.' );
		$this->assertSame( Status::Good, $field->status, 'Search engine visibility should be Good when blog_public is 1.' );
	}

	/**
	 * Test that search engine visibility is Status::Warning when discouraged.
	 */
	public function test_search_engine_visibility_discouraged_is_warning(): void {
		// Use a filter to guarantee the option value is the string '0'.
		// update_option alone can be unreliable in CI environments where
		// the alloptions cache may retain the original value.
		$force_zero = static function () {
			return '0';
		};
		add_filter( 'option_blog_public', $force_zero );

		$fields = $this->collector->collect();
		$field  = $this->find_field_in( $fields, 'Search Engine Visibility' );

		remove_filter( 'option_blog_public', $force_zero );

		$this->assertNotNull( $field, 'Expected "Search Engine Visibility" field to be present.' );
		$this->assertSame( Status::Warning, $field->status, 'Search engine visibility should be Warning when blog_public is 0.' );
		$this->assertStringContainsString( 'Discouraged', $field->value );
	}

	// -------------------------------------------------------
	// Caching tests.
	// -------------------------------------------------------

	/**
	 * Test that the collector has no cache key (caching disabled).
	 */
	public function test_no_caching(): void {
		$reflection = new ReflectionMethod( $this->collector, 'get_cache_key' );
		$reflection->setAccessible( true );

		$cache_key = $reflection->invoke( $this->collector );

		$this->assertNull( $cache_key, 'WordPress_Environment collector should not define a cache key.' );
	}

	// -------------------------------------------------------
	// Helper methods.
	// -------------------------------------------------------

	/**
	 * Find a field by label in a freshly collected result set.
	 *
	 * @param string $label The field label to search for.
	 * @return Field|null The matching Field object, or null if not found.
	 */
	private function find_field( string $label ): ?Field {
		return $this->find_field_in( $this->collector->collect(), $label );
	}

	/**
	 * Find a field by label in a given array of fields.
	 *
	 * @param Field[] $fields Array of Field objects.
	 * @param string  $label  The field label to search for.
	 * @return Field|null The matching Field object, or null if not found.
	 */
	private function find_field_in( array $fields, string $label ): ?Field {
		foreach ( $fields as $field ) {
			if ( $label === $field->label ) {
				return $field;
			}
		}

		return null;
	}
}
