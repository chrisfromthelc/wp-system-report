# WP System Report

A comprehensive WordPress system status report plugin with AI-optimized export. Standalone, no WooCommerce required.

## Features

- **Full System Diagnostics** - 17 collectors covering WordPress environment, server, database, plugins, themes, security, cron, REST API, and more
- **Multiple Export Formats** - Plain text, GitHub-friendly (with redactions and `<details>` wrapper), and AI-optimized markdown
- **AI-Ready Export** - Structured markdown with contextual descriptions, status indicators, recommendations, and proactive issue detection designed for Claude, ChatGPT, and other LLMs
- **Error Log Viewer** - View, copy, and download the PHP error log directly from the admin, with configurable line count (up to 10,000) and optional system report inclusion
- **Debug Toggle** - Enable/disable `WP_DEBUG`, `WP_DEBUG_LOG`, and `WP_DEBUG_DISPLAY` from the admin UI (with graceful read-only fallback)
- **REST API** - Full JSON API at `wp-system-report/v1/report` and `wp-system-report/v1/error-log` with format parameter support
- **Extensible** - Filter hooks for adding custom collectors, modifying fields, and extending issue detection
- **Zero Dependencies** - Works standalone without WooCommerce or any other plugin (uses `wp-cli/wp-config-transformer` for wp-config.php editing)
- **MCP Integration with Agent Guidance** - Registers 7 abilities with the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) via the Abilities API (WP 6.9+). AI agents can query site health, read error logs, and toggle debug — with built-in environment-aware guidance that ensures safe, context-appropriate recommendations compiled from official WordPress, PHP, WooCommerce, and security research sources
- **Cached** - Transient caching for expensive collectors with automatic invalidation
- **Auto-Updates** - Checks GitHub Releases for new versions and serves updates through the WordPress dashboard

## Requirements

- WordPress 6.2+ (tested up to 6.9.4)
- PHP 7.4+

## Installation

### Manual Installation

1. Download or clone this repository into `wp-content/plugins/wp-system-report/`
2. Activate the plugin through the WordPress admin
3. Navigate to **Tools > WP System Report**

[Click to download the latest release](https://github.com/chrisfromthelc/wp-system-report/releases/latest/download/wp-system-report.zip).

### From GitHub

```bash
cd wp-content/plugins/
git clone https://github.com/chrisfromthelc/wp-system-report.git wp-system-report
```

## Usage

### Admin Page

Navigate to **Tools > WP System Report** to view the full system status report. The admin page has two tabs:

#### System Report Tab

| Button | Description |
|--------|-------------|
| **Get system report** | Generates a plain text report from the displayed data |
| **Copy for support** | Copies the report to clipboard |
| **Download for support** | Downloads a `.txt` file |
| **Copy for GitHub** | Copies a redacted report wrapped in `<details>` tags |
| **Download for AI analysis** | Downloads an AI-optimized `.md` file via the REST API |

#### Error Log Tab

- **Debug Configuration** — Toggle `WP_DEBUG`, `WP_DEBUG_LOG`, and `WP_DEBUG_DISPLAY` with a single click (modifies `wp-config.php` via `WPConfigTransformer`). When `wp-config.php` is not writable or `DISALLOW_FILE_MODS` is set, displays read-only status badges with copy-pasteable code snippets and WP-CLI commands.
- **Error Log Viewer** — Load the last N lines (1–10,000, default 100) of the PHP error log. Supports copy to clipboard and download, with an "Include system report" checkbox to prepend the full system report for context when sharing with developers.

### REST API

The plugin registers REST endpoints under `wp-system-report/v1/` (requires `manage_options` capability).

**Report Formats:**

```
GET /wp-json/wp-system-report/v1/report              # JSON (default)
GET /wp-json/wp-system-report/v1/report?format=plain  # Plain text
GET /wp-json/wp-system-report/v1/report?format=github # GitHub (redacted + details wrapper)
GET /wp-json/wp-system-report/v1/report?format=ai     # AI-optimized markdown
```

**Error Log Endpoints:**

```
GET  /wp-json/wp-system-report/v1/error-log                # JSON log lines (params: lines, format)
GET  /wp-json/wp-system-report/v1/error-log?format=raw     # Raw text output
GET  /wp-json/wp-system-report/v1/error-log/status         # Debug constants, file info, toggle state
POST /wp-json/wp-system-report/v1/error-log/toggle         # Enable/disable debug logging (body: {"enable": true})
```

**Example (cURL):**

```bash
curl -H "Authorization: Basic BASE64_CREDENTIALS" \
  "https://example.com/wp-json/wp-system-report/v1/report?format=ai"
```

### MCP Integration and AI Agent Guidance

When the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) is active on WordPress 6.9+, WP System Report registers seven abilities that AI agents (Claude, ChatGPT, Copilot, etc.) can invoke through the [Model Context Protocol](https://modelcontextprotocol.io/). This turns your system report into an interactive diagnostic tool — agents can query your site, understand the environment, and provide safe, context-aware recommendations.

#### Available Abilities

| Ability | Description | Input |
|---------|-------------|-------|
| `get-agent-context` | Environment-aware guidance, rules, and thresholds | _(none)_ |
| `get-issues` | Detected warnings and critical issues | _(none)_ |
| `get-report` | Full system report in markdown or JSON | `format`: `"markdown"` / `"json"` |
| `get-section` | Single report section by collector ID | `section`: e.g. `"database"`, `"security"` |
| `get-error-log` | Last N lines of the PHP error log | `lines`: 1-10000 (default 100) |
| `get-debug-status` | Current WP_DEBUG/WP_DEBUG_LOG state | _(none)_ |
| `toggle-debug` | Enable/disable debug logging | `enable`: `true` / `false` |

All abilities require `manage_options` capability. Every response includes an `_environment` object with the detected environment type, hosting provider, and site context.

#### Intelligent Agent Guidance

The standout feature is **`get-agent-context`** — a dedicated ability that provides AI agents with structured, environment-aware guidance so they make safe, accurate recommendations instead of hallucinating or giving generic advice.

**What it provides:**

- **Environment detection** — Automatically identifies local, development, staging, and production environments via `WP_ENVIRONMENT_TYPE`, hostname inference (`.local`, `.test`, `localhost`), and managed hosting detection (WP Engine, Kinsta, Pantheon, Flywheel, GoDaddy, Pressable)
- **Safety rules** — A "never recommend" list that prevents dangerous suggestions like `chmod 777`, disabling SSL, editing core files, or running destructive database queries without confirmation
- **Environment-specific severity** — The same issue carries different weight depending on context. No HTTPS on `localhost`? Informational. No HTTPS on production? Critical. `WP_DEBUG` enabled locally? Expected. On production? Critical. The agent knows the difference
- **Validated thresholds** — Specific numeric thresholds for PHP memory, autoloaded options, execution time, cron events, and database size — so agents assess values against real benchmarks, not guesses
- **PHP version lifecycle** — Complete EOL dates and support status for PHP 7.4 through 8.5, with classifications (critical/warning/info/ok) agents can use directly
- **WooCommerce-aware** — When WooCommerce is detected, includes HPOS migration status, Action Scheduler health thresholds, session table limits, `max_input_vars` requirements, cart fragment optimization guidance, and caching exclusion rules

**Why this matters:**

Without guidance, an AI agent analyzing a system report might tell you "CRITICAL: Your site is not using HTTPS!" on a Local dev environment, or recommend installing a caching plugin on WP Engine (which provides built-in caching and bans certain caching plugins). The agent guidance prevents these mistakes by giving agents the same context an experienced WordPress developer would have.

#### Guidance Sources

The agent guidance data is compiled from official and trusted third-party sources:

- [WordPress.org requirements and hosting handbook](https://developer.wordpress.org/)
- [PHP.net release lifecycle and EOL data](https://www.php.net/supported-versions.php)
- [WooCommerce server requirements](https://woocommerce.com/document/server-requirements/)
- [Patchstack vulnerability intelligence](https://patchstack.com/) (WordPress ecosystem vulnerability statistics)
- [Wordfence security research](https://www.wordfence.com/) (plugin security analysis, WAF guidance)
- WordPress core source code (Site Health thresholds, cron timing, autoloaded options limits)
- Managed hosting provider documentation ([WP Engine](https://wpengine.com/support/), [Kinsta](https://kinsta.com/docs/), [Pantheon](https://docs.pantheon.io/), [WordPress VIP](https://docs.wpvip.com/))
- [OWASP security headers](https://owasp.org/www-project-secure-headers/) and [MDN web security](https://developer.mozilla.org/en-US/docs/Web/Security) references

#### Example Agent Workflow

```
Agent: "What issues does this WordPress site have, and how should I fix them?"

1. get-agent-context
   → Learns this is a "local" environment on Flywheel
   → Loads safety rules, thresholds, and severity overrides
   → Sees WooCommerce is not active

2. get-issues
   → Finds "HTTPS not enabled" flagged as critical
   → Checks _environment: is_local = true
   → Knows from environment_overrides: HTTPS on local = informational, not critical

3. get-section (section: "active_plugins")
   → Sees 8 active plugins, all up to date
   → Compares against thresholds: well within normal range

4. get-error-log
   → Finds 3 PHP deprecation notices
   → Groups by pattern, identifies the responsible plugin

5. Agent responds:
   "Your local dev site looks healthy. No critical issues — the HTTPS
   notice is expected for local development. I found 3 PHP deprecation
   notices from Plugin X that you may want to report to the developer.
   All plugins are up to date."
```

Without agent guidance, the same agent might have said: *"CRITICAL: Your site is insecure! Enable HTTPS immediately! You should also install a WAF plugin and change your file permissions!"* — all wrong for a local dev site.

If the MCP Adapter is not installed or WordPress < 6.9, the integration is a no-op.

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
| `wp_system_report_error_log_capability` | Change required capability for error log access (default: `manage_options`) |
| `wp_system_report_allowed_log_paths` | Add additional directories allowed for log file reading |
| `wp_system_report_redact_log_line` | Redact sensitive content from each log line before display |

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
  wp-system-report.php              # Bootstrap + autoloader
  uninstall.php                     # Cleanup on deletion
  includes/
    class-plugin.php                # Singleton orchestrator
    class-admin-page.php            # Admin menu + tabbed rendering
    class-rest-controller.php       # Report REST API controller
    class-error-log-controller.php  # Error log REST API controller
    class-report-generator.php      # Collector registry
    class-settings.php              # Plugin settings (error_log_lines)
    class-error-log-reader.php      # Error log file reader
    class-debug-toggle.php          # wp-config.php debug constant toggle
    class-issue-detector.php        # Reusable issue detection logic
    class-agent-guidance.php        # Environment-aware guidance for AI agents
    class-abilities-provider.php    # MCP Abilities API integration (7 abilities)
    class-github-updater.php        # GitHub release update checker
    collectors/
      interface-collector.php       # Collector contract
      class-abstract-collector.php  # Shared helpers + caching
      class-*.php                   # 17 concrete collectors
    formatters/
      interface-formatter.php       # Formatter contract
      class-plain-text-formatter.php
      class-github-formatter.php
      class-ai-formatter.php
  assets/
    css/wp-system-report-admin.css     # Admin styles
    js/wp-system-report-admin.js       # Report tab JS (vanilla, no jQuery)
    js/wp-system-report-error-log.js   # Error log tab JS (vanilla, no jQuery)
  templates/
    admin-page.php                  # Admin page template (tabbed)
    error-log-tab.php               # Error log tab template
  tests/                            # PHPUnit tests
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

MIT License See [LICENSE](https://opensource.org/license/mit).
