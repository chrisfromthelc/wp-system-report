<?php
/**
 * Server Environment collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects server software, PHP configuration, and database settings.
 */
class Server_Environment extends Abstract_Collector {

	/**
 * Get the collector ID.
 */
	public function get_id(): string {
		return 'server_environment';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Server Environment', 'wp-system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Server software, PHP configuration, and database settings.', 'wp-system-report' );
	}

	/**
 * Get the collector priority.
 */
	public function get_priority(): int {
		return 20;
	}

	/**
 * Collect the data.
 */
	public function collect(): array {
		global $wpdb;

		$data = array();

		// Server Software.
		$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown';
		$data[]          = $this->make_field(
			__( 'Server Software', 'wp-system-report' ),
			$server_software
		);

		// PHP Version.
		$php_version        = phpversion();
		$php_version_status = 'info';
		if ( version_compare( $php_version, '8.0', '<' ) ) {
			$php_version_status = 'critical';
		} elseif ( version_compare( $php_version, '8.1', '<' ) ) {
			$php_version_status = 'warning';
		} else {
			$php_version_status = 'good';
		}

		$data[] = $this->make_field(
			__( 'PHP Version', 'wp-system-report' ),
			$php_version,
			array(
				'status'      => $php_version_status,
				'recommended' => '>= 8.1',
			)
		);

		// PHP Post Max Size.
		$data[] = $this->make_field(
			__( 'PHP Post Max Size', 'wp-system-report' ),
			ini_get( 'post_max_size' )
		);

		// PHP Max Execution Time.
		$data[] = $this->make_field(
			__( 'PHP Max Execution Time', 'wp-system-report' ),
			ini_get( 'max_execution_time' ),
			array( 'recommended' => '>= 30' )
		);

		// PHP Max Input Vars.
		$data[] = $this->make_field(
			__( 'PHP Max Input Vars', 'wp-system-report' ),
			ini_get( 'max_input_vars' ),
			array( 'recommended' => '>= 1000' )
		);

		// PHP Memory Limit.
		$data[] = $this->make_field(
			__( 'PHP Memory Limit', 'wp-system-report' ),
			ini_get( 'memory_limit' ),
			array( 'recommended' => '>= 128M' )
		);

		// Max Upload Size.
		$data[] = $this->make_field(
			__( 'Max Upload Size', 'wp-system-report' ),
			size_format( wp_max_upload_size() )
		);

		// MySQL Version.
		$mysql_version = $wpdb->get_var( 'SELECT VERSION()' );
		$data[]        = $this->make_field(
			__( 'MySQL Version', 'wp-system-report' ),
			$mysql_version ? $mysql_version : __( 'Unknown', 'wp-system-report' )
		);

		// cURL Version.
		$curl_available = function_exists( 'curl_version' );
		$curl_version   = $curl_available ? curl_version()['version'] : __( 'Not available', 'wp-system-report' );
		$curl_status    = $curl_available ? 'good' : 'warning';

		$data[] = $this->make_field(
			__( 'cURL Version', 'wp-system-report' ),
			$curl_version,
			array( 'status' => $curl_status )
		);

		// DOMDocument.
		$dom_available = class_exists( 'DOMDocument' );
		$data[]        = $this->make_field(
			__( 'DOMDocument', 'wp-system-report' ),
			$this->format_boolean( $dom_available ),
			array( 'status' => $dom_available ? 'good' : 'warning' )
		);

		// GZip.
		$gzip_available = is_callable( 'gzopen' );
		$data[]         = $this->make_field(
			__( 'GZip', 'wp-system-report' ),
			$this->format_boolean( $gzip_available ),
			array( 'status' => $gzip_available ? 'good' : 'warning' )
		);

		// Multibyte String.
		$mbstring_available = extension_loaded( 'mbstring' );
		$data[]             = $this->make_field(
			__( 'Multibyte String', 'wp-system-report' ),
			$this->format_boolean( $mbstring_available ),
			array( 'status' => $mbstring_available ? 'good' : 'warning' )
		);

		// OpenSSL.
		$openssl_available = extension_loaded( 'openssl' );
		$data[]            = $this->make_field(
			__( 'OpenSSL', 'wp-system-report' ),
			$this->format_boolean( $openssl_available ),
			array( 'status' => $openssl_available ? 'good' : 'warning' )
		);

		// Imagick.
		$imagick_available = extension_loaded( 'imagick' );
		$data[]            = $this->make_field(
			__( 'Imagick', 'wp-system-report' ),
			$this->format_boolean( $imagick_available )
		);

		return $data;
	}
}
