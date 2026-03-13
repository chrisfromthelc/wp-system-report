<?php
/**
 * Performance collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

use SystemReport\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Collects object cache, page cache, OPcache, wp_options health,
 * database overhead, and the largest autoloaded options.
 */
class Performance extends Abstract_Collector {

	/**
	 * Cached wp_options row count.
	 *
	 * Populated by collect_options_row_count() on first use and reused
	 * by collect_persistent_cache_recommended() to avoid a duplicate query.
	 */
	private ?int $options_row_count = null;

	/**
	 * Known page-caching plugins keyed by main file path.
	 *
	 * @var array<string, string>
	 */
	private const KNOWN_CACHE_PLUGINS = array(
		'wp-super-cache/wp-cache.php'                => 'WP Super Cache',
		'w3-total-cache/w3-total-cache.php'          => 'W3 Total Cache',
		'wp-rocket/wp-rocket.php'                    => 'WP Rocket',
		'litespeed-cache/litespeed-cache.php'        => 'LiteSpeed Cache',
		'wp-fastest-cache/wpFastestCache.php'        => 'WP Fastest Cache',
		'cache-enabler/cache-enabler.php'            => 'Cache Enabler',
		'comet-cache/comet-cache.php'                => 'Comet Cache',
		'hummingbird-performance/wp-hummingbird.php' => 'Hummingbird',
		'breeze/breeze.php'                          => 'Breeze',
		'sg-cachepress/sg-cachepress.php'            => 'SG Optimizer',
		'powered-cache/powered-cache.php'            => 'Powered Cache',
		'nitropack/main.php'                         => 'NitroPack',
	);

	/**
	 * Get the transient cache key.
	 *
	 * @return string Cache key.
	 */
	protected function get_cache_key(): string {
		return 'sr_performance';
	}

	/**
	 * Get the collector ID.
	 */
	public function get_id(): string {
		return 'performance';
	}

	/**
	 * Get the collector label.
	 */
	public function get_label(): string {
		return __( 'Performance', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 */
	public function get_description(): string {
		return __( 'Object cache, page cache, OPcache status, wp_options health, and database overhead.', 'wp-system-report' );
	}

	/**
	 * Get the collector priority.
	 */
	public function get_priority(): int {
		return 200;
	}

	/**
	 * Collect performance data.
	 *
	 * @return array Array of Field objects.
	 */
	public function collect(): array {
		$data = array();

		$data[] = $this->collect_object_cache_backend();
		$data[] = $this->collect_object_cache_dropin();
		$data[] = $this->collect_page_cache();
		$data[] = $this->collect_opcache_status();
		$data[] = $this->collect_options_row_count();
		$data[] = $this->collect_options_table_size();
		$data[] = $this->collect_expired_transients();
		$data[] = $this->collect_database_overhead();
		$data[] = $this->collect_top_autoloaded_options();
		$data[] = $this->collect_persistent_cache_recommended();

		return $data;
	}

	/**
	 * Detect the active object cache backend.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_object_cache_backend() {
		$backend = $this->detect_object_cache_backend();

		$status = Status::Info;
		if ( 'Redis' === $backend || 'Memcached' === $backend ) {
			$status = Status::Good;
		} elseif ( 'APCu' === $backend ) {
			$status = Status::Good;
		} elseif ( 'Default (File)' === $backend ) {
			$status = Status::Info;
		}

		return $this->make_field(
			__( 'Object Cache Backend', 'wp-system-report' ),
			$backend,
			array(
				'status'      => $status,
				'description' => __( 'The persistent object cache backend in use.', 'wp-system-report' ),
				'recommended' => 'Redis',
			)
		);
	}

	/**
	 * Check for the object-cache.php drop-in.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_object_cache_dropin() {
		$dropin_path = WP_CONTENT_DIR . '/object-cache.php';
		$exists      = file_exists( $dropin_path );

		return $this->make_field(
			__( 'Object Cache Drop-in', 'wp-system-report' ),
			$this->format_boolean( $exists ),
			array(
				'status'      => $exists ? Status::Good : Status::Info,
				'description' => __( 'Whether an object-cache.php drop-in is installed in wp-content.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Detect active page-caching plugin.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_page_cache() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$detected       = array();

		foreach ( self::KNOWN_CACHE_PLUGINS as $plugin_file => $label ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				$detected[] = $label;
			}
		}

		// Check multisite network-active plugins.
		if ( is_multisite() ) {
			$network_plugins = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
			foreach ( self::KNOWN_CACHE_PLUGINS as $plugin_file => $label ) {
				if ( in_array( $plugin_file, $network_plugins, true ) && ! in_array( $label, $detected, true ) ) {
					$detected[] = $label;
				}
			}
		}

		$value  = ! empty( $detected ) ? implode( ', ', $detected ) : __( 'None detected', 'wp-system-report' );
		$status = ! empty( $detected ) ? Status::Good : Status::Info;

		return $this->make_field(
			__( 'Page Cache Plugin', 'wp-system-report' ),
			$value,
			array(
				'status'      => $status,
				'description' => __( 'Active page-caching plugin(s) detected from a known list.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect PHP OPcache status.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_opcache_status() {
		if ( ! function_exists( 'opcache_get_status' ) ) {
			return $this->make_field(
				__( 'OPcache', 'wp-system-report' ),
				__( 'Not available', 'wp-system-report' ),
				array(
					'status'      => Status::Warning,
					'description' => __( 'PHP OPcache extension is not loaded.', 'wp-system-report' ),
					'recommended' => __( 'Enabled', 'wp-system-report' ),
				)
			);
		}

		// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection,WordPress.PHP.NoSilencedErrors.Discouraged -- opcache_get_status() emits warnings when OPcache is restricted.
		$status_data = @opcache_get_status( false );

		if ( false === $status_data || ! is_array( $status_data ) ) {
			return $this->make_field(
				__( 'OPcache', 'wp-system-report' ),
				__( 'Disabled', 'wp-system-report' ),
				array(
					'status'      => Status::Warning,
					'description' => __( 'OPcache is installed but not enabled.', 'wp-system-report' ),
					'recommended' => __( 'Enabled', 'wp-system-report' ),
				)
			);
		}

		$stats  = $status_data['opcache_statistics'] ?? array();
		$memory = $status_data['memory_usage'] ?? array();

		$hit_rate   = isset( $stats['opcache_hit_rate'] ) ? round( (float) $stats['opcache_hit_rate'], 1 ) . '%' : '?';
		$used_mem   = isset( $memory['used_memory'] ) ? $this->format_size( (int) $memory['used_memory'] ) : '?';
		$wasted_pct = isset( $memory['current_wasted_percentage'] ) ? round( (float) $memory['current_wasted_percentage'], 1 ) . '%' : '0%';

		$value = sprintf(
			/* translators: 1: hit rate, 2: used memory, 3: wasted percentage */
			__( 'Enabled (Hit rate: %1$s, Used: %2$s, Wasted: %3$s)', 'wp-system-report' ),
			$hit_rate,
			$used_mem,
			$wasted_pct
		);

		$field_status = Status::Good;
		if ( isset( $memory['current_wasted_percentage'] ) && $memory['current_wasted_percentage'] > 10 ) {
			$field_status = Status::Warning;
		}

		return $this->make_field(
			__( 'OPcache', 'wp-system-report' ),
			$value,
			array(
				'status'      => $field_status,
				'description' => __( 'PHP OPcache caches compiled scripts to improve performance.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect total wp_options row count.
	 *
	 * The result is stored in $this->options_row_count so that
	 * collect_persistent_cache_recommended() can reuse it without
	 * issuing a second identical query.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_options_row_count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time diagnostic query.
		$this->options_row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options}" );
		$count                   = $this->options_row_count;

		$status = Status::Good;
		if ( $count > 5000 ) {
			$status = Status::Warning;
		} elseif ( $count > 2000 ) {
			$status = Status::Info;
		}

		return $this->make_field(
			__( 'Total wp_options Rows', 'wp-system-report' ),
			number_format_i18n( $count ),
			array(
				'status'      => $status,
				'description' => __( 'Total number of rows in the options table. A high count can slow option lookups.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect wp_options table size.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_options_table_size() {
		global $wpdb;

		$database_name = $this->get_constant_value( 'DB_NAME' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time diagnostic query.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT DATA_LENGTH + INDEX_LENGTH AS table_size
				FROM information_schema.TABLES
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				$database_name,
				$wpdb->options
			)
		);

		$size = $row ? (int) $row->table_size : 0;

		$status = Status::Good;
		if ( $size > 10 * MB_IN_BYTES ) {
			$status = Status::Warning;
		}

		return $this->make_field(
			__( 'wp_options Table Size', 'wp-system-report' ),
			$this->format_size( $size ),
			array(
				'status'      => $status,
				'description' => __( 'Combined data and index size of the options table.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect expired transient count.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_expired_transients() {
		global $wpdb;

		$time = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time diagnostic query.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				$time
			)
		);

		$status = Status::Good;
		if ( $count > 500 ) {
			$status = Status::Warning;
		} elseif ( $count > 100 ) {
			$status = Status::Info;
		}

		return $this->make_field(
			__( 'Expired Transients', 'wp-system-report' ),
			number_format_i18n( $count ),
			array(
				'status'      => $status,
				'description' => __( 'Expired transient entries that could be cleaned up.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect total database overhead (fragmentation).
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_database_overhead() {
		global $wpdb;

		$database_name = $this->get_constant_value( 'DB_NAME' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time diagnostic query.
		$overhead = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(DATA_FREE) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s',
				$database_name
			)
		);

		$overhead = $overhead ? (int) $overhead : 0;

		$status = Status::Good;
		if ( $overhead > 100 * MB_IN_BYTES ) {
			$status = Status::Warning;
		} elseif ( $overhead > 10 * MB_IN_BYTES ) {
			$status = Status::Info;
		}

		return $this->make_field(
			__( 'Database Overhead', 'wp-system-report' ),
			$this->format_size( $overhead ),
			array(
				'status'      => $status,
				'description' => __( 'Total free space (fragmentation) across all database tables. Can be reclaimed with OPTIMIZE TABLE.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect the top autoloaded options by value size.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_top_autoloaded_options() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time diagnostic query.
		$results = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) AS val_size
			FROM {$wpdb->options}
			WHERE autoload IN ('yes', 'on')
			ORDER BY val_size DESC
			LIMIT 5"
		);

		$parts = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$parts[] = $row->option_name . ' (' . $this->format_size( (int) $row->val_size ) . ')';
			}
		}

		$value = ! empty( $parts ) ? implode( ', ', $parts ) : __( 'None', 'wp-system-report' );

		return $this->make_field(
			__( 'Top Autoloaded Options', 'wp-system-report' ),
			$value,
			array(
				'status'      => Status::Info,
				'description' => __( 'The 5 largest autoloaded options by value size. Helps identify bloated options.', 'wp-system-report' ),
				'private'     => true,
			)
		);
	}

	/**
	 * Determine if a persistent object cache is recommended.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_persistent_cache_recommended() {
		$using_ext = wp_using_ext_object_cache();

		if ( $using_ext ) {
			return $this->make_field(
				__( 'Persistent Object Cache', 'wp-system-report' ),
				__( 'In use', 'wp-system-report' ),
				array(
					'status'      => Status::Good,
					'description' => __( 'A persistent object cache is active.', 'wp-system-report' ),
				)
			);
		}

		// Heuristic: recommend if options table has many rows or site has many posts.
		// Reuse the count already fetched by collect_options_row_count() when available.
		if ( null === $this->options_row_count ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time diagnostic query.
			$this->options_row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options}" );
		}

		$option_count = $this->options_row_count;
		$post_count   = (int) wp_count_posts()->publish;

		$recommended = ( $option_count > 1000 || $post_count > 500 );

		$value  = $recommended
			? __( 'Recommended', 'wp-system-report' )
			: __( 'Optional', 'wp-system-report' );
		$status = $recommended ? Status::Warning : Status::Info;

		return $this->make_field(
			__( 'Persistent Object Cache', 'wp-system-report' ),
			$value,
			array(
				'status'      => $status,
				'description' => __( 'Whether a persistent object cache (Redis, Memcached) is recommended based on site size.', 'wp-system-report' ),
				'recommended' => __( 'Redis or Memcached', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Detect the active object cache backend by inspecting the drop-in
	 * and known global class names.
	 *
	 * @return string Human-readable backend name.
	 */
	private function detect_object_cache_backend(): string {
		if ( ! wp_using_ext_object_cache() ) {
			return __( 'Default (File)', 'wp-system-report' );
		}

		// Check for known classes/globals that reveal the backend.
		if ( class_exists( 'Redis', false ) || class_exists( 'Predis\\Client', false ) ) {
			return 'Redis';
		}

		if ( class_exists( 'Memcached', false ) ) {
			return 'Memcached';
		}

		if ( class_exists( 'Memcache', false ) ) {
			return 'Memcache';
		}

		if ( function_exists( 'apcu_fetch' ) ) {
			// Check if the drop-in actually uses APCu.
			$dropin = WP_CONTENT_DIR . '/object-cache.php';
			if ( file_exists( $dropin ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local drop-in.
				$contents = file_get_contents( $dropin );
				if ( false !== $contents && str_contains( $contents, 'apcu' ) ) {
					return 'APCu';
				}
			}
		}

		return __( 'External (unknown)', 'wp-system-report' );
	}
}
