# Architecture

## Overview

WP System Report follows a collector/formatter pattern. Collectors gather diagnostic data, the report generator orchestrates collection, and formatters transform the data for different output targets.

## Directory Structure

```
wp-system-report/
  wp-system-report.php              # Bootstrap, constants, autoloader
  uninstall.php                     # Cleanup on plugin deletion
  composer.json                     # Dependencies and dev tooling
  includes/
    class-plugin.php                # Singleton orchestrator (hooks, initialization)
    class-admin-page.php            # Admin menu registration and page rendering
    class-report-generator.php      # Collector registry and report generation
    class-rest-controller.php       # Report REST API (wp-system-report/v1/report)
    class-error-log-controller.php  # Error log REST API (wp-system-report/v1/error-log)
    class-error-log-reader.php      # Safe error log file reader with redaction
    class-debug-toggle.php          # wp-config.php debug constant management
    class-settings.php              # Plugin settings (wp_system_report_settings option)
    class-github-updater.php        # GitHub Releases update checker
    collectors/
      interface-collector.php       # Collector contract
      class-abstract-collector.php  # Base class with caching and helpers
      class-*.php                   # 17 concrete collector implementations
    formatters/
      interface-formatter.php       # Formatter contract
      class-plain-text-formatter.php
      class-github-formatter.php
      class-ai-formatter.php
  assets/
    css/wp-system-report-admin.css  # Admin page styles
    js/wp-system-report-admin.js    # Report tab JavaScript (vanilla, no jQuery)
    js/wp-system-report-error-log.js # Error log tab JavaScript (vanilla, no jQuery)
  templates/
    admin-page.php                  # Tabbed admin page template
    error-log-tab.php               # Error log tab template
  tests/                            # PHPUnit test suite
```

## Data Flow

```
1. User navigates to Tools > WP System Report
   └── Admin_Page renders the template

2. User clicks "Download for AI analysis"
   └── JavaScript calls REST API: GET /wp-system-report/v1/report?format=ai

3. REST_Controller receives the request
   ├── Checks permissions (manage_options)
   └── Calls Report_Generator::generate()

4. Report_Generator iterates all registered collectors (sorted by priority)
   ├── Collector 1 (priority 10): WordPress_Environment::collect()
   ├── Collector 2 (priority 20): Server_Environment::collect()
   ├── ...cached collectors check transient first...
   └── Collector 17 (priority 170): Advanced_Diagnostics::collect()

5. Each collector's fields pass through wp_system_report_fields_{id} filter

6. Report_Generator returns the complete report data array

7. REST_Controller passes data to the requested Formatter
   └── AI_Formatter::format() produces structured markdown

8. Response is returned with appropriate Content-Type header
```

## Class Relationships

### Plugin (Singleton)

The `Plugin` class is the entry point. It:
- Registers all default collectors with the `Report_Generator`
- Registers WordPress hooks (admin menu, REST routes, cache invalidation)
- Provides `get_report_generator()` for external access

### Report Generator (Registry)

The `Report_Generator` manages the collector registry:
- `register_collector()` adds a collector
- `generate()` runs all collectors and applies field filters
- `generate_section()` runs a single collector by ID
- Collectors are sorted by priority before execution

### Collectors (Strategy Pattern)

All collectors implement the `Collector` interface:
- `get_id()` - Unique identifier (used as array key and filter suffix)
- `get_label()` - Human-readable section name
- `get_description()` - Section description for AI context
- `get_priority()` - Sort order (lower = earlier)
- `collect()` - Returns array of field arrays

The `Abstract_Collector` base class adds:
- Transient caching via `get_cached_data()`
- Field builder via `make_field()`
- Utility methods for formatting

### Formatters (Strategy Pattern)

All formatters implement the `Formatter` interface:
- `format( $report_data )` - Transform report data to string
- `get_content_type()` - MIME type for the response
- `get_file_extension()` - File extension for downloads

## Autoloader

The custom autoloader in `wp-system-report.php` maps the `SystemReport\` namespace to `includes/` using WordPress file naming conventions:

| Fully Qualified Class | File Path |
|----------------------|-----------|
| `SystemReport\Plugin` | `includes/class-plugin.php` |
| `SystemReport\Collectors\Active_Plugins` | `includes/collectors/class-active-plugins.php` |
| `SystemReport\Collectors\Collector` | `includes/collectors/interface-collector.php` |
| `SystemReport\Formatters\AI_Formatter` | `includes/formatters/class-ai-formatter.php` |

The Composer autoloader (`vendor/autoload.php`) is loaded separately for third-party dependencies.

## Caching Strategy

| Transient Key | Collector | Default TTL |
|---------------|-----------|-------------|
| `sr_active_plugins` | Active Plugins | 1 hour |
| `sr_inactive_plugins` | Inactive Plugins | 1 hour |
| `sr_dropins_mu_plugins` | Drop-ins & MU Plugins | 1 hour |
| `sr_theme_info` | Theme Information | 1 hour |
| `sr_site_health` | Site Health | 1 hour |
| `sr_database` | Database | 1 hour |
| `sr_post_type_counts` | Post Type Counts | 1 hour |
| `sr_advanced_diagnostics` | Advanced Diagnostics | 1 hour |
| `sr_github_update` | GitHub Updater | 12 hours |
| `sr_github_update_failed` | GitHub Updater (failure) | 30 minutes |

Cache invalidation is triggered by:
- Theme switch (`switch_theme`)
- Plugin activation/deactivation (`activate_plugin`, `deactivate_plugin`)
- Plugin/theme upgrades (`upgrader_process_complete`)

## Security Model

- All endpoints require `manage_options` capability (filterable)
- Error log capability filter only accepts admin-level capabilities
- Error log reader validates file paths are within allowed directories
- GitHub formatter redacts sensitive data (URLs, database prefixes)
- Debug toggle uses file locking and rate limiting (3-second cooldown)
- Uninstall cleanup removes all transients and options
