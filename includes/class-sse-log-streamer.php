<?php
/**
 * SSE live log streamer.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Streams PHP error log lines to the client via Server-Sent Events (SSE).
 *
 * Tails the active error log file and emits new lines as SSE `message`
 * events, with periodic `heartbeat` events to keep the connection alive.
 * Handles file rotation/truncation and honours a configurable maximum
 * stream duration to prevent zombie connections.
 *
 * Intended to be invoked from {@see SSE_Log_Controller} which takes over
 * the REST response via the `rest_pre_serve_request` filter.
 */
class SSE_Log_Streamer {

	/**
	 * Default poll interval in microseconds (1 second).
	 *
	 * @var int
	 */
	const DEFAULT_POLL_INTERVAL = 1000000;

	/**
	 * Default heartbeat interval in seconds (15 seconds).
	 *
	 * @var int
	 */
	const DEFAULT_HEARTBEAT_INTERVAL = 15;

	/**
	 * Default maximum stream duration in seconds (5 minutes).
	 *
	 * @var int
	 */
	const DEFAULT_MAX_DURATION = 300;

	/**
	 * Default number of initial lines to send on first load.
	 *
	 * @var int
	 */
	const DEFAULT_INITIAL_LINES = 50;

	/**
	 * Error log reader instance.
	 */
	private Error_Log_Reader $reader;

	/**
	 * Constructor.
	 *
	 * @param Error_Log_Reader $reader Error log reader instance used for
	 *                                 path resolution, safety validation,
	 *                                 and initial line reads.
	 */
	public function __construct( Error_Log_Reader $reader ) {
		$this->reader = $reader;
	}

	/**
	 * Return the last N lines of the log file for initial page load.
	 *
	 * Delegates entirely to {@see Error_Log_Reader::read_last_lines()} so
	 * that the same redaction and chunking logic is reused.
	 *
	 * @param int $count Number of lines to return. Filtered via
	 *                   `wp_system_report_sse_initial_lines`.
	 * @return array Array of log line strings, or empty array when no log
	 *               file exists or the path is unsafe.
	 */
	public function get_initial_lines( int $count = self::DEFAULT_INITIAL_LINES ): array {
		/**
		 * Filter the number of initial lines returned for the live log view.
		 *
		 * @param int $count Number of lines. Default 50.
		 */
		$count = (int) apply_filters( 'wp_system_report_sse_initial_lines', $count );

		if ( $count < 1 ) {
			$count = self::DEFAULT_INITIAL_LINES;
		}

		$path = $this->reader->resolve_log_path();

		if ( null === $path ) {
			return array();
		}

		if ( ! $this->reader->is_path_safe( $path ) ) {
			return array();
		}

		return $this->reader->read_last_lines( $path, $count );
	}

	/**
	 * Begin streaming the error log as Server-Sent Events.
	 *
	 * This method takes over the HTTP response directly. It must be called
	 * from inside a `rest_pre_serve_request` filter callback so that the
	 * REST API does not attempt to serialise the response again after this
	 * method returns.
	 *
	 * The loop runs until one of:
	 * - The client disconnects (`connection_aborted()` returns 1).
	 * - The maximum duration is reached (a `close` event is sent).
	 * - An unrecoverable read error occurs.
	 *
	 * Events emitted:
	 * - `heartbeat` — sent every heartbeat interval with a Unix timestamp.
	 * - `message`   — one event per new log line, JSON-encoded with keys
	 *                 `line` (redacted string) and `timestamp` (ISO-8601).
	 * - `close`     — sent once when the maximum duration is reached.
	 */
	public function stream(): void {
		$path = $this->reader->resolve_log_path();

		if ( null === $path ) {
			$this->emit_error_event( 'no_log_file', __( 'No error log file found.', 'wp-system-report' ) );
			return;
		}

		if ( ! $this->reader->is_path_safe( $path ) ) {
			$this->emit_error_event( 'unsafe_path', __( 'The error log path is outside the allowed directory boundary.', 'wp-system-report' ) );
			return;
		}

		// Resolve filterable timing values.
		/**
		 * Filter the SSE poll interval in microseconds.
		 *
		 * Lower values increase CPU usage; values below 100 000 (100 ms)
		 * are clamped to 100 000.
		 *
		 * @param int $interval Microseconds between file polls. Default 1 000 000.
		 */
		$poll_interval = (int) apply_filters( 'wp_system_report_sse_poll_interval', self::DEFAULT_POLL_INTERVAL );
		$poll_interval = max( 100000, $poll_interval );

		/**
		 * Filter the SSE heartbeat interval in seconds.
		 *
		 * @param int $interval Seconds between heartbeat events. Default 15.
		 */
		$heartbeat_interval = (int) apply_filters( 'wp_system_report_sse_heartbeat_interval', self::DEFAULT_HEARTBEAT_INTERVAL );
		$heartbeat_interval = max( 1, $heartbeat_interval );

		/**
		 * Filter the SSE maximum stream duration in seconds.
		 *
		 * @param int $duration Seconds before the stream is closed. Default 300.
		 */
		$max_duration = (int) apply_filters( 'wp_system_report_sse_max_duration', self::DEFAULT_MAX_DURATION );
		$max_duration = max( 1, $max_duration );

		$this->send_sse_headers();

		/**
		 * Fires when an SSE log stream starts.
		 *
		 * @param string $path Absolute path to the log file being streamed.
		 */
		do_action( 'wp_system_report_sse_stream_start', $path );

		$start_time     = time();
		$last_heartbeat = $start_time;

		// Seed file position at the current end of file so only new lines
		// written after the connection is established are streamed.
		$file_size  = @filesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- filesize() can legitimately return false for stat failure.
		$position   = ( false !== $file_size ) ? $file_size : 0;
		$last_inode = $this->get_inode( $path );

		while ( true ) {
			// Honour client disconnect.
			if ( connection_aborted() ) {
				break;
			}

			$now = time();

			// Enforce maximum duration.
			if ( ( $now - $start_time ) >= $max_duration ) {
				$this->emit_event( 'close', array( 'reason' => 'max_duration' ) );
				$this->flush_output();
				break;
			}

			// Send heartbeat if interval has elapsed.
			if ( ( $now - $last_heartbeat ) >= $heartbeat_interval ) {
				$this->emit_event( 'heartbeat', array( 'timestamp' => $now ) );
				$this->flush_output();
				$last_heartbeat = $now;
			}

			// Detect file rotation or truncation.
			clearstatcache( true, $path );
			$current_inode = $this->get_inode( $path );
			$current_size  = @filesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( false === $current_size ) {
				// File no longer accessible; wait and retry.
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_sleep -- SSE requires deliberate delays.
				usleep( $poll_interval );
				continue;
			}

			// Rotation: inode changed or file is smaller than our position.
			if ( $current_inode !== $last_inode || $current_size < $position ) {
				$position   = 0;
				$last_inode = $current_inode;
			}

			if ( $current_size > $position ) {
				$new_lines = $this->read_new_lines( $path, $position, $current_size );

				foreach ( $new_lines['lines'] as $line ) {
					if ( '' === trim( $line ) ) {
						continue;
					}

					$redacted = (string) apply_filters( 'wp_system_report_redact_log_line', $line );

					$this->emit_event(
						'message',
						array(
							'line'      => $redacted,
							'timestamp' => gmdate( 'c' ),
						)
					);
				}

				$position = $new_lines['new_position'];
				$this->flush_output();
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_sleep -- Required for SSE polling loop.
			usleep( $poll_interval );
		}

		/**
		 * Fires when an SSE log stream ends.
		 *
		 * @param string $path Absolute path to the log file that was streamed.
		 */
		do_action( 'wp_system_report_sse_stream_end', $path );
	}

	/**
	 * Send the HTTP headers required to initiate an SSE stream.
	 *
	 * Safe to call even when headers may already be sent; the check is
	 * performed before each header() call.
	 */
	private function send_sse_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'X-Accel-Buffering: no' );
		header( 'Connection: keep-alive' );
		header( 'X-Content-Type-Options: nosniff' );

		// Disable PHP output buffering.
		if ( function_exists( 'apache_setenv' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_apache_setenv, WordPress.PHP.NoSilencedErrors.Discouraged -- Required for SSE.
			@apache_setenv( 'no-gzip', '1' );
		}

		// phpcs:ignore WordPress.PHP.IniSet.Risky, WordPress.PHP.NoSilencedErrors.Discouraged -- Required to disable buffering for SSE.
		@ini_set( 'zlib.output_compression', 'Off' );
	}

	/**
	 * Read bytes from the log file between the old and new positions.
	 *
	 * @param string $path         Absolute path to the log file.
	 * @param int    $from         Byte offset to start reading from.
	 * @param int    $to           Byte offset to stop reading at (current file size).
	 * @return array{lines: array<string>, new_position: int}
	 */
	private function read_new_lines( string $path, int $from, int $to ): array {
		$result = array(
			'lines'        => array(),
			'new_position' => $from,
		);

		$read_length = $to - $from;

		if ( $read_length <= 0 ) {
			return $result;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged -- Low-level file tail requires fopen.
		$handle = @fopen( $path, 'r' );

		if ( ! $handle ) {
			return $result;
		}

		fseek( $handle, $from );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Required for position-based file reading.
		$chunk = fread( $handle, $read_length );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		if ( false === $chunk ) {
			return $result;
		}

		$result['lines']        = explode( "\n", $chunk );
		$result['new_position'] = $to;

		return $result;
	}

	/**
	 * Emit a single SSE event to the output buffer.
	 *
	 * Follows the SSE wire format:
	 *
	 *     event: <type>\n
	 *     data: <json>\n
	 *     \n
	 *
	 * @param string $event_type SSE event type name (e.g. 'message', 'heartbeat').
	 * @param array  $data       Data to JSON-encode and emit under the `data` field.
	 */
	private function emit_event( string $event_type, array $data ): void {
		$json = wp_json_encode( $data );

		if ( false === $json ) {
			return;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE protocol output; not HTML.
		echo 'event: ' . $event_type . "\n";
		echo 'data: ' . $json . "\n";
		echo "\n";
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Emit an SSE error event.
	 *
	 * Used to signal a configuration problem before the stream loop starts,
	 * so the client can display a meaningful message rather than timing out.
	 *
	 * @param string $code    Machine-readable error code.
	 * @param string $message Human-readable error message.
	 */
	private function emit_error_event( string $code, string $message ): void {
		$this->send_sse_headers();
		$this->emit_event(
			'error',
			array(
				'code'    => $code,
				'message' => $message,
			)
		);
		$this->flush_output();
	}

	/**
	 * Flush all output buffers and the system write buffer.
	 *
	 * Calls both `ob_flush()` (PHP output buffering) and `flush()`
	 * (system-level write buffer) to ensure bytes reach the client.
	 */
	private function flush_output(): void {
		if ( ob_get_level() > 0 ) {
			ob_flush();
		}
		flush();
	}

	/**
	 * Retrieve the inode number of a file, or null on failure.
	 *
	 * Used for file-rotation detection: a changed inode (or false when the
	 * file disappears) indicates the log was rotated.
	 *
	 * @param string $path Absolute path to the file.
	 * @return int|false Inode number, or false if stat failed.
	 */
	private function get_inode( string $path ): false|int {
		$stat = @stat( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- stat() can fail if file is removed/rotated.

		if ( false === $stat || ! isset( $stat['ino'] ) ) {
			return false;
		}

		return $stat['ino'];
	}
}
