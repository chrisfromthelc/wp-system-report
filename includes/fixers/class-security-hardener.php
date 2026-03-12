<?php
/**
 * Security hardener fixer.
 *
 * @package SystemReport
 */

namespace SystemReport\Fixers;

use SystemReport\Fixer;
use SystemReport\Fix_Result;
use SystemReport\Risk_Level;

defined( 'ABSPATH' ) || exit;

/**
 * Hardens the WordPress installation by applying security best practices.
 *
 * Detects and remediates common security misconfigurations:
 *
 * - XML-RPC enabled when not needed (attack surface reduction)
 * - File editor enabled (prevents code injection via admin panel)
 * - Missing security headers (clickjacking, MIME-sniffing, XSS protection)
 *
 * All changes are reversible: XML-RPC is disabled via a persistent option,
 * the file editor requires a wp-config.php constant, and security headers
 * are added via a persistent option checked on the `send_headers` action.
 */
class Security_Hardener implements Fixer {

	/**
	 * Option name for storing which hardening measures are active.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'sr_security_hardening';

	/**
	 * Recommended security headers.
	 *
	 * @var array<string, string>
	 */
	private const RECOMMENDED_HEADERS = array(
		'X-Content-Type-Options' => 'nosniff',
		'X-Frame-Options'        => 'SAMEORIGIN',
		'Referrer-Policy'        => 'strict-origin-when-cross-origin',
	);

	/**
	 * Get the unique slug identifier.
	 *
	 * @return string Fixer ID.
	 */
	public function get_id(): string {
		return 'security_hardener';
	}

	/**
	 * Get the human-readable label.
	 *
	 * @return string Translated label.
	 */
	public function get_label(): string {
		return __( 'Security Hardener', 'wp-system-report' );
	}

	/**
	 * Get the fixer description.
	 *
	 * @return string Translated description.
	 */
	public function get_description(): string {
		return __( 'Disables XML-RPC, recommends disabling the file editor, and adds security headers to reduce attack surface.', 'wp-system-report' );
	}

	/**
	 * Get the risk level.
	 *
	 * @return Risk_Level Risk level.
	 */
	public function get_risk_level(): Risk_Level {
		return Risk_Level::Medium;
	}

	/**
	 * Get the category.
	 *
	 * @return string Category slug.
	 */
	public function get_category(): string {
		return 'security';
	}

	/**
	 * Check if any hardening measures can be applied.
	 *
	 * @return bool True when at least one measure is applicable.
	 */
	public function can_fix(): bool {
		if ( $this->is_xmlrpc_enabled() ) {
			return true;
		}

		if ( ! $this->is_file_editor_disabled() ) {
			return true;
		}

		return $this->has_missing_security_headers();
	}

	/**
	 * Execute the security hardening.
	 *
	 * @return Fix_Result Result with before/after snapshots.
	 */
	public function fix(): Fix_Result {
		$before = $this->capture_state();

		if ( ! $before['xmlrpc_enabled'] && $before['file_editor_disabled'] && ! $before['missing_headers'] ) {
			return Fix_Result::success(
				__( 'All security hardening measures are already in place.', 'wp-system-report' )
			);
		}

		$applied = array();
		$skipped = array();

		// Step 1: Disable XML-RPC.
		if ( $before['xmlrpc_enabled'] ) {
			$this->disable_xmlrpc();
			$applied[] = __( 'XML-RPC disabled', 'wp-system-report' );
		}

		// Step 2: File editor — we can only advise, not set the constant at runtime.
		if ( ! $before['file_editor_disabled'] ) {
			$skipped[] = __( 'File editor: add DISALLOW_FILE_EDIT to wp-config.php manually', 'wp-system-report' );
		}

		// Step 3: Enable security headers.
		if ( $before['missing_headers'] ) {
			$this->enable_security_headers();
			$applied[] = sprintf(
				/* translators: %s: comma-separated list of header names */
				__( 'Security headers enabled: %s', 'wp-system-report' ),
				implode( ', ', array_keys( $before['missing_headers_list'] ) )
			);
		}

		$after = $this->capture_state();

		$parts = $applied;
		if ( ! empty( $skipped ) ) {
			$parts = array_merge( $parts, $skipped );
		}

		$message = ! empty( $parts )
			? implode( '; ', $parts ) . '.'
			: __( 'Security hardening complete.', 'wp-system-report' );

		return Fix_Result::success( $message, $before, $after );
	}

	/**
	 * Capture the current security state.
	 *
	 * @return array<string, mixed> State snapshot.
	 */
	private function capture_state(): array {
		$missing_headers = $this->get_missing_security_headers();

		return array(
			'xmlrpc_enabled'       => $this->is_xmlrpc_enabled(),
			'file_editor_disabled' => $this->is_file_editor_disabled(),
			'missing_headers'      => ! empty( $missing_headers ),
			'missing_headers_list' => $missing_headers,
			'hardening_options'    => get_option( self::OPTION_KEY, array() ),
		);
	}

	/**
	 * Check if XML-RPC is currently enabled.
	 *
	 * @return bool True if XML-RPC is enabled.
	 */
	private function is_xmlrpc_enabled(): bool {
		$options = get_option( self::OPTION_KEY, array() );

		// If we've previously disabled it via our option, it's disabled.
		if ( ! empty( $options['xmlrpc_disabled'] ) ) {
			return false;
		}

		/**
		 * Check if XML-RPC is enabled.
		 *
		 * We consider it enabled unless we've explicitly disabled it
		 * or the `xmlrpc_enabled` filter returns false.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress filter.
		return (bool) apply_filters( 'xmlrpc_enabled', true );
	}

	/**
	 * Check if the file editor is disabled.
	 *
	 * @return bool True if DISALLOW_FILE_EDIT is defined and true.
	 */
	private function is_file_editor_disabled(): bool {
		return defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
	}

	/**
	 * Check if any recommended security headers are missing.
	 *
	 * @return bool True if at least one header is missing.
	 */
	private function has_missing_security_headers(): bool {
		return ! empty( $this->get_missing_security_headers() );
	}

	/**
	 * Get the list of missing security headers.
	 *
	 * @return array<string, string> Missing headers (name => recommended value).
	 */
	private function get_missing_security_headers(): array {
		$options         = get_option( self::OPTION_KEY, array() );
		$active_headers  = ! empty( $options['security_headers'] ) ? $options['security_headers'] : array();
		$missing_headers = array();

		foreach ( self::RECOMMENDED_HEADERS as $header_name => $header_value ) {
			if ( ! isset( $active_headers[ $header_name ] ) ) {
				$missing_headers[ $header_name ] = $header_value;
			}
		}

		return $missing_headers;
	}

	/**
	 * Disable XML-RPC by setting a persistent option.
	 *
	 * The option is checked by a filter registered in the plugin constructor.
	 */
	private function disable_xmlrpc(): void {
		$options                    = get_option( self::OPTION_KEY, array() );
		$options['xmlrpc_disabled'] = true;
		update_option( self::OPTION_KEY, $options, false );
	}

	/**
	 * Enable security headers by storing them in the persistent option.
	 *
	 * Headers are sent via a hook on the `send_headers` action.
	 */
	private function enable_security_headers(): void {
		$options                     = get_option( self::OPTION_KEY, array() );
		$options['security_headers'] = self::RECOMMENDED_HEADERS;
		update_option( self::OPTION_KEY, $options, false );
	}

	/**
	 * Apply runtime security hardening based on stored options.
	 *
	 * Called from the plugin's hook registration. This method:
	 * - Adds the `xmlrpc_enabled` filter to disable XML-RPC
	 * - Adds the `send_headers` action to send security headers
	 */
	public static function apply_runtime_hardening(): void {
		$options = get_option( self::OPTION_KEY, array() );

		if ( ! empty( $options['xmlrpc_disabled'] ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
		}

		if ( ! empty( $options['security_headers'] ) && is_array( $options['security_headers'] ) ) {
			add_action(
				'send_headers',
				function () use ( $options ): void {
					foreach ( $options['security_headers'] as $name => $value ) {
						if ( ! headers_sent() ) {
							header( sprintf( '%s: %s', $name, $value ) );
						}
					}
				}
			);
		}
	}
}
