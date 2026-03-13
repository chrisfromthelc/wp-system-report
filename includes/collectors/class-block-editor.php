<?php
/**
 * Block Editor collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

use SystemReport\Status;
use WP_Block_Type_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Collects block types, block patterns, FSE/block theme status,
 * template hierarchy, and editor performance indicators.
 */
class Block_Editor extends Abstract_Collector {

	/**
	 * Cached result of WP_Block_Type_Registry::get_all_registered().
	 *
	 * Populated once during collect() and reused by all sub-methods
	 * to avoid redundant registry lookups.
	 *
	 * @var \WP_Block_Type[]|null
	 */
	private ?array $registered_blocks = null;

	/**
	 * Get the transient cache key.
	 *
	 * @return string Cache key.
	 */
	protected function get_cache_key(): string {
		return 'sr_block_editor';
	}

	/**
	 * Get the collector ID.
	 */
	public function get_id(): string {
		return 'block_editor';
	}

	/**
	 * Get the collector label.
	 */
	public function get_label(): string {
		return __( 'Block Editor', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 */
	public function get_description(): string {
		return __( 'Registered block types, block patterns, FSE/block theme detection, and editor performance indicators.', 'wp-system-report' );
	}

	/**
	 * Get the collector priority.
	 */
	public function get_priority(): int {
		return 230;
	}

	/**
	 * Collect block editor data.
	 *
	 * @return array Array of Field objects.
	 */
	public function collect(): array {
		// Cache the registered-blocks list once for all sub-methods.
		$this->registered_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();

		$data = array();

		$data[] = $this->collect_block_theme();
		$data[] = $this->collect_registered_block_types();
		$data[] = $this->collect_block_sources();
		$data[] = $this->collect_registered_patterns();
		$data[] = $this->collect_pattern_categories();
		$data[] = $this->collect_template_parts();
		$data[] = $this->collect_global_styles();
		$data[] = $this->collect_block_directory();
		$data[] = $this->collect_editor_performance();
		$data[] = $this->collect_classic_editor();

		return $data;
	}

	/**
	 * Detect whether the active theme is a block/FSE theme.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_block_theme() {
		$is_block_theme = wp_is_block_theme();
		$theme          = wp_get_theme();
		$value          = $is_block_theme
			/* translators: %s: theme name */
			? sprintf( __( 'Yes (%s)', 'wp-system-report' ), $theme->get( 'Name' ) )
			/* translators: %s: theme name */
			: sprintf( __( 'No (%s)', 'wp-system-report' ), $theme->get( 'Name' ) );

		return $this->make_field(
			__( 'Block Theme (FSE)', 'wp-system-report' ),
			$value,
			array(
				'status'      => $is_block_theme ? Status::Good : Status::Info,
				'description' => __( 'Whether the active theme is a Full Site Editing (block) theme with templates and template parts.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Count total registered block types.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_registered_block_types() {
		$blocks = $this->registered_blocks ?? WP_Block_Type_Registry::get_instance()->get_all_registered();
		$count  = count( $blocks );

		$status = Status::Good;
		if ( $count > 500 ) {
			$status = Status::Warning;
		} elseif ( $count > 300 ) {
			$status = Status::Info;
		}

		return $this->make_field(
			__( 'Registered Block Types', 'wp-system-report' ),
			number_format_i18n( $count ),
			array(
				'status'      => $status,
				'description' => __( 'Total number of registered block types. A very high count may impact editor load time.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Break down block types by source (core, plugin, theme).
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_block_sources() {
		$blocks = $this->registered_blocks ?? WP_Block_Type_Registry::get_instance()->get_all_registered();

		$core_count   = 0;
		$plugin_count = 0;
		$theme_count  = 0;
		$other_count  = 0;

		foreach ( $blocks as $block ) {
			$name = $block->name;
			if ( str_starts_with( $name, 'core/' ) ) {
				++$core_count;
			} elseif ( $this->is_theme_block( $block ) ) {
				++$theme_count;
			} elseif ( str_contains( $name, '/' ) ) {
				++$plugin_count;
			} else {
				++$other_count;
			}
		}

		$parts = array();
		/* translators: %d: number of core blocks */
		$parts[] = sprintf( __( 'Core: %d', 'wp-system-report' ), $core_count );
		/* translators: %d: number of plugin blocks */
		$parts[] = sprintf( __( 'Plugin: %d', 'wp-system-report' ), $plugin_count );
		/* translators: %d: number of theme blocks */
		$parts[] = sprintf( __( 'Theme: %d', 'wp-system-report' ), $theme_count );
		if ( $other_count > 0 ) {
			/* translators: %d: number of other blocks */
			$parts[] = sprintf( __( 'Other: %d', 'wp-system-report' ), $other_count );
		}

		return $this->make_field(
			__( 'Block Sources', 'wp-system-report' ),
			implode( ', ', $parts ),
			array(
				'status'      => Status::Info,
				'description' => __( 'Breakdown of registered block types by source.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Count registered block patterns.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_registered_patterns() {
		$patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		$count    = count( $patterns );

		return $this->make_field(
			__( 'Registered Block Patterns', 'wp-system-report' ),
			number_format_i18n( $count ),
			array(
				'status'      => Status::Info,
				'description' => __( 'Total number of block patterns registered from core, plugins, and the active theme.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Count registered pattern categories.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_pattern_categories() {
		$categories = \WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered();
		$count      = count( $categories );

		return $this->make_field(
			__( 'Pattern Categories', 'wp-system-report' ),
			number_format_i18n( $count ),
			array(
				'status'      => Status::Info,
				'description' => __( 'Total number of registered block pattern categories.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Count template parts for block themes.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_template_parts() {
		if ( ! wp_is_block_theme() ) {
			return $this->make_field(
				__( 'Template Parts', 'wp-system-report' ),
				__( 'N/A (classic theme)', 'wp-system-report' ),
				array(
					'status'      => Status::Info,
					'description' => __( 'Number of template parts available in the block theme.', 'wp-system-report' ),
				)
			);
		}

		$template_parts = get_block_templates( array(), 'wp_template_part' );
		$count          = is_array( $template_parts ) ? count( $template_parts ) : 0;

		return $this->make_field(
			__( 'Template Parts', 'wp-system-report' ),
			number_format_i18n( $count ),
			array(
				'status'      => Status::Info,
				'description' => __( 'Number of template parts available in the block theme.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Check for global styles (theme.json) support.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_global_styles() {
		$theme           = wp_get_theme();
		$theme_json_path = $theme->get_stylesheet_directory() . '/theme.json';
		$has_theme_json  = file_exists( $theme_json_path );

		if ( ! $has_theme_json ) {
			return $this->make_field(
				__( 'Global Styles (theme.json)', 'wp-system-report' ),
				__( 'Not present', 'wp-system-report' ),
				array(
					'status'      => Status::Info,
					'description' => __( 'Whether the active theme uses theme.json for global styles and settings.', 'wp-system-report' ),
				)
			);
		}

		// Use WP_Theme_JSON_Resolver when available (WP 5.8+) to read the
		// merged theme.json data instead of raw file_get_contents().
		if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			$theme_json = \WP_Theme_JSON_Resolver::get_theme_data();
			$raw_data   = $theme_json->get_data();
			$version    = $raw_data['version'] ?? __( 'unknown', 'wp-system-report' );

			return $this->make_field(
				__( 'Global Styles (theme.json)', 'wp-system-report' ),
				/* translators: %s: theme.json schema version */
				sprintf( __( 'Present (v%s)', 'wp-system-report' ), $version ),
				array(
					'status'      => Status::Good,
					'description' => __( 'Whether the active theme uses theme.json for global styles and settings.', 'wp-system-report' ),
				)
			);
		}

		// Fallback for older WordPress versions without WP_Theme_JSON_Resolver.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Fallback for pre-5.8 WordPress.
		$contents = file_get_contents( $theme_json_path );
		if ( false === $contents ) {
			return $this->make_field(
				__( 'Global Styles (theme.json)', 'wp-system-report' ),
				__( 'Present (unreadable)', 'wp-system-report' ),
				array(
					'status'      => Status::Warning,
					'description' => __( 'Whether the active theme uses theme.json for global styles and settings.', 'wp-system-report' ),
				)
			);
		}

		$json    = json_decode( $contents, true );
		$version = $json['version'] ?? __( 'unknown', 'wp-system-report' );

		return $this->make_field(
			__( 'Global Styles (theme.json)', 'wp-system-report' ),
			/* translators: %s: theme.json schema version */
			sprintf( __( 'Present (v%s)', 'wp-system-report' ), $version ),
			array(
				'status'      => Status::Good,
				'description' => __( 'Whether the active theme uses theme.json for global styles and settings.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Check if the Block Directory is enabled.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_block_directory() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter.
		$enabled = apply_filters( 'should_load_remote_block_patterns', true );

		return $this->make_field(
			__( 'Remote Block Patterns', 'wp-system-report' ),
			$this->format_boolean( $enabled ),
			array(
				'status'      => Status::Info,
				'description' => __( 'Whether remote block patterns from the WordPress.org pattern directory are loaded.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect editor performance indicators.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_editor_performance() {
		$blocks      = $this->registered_blocks ?? WP_Block_Type_Registry::get_instance()->get_all_registered();
		$block_count = count( $blocks );

		$patterns      = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		$pattern_count = count( $patterns );

		$issues = array();

		if ( $block_count > 400 ) {
			/* translators: %d: number of blocks */
			$issues[] = sprintf( __( '%d block types registered', 'wp-system-report' ), $block_count );
		}

		if ( $pattern_count > 200 ) {
			/* translators: %d: number of patterns */
			$issues[] = sprintf( __( '%d patterns registered', 'wp-system-report' ), $pattern_count );
		}

		// Check for render_callback on many blocks (can slow server-side rendering).
		$dynamic_count = 0;
		foreach ( $blocks as $block ) {
			if ( $block->is_dynamic() ) {
				++$dynamic_count;
			}
		}

		if ( $dynamic_count > 100 ) {
			/* translators: %d: number of dynamic blocks */
			$issues[] = sprintf( __( '%d dynamic blocks', 'wp-system-report' ), $dynamic_count );
		}

		if ( empty( $issues ) ) {
			return $this->make_field(
				__( 'Editor Performance', 'wp-system-report' ),
				__( 'No concerns detected', 'wp-system-report' ),
				array(
					'status'      => Status::Good,
					'description' => __( 'Flags potential editor performance issues from excessive block or pattern registrations.', 'wp-system-report' ),
				)
			);
		}

		return $this->make_field(
			__( 'Editor Performance', 'wp-system-report' ),
			implode( '; ', $issues ),
			array(
				'status'      => Status::Warning,
				'description' => __( 'Flags potential editor performance issues from excessive block or pattern registrations.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Detect if Classic Editor plugin is active.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_classic_editor() {
		$classic_editor_active = is_plugin_active( 'classic-editor/classic-editor.php' );
		$disable_gutenberg     = is_plugin_active( 'disable-gutenberg/disable-gutenberg.php' );

		if ( $classic_editor_active || $disable_gutenberg ) {
			$plugin = $classic_editor_active ? 'Classic Editor' : 'Disable Gutenberg';

			return $this->make_field(
				__( 'Classic Editor Override', 'wp-system-report' ),
				/* translators: %s: plugin name */
				sprintf( __( 'Active (%s)', 'wp-system-report' ), $plugin ),
				array(
					'status'      => Status::Info,
					'description' => __( 'Whether a plugin is overriding the block editor with the classic editor.', 'wp-system-report' ),
				)
			);
		}

		return $this->make_field(
			__( 'Classic Editor Override', 'wp-system-report' ),
			__( 'Not active', 'wp-system-report' ),
			array(
				'status'      => Status::Good,
				'description' => __( 'Whether a plugin is overriding the block editor with the classic editor.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Determine if a block type originates from the active theme.
	 *
	 * @param \WP_Block_Type $block Block type instance.
	 * @return bool True if the block appears to be from the theme.
	 */
	private function is_theme_block( \WP_Block_Type $block ): bool {
		// Blocks with a file reference inside the theme directory.
		if ( ! empty( $block->editor_script_handles ) ) {
			$theme_dir = get_stylesheet_directory();
			foreach ( $block->editor_script_handles as $handle ) {
				$src = wp_scripts()->query( $handle );
				if ( $src && str_contains( $src->src, $theme_dir ) ) {
					return true;
				}
			}
		}

		// Block name prefixed with the theme's text domain.
		$theme_slug = wp_get_theme()->get( 'TextDomain' );
		if ( $theme_slug && str_starts_with( $block->name, $theme_slug . '/' ) ) {
			return true;
		}

		return false;
	}
}
