<?php
/**
 * Security collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects security-related settings and configuration.
 */
class Security extends Abstract_Collector {

	/**
	 * Get the collector ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'security';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Security', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Security-related settings and configuration.', 'system-report' );
	}

	/**
	 * Get the collector priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 50;
	}

	/**
	 * Collect the data.
	 *
	 * @return array
	 */
	public function collect() {
		$data = array();

		// Secure Connection (HTTPS).
		$is_ssl     = is_ssl();
		$ssl_status = $is_ssl ? 'good' : 'critical';

		$data[] = $this->make_field(
			__( 'Secure Connection (HTTPS)', 'system-report' ),
			$this->format_boolean( $is_ssl ),
			array(
				'status'      => $ssl_status,
				'recommended' => 'Yes',
			)
		);

		// Hide Errors from Visitors.
		$wp_debug_display  = $this->get_constant_value( 'WP_DEBUG_DISPLAY', true );
		$display_errors    = ini_get( 'display_errors' );
		$errors_hidden     = ( false === $wp_debug_display ) && ( 'off' === strtolower( $display_errors ) || '0' === $display_errors );
		$environment_type  = wp_get_environment_type();
		$error_hide_status = 'info';

		if ( 'production' === $environment_type && ! $errors_hidden ) {
			$error_hide_status = 'critical';
		} elseif ( $errors_hidden ) {
			$error_hide_status = 'good';
		}

		$data[] = $this->make_field(
			__( 'Hide Errors from Visitors', 'system-report' ),
			$this->format_boolean( $errors_hidden ),
			array( 'status' => $error_hide_status )
		);

		// File Editing Disabled.
		$file_edit_disabled = $this->get_constant_value( 'DISALLOW_FILE_EDIT', false );
		$file_edit_status   = $file_edit_disabled ? 'good' : 'info';

		$data[] = $this->make_field(
			__( 'File Editing Disabled', 'system-report' ),
			$this->format_boolean( $file_edit_disabled ),
			array( 'status' => $file_edit_status )
		);

		// File Modifications Disabled.
		$file_mods_disabled = $this->get_constant_value( 'DISALLOW_FILE_MODS', false );

		$data[] = $this->make_field(
			__( 'File Modifications Disabled', 'system-report' ),
			$this->format_boolean( $file_mods_disabled )
		);

		// Application Passwords.
		$app_passwords_available = function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available();

		$data[] = $this->make_field(
			__( 'Application Passwords', 'system-report' ),
			$this->format_boolean( $app_passwords_available )
		);

		return $data;
	}
}
