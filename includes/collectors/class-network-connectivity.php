<?php
/**
 * Network & Connectivity collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

use SystemReport\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Collects outbound HTTP connectivity, proxy configuration,
 * loopback request health, and SSL certificate details.
 */
class Network_Connectivity extends Abstract_Collector {

	/**
	 * Get the transient cache key.
	 *
	 * @return string Cache key.
	 */
	protected function get_cache_key(): string {
		return 'sr_network_connectivity';
	}

	/**
	 * Get the collector ID.
	 */
	public function get_id(): string {
		return 'network_connectivity';
	}

	/**
	 * Get the collector label.
	 */
	public function get_label(): string {
		return __( 'Network & Connectivity', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 */
	public function get_description(): string {
		return __( 'Outbound HTTP connectivity, proxy configuration, loopback health, and SSL certificate status.', 'wp-system-report' );
	}

	/**
	 * Get the collector priority.
	 */
	public function get_priority(): int {
		return 220;
	}

	/**
	 * Collect network and connectivity data.
	 *
	 * @return array Array of Field objects.
	 */
	public function collect(): array {
		$data = array();

		$data[] = $this->collect_wordpress_org_connectivity();
		$data[] = $this->collect_downloads_connectivity();
		$data[] = $this->collect_loopback_request();
		$data[] = $this->collect_http_proxy();
		$data[] = $this->collect_http_transport();
		$data[] = $this->collect_ssl_certificate();
		$data[] = $this->collect_ssl_verification();
		$data[] = $this->collect_external_http_blocked();
		$data[] = $this->collect_dns_resolution();
		$data[] = $this->collect_curl_version();

		return $data;
	}

	/**
	 * Test outbound HTTP to api.wordpress.org.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_wordpress_org_connectivity() {
		$response = wp_remote_get(
			'https://api.wordpress.org/core/version-check/1.7/',
			array(
				'timeout'   => 10,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->make_field(
				__( 'WordPress.org API', 'wp-system-report' ),
				$response->get_error_message(),
				array(
					'status'      => Status::Critical,
					'description' => __( 'Connectivity to api.wordpress.org for updates and plugin information.', 'wp-system-report' ),
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		return $this->make_field(
			__( 'WordPress.org API', 'wp-system-report' ),
			200 === $code
				? __( 'Connected', 'wp-system-report' )
				/* translators: %d: HTTP status code */
				: sprintf( __( 'HTTP %d', 'wp-system-report' ), $code ),
			array(
				'status'      => 200 === $code ? Status::Good : Status::Warning,
				'description' => __( 'Connectivity to api.wordpress.org for updates and plugin information.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Test outbound HTTP to downloads.wordpress.org.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_downloads_connectivity() {
		$response = wp_remote_head(
			'https://downloads.wordpress.org/',
			array(
				'timeout'   => 10,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->make_field(
				__( 'WordPress.org Downloads', 'wp-system-report' ),
				$response->get_error_message(),
				array(
					'status'      => Status::Critical,
					'description' => __( 'Connectivity to downloads.wordpress.org for core, plugin, and theme downloads.', 'wp-system-report' ),
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		return $this->make_field(
			__( 'WordPress.org Downloads', 'wp-system-report' ),
			$code >= 200 && $code < 400
				? __( 'Connected', 'wp-system-report' )
				/* translators: %d: HTTP status code */
				: sprintf( __( 'HTTP %d', 'wp-system-report' ), $code ),
			array(
				'status'      => $code >= 200 && $code < 400 ? Status::Good : Status::Warning,
				'description' => __( 'Connectivity to downloads.wordpress.org for core, plugin, and theme downloads.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Test loopback request to the site itself.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_loopback_request() {
		$url      = rest_url( 'wp/v2/types' );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 10,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->make_field(
				__( 'Loopback Request', 'wp-system-report' ),
				$response->get_error_message(),
				array(
					'status'      => Status::Critical,
					'description' => __( 'Whether the site can make HTTP requests to itself. Required for cron, REST API, and Site Health.', 'wp-system-report' ),
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		return $this->make_field(
			__( 'Loopback Request', 'wp-system-report' ),
			200 === $code
				? __( 'Connected', 'wp-system-report' )
				/* translators: %d: HTTP status code */
				: sprintf( __( 'HTTP %d', 'wp-system-report' ), $code ),
			array(
				'status'      => 200 === $code ? Status::Good : Status::Warning,
				'description' => __( 'Whether the site can make HTTP requests to itself. Required for cron, REST API, and Site Health.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Detect HTTP proxy configuration.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_http_proxy() {
		$proxy_host = defined( 'WP_PROXY_HOST' ) ? WP_PROXY_HOST : '';
		$proxy_port = defined( 'WP_PROXY_PORT' ) ? WP_PROXY_PORT : '';

		if ( empty( $proxy_host ) ) {
			return $this->make_field(
				__( 'HTTP Proxy', 'wp-system-report' ),
				__( 'Not configured', 'wp-system-report' ),
				array(
					'status'      => Status::Info,
					'description' => __( 'Whether an HTTP proxy is configured via WP_PROXY_HOST.', 'wp-system-report' ),
				)
			);
		}

		$value = $proxy_host;
		if ( ! empty( $proxy_port ) ) {
			$value .= ':' . $proxy_port;
		}

		return $this->make_field(
			__( 'HTTP Proxy', 'wp-system-report' ),
			$value,
			array(
				'status'      => Status::Info,
				'description' => __( 'Whether an HTTP proxy is configured via WP_PROXY_HOST.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Detect the HTTP transport method.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_http_transport() {
		$transports = array();

		if ( function_exists( 'curl_version' ) ) {
			$transports[] = 'cURL';
		}

		if ( function_exists( 'fsockopen' ) || function_exists( 'stream_socket_client' ) ) {
			$transports[] = 'Streams';
		}

		$value = ! empty( $transports ) ? implode( ', ', $transports ) : __( 'None available', 'wp-system-report' );

		return $this->make_field(
			__( 'HTTP Transport', 'wp-system-report' ),
			$value,
			array(
				'status'      => ! empty( $transports ) ? Status::Good : Status::Critical,
				'description' => __( 'Available HTTP transport methods for outbound requests.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect SSL certificate information for the site.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_ssl_certificate() {
		$site_url = site_url();
		$parsed   = wp_parse_url( $site_url );

		if ( ! isset( $parsed['scheme'] ) || 'https' !== $parsed['scheme'] ) {
			return $this->make_field(
				__( 'SSL Certificate', 'wp-system-report' ),
				__( 'Not applicable (HTTP)', 'wp-system-report' ),
				array(
					'status'      => Status::Info,
					'description' => __( 'SSL certificate status and expiry for this site.', 'wp-system-report' ),
				)
			);
		}

		$host = $parsed['host'] ?? '';
		$port = $parsed['port'] ?? 443;

		/*
		 * `verify_peer` is intentionally disabled here because the purpose of this
		 * stream connection is solely to capture the raw peer certificate for
		 * inspection (expiry date, issuer). Full chain validation is not needed for
		 * this diagnostic check, and enabling it would cause the connection to fail
		 * on sites with self-signed or misconfigured certificates — exactly the cases
		 * we need to diagnose. The SSL verification posture of outbound WordPress
		 * HTTP requests is reported separately via collect_ssl_verification().
		 */
		$context = stream_context_create(
			array(
				'ssl' => array(
					'capture_peer_cert' => true,
					'verify_peer'       => false,
				),
			)
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.PHP.NoSilencedErrors.Discouraged -- SSL peer cert inspection requires stream_socket_client; it may warn on connection failure.
		$client = @stream_socket_client(
			'ssl://' . $host . ':' . $port,
			$errno,
			$errstr,
			10,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ( false === $client ) {
			return $this->make_field(
				__( 'SSL Certificate', 'wp-system-report' ),
				__( 'Unable to connect', 'wp-system-report' ),
				array(
					'status'      => Status::Warning,
					'description' => __( 'SSL certificate status and expiry for this site.', 'wp-system-report' ),
				)
			);
		}

		$params = stream_context_get_params( $client );
		fclose( $client ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing SSL stream handle.

		if ( ! isset( $params['options']['ssl']['peer_certificate'] ) ) {
			return $this->make_field(
				__( 'SSL Certificate', 'wp-system-report' ),
				__( 'No certificate found', 'wp-system-report' ),
				array(
					'status'      => Status::Warning,
					'description' => __( 'SSL certificate status and expiry for this site.', 'wp-system-report' ),
				)
			);
		}

		$cert_info  = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] );
		$valid_to   = $cert_info['validTo_time_t'] ?? 0;
		$days_until = (int) round( ( $valid_to - time() ) / DAY_IN_SECONDS );

		if ( $days_until < 0 ) {
			$value  = __( 'Expired', 'wp-system-report' );
			$status = Status::Critical;
		} elseif ( $days_until <= 30 ) {
			/* translators: %d: number of days */
			$value  = sprintf( __( 'Expires in %d days', 'wp-system-report' ), $days_until );
			$status = Status::Warning;
		} else {
			/* translators: %d: number of days */
			$value  = sprintf( __( 'Valid (%d days remaining)', 'wp-system-report' ), $days_until );
			$status = Status::Good;
		}

		$issuer = $cert_info['issuer']['O'] ?? $cert_info['issuer']['CN'] ?? __( 'Unknown', 'wp-system-report' );
		$value .= ' — ' . $issuer;

		return $this->make_field(
			__( 'SSL Certificate', 'wp-system-report' ),
			$value,
			array(
				'status'      => $status,
				'description' => __( 'SSL certificate status and expiry for this site.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Check if WordPress is verifying SSL certificates.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_ssl_verification() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter.
		$verify = apply_filters( 'https_ssl_verify', true );

		return $this->make_field(
			__( 'SSL Verification', 'wp-system-report' ),
			$this->format_boolean( $verify ),
			array(
				'status'      => $verify ? Status::Good : Status::Warning,
				'description' => __( 'Whether WordPress verifies SSL certificates for outbound HTTPS requests.', 'wp-system-report' ),
				'recommended' => __( 'Yes', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Check if external HTTP requests are blocked.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_external_http_blocked() {
		$blocked = defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL;

		$value = $this->format_boolean( $blocked );
		if ( $blocked && defined( 'WP_ACCESSIBLE_HOSTS' ) && WP_ACCESSIBLE_HOSTS ) {
			/* translators: %s: allowed hosts */
			$value .= ' — ' . sprintf( __( 'Allowed: %s', 'wp-system-report' ), WP_ACCESSIBLE_HOSTS );
		}

		return $this->make_field(
			__( 'External HTTP Blocked', 'wp-system-report' ),
			$value,
			array(
				'status'      => $blocked ? Status::Warning : Status::Good,
				'description' => __( 'Whether WP_HTTP_BLOCK_EXTERNAL prevents outbound HTTP requests.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Test DNS resolution capability.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_dns_resolution() {
		if ( ! function_exists( 'dns_get_record' ) ) {
			return $this->make_field(
				__( 'DNS Resolution', 'wp-system-report' ),
				__( 'dns_get_record() unavailable', 'wp-system-report' ),
				array(
					'status'      => Status::Info,
					'description' => __( 'Whether the server can resolve DNS names.', 'wp-system-report' ),
				)
			);
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- dns_get_record may emit warnings on failure.
		$result = @dns_get_record( 'api.wordpress.org', DNS_A );

		if ( false === $result || empty( $result ) ) {
			return $this->make_field(
				__( 'DNS Resolution', 'wp-system-report' ),
				__( 'Failed', 'wp-system-report' ),
				array(
					'status'      => Status::Critical,
					'description' => __( 'Whether the server can resolve DNS names.', 'wp-system-report' ),
				)
			);
		}

		return $this->make_field(
			__( 'DNS Resolution', 'wp-system-report' ),
			__( 'Working', 'wp-system-report' ),
			array(
				'status'      => Status::Good,
				'description' => __( 'Whether the server can resolve DNS names.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect cURL version if available.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_curl_version() {
		if ( ! function_exists( 'curl_version' ) ) {
			return $this->make_field(
				__( 'cURL Version', 'wp-system-report' ),
				__( 'Not available', 'wp-system-report' ),
				array(
					'status'      => Status::Warning,
					'description' => __( 'cURL library version used for HTTP requests.', 'wp-system-report' ),
				)
			);
		}

		$curl_info = curl_version();
		$value     = $curl_info['version'] ?? __( 'Unknown', 'wp-system-report' );

		if ( ! empty( $curl_info['ssl_version'] ) ) {
			$value .= ' / ' . $curl_info['ssl_version'];
		}

		return $this->make_field(
			__( 'cURL Version', 'wp-system-report' ),
			$value,
			array(
				'status'      => Status::Good,
				'description' => __( 'cURL library version used for HTTP requests.', 'wp-system-report' ),
			)
		);
	}
}
