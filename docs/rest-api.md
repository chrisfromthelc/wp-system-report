# REST API

WP System Report exposes a REST API under the `wp-system-report/v1` namespace. All endpoints require the `manage_options` capability by default.

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

## Report Endpoints

### GET /wp-system-report/v1/report

Generate a system report in the specified format.

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `format` | string | `json` | Output format: `json`, `plain`, `github`, or `ai` |

**Response (JSON format):**

```json
{
  "wordpress_environment": {
    "label": "WordPress Environment",
    "description": "Core WordPress installation settings and configuration.",
    "fields": [
      {
        "label": "WordPress Version",
        "value": "6.9.1",
        "debug": "6.9.1",
        "status": "good",
        "description": "",
        "recommended": ">= 6.4"
      }
    ]
  }
}
```

**Response (plain/github/ai):** Returns the formatted string with the appropriate content type header.

**Permission:** `manage_options` (filterable via `wp_system_report_capability`)

---

## Error Log Endpoints

### GET /wp-system-report/v1/error-log

Retrieve the latest lines from the PHP error log.

**Parameters:**

| Parameter | Type | Default | Range | Description |
|-----------|------|---------|-------|-------------|
| `lines` | integer | `100` | 1–10,000 | Number of log lines to return |
| `format` | string | `json` | `json`, `raw` | Output format |

**Response (JSON):**

```json
{
  "lines": [
    "[11-Mar-2026 14:30:00 UTC] PHP Notice: ...",
    "[11-Mar-2026 14:31:00 UTC] PHP Warning: ..."
  ],
  "total_lines": 2,
  "file": "/path/to/debug.log",
  "file_size": "1.2 MB"
}
```

**Response (raw):** Returns the log text as `text/plain`.

**Permission:** `manage_options` (filterable via `wp_system_report_error_log_capability`)

---

### GET /wp-system-report/v1/error-log/status

Get the current debug configuration and error log file information.

**Response:**

```json
{
  "debug_enabled": false,
  "debug_log": false,
  "debug_display": false,
  "log_file": "/path/to/debug.log",
  "log_file_exists": true,
  "log_file_size": "1.2 MB",
  "can_toggle": true,
  "settings": {
    "error_log_lines": 100
  }
}
```

---

### POST /wp-system-report/v1/error-log/toggle

Enable or disable debug logging. Modifies `WP_DEBUG`, `WP_DEBUG_LOG`, and `WP_DEBUG_DISPLAY` in `wp-config.php`.

**Request Body:**

```json
{
  "enable": true
}
```

**Response:**

```json
{
  "success": true,
  "debug_enabled": true
}
```

**Rate Limit:** 3-second cooldown between toggle operations.

**Permission:** `manage_options` (filterable via `wp_system_report_error_log_capability`)

---

## Changing the Required Capability

Both the report and error log endpoints support filtering the required capability:

```php
// Require a custom capability for the report.
add_filter( 'wp_system_report_capability', function () {
    return 'edit_theme_options';
} );

// Require a custom capability for the error log.
add_filter( 'wp_system_report_error_log_capability', function () {
    return 'manage_network';
} );
```

> **Note:** The error log capability filter only accepts admin-level capabilities for security.
