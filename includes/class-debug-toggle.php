<?php
/**
 * Debug toggle for wp-config.php constants.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Toggles WP_DEBUG, WP_DEBUG_LOG, and WP_DEBUG_DISPLAY in wp-config.php.
 *
 * Uses the wp-cli/wp-config-transformer library for safe, regex-based editing
 * of wp-config.php. Creates a backup before every write operation.
 *
 * When the WPConfigTransformer class is not available (e.g. in environments
 * where the wp-cli/wp-config-transformer package is not loaded), all read
 * operations degrade gracefully to a read-only state and all write operations
 * return an error string rather than throwing a fatal error.
 */
class Debug_Toggle {

	/**
	 * Path to wp-config.php.
	 */
	private string $config_path;

	/**
	 * Randomised backup filename token, generated once per instance.
	 *
	 * Using a CSPRNG-derived token instead of a deterministic MD5 hash
	 * prevents an attacker who can predict the config path from knowing
	 * the backup file location.
	 */
	private string $backup_token = '';

	/**
	 * Constructor.
	 *
	 * @param string $config_path Optional. Absolute path to wp-config.php.
	 *                            Defaults to ABSPATH . 'wp-config.php'.
	 */
	public function __construct( string $config_path = '' ) {
		if ( '' === $config_path ) {
			$this->config_path = ABSPATH . 'wp-config.php';
		} else {
			$this->config_path = $config_path;
		}
	}

	/**
	 * Check whether the WPConfigTransformer class is available.
	 *
	 * The wp-cli/wp-config-transformer package is required for reading and
	 * writing debug constants. When it is absent (e.g. in a plain WordPress
	 * REST API request without WP-CLI loaded) this method returns false so
	 * that callers can degrade gracefully instead of fataling.
	 *
	 * @since 1.2.0
	 * @return bool True if WPConfigTransformer can be instantiated.
	 */
	public function is_transformer_available(): bool {
		return class_exists( '\WPConfigTransformer' );
	}

	/**
	 * Check whether wp-config.php can be modified.
	 *
	 * Returns false if:
	 * - The file doesn't exist
	 * - The file is not writable
	 * - DISALLOW_FILE_MODS is true
	 *
	 * @return bool True if modifications are allowed.
	 */
	public function can_modify(): bool {
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return false;
		}

		if ( ! file_exists( $this->config_path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Checking writability before direct file ops; WP_Filesystem not appropriate here.
		return is_writable( $this->config_path );
	}

	/**
	 * Get the current state of debug constants in wp-config.php.
	 *
	 * Reads the actual constant definitions from the file, not the runtime values.
	 *
	 * When WPConfigTransformer is not available, returns a state with all
	 * constant values set to null and can_modify set to false to signal that
	 * the environment is read-only from the perspective of this class.
	 *
	 * @return array{wp_debug: bool|null, wp_debug_log: bool|string|null, wp_debug_display: bool|null, can_modify: bool}
	 */
	public function get_state(): array {
		if ( ! $this->is_transformer_available() ) {
			return array(
				'wp_debug'         => null,
				'wp_debug_log'     => null,
				'wp_debug_display' => null,
				'can_modify'       => false,
			);
		}

		$state = array(
			'wp_debug'         => null,
			'wp_debug_log'     => null,
			'wp_debug_display' => null,
			'can_modify'       => $this->can_modify(),
		);

		if ( ! file_exists( $this->config_path ) ) {
			return $state;
		}

		try {
			$transformer = new \WPConfigTransformer( $this->config_path );

			if ( $transformer->exists( 'constant', 'WP_DEBUG' ) ) {
				$state['wp_debug'] = $this->parse_bool_value(
					$transformer->get_value( 'constant', 'WP_DEBUG' )
				);
			}

			if ( $transformer->exists( 'constant', 'WP_DEBUG_LOG' ) ) {
				$raw_value = $transformer->get_value( 'constant', 'WP_DEBUG_LOG' );
				$bool_val  = $this->parse_bool_value( $raw_value );
				// WP_DEBUG_LOG can be a path string.
				$state['wp_debug_log'] = ( null === $bool_val ) ? trim( $raw_value, "'" ) : $bool_val;
			}

			if ( $transformer->exists( 'constant', 'WP_DEBUG_DISPLAY' ) ) {
				$state['wp_debug_display'] = $this->parse_bool_value(
					$transformer->get_value( 'constant', 'WP_DEBUG_DISPLAY' )
				);
			}
		} catch ( \Exception $e ) {
			// If the transformer fails to parse, return nulls.
			return $state;
		}

		return $state;
	}

	/**
	 * Enable debug logging.
	 *
	 * Sets WP_DEBUG=true, WP_DEBUG_LOG=true, WP_DEBUG_DISPLAY=false.
	 *
	 * Fires 'wp_system_report_before_debug_toggle' before modifying
	 * wp-config.php and 'wp_system_report_after_debug_toggle' after
	 * a successful modification.
	 *
	 * @return bool|string True on success, error message string on failure.
	 */
	public function enable_debug() {
		if ( ! $this->is_transformer_available() ) {
			return $this->transformer_unavailable_error();
		}

		if ( ! $this->can_modify() ) {
			return __( 'wp-config.php is not writable or file modifications are disabled.', 'wp-system-report' );
		}

		/**
		 * Fires before debug logging is toggled.
		 *
		 * @param bool   $enable      Whether debug is being enabled (true) or disabled (false).
		 * @param string $config_path Absolute path to wp-config.php.
		 */
		do_action( 'wp_system_report_before_debug_toggle', true, $this->config_path );

		$lock = $this->acquire_lock();
		if ( false === $lock ) {
			return __( 'Could not acquire lock on wp-config.php. Another operation may be in progress.', 'wp-system-report' );
		}

		$backup = $this->create_backup();
		if ( true !== $backup ) {
			$this->release_lock( $lock );
			return $backup;
		}

		try {
			$transformer = new \WPConfigTransformer( $this->config_path );
			$raw_option  = array( 'raw' => true );

			$this->update_or_add( $transformer, 'WP_DEBUG', 'true', $raw_option );
			$this->update_or_add( $transformer, 'WP_DEBUG_LOG', 'true', $raw_option );
			$this->update_or_add( $transformer, 'WP_DEBUG_DISPLAY', 'false', $raw_option );
		} catch ( \Exception $e ) {
			$this->restore_backup();
			$this->release_lock( $lock );
			return sprintf(
				/* translators: %s: error message */
				__( 'Failed to modify wp-config.php: %s', 'wp-system-report' ),
				$e->getMessage()
			);
		}

		$this->delete_backup();
		$this->release_lock( $lock );

		/**
		 * Fires after debug logging has been successfully toggled.
		 *
		 * @param bool   $enable      Whether debug was enabled (true) or disabled (false).
		 * @param string $config_path Absolute path to wp-config.php.
		 */
		do_action( 'wp_system_report_after_debug_toggle', true, $this->config_path );

		return true;
	}

	/**
	 * Disable debug logging.
	 *
	 * Sets WP_DEBUG=false, WP_DEBUG_LOG=false, WP_DEBUG_DISPLAY=false.
	 *
	 * Fires 'wp_system_report_before_debug_toggle' before modifying
	 * wp-config.php and 'wp_system_report_after_debug_toggle' after
	 * a successful modification.
	 *
	 * @return bool|string True on success, error message string on failure.
	 */
	public function disable_debug() {
		if ( ! $this->is_transformer_available() ) {
			return $this->transformer_unavailable_error();
		}

		if ( ! $this->can_modify() ) {
			return __( 'wp-config.php is not writable or file modifications are disabled.', 'wp-system-report' );
		}

		/** This action is documented in includes/class-debug-toggle.php */
		do_action( 'wp_system_report_before_debug_toggle', false, $this->config_path );

		$lock = $this->acquire_lock();
		if ( false === $lock ) {
			return __( 'Could not acquire lock on wp-config.php. Another operation may be in progress.', 'wp-system-report' );
		}

		$backup = $this->create_backup();
		if ( true !== $backup ) {
			$this->release_lock( $lock );
			return $backup;
		}

		try {
			$transformer = new \WPConfigTransformer( $this->config_path );
			$raw_option  = array( 'raw' => true );

			$this->update_or_add( $transformer, 'WP_DEBUG', 'false', $raw_option );
			$this->update_or_add( $transformer, 'WP_DEBUG_LOG', 'false', $raw_option );
			$this->update_or_add( $transformer, 'WP_DEBUG_DISPLAY', 'false', $raw_option );
		} catch ( \Exception $e ) {
			$this->restore_backup();
			$this->release_lock( $lock );
			return sprintf(
				/* translators: %s: error message */
				__( 'Failed to modify wp-config.php: %s', 'wp-system-report' ),
				$e->getMessage()
			);
		}

		$this->delete_backup();
		$this->release_lock( $lock );

		/** This action is documented in includes/class-debug-toggle.php */
		do_action( 'wp_system_report_after_debug_toggle', false, $this->config_path );

		return true;
	}

	/**
	 * Return the error message for a missing WPConfigTransformer.
	 *
	 * Centralizes the translatable string so it only appears once in the
	 * codebase, satisfying i18n tooling which requires string literals inside
	 * translation function calls.
	 *
	 * @since 1.2.0
	 * @return string Translated error message.
	 */
	private function transformer_unavailable_error(): string {
		return __( 'Cannot modify wp-config.php: WPConfigTransformer class is not available.', 'wp-system-report' );
	}

	/**
	 * Update a constant if it exists, or add it if it doesn't.
	 *
	 * @param \WPConfigTransformer $transformer Config transformer instance.
	 * @param string               $name        Constant name.
	 * @param string               $value       Constant value.
	 * @param array                $options     Transformer options.
	 */
	private function update_or_add( \WPConfigTransformer $transformer, string $name, string $value, array $options ): void {
		if ( $transformer->exists( 'constant', $name ) ) {
			$transformer->update( 'constant', $name, $value, $options );
		} else {
			$transformer->add( 'constant', $name, $value, $options );
		}
	}

	/**
	 * Get the backup file path in the system temp directory.
	 *
	 * Uses a CSPRNG-derived token (generated once per instance) so that
	 * the backup filename is not predictable from the config path alone.
	 *
	 * @return string Absolute path to the backup file.
	 */
	private function get_backup_path(): string {
		if ( '' === $this->backup_token ) {
			try {
				$this->backup_token = bin2hex( random_bytes( 16 ) );
			} catch ( \Throwable $e ) {
				// Fall back to a deterministic hash if no CSPRNG is available.
				$this->backup_token = md5( $this->config_path );
			}
		}

		return get_temp_dir() . 'wp-system-report-config-' . $this->backup_token . '.bak';
	}

	/**
	 * Create a backup of wp-config.php in the system temp directory.
	 *
	 * The backup is stored outside the webroot with restricted permissions
	 * (0600) so it cannot be served over HTTP.
	 *
	 * @return bool|string True on success, error message on failure.
	 */
	private function create_backup() {
		$backup_path = $this->get_backup_path();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $this->config_path );

		if ( false === $contents ) {
			return __( 'Failed to read wp-config.php for backup.', 'wp-system-report' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $backup_path, $contents );

		if ( false === $result ) {
			return __( 'Failed to create wp-config.php backup.', 'wp-system-report' );
		}

		// Restrict permissions to owner read/write only.
		chmod( $backup_path, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod

		return true;
	}

	/**
	 * Delete the backup file after a successful operation.
	 */
	private function delete_backup(): void {
		$backup_path = $this->get_backup_path();

		if ( file_exists( $backup_path ) ) {
			wp_delete_file( $backup_path );
		}
	}

	/**
	 * Restore wp-config.php from backup.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function restore_backup(): bool {
		$backup_path = $this->get_backup_path();

		if ( ! file_exists( $backup_path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $backup_path );

		if ( false === $contents ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $this->config_path, $contents );

		// Clean up the backup regardless of restore result.
		wp_delete_file( $backup_path );

		return false !== $result;
	}

	/**
	 * Acquire an exclusive file lock for wp-config.php modification.
	 *
	 * Uses a separate lock file in the temp directory rather than
	 * locking wp-config.php itself (WPConfigTransformer opens its own handle).
	 *
	 * @return resource|false File handle on success, false on failure.
	 */
	private function acquire_lock() {
		$lock_path = get_temp_dir() . 'wp-system-report-config.lock';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $lock_path, 'w' );

		if ( ! $handle ) {
			return false;
		}

		// Non-blocking attempt — fail fast if another operation is in progress.
		if ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
			return false;
		}

		return $handle;
	}

	/**
	 * Release a previously acquired file lock.
	 *
	 * @param resource $handle File handle from acquire_lock().
	 */
	private function release_lock( $handle ): void {
		flock( $handle, LOCK_UN );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );
	}

	/**
	 * Parse a raw config value into a boolean.
	 *
	 * @param string $value Raw value from WPConfigTransformer.
	 * @return bool|null Parsed boolean, or null if not a boolean value.
	 */
	private function parse_bool_value( string $value ): ?bool {
		$value = strtolower( trim( $value, "' " ) );

		if ( 'true' === $value ) {
			return true;
		}

		if ( 'false' === $value ) {
			return false;
		}

		return null;
	}

	/**
	 * Get the wp-config.php path.
	 *
	 * @return string The config path.
	 */
	public function get_config_path(): string {
		return $this->config_path;
	}
}
