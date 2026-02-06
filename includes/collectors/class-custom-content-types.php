<?php
/**
 * Custom Content Types collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects custom post types, taxonomies, image sizes, and shortcodes.
 */
class Custom_Content_Types extends Abstract_Collector {

	/**
	 * Get the collector ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'custom_content_types';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Custom Content Types', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Custom post types, taxonomies, image sizes, and shortcodes.', 'system-report' );
	}

	/**
	 * Get the collector priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 150;
	}

	/**
	 * Collect the data.
	 *
	 * @return array
	 */
	public function collect() {
		global $shortcode_tags, $wp_registered_sidebars;

		$fields = array();

		// Custom Post Types.
		$custom_post_types = get_post_types( array( '_builtin' => false ), 'objects' );
		$cpt_list          = array();

		foreach ( $custom_post_types as $post_type ) {
			$cpt_list[] = sprintf( '%s (%s)', $post_type->name, $post_type->label );
		}

		$fields[] = $this->make_field(
			__( 'Custom Post Types', 'system-report' ),
			! empty( $cpt_list ) ? implode( ', ', $cpt_list ) : __( 'None', 'system-report' )
		);

		// Custom Taxonomies.
		$custom_taxonomies = get_taxonomies( array( '_builtin' => false ), 'objects' );
		$taxonomy_list     = array();

		foreach ( $custom_taxonomies as $taxonomy ) {
			$taxonomy_list[] = sprintf( '%s (%s)', $taxonomy->name, $taxonomy->label );
		}

		$fields[] = $this->make_field(
			__( 'Custom Taxonomies', 'system-report' ),
			! empty( $taxonomy_list ) ? implode( ', ', $taxonomy_list ) : __( 'None', 'system-report' )
		);

		// Registered Image Sizes.
		$image_sizes = wp_get_registered_image_subsizes();
		$size_list   = array();

		foreach ( $image_sizes as $size_name => $size_data ) {
			$size_list[] = sprintf(
				'%s (%dx%d)',
				$size_name,
				$size_data['width'],
				$size_data['height']
			);
		}

		$fields[] = $this->make_field(
			__( 'Registered Image Sizes', 'system-report' ),
			! empty( $size_list ) ? implode( ', ', $size_list ) : __( 'None', 'system-report' )
		);

		// Registered Shortcodes.
		$shortcodes = ! empty( $shortcode_tags ) ? array_keys( $shortcode_tags ) : array();

		$fields[] = $this->make_field(
			__( 'Registered Shortcodes', 'system-report' ),
			! empty( $shortcodes ) ? implode( ', ', $shortcodes ) : __( 'None', 'system-report' )
		);

		// Active Sidebars.
		$sidebars      = ! empty( $wp_registered_sidebars ) ? $wp_registered_sidebars : array();
		$sidebar_count = count( $sidebars );
		$sidebar_list  = array();

		foreach ( $sidebars as $sidebar ) {
			$sidebar_list[] = sprintf(
				'%s (%s)',
				$sidebar['id'],
				! empty( $sidebar['name'] ) ? $sidebar['name'] : $sidebar['id']
			);
		}

		$fields[] = $this->make_field(
			__( 'Active Sidebars', 'system-report' ),
			sprintf(
				/* translators: 1: sidebar count, 2: sidebar list */
				__( '%1$d: %2$s', 'system-report' ),
				$sidebar_count,
				! empty( $sidebar_list ) ? implode( ', ', $sidebar_list ) : __( 'None', 'system-report' )
			)
		);

		return $fields;
	}
}
