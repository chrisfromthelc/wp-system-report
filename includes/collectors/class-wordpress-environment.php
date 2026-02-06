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
	 *
	 * @return string
	 */
	public function get_id() {
		return 'wordpress_environment';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'WordPress Environment', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Core WordPress installation settings and configuration.', 'system-report' );
	}

	/**
	 * Get the collector priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 10;
	}

	/**
	 * Collect the data.
	 *
	 * @return array
	 */
	public function collect() {
		$data = array();

		// Home URL.
		$data[] = $this->make_field(
			__( 'Home URL', 'system-report' ),
			get_option( 'home' ),
			array( 'private' => true )
		);

		// Site URL.
		$data[] = $this->make_field(
			__( 'Site URL', 'system-report' ),
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
			__( 'WordPress Version', 'system-report' ),
			$wp_version,
			array( 'status' => $wp_version_status )
		);

		// WordPress Multisite.
		$data[] = $this->make_field(
			__( 'WordPress Multisite', 'system-report' ),
			$this->format_boolean( is_multisite() )
		);

		// WordPress Memory Limit.
		$memory_limit = $this->get_constant_value( 'WP_MEMORY_LIMIT', '40M' );
		$data[]       = $this->make_field(
			__( 'WordPress Memory Limit', 'system-report' ),
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
			__( 'WordPress Debug Mode', 'system-report' ),
			$this->format_boolean( $debug_mode ),
			array( 'status' => $debug_mode_status )
		);

		// WordPress Cron.
		$cron_disabled = $this->get_constant_value( 'DISABLE_WP_CRON', false );
		$cron_enabled  = ! $cron_disabled;
		$cron_status   = $cron_disabled ? 'warning' : 'good';

		$data[] = $this->make_field(
			__( 'WordPress Cron', 'system-report' ),
			$this->format_boolean( $cron_enabled ),
			array( 'status' => $cron_status )
		);

		// Language.
		$data[] = $this->make_field(
			__( 'Language', 'system-report' ),
			get_locale()
		);

		// Environment Type.
		$data[] = $this->make_field(
			__( 'Environment Type', 'system-report' ),
			wp_get_environment_type()
		);

		// External Object Cache.
		$data[] = $this->make_field(
			__( 'External Object Cache', 'system-report' ),
			$this->format_boolean( wp_using_ext_object_cache() )
		);

		// Search Engine Visibility.
		$blog_public       = get_option( 'blog_public' );
		$visibility_status = ( '0' === $blog_public ) ? 'warning' : 'good';

		$data[] = $this->make_field(
			__( 'Search Engine Visibility', 'system-report' ),
			( '0' === $blog_public ) ? __( 'Discouraged', 'system-report' ) : __( 'Allowed', 'system-report' ),
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
