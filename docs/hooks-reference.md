# Hooks Reference

Complete reference for all actions and filters fired by WP System Report.

---

## Table of Contents

- [Report Lifecycle](#report-lifecycle)
- [Collectors](#collectors)
- [Health Score](#health-score)
- [Report History & Snapshots](#report-history--snapshots)
- [Report Diff](#report-diff)
- [Fixers](#fixers)
- [Notifications](#notifications)
- [Webhooks](#webhooks)
- [AI Formatter](#ai-formatter)
- [MCP Formatter](#mcp-formatter)
- [AI Context File](#ai-context-file)
- [Error Log & SSE Streaming](#error-log--sse-streaming)
- [Access Control](#access-control)
- [Debug Toggle](#debug-toggle)
- [Feature Flags](#feature-flags)

---

## Report Lifecycle

### Filter: `wp_system_report_collectors`

Add, remove, or reorder collectors in the report.

**File:** `includes/class-report-generator.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$collectors` | `Collector[]` | Associative array of collector ID => Collector instance |

```php
add_filter( 'wp_system_report_collectors', function ( array $collectors ): array {
    // Add a custom collector.
    $collectors['my_custom'] = new My_Custom_Collector();

    // Remove a built-in collector.
    unset( $collectors['inactive_plugins'] );

    return $collectors;
} );
```

---

### Filter: `wp_system_report_fields_{collector_id}`

Modify the fields returned by a specific collector. Replace `{collector_id}` with the collector's ID (e.g., `wordpress_environment`, `security`, `database`).

**File:** `includes/class-report-generator.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$fields` | `array` | Array of field arrays |
| `$collector` | `Collector` | The collector instance |

```php
add_filter( 'wp_system_report_fields_wordpress_environment', function ( array $fields, $collector ): array {
    // Add a custom field to the WordPress Environment section.
    $fields[] = array(
        'label'  => 'Custom Check',
        'value'  => 'All good',
        'status' => 'good',
    );
    return $fields;
}, 10, 2 );
```

---

### Action: `wp_system_report_generated`

Fires after a full system report is generated. Use this to react to report generation without modifying the report data.

**File:** `includes/class-report-generator.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$report` | `array` | The complete report data, keyed by collector ID |

```php
add_action( 'wp_system_report_generated', function ( array $report ): void {
    // Log the number of sections generated.
    error_log( 'WP System Report: Generated report with ' . count( $report ) . ' sections.' );
} );
```

---

## Collectors

### Filter: `wp_system_report_cache_ttl`

Change the transient cache duration for collectors. Applies once per collector `get_cached_data()` call.

**File:** `includes/collectors/class-abstract-collector.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$ttl` | `int` | `3600` (1 hour) | Cache TTL in seconds |
| `$cache_key` | `string` | — | The transient key being cached |

```php
add_filter( 'wp_system_report_cache_ttl', function ( int $ttl, string $cache_key ): int {
    // Cache the database collector for 4 hours.
    if ( 'sr_database' === $cache_key ) {
        return 4 * HOUR_IN_SECONDS;
    }
    return $ttl;
}, 10, 2 );
```

---

### Filter: `wp_system_report_constants`

Filter the list of WordPress constants to check and display in the WordPress Constants collector section.

**File:** `includes/collectors/class-wordpress-constants.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$constants` | `array` | Array of constant name strings |

```php
add_filter( 'wp_system_report_constants', function ( array $constants ): array {
    // Add a custom constant.
    $constants[] = 'MY_PLUGIN_DEBUG';

    // Remove a constant.
    $constants = array_diff( $constants, array( 'AUTOSAVE_INTERVAL' ) );

    return $constants;
} );
```

---

## Health Score

### Filter: `wp_system_report_health_score_weights`

Override the per-category weights used when computing the overall health score. A higher weight means that category has a greater influence on the final score.

**File:** `includes/class-health-score.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$weights` | `array<string, float>` | Map of collector/section ID to floating-point weight |

Default weights (excerpt):

| Section ID | Default Weight |
|------------|---------------|
| `security` | `3.0` |
| `update_health` | `2.5` |
| `performance` | `2.5` |
| `wordpress_environment` | `2.0` |
| `database` | `2.0` |
| `cron_health` | `1.5` |
| `email_delivery` | `1.5` |

```php
add_filter( 'wp_system_report_health_score_weights', function ( array $weights ): array {
    // Double the weight for security checks.
    $weights['security'] = 6.0;

    // Reduce the weight of inactive plugins.
    $weights['inactive_plugins'] = 0.1;

    return $weights;
} );
```

---

### Filter: `wp_system_report_health_score`

Filter the final computed health score (0-100) after it has been calculated. Values outside 0-100 are clamped by the plugin.

**File:** `includes/class-health-score.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$score` | `int` | The computed health score (0-100) |
| `$breakdown` | `array` | Per-section score breakdown |
| `$report` | `array` | The raw report data |

```php
add_filter( 'wp_system_report_health_score', function ( int $score, array $breakdown, array $report ): int {
    // Always report 100 on staging sites.
    if ( defined( 'WP_ENV' ) && 'staging' === WP_ENV ) {
        return 100;
    }
    return $score;
}, 10, 3 );
```

---

## Report History & Snapshots

### Filter: `wp_system_report_snapshot_interval`

Change the minimum time between automatic report snapshots. A new automatic snapshot will not be saved until this interval has elapsed since the last one.

**File:** `includes/class-report-history.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$interval` | `int` | `3600` (1 hour) | Minimum seconds between automatic snapshots |

```php
add_filter( 'wp_system_report_snapshot_interval', function ( int $interval ): int {
    // Save snapshots at most once every 6 hours.
    return 6 * HOUR_IN_SECONDS;
} );
```

---

### Filter: `wp_system_report_retention_limit`

Change the maximum number of report snapshots to keep. When exceeded, the oldest snapshots are deleted automatically.

**File:** `includes/class-report-history.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$limit` | `int` | `90` | Maximum snapshot count to retain |

```php
add_filter( 'wp_system_report_retention_limit', function ( int $limit ): int {
    // Keep up to 365 snapshots.
    return 365;
} );
```

---

### Action: `wp_system_report_snapshot_saved`

Fires after a report snapshot is successfully saved to the history table.

**File:** `includes/class-report-history.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$snapshot_id` | `int` | The database ID of the new snapshot |
| `$score_data` | `array` | The health score data for the snapshot (`score`, `grade`, `breakdown`, `summary`) |

```php
add_action( 'wp_system_report_snapshot_saved', function ( int $snapshot_id, array $score_data ): void {
    // Send a notification when a snapshot falls below 50.
    if ( $score_data['score'] < 50 ) {
        wp_mail( 'admin@example.com', 'Health score dropped', 'Score: ' . $score_data['score'] );
    }
}, 10, 2 );
```

---

## Report Diff

### Filter: `wp_system_report_diff`

Filter the computed diff result between two report snapshots. The result contains `sections`, `summary`, and `labels` keys.

**File:** `includes/class-report-diff.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$result` | `array` | The diff result with `sections`, `summary`, and `labels` keys |
| `$before` | `array` | The older report data |
| `$after` | `array` | The newer report data |

```php
add_filter( 'wp_system_report_diff', function ( array $result, array $before, array $after ): array {
    // Attach extra metadata to the diff result.
    $result['generated_at'] = gmdate( 'c' );
    return $result;
}, 10, 3 );
```

---

## Fixers

### Filter: `wp_system_report_fixers`

Add, remove, or replace registered fixers in the fixer registry. Fixers are identified by an associative key (fixer ID).

**File:** `includes/class-fixer-registry.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$fixers` | `Fixer[]` | Associative array of fixer ID => Fixer instance |

```php
add_filter( 'wp_system_report_fixers', function ( array $fixers ): array {
    // Register a custom fixer.
    $fixers['my_custom_fixer'] = new My_Custom_Fixer();

    // Remove the built-in autoload optimizer.
    unset( $fixers['autoload_optimizer'] );

    return $fixers;
} );
```

---

### Filter: `wp_system_report_autoload_threshold`

Change the minimum byte size for an autoloaded option to be considered bloated by the Autoload Optimizer fixer.

**File:** `includes/fixers/class-autoload-optimizer.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$threshold` | `int` | Minimum byte size. Options at or above this size are flagged |

```php
add_filter( 'wp_system_report_autoload_threshold', function ( int $threshold ): int {
    // Flag options larger than 512 KB instead of the default.
    return 512 * KB_IN_BYTES;
} );
```

---

### Filter: `wp_system_report_autoload_protected`

Mark an autoloaded option as protected so the Autoload Optimizer will not modify it, even if it exceeds the threshold.

**File:** `includes/fixers/class-autoload-optimizer.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$is_protected` | `bool` | Whether the option is protected. Default `false` |
| `$option_name` | `string` | The option name being evaluated |

```php
add_filter( 'wp_system_report_autoload_protected', function ( bool $is_protected, string $option_name ): bool {
    // Protect all options belonging to My Plugin.
    if ( str_starts_with( $option_name, 'my_plugin_' ) ) {
        return true;
    }
    return $is_protected;
}, 10, 2 );
```

---

### Filter: `wp_system_report_core_cron_hooks`

Extend or modify the list of cron hook names that are considered WordPress core hooks. Core hooks are excluded from orphan-cron detection in the Cron Repair fixer.

**File:** `includes/fixers/class-cron-repair.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$core_hooks` | `string[]` | Array of core cron hook names |

```php
add_filter( 'wp_system_report_core_cron_hooks', function ( array $core_hooks ): array {
    // Treat WooCommerce's action scheduler hooks as core so they are not flagged.
    $core_hooks[] = 'action_scheduler_run_queue';
    $core_hooks[] = 'action_scheduler_cleanup';
    return $core_hooks;
} );
```

---

### Filter: `wp_system_report_optimize_overhead_threshold`

Change the minimum `DATA_FREE` value (in bytes) for a database table to be considered eligible for optimization by the Database Optimizer fixer.

**File:** `includes/fixers/class-database-optimizer.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$threshold` | `int` | `1048576` (1 MB) | Minimum DATA_FREE in bytes |

```php
add_filter( 'wp_system_report_optimize_overhead_threshold', function ( int $threshold ): int {
    // Only optimize tables with at least 10 MB of overhead.
    return 10 * MB_IN_BYTES;
} );
```

---

## Notifications

### Filter: `wp_system_report_notification_findings`

Modify, suppress, or enrich the analysed findings before any notification channels are triggered. Return an empty `critical` and `warnings` array to suppress all notifications.

**File:** `includes/class-notification-manager.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$findings` | `array` | Analysed findings with `critical` and `warnings` sub-arrays |
| `$report_data` | `array` | The full raw report data |

Each entry in `critical` and `warnings` is an array with keys: `section` (string), `label` (string), `value` (string).

```php
add_filter( 'wp_system_report_notification_findings', function ( array $findings, array $report_data ): array {
    // Suppress all warnings on staging environments.
    if ( defined( 'WP_ENV' ) && 'staging' === WP_ENV ) {
        $findings['warnings'] = array();
    }
    return $findings;
}, 10, 2 );
```

---

### Filter: `wp_system_report_notification_email`

Modify the email notification arguments before the email is sent. Each recipient receives a separate email to avoid exposing addresses in the To: header.

**File:** `includes/class-notification-manager.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$email_args` | `array` | Email arguments: `recipients` (string[]), `subject` (string), `body` (string) |
| `$findings` | `array` | The findings that triggered the notification |

```php
add_filter( 'wp_system_report_notification_email', function ( array $email_args, array $findings ): array {
    // Add a custom footer to the notification email.
    $email_args['body'] .= "\n\n--\nThis report was generated automatically.";

    // Add an extra recipient for critical findings.
    if ( ! empty( $findings['critical'] ) ) {
        $email_args['recipients'][] = 'oncall@example.com';
    }

    return $email_args;
}, 10, 2 );
```

---

### Filter: `wp_system_report_slack_payload`

Modify the Slack Block Kit payload before it is sent to the configured Slack webhook URL.

**File:** `includes/class-notification-manager.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$slack_payload` | `array` | The Slack Block Kit payload (contains a `blocks` key) |
| `$findings` | `array` | The findings that triggered the notification |

```php
add_filter( 'wp_system_report_slack_payload', function ( array $slack_payload, array $findings ): array {
    // Prepend a custom context block with the environment name.
    array_unshift( $slack_payload['blocks'], array(
        'type' => 'context',
        'elements' => array(
            array(
                'type' => 'mrkdwn',
                'text' => '*Environment:* ' . ( defined( 'WP_ENV' ) ? WP_ENV : 'production' ),
            ),
        ),
    ) );
    return $slack_payload;
}, 10, 2 );
```

---

### Action: `wp_system_report_notifications_sent`

Fires after all built-in notification channels (webhook, email, Slack) have dispatched. Use this to implement additional custom notification channels.

**File:** `includes/class-notification-manager.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$findings` | `array` | The findings that triggered notifications, with `critical` and `warnings` arrays |

```php
add_action( 'wp_system_report_notifications_sent', function ( array $findings ): void {
    // Post a PagerDuty alert for critical issues.
    if ( ! empty( $findings['critical'] ) ) {
        wp_remote_post( 'https://events.pagerduty.com/v2/enqueue', array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array(
                'routing_key'  => 'your-integration-key',
                'event_action' => 'trigger',
                'payload'      => array(
                    'summary'  => 'WP System Report: Critical issues detected',
                    'severity' => 'critical',
                    'source'   => get_option( 'home' ),
                ),
            ) ),
        ) );
    }
} );
```

---

## Webhooks

### Filter: `wp_system_report_webhook_urls`

Filter the list of validated webhook URLs before dispatching an event. The input array contains only URLs that have already passed format validation.

**File:** `includes/class-webhook-dispatcher.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$urls` | `string[]` | Array of validated webhook URLs |

```php
add_filter( 'wp_system_report_webhook_urls', function ( array $urls ): array {
    // Add a programmatic endpoint that is not stored in settings.
    $urls[] = 'https://hooks.example.com/system-report';
    return $urls;
} );
```

---

### Filter: `wp_system_report_webhook_args`

Filter the WP HTTP API request arguments for an individual webhook request before it is sent.

**File:** `includes/class-webhook-dispatcher.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$args` | `array` | WP HTTP API arguments (`method`, `timeout`, `headers`, `body`, etc.) |
| `$url` | `string` | The target webhook URL |
| `$event` | `string` | The event name (e.g., `report.critical`, `report.warning`) |

```php
add_filter( 'wp_system_report_webhook_args', function ( array $args, string $url, string $event ): array {
    // Disable SSL verification for a local development endpoint.
    if ( str_contains( $url, 'localhost' ) ) {
        $args['sslverify'] = false;
    }
    return $args;
}, 10, 3 );
```

---

### Action: `wp_system_report_webhooks_dispatched`

Fires after a webhook event has been dispatched to all configured URLs.

**File:** `includes/class-webhook-dispatcher.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$event` | `string` | The event name (e.g., `report.critical`) |
| `$payload` | `array` | The event payload that was sent |
| `$results` | `array` | Dispatch results, keyed by URL. Each value is a `bool` indicating success |

```php
add_action( 'wp_system_report_webhooks_dispatched', function ( string $event, array $payload, array $results ): void {
    $failed = array_keys( array_filter( $results, fn( bool $ok ) => ! $ok ) );
    if ( ! empty( $failed ) ) {
        error_log( 'WP System Report: Webhook delivery failed for: ' . implode( ', ', $failed ) );
    }
}, 10, 3 );
```

---

## AI Formatter

### Filter: `wp_system_report_ai_header`

Customize the header section of the AI-formatted Markdown report.

**File:** `includes/formatters/class-ai-formatter.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$header` | `string` | The Markdown header string |

```php
add_filter( 'wp_system_report_ai_header', function ( string $header ): string {
    return $header . "\n> Custom context: This is a staging site.\n";
} );
```

---

### Filter: `wp_system_report_ai_issues`

Add or modify the detected issues that appear in the AI-formatted report. Issues with higher `score` values are sorted to the top.

**File:** `includes/formatters/class-ai-formatter.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$issues` | `array` | Array of issue arrays with `severity`, `title`, `description`, `score`, `category` keys |
| `$report_data` | `array` | The full raw report data |

```php
add_filter( 'wp_system_report_ai_issues', function ( array $issues, array $report_data ): array {
    $issues[] = array(
        'severity'    => 'warning',
        'title'       => 'Custom Plugin Check',
        'description' => 'My plugin detected an unusual configuration.',
        'score'       => 5,
        'category'    => 'custom',
    );
    return $issues;
}, 10, 2 );
```

---

### Filter: `wp_system_report_ai_executive_summary`

Filter the rendered Markdown for the Executive Summary section of the AI report.

**File:** `includes/formatters/class-ai-formatter.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$output` | `string` | The Executive Summary Markdown |
| `$health_score` | `int` | Computed health score (0-100) |
| `$issues` | `array` | All detected issues |
| `$report_data` | `array` | Full report data |

```php
add_filter( 'wp_system_report_ai_executive_summary', function ( string $output, int $health_score, array $issues, array $report_data ): string {
    $output .= "\n> Note: Reviewed by the internal security team on " . gmdate( 'Y-m-d' ) . ".\n";
    return $output;
}, 10, 4 );
```

---

## MCP Formatter

### Filter: `wp_system_report_mcp_payload`

Filter the full MCP formatter payload before it is JSON-encoded and returned to the client. The payload is structured for consumption by AI development tools.

**File:** `includes/formatters/class-mcp-formatter.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$payload` | `array` | Structured report payload with `site`, `health`, `issues`, and `sections` keys |
| `$report_data` | `array` | Raw report data from Report_Generator |

```php
add_filter( 'wp_system_report_mcp_payload', function ( array $payload, array $report_data ): array {
    // Append custom metadata for AI tool context.
    $payload['custom_context'] = array(
        'environment' => defined( 'WP_ENV' ) ? WP_ENV : 'production',
        'reviewed_at' => gmdate( 'c' ),
    );
    return $payload;
}, 10, 2 );
```

---

### Filter: `wp_system_report_mcp_site_identity`

Filter the site identity block within the MCP formatter payload. This block provides essential context for AI agents.

**File:** `includes/formatters/class-mcp-formatter.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$identity` | `array` | Site identity data: `url`, `name`, `wordpress`, `php`, `multisite`, `plugin_version`, `generated_at` |

```php
add_filter( 'wp_system_report_mcp_site_identity', function ( array $identity ): array {
    $identity['environment'] = defined( 'WP_ENV' ) ? WP_ENV : 'production';
    $identity['region']      = 'us-east-1';
    return $identity;
} );
```

---

## AI Context File

### Filter: `wp_system_report_ai_context_path`

Change the absolute path where the AI context Markdown file is written. The file is placed at the WordPress root by default.

**File:** `includes/class-ai-context-generator.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | The absolute path to the context file |

```php
add_filter( 'wp_system_report_ai_context_path', function ( string $path ): string {
    // Write the file to a custom directory instead.
    return WP_CONTENT_DIR . '/ai-context/system-report.md';
} );
```

---

### Filter: `wp_system_report_ai_context_content`

Filter the rendered Markdown content before it is written to the AI context file.

**File:** `includes/class-ai-context-generator.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$output` | `string` | The rendered Markdown content |
| `$report_data` | `array` | The full report data used to render the content |

```php
add_filter( 'wp_system_report_ai_context_content', function ( string $output, array $report_data ): string {
    $output .= "\n## Custom Notes\n\nThis site uses a bespoke caching layer.\n";
    return $output;
}, 10, 2 );
```

---

### Action: `wp_system_report_ai_context_written`

Fires after the AI context file is successfully written to disk.

**File:** `includes/class-ai-context-generator.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | The absolute path to the file that was written |
| `$content` | `string` | The file content that was written |

```php
add_action( 'wp_system_report_ai_context_written', function ( string $path, string $content ): void {
    // Purge a CDN cache for the context file after each write.
    wp_remote_post( 'https://api.cdn.example.com/purge', array(
        'body' => wp_json_encode( array( 'path' => $path ) ),
    ) );
}, 10, 2 );
```

---

## Error Log & SSE Streaming

### Filter: `wp_system_report_allowed_log_paths`

Add additional directory paths that are allowed for error log reading. Paths are validated against this list to prevent directory traversal attacks.

**File:** `includes/class-error-log-reader.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$allowed_paths` | `array` | Array of absolute directory paths |

```php
add_filter( 'wp_system_report_allowed_log_paths', function ( array $paths ): array {
    $paths[] = '/var/log/php/';
    return $paths;
} );
```

---

### Filter: `wp_system_report_redact_log_line`

Redact sensitive content from individual error log lines before they are displayed in the UI or streamed over SSE. Applied in both `Error_Log_Reader` and `SSE_Log_Streamer`.

**Files:** `includes/class-error-log-reader.php`, `includes/class-sse-log-streamer.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$line` | `string` | A single log line |

```php
add_filter( 'wp_system_report_redact_log_line', function ( string $line ): string {
    // Redact API keys.
    return preg_replace( '/api_key=[a-zA-Z0-9]+/', 'api_key=REDACTED', $line );
} );
```

---

### Filter: `wp_system_report_sse_initial_lines`

Change the number of existing log lines sent to the client when the live log SSE connection is first established.

**File:** `includes/class-sse-log-streamer.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$count` | `int` | `50` | Number of initial lines to return |

```php
add_filter( 'wp_system_report_sse_initial_lines', function ( int $count ): int {
    return 100;
} );
```

---

### Filter: `wp_system_report_sse_poll_interval`

Change how frequently the SSE streamer checks the log file for new content. The value is in microseconds. Values below 100,000 (100 ms) are clamped to 100,000.

**File:** `includes/class-sse-log-streamer.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$interval` | `int` | `1000000` (1 second) | Microseconds between file polls |

```php
add_filter( 'wp_system_report_sse_poll_interval', function ( int $interval ): int {
    // Poll every 500 ms for a more responsive stream.
    return 500000;
} );
```

---

### Filter: `wp_system_report_sse_heartbeat_interval`

Change the interval in seconds between SSE heartbeat events. Heartbeat events keep the connection alive and allow the client to detect stale connections.

**File:** `includes/class-sse-log-streamer.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$interval` | `int` | `15` | Seconds between heartbeat events |

```php
add_filter( 'wp_system_report_sse_heartbeat_interval', function ( int $interval ): int {
    // Send a heartbeat every 30 seconds.
    return 30;
} );
```

---

### Filter: `wp_system_report_sse_max_duration`

Change the maximum number of seconds that an SSE log stream will remain open. Once the duration is reached, a `close` event is emitted and the connection is terminated.

**File:** `includes/class-sse-log-streamer.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$duration` | `int` | `300` (5 minutes) | Maximum stream duration in seconds |

```php
add_filter( 'wp_system_report_sse_max_duration', function ( int $duration ): int {
    // Allow streams to stay open for up to 10 minutes.
    return 600;
} );
```

---

### Action: `wp_system_report_sse_stream_start`

Fires immediately after SSE headers are sent and the live log stream begins.

**File:** `includes/class-sse-log-streamer.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Absolute path to the log file being streamed |

```php
add_action( 'wp_system_report_sse_stream_start', function ( string $path ): void {
    error_log( 'WP System Report: SSE stream started for ' . $path );
} );
```

---

### Action: `wp_system_report_sse_stream_end`

Fires after the SSE log stream ends, whether due to client disconnect, max duration, or a read error.

**File:** `includes/class-sse-log-streamer.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Absolute path to the log file that was streamed |

```php
add_action( 'wp_system_report_sse_stream_end', function ( string $path ): void {
    error_log( 'WP System Report: SSE stream ended for ' . $path );
} );
```

---

## Access Control

### Filter: `wp_system_report_capability`

Change the required WordPress capability for accessing the report admin page, the main REST API endpoints, the fixer REST controller, the health score controller, the report history controller, and the report diff controller.

**Files:** `includes/class-admin-page.php`, `includes/class-rest-controller.php`, `includes/class-fixer-controller.php`, `includes/class-health-score-controller.php`, `includes/class-report-history-controller.php`, `includes/class-report-diff-controller.php`, `includes/class-abilities-provider.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$capability` | `string` | `manage_options` | WordPress capability string |

```php
add_filter( 'wp_system_report_capability', function (): string {
    return 'edit_theme_options';
} );
```

---

### Filter: `wp_system_report_error_log_capability`

Change the required capability for error log access (both the REST controller and SSE streaming controller). Only admin-level capabilities are accepted.

**Files:** `includes/class-error-log-controller.php`, `includes/class-sse-log-controller.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$capability` | `string` | `manage_options` | WordPress capability string |

```php
add_filter( 'wp_system_report_error_log_capability', function (): string {
    return 'manage_network';
} );
```

---

## Debug Toggle

### Action: `wp_system_report_before_debug_toggle`

Fires immediately before the debug constants are modified in `wp-config.php`.

**File:** `includes/class-debug-toggle.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$enable` | `bool` | `true` if enabling debug mode, `false` if disabling |
| `$config_path` | `string` | Absolute path to `wp-config.php` |

```php
add_action( 'wp_system_report_before_debug_toggle', function ( bool $enable, string $config_path ): void {
    if ( $enable ) {
        error_log( 'WP System Report: Debug mode being enabled on ' . get_option( 'home' ) );
    }
}, 10, 2 );
```

---

### Action: `wp_system_report_after_debug_toggle`

Fires immediately after the debug constants have been successfully written to `wp-config.php`.

**File:** `includes/class-debug-toggle.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$enable` | `bool` | `true` if debug was enabled, `false` if disabled |
| `$config_path` | `string` | Absolute path to `wp-config.php` |

```php
add_action( 'wp_system_report_after_debug_toggle', function ( bool $enable, string $config_path ): void {
    // Notify an external monitoring service.
    wp_remote_post( 'https://monitoring.example.com/webhook', array(
        'body' => wp_json_encode( array( 'debug_enabled' => $enable ) ),
    ) );
}, 10, 2 );
```

---

## Feature Flags

### Filter: `wp_system_report_is_pro`

Control whether Pro features (fixers, MCP integration, etc.) are available. Intended for automated testing and third-party integrations that need to control feature gating.

**File:** `includes/class-features.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$is_pro` | `bool` | `true` | Whether Pro features are available |

```php
add_filter( 'wp_system_report_is_pro', function ( bool $is_pro ): bool {
    // Disable Pro features on non-production environments.
    if ( defined( 'WP_ENV' ) && 'production' !== WP_ENV ) {
        return false;
    }
    return $is_pro;
} );
```

---

## GitHub Formatter

### Filter: `wp_system_report_redactions`

Filter the redaction patterns used by the GitHub formatter when sanitizing the report for public sharing. Each pattern replaces matched text before the report is output.

**File:** `includes/formatters/class-github-formatter.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$redactions` | `array` | Array of arrays with `pattern` (regex string) and `replacement` (string) keys |

```php
add_filter( 'wp_system_report_redactions', function ( array $redactions ): array {
    $redactions[] = array(
        'pattern'     => '/my-secret-path/',
        'replacement' => '/redacted-path/',
    );
    return $redactions;
} );
```

---

*This file is generated from the plugin source. If you add a new hook, update this document to keep it accurate.*
