<?php
/**
 * Autoload optimizer fixer.
 *
 * @package SystemReport
 */

namespace SystemReport\Fixers;

use SystemReport\Fixer;
use SystemReport\Fix_Result;
use SystemReport\Risk_Level;

defined( 'ABSPATH' ) || exit;

/**
 * Optimizes the wp_options autoload column by disabling autoload
 * for options whose serialized values exceed a configurable threshold.
 *
 * WordPress autoloads every option flagged with autoload='yes' on
 * every page request. Large options (often transient data, plugin
 * caches, or serialized settings) can dramatically increase memory
 * usage and slow response times. This fixer switches oversized
 * options to autoload='no' so they are loaded on demand instead.
 */
class Autoload_Optimizer implements Fixer {

	/**
	 * Default minimum byte threshold for an option to be considered bloated.
	 *
	 * Options with a serialized value equal to or larger than this size
	 * will be switched to autoload='no'.
	 *
	 * @var int
	 */
	private const DEFAULT_THRESHOLD = 100 * 1024; // 100 KB.

	/**
	 * WordPress core options that must always be autoloaded.
	 *
	 * These options are loaded early in the WordPress bootstrap process
	 * and disabling autoload could break the site.
	 *
	 * @var string[]
	 */
	private const PROTECTED_OPTIONS = array(
		'siteurl',
		'home',
		'blogname',
		'blogdescription',
		'users_can_register',
		'admin_email',
		'start_of_week',
		'use_balanceTags',
		'use_smilies',
		'require_name_email',
		'comments_notify',
		'posts_per_rss',
		'rss_use_excerpt',
		'mailserver_url',
		'mailserver_login',
		'mailserver_pass',
		'mailserver_port',
		'default_category',
		'default_comment_status',
		'default_ping_status',
		'default_pingback_flag',
		'posts_per_page',
		'date_format',
		'time_format',
		'links_updated_date_format',
		'comment_moderation',
		'moderation_notify',
		'permalink_structure',
		'rewrite_rules',
		'hack_file',
		'blog_charset',
		'moderation_keys',
		'active_plugins',
		'category_base',
		'ping_sites',
		'comment_max_links',
		'gmt_offset',
		'default_email_category',
		'recently_edited',
		'template',
		'stylesheet',
		'comment_registration',
		'html_type',
		'default_role',
		'db_version',
		'uploads_use_yearmonth_folders',
		'upload_path',
		'blog_public',
		'default_link_category',
		'show_on_front',
		'tag_base',
		'show_avatars',
		'avatar_rating',
		'upload_url_path',
		'thumbnail_size_w',
		'thumbnail_size_h',
		'thumbnail_crop',
		'medium_size_w',
		'medium_size_h',
		'avatar_default',
		'large_size_w',
		'large_size_h',
		'image_default_link_type',
		'image_default_size',
		'image_default_align',
		'close_comments_for_old_posts',
		'close_comments_days_old',
		'thread_comments',
		'thread_comments_depth',
		'page_comments',
		'comments_per_page',
		'default_comments_page',
		'comment_order',
		'sticky_posts',
		'widget_categories',
		'widget_text',
		'widget_rss',
		'uninstall_plugins',
		'timezone_string',
		'page_for_posts',
		'page_on_front',
		'default_post_format',
		'link_manager_enabled',
		'finished_splitting_shared_terms',
		'site_icon',
		'medium_large_size_w',
		'medium_large_size_h',
		'wp_page_for_privacy_policy',
		'show_comments_cookies_opt_in',
		'admin_email_lifespan',
		'initial_db_version',
		'wp_user_roles',
		'WPLANG',
		'new_admin_email',
		'fresh_site',
		'auto_update_core_dev',
		'auto_update_core_minor',
		'auto_update_core_major',
		'wp_force_deactivated_plugins',
		'wp_attachment_pages_enabled',
	);

	/**
	 * Byte threshold for this instance.
	 */
	private int $threshold;

	/**
	 * Constructor.
	 *
	 * @param int|null $threshold Optional custom threshold in bytes.
	 */
	public function __construct( ?int $threshold = null ) {
		$this->threshold = $threshold ?? self::DEFAULT_THRESHOLD;
	}

	/**
	 * Get the unique slug identifier for this fixer.
	 *
	 * @return string Fixer ID.
	 */
	public function get_id(): string {
		return 'autoload_optimizer';
	}

	/**
	 * Get the human-readable display label.
	 *
	 * @return string Translated display name.
	 */
	public function get_label(): string {
		return __( 'Autoload Optimizer', 'wp-system-report' );
	}

	/**
	 * Get a description of what this fixer does.
	 *
	 * @return string Translated description.
	 */
	public function get_description(): string {
		return __( 'Disables autoload for oversized options to reduce memory usage on every page load.', 'wp-system-report' );
	}

	/**
	 * Get the risk level of this operation.
	 *
	 * @return Risk_Level Risk level enum case.
	 */
	public function get_risk_level(): Risk_Level {
		return Risk_Level::Medium;
	}

	/**
	 * Get the category this fixer belongs to.
	 *
	 * @return string Category slug.
	 */
	public function get_category(): string {
		return 'performance';
	}

	/**
	 * Check if this fix is applicable.
	 *
	 * Returns true when at least one non-protected autoloaded option
	 * exceeds the configured threshold.
	 *
	 * @return bool Whether the fix can be applied.
	 */
	public function can_fix(): bool {
		$bloated = $this->get_bloated_options();
		return count( $bloated ) > 0;
	}

	/**
	 * Execute the autoload optimization.
	 *
	 * Identifies all non-protected autoloaded options exceeding the
	 * threshold and switches them to autoload='no'.
	 *
	 * @return Fix_Result Result with before/after snapshots.
	 */
	public function fix(): Fix_Result {
		$bloated = $this->get_bloated_options();

		if ( empty( $bloated ) ) {
			return Fix_Result::success(
				__( 'No oversized autoloaded options found. Nothing to optimize.', 'wp-system-report' )
			);
		}

		$before = array(
			'total_autoload_size' => $this->get_total_autoload_size(),
			'bloated_options'     => $this->format_options_snapshot( $bloated ),
			'bloated_count'       => count( $bloated ),
		);

		$optimized = array();
		$errors    = array();

		foreach ( $bloated as $option ) {
			$result = $this->disable_autoload( $option->option_name );

			if ( $result ) {
				$optimized[] = $option->option_name;
			} else {
				$errors[] = sprintf(
					/* translators: %s: option name */
					__( 'Failed to update autoload for: %s', 'wp-system-report' ),
					$option->option_name
				);
			}
		}

		// Clear the alloptions cache so WordPress picks up the changes.
		wp_cache_delete( 'alloptions', 'options' );

		$after = array(
			'total_autoload_size' => $this->get_total_autoload_size(),
			'optimized_options'   => $optimized,
			'optimized_count'     => count( $optimized ),
		);

		if ( ! empty( $errors ) ) {
			return Fix_Result::failure(
				sprintf(
					/* translators: 1: number of successful optimizations, 2: number of failures */
					__( 'Partially completed: %1$d option(s) optimized, %2$d failed.', 'wp-system-report' ),
					count( $optimized ),
					count( $errors )
				),
				$errors
			);
		}

		return Fix_Result::success(
			sprintf(
				/* translators: %d: number of options optimized */
				__( 'Successfully disabled autoload for %d oversized option(s).', 'wp-system-report' ),
				count( $optimized )
			),
			$before,
			$after
		);
	}

	/**
	 * Get the threshold in bytes.
	 *
	 * @return int Current threshold.
	 */
	public function get_threshold(): int {
		/**
		 * Filter the autoload optimization threshold.
		 *
		 * @param int $threshold Minimum byte size for an option to be considered bloated.
		 */
		return (int) apply_filters( 'wp_system_report_autoload_threshold', $this->threshold );
	}

	/**
	 * Query for autoloaded options that exceed the threshold.
	 *
	 * @return object[] Array of database row objects with option_name and val_size properties.
	 */
	private function get_bloated_options(): array {
		global $wpdb;

		$threshold = $this->get_threshold();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic query, no suitable cache.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, LENGTH(option_value) AS val_size
				FROM {$wpdb->options}
				WHERE autoload IN ('yes', 'on')
				AND LENGTH(option_value) >= %d
				ORDER BY val_size DESC",
				$threshold
			)
		);

		if ( ! $results ) {
			return array();
		}

		return array_filter(
			$results,
			function ( object $row ): bool {
				return ! $this->is_protected( $row->option_name );
			}
		);
	}

	/**
	 * Check if an option name is protected from optimization.
	 *
	 * @param string $option_name Option name to check.
	 * @return bool True if the option must not be modified.
	 */
	private function is_protected( string $option_name ): bool {
		if ( in_array( $option_name, self::PROTECTED_OPTIONS, true ) ) {
			return true;
		}

		/**
		 * Filter whether an option should be protected from autoload optimization.
		 *
		 * @param bool   $is_protected Whether the option is protected.
		 * @param string $option_name  The option name being checked.
		 */
		return (bool) apply_filters( 'wp_system_report_autoload_protected', false, $option_name );
	}

	/**
	 * Disable autoload for a single option.
	 *
	 * @param string $option_name Option to modify.
	 * @return bool True on success, false on failure.
	 */
	private function disable_autoload( string $option_name ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted update, no cache.
		$result = $wpdb->update(
			$wpdb->options,
			array( 'autoload' => 'no' ),
			array( 'option_name' => $option_name ),
			array( '%s' ),
			array( '%s' )
		);

		return false !== $result;
	}

	/**
	 * Get the total size of all autoloaded option values.
	 *
	 * @return int Total size in bytes.
	 */
	private function get_total_autoload_size(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic query, no suitable cache.
		$size = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value))
			FROM {$wpdb->options}
			WHERE autoload IN ('yes', 'on')"
		);

		return (int) $size;
	}

	/**
	 * Format a list of bloated options for a snapshot.
	 *
	 * @param object[] $options Array of option row objects.
	 * @return array<string, int> Associative array of option_name => size_in_bytes.
	 */
	private function format_options_snapshot( array $options ): array {
		$snapshot = array();

		foreach ( $options as $option ) {
			$snapshot[ $option->option_name ] = (int) $option->val_size;
		}

		return $snapshot;
	}
}
