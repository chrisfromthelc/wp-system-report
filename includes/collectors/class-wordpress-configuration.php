<?php
/**
 * WordPress Configuration collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects user roles, permalink structure, and site settings.
 */
class WordPress_Configuration extends Abstract_Collector {

	/**
 * Get the collector ID.
 */
	public function get_id(): string {
		return 'wordpress_configuration';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'WordPress Configuration', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'User roles, permalink structure, and site settings.', 'system-report' );
	}

	/**
 * Get the collector priority.
 */
	public function get_priority(): int {
		return 160;
	}

	/**
 * Collect the data.
 */
	public function collect(): array {
		$fields = array();

		// Permalink Structure.
		$permalink_structure = get_option( 'permalink_structure' );
		$fields[]            = $this->make_field(
			__( 'Permalink Structure', 'system-report' ),
			! empty( $permalink_structure ) ? $permalink_structure : __( 'Plain (default)', 'system-report' ),
			array(
				'status' => empty( $permalink_structure ) ? 'warning' : 'good',
			)
		);

		// User Registration Enabled.
		$fields[] = $this->make_field(
			__( 'User Registration Enabled', 'system-report' ),
			$this->format_boolean( get_option( 'users_can_register' ) )
		);

		// Default Role.
		$fields[] = $this->make_field(
			__( 'Default Role', 'system-report' ),
			get_option( 'default_role' )
		);

		// User Roles with counts.
		$wp_roles   = wp_roles();
		$user_stats = count_users();
		$role_info  = array();

		foreach ( $wp_roles->roles as $role_key => $role_data ) {
			$user_count  = isset( $user_stats['avail_roles'][ $role_key ] ) ? $user_stats['avail_roles'][ $role_key ] : 0;
			$role_info[] = sprintf(
				'%s (%d)',
				$role_data['name'],
				$user_count
			);
		}

		$fields[] = $this->make_field(
			__( 'User Roles', 'system-report' ),
			! empty( $role_info ) ? implode( ', ', $role_info ) : __( 'None', 'system-report' )
		);

		// Comments Enabled.
		$fields[] = $this->make_field(
			__( 'Comments Enabled', 'system-report' ),
			$this->format_boolean( get_option( 'default_comment_status' ) === 'open' )
		);

		// Comment Moderation.
		$fields[] = $this->make_field(
			__( 'Comment Moderation', 'system-report' ),
			$this->format_boolean( get_option( 'comment_moderation' ) )
		);

		// Comment Registration Required.
		$fields[] = $this->make_field(
			__( 'Comment Registration Required', 'system-report' ),
			$this->format_boolean( get_option( 'comment_registration' ) )
		);

		// Max Upload Size.
		$fields[] = $this->make_field(
			__( 'Max Upload Size', 'system-report' ),
			size_format( wp_max_upload_size() )
		);

		// Allowed Upload Types.
		$allowed_mimes = get_allowed_mime_types();
		$extensions    = ! empty( $allowed_mimes ) ? array_keys( $allowed_mimes ) : array();

		$fields[] = $this->make_field(
			__( 'Allowed Upload Types', 'system-report' ),
			! empty( $extensions ) ? implode( ', ', $extensions ) : __( 'None', 'system-report' )
		);

		// Timezone.
		$timezone_string = get_option( 'timezone_string' );
		$gmt_offset      = get_option( 'gmt_offset' );

		if ( ! empty( $timezone_string ) ) {
			$timezone_value = $timezone_string;
		} elseif ( $gmt_offset ) {
			$timezone_value = sprintf(
			/* translators: %s: GMT offset */
				__( 'UTC%s', 'system-report' ),
				( $gmt_offset >= 0 ? '+' : '' ) . $gmt_offset
			);
		} else {
			$timezone_value = 'UTC+0';
		}

		$fields[] = $this->make_field(
			__( 'Timezone', 'system-report' ),
			$timezone_value
		);

		// Date Format.
		$fields[] = $this->make_field(
			__( 'Date Format', 'system-report' ),
			get_option( 'date_format' )
		);

		// Time Format.
		$fields[] = $this->make_field(
			__( 'Time Format', 'system-report' ),
			get_option( 'time_format' )
		);

		return $fields;
	}
}
