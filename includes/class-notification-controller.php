<?php
/**
 * Notification REST API controller.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * REST API controller for notification settings and webhook testing.
 *
 * Provides endpoints for:
 * - Retrieving notification settings.
 * - Updating notification settings.
 * - Sending a test webhook/email/Slack notification.
 */
class Notification_Controller extends \WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wp-system-report/v1';

	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'notifications';

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
	 * Register the REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_settings_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/test',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_test' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'channel' => array(
							'description'       => __( 'Notification channel to test.', 'wp-system-report' ),
							'type'              => 'string',
							'enum'              => array( 'webhook', 'email', 'slack' ),
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Check permissions.
	 *
	 * @return bool|\WP_Error True if permitted, WP_Error otherwise.
	 */
	public function permissions_check(): bool|\WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'wp_system_report_rest_forbidden',
				__( 'Sorry, you are not allowed to manage notification settings.', 'wp-system-report' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Get notification settings.
	 *
	 * @return \WP_REST_Response Response containing current notification settings.
	 */
	public function get_settings(): \WP_REST_Response {
		$settings = array(
			'notifications_enabled'     => (bool) Settings::get( 'notifications_enabled', false ),
			'notify_email_enabled'      => (bool) Settings::get( 'notify_email_enabled', false ),
			'notify_email_recipients'   => (string) Settings::get( 'notify_email_recipients', '' ),
			'notify_slack_enabled'      => (bool) Settings::get( 'notify_slack_enabled', false ),
			'slack_webhook_url'         => (string) Settings::get( 'slack_webhook_url', '' ),
			'webhook_urls'              => (string) Settings::get( 'webhook_urls', '' ),
			'notify_critical_threshold' => (int) Settings::get( 'notify_critical_threshold', 1 ),
			'notify_warning_threshold'  => (int) Settings::get( 'notify_warning_threshold', 5 ),
			'notification_cooldown'     => (int) Settings::get( 'notification_cooldown', 3600 ),
		);

		return REST_Envelope::success( $settings, array( 'type' => 'notification_settings' ) );
	}

	/**
	 * Update notification settings.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response Response confirming the update.
	 */
	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$updatable = array(
			'notifications_enabled',
			'notify_email_enabled',
			'notify_email_recipients',
			'notify_slack_enabled',
			'slack_webhook_url',
			'webhook_urls',
			'notify_critical_threshold',
			'notify_warning_threshold',
			'notification_cooldown',
		);

		$updated = array();

		foreach ( $updatable as $key ) {
			if ( $request->has_param( $key ) ) {
				$value = $request->get_param( $key );
				Settings::update( $key, $value );
				$updated[] = $key;
			}
		}

		return REST_Envelope::success(
			array( 'updated' => $updated ),
			array( 'type' => 'notification_settings_updated' )
		);
	}

	/**
	 * Send a test notification.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 */
	public function send_test( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$channel = $request->get_param( 'channel' );

		$test_payload = array(
			'summary'  => 'This is a test notification from WP System Report.',
			'critical' => array(
				array(
					'section' => 'Test',
					'label'   => 'Test Critical Issue',
					'value'   => 'This is a test critical finding.',
				),
			),
			'warnings' => array(
				array(
					'section' => 'Test',
					'label'   => 'Test Warning',
					'value'   => 'This is a test warning finding.',
				),
			),
		);

		switch ( $channel ) {
			case 'webhook':
				$results = $this->webhook_dispatcher->dispatch( 'test.notification', $test_payload );

				if ( empty( $results ) ) {
					return new \WP_Error(
						'wp_system_report_no_webhooks',
						__( 'No webhook URLs configured.', 'wp-system-report' ),
						array( 'status' => 400 )
					);
				}

				return REST_Envelope::success(
					array(
						'channel' => 'webhook',
						'results' => $results,
					),
					array( 'type' => 'test_notification' )
				);

			case 'email':
				$recipients = $this->get_test_email_recipients();

				if ( empty( $recipients ) ) {
					return new \WP_Error(
						'wp_system_report_no_email',
						__( 'No email recipients configured.', 'wp-system-report' ),
						array( 'status' => 400 )
					);
				}

				$site_name = get_option( 'blogname' );
				$subject   = sprintf(
					/* translators: %s: site name */
					__( '[Test] WP System Report Alert — %s', 'wp-system-report' ),
					$site_name
				);
				$body = "This is a test notification from WP System Report.\n\n"
					. "If you received this email, your notification settings are working correctly.\n\n"
					. sprintf( "Site: %s\n", get_option( 'home' ) )
					. sprintf( "Time: %s UTC\n", gmdate( 'Y-m-d H:i:s' ) );

				$sent = false;
				foreach ( $recipients as $recipient ) {
					if ( wp_mail( $recipient, $subject, $body ) ) {
						$sent = true;
					}
				}

				return REST_Envelope::success(
					array(
						'channel'    => 'email',
						'recipients' => $recipients,
						'sent'       => $sent,
					),
					array( 'type' => 'test_notification' )
				);

			case 'slack':
				$webhook_url = Settings::get( 'slack_webhook_url', '' );

				if ( ! is_string( $webhook_url ) || '' === $webhook_url ) {
					return new \WP_Error(
						'wp_system_report_no_slack',
						__( 'No Slack webhook URL configured.', 'wp-system-report' ),
						array( 'status' => 400 )
					);
				}

				$payload = array(
					'blocks' => array(
						array(
							'type' => 'header',
							'text' => array(
								'type' => 'plain_text',
								'text' => 'Test Notification — WP System Report',
							),
						),
						array(
							'type' => 'section',
							'text' => array(
								'type' => 'mrkdwn',
								'text' => sprintf(
									"This is a test notification from *%s*.\nIf you see this, Slack notifications are working.",
									get_option( 'blogname' )
								),
							),
						),
					),
				);

				$response = wp_remote_post(
					$webhook_url,
					array(
						'method'  => 'POST',
						'timeout' => 10,
						'headers' => array( 'Content-Type' => 'application/json' ),
						'body'    => wp_json_encode( $payload ),
					)
				);

				$success = ! is_wp_error( $response )
					&& wp_remote_retrieve_response_code( $response ) >= 200
					&& wp_remote_retrieve_response_code( $response ) < 300;

				return REST_Envelope::success(
					array(
						'channel' => 'slack',
						'sent'    => $success,
					),
					array( 'type' => 'test_notification' )
				);

			default:
				return new \WP_Error(
					'wp_system_report_invalid_channel',
					__( 'Invalid notification channel.', 'wp-system-report' ),
					array( 'status' => 400 )
				);
		}
	}

	/**
	 * Get email recipients for testing.
	 *
	 * @return string[] Email addresses.
	 */
	private function get_test_email_recipients(): array {
		$raw = Settings::get( 'notify_email_recipients', '' );

		if ( is_string( $raw ) && '' !== $raw ) {
			$emails = preg_split( '/[\r\n,]+/', $raw );

			if ( false !== $emails ) {
				$emails = array_map( 'trim', $emails );
				$emails = array_filter( $emails, 'is_email' );

				if ( ! empty( $emails ) ) {
					return array_values( $emails );
				}
			}
		}

		$admin_email = get_option( 'admin_email' );

		return $admin_email ? array( $admin_email ) : array();
	}

	/**
	 * Get the schema for settings update arguments.
	 *
	 * @return array Argument definitions.
	 */
	private function get_settings_args(): array {
		return array(
			'notifications_enabled'     => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'notify_email_enabled'      => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'notify_email_recipients'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'notify_slack_enabled'      => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'slack_webhook_url'         => array(
				'type'              => 'string',
				'format'            => 'uri',
				'sanitize_callback' => 'esc_url_raw',
			),
			'webhook_urls'              => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'notify_critical_threshold' => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100,
			),
			'notify_warning_threshold'  => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100,
			),
			'notification_cooldown'     => array(
				'type'    => 'integer',
				'minimum' => 60,
				'maximum' => 86400,
			),
		);
	}
}
