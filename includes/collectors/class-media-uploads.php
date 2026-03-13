<?php
/**
 * Media & Uploads collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

use SystemReport\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Collects media library statistics, upload directory health,
 * image processing capabilities, and upload limit alignment.
 */
class Media_Uploads extends Abstract_Collector {

	/**
	 * Get the transient cache key.
	 *
	 * @return string Cache key.
	 */
	protected function get_cache_key(): string {
		return 'sr_media_uploads';
	}

	/**
	 * Get the collector ID.
	 */
	public function get_id(): string {
		return 'media_uploads';
	}

	/**
	 * Get the collector label.
	 */
	public function get_label(): string {
		return __( 'Media & Uploads', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 */
	public function get_description(): string {
		return __( 'Media library statistics, upload directory health, image processing, and upload limits.', 'wp-system-report' );
	}

	/**
	 * Get the collector priority.
	 */
	public function get_priority(): int {
		return 190;
	}

	/**
	 * Collect media and upload data.
	 *
	 * @return array Array of Field objects.
	 */
	public function collect(): array {
		$data = array();

		$data[] = $this->collect_upload_directory();
		$data[] = $this->collect_upload_dir_writable();
		$data[] = $this->collect_upload_dir_size();
		$data[] = $this->collect_total_attachments();
		$data[] = $this->collect_media_by_type();
		$data[] = $this->collect_orphaned_attachments();
		$data[] = $this->collect_upload_limit_alignment();
		$data[] = $this->collect_max_file_upload();
		$data[] = $this->collect_image_editor();
		$data[] = $this->collect_registered_image_sizes();
		$data[] = $this->collect_big_image_threshold();

		return $data;
	}

	/**
	 * Collect upload directory path.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_upload_directory() {
		$upload_dir = wp_upload_dir();
		$basedir    = $upload_dir['basedir'];

		$status = Status::Info;
		if ( ! empty( $upload_dir['error'] ) ) {
			$status = Status::Critical;
		}

		return $this->make_field(
			__( 'Upload Directory', 'wp-system-report' ),
			$basedir,
			array(
				'status'      => $status,
				'description' => ! empty( $upload_dir['error'] )
					? $upload_dir['error']
					: __( 'Base directory for media uploads.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect upload directory writability.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_upload_dir_writable() {
		$upload_dir = wp_upload_dir();
		$writable   = wp_is_writable( $upload_dir['basedir'] );

		return $this->make_field(
			__( 'Upload Dir Writable', 'wp-system-report' ),
			$this->format_boolean( $writable ),
			array(
				'status'      => $writable ? Status::Good : Status::Critical,
				'description' => __( 'Whether PHP can write to the uploads directory.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect upload directory size.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_upload_dir_size() {
		$upload_dir = wp_upload_dir();
		$basedir    = $upload_dir['basedir'];

		$truncated = false;
		$size      = $this->get_directory_size( $basedir, $truncated );

		$status = Status::Info;
		if ( $size > GB_IN_BYTES * 10 ) {
			$status = Status::Warning;
		}

		$formatted = $this->format_size( $size );
		if ( $truncated ) {
			/* translators: %s: formatted file size */
			$formatted = sprintf( __( '%s+ (estimate, file cap reached)', 'wp-system-report' ), $formatted );
		}

		return $this->make_field(
			__( 'Upload Directory Size', 'wp-system-report' ),
			$formatted,
			array(
				'status'      => $status,
				'debug'       => $truncated ? $size . ' (truncated)' : $size,
				'description' => $truncated
					? __( 'Partial total — directory exceeds 50 000 files.', 'wp-system-report' )
					: __( 'Total size of all files in the uploads directory.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect total attachment count.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_total_attachments() {
		$count = (int) wp_count_posts( 'attachment' )->inherit;

		return $this->make_field(
			__( 'Total Attachments', 'wp-system-report' ),
			number_format_i18n( $count ),
			array(
				'status'      => Status::Info,
				'description' => __( 'Total number of media library items.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect media counts grouped by MIME type category.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_media_by_type() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time diagnostic query.
		$results = $wpdb->get_results(
			"SELECT
				CASE
					WHEN post_mime_type LIKE 'image/%' THEN 'Images'
					WHEN post_mime_type LIKE 'video/%' THEN 'Videos'
					WHEN post_mime_type LIKE 'audio/%' THEN 'Audio'
					WHEN post_mime_type LIKE 'application/pdf' THEN 'PDFs'
					ELSE 'Other'
				END AS media_category,
				COUNT(1) AS count
			FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			GROUP BY media_category
			ORDER BY count DESC"
		);

		$parts = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$parts[] = $row->media_category . ': ' . number_format_i18n( (int) $row->count );
			}
		}

		$value = ! empty( $parts ) ? implode( ', ', $parts ) : __( 'None', 'wp-system-report' );

		return $this->make_field(
			__( 'Media by Type', 'wp-system-report' ),
			$value,
			array(
				'status'      => Status::Info,
				'description' => __( 'Breakdown of media library items by MIME type category.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect orphaned attachment count.
	 *
	 * Orphaned attachments are those with a parent post ID that
	 * no longer exists in the posts table.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_orphaned_attachments() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time diagnostic query.
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(1)
			FROM {$wpdb->posts} a
			WHERE a.post_type = 'attachment'
			AND a.post_parent > 0
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->posts} p
				WHERE p.ID = a.post_parent
			)"
		);

		$status = Status::Good;
		if ( $count > 100 ) {
			$status = Status::Warning;
		} elseif ( $count > 0 ) {
			$status = Status::Info;
		}

		return $this->make_field(
			__( 'Orphaned Attachments', 'wp-system-report' ),
			number_format_i18n( $count ),
			array(
				'status'      => $status,
				'description' => __( 'Attachments whose parent post no longer exists.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect upload limit alignment check.
	 *
	 * Compares upload_max_filesize and post_max_size to flag
	 * misconfigurations where upload_max_filesize exceeds post_max_size.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_upload_limit_alignment() {
		$upload_max = $this->convert_to_bytes( (string) ini_get( 'upload_max_filesize' ) );
		$post_max   = $this->convert_to_bytes( (string) ini_get( 'post_max_size' ) );

		$aligned = $upload_max <= $post_max;
		$value   = ini_get( 'upload_max_filesize' ) . ' / ' . ini_get( 'post_max_size' );

		$status = Status::Good;
		if ( ! $aligned ) {
			$status = Status::Warning;
		}

		return $this->make_field(
			__( 'Upload / Post Max Alignment', 'wp-system-report' ),
			$value,
			array(
				'status'      => $status,
				'description' => __( 'upload_max_filesize vs post_max_size. upload_max_filesize should not exceed post_max_size.', 'wp-system-report' ),
				'recommended' => __( 'upload_max_filesize <= post_max_size', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect WordPress maximum file upload size.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_max_file_upload() {
		$max_upload = wp_max_upload_size();

		$status = Status::Info;
		if ( $max_upload < 2 * MB_IN_BYTES ) {
			$status = Status::Warning;
		}

		return $this->make_field(
			__( 'WP Max Upload Size', 'wp-system-report' ),
			$this->format_size( $max_upload ),
			array(
				'status'      => $status,
				'description' => __( 'Effective maximum upload size after applying WordPress and PHP limits.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect the active image editor.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_image_editor() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter.
		$editors = apply_filters( 'wp_image_editors', array( 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' ) );

		$active_editor = __( 'None available', 'wp-system-report' );
		$status        = Status::Critical;

		foreach ( $editors as $editor_class ) {
			if ( class_exists( $editor_class ) && call_user_func( array( $editor_class, 'test' ) ) ) {
				$short_name    = str_replace( 'WP_Image_Editor_', '', $editor_class );
				$active_editor = $short_name;
				$status        = 'Imagick' === $short_name ? Status::Good : Status::Info;
				break;
			}
		}

		return $this->make_field(
			__( 'Image Editor', 'wp-system-report' ),
			$active_editor,
			array(
				'status'      => $status,
				'description' => __( 'The image processing library used for thumbnail generation and image manipulation.', 'wp-system-report' ),
				'recommended' => 'Imagick',
			)
		);
	}

	/**
	 * Collect registered image sizes.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_registered_image_sizes() {
		$sizes        = wp_get_registered_image_subsizes();
		$size_count   = count( $sizes );
		$custom_count = $size_count - 4; // Subtract core sizes: thumbnail, medium, medium_large, large.
		$custom_count = max( 0, $custom_count );

		$value = sprintf(
			/* translators: 1: total image sizes, 2: custom image sizes */
			__( '%1$d total (%2$d custom)', 'wp-system-report' ),
			$size_count,
			$custom_count
		);

		$status = Status::Info;
		if ( $size_count > 15 ) {
			$status = Status::Warning;
		}

		return $this->make_field(
			__( 'Registered Image Sizes', 'wp-system-report' ),
			$value,
			array(
				'status'      => $status,
				'description' => __( 'Total registered image sizes. Many custom sizes can slow media uploads.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect the big image size threshold.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_big_image_threshold() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter.
		$threshold = (int) apply_filters( 'big_image_size_threshold', 2560 );

		$status = Status::Info;
		if ( 0 === $threshold ) {
			$status = Status::Warning;
		}

		$value = 0 === $threshold
			? __( 'Disabled', 'wp-system-report' )
			: sprintf( '%dpx', $threshold );

		return $this->make_field(
			__( 'Big Image Threshold', 'wp-system-report' ),
			$value,
			array(
				'status'      => $status,
				'description' => __( 'Images exceeding this width/height are scaled down on upload. 0 means disabled.', 'wp-system-report' ),
				'recommended' => '2560px',
			)
		);
	}

	/**
	 * Maximum number of files to count before aborting directory size calculation.
	 *
	 * Prevents runaway I/O on enormous upload directories. When exceeded the
	 * method returns the partial total accumulated so far.
	 */
	private const MAX_FILES_TO_COUNT = 50000;

	/**
	 * Calculate the total size of a directory recursively.
	 *
	 * Caps the walk at {@see MAX_FILES_TO_COUNT} files to avoid unbounded
	 * I/O on very large upload directories.
	 *
	 * @param string $path      Directory path.
	 * @param bool   $truncated Optional. Set to true by reference when the cap is reached.
	 * @return int Size in bytes (may be a partial total if the cap is hit).
	 */
	private function get_directory_size( string $path, bool &$truncated = false ): int {
		$truncated = false;

		if ( ! is_dir( $path ) ) {
			return 0;
		}

		$size  = 0;
		$count = 0;

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$size += $file->getSize();
				++$count;

				if ( $count >= self::MAX_FILES_TO_COUNT ) {
					$truncated = true;
					break;
				}
			}
		}

		return $size;
	}
}
