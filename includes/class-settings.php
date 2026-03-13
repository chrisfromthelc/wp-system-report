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
	 * In-memory cache of the raw option array.
	 *
	 * Populated on the first call to get_option() and reused for the
	 * remainder of the request. Set to null to invalidate (e.g. after
	 * update() or delete()).
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Default settings values.
	 */
	private static array $defaults = array(
		'error_log_lines'           => 100,
		'notifications_enabled'     => false,
		'notify_email_enabled'      => false,
		'notify_email_recipients'   => '',
		'notify_slack_enabled'      => false,
		'notify_webhook_enabled'    => false,
		'slack_webhook_url'         => '',
		'webhook_urls'              => '',
		'webhook_secret'            => '',
		'notify_critical_threshold' => 1,
		'notify_warning_threshold'  => 5,
		'notification_cooldown'     => 3600,
	);

	/**
	 * Load the raw settings array from the database, with in-memory caching.
	 *
	 * get_option() is called at most once per request. All read methods go
	 * through this helper so the cache is consistently populated and used.
	 *
	 * @return array<string, mixed> Raw settings array (may be empty).
	 */
	private static function load(): array {
		if ( null === self::$cache ) {
			$settings    = get_option( self::OPTION_NAME, array() );
			self::$cache = is_array( $settings ) ? $settings : array();
		}

		return self::$cache;
	}

	/**
	 * Get a settings value.
	 *
	 * @param string $key           Setting key.
	 * @param mixed  $default_value Optional. Default value if key is not set.
	 *                              Falls back to the built-in default if not provided.
	 * @return mixed Setting value.
	 */
	public static function get( string $key, $default_value = null ) {
		$settings = self::load();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		// Fall back to built-in default, then caller-provided default.
		if ( array_key_exists( $key, self::$defaults ) ) {
			return self::$defaults[ $key ];
		}

		return $default_value;
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array All settings.
	 */
	public static function get_all(): array {
		return array_merge( self::$defaults, self::load() );
	}

	/**
	 * Update a settings value.
	 *
	 * The value is sanitized before saving. The in-memory cache is
	 * invalidated so subsequent reads reflect the new value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True if updated, false otherwise.
	 */
	public static function update( string $key, $value ): bool {
		$settings         = self::load();
		$settings[ $key ] = self::sanitize( $key, $value );

		// Invalidate cache before writing so a failed update_option() does not
		// leave stale data in memory.
		self::$cache = null;

		$result = update_option( self::OPTION_NAME, $settings );

		// Repopulate cache with the value we just persisted.
		if ( $result ) {
			self::$cache = $settings;
		}

		return $result;
	}

	/**
	 * Delete all plugin settings.
	 *
	 * The in-memory cache is cleared so subsequent reads return defaults.
	 *
	 * @return bool True if deleted, false otherwise.
	 */
	public static function delete(): bool {
		self::$cache = null;
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
				return max( 1, min( 10000, $value ) );

			case 'notifications_enabled':
			case 'notify_email_enabled':
			case 'notify_slack_enabled':
			case 'notify_webhook_enabled':
				return (bool) $value;

			case 'notify_email_recipients':
			case 'webhook_urls':
				return sanitize_textarea_field( (string) $value );

			case 'slack_webhook_url':
				return esc_url_raw( (string) $value );

			case 'webhook_secret':
				return sanitize_text_field( (string) $value );

			case 'notify_critical_threshold':
			case 'notify_warning_threshold':
				$value = absint( $value );
				return max( 1, min( 100, $value ) );

			case 'notification_cooldown':
				$value = absint( $value );
				return max( 60, min( 86400, $value ) );

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
