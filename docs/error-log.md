# Error Log Viewer

The **Error Log** tab provides tools for viewing and managing the PHP error log directly from the WordPress admin.

## Debug Toggle

Toggle `WP_DEBUG`, `WP_DEBUG_LOG`, and `WP_DEBUG_DISPLAY` with a single click. The toggle modifies `wp-config.php` using the `WPConfigTransformer` library with file locking to prevent concurrent modifications.

### Writable Mode

When `wp-config.php` is writable, the toggle button enables or disables all three constants:

- **Enable**: Sets `WP_DEBUG` and `WP_DEBUG_LOG` to `true`, `WP_DEBUG_DISPLAY` to `false`
- **Disable**: Sets all three to `false`

### Read-Only Fallback

When `wp-config.php` is not writable (e.g., `DISALLOW_FILE_MODS` is set, or file permissions prevent editing), the UI displays:

- Read-only status badges showing the current state of each constant
- Copy-pasteable PHP code snippets to manually add to `wp-config.php`
- WP-CLI commands for environments that support it

### Rate Limiting

The debug toggle has a 3-second cooldown between operations to prevent rapid toggling and filesystem contention.

## Error Log Viewer

View the latest lines from the PHP error log without SSH or FTP access.

### Features

- **Configurable line count**: Load between 1 and 10,000 lines (default: 100)
- **Copy to clipboard**: Copy log output directly
- **Download**: Save the log as a file
- **Include system report**: Checkbox to prepend the full system report for context when sharing with developers
- **Automatic redaction**: Sensitive data (paths, credentials) is redacted via the `wp_system_report_redact_log_line` filter

### Security

- Requires `manage_options` capability (filterable via `wp_system_report_error_log_capability`)
- Only allows reading from the configured error log path and any paths whitelisted via the `wp_system_report_allowed_log_paths` filter
- Path traversal protection validates all file paths are within allowed directories

## REST API Endpoints

See the [REST API documentation](rest-api.md) for programmatic access to error log features:

- `GET /wp-system-report/v1/error-log` - Retrieve log lines
- `GET /wp-system-report/v1/error-log/status` - Debug constant status
- `POST /wp-system-report/v1/error-log/toggle` - Toggle debug mode

## Hooks

| Hook | Type | Description |
|------|------|-------------|
| `wp_system_report_error_log_capability` | Filter | Change required capability (default: `manage_options`) |
| `wp_system_report_allowed_log_paths` | Filter | Add additional directories for log file reading |
| `wp_system_report_redact_log_line` | Filter | Redact sensitive content from each log line |
| `wp_system_report_before_debug_toggle` | Action | Fires before debug mode is toggled |
| `wp_system_report_after_debug_toggle` | Action | Fires after debug mode is toggled |
