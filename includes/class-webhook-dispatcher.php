<?php
/**
 * Webhook dispatcher.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Dispatches webhook payloads to configured URLs.
 *
 * Webhooks are fired when a report contains critical or warning-level
 * findings that exceed configured thresholds. Payloads are sent as
 * JSON POST requests with HMAC-SHA256 signatures for verification.
 *
 * All webhook delivery is non-blocking: payloads are sent via
 * `wp_remote_post()` with a short timeout to avoid slowing down
 * report generation.
 */
class Webhook_Dispatcher {

	/**
	 * User-agent string for webhook requests.
	 *
	 * @var string
	 */
	private const USER_AGENT = 'WP-System-Report-Webhook/1.0';

	/**
	 * Maximum timeout in seconds for webhook delivery.
	 *
	 * @var int
	 */
	private const TIMEOUT = 5;

	/**
	 * Dispatch a webhook payload to all configured URLs.
	 *
	 * @param string $event   Event name (e.g. 'report.critical', 'report.warning').
	 * @param array  $payload Event payload data.
	 * @return array<string, bool> Results keyed by URL, true if sent successfully.
	 */
	public function dispatch( string $event, array $payload ): array {
		$urls = $this->get_webhook_urls();

		if ( empty( $urls ) ) {
			return array();
		}

		$secret  = $this->get_webhook_secret();
		$results = array();

		$body = wp_json_encode(
			array(
				'event'      => $event,
				'timestamp'  => gmdate( 'c' ),
				'site_url'   => get_option( 'home' ),
				'site_name'  => get_option( 'blogname' ),
				'plugin_ver' => defined( 'WP_SYSTEM_REPORT_VERSION' ) ? WP_SYSTEM_REPORT_VERSION : 'unknown',
				'payload'    => $payload,
			)
		);

		if ( false === $body ) {
			return array();
		}

		$signature = hash_hmac( 'sha256', $body, $secret );

		foreach ( $urls as $url ) {
			$results[ $url ] = $this->send( $url, $body, $signature, $event );
		}

		/**
		 * Fires after webhook dispatch completes.
		 *
		 * @param string $event   The event name.
		 * @param array  $payload The event payload.
		 * @param array  $results Dispatch results keyed by URL.
		 */
		do_action( 'wp_system_report_webhooks_dispatched', $event, $payload, $results );

		return $results;
	}

	/**
	 * Send a single webhook request.
	 *
	 * @param string $url       Target URL.
	 * @param string $body      JSON-encoded body.
	 * @param string $signature HMAC-SHA256 signature.
	 * @param string $event     Event name.
	 * @return bool True if the request was accepted (2xx status).
	 */
	private function send( string $url, string $body, string $signature, string $event ): bool {
		/**
		 * Filter the webhook request arguments before sending.
		 *
		 * @param array  $args  WP HTTP API arguments.
		 * @param string $url   Target URL.
		 * @param string $event Event name.
		 */
		$args = apply_filters(
			'wp_system_report_webhook_args',
			array(
				'method'    => 'POST',
				'timeout'   => self::TIMEOUT,
				'blocking'  => false,
				'headers'   => array(
					'Content-Type'                 => 'application/json',
					'User-Agent'                   => self::USER_AGENT,
					'X-WP-System-Report-Event'     => $event,
					'X-WP-System-Report-Signature' => $signature,
				),
				'body'      => $body,
				'sslverify' => true,
			),
			$url,
			$event
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		return $code >= 200 && $code < 300;
	}

	/**
	 * Get configured webhook URLs.
	 *
	 * @return string[] Array of valid URLs.
	 */
	private function get_webhook_urls(): array {
		$raw = Settings::get( 'webhook_urls', '' );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		// Support comma-separated or newline-separated URLs.
		$urls = preg_split( '/[\r\n,]+/', $raw );

		if ( false === $urls ) {
			return array();
		}

		$urls = array_map( 'trim', $urls );
		$urls = array_filter( $urls, array( $this, 'is_valid_url' ) );

		/**
		 * Filter the webhook URLs before dispatch.
		 *
		 * @param string[] $urls Validated webhook URLs.
		 */
		return (array) apply_filters( 'wp_system_report_webhook_urls', array_values( $urls ) );
	}

	/**
	 * Get the webhook signing secret.
	 *
	 * If no secret is configured, generates and stores one automatically.
	 *
	 * @return string HMAC signing secret.
	 */
	private function get_webhook_secret(): string {
		$secret = Settings::get( 'webhook_secret', '' );

		if ( is_string( $secret ) && '' !== $secret ) {
			return $secret;
		}

		// Auto-generate a secret on first use.
		$secret = wp_generate_password( 40, false );
		Settings::update( 'webhook_secret', $secret );

		return $secret;
	}

	/**
	 * Validate a webhook URL.
	 *
	 * Only HTTPS URLs are accepted to prevent credentials leaking
	 * over unencrypted connections.
	 *
	 * @param string $url URL to validate.
	 * @return bool True if the URL is valid for webhook delivery.
	 */
	private function is_valid_url( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		$parsed = wp_parse_url( $url );

		if ( false === $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return false;
		}

		// Allow http only for localhost development.
		if ( 'http' === $parsed['scheme'] ) {
			return in_array( $parsed['host'], array( 'localhost', '127.0.0.1', '::1' ), true );
		}

		return 'https' === $parsed['scheme'];
	}
}
