<?php
/**
 * Field value object.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a single diagnostic field in a report section.
 *
 * Implements ArrayAccess for backward compatibility with code that
 * accesses fields as associative arrays, and JsonSerializable for
 * clean REST API output.
 */
class Field implements \ArrayAccess, \JsonSerializable {

	/**
	 * Display label.
	 *
	 * @var string
	 */
	public string $label;

	/**
	 * Formatted display value.
	 *
	 * @var string
	 */
	public string $value;

	/**
	 * Raw/machine-readable value.
	 *
	 * @var mixed
	 */
	public mixed $debug;

	/**
	 * Field status indicator.
	 *
	 * @var Status
	 */
	public Status $status;

	/**
	 * Contextual description for AI export.
	 *
	 * @var string
	 */
	public string $description;

	/**
	 * Recommended value for AI export.
	 *
	 * @var string
	 */
	public string $recommended;

	/**
	 * Compact label for text export.
	 *
	 * @var string
	 */
	public string $export_label;

	/**
	 * Whether to exclude from exports.
	 *
	 * @var bool
	 */
	public bool $private;

	/**
	 * Linked fixer identifier (Phase 3).
	 *
	 * @var string|null
	 */
	public ?string $fix_id;

	/**
	 * Constructor.
	 *
	 * @param string      $label        Display label.
	 * @param string      $value        Formatted display value.
	 * @param mixed       $debug        Raw/machine-readable value.
	 * @param Status      $status       Status indicator.
	 * @param string      $description  Contextual description.
	 * @param string      $recommended  Recommended value.
	 * @param string      $export_label Compact label for text export.
	 * @param bool        $is_private   Whether to exclude from exports.
	 * @param string|null $fix_id       Linked fixer identifier.
	 */
	public function __construct(
		string $label,
		string $value,
		mixed $debug = null,
		Status $status = Status::Info,
		string $description = '',
		string $recommended = '',
		string $export_label = '',
		bool $is_private = false,
		?string $fix_id = null
	) {
		$this->label        = $label;
		$this->value        = $value;
		$this->debug        = $debug ?? $value;
		$this->status       = $status;
		$this->description  = $description;
		$this->recommended  = $recommended;
		$this->export_label = '' === $export_label ? $label : $export_label;
		$this->private      = $is_private;
		$this->fix_id       = $fix_id;
	}

	/**
	 * Convert to an associative array.
	 *
	 * @return array<string, mixed> Field data with string status value.
	 */
	public function to_array(): array {
		$data = array(
			'label'        => $this->label,
			'value'        => $this->value,
			'debug'        => $this->debug,
			'status'       => $this->status->value,
			'description'  => $this->description,
			'recommended'  => $this->recommended,
			'export_label' => $this->export_label,
			'private'      => $this->private,
		);

		if ( null !== $this->fix_id ) {
			$data['fix_id'] = $this->fix_id;
		}

		return $data;
	}

	/**
	 * Check whether an offset exists.
	 *
	 * @param mixed $offset Array key to check.
	 * @return bool
	 */
	public function offsetExists( mixed $offset ): bool {
		return in_array(
			$offset,
			array( 'label', 'value', 'debug', 'status', 'description', 'recommended', 'export_label', 'private', 'fix_id' ),
			true
		);
	}

	/**
	 * Retrieve a value by array key.
	 *
	 * The 'status' key returns the string value of the Status enum
	 * for backward compatibility with existing code.
	 *
	 * @param mixed $offset Array key.
	 * @return mixed
	 */
	public function offsetGet( mixed $offset ): mixed {
		return match ( $offset ) {
			'label'        => $this->label,
			'value'        => $this->value,
			'debug'        => $this->debug,
			'status'       => $this->status->value,
			'description'  => $this->description,
			'recommended'  => $this->recommended,
			'export_label' => $this->export_label,
			'private'      => $this->private,
			'fix_id'       => $this->fix_id,
			default        => null,
		};
	}

	/**
	 * Set a value by array key.
	 *
	 * Supports both string and Status enum values for the 'status' key.
	 *
	 * @param mixed $offset Array key.
	 * @param mixed $value  New value.
	 */
	public function offsetSet( mixed $offset, mixed $value ): void {
		switch ( $offset ) {
			case 'label':
				$this->label = (string) $value;
				break;
			case 'value':
				$this->value = (string) $value;
				break;
			case 'debug':
				$this->debug = $value;
				break;
			case 'status':
				$this->status = $value instanceof Status ? $value : Status::from_legacy( (string) $value );
				break;
			case 'description':
				$this->description = (string) $value;
				break;
			case 'recommended':
				$this->recommended = (string) $value;
				break;
			case 'export_label':
				$this->export_label = (string) $value;
				break;
			case 'private':
				$this->private = (bool) $value;
				break;
			case 'fix_id':
				$this->fix_id = null === $value ? null : (string) $value;
				break;
		}
	}

	/**
	 * Unset a value by array key (no-op).
	 *
	 * Field properties cannot be unset; this satisfies the ArrayAccess contract.
	 *
	 * @param mixed $offset Array key.
	 */
	public function offsetUnset( mixed $offset ): void {
		// No-op: Field properties cannot be unset.
	}

	/**
	 * Serialize for JSON output.
	 *
	 * @return array<string, mixed> Field data suitable for JSON encoding.
	 */
	public function jsonSerialize(): array {
		return $this->to_array();
	}
}
