<?php
/**
 * Fix result value object.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable value object representing the outcome of a fixer execution.
 *
 * Implements JsonSerializable so that results can be returned directly
 * from REST API endpoints without additional transformation.
 */
class Fix_Result implements \JsonSerializable {

	/**
	 * Constructor.
	 *
	 * @param bool     $success Whether the fix operation succeeded.
	 * @param string   $message Human-readable summary of the outcome.
	 * @param array    $before  Snapshot of state before the fix was applied.
	 * @param array    $after   Snapshot of state after the fix was applied.
	 * @param array    $errors  Error details when the operation failed.
	 */
	public function __construct(
		public readonly bool $success,
		public readonly string $message,
		public readonly array $before = array(),
		public readonly array $after = array(),
		public readonly array $errors = array(),
	) {}

	/**
	 * Create a successful fix result.
	 *
	 * @param string $message Human-readable success message.
	 * @param array  $before  Snapshot of state before the fix.
	 * @param array  $after   Snapshot of state after the fix.
	 * @return self New Fix_Result instance.
	 */
	public static function success( string $message, array $before = array(), array $after = array() ): self {
		return new self(
			success: true,
			message: $message,
			before:  $before,
			after:   $after,
		);
	}

	/**
	 * Create a failed fix result.
	 *
	 * @param string $message Human-readable failure message.
	 * @param array  $errors  Detailed error information.
	 * @return self New Fix_Result instance.
	 */
	public static function failure( string $message, array $errors = array() ): self {
		return new self(
			success: false,
			message: $message,
			errors:  $errors,
		);
	}

	/**
	 * Convert this result to an associative array.
	 *
	 * @return array<string, mixed> Result data suitable for serialization.
	 */
	public function to_array(): array {
		$data = array(
			'success' => $this->success,
			'message' => $this->message,
		);

		if ( array() !== $this->before ) {
			$data['before'] = $this->before;
		}

		if ( array() !== $this->after ) {
			$data['after'] = $this->after;
		}

		if ( array() !== $this->errors ) {
			$data['errors'] = $this->errors;
		}

		return $data;
	}

	/**
	 * Serialize for JSON output.
	 *
	 * @return array<string, mixed> Result data suitable for JSON encoding.
	 */
	public function jsonSerialize(): mixed {
		return $this->to_array();
	}
}
