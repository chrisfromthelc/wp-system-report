# Hooks Reference

Complete reference for all actions and filters fired by WP System Report.

## Filters

### wp_system_report_collectors

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

### wp_system_report_fields_{collector_id}

Modify the fields returned by a specific collector. Replace `{collector_id}` with the collector's ID (e.g., `wordpress_environment`).

**File:** `includes/class-report-generator.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$fields` | `array` | Array of field arrays |
| `$collector` | `Collector` | The collector instance |

```php
add_filter( 'wp_system_report_fields_wordpress_environment', function ( array $fields, $collector ): array {
    // Add a custom field to the WordPress Environment section.
    $fields[] = array(
        'label' => 'Custom Check',
        'value' => 'All good',
        'status' => 'good',
    );
    return $fields;
}, 10, 2 );
```

---

### wp_system_report_capability

Change the required WordPress capability for accessing the report (admin page and REST API).

**Files:** `includes/class-rest-controller.php`, `includes/class-admin-page.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$capability` | `string` | `manage_options` | WordPress capability string |

```php
add_filter( 'wp_system_report_capability', function (): string {
    return 'edit_theme_options';
} );
```

---

### wp_system_report_error_log_capability

Change the required capability for error log access. Only admin-level capabilities are accepted.

**File:** `includes/class-error-log-controller.php`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$capability` | `string` | `manage_options` | WordPress capability string |

```php
add_filter( 'wp_system_report_error_log_capability', function (): string {
    return 'manage_network';
} );
```

---

### wp_system_report_cache_ttl

Change the transient cache duration for collectors.

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

### wp_system_report_constants

Filter the list of WordPress constants to check and display.

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

### wp_system_report_allowed_log_paths

Add additional directory paths that are allowed for error log reading.

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

### wp_system_report_redact_log_line

Redact sensitive content from individual error log lines before display.

**File:** `includes/class-error-log-reader.php`

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

### wp_system_report_redactions

Filter the redaction patterns used by the GitHub formatter.

**File:** `includes/formatters/class-github-formatter.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$redactions` | `array` | Array of arrays with `pattern` and `replacement` keys |

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

### wp_system_report_ai_header

Customize the header section of the AI-formatted report.

**File:** `includes/formatters/class-ai-formatter.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$header` | `string` | The markdown header string |

```php
add_filter( 'wp_system_report_ai_header', function ( string $header ): string {
    return $header . "\n> Custom context: This is a staging site.\n";
} );
```

---

### wp_system_report_ai_issues

Add or modify the detected issues in the AI report.

**File:** `includes/formatters/class-ai-formatter.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$issues` | `array` | Array of issue arrays with `severity`, `title`, `description` keys |
| `$report_data` | `array` | The full raw report data |

```php
add_filter( 'wp_system_report_ai_issues', function ( array $issues, array $report_data ): array {
    $issues[] = array(
        'severity'    => 'warning',
        'title'       => 'Custom Plugin Check',
        'description' => 'My plugin detected an unusual configuration.',
    );
    return $issues;
}, 10, 2 );
```

---

## Actions

### wp_system_report_before_debug_toggle

Fires immediately before the debug constants are modified in `wp-config.php`.

**File:** `includes/class-debug-toggle.php`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$enable` | `bool` | `true` if enabling debug, `false` if disabling |
| `$config_path` | `string` | Absolute path to `wp-config.php` |

```php
add_action( 'wp_system_report_before_debug_toggle', function ( bool $enable, string $config_path ): void {
    if ( $enable ) {
        error_log( 'WP System Report: Debug mode being enabled.' );
    }
}, 10, 2 );
```

---

### wp_system_report_after_debug_toggle

Fires immediately after the debug constants have been modified in `wp-config.php`.

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
