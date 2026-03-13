<?php
/**
 * Notification manager.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates alert notifications based on report findings.
 *
 * Analyses report data after generation, determines whether alert
 * thresholds have been exceeded, and dispatches notifications via
 * configured channels (webhooks, email, Slack).
 *
 * Notification frequency is controlled by a cooldown period to
 * prevent alert fatigue. A transient stores the last notification
 * timestamp and prevents re-firing within the configured window.
 */
class Notification_Manager {

	/**
	 * Transient key for the notification cooldown.
	 *
	 * @var string
	 */
	private const COOLDOWN_TRANSIENT = 'sr_notification_cooldown';

	/**
	 * Default cooldown period in seconds (1 hour).
	 *
	 * @var int
	 */
	private const DEFAULT_COOLDOWN = 3600;

	/**
	 * Webhook dispatcher instance.
	 */
	private Webhook_Dispatcher $webhook_dispatcher;

	/**
	 * Constructor.
	 *
	 * @param Webhook_Dispatcher $webhook_dispatcher Webhook dispatcher instance.
	 */
	public function __construct( Webhook_Dispatcher $webhook_dispatcher ) {
		$this->webhook_dispatcher = $webhook_dispatcher;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * Hooks into the report generation lifecycle to evaluate alert
	 * conditions after each report is produced.
	 */
	public function register_hooks(): void {
		add_action( 'wp_system_report_generated', array( $this, 'evaluate_and_notify' ), 20, 1 );
	}

	/**
	 * Evaluate report data and send notifications if thresholds are exceeded.
	 *
	 * Called automatically after report generation. Analyses the report for
	 * critical and warning-level findings, then dispatches notifications
	 * via all enabled channels.
	 *
	 * @param array $report_data Full report data from Report_Generator.
	 */
	public function evaluate_and_notify( array $report_data ): void {
		if ( ! $this->is_notifications_enabled() ) {
			return;
		}

		if ( $this->is_in_cooldown() ) {
			return;
		}

		$findings = $this->analyse_report( $report_data );

		if ( empty( $findings['critical'] ) && empty( $findings['warnings'] ) ) {
			return;
		}

		$should_notify = $this->exceeds_thresholds( $findings );

		if ( ! $should_notify ) {
			return;
		}

		/**
		 * Filter the notification findings before dispatch.
		 *
		 * Allows third-party code to modify, suppress, or enrich the
		 * findings before notifications are sent.
		 *
		 * @param array $findings    Analysed findings with 'critical' and 'warnings' arrays.
		 * @param array $report_data The full report data.
		 */
		$findings = apply_filters( 'wp_system_report_notification_findings', $findings, $report_data );

		if ( empty( $findings['critical'] ) && empty( $findings['warnings'] ) ) {
			return;
		}

		$this->dispatch_notifications( $findings );
		$this->set_cooldown();
	}

	/**
	 * Analyse report data for actionable findings.
	 *
	 * Iterates through all report sections and extracts fields with
	 * critical or warning status, excluding private fields.
	 *
	 * @param array $report_data Full report data.
	 * @return array{critical: array<array{section: string, label: string, value: string}>, warnings: array<array{section: string, label: string, value: string}>}
	 */
	private function analyse_report( array $report_data ): array {
		$critical = array();
		$warnings = array();

		foreach ( $report_data as $section_id => $section ) {
			if ( empty( $section['fields'] ) ) {
				continue;
			}

			$section_label = $section['label'] ?? $section_id;

			foreach ( $section['fields'] as $field ) {
				if ( ! empty( $field['private'] ) ) {
					continue;
				}

				$status = Field::get_status_string( $field );

				$entry = array(
					'section' => $section_label,
					'label'   => $field['label'] ?? '',
					'value'   => $field['value'] ?? '',
				);

				if ( 'critical' === $status ) {
					$critical[] = $entry;
				} elseif ( 'warning' === $status ) {
					$warnings[] = $entry;
				}
			}
		}

		return array(
			'critical' => $critical,
			'warnings' => $warnings,
		);
	}

	/**
	 * Check whether findings exceed the configured alert thresholds.
	 *
	 * @param array $findings Analysed findings.
	 * @return bool True if notifications should be sent.
	 */
	private function exceeds_thresholds( array $findings ): bool {
		// Critical findings always trigger notifications.
		$critical_threshold = (int) Settings::get( 'notify_critical_threshold', 1 );
		if ( count( $findings['critical'] ) >= $critical_threshold ) {
			return true;
		}

		// Warnings trigger only if threshold is met.
		$warning_threshold = (int) Settings::get( 'notify_warning_threshold', 5 );
		if ( count( $findings['warnings'] ) >= $warning_threshold ) {
			return true;
		}

		return false;
	}

	/**
	 * Dispatch notifications via all enabled channels.
	 *
	 * @param array $findings Analysed findings.
	 */
	private function dispatch_notifications( array $findings ): void {
		$this->dispatch_webhooks( $findings );
		$this->dispatch_email( $findings );
		$this->dispatch_slack( $findings );

		/**
		 * Fires after all notifications have been dispatched.
		 *
		 * Allows third-party code to add custom notification channels.
		 *
		 * @param array $findings The findings that triggered notifications.
		 */
		do_action( 'wp_system_report_notifications_sent', $findings );
	}

	/**
	 * Dispatch webhook notifications.
	 *
	 * Only sends when the webhook channel is enabled via the
	 * `notify_webhook_enabled` setting, consistent with the email and
	 * Slack channel guards.
	 *
	 * @param array $findings Analysed findings.
	 */
	private function dispatch_webhooks( array $findings ): void {
		if ( ! $this->is_channel_enabled( 'webhook' ) ) {
			return;
		}

		$event = ! empty( $findings['critical'] ) ? 'report.critical' : 'report.warning';

		$this->webhook_dispatcher->dispatch(
			$event,
			array(
				'summary'  => $this->build_summary( $findings ),
				'critical' => $findings['critical'],
				'warnings' => $findings['warnings'],
			)
		);
	}

	/**
	 * Dispatch email notifications.
	 *
	 * @param array $findings Analysed findings.
	 */
	private function dispatch_email( array $findings ): void {
		if ( ! $this->is_channel_enabled( 'email' ) ) {
			return;
		}

		$recipients = $this->get_email_recipients();

		if ( empty( $recipients ) ) {
			return;
		}

		$site_name = get_option( 'blogname' );
		$severity  = ! empty( $findings['critical'] ) ? 'Critical' : 'Warning';
		$subject   = sprintf(
			/* translators: 1: severity level, 2: site name */
			__( '[%1$s] WP System Report Alert — %2$s', 'wp-system-report' ),
			$severity,
			$site_name
		);

		$body = $this->build_email_body( $findings );

		/**
		 * Filter the notification email arguments.
		 *
		 * @param array $email_args {
		 *     Email arguments.
		 *
		 *     @type string[] $recipients Email recipients.
		 *     @type string   $subject    Email subject.
		 *     @type string   $body       Email body (plain text).
		 * }
		 * @param array $findings   The findings that triggered the notification.
		 */
		$email_args = apply_filters(
			'wp_system_report_notification_email',
			array(
				'recipients' => $recipients,
				'subject'    => $subject,
				'body'       => $body,
			),
			$findings
		);

		// Send one email per recipient to avoid exposing addresses in the
		// To: header. Using a shared To: field would leak every recipient's
		// address to all others, which is a privacy concern.
		foreach ( (array) $email_args['recipients'] as $recipient ) {
			wp_mail( $recipient, $email_args['subject'], $email_args['body'] );
		}
	}

	/**
	 * Dispatch Slack notifications.
	 *
	 * @param array $findings Analysed findings.
	 */
	private function dispatch_slack( array $findings ): void {
		if ( ! $this->is_channel_enabled( 'slack' ) ) {
			return;
		}

		$webhook_url = Settings::get( 'slack_webhook_url', '' );

		if ( ! is_string( $webhook_url ) || '' === $webhook_url ) {
			return;
		}

		$site_name = get_option( 'blogname' );
		$site_url  = get_option( 'home' );
		$severity  = ! empty( $findings['critical'] ) ? ':rotating_light: Critical' : ':warning: Warning';
		$summary   = $this->build_summary( $findings );

		$blocks = array(
			array(
				'type' => 'header',
				'text' => array(
					'type' => 'plain_text',
					'text' => sprintf( '%s — %s', $severity, $site_name ),
				),
			),
			array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => $summary,
				),
			),
		);

		// Add critical findings.
		if ( ! empty( $findings['critical'] ) ) {
			$items = array_slice( $findings['critical'], 0, 10 );
			$text  = "*Critical Issues:*\n";
			foreach ( $items as $item ) {
				$text .= sprintf( "• %s — %s: %s\n", $item['section'], $item['label'], $item['value'] );
			}
			$blocks[] = array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => $text,
				),
			);
		}

		// Add warning findings (limited).
		if ( ! empty( $findings['warnings'] ) ) {
			$items = array_slice( $findings['warnings'], 0, 5 );
			$text  = "*Warnings:*\n";
			foreach ( $items as $item ) {
				$text .= sprintf( "• %s — %s: %s\n", $item['section'], $item['label'], $item['value'] );
			}
			if ( count( $findings['warnings'] ) > 5 ) {
				$text .= sprintf( '_...and %d more_', count( $findings['warnings'] ) - 5 );
			}
			$blocks[] = array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => $text,
				),
			);
		}

		$blocks[] = array(
			'type'     => 'context',
			'elements' => array(
				array(
					'type' => 'mrkdwn',
					'text' => sprintf( '<%s|View Site> | Generated by WP System Report', $site_url ),
				),
			),
		);

		/**
		 * Filter the Slack message payload.
		 *
		 * @param array $slack_payload The Slack Block Kit payload.
		 * @param array $findings      The findings that triggered the notification.
		 */
		$slack_payload = apply_filters(
			'wp_system_report_slack_payload',
			array( 'blocks' => $blocks ),
			$findings
		);

		wp_remote_post(
			$webhook_url,
			array(
				'method'   => 'POST',
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( $slack_payload ),
			)
		);
	}

	/**
	 * Build a human-readable summary of findings.
	 *
	 * @param array $findings Analysed findings.
	 * @return string Summary text.
	 */
	private function build_summary( array $findings ): string {
		$parts = array();

		$critical_count = count( $findings['critical'] );
		$warning_count  = count( $findings['warnings'] );

		if ( $critical_count > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of critical issues */
				_n( '%d critical issue', '%d critical issues', $critical_count, 'wp-system-report' ),
				$critical_count
			);
		}

		if ( $warning_count > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of warnings */
				_n( '%d warning', '%d warnings', $warning_count, 'wp-system-report' ),
				$warning_count
			);
		}

		return implode( ', ', $parts ) . ' detected.';
	}

	/**
	 * Build the plain-text email body.
	 *
	 * @param array $findings Analysed findings.
	 * @return string Email body text.
	 */
	private function build_email_body( array $findings ): string {
		$site_name = get_option( 'blogname' );
		$site_url  = get_option( 'home' );
		$summary   = $this->build_summary( $findings );

		$body  = sprintf( "WP System Report Alert — %s\n", $site_name );
		$body .= sprintf( "%s\n\n", $site_url );
		$body .= sprintf( "%s\n\n", $summary );

		if ( ! empty( $findings['critical'] ) ) {
			$body .= "CRITICAL ISSUES\n";
			$body .= str_repeat( '-', 40 ) . "\n";
			foreach ( $findings['critical'] as $item ) {
				$body .= sprintf( "  [%s] %s: %s\n", $item['section'], $item['label'], $item['value'] );
			}
			$body .= "\n";
		}

		if ( ! empty( $findings['warnings'] ) ) {
			$body .= "WARNINGS\n";
			$body .= str_repeat( '-', 40 ) . "\n";
			foreach ( $findings['warnings'] as $item ) {
				$body .= sprintf( "  [%s] %s: %s\n", $item['section'], $item['label'], $item['value'] );
			}
			$body .= "\n";
		}

		$body .= "---\n";
		$body .= "This alert was sent by WP System Report.\n";

		return $body . "Manage notification settings in your WordPress admin.\n";
	}

	/**
	 * Check whether notifications are globally enabled.
	 *
	 * @return bool True if notifications are enabled.
	 */
	private function is_notifications_enabled(): bool {
		return (bool) Settings::get( 'notifications_enabled', false );
	}

	/**
	 * Check whether a specific notification channel is enabled.
	 *
	 * @param string $channel Channel name ('email', 'slack', 'webhook').
	 * @return bool True if the channel is enabled.
	 */
	private function is_channel_enabled( string $channel ): bool {
		$key = 'notify_' . $channel . '_enabled';
		return (bool) Settings::get( $key, false );
	}

	/**
	 * Get email notification recipients.
	 *
	 * @return string[] Array of valid email addresses.
	 */
	private function get_email_recipients(): array {
		return self::parse_email_recipients();
	}

	/**
	 * Parse and validate the configured email recipient list.
	 *
	 * Shared helper used by both the notification manager and the
	 * notification REST controller to ensure consistent behaviour.
	 * Accepts a comma- or newline-separated list stored in settings;
	 * falls back to the site admin email when no recipients are configured.
	 *
	 * @return string[] Array of valid, sanitised email addresses.
	 */
	public static function parse_email_recipients(): array {
		$raw = Settings::get( 'notify_email_recipients', '' );

		if ( ! is_string( $raw ) || '' === $raw ) {
			// Default to the site admin email.
			$admin_email = get_option( 'admin_email' );
			return $admin_email ? array( $admin_email ) : array();
		}

		$emails = preg_split( '/[\r\n,]+/', $raw );

		if ( false === $emails ) {
			return array();
		}

		$emails = array_map( 'trim', $emails );
		$emails = array_filter( $emails, 'is_email' );

		return array_values( $emails );
	}

	/**
	 * Check whether the notification cooldown is active.
	 *
	 * @return bool True if in cooldown (notifications suppressed).
	 */
	private function is_in_cooldown(): bool {
		return false !== get_transient( self::COOLDOWN_TRANSIENT );
	}

	/**
	 * Set the notification cooldown.
	 */
	private function set_cooldown(): void {
		$cooldown = (int) Settings::get( 'notification_cooldown', self::DEFAULT_COOLDOWN );
		$cooldown = max( 60, $cooldown ); // Minimum 1 minute.

		set_transient( self::COOLDOWN_TRANSIENT, time(), $cooldown );
	}
}
