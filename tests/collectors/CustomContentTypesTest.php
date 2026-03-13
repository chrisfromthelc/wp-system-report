<?php
/**
 * Tests for the Custom_Content_Types collector.
 *
 * @package SystemReport
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Tests for the Custom Content Types collector.
 *
 * The WordPress test environment provides a fully initialised registry for
 * post types, taxonomies, image sizes, shortcodes, and sidebars, which lets
 * these tests exercise both the default-state paths and registration-reflected
 * behaviour without needing to mock anything.
 */
class CustomContentTypesTest extends WP_UnitTestCase {

	/**
	 * Slug used for the test post type created in some test methods.
	 */
	const TEST_POST_TYPE = 'sr_test_cpt';

	/**
	 * Slug used for the test taxonomy created in some test methods.
	 */
	const TEST_TAXONOMY = 'sr_test_tax';

	/**
	 * Collector instance under test.
	 *
	 * @var \SystemReport\Collectors\Custom_Content_Types
	 */
	private $collector;

	/**
	 * Set up the collector before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->collector = new \SystemReport\Collectors\Custom_Content_Types();
	}

	/**
	 * Unregister any post types or taxonomies this suite registered.
	 */
	public function tear_down() {
		if ( post_type_exists( self::TEST_POST_TYPE ) ) {
			unregister_post_type( self::TEST_POST_TYPE );
		}

		if ( taxonomy_exists( self::TEST_TAXONOMY ) ) {
			unregister_taxonomy( self::TEST_TAXONOMY );
		}

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Identity contracts
	// -------------------------------------------------------------------------

	/**
	 * The collector must identify itself with the canonical ID string.
	 */
	public function test_collector_id() {
		$this->assertSame( 'custom_content_types', $this->collector->get_id() );
	}

	/**
	 * The collector must return a non-empty human-readable label.
	 */
	public function test_collector_label() {
		$label = $this->collector->get_label();
		$this->assertIsString( $label );
		$this->assertNotEmpty( $label );
	}

	/**
	 * The collector priority must be 150.
	 */
	public function test_collector_priority() {
		$this->assertSame( 150, $this->collector->get_priority() );
	}

	// -------------------------------------------------------------------------
	// Field count contract
	// -------------------------------------------------------------------------

	/**
	 * collect() must return exactly five Field objects in the correct order.
	 */
	public function test_collect_returns_exactly_five_fields() {
		$fields = $this->collector->collect();

		$this->assertCount( 5, $fields, 'Custom Content Types collector must return exactly 5 fields.' );

		foreach ( $fields as $index => $field ) {
			$this->assertInstanceOf(
				Field::class,
				$field,
				"Item at index $index must be a Field instance."
			);
		}
	}

	// -------------------------------------------------------------------------
	// Individual field label contracts
	// -------------------------------------------------------------------------

	/**
	 * The first field must be the Custom Post Types field.
	 */
	public function test_custom_post_types_field() {
		$fields = $this->collector->collect();
		$field  = $fields[0];

		$this->assertStringContainsString(
			'Custom Post Types',
			$field->label,
			'First field label must contain "Custom Post Types".'
		);
	}

	/**
	 * The second field must be the Custom Taxonomies field.
	 */
	public function test_custom_taxonomies_field() {
		$fields = $this->collector->collect();
		$field  = $fields[1];

		$this->assertStringContainsString(
			'Custom Taxonomies',
			$field->label,
			'Second field label must contain "Custom Taxonomies".'
		);
	}

	/**
	 * The third field must be the Registered Image Sizes field, and its value
	 * must mention at least the three default WP image sizes.
	 */
	public function test_image_sizes_field() {
		$fields = $this->collector->collect();
		$field  = $fields[2];

		$this->assertStringContainsString(
			'Image Sizes',
			$field->label,
			'Third field label must contain "Image Sizes".'
		);

		// WordPress always registers thumbnail, medium, and large by default.
		foreach ( array( 'thumbnail', 'medium', 'large' ) as $size ) {
			$this->assertStringContainsString(
				$size,
				$field->value,
				"Image Sizes field value must mention the default '$size' size."
			);
		}
	}

	/**
	 * The fourth field must be the Registered Shortcodes field.
	 */
	public function test_shortcodes_field() {
		$fields = $this->collector->collect();
		$field  = $fields[3];

		$this->assertStringContainsString(
			'Shortcodes',
			$field->label,
			'Fourth field label must contain "Shortcodes".'
		);
	}

	/**
	 * The fifth field must be the Active Sidebars field.
	 */
	public function test_sidebars_field() {
		$fields = $this->collector->collect();
		$field  = $fields[4];

		$this->assertStringContainsString(
			'Sidebars',
			$field->label,
			'Fifth field label must contain "Sidebars".'
		);
	}

	// -------------------------------------------------------------------------
	// Status contract
	// -------------------------------------------------------------------------

	/**
	 * Every field must carry the default Status::Info status.
	 */
	public function test_all_fields_have_info_status() {
		$fields = $this->collector->collect();

		foreach ( $fields as $index => $field ) {
			$this->assertSame(
				Status::Info,
				$field->status,
				"Field at index $index must carry Status::Info."
			);
		}
	}

	// -------------------------------------------------------------------------
	// Dynamic registration tests
	// -------------------------------------------------------------------------

	/**
	 * Registering a custom post type must be reflected in the first field value.
	 *
	 * The source formats each entry as "{slug} ({label})", so we assert on
	 * the slug which is always present.
	 */
	public function test_custom_post_type_registration_reflected() {
		register_post_type(
			self::TEST_POST_TYPE,
			array(
				'label'  => 'SR Test CPT',
				'public' => true,
			)
		);

		$fields = $this->collector->collect();
		$field  = $fields[0];

		$this->assertStringContainsString(
			self::TEST_POST_TYPE,
			$field->value,
			'Custom Post Types field value must contain the newly registered post type slug.'
		);
	}

	/**
	 * Registering a custom taxonomy must be reflected in the second field value.
	 *
	 * The source formats each entry as "{slug} ({label})", so we assert on
	 * the slug which is always present.
	 */
	public function test_custom_taxonomy_registration_reflected() {
		register_taxonomy(
			self::TEST_TAXONOMY,
			'post',
			array(
				'label'  => 'SR Test Taxonomy',
				'public' => true,
			)
		);

		$fields = $this->collector->collect();
		$field  = $fields[1];

		$this->assertStringContainsString(
			self::TEST_TAXONOMY,
			$field->value,
			'Custom Taxonomies field value must contain the newly registered taxonomy slug.'
		);
	}

	// -------------------------------------------------------------------------
	// Caching contract
	// -------------------------------------------------------------------------

	/**
	 * The Custom Content Types collector has no cache key.
	 * get_cached_data() must still return valid data without writing a transient.
	 */
	public function test_no_caching() {
		$data = $this->collector->get_cached_data();

		$this->assertIsArray( $data );
		$this->assertCount( 5, $data, 'get_cached_data() must return the same 5 fields.' );

		// Confirm via reflection that the class truly exposes no cache key.
		$reflection = new ReflectionMethod( $this->collector, 'get_cache_key' );
		$reflection->setAccessible( true );
		$cache_key = $reflection->invoke( $this->collector );

		$this->assertNull(
			$cache_key,
			'Custom_Content_Types::get_cache_key() must return null (no caching).'
		);
	}
}
