# REST API

WP System Report exposes a REST API under the `wp-system-report/v1` namespace. All endpoints require the `manage_options` capability by default unless noted otherwise.

## Table of Contents

- [Authentication](#authentication)
- [Response Envelope](#response-envelope)
- [Report Endpoints](#report-endpoints)
- [Health Score Endpoints](#health-score-endpoints)
- [Report History Endpoints](#report-history-endpoints)
- [Report Diff Endpoints](#report-diff-endpoints)
- [Error Log Endpoints](#error-log-endpoints)
- [Fixer Endpoints](#fixer-endpoints)
- [Notification Endpoints](#notification-endpoints)
- [Capability Filters](#capability-filters)
- [Error Responses](#error-responses)

---

## Authentication

The API requires authentication. Use any standard WordPress REST API authentication method:

- **Application Passwords** (recommended for external tools)
- **Cookie authentication** (for requests from the WordPress admin)
- **OAuth / JWT** (if provided by another plugin)

### Example with Application Passwords

```bash
# Base64-encode username:application_password
curl -H "Authorization: Basic $(echo -n 'admin:xxxx xxxx xxxx xxxx xxxx xxxx' | base64)" \
  "https://example.com/wp-json/wp-system-report/v1/report"
```

---

## Response Envelope

All JSON responses from the plugin use a consistent envelope structure:

```json
{
  "status": "success",
  "data": { ... },
  "meta": {
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

| Field | Description |
|-------|-------------|
| `status` | Always `"success"` for successful responses. Error responses use standard WordPress REST API error format. |
| `data` | The endpoint-specific payload. |
| `meta.generated_at` | ISO 8601 timestamp of when the response was generated. |
| `meta.plugin_version` | The installed plugin version. |

Additional endpoint-specific keys may appear in `meta` (for example, `total`, `pages`, `format`).

---

## Report Endpoints

### GET /wp-system-report/v1/report

Generate a system report in the requested format.

**Permission:** `manage_options` (filterable via `wp_system_report_capability`)

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `format` | string | `json` | Output format: `json`, `plain`, `github`, `ai`, or `mcp` |

**Response — `json` format:**

The `data` key contains an object keyed by collector slug. Each collector has a `label`, `description`, and `fields` array.

```json
{
  "status": "success",
  "data": {
    "wordpress_environment": {
      "label": "WordPress Environment",
      "description": "Core WordPress installation settings and configuration.",
      "fields": [
        {
          "label": "WordPress Version",
          "value": "6.7.0",
          "debug": "6.7.0",
          "status": "good",
          "description": "",
          "recommended": ">= 6.4"
        }
      ]
    }
  },
  "meta": {
    "format": "json",
    "collector_count": 10,
    "fixes_available": true,
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Response — `mcp` format:**

Returns a structured JSON payload optimised for consumption by AI tools and MCP clients, wrapped in the standard envelope.

**Response — `plain`, `github`, `ai` formats:**

Returns a formatted text string with the appropriate `Content-Type` header (`text/plain` or `text/markdown`). The response is served as raw text, not wrapped in the JSON envelope.

```bash
# Fetch the plain-text report
curl -H "Authorization: Basic ..." \
  "https://example.com/wp-json/wp-system-report/v1/report?format=plain"
```

---

## Health Score Endpoints

### GET /wp-system-report/v1/health-score

Calculate and return the aggregate site health score with a per-section breakdown and field status summary.

**Permission:** `manage_options` (filterable via `wp_system_report_capability`)

**No query parameters.**

**Response:**

```json
{
  "status": "success",
  "data": {
    "score": 82,
    "grade": "B",
    "breakdown": {
      "wordpress_environment": {
        "score": 95,
        "weight": 1.0
      },
      "server_environment": {
        "score": 70,
        "weight": 0.8
      }
    },
    "summary": {
      "total_fields": 48,
      "good": 40,
      "warnings": 5,
      "criticals": 1,
      "info": 2
    }
  },
  "meta": {
    "endpoint": "health-score",
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Score and grade mapping:**

| Score range | Grade |
|-------------|-------|
| 95 – 100 | A+ |
| 80 – 94 | A |
| 65 – 79 | B |
| 50 – 64 | C |
| 35 – 49 | D |
| 0 – 34 | F |

---

## Report History Endpoints

These endpoints are only available when the report history feature is enabled.

### GET /wp-system-report/v1/history

List saved report snapshots in reverse chronological order (most recent first by default).

**Permission:** `manage_options` (filterable via `wp_system_report_capability`)

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `per_page` | integer | `20` | Results per page (1–100) |
| `page` | integer | `1` | Page number |
| `order` | string | `desc` | Sort order: `asc` or `desc` |
| `after` | string | — | Return snapshots created after this ISO 8601 datetime |
| `before` | string | — | Return snapshots created before this ISO 8601 datetime |

**Response:**

```json
{
  "status": "success",
  "data": [
    {
      "id": 42,
      "score": 85,
      "grade": "B",
      "created_at": "2026-03-13T10:00:00+00:00"
    },
    {
      "id": 41,
      "score": 80,
      "grade": "B",
      "created_at": "2026-03-12T10:00:00+00:00"
    }
  ],
  "meta": {
    "total": 42,
    "pages": 3,
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

---

### POST /wp-system-report/v1/history

Capture a new report snapshot. The current live report data and health score are calculated and persisted immediately.

**Permission:** `manage_options` (filterable via `wp_system_report_capability`)

**No request body required.**

**Response:** HTTP `201 Created` with the full snapshot object (same shape as the single-snapshot GET response below).

---

### DELETE /wp-system-report/v1/history

Delete all saved snapshots. This action is irreversible.

**Permission:** `manage_options` (filterable via `wp_system_report_capability`)

**Response:**

```json
{
  "status": "success",
  "data": {
    "deleted": 42
  },
  "meta": {
    "purged": true,
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

---

### GET /wp-system-report/v1/history/{id}

Retrieve a single snapshot including its full report data.

**Permission:** `manage_options` (filterable via `wp_system_report_capability`)

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Snapshot ID |

**Response:**

```json
{
  "status": "success",
  "data": {
    "id": 42,
    "score": 85,
    "grade": "B",
    "created_at": "2026-03-13T10:00:00+00:00",
    "report_data": {
      "wordpress_environment": { ... }
    }
  },
  "meta": {
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Errors:**

| Code | HTTP | Description |
|------|------|-------------|
| `wp_system_report_snapshot_not_found` | 404 | No snapshot exists with that ID |

---

### DELETE /wp-system-report/v1/history/{id}

Delete a single snapshot by ID.

**Permission:** `manage_options` (filterable via `wp_system_report_capability`)

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Snapshot ID |

**Response:**

```json
{
  "status": "success",
  "data": {
    "deleted": true
  },
  "meta": {
    "id": 42,
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Errors:**

| Code | HTTP | Description |
|------|------|-------------|
| `wp_system_report_snapshot_not_found` | 404 | No snapshot exists with that ID |

---

### GET /wp-system-report/v1/history/trend

Return health score trend data over a time window for charting and monitoring purposes.

**Permission:** `manage_options` (filterable via `wp_system_report_capability`)

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `days` | integer | `30` | Number of days of history to include (1–365) |
| `after` | string | — | Return data after this ISO 8601 datetime (overrides `days`) |

**Response:**

```json
{
  "status": "success",
  "data": {
    "data_points": [
      {
        "date": "2026-03-12",
        "score": 80,
        "grade": "B"
      },
      {
        "date": "2026-03-13",
        "score": 85,
        "grade": "B"
      }
    ],
    "latest": {
      "id": 42,
      "score": 85,
      "grade": "B",
      "created_at": "2026-03-13T10:00:00+00:00"
    },
    "period_days": 30
  },
  "meta": {
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

---

## Report Diff Endpoints

### POST /wp-system-report/v1/diff

Compare two report snapshots and return a structured diff. Either operand can be a historical snapshot ID or the string `"current"` to generate a live report on the fly.

**Permission:** `manage_options` (filterable via `wp_system_report_capability`)

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `before` | integer or `"current"` | Yes | Snapshot ID for the older report, or `"current"` for a live report |
| `after` | integer or `"current"` | Yes | Snapshot ID for the newer report, or `"current"` for a live report |

```json
{
  "before": 41,
  "after": "current"
}
```

**Response:**

```json
{
  "status": "success",
  "data": {
    "before_label": "2026-03-12T10:00:00+00:00",
    "after_label": "Current",
    "changes": [
      {
        "section": "wordpress_environment",
        "field": "WordPress Version",
        "before": "6.6.0",
        "after": "6.7.0",
        "change_type": "changed"
      }
    ],
    "summary": {
      "added": 0,
      "removed": 0,
      "changed": 1
    },
    "health_score": {
      "before": {
        "score": 80,
        "grade": "B"
      },
      "after": {
        "score": 85,
        "grade": "B"
      }
    }
  },
  "meta": {
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Errors:**

| Code | HTTP | Description |
|------|------|-------------|
| `rest_snapshot_not_found` | 404 | A referenced snapshot ID does not exist |
| `rest_invalid_snapshot_id` | 400 | A snapshot ID value is less than 1 |
| `rest_report_history_disabled` | 400 | Report history is disabled and a snapshot ID was referenced |

---

## Error Log Endpoints

All error log endpoints share the same permission model: `manage_options` by default, filterable via `wp_system_report_error_log_capability`. The capability filter only accepts admin-level capabilities (see [Capability Filters](#capability-filters)).

### GET /wp-system-report/v1/error-log

Retrieve lines from the PHP error log file.

**Permission:** `manage_options` (filterable via `wp_system_report_error_log_capability`)

**Query Parameters:**

| Parameter | Type | Default | Range | Description |
|-----------|------|---------|-------|-------------|
| `lines` | integer | `100` | 1–10,000 | Number of log lines to return (from the end of the file) |
| `format` | string | `json` | `json`, `raw` | Output format |

**Response — `json` format:**

```json
{
  "status": "success",
  "data": {
    "lines": [
      "[13-Mar-2026 09:00:00 UTC] PHP Notice: Undefined variable ...",
      "[13-Mar-2026 09:01:00 UTC] PHP Warning: Division by zero ..."
    ],
    "count": 2,
    "file": {
      "path": "/var/www/html/wp-content/debug.log",
      "size": "1.2 MB",
      "exists": true
    }
  },
  "meta": {
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Response — `raw` format:**

Returns the log lines as `text/plain; charset=utf-8`. The `X-Content-Type-Options: nosniff` header is included. The response is not wrapped in the JSON envelope.

**Errors:**

| Code | HTTP | Description |
|------|------|-------------|
| `wp_system_report_no_log` | 404 | No error log file could be located |
| `wp_system_report_unsafe_path` | 403 | The resolved log path is outside the allowed directory boundary |

---

### GET /wp-system-report/v1/error-log/status

Return debug configuration constants and error log file metadata. The response is cached in a transient for 30 seconds to minimise repeated filesystem reads.

**Permission:** `manage_options` (filterable via `wp_system_report_error_log_capability`)

**No query parameters.**

**Response:**

```json
{
  "status": "success",
  "data": {
    "debug_enabled": false,
    "debug_log": false,
    "debug_display": false,
    "log_file": "/var/www/html/wp-content/debug.log",
    "log_file_exists": true,
    "log_file_size": "1.2 MB",
    "toggle": {
      "can_modify": true,
      "reason": null
    },
    "settings": {
      "error_log_lines": 100,
      "notifications_enabled": false
    }
  },
  "meta": {
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

---

### POST /wp-system-report/v1/error-log/toggle

Enable or disable WordPress debug logging by modifying the `WP_DEBUG`, `WP_DEBUG_LOG`, and `WP_DEBUG_DISPLAY` constants in `wp-config.php`.

A 3-second cooldown is enforced between toggle operations to prevent rapid repeated file modifications.

**Permission:** `manage_options` (filterable via `wp_system_report_error_log_capability`)

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `enable` | boolean | Yes | `true` to enable debug logging, `false` to disable it |

```json
{
  "enable": true
}
```

**Response:**

```json
{
  "status": "success",
  "data": {
    "success": true,
    "enabled": true,
    "state": {
      "can_modify": true,
      "reason": null
    }
  },
  "meta": {
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Errors:**

| Code | HTTP | Description |
|------|------|-------------|
| `wp_system_report_rate_limited` | 429 | A toggle was performed within the last 3 seconds |
| `wp_system_report_cannot_modify` | 403 | `wp-config.php` is not writable or file modifications are disabled |
| `wp_system_report_toggle_failed` | 500 | The write to `wp-config.php` failed |

---

### GET /wp-system-report/v1/error-log/stream

Open a long-lived Server-Sent Events (SSE) stream that delivers new log lines to the client in real time as they are appended to the error log file.

**Permission:** `manage_options` (filterable via `wp_system_report_error_log_capability`)

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `last_event_id` | string | No | The last SSE event ID received by the client, used to resume a dropped connection without missing lines |

**Response format:** `text/event-stream`

The response is a continuous stream of SSE frames. Each frame carries a single log line as its `data` field. The PHP execution time limit is removed for the duration of the stream.

```
id: 1
data: [13-Mar-2026 10:01:00 UTC] PHP Notice: Example notice

id: 2
data: [13-Mar-2026 10:01:05 UTC] PHP Warning: Example warning
```

**Client example:**

```javascript
const source = new EventSource(
  '/wp-json/wp-system-report/v1/error-log/stream',
  { withCredentials: true }
);

source.onmessage = ( event ) => {
  console.log( event.data );
};
```

---

## Fixer Endpoints

Fixer endpoints are only available when fixer capabilities are enabled in the plugin. Both endpoints share the same permission check.

**Permission:** `manage_options` (filterable via `wp_system_report_capability`)

**Feature flag:** Returns HTTP 403 with code `wp_system_report_fixers_disabled` if fixers are not available.

### GET /wp-system-report/v1/fixes

List all registered fixers with their metadata and current status.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `category` | string | No | Filter the list to fixers in a specific category |

**Response:**

```json
{
  "status": "success",
  "data": [
    {
      "id": "disable_file_editing",
      "label": "Disable File Editing",
      "description": "Disables the built-in theme and plugin file editors by defining DISALLOW_FILE_EDIT in wp-config.php.",
      "category": "security",
      "risk_level": "low",
      "risk_label": "Low",
      "requires_confirmation": false,
      "can_fix": true
    },
    {
      "id": "autoload_optimizer",
      "label": "Autoload Optimizer",
      "description": "Identifies and flags large autoloaded options that may slow down every page load.",
      "category": "performance",
      "risk_level": "medium",
      "risk_label": "Medium",
      "requires_confirmation": true,
      "can_fix": true
    }
  ],
  "meta": {
    "total": 2,
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Fixer risk levels:**

| `risk_level` | `requires_confirmation` | Description |
|---|---|---|
| `low` | `false` | Safe to apply without extra confirmation |
| `medium` | `true` | Requires `confirmed: true` in the execute request |
| `high` | `true` | Requires `confirmed: true` in the execute request |

---

### POST /wp-system-report/v1/fixes/{fix_id}

Execute a specific fixer. Medium and high risk fixers require an explicit confirmation flag.

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `fix_id` | string | The fixer identifier (alphanumeric and underscores only) |

**Request Body:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `confirmed` | boolean | `false` | Must be `true` for medium and high risk fixers |

```json
{
  "confirmed": true
}
```

**Response — fix applied:**

```json
{
  "status": "success",
  "data": {
    "fix_id": "disable_file_editing",
    "result": {
      "success": true,
      "message": "DISALLOW_FILE_EDIT has been defined in wp-config.php."
    },
    "applied": true
  },
  "meta": {
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Response — no issue detected (`can_fix` is false):**

```json
{
  "status": "success",
  "data": {
    "fix_id": "disable_file_editing",
    "result": {
      "success": true,
      "message": "No issues detected. Nothing to fix."
    },
    "applied": false
  },
  "meta": {
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Errors:**

| Code | HTTP | Description |
|------|------|-------------|
| `wp_system_report_fixer_not_found` | 404 | No fixer is registered with that ID |
| `wp_system_report_confirmation_required` | 409 | The fixer has medium or high risk and `confirmed` was not `true` |

---

## Notification Endpoints

### GET /wp-system-report/v1/notifications/settings

Retrieve the current notification settings.

**Permission:** `manage_options` (not filterable)

**No query parameters.**

**Response:**

```json
{
  "status": "success",
  "data": {
    "notifications_enabled": false,
    "notify_email_enabled": false,
    "notify_email_recipients": "",
    "notify_slack_enabled": false,
    "notify_webhook_enabled": false,
    "slack_webhook_url": "",
    "webhook_urls": "",
    "notify_critical_threshold": 1,
    "notify_warning_threshold": 5,
    "notification_cooldown": 3600
  },
  "meta": {
    "type": "notification_settings",
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

---

### POST /wp-system-report/v1/notifications/settings

Update one or more notification settings. Only the keys present in the request body are modified; all others are left unchanged.

**Permission:** `manage_options` (not filterable)

**Request Body (all fields optional):**

| Parameter | Type | Constraints | Description |
|-----------|------|-------------|-------------|
| `notifications_enabled` | boolean | — | Master toggle for all notifications |
| `notify_email_enabled` | boolean | — | Enable email notifications |
| `notify_email_recipients` | string | — | Newline- or comma-separated list of email addresses |
| `notify_slack_enabled` | boolean | — | Enable Slack notifications |
| `notify_webhook_enabled` | boolean | — | Enable webhook notifications |
| `slack_webhook_url` | string (URI) | — | Incoming webhook URL for Slack |
| `webhook_urls` | string | — | Newline- or comma-separated list of webhook URLs |
| `notify_critical_threshold` | integer | 1–100 | Minimum critical issue count to trigger a notification |
| `notify_warning_threshold` | integer | 1–100 | Minimum warning count to trigger a notification |
| `notification_cooldown` | integer | 60–86400 | Seconds between repeated notifications for the same state |

```json
{
  "notifications_enabled": true,
  "notify_email_enabled": true,
  "notify_email_recipients": "admin@example.com\nops@example.com",
  "notify_critical_threshold": 1,
  "notification_cooldown": 3600
}
```

**Response:**

```json
{
  "status": "success",
  "data": {
    "updated": [
      "notifications_enabled",
      "notify_email_enabled",
      "notify_email_recipients",
      "notify_critical_threshold",
      "notification_cooldown"
    ]
  },
  "meta": {
    "type": "notification_settings_updated",
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

---

### POST /wp-system-report/v1/notifications/test

Send a test notification through the specified channel to verify that the channel is configured correctly.

**Permission:** `manage_options` (not filterable)

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `channel` | string | Yes | Channel to test: `webhook`, `email`, or `slack` |

```json
{
  "channel": "email"
}
```

**Response — `webhook` channel:**

```json
{
  "status": "success",
  "data": {
    "channel": "webhook",
    "results": [
      {
        "url": "https://hooks.example.com/...",
        "success": true,
        "status_code": 200
      }
    ]
  },
  "meta": {
    "type": "test_notification",
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Response — `email` channel:**

```json
{
  "status": "success",
  "data": {
    "channel": "email",
    "recipients": ["admin@example.com"],
    "sent": true
  },
  "meta": {
    "type": "test_notification",
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Response — `slack` channel:**

```json
{
  "status": "success",
  "data": {
    "channel": "slack",
    "sent": true
  },
  "meta": {
    "type": "test_notification",
    "generated_at": "2026-03-13T10:00:00+00:00",
    "plugin_version": "1.0.0"
  }
}
```

**Errors:**

| Code | HTTP | Description |
|------|------|-------------|
| `wp_system_report_no_webhooks` | 400 | No webhook URLs are configured |
| `wp_system_report_no_email` | 400 | No email recipients are configured |
| `wp_system_report_no_slack` | 400 | No Slack webhook URL is configured |
| `wp_system_report_invalid_channel` | 400 | The `channel` value is not one of `webhook`, `email`, `slack` |

---

## Capability Filters

### Report, Health Score, History, Diff, and Fixer endpoints

```php
// Use a custom capability for report, history, diff, health score, and fixer endpoints.
add_filter( 'wp_system_report_capability', function () {
    return 'edit_theme_options';
} );
```

### Error log endpoints

```php
// Use a custom capability for error log endpoints.
add_filter( 'wp_system_report_error_log_capability', function () {
    return 'manage_network';
} );
```

> **Security note:** The `wp_system_report_error_log_capability` filter enforces an allowlist of admin-level capabilities. Returning a low-privilege capability such as `read` will be silently rejected and the default `manage_options` will be used instead. Accepted values are: `manage_options`, `manage_network`, `install_plugins`, `edit_plugins`, `update_plugins`, `delete_plugins`, `manage_network_plugins`.

### Notification endpoints

Notification endpoints always require `manage_options` and do not support a capability filter.

---

## Error Responses

When a request fails, the API returns a standard WordPress REST API error object. The HTTP status code and `code` field identify the failure:

```json
{
  "code": "wp_system_report_rest_forbidden",
  "message": "Sorry, you are not allowed to view the system report.",
  "data": {
    "status": 401
  }
}
```

**Common error codes:**

| Code | HTTP | Applicable endpoints |
|------|------|----------------------|
| `wp_system_report_rest_forbidden` | 401 or 403 | All endpoints — insufficient capability |
| `wp_system_report_fixers_disabled` | 403 | Fixer endpoints — feature not enabled |
| `wp_system_report_fixer_not_found` | 404 | `POST /fixes/{fix_id}` — unknown fixer ID |
| `wp_system_report_confirmation_required` | 409 | `POST /fixes/{fix_id}` — medium/high risk fixer, no confirmation |
| `wp_system_report_snapshot_not_found` | 404 | History single-item endpoints |
| `wp_system_report_no_log` | 404 | `GET /error-log` — no log file found |
| `wp_system_report_unsafe_path` | 403 | `GET /error-log` — path outside allowed boundary |
| `wp_system_report_rate_limited` | 429 | `POST /error-log/toggle` — cooldown active |
| `wp_system_report_cannot_modify` | 403 | `POST /error-log/toggle` — file not writable |
