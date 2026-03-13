# Extending the Plugin

WP System Report is designed to be extended. You can add custom collectors, modify report output, and integrate with external tools.

## Writing a Custom Collector

A collector gathers diagnostic data for one section of the report. Every collector must implement the `SystemReport\Collectors\Collector` interface or extend `SystemReport\Collectors\Abstract_Collector`.

### Step 1: Create the Collector Class

```php
<?php

use SystemReport\Collectors\Abstract_Collector;

class My_Hosting_Collector extends Abstract_Collector {

    public function get_id(): string {
        return 'my_hosting';
    }

    public function get_label() {
        return __( 'Hosting Environment', 'my-plugin' );
    }

    public function get_description() {
        return __( 'Hosting provider details and resource limits.', 'my-plugin' );
    }

    public function get_priority(): int {
        return 25; // Between WordPress Environment (10) and Server Environment (20).
    }

    public function collect(): array {
        return array(
            $this->make_field( 'Hosting Provider', $this->detect_host(), array(
                'status' => 'info',
            ) ),
            $this->make_field( 'PHP Workers', ini_get( 'pm.max_children' ) ?: 'Unknown', array(
                'status'      => 'info',
                'description' => 'Maximum concurrent PHP processes.',
            ) ),
            $this->make_field( 'Disk Quota', $this->get_disk_quota(), array(
                'status'      => $this->is_disk_low() ? 'warning' : 'good',
                'recommended' => '> 1 GB free',
            ) ),
        );
    }

    private function detect_host(): string {
        // Detection logic here.
        return 'Unknown';
    }

    private function get_disk_quota(): string {
        return $this->format_size( disk_free_space( ABSPATH ) );
    }

    private function is_disk_low(): bool {
        return disk_free_space( ABSPATH ) < 1073741824; // 1 GB
    }
}
```

### Step 2: Register the Collector

```php
add_filter( 'wp_system_report_collectors', function ( array $collectors ): array {
    $collectors['my_hosting'] = new My_Hosting_Collector();
    return $collectors;
} );
```

### Abstract Collector Helper Methods

The `Abstract_Collector` base class provides these utilities:

| Method | Description |
|--------|-------------|
| `make_field( $label, $value, $options = [] )` | Build a field array with defaults |
| `format_size( $bytes )` | Format bytes as human-readable (e.g., `2.5 MB`) |
| `format_boolean( $value )` | Format boolean as `Yes` / `No` |
| `get_constant_value( $name, $fallback )` | Safely get a constant value |
| `convert_to_bytes( $value )` | Convert PHP ini values (e.g., `128M`) to bytes |
| `get_cached_data()` | Returns cached data or runs `collect()` and caches the result |

### Field Structure

Each field returned by `make_field()` has these properties:

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `label` | string | Yes | Display label |
| `value` | string | Yes | Formatted display value |
| `debug` | mixed | No | Raw/machine-readable value (defaults to `value`) |
| `status` | `Status` or string | No | `Status::Good`, `Status::Warning`, `Status::Critical`, or `Status::Info`. Legacy strings (`'good'`, `'warning'`, etc.) are also accepted. |
| `description` | string | No | Contextual description for AI export |
| `recommended` | string | No | Recommended value for AI export |
| `private` | bool | No | If `true`, excluded from exports |
| `export_label` | string | No | Compact label for text export (defaults to `label`) |
| `fix_id` | string or null | No | ID of an available fixer that can resolve this issue |

### Caching

To enable transient caching for your collector, override the `get_cache_key()` method:

```php
protected function get_cache_key(): string {
    return 'sr_my_hosting';
}
```

The cache TTL defaults to 1 hour and is filterable via `wp_system_report_cache_ttl`.

---

## Modifying Existing Collectors

### Add Fields to a Section

```php
add_filter( 'wp_system_report_fields_server_environment', function ( array $fields ): array {
    $fields[] = array(
        'label'  => 'Custom Server Check',
        'value'  => 'Passed',
        'status' => 'good',
    );
    return $fields;
} );
```

### Remove a Collector Entirely

```php
add_filter( 'wp_system_report_collectors', function ( array $collectors ): array {
    unset( $collectors['post_type_counts'] );
    return $collectors;
} );
```

### Change Collector Priority

Collector priority is determined by each collector's `get_priority()` method. To reorder an existing collector, replace it with an anonymous subclass that overrides the priority:

```php
add_filter( 'wp_system_report_collectors', function ( array $collectors ): array {
    if ( isset( $collectors['security'] ) ) {
        // Replace with a subclass that runs first in the report.
        $collectors['security'] = new class extends \SystemReport\Collectors\Security {
            public function get_priority(): int {
                return 5; // Before WordPress Environment (10).
            }
        };
    }
    return $collectors;
} );
```

---

## Writing a Custom Formatter

Formatters implement `SystemReport\Formatters\Formatter`:

```php
<?php

use SystemReport\Formatters\Formatter;

class CSV_Formatter implements Formatter {

    public function format( array $report_data ): string {
        $output = "Section,Field,Value,Status\n";

        foreach ( $report_data as $section_id => $section ) {
            foreach ( $section['fields'] as $field ) {
                $output .= sprintf(
                    "%s,%s,%s,%s\n",
                    $this->escape_csv( $section['label'] ),
                    $this->escape_csv( $field['label'] ),
                    $this->escape_csv( $field['value'] ),
                    $field['status'] ?? ''
                );
            }
        }

        return $output;
    }

    public function get_content_type(): string {
        return 'text/csv; charset=utf-8';
    }

    public function get_file_extension(): string {
        return 'csv';
    }

    private function escape_csv( string $value ): string {
        return '"' . str_replace( '"', '""', $value ) . '"';
    }
}
```

---

## Adding AI Issue Detection

Add custom heuristic checks to the AI export:

```php
add_filter( 'wp_system_report_ai_issues', function ( array $issues, array $report_data ): array {
    // Check if Redis is configured but not responding.
    if ( class_exists( 'Redis' ) && ! wp_using_ext_object_cache() ) {
        $issues[] = array(
            'severity'    => 'warning',
            'title'       => 'Redis Extension Loaded but Object Cache Inactive',
            'description' => 'The Redis PHP extension is available but no persistent object cache is configured.',
        );
    }
    return $issues;
}, 10, 2 );
```

---

## Writing a Custom Fixer

See the [Fixers documentation](fixers.md#writing-a-custom-fixer) for a complete guide to implementing the `Fixer` interface, including risk levels, the `Fix_Result` value object, and registration via the `wp_system_report_fixers` filter.

---

## Adding Redaction Patterns

Add patterns to the GitHub formatter's redaction engine:

```php
add_filter( 'wp_system_report_redactions', function ( array $redactions ): array {
    $redactions[] = array(
        'pattern'     => '/my-internal-domain\.local/',
        'replacement' => 'internal.example.com',
    );
    return $redactions;
} );
```

---

## Testing Custom Extensions

See the [Testing Guide](testing.md) for instructions on writing PHPUnit tests for custom collectors and fixers, including mocking strategies for WordPress dependencies.
