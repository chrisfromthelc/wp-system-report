# WP System Report

A comprehensive WordPress system status report plugin with AI-optimized export. Standalone, no WooCommerce required.

## Features

- **Full System Diagnostics** - 17 collectors covering WordPress environment, server, database, plugins, themes, security, cron, REST API, and more
- **Multiple Export Formats** - Plain text, GitHub-friendly (with redactions and `<details>` wrapper), and AI-optimized markdown
- **AI-Ready Export** - Structured markdown with contextual descriptions, status indicators, recommendations, and proactive issue detection designed for Claude, ChatGPT, and other LLMs
- **REST API** - Full JSON API at `wp-system-report/v1/report` with format parameter support
- **Extensible** - Filter hooks for adding custom collectors, modifying fields, and extending issue detection
- **Zero Dependencies** - Works standalone without WooCommerce or any other plugin
- **Cached** - Transient caching for expensive collectors with automatic invalidation
- **Auto-Updates** - Checks GitHub Releases for new versions and serves updates through the WordPress dashboard

## Requirements

- WordPress 6.2+
- PHP 7.4+

## Installation

### Manual Installation

1. Download or clone this repository into `wp-content/plugins/wp-system-report/`
2. Activate the plugin through the WordPress admin
3. Navigate to **Tools > WP System Report**

### From GitHub

```bash
cd wp-content/plugins/
git clone https://github.com/chrisfromthelc/wp-system-report.git wp-system-report
```

## Usage

### Admin Page

Navigate to **Tools > WP System Report** to view the full system status report. The admin page provides:

| Button | Description |
|--------|-------------|
| **Get WP System Report** | Generates a plain text report from the displayed data |
| **Copy for Support** | Copies the report to clipboard |
| **Download for Support** | Downloads a `.txt` file |
| **Copy for GitHub** | Copies a redacted report wrapped in `<details>` tags |
| **Download for AI** | Downloads an AI-optimized `.md` file via the REST API |

### REST API

The plugin registers a REST endpoint at `wp-system-report/v1/report` (requires `manage_options` capability).

**Formats:**

```
GET /wp-json/wp-system-report/v1/report              # JSON (default)
GET /wp-json/wp-system-report/v1/report?format=plain  # Plain text
GET /wp-json/wp-system-report/v1/report?format=github # GitHub (redacted + details wrapper)
GET /wp-json/wp-system-report/v1/report?format=ai     # AI-optimized markdown
```

**Example (cURL):**

```bash
curl -H "Authorization: Basic BASE64_CREDENTIALS" \
  "https://example.com/wp-json/wp-system-report/v1/report?format=ai"
```

### AI Export Format

The AI export produces structured markdown designed for LLM consumption:

- **Header** with site URL, timestamp, WP/PHP versions
- **Issues Summary** with severity-ranked detected problems
- **Section Tables** with Setting | Value | Status | Recommended columns
- **Contextual Descriptions** as blockquotes for each section
- **Heuristic Checks** for common problems (PHP EOL, autoloaded options size, missing object cache, non-InnoDB tables)

## Report Sections

### Core Diagnostics (WooCommerce-parity)

1. **WordPress Environment** - URLs, version, memory, debug mode, cron, locale, multisite, object cache, environment type
2. **Server Environment** - Server software, PHP version/config, MySQL, cURL, extensions, connectivity
3. **Database** - All tables with sizes, engines, charset, total size
4. **Post Type Counts** - Count per registered post type
5. **Security** - HTTPS, error display, file editing, application passwords
6. **Active Plugins** - Name, version, update status, author
7. **Inactive Plugins** - Same fields for deactivated plugins
8. **Drop-in & MU Plugins** - Name, file, description
9. **Theme Information** - Active theme, parent, child/block theme detection

### Extended Diagnostics

10. **WordPress Constants** - WP_DEBUG, WP_CACHE, DISALLOW_FILE_EDIT, FORCE_SSL_ADMIN, etc.
11. **Filesystem Permissions** - Writability of core directories
12. **Site Health Results** - WordPress Site Health test results (good/recommended/critical)
13. **Cron Health** - Scheduled events, overdue jobs, orphaned schedules
14. **REST API Info** - REST availability, prefix, registered namespaces
15. **Custom Content Types** - Custom post types, taxonomies, image sizes, shortcodes, widgets
16. **WordPress Configuration** - User roles, permalink structure, comment/media settings
17. **Advanced Diagnostics** - Autoloaded options size, disk usage, rewrite rules, error log

## Extensibility

### Adding a Custom Collector

```php
add_filter( 'wp_system_report_collectors', function ( $collectors ) {
    $collectors['my_custom'] = new My_Custom_Collector();
    return $collectors;
});
```

Your collector must implement `SystemReport\Collectors\Collector`:

```php
use SystemReport\Collectors\Abstract_Collector;

class My_Custom_Collector extends Abstract_Collector {
    public function get_id(): string { return 'my_custom'; }
    public function get_label() { return 'My Custom Section'; }
    public function get_description() { return 'Custom diagnostic data.'; }
    public function get_priority(): int { return 200; }

    public function collect(): array {
        return array(
            $this->make_field( 'Custom Field', 'Custom Value', array(
                'status'      => 'good',
                'recommended' => 'Expected value',
            )),
        );
    }
}
```

### Available Filters

| Filter | Description |
|--------|-------------|
| `wp_system_report_collectors` | Add, remove, or reorder collectors |
| `wp_system_report_fields_{collector_id}` | Modify fields for a specific collector |
| `wp_system_report_ai_issues` | Add or modify AI-detected issues |
| `wp_system_report_ai_header` | Customize the AI report header |
| `wp_system_report_capability` | Change required capability (default: `manage_options`) |
| `wp_system_report_cache_ttl` | Change cache TTL (default: 1 hour) |
| `wp_system_report_redactions` | Add redaction patterns for GitHub export |
| `wp_system_report_constants` | Add/remove constants to display |

### Available Actions

| Action | Description |
|--------|-------------|
| `wp_system_report_after_section_{id}` | Fires after each section renders |

## Development

### Prerequisites

```bash
composer install
```

### Coding Standards (PHPCS)

```bash
composer phpcs        # Check
composer phpcbf       # Auto-fix
```

### Static Analysis (PHPStan)

```bash
composer phpstan      # Level 5
```

### Rector

```bash
composer rector-dry   # Dry run
composer rector       # Apply changes
```

### Run All Linters

```bash
composer lint         # PHPCS + PHPStan + Rector dry-run
```

### Tests (PHPUnit)

Tests require the WordPress test suite. Set up the test database:

```bash
# Set WP_TESTS_DIR to your WordPress test library path
export WP_TESTS_DIR=/path/to/wordpress-tests-lib

# Run tests
composer test
```

### Architecture

```
wp-system-report/
  wp-system-report.php           # Bootstrap + autoloader
  uninstall.php                  # Cleanup on deletion
  includes/
    class-plugin.php             # Singleton orchestrator
    class-admin-page.php         # Admin menu + rendering
    class-rest-controller.php    # WP_REST_Controller
    class-report-generator.php   # Collector registry
    class-github-updater.php   # GitHub release update checker
    collectors/
      interface-collector.php    # Collector contract
      class-abstract-collector.php # Shared helpers + caching
      class-*.php                # 17 concrete collectors
    formatters/
      interface-formatter.php    # Formatter contract
      class-plain-text-formatter.php
      class-github-formatter.php
      class-ai-formatter.php
  assets/
    css/wp-system-report-admin.css  # Admin styles
    js/wp-system-report-admin.js    # Vanilla JS (no jQuery)
  templates/
    admin-page.php               # Admin page template
    report-section.php           # Section template
  tests/                         # PHPUnit tests
```

### Field Structure

Each collector returns fields via `make_field()`:

```php
array(
    'label'        => 'WordPress Version',   // Display label
    'value'        => '6.9.1',               // Formatted display value
    'debug'        => '6.9.1',               // Raw/machine value
    'private'      => false,                 // Exclude from exports
    'status'       => 'good',                // good|warning|critical|info
    'description'  => '',                    // AI context
    'recommended'  => '>= 6.4',             // AI recommendation
    'export_label' => 'WP Version',          // Compact label for text export
)
```

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
