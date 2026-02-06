<?php
/**
 * Abstract base collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract collector with shared helper methods.
 */
abstract class Abstract_Collector implements Collector {

	/**
	 * Build a field array with sensible defaults.
	 *
	 * @param string $label   Display label.
	 * @param mixed  $value   Display value.
	 * @param array  $options Optional. Additional field options.
	 * @return array Complete field array.
	 */
	protected function make_field( $label, $value, $options = array() ) {
		return array_merge(
			array(
				'label'        => $label,
				'value'        => (string) $value,
				'debug'        => $value,
				'private'      => false,
				'status'       => 'info',
				'description'  => '',
				'recommended'  => '',
				'export_label' => $label,
			),
			$options
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

		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );
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
		return $value ? __( 'Yes', 'system-report' ) : __( 'No', 'system-report' );
	}

	/**
	 * Safely get a PHP constant's value.
	 *
	 * @param string $name    Constant name.
	 * @param mixed  $default Default value if constant is not defined.
	 * @return mixed Constant value or default.
	 */
	protected function get_constant_value( $name, $default = null ) {
		return defined( $name ) ? constant( $name ) : $default;
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
