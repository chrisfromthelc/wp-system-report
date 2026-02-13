<?php
/**
 * Plugin settings manager.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Manages plugin settings stored in a single option.
 */
class Settings {

	/**
	 * Option name in the database.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wp_system_report_settings';

	/**
	 * Default settings values.
	 *
	 * @var array
	 */
	private static array $defaults = array(
		'error_log_lines' => 100,
	);

	/**
	 * Get a settings value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Optional. Default value if key is not set.
	 *                        Falls back to the built-in default if not provided.
	 * @return mixed Setting value.
	 */
	public static function get( string $key, $default = null ) {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		// Fall back to built-in default, then caller-provided default.
		if ( array_key_exists( $key, self::$defaults ) ) {
			return self::$defaults[ $key ];
		}

		return $default;
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array All settings.
	 */
	public static function get_all(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return array_merge( self::$defaults, $settings );
	}

	/**
	 * Update a settings value.
	 *
	 * The value is sanitized before saving.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True if updated, false otherwise.
	 */
	public static function update( string $key, $value ): bool {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings[ $key ] = self::sanitize( $key, $value );

		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Delete all plugin settings.
	 *
	 * @return bool True if deleted, false otherwise.
	 */
	public static function delete(): bool {
		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Sanitize a setting value based on its key.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return mixed Sanitized value.
	 */
	public static function sanitize( string $key, $value ) {
		switch ( $key ) {
			case 'error_log_lines':
				$value = absint( $value );
				return max( 1, min( 1000, $value ) );

			default:
				return $value;
		}
	}

	/**
	 * Get the default value for a setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed|null Default value, or null if no default exists.
	 */
	public static function get_default( string $key ) {
		return self::$defaults[ $key ] ?? null;
	}
}
