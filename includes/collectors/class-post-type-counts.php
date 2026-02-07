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
 * Collect the data.
 */
	public function collect(): array {
		global $wpdb;

		$data = array();

		// Query post type counts. Table name is from $wpdb->posts (safe).
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; table name from $wpdb->posts.
		$results = $wpdb->get_results( "SELECT post_type, COUNT(1) as count FROM {$wpdb->posts} GROUP BY post_type ORDER BY count DESC" );

		if ( $results ) {
			foreach ( $results as $row ) {
				$post_type       = $row->post_type;
				$count           = $row->count;
				$post_type_label = $this->get_post_type_label( $post_type );

				$data[] = $this->make_field(
					$post_type_label,
					number_format_i18n( $count )
				);
			}
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
