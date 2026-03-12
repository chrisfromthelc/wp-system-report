<?php
/**
 * Abstract base collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

use SystemReport\Field;
use SystemReport\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract collector with shared helper methods.
 */
abstract class Abstract_Collector implements Collector {

	/**
	 * Get the transient cache key for this collector.
	 *
	 * Override in subclasses to enable caching for expensive operations.
	 *
	 * @return string|null Cache key, or null to disable caching.
	 */
	protected function get_cache_key() {
		return null;
	}

	/**
	 * Get cached or fresh data from this collector.
	 *
	 * If the collector defines a cache key, results are stored in a transient.
	 *
	 * @return array Collected field data.
	 */
	public function get_cached_data() {
		$cache_key = $this->get_cache_key();

		if ( null !== $cache_key ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$data = $this->collect();

		if ( null !== $cache_key ) {
			/**
			 * Filter the transient cache TTL for WP System Report collectors.
			 *
			 * @param int    $ttl       Cache TTL in seconds. Default 3600 (1 hour).
			 * @param string $cache_key The transient cache key.
			 */
			$ttl = apply_filters( 'wp_system_report_cache_ttl', HOUR_IN_SECONDS, $cache_key );
			set_transient( $cache_key, $data, $ttl );
		}

		return $data;
	}

	/**
	 * Build a Field value object with sensible defaults.
	 *
	 * Accepts both Status enum instances and legacy string values
	 * (e.g. 'good', 'warning') in $options['status'] for backward
	 * compatibility with third-party code.
	 *
	 * @param string $label   Display label.
	 * @param mixed  $value   Display value.
	 * @param array  $options Optional. Additional field options.
	 * @return Field Complete field object.
	 */
	protected function make_field( $label, $value, $options = array() ): Field {
		$status = $options['status'] ?? Status::Info;
		if ( is_string( $status ) ) {
			$status = Status::from_legacy( $status );
		}

		return new Field(
			label:        $label,
			value:        (string) $value,
			debug:        $options['debug'] ?? $value,
			status:       $status,
			description:  $options['description'] ?? '',
			recommended:  $options['recommended'] ?? '',
			export_label: $options['export_label'] ?? $label,
			is_private:   $options['private'] ?? false,
			fix_id:       $options['fix_id'] ?? null,
		);
	}

	/**
	 * Format a byte value into a human-readable size string.
	 *
	 * @param int|float $bytes Number of bytes.
	 * @return string Formatted size string, e.g., '256 MB'.
	 */
	protected function format_size( $bytes ) {
		if ( function_exists( 'size_format' ) ) {
			return size_format( $bytes );
		}

		$units  = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$bytes  = max( $bytes, 0 );
		$pow    = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow    = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}

	/**
	 * Format a boolean value for display.
	 *
	 * @param bool $value The boolean value.
	 * @return string 'Yes' or 'No'.
	 */
	protected function format_boolean( $value ) {
		return $value ? __( 'Yes', 'wp-system-report' ) : __( 'No', 'wp-system-report' );
	}

	/**
	 * Safely get a PHP constant's value.
	 *
	 * @param string $name     Constant name.
	 * @param mixed  $fallback Fallback value if constant is not defined.
	 * @return mixed Constant value or fallback.
	 */
	protected function get_constant_value( $name, $fallback = null ) {
		return defined( $name ) ? constant( $name ) : $fallback;
	}

	/**
	 * Convert a PHP ini value to bytes.
	 *
	 * @param string $value PHP ini value (e.g., '128M', '1G').
	 * @return int Value in bytes.
	 */
	protected function convert_to_bytes( $value ) {
		$value = strtolower( trim( $value ) );
		$num   = (int) $value;

		switch ( substr( $value, -1 ) ) {
			case 'g':
				$num *= GB_IN_BYTES;
				break;
			case 'm':
				$num *= MB_IN_BYTES;
				break;
			case 'k':
				$num *= KB_IN_BYTES;
				break;
		}

		return $num;
	}
}
