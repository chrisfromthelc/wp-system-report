<?php
/**
 * WordPress Environment collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects core WordPress installation settings and configuration.
 */
class WordPress_Environment extends Abstract_Collector {

	/**
 * Get the collector ID.
 */
	public function get_id(): string {
		return 'wordpress_environment';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'WordPress Environment', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Core WordPress installation settings and configuration.', 'wp-system-report' );
	}

	/**
 * Get the collector priority.
 */
	public function get_priority(): int {
		return 10;
	}

	/**
 * Collect the data.
 */
	public function collect(): array {
		$data = array();

		// Home URL.
		$data[] = $this->make_field(
			__( 'Home URL', 'wp-system-report' ),
			get_option( 'home' ),
			array( 'private' => true )
		);

		// Site URL.
		$data[] = $this->make_field(
			__( 'Site URL', 'wp-system-report' ),
			get_option( 'siteurl' ),
			array( 'private' => true )
		);

		// WordPress Version.
		$wp_version        = get_bloginfo( 'version' );
		$wp_version_status = 'info';
		$latest_version    = $this->get_latest_wordpress_version();
		if ( $latest_version && version_compare( $wp_version, $latest_version, '<' ) ) {
			$wp_version_status = 'warning';
		} elseif ( $latest_version && version_compare( $wp_version, $latest_version, '>=' ) ) {
			$wp_version_status = 'good';
		}

		$data[] = $this->make_field(
			__( 'WordPress Version', 'wp-system-report' ),
			$wp_version,
			array( 'status' => $wp_version_status )
		);

		// WordPress Multisite.
		$data[] = $this->make_field(
			__( 'WordPress Multisite', 'wp-system-report' ),
			$this->format_boolean( is_multisite() )
		);

		// WordPress Memory Limit.
		$memory_limit = $this->get_constant_value( 'WP_MEMORY_LIMIT', '40M' );
		$data[]       = $this->make_field(
			__( 'WordPress Memory Limit', 'wp-system-report' ),
			$memory_limit,
			array( 'recommended' => '>= 256M' )
		);

		// WordPress Debug Mode.
		$debug_mode        = $this->get_constant_value( 'WP_DEBUG', false );
		$debug_mode_status = 'info';
		if ( $debug_mode && wp_get_environment_type() === 'production' ) {
			$debug_mode_status = 'warning';
		}

		$data[] = $this->make_field(
			__( 'WordPress Debug Mode', 'wp-system-report' ),
			$this->format_boolean( $debug_mode ),
			array( 'status' => $debug_mode_status )
		);

		// WordPress Cron.
		$cron_disabled = $this->get_constant_value( 'DISABLE_WP_CRON', false );
		$cron_enabled  = ! $cron_disabled;
		$cron_status   = $cron_disabled ? 'warning' : 'good';

		$data[] = $this->make_field(
			__( 'WordPress Cron', 'wp-system-report' ),
			$this->format_boolean( $cron_enabled ),
			array( 'status' => $cron_status )
		);

		// Language.
		$data[] = $this->make_field(
			__( 'Language', 'wp-system-report' ),
			get_locale()
		);

		// Environment Type.
		$data[] = $this->make_field(
			__( 'Environment Type', 'wp-system-report' ),
			wp_get_environment_type()
		);

		// External Object Cache.
		$data[] = $this->make_field(
			__( 'External Object Cache', 'wp-system-report' ),
			$this->format_boolean( wp_using_ext_object_cache() )
		);

		// Search Engine Visibility.
		$blog_public       = get_option( 'blog_public' );
		$visibility_status = ( '0' === $blog_public ) ? 'warning' : 'good';

		$data[] = $this->make_field(
			__( 'Search Engine Visibility', 'wp-system-report' ),
			( '0' === $blog_public ) ? __( 'Discouraged', 'wp-system-report' ) : __( 'Allowed', 'wp-system-report' ),
			array( 'status' => $visibility_status )
		);

		return $data;
	}

	/**
	 * Get the latest WordPress version from update core.
	 *
	 * @return string|null Latest version or null if unavailable.
	 */
	private function get_latest_wordpress_version() {
		if ( ! function_exists( 'get_preferred_from_update_core' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$update = get_preferred_from_update_core();

		if ( $update && isset( $update->current ) ) {
			return $update->current;
		}

		return null;
	}
}
