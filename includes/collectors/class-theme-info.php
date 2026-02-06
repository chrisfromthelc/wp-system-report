<?php
/**
 * Theme collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects active theme information including parent and child theme details.
 */
class Theme_Info extends Abstract_Collector {

	/**
	 * Get the collector ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'theme_info';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Theme', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Active theme information including parent and child theme details.', 'system-report' );
	}

	/**
	 * Get the collector priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 90;
	}

	/**
	 * Get the transient cache key.
	 *
	 * @return string
	 */
	protected function get_cache_key() {
		return 'sr_theme_info';
	}

	/**
	 * Collect the data.
	 *
	 * @return array
	 */
	public function collect() {
		// Require update functions if not available.
		if ( ! function_exists( 'get_theme_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$fields = array();

		// Get active theme.
		$theme = wp_get_theme();

		// Theme Name.
		$fields[] = $this->make_field(
			__( 'Theme Name', 'system-report' ),
			$theme->get( 'Name' ),
			array(
				'export_label' => 'Theme Name',
				'status'       => 'info',
			)
		);

		// Theme Version with update check.
		$version        = $theme->get( 'Version' );
		$version_value  = $version;
		$version_status = 'info';

		$theme_updates = get_theme_updates();
		$stylesheet    = $theme->get_stylesheet();

		if ( isset( $theme_updates[ $stylesheet ] ) ) {
			$update_info = $theme_updates[ $stylesheet ];
			if ( isset( $update_info->update['new_version'] ) ) {
				$new_version    = $update_info->update['new_version'];
				$version_value .= sprintf(
					/* translators: %s: New version number */
					__( ' (update available: %s)', 'system-report' ),
					$new_version
				);
				$version_status = 'warning';
			}
		}

		$fields[] = $this->make_field(
			__( 'Theme Version', 'system-report' ),
			$version_value,
			array(
				'export_label' => 'Theme Version',
				'status'       => $version_status,
			)
		);

		// Theme Author.
		$author   = wp_strip_all_tags( $theme->get( 'Author' ) );
		$fields[] = $this->make_field(
			__( 'Theme Author', 'system-report' ),
			$author,
			array(
				'export_label' => 'Theme Author',
				'status'       => 'info',
			)
		);

		// Theme Author URL.
		$author_uri = $theme->get( 'AuthorURI' );
		if ( ! empty( $author_uri ) ) {
			$fields[] = $this->make_field(
				__( 'Theme Author URL', 'system-report' ),
				$author_uri,
				array(
					'export_label' => 'Theme Author URL',
					'status'       => 'info',
				)
			);
		}

		// Is Child Theme.
		$is_child_theme = $theme->parent() !== false;
		$fields[]       = $this->make_field(
			__( 'Is Child Theme', 'system-report' ),
			$this->format_boolean( $is_child_theme ),
			array(
				'export_label' => 'Is Child Theme',
				'status'       => 'info',
			)
		);

		// Is Block Theme.
		$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
		$fields[]       = $this->make_field(
			__( 'Is Block Theme', 'system-report' ),
			$this->format_boolean( $is_block_theme ),
			array(
				'export_label' => 'Is Block Theme',
				'status'       => 'info',
			)
		);

		// Parent theme details if child theme.
		if ( $is_child_theme ) {
			$parent_theme = $theme->parent();

			// Parent Theme Name.
			$fields[] = $this->make_field(
				__( 'Parent Theme Name', 'system-report' ),
				$parent_theme->get( 'Name' ),
				array(
					'export_label' => 'Parent Theme Name',
					'status'       => 'info',
				)
			);

			// Parent Theme Version.
			$fields[] = $this->make_field(
				__( 'Parent Theme Version', 'system-report' ),
				$parent_theme->get( 'Version' ),
				array(
					'export_label' => 'Parent Theme Version',
					'status'       => 'info',
				)
			);

			// Parent Theme Author.
			$parent_author = wp_strip_all_tags( $parent_theme->get( 'Author' ) );
			$fields[]      = $this->make_field(
				__( 'Parent Theme Author', 'system-report' ),
				$parent_author,
				array(
					'export_label' => 'Parent Theme Author',
					'status'       => 'info',
				)
			);
		}

		return $fields;
	}
}
