<?php
/**
 * Theme collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

use SystemReport\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Collects active theme information including parent and child theme details.
 */
class Theme_Info extends Abstract_Collector {

	/**
 * Get the collector ID.
 */
	public function get_id(): string {
		return 'theme_info';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Theme', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Active theme information including parent and child theme details.', 'wp-system-report' );
	}

	/**
 * Get the collector priority.
 */
	public function get_priority(): int {
		return 90;
	}

	/**
 * Get the transient cache key.
 */
	protected function get_cache_key(): string {
		return 'sr_theme_info';
	}

	/**
 * Collect the data.
 */
	public function collect(): array {
		// Require update functions if not available.
		if ( ! function_exists( 'get_theme_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$fields = array();

		// Get active theme.
		$theme = wp_get_theme();

		// Theme Name.
		$fields[] = $this->make_field(
			__( 'Theme Name', 'wp-system-report' ),
			$theme->get( 'Name' ),
			array(
				'export_label' => 'Theme Name',
				'status'       => Status::Info,
			)
		);

		// Theme Version with update check.
		$version        = $theme->get( 'Version' );
		$version_value  = $version;
		$version_status = Status::Info;

		$theme_updates = get_theme_updates();
		$stylesheet    = $theme->get_stylesheet();

		if ( isset( $theme_updates[ $stylesheet ] ) ) {
			$update_info = $theme_updates[ $stylesheet ];
			if ( isset( $update_info->update['new_version'] ) ) {
				$new_version    = $update_info->update['new_version'];
				$version_value .= sprintf(
					/* translators: %s: New version number */
					__( ' (update available: %s)', 'wp-system-report' ),
					$new_version
				);
				$version_status = Status::Warning;
			}
		}

		$fields[] = $this->make_field(
			__( 'Theme Version', 'wp-system-report' ),
			$version_value,
			array(
				'export_label' => 'Theme Version',
				'status'       => $version_status,
			)
		);

		// Theme Author.
		$author   = wp_strip_all_tags( $theme->get( 'Author' ) );
		$fields[] = $this->make_field(
			__( 'Theme Author', 'wp-system-report' ),
			$author,
			array(
				'export_label' => 'Theme Author',
				'status'       => Status::Info,
			)
		);

		// Theme Author URL.
		$author_uri = $theme->get( 'AuthorURI' );
		if ( ! empty( $author_uri ) ) {
			$fields[] = $this->make_field(
				__( 'Theme Author URL', 'wp-system-report' ),
				$author_uri,
				array(
					'export_label' => 'Theme Author URL',
					'status'       => Status::Info,
				)
			);
		}

		// Is Child Theme.
		$is_child_theme = $theme->parent() !== false;
		$fields[]       = $this->make_field(
			__( 'Is Child Theme', 'wp-system-report' ),
			$this->format_boolean( $is_child_theme ),
			array(
				'export_label' => 'Is Child Theme',
				'status'       => Status::Info,
			)
		);

		// Is Block Theme.
		$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
		$fields[]       = $this->make_field(
			__( 'Is Block Theme', 'wp-system-report' ),
			$this->format_boolean( $is_block_theme ),
			array(
				'export_label' => 'Is Block Theme',
				'status'       => Status::Info,
			)
		);

		// Parent theme details if child theme.
		if ( $is_child_theme ) {
			$parent_theme = $theme->parent();

			// Parent Theme Name.
			$fields[] = $this->make_field(
				__( 'Parent Theme Name', 'wp-system-report' ),
				$parent_theme->get( 'Name' ),
				array(
					'export_label' => 'Parent Theme Name',
					'status'       => Status::Info,
				)
			);

			// Parent Theme Version.
			$fields[] = $this->make_field(
				__( 'Parent Theme Version', 'wp-system-report' ),
				$parent_theme->get( 'Version' ),
				array(
					'export_label' => 'Parent Theme Version',
					'status'       => Status::Info,
				)
			);

			// Parent Theme Author.
			$parent_author = wp_strip_all_tags( $parent_theme->get( 'Author' ) );
			$fields[]      = $this->make_field(
				__( 'Parent Theme Author', 'wp-system-report' ),
				$parent_author,
				array(
					'export_label' => 'Parent Theme Author',
					'status'       => Status::Info,
				)
			);
		}

		return $fields;
	}
}
