<?php
/**
 * WordPress Constants collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects defined WordPress constants and their values.
 */
class WordPress_Constants extends Abstract_Collector {

	/**
 * Get the collector ID.
 */
	public function get_id(): string {
		return 'wordpress_constants';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'WordPress Constants', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Defined WordPress constants and their values.', 'wp-system-report' );
	}

	/**
 * Get the collector priority.
 */
	public function get_priority(): int {
		return 100;
	}

	/**
 * Collect the data.
 */
	public function collect(): array {
		$constants_to_check = array(
			'ABSPATH'             => array(
				'label'   => __( 'ABSPATH', 'wp-system-report' ),
				'private' => true,
			),
			'WP_HOME'             => array(
				'label'   => __( 'WP_HOME', 'wp-system-report' ),
				'private' => true,
			),
			'WP_SITEURL'          => array(
				'label'   => __( 'WP_SITEURL', 'wp-system-report' ),
				'private' => true,
			),
			'WP_CONTENT_DIR'      => array(
				'label' => __( 'WP_CONTENT_DIR', 'wp-system-report' ),
			),
			'WP_PLUGIN_DIR'       => array(
				'label' => __( 'WP_PLUGIN_DIR', 'wp-system-report' ),
			),
			'WP_MEMORY_LIMIT'     => array(
				'label' => __( 'WP_MEMORY_LIMIT', 'wp-system-report' ),
			),
			'WP_MAX_MEMORY_LIMIT' => array(
				'label' => __( 'WP_MAX_MEMORY_LIMIT', 'wp-system-report' ),
			),
			'WP_DEBUG'            => array(
				'label'   => __( 'WP_DEBUG', 'wp-system-report' ),
				'boolean' => true,
			),
			'WP_DEBUG_DISPLAY'    => array(
				'label'   => __( 'WP_DEBUG_DISPLAY', 'wp-system-report' ),
				'boolean' => true,
			),
			'WP_DEBUG_LOG'        => array(
				'label'   => __( 'WP_DEBUG_LOG', 'wp-system-report' ),
				'boolean' => true,
			),
			'SCRIPT_DEBUG'        => array(
				'label'   => __( 'SCRIPT_DEBUG', 'wp-system-report' ),
				'boolean' => true,
			),
			'WP_CACHE'            => array(
				'label'   => __( 'WP_CACHE', 'wp-system-report' ),
				'boolean' => true,
			),
			'CONCATENATE_SCRIPTS' => array(
				'label'   => __( 'CONCATENATE_SCRIPTS', 'wp-system-report' ),
				'boolean' => true,
			),
			'COMPRESS_SCRIPTS'    => array(
				'label'   => __( 'COMPRESS_SCRIPTS', 'wp-system-report' ),
				'boolean' => true,
			),
			'COMPRESS_CSS'        => array(
				'label'   => __( 'COMPRESS_CSS', 'wp-system-report' ),
				'boolean' => true,
			),
			'WP_ENVIRONMENT_TYPE' => array(
				'label' => __( 'WP_ENVIRONMENT_TYPE', 'wp-system-report' ),
			),
			'WP_DEVELOPMENT_MODE' => array(
				'label' => __( 'WP_DEVELOPMENT_MODE', 'wp-system-report' ),
			),
			'DISALLOW_FILE_EDIT'  => array(
				'label'   => __( 'DISALLOW_FILE_EDIT', 'wp-system-report' ),
				'boolean' => true,
			),
			'DISALLOW_FILE_MODS'  => array(
				'label'   => __( 'DISALLOW_FILE_MODS', 'wp-system-report' ),
				'boolean' => true,
			),
			'DISABLE_WP_CRON'     => array(
				'label'   => __( 'DISABLE_WP_CRON', 'wp-system-report' ),
				'boolean' => true,
			),
			'WP_AUTO_UPDATE_CORE' => array(
				'label' => __( 'WP_AUTO_UPDATE_CORE', 'wp-system-report' ),
			),
			'FORCE_SSL_ADMIN'     => array(
				'label'   => __( 'FORCE_SSL_ADMIN', 'wp-system-report' ),
				'boolean' => true,
			),
			'AUTOSAVE_INTERVAL'   => array(
				'label' => __( 'AUTOSAVE_INTERVAL', 'wp-system-report' ),
			),
			'WP_POST_REVISIONS'   => array(
				'label' => __( 'WP_POST_REVISIONS', 'wp-system-report' ),
			),
		);

		// Allow filtering the constants list.
		$constants_to_check = apply_filters( 'wp_system_report_constants', $constants_to_check );

		$fields           = array();
		$environment_type = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$is_production    = 'production' === $environment_type;

		foreach ( $constants_to_check as $constant => $config ) {
			$value = $this->get_constant_value( $constant );

			if ( null === $value ) {
				$display_value = __( 'Not defined', 'wp-system-report' );
				$debug_value   = null;
			} elseif ( ! empty( $config['boolean'] ) ) {
				$display_value = $this->format_boolean( $value );
				$debug_value   = $value;
			} else {
				$display_value = $value;
				$debug_value   = $value;
			}

			// Determine field status based on constant value and environment.
			$status = 'info';

			// WP_DEBUG checks.
			if ( 'WP_DEBUG' === $constant && $value && $is_production ) {
				$status = 'warning';
			}

			// WP_DEBUG_DISPLAY is critical if enabled on production.
			if ( 'WP_DEBUG_DISPLAY' === $constant && $value && $is_production ) {
				$status = 'critical';
			}

			// WP_CACHE is good when enabled.
			if ( 'WP_CACHE' === $constant && $value ) {
				$status = 'good';
			}

			// DISALLOW_FILE_EDIT is good when enabled.
			if ( 'DISALLOW_FILE_EDIT' === $constant && $value ) {
				$status = 'good';
			}

			// DISABLE_WP_CRON is warning when enabled.
			if ( 'DISABLE_WP_CRON' === $constant && $value ) {
				$status = 'warning';
			}

			// FORCE_SSL_ADMIN is good when enabled.
			if ( 'FORCE_SSL_ADMIN' === $constant && $value ) {
				$status = 'good';
			}

			$field_options = array(
				'debug'  => $debug_value,
				'status' => $status,
			);

			if ( ! empty( $config['private'] ) ) {
				$field_options['private'] = true;
			}

			$fields[] = $this->make_field( $config['label'], $display_value, $field_options );
		}

		return $fields;
	}
}
