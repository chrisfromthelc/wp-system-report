<?php
/**
 * Error log reader.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Reads PHP error log files with adaptive backward chunking.
 *
 * Resolves the active error log path, validates it against safe boundaries,
 * and reads the last N lines without loading the entire file into memory.
 */
class Error_Log_Reader {

	/**
	 * Maximum bytes to read from the log file (2 MB).
	 *
	 * @var int
	 */
	const MAX_READ_BYTES = 2097152;

	/**
	 * Initial chunk size for backward reading (8 KB).
	 *
	 * @var int
	 */
	const INITIAL_CHUNK_SIZE = 8192;

	/**
 * Whether default redaction filters have been registered.
 */
	private static bool $redaction_registered = false;

	/**
	 * Constructor.
	 *
	 * Registers default redaction patterns on first instantiation.
	 */
	public function __construct() {
		$this->register_default_redaction();
	}

	/**
 * Register default redaction patterns for sensitive data in log lines.
 *
 * Runs at priority 5 so custom user filters at priority 10 can
 * override or extend the default patterns.
 */
	private function register_default_redaction(): void {
		if ( self::$redaction_registered ) {
			return;
		}

		add_filter( 'wp_system_report_redact_log_line', array( __CLASS__, 'redact_sensitive_patterns' ), 5 );
		self::$redaction_registered = true;
	}

	/**
	 * Redact common sensitive patterns from a log line.
	 *
	 * Matches key=value, key: value, and connection string patterns for:
	 * - Passwords, secrets, tokens, API keys
	 * - Authorization headers
	 * - Database connection strings
	 *
	 * @param string $line A single log line.
	 * @return string The line with sensitive values replaced by [REDACTED].
	 */
	public static function redact_sensitive_patterns( string $line ): string {
		// Redact key=value patterns (password=xxx, token=xxx, secret=xxx, key=xxx, api_key=xxx).
		$line = preg_replace(
			'/\b(password|passwd|secret|token|api_key|apikey|api[-_]?secret|access[-_]?key|private[-_]?key)\s*[=:]\s*\S+/i',
			'$1=[REDACTED]',
			$line
		);

		// Redact Authorization header values (e.g. "Authorization: Bearer token123").
		$line = preg_replace(
			'/Authorization:\s*\S+(\s+\S+)?/i',
			'Authorization: [REDACTED]',
			$line
		);

		// Redact database connection strings (mysql://, pgsql://, etc).
		$line = preg_replace(
			'#(mysql|pgsql|mysqli|postgres|mariadb)://[^\s]+#i',
			'$1://[REDACTED]',
			$line
		);

		return $line;
	}

	/**
	 * Resolve the active PHP error log path.
	 *
	 * Checks in order:
	 * 1. `WP_DEBUG_LOG` constant (if it's a string path)
	 * 2. `ini_get('error_log')`
	 * 3. `WP_CONTENT_DIR . '/debug.log'` (WordPress default when WP_DEBUG_LOG is true)
	 *
	 * @return string|null Resolved file path, or null if no log is configured/found.
	 */
	public function resolve_log_path(): ?string {
		// 1. WP_DEBUG_LOG set to a custom path.
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( constant( 'WP_DEBUG_LOG' ) ) && '' !== constant( 'WP_DEBUG_LOG' ) ) {
			$path = constant( 'WP_DEBUG_LOG' );
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		// 2. PHP ini error_log setting.
		$ini_path = ini_get( 'error_log' );
		if ( ! empty( $ini_path ) && file_exists( $ini_path ) ) {
			return $ini_path;
		}

		// 3. WordPress default debug.log location.
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$default_path = WP_CONTENT_DIR . '/debug.log';
			if ( file_exists( $default_path ) ) {
				return $default_path;
			}
		}

		return null;
	}

	/**
	 * Check if a file path is within a safe boundary.
	 *
	 * Prevents path traversal by verifying the resolved real path
	 * falls within ABSPATH or explicitly allowed directories.
	 *
	 * @param string $file_path The file path to validate.
	 * @return bool True if the path is safe to read.
	 */
	public function is_path_safe( string $file_path ): bool {
		$real_path = realpath( $file_path );

		if ( false === $real_path ) {
			return false;
		}

		// Check if within ABSPATH.
		$abspath = defined( 'ABSPATH' ) ? realpath( ABSPATH ) : false;
		if ( false !== $abspath && 0 === strpos( $real_path, $abspath ) ) {
			return true;
		}

		// Check if within WP_CONTENT_DIR.
		$content_dir = defined( 'WP_CONTENT_DIR' ) ? realpath( WP_CONTENT_DIR ) : false;
		if ( false !== $content_dir && 0 === strpos( $real_path, $content_dir ) ) {
			return true;
		}

		// Allow the PHP-configured error_log path implicitly.
		// Shared hosts commonly place logs outside WordPress (e.g. /home/user/logs/).
		$ini_error_log = ini_get( 'error_log' );
		if ( ! empty( $ini_error_log ) ) {
			$real_ini_log = realpath( $ini_error_log );
			if ( false !== $real_ini_log && $real_path === $real_ini_log ) {
				$ini_log_dir = dirname( $real_ini_log );
				if ( ! $this->is_sensitive_path( $ini_log_dir ) ) {
					return true;
				}
			}
		}

		/**
		 * Filter additional directories allowed for error log reading.
		 *
		 * Useful for hosting environments where the error log resides
		 * outside the WordPress installation directory.
		 *
		 * Paths are validated against a blocklist of sensitive system
		 * directories to prevent abuse by malicious plugins.
		 *
		 * @param array $paths Array of absolute directory paths to allow.
		 */
		$allowed_paths = apply_filters( 'wp_system_report_allowed_log_paths', array() );

		if ( is_array( $allowed_paths ) ) {
			foreach ( $allowed_paths as $allowed ) {
				if ( ! is_string( $allowed ) ) {
					continue;
				}
				if ( '' === $allowed ) {
					continue;
				}
				$real_allowed = realpath( $allowed );

				if ( false === $real_allowed ) {
					continue;
				}

				// Reject sensitive system directories.
				if ( $this->is_sensitive_path( $real_allowed ) ) {
					continue;
				}

				if ( 0 === strpos( $real_path, $real_allowed ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Read the last N lines of a file using adaptive backward chunking.
	 *
	 * Starts with an 8 KB chunk from the end of the file and doubles the
	 * chunk size until enough lines are found, up to a 2 MB maximum.
	 *
	 * @param string $file_path Absolute path to the log file.
	 * @param int    $num_lines Number of lines to return.
	 * @return array Array of log lines, or empty array on failure.
	 */
	public function read_last_lines( string $file_path, int $num_lines = 100 ): array {
		if ( ! is_readable( $file_path ) ) {
			return array();
		}

		$file_size = filesize( $file_path );

		if ( false === $file_size || 0 === $file_size ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file_path, 'r' );

		if ( ! $handle ) {
			return array();
		}

		$chunk_size = self::INITIAL_CHUNK_SIZE;
		$lines      = array();

		// Adaptive backward chunking: double chunk size until we have enough lines.
		$line_count = count( $lines );
		while ( $line_count <= $num_lines && $chunk_size <= self::MAX_READ_BYTES ) {
			$read_size = min( $chunk_size, $file_size );
			$offset    = max( 0, $file_size - $read_size );

			fseek( $handle, $offset );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			$chunk = fread( $handle, $read_size );

			if ( false === $chunk ) {
				break;
			}

			$lines      = explode( "\n", trim( $chunk ) );
			$line_count = count( $lines );

			// If we read the entire file or have enough lines, stop.
			if ( $read_size >= $file_size || $line_count > $num_lines ) {
				break;
			}

			$chunk_size *= 2;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		// Take only the last N lines.
		$result = array_slice( $lines, -$num_lines );

		/**
		 * Filter each log line before returning, allowing redaction of sensitive data.
		 *
		 * @param string $line A single log line.
		 */
		return array_map(
			static function ( string $line ): string {
				return (string) apply_filters( 'wp_system_report_redact_log_line', $line );
			},
			$result
		);
	}

	/**
	 * Get information about the current error log file.
	 *
	 * Returns a relative path instead of the absolute filesystem path
	 * to avoid disclosing server directory structure in REST responses.
	 * If the file is within WP_CONTENT_DIR, the path is relative to it.
	 * Otherwise, only the basename is returned.
	 *
	 * @return array{path: string|null, exists: bool, readable: bool, size: int, size_formatted: string, safe: bool}
	 */
	public function get_file_info(): array {
		$path = $this->resolve_log_path();

		$info = array(
			'path'           => null,
			'exists'         => false,
			'readable'       => false,
			'size'           => 0,
			'size_formatted' => '0 B',
			'safe'           => false,
		);

		if ( null === $path ) {
			return $info;
		}

		// Expose only a relative path, not the full filesystem path.
		$info['path'] = $this->make_relative_path( $path );

		$info['exists'] = file_exists( $path );

		if ( ! $info['exists'] ) {
			return $info;
		}

		$info['readable'] = is_readable( $path );
		$info['safe']     = $this->is_path_safe( $path );

		$size = filesize( $path );
		if ( false !== $size ) {
			$info['size']           = $size;
			$info['size_formatted'] = function_exists( 'size_format' ) ? size_format( $size ) : $size . ' B';
		}

		return $info;
	}

	/**
	 * Convert an absolute path to a relative one for safe display.
	 *
	 * If the file is within WP_CONTENT_DIR, returns the path relative to it.
	 * Otherwise, returns only the basename to prevent path disclosure.
	 *
	 * @param string $absolute_path Absolute file path.
	 * @return string Relative or basename path.
	 */
	private function make_relative_path( string $absolute_path ): string {
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$content_dir = trailingslashit( WP_CONTENT_DIR );
			if ( 0 === strpos( $absolute_path, $content_dir ) ) {
				return substr( $absolute_path, strlen( $content_dir ) );
			}
		}

		return basename( $absolute_path );
	}

	/**
	 * Check if a path is within a sensitive system directory.
	 *
	 * Rejects root-level system directories that should never be
	 * allowed for error log reading, even via filter.
	 *
	 * @param string $real_path Resolved real path to check.
	 * @return bool True if the path is sensitive and should be rejected.
	 */
	private function is_sensitive_path( string $real_path ): bool {
		$sensitive_dirs = array(
			'/etc',
			'/proc',
			'/sys',
			'/dev',
			'/boot',
			'/root',
			'/usr/sbin',
			'/sbin',
		);

		// On Windows, override with system-critical directories.
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$sensitive_dirs = array(
				'C:\\Windows',
				'C:\\Windows\\System32',
			);
		}

		$real_path_lower = strtolower( $real_path );

		foreach ( $sensitive_dirs as $sensitive ) {
			$sensitive_lower = strtolower( $sensitive );

			// Exact match (someone allowed '/etc' itself).
			if ( $real_path_lower === $sensitive_lower ) {
				return true;
			}

			// Path is inside the sensitive directory.
			if ( 0 === strpos( $real_path_lower, $sensitive_lower . DIRECTORY_SEPARATOR ) ) {
				return true;
			}
		}

		// Reject the filesystem root itself.
		if ( '/' === $real_path || preg_match( '#^[A-Z]:\\\\$#i', $real_path ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the current state of debug-related constants and PHP settings.
	 *
	 * @return array{wp_debug: bool, wp_debug_log: mixed, wp_debug_display: bool, log_errors: string, error_log: string, display_errors: string, error_reporting: int}
	 */
	public function get_debug_constants(): array {
		$log_errors     = ini_get( 'log_errors' );
		$error_log      = ini_get( 'error_log' );
		$display_errors = ini_get( 'display_errors' );

		return array(
			'wp_debug'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log'     => defined( 'WP_DEBUG_LOG' ) ? constant( 'WP_DEBUG_LOG' ) : false,
			'wp_debug_display' => defined( 'WP_DEBUG_DISPLAY' ) ? (bool) constant( 'WP_DEBUG_DISPLAY' ) : true,
			'log_errors'       => ( false !== $log_errors && '' !== $log_errors ) ? $log_errors : '0',
			'error_log'        => ( false !== $error_log ) ? $error_log : '',
			'display_errors'   => ( false !== $display_errors && '' !== $display_errors ) ? $display_errors : '0',
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting -- Read-only, not changing runtime config.
			'error_reporting'  => error_reporting(),
		);
	}

	/**
	 * Get a combined status summary.
	 *
	 * @return array Status array with file info and debug constants.
	 */
	public function get_status(): array {
		return array(
			'file'      => $this->get_file_info(),
			'constants' => $this->get_debug_constants(),
		);
	}
}
