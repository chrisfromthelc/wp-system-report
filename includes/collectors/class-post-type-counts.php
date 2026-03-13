<?php
/**
 * Post Type Counts collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects number of published entries for each post type.
 */
class Post_Type_Counts extends Abstract_Collector {

	/**
	 * Get the transient cache key.
	 *
	 * @return string Cache key.
	 */
	protected function get_cache_key(): string {
		return 'sr_post_type_counts';
	}

	/**
	 * Get the collector ID.
	 */
	public function get_id(): string {
		return 'post_type_counts';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Post Type Counts', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Number of published entries for each post type.', 'wp-system-report' );
	}

	/**
 * Get the collector priority.
 */
	public function get_priority(): int {
		return 40;
	}

	/**
	 * Statuses considered "published" for count purposes.
	 *
	 * Auto-draft, trash, and inherit (attachment revisions) are excluded
	 * because they do not represent real published content.
	 *
	 * @var string[]
	 */
	private const COUNTED_STATUSES = array( 'publish', 'private', 'future', 'pending', 'draft' );

	/**
 * Collect the data.
 */
	public function collect(): array {
		$data = array();

		// Retrieve all registered post types (public and non-public).
		$post_types = get_post_types( array(), 'names' );

		foreach ( $post_types as $post_type ) {
			$counts = wp_count_posts( $post_type );

			if ( ! $counts ) {
				continue;
			}

			// Sum only the statuses that represent real content.
			$total = 0;
			foreach ( self::COUNTED_STATUSES as $status ) {
				$total += isset( $counts->$status ) ? (int) $counts->$status : 0;
			}

			// Skip post types with no content at all.
			if ( 0 === $total ) {
				continue;
			}

			$data[] = $this->make_field(
				$this->get_post_type_label( $post_type ),
				number_format_i18n( $total )
			);
		}

		return $data;
	}

	/**
	 * Get a human-readable label for a post type.
	 *
	 * @param string $post_type The post type name.
	 * @return string The post type label.
	 */
	private function get_post_type_label( $post_type ) {
		$post_type_object = get_post_type_object( $post_type );

		if ( $post_type_object && isset( $post_type_object->labels->name ) ) {
			return $post_type_object->labels->name;
		}

		return ucfirst( $post_type );
	}
}
