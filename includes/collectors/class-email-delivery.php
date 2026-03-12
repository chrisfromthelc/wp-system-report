<?php
/**
 * Email Delivery collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

use SystemReport\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Collects email delivery configuration and mail service status.
 *
 * Reports the active mail transport method, SMTP configuration,
 * detected mail plugins, and key email-related PHP settings.
 */
class Email_Delivery extends Abstract_Collector {

	/**
	 * Known mail plugins indexed by main plugin file.
	 *
	 * @var array<string, string>
	 */
	private const KNOWN_MAIL_PLUGINS = array(
		'wp-mail-smtp/wp_mail_smtp.php'               => 'WP Mail SMTP',
		'fluent-smtp/fluent-smtp.php'                 => 'FluentSMTP',
		'post-smtp/postman-smtp.php'                  => 'Post SMTP',
		'easy-wp-smtp/easy-wp-smtp.php'               => 'Easy WP SMTP',
		'smtp-mailer/main.php'                        => 'SMTP Mailer',
		'wp-smtp/wp-smtp.php'                         => 'WP SMTP',
		'mailgun/mailgun.php'                         => 'Mailgun',
		'sparkpost/wordpress-sparkpost.php'           => 'SparkPost',
		'sendgrid-email-delivery-simplified/wpsendgrid.php' => 'SendGrid',
		'wp-ses/wp-ses.php'                           => 'WP Offload SES',
		'wp-offload-ses-lite/wp-offload-ses-lite.php' => 'WP Offload SES Lite',
	);

	/**
	 * Get the transient cache key.
	 *
	 * @return string Cache key.
	 */
	protected function get_cache_key(): string {
		return 'sr_email_delivery';
	}

	/**
 * Get the collector ID.
 */
	public function get_id(): string {
		return 'email_delivery';
	}

	/**
 * Get the collector label.
 */
	public function get_label(): string {
		return __( 'Email Delivery', 'wp-system-report' );
	}

	/**
 * Get the collector description.
 */
	public function get_description(): string {
		return __( 'Email configuration, mail transport method, SMTP status, and mail service plugins.', 'wp-system-report' );
	}

	/**
 * Get the collector priority.
 */
	public function get_priority(): int {
		return 180;
	}

	/**
	 * Collect email delivery data.
	 *
	 * @return array Array of Field objects.
	 */
	public function collect(): array {
		$data = array();

		$data[] = $this->collect_admin_email();
		$data[] = $this->collect_from_address();
		$data[] = $this->collect_from_name();
		$data[] = $this->collect_mail_transport();
		$data[] = $this->collect_smtp_host();
		$data[] = $this->collect_smtp_port();
		$data[] = $this->collect_smtp_encryption();
		$data[] = $this->collect_mail_plugin();
		$data[] = $this->collect_phpmailer_override();
		$data[] = $this->collect_sendmail_path();
		$data[] = $this->collect_disable_mail_functions();

		return $data;
	}

	/**
	 * Collect admin email.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_admin_email() {
		$email  = get_option( 'admin_email', '' );
		$status = Status::Info;

		if ( '' === $email ) {
			$status = Status::Critical;
		}

		return $this->make_field(
			__( 'Admin Email', 'wp-system-report' ),
			$email,
			array(
				'status'      => $status,
				'description' => __( 'Site administrator email used as the default sender.', 'wp-system-report' ),
				'private'     => true,
			)
		);
	}

	/**
	 * Collect configured From address.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_from_address() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter.
		$from_email = apply_filters( 'wp_mail_from', get_option( 'admin_email', '' ) );

		return $this->make_field(
			__( 'From Address', 'wp-system-report' ),
			$from_email,
			array(
				'status'      => Status::Info,
				'description' => __( 'The email address used in the From header.', 'wp-system-report' ),
				'private'     => true,
			)
		);
	}

	/**
	 * Collect configured From name.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_from_name() {
		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter.
		$from_name = apply_filters( 'wp_mail_from_name', 'WordPress' );

		$status = 'WordPress' === $from_name ? Status::Warning : Status::Good;

		return $this->make_field(
			__( 'From Name', 'wp-system-report' ),
			$from_name,
			array(
				'status'      => $status,
				'description' => __( 'The name used in the From header. Default "WordPress" is not recommended.', 'wp-system-report' ),
				'recommended' => $site_name,
			)
		);
	}

	/**
	 * Collect the active mail transport method.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_mail_transport() {
		$transport = 'PHP mail()';
		$status    = Status::Info;

		if ( defined( 'WPMS_MAILER' ) ) {
			$transport = (string) WPMS_MAILER;
		}

		// Check phpmailer_init overrides.
		if ( has_action( 'phpmailer_init' ) ) {
			$transport = __( 'Custom (phpmailer_init override)', 'wp-system-report' );
		}

		// Detect SMTP constants.
		if ( defined( 'SMTP' ) && '' !== SMTP && 'localhost' !== SMTP ) {
			$transport = 'SMTP';
			$status    = Status::Good;
		}

		return $this->make_field(
			__( 'Mail Transport', 'wp-system-report' ),
			$transport,
			array(
				'status'      => $status,
				'description' => __( 'The method used to send outgoing email.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect SMTP host.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_smtp_host() {
		$host = $this->get_constant_value( 'SMTP', 'localhost' );

		return $this->make_field(
			__( 'SMTP Host', 'wp-system-report' ),
			$host,
			array(
				'status'      => 'localhost' === $host ? Status::Info : Status::Good,
				'description' => __( 'SMTP server hostname. "localhost" means PHP\'s built-in mail function is used.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect SMTP port.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_smtp_port() {
		$port = $this->get_constant_value( 'SMTP_PORT', '' );

		if ( '' === $port ) {
			// Check php.ini default.
			$ini_port = ini_get( 'smtp_port' );
			$port     = false !== $ini_port && '' !== $ini_port ? $ini_port : '25';
		}

		$status = Status::Info;
		if ( '465' === (string) $port || '587' === (string) $port ) {
			$status = Status::Good;
		} elseif ( '25' === (string) $port ) {
			$status = Status::Warning;
		}

		return $this->make_field(
			__( 'SMTP Port', 'wp-system-report' ),
			(string) $port,
			array(
				'status'      => $status,
				'description' => __( 'Port 25 is unencrypted and often blocked. Port 587 (STARTTLS) or 465 (SSL) recommended.', 'wp-system-report' ),
				'recommended' => '587',
			)
		);
	}

	/**
	 * Collect SMTP encryption setting.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_smtp_encryption() {
		$encryption = __( 'None', 'wp-system-report' );
		$status     = Status::Info;

		if ( defined( 'SMTP_PORT' ) ) {
			$port = (int) SMTP_PORT;

			if ( 465 === $port ) {
				$encryption = 'SSL/TLS';
				$status     = Status::Good;
			} elseif ( 587 === $port ) {
				$encryption = 'STARTTLS';
				$status     = Status::Good;
			}
		}

		return $this->make_field(
			__( 'SMTP Encryption', 'wp-system-report' ),
			$encryption,
			array(
				'status'      => $status,
				'description' => __( 'TLS encryption for SMTP connections.', 'wp-system-report' ),
				'recommended' => 'STARTTLS',
			)
		);
	}

	/**
	 * Collect active mail plugin status.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_mail_plugin() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$detected       = array();

		foreach ( self::KNOWN_MAIL_PLUGINS as $file => $name ) {
			if ( in_array( $file, $active_plugins, true ) ) {
				$detected[] = $name;
			}
		}

		if ( ! empty( $detected ) ) {
			return $this->make_field(
				__( 'Mail Plugin', 'wp-system-report' ),
				implode( ', ', $detected ),
				array(
					'status'      => Status::Good,
					'description' => __( 'Active mail delivery plugin(s) handling outgoing email.', 'wp-system-report' ),
				)
			);
		}

		return $this->make_field(
			__( 'Mail Plugin', 'wp-system-report' ),
			__( 'None detected', 'wp-system-report' ),
			array(
				'status'      => Status::Info,
				'description' => __( 'No dedicated mail plugin detected. Email is sent via PHP\'s built-in mail() function.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Check whether phpmailer_init is being used.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_phpmailer_override() {
		$has_override = has_action( 'phpmailer_init' );

		return $this->make_field(
			__( 'PHPMailer Override', 'wp-system-report' ),
			$this->format_boolean( false !== $has_override ),
			array(
				'status'      => false !== $has_override ? Status::Info : Status::Info,
				'description' => __( 'Whether a plugin or theme has hooked into phpmailer_init to customize mail settings.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Collect sendmail path.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_sendmail_path() {
		$sendmail = ini_get( 'sendmail_path' );
		$value    = false !== $sendmail && '' !== $sendmail ? $sendmail : __( 'Not set', 'wp-system-report' );

		return $this->make_field(
			__( 'Sendmail Path', 'wp-system-report' ),
			$value,
			array(
				'status'      => Status::Info,
				'description' => __( 'Path to the sendmail binary used by PHP\'s mail() function.', 'wp-system-report' ),
			)
		);
	}

	/**
	 * Check for disabled mail functions.
	 *
	 * @return \SystemReport\Field
	 */
	private function collect_disable_mail_functions() {
		$disabled = ini_get( 'disable_functions' );
		$disabled = false !== $disabled ? $disabled : '';

		$mail_disabled = str_contains( $disabled, 'mail' );

		return $this->make_field(
			__( 'PHP mail() Disabled', 'wp-system-report' ),
			$this->format_boolean( $mail_disabled ),
			array(
				'status'      => $mail_disabled ? Status::Critical : Status::Good,
				'description' => __( 'Whether PHP\'s mail() function is disabled via disable_functions.', 'wp-system-report' ),
			)
		);
	}
}
