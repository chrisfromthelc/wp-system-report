# Architecture

## Overview

WP System Report is built around a collector/formatter pipeline with an expanding set of subsystems for fixers, report history, health scoring, notifications, and AI/MCP integration. Collectors gather diagnostic data, the report generator orchestrates collection, formatters transform the data for different output targets, and fixers apply automated remediations.

The plugin is gated by a `Features` class that controls Pro-tier capabilities. All features currently return `true` (the Pro split is not yet active), but the structure is in place to introduce license-key validation in a single location.

## Directory Structure

```
wp-system-report/
  wp-system-report.php                    # Bootstrap, constants, autoloader, activation hook
  uninstall.php                           # Cleanup on plugin deletion
  composer.json                           # Dependencies and dev tooling
  includes/
    class-plugin.php                      # Singleton orchestrator (hooks, initialization)
    class-admin-page.php                  # Admin menu registration and page rendering
    class-report-generator.php            # Collector registry and report generation
    class-rest-controller.php             # Report REST API (wp-system-report/v1/report)
    class-rest-envelope.php               # Standardised JSON response envelope
    class-error-log-controller.php        # Error log REST API (wp-system-report/v1/error-log)
    class-error-log-reader.php            # Safe error log file reader with redaction
    class-sse-log-streamer.php            # Server-Sent Events loop for live log tailing
    class-sse-log-controller.php          # SSE stream REST endpoint (error-log/stream)
    class-debug-toggle.php                # wp-config.php debug constant management
    class-settings.php                    # Plugin settings (wp_system_report_settings option)
    class-github-updater.php              # GitHub Releases update checker
    class-features.php                    # Feature flags / Pro-tier gate
    class-field.php                       # Field value object (ArrayAccess + JsonSerializable)
    enum-status.php                       # Status enum: Good, Warning, Critical, Info
    interface-fixer.php                   # Fixer contract
    enum-risk-level.php                   # Risk_Level enum: Low, Medium, High
    class-fix-result.php                  # Immutable fix outcome value object
    class-fixer-registry.php              # Central registry for Fixer instances
    class-fixer-controller.php            # Fixer REST API (fixes, fixes/{fix_id})
    class-health-score.php                # Weighted health score calculator (0-100 + grade)
    class-health-score-controller.php     # Health score REST API (wp-system-report/v1/health-score)
    class-report-history.php              # Snapshot storage in custom DB table
    class-report-history-controller.php   # Report history REST API (history, history/{id})
    class-report-diff.php                 # Diff engine: compares two report snapshots
    class-report-diff-controller.php      # Report diff REST API (diff)
    class-notification-manager.php        # Alert evaluation and dispatch coordination
    class-notification-controller.php     # Notification settings REST API (notifications/*)
    class-webhook-dispatcher.php          # HMAC-signed JSON webhook delivery
    class-ai-context-generator.php        # Generates AI context file after report generation
    class-abilities-provider.php          # WordPress Abilities API integration (WP 6.9+)
    class-cli-command.php                 # WP-CLI commands (system-report *)
    collectors/
      interface-collector.php             # Collector contract
      class-abstract-collector.php        # Base class with caching and helpers
      class-wordpress-environment.php     # Priority 10  — WordPress version, locale, etc.
      class-server-environment.php        # Priority 20  — PHP, OS, memory limits
      class-database.php                  # Priority 30  — DB version, size, table health
      class-post-type-counts.php          # Priority 40  — Post counts by type/status
      class-security.php                  # Priority 50  — File editor, SSL, debug mode
      class-active-plugins.php            # Priority 60  — Active plugin list + metadata
      class-inactive-plugins.php          # Priority 70  — Inactive plugin list
      class-dropins-mu-plugins.php        # Priority 80  — Drop-ins and MU plugins
      class-theme-info.php                # Priority 90  — Active/parent theme info
      class-wordpress-constants.php       # Priority 100 — WP_DEBUG, WP_CACHE, etc.
      class-filesystem-permissions.php    # Priority 110 — Key file/dir permission checks
      class-site-health.php               # Priority 120 — Core WP_Site_Health integration
      class-cron-health.php               # Priority 130 — Cron schedule and lock status
      class-rest-api-info.php             # Priority 140 — REST API availability
      class-custom-content-types.php      # Priority 150 — Custom post types and taxonomies
      class-wordpress-configuration.php   # Priority 160 — wp-config settings audit
      class-advanced-diagnostics.php      # Priority 170 — Opcache, object cache, misc
      class-email-delivery.php            # Priority 180 — wp_mail delivery verification
      class-media-uploads.php             # Priority 190 — Upload directory and limits
      class-performance.php               # Priority 200 — Query counts, load time, memory
      class-update-health.php             # Priority 210 — Plugin/theme/core update status
      class-network-connectivity.php      # Priority 220 — External HTTP reachability checks
      class-block-editor.php              # Priority 230 — Block editor and Gutenberg state
    formatters/
      interface-formatter.php             # Formatter contract
      class-plain-text-formatter.php      # Plain text output
      class-github-formatter.php          # GitHub Markdown (redacts sensitive data)
      class-ai-formatter.php              # AI-optimised markdown with context/recommendations
      class-mcp-formatter.php             # MCP structured JSON for AI tool consumption
    fixers/
      class-autoload-optimizer.php        # Disables autoload on oversized wp_options rows
      class-database-optimizer.php        # Clears expired transients, runs OPTIMIZE TABLE
      class-security-hardener.php         # Disables XML-RPC/file editor, adds security headers
      class-cron-repair.php               # Removes orphaned events, clears stale cron lock
  assets/
    css/
      wp-system-report-admin.css          # Admin page styles
    js/
      wp-system-report-admin.js           # Report tab: Interactivity API store + UI
      wp-system-report-error-log.js       # Error log tab JavaScript
      wp-system-report-fixes.js           # Fixes tab JavaScript
      modules/
        store-report.js                   # @wordpress/interactivity report store module
        store-fixes.js                    # @wordpress/interactivity fixes store module
        store-error-log.js                # @wordpress/interactivity error log store module
        store-utils.js                    # Shared store utility functions
  templates/
    admin-page.php                        # Tabbed admin page template
    error-log-tab.php                     # Error log tab template
    fixes-tab.php                         # Fixes tab template
    mcp-adapter-notice.php                # Admin notice recommending MCP Adapter plugin
  tests/
    bootstrap.php                         # PHPUnit bootstrap
    SampleTest.php                        # Smoke test
    CollectorsTest.php                    # Collector interface conformance tests
    FormattersTest.php                    # Formatter output tests
    ReportGeneratorTest.php               # Generator registry and sorting tests
    RESTControllerTest.php                # REST endpoint authentication/format tests
    HealthScoreTest.php                   # Health score calculation tests
    FixersTest.php                        # Fixer unit tests
    FixerControllerTest.php               # Fixer REST controller tests
    ReportHistoryTest.php                 # History snapshot CRUD tests
    ReportDiffTest.php                    # Diff engine tests
    SSELogStreamerTest.php                 # SSE log streamer tests
    ErrorLogControllerTest.php            # Error log REST tests
    ErrorLogReaderTest.php                # Log reader redaction tests
    DebugToggleTest.php                   # Debug constant toggling tests
    GitHubUpdaterTest.php                 # Update checker tests
    SettingsTest.php                      # Settings option tests
    collectors/
      ActivePluginsTest.php
      AdvancedDiagnosticsTest.php
      BlockEditorTest.php
      CronHealthTest.php
      CustomContentTypesTest.php
      DatabaseTest.php
      DropinsMuPluginsTest.php
      EmailDeliveryTest.php
      FilesystemPermissionsTest.php
      InactivePluginsTest.php
      MediaUploadsTest.php
      NetworkConnectivityTest.php
      PerformanceTest.php
      PostTypeCountsTest.php
      RestApiInfoTest.php
      SecurityTest.php
      ServerEnvironmentTest.php
      SiteHealthTest.php
      ThemeInfoTest.php
      UpdateHealthTest.php
      WordPressConfigurationTest.php
      WordPressConstantsTest.php
      WordPressEnvironmentTest.php
```

## Data Flow

```
1. User navigates to Tools > WP System Report
   └── Admin_Page renders the tabbed template (Report, Error Log, Fixes tabs)

2. Report tab loads
   └── JavaScript (Interactivity API store-report.js) calls:
       GET /wp-system-report/v1/report?format=json

3. REST_Controller receives the request
   ├── Verifies nonce / manage_options capability
   └── Calls Report_Generator::generate()

4. Report_Generator iterates all registered collectors (sorted by priority)
   ├── Collector 1  (priority 10):  WordPress_Environment::collect()
   ├── Collector 2  (priority 20):  Server_Environment::collect()
   ├── ...cached collectors call Abstract_Collector::get_cached_data()...
   └── Collector 23 (priority 230): Block_Editor::collect()

5. Each collector's fields are passed through wp_system_report_fields_{id} filter

6. Report_Generator fires do_action('wp_system_report_generated', $report)
   ├── Notification_Manager::evaluate_and_notify()  (priority 20)
   │   └── Evaluates warning/critical thresholds
   │       ├── Sends per-recipient emails via wp_mail()
   │       └── Dispatches HMAC-signed JSON webhooks via Webhook_Dispatcher
   └── Report_History::maybe_save_snapshot()  (if feature enabled)
       └── Compresses and stores report in wp_sr_report_history table
           with health score and grade pre-computed

7. REST_Controller returns the report
   ├── format=json  → REST_Envelope::success($report)
   ├── format=mcp   → REST_Envelope::success(MCP_Formatter::format_array($report))
   └── format=plain|github|ai → raw text via rest_pre_serve_request filter

8. Health Score (on-demand or after history save)
   ├── GET /wp-system-report/v1/health-score
   └── Health_Score::calculate($report)
       ├── Scores each field: Good=100, Warning=40, Critical=0, Info=excluded
       ├── Applies per-collector weights (security=3.0, updates=2.5, etc.)
       └── Returns score (0-100), letter grade (A+/A/B/C/D/F), and breakdown

9. Fixer execution lifecycle (Fixes tab)
   ├── GET  /wp-system-report/v1/fixes       → list available fixers + can_fix() state
   └── POST /wp-system-report/v1/fixes/{id}  → execute a specific fixer
       ├── confirmed=false + Medium/High risk → HTTP 409 (confirmation guard)
       ├── confirmed=true  → Fixer::fix()
       └── Returns Fix_Result (success, message, before/after snapshots)

10. Report diff (History tab)
    └── GET /wp-system-report/v1/diff?before={id}&after={id}
        └── Report_Diff::compare() classifies field changes as
            improved, degraded, added, removed, or changed
```

## Class Relationships

### Plugin (Singleton Orchestrator)

The `Plugin` class is the single entry point, constructed via `wp_system_report()`. It:
- Instantiates all subsystem objects (generator, registry, controllers, managers)
- Registers all 23 default collectors and 4 default fixers
- Hooks all REST controllers onto `rest_api_init`
- Conditionally activates Health_Score_Controller, Report_History, and Abilities_Provider based on feature flags
- Exposes `get_report_generator()`, `get_fixer_registry()`, `get_health_score()`, `get_report_history()`, `get_report_diff()`

### Report Generator (Registry)

`Report_Generator` manages the collector registry:
- `register_collector()` adds a collector (keyed by ID)
- `get_collectors()` returns all collectors sorted by priority, after applying the `wp_system_report_collectors` filter
- `generate()` runs all collectors, applies per-section filters, fires `wp_system_report_generated`
- `generate_section()` runs a single collector by ID

### Collectors (Strategy Pattern)

All collectors implement the `Collector` interface:
- `get_id()` — Unique identifier used as array key and filter suffix
- `get_label()` — Human-readable section name
- `get_description()` — Section description for AI export context
- `get_priority()` — Sort order (lower = earlier in report)
- `collect()` — Returns an array of `Field` objects

`Abstract_Collector` adds:
- `get_cached_data()` — Checks transient before calling `collect()`; TTL is filterable via `wp_system_report_cache_ttl`
- `make_field()` — Convenience builder for `Field` objects
- `get_cache_key()` — Override to define the transient key (defaults to no caching)

### Field Value Object

`Field` (`class-field.php`) represents a single diagnostic data point:
- Public properties: `$label`, `$value`, `$debug`, `$status` (Status enum), `$description`, `$recommended`, `$export_label`, `$private`, `$fix_id`
- Implements `ArrayAccess` for backward compatibility with legacy array-access code
- Implements `JsonSerializable` for clean REST output
- `to_array()` serialises `$status` to its string value for JSON output
- `get_status_string( $field )` is a static helper used by `Health_Score` and `CLI_Command`

### Status Enum

`Status` (`enum-status.php`) is a backed string enum replacing legacy status strings:
- Cases: `Good = 'good'`, `Warning = 'warning'`, `Critical = 'critical'`, `Info = 'info'`
- `from_legacy( $value )` — Falls back to `Info` for unrecognised strings
- `is_actionable()` — Returns `true` for Warning and Critical

### Formatters (Strategy Pattern)

All formatters implement the `Formatter` interface:
- `format( $report_data )` — Transform report data to string
- `get_content_type()` — MIME type for the response
- `get_file_extension()` — File extension hint for downloads

Available formatters:

| Class | Format key | Output |
|---|---|---|
| `Plain_Text_Formatter` | `plain` | Human-readable plain text |
| `GitHub_Formatter` | `github` | GitHub-flavoured Markdown with sensitive data redacted |
| `AI_Formatter` | `ai` | Structured Markdown with AI context and recommendations |
| `MCP_Formatter` | `mcp` | Structured JSON for Model Context Protocol consumption |

The `MCP_Formatter` exposes a `format_array()` method used by `REST_Controller` to return structured data inside the standard envelope rather than a raw string.

### REST Envelope

`REST_Envelope` wraps all JSON responses in a consistent structure:

```json
{
    "status": "success",
    "data":   { ... },
    "meta":   { "generated_at": "...", "plugin_version": "..." }
}
```

All controllers call `REST_Envelope::success( $data, $extra_meta )` rather than returning raw `WP_REST_Response` objects. This guarantees a uniform shape across all endpoints.

### REST Controllers

All controllers extend `WP_REST_Controller` and register under the `wp-system-report/v1` namespace.

| Class | Route base | Methods | Feature gate |
|---|---|---|---|
| `REST_Controller` | `report` | GET | Always active |
| `Error_Log_Controller` | `error-log` | GET, DELETE | Always active |
| `SSE_Log_Controller` | `error-log/stream` | GET (SSE) | Always active |
| `Fixer_Controller` | `fixes`, `fixes/{fix_id}` | GET, POST | `Features::has_fixers()` |
| `Health_Score_Controller` | `health-score` | GET | `Features::has_health_score()` |
| `Report_History_Controller` | `history`, `history/{id}` | GET, POST, DELETE | `Features::has_report_history()` |
| `Report_Diff_Controller` | `diff` | GET | Always active |
| `Notification_Controller` | `notifications/settings`, `notifications/test` | GET, POST | Always active |

### Fixer System

Fixers address specific issues identified by collectors. The system consists of:

**`Fixer` interface** (`interface-fixer.php`):
- `get_id()` — Unique slug (e.g. `'autoload_optimizer'`)
- `get_label()` — Translated display name
- `get_description()` — Problem and action description
- `get_risk_level()` — Returns a `Risk_Level` enum case
- `get_category()` — Category slug (`'performance'`, `'security'`, `'database'`)
- `can_fix()` — Lightweight pre-check; returns `false` if already resolved
- `fix()` — Executes the remediation and returns a `Fix_Result`

Note: there is no `Abstract_Fixer` base class — all four concrete fixers implement `Fixer` directly.

**`Risk_Level` enum** (`enum-risk-level.php`): `Low`, `Medium`, `High`
- `requires_confirmation()` — Returns `true` for Medium and High
- `get_label()` — Translated label

**`Fix_Result`** (`class-fix-result.php`): Immutable value object
- `Fix_Result::success( $message, $before, $after )`
- `Fix_Result::failure( $message, $errors )`
- Implements `JsonSerializable`

**`Fixer_Registry`** (`class-fixer-registry.php`):
- `register( $fixer )` — Adds a fixer, warns on duplicate IDs
- `get( $id )` / `get_all()` / `has( $id )` / `get_by_category( $category )`
- `get_all()` applies the `wp_system_report_fixers` filter for extensibility

**Concrete fixers** (`includes/fixers/`):

| Class | ID | Category | Risk |
|---|---|---|---|
| `Autoload_Optimizer` | `autoload_optimizer` | performance | Low |
| `Database_Optimizer` | `database_optimizer` | database | Medium |
| `Security_Hardener` | `security_hardener` | security | Medium |
| `Cron_Repair` | `cron_repair` | performance | Low |

**Confirmation guard** in `Fixer_Controller::execute_fix()`: Medium and High risk fixers require the caller to pass `confirmed=true`. Without it, the endpoint returns HTTP 409 with a descriptive error. The admin UI enforces the same guard via a JavaScript confirmation dialog before dispatching the POST request.

### Health Score

`Health_Score` computes an aggregate 0–100 score from the full report:
- Each non-info field is scored: Good=100, Warning=40, Critical=0
- Scores are averaged per collector section, then combined as a weighted average
- Section weights are defined as constants (e.g. `security=3.0`, `update_health=2.5`, `performance=2.5`) and filterable via `wp_system_report_health_score_weights`
- The final integer score is clamped to 0–100 and filterable via `wp_system_report_health_score`
- `score_to_grade( $score )` converts to letter grades: A+ (≥95), A (≥80), B (≥65), C (≥50), D (≥35), F (<35)

### Report History

`Report_History` stores compressed snapshots in a custom table (`{prefix}sr_report_history`):
- Created on plugin activation via `Report_History::create_table()`
- Schema version tracking via `sr_report_history_schema_version` option; checked on `admin_init` for upgrades without reactivation
- Stores each snapshot as gzip-compressed JSON with pre-computed health score and grade
- Default retention limit: 90 snapshots (filterable)
- `register_hooks()` hooks `maybe_save_snapshot()` onto `wp_system_report_generated` to auto-save after every report generation

`Report_History_Controller` exposes:
- `GET  /history` — List snapshots (supports `per_page`, `page`, `order` params)
- `POST /history` — Save a new snapshot on demand
- `GET  /history/{id}` — Retrieve a specific snapshot
- `DELETE /history/{id}` — Delete a specific snapshot

### Report Diff

`Report_Diff` compares two report snapshots:
- `compare( $before, $after, $before_label, $after_label )` returns sections with per-field change classifications: `improved`, `degraded`, `added`, `removed`, `changed`, `unchanged`
- Status transitions use a numeric rank (`critical=0`, `warning=1`, `info=2`, `good=3`) to determine direction

`Report_Diff_Controller` provides:
- `GET /diff?before={id}&after={id}` — Compare two history snapshots
- `GET /diff?before={id}` — Compare a history snapshot to the current live report

### Notification System

`Notification_Manager` evaluates alert thresholds after each report:
- Hooked onto `wp_system_report_generated` at priority 20
- Respects a cooldown transient (`sr_notification_cooldown`, default 1 hour) to prevent alert fatigue
- Dispatches per-recipient email notifications via `wp_mail()` for configured addresses
- Delegates webhook delivery to `Webhook_Dispatcher`

`Webhook_Dispatcher`:
- Reads webhook URLs from `Settings::get('webhook_urls')` (comma- or newline-separated)
- Restricts delivery to HTTPS URLs (localhost HTTP allowed for development)
- Signs each payload with HMAC-SHA256 using a stored secret (auto-generated on first use)
- Sends blocking HTTP POST requests with `X-WP-System-Report-Signature` and `X-WP-System-Report-Event` headers
- Returns per-URL success/failure results (useful for the test endpoint)
- Fires `wp_system_report_webhooks_dispatched` action after dispatch

### Abilities Provider (MCP Integration)

`Abilities_Provider` registers plugin capabilities with the WordPress Abilities API (WordPress 6.9+):
- Activated only when `Features::has_abilities()` is true (Pro gate + API availability check)
- Hooks onto `wp_abilities_api_categories_init` and `wp_abilities_api_init`
- Registers a `wp-system-report` category and individual abilities for report generation, error log access, and fixer execution
- Consumed by the MCP Adapter plugin to expose these capabilities to AI agents

### WP-CLI Command

`CLI_Command` extends `WP_CLI_Command` and is registered as `wp system-report`:
- `generate` — Full report; supports `--format=table|json|plain|github|ai|mcp` and `--section=<id>`
- `export` — Export report to a file
- `cron-check` — Run the cron health collector
- `fix <fixer-id>` — Execute a specific fixer
- `health` — Display the health score and grade

### Features (Feature Flags)

`Features` is a static class with no instance state:
- `is_pro()` — Currently returns `true` unconditionally; filterable via `wp_system_report_is_pro`
- `has_fixers()` — Fixer system availability
- `has_mcp()` — MCP formatter availability
- `has_health_score()` — Health score availability
- `has_report_history()` — Report history availability
- `has_interactivity()` — Interactivity API UI (requires WP 6.5+)
- `has_abilities()` — Abilities API integration (requires WP 6.9+)

All guarded subsystems are conditionally instantiated in `Plugin::__construct()` based on these checks.

### SSE Log Streaming

`SSE_Log_Streamer` implements a long-polling Server-Sent Events loop:
- Reads new log lines since a `last_event_id` cursor
- Writes `data:` frames directly to PHP output
- `SSE_Log_Controller` registers the `GET /error-log/stream` route and takes over response delivery via the `rest_pre_serve_request` filter, bypassing normal REST serialisation
- PHP execution time limit is removed during the stream and restored on shutdown

## Autoloader

The custom autoloader in `wp-system-report.php` maps the `SystemReport\` namespace to `includes/` using WordPress file naming conventions. It checks for interface, enum, and class file prefixes in that order.

| Fully Qualified Name | File Path |
|---|---|
| `SystemReport\Plugin` | `includes/class-plugin.php` |
| `SystemReport\Features` | `includes/class-features.php` |
| `SystemReport\Field` | `includes/class-field.php` |
| `SystemReport\Status` | `includes/enum-status.php` |
| `SystemReport\Risk_Level` | `includes/enum-risk-level.php` |
| `SystemReport\Fixer` | `includes/interface-fixer.php` |
| `SystemReport\Fix_Result` | `includes/class-fix-result.php` |
| `SystemReport\Fixer_Registry` | `includes/class-fixer-registry.php` |
| `SystemReport\Health_Score` | `includes/class-health-score.php` |
| `SystemReport\Report_History` | `includes/class-report-history.php` |
| `SystemReport\Report_Diff` | `includes/class-report-diff.php` |
| `SystemReport\Abilities_Provider` | `includes/class-abilities-provider.php` |
| `SystemReport\CLI_Command` | `includes/class-cli-command.php` |
| `SystemReport\Collectors\Active_Plugins` | `includes/collectors/class-active-plugins.php` |
| `SystemReport\Collectors\Collector` | `includes/collectors/interface-collector.php` |
| `SystemReport\Formatters\AI_Formatter` | `includes/formatters/class-ai-formatter.php` |
| `SystemReport\Formatters\MCP_Formatter` | `includes/formatters/class-mcp-formatter.php` |
| `SystemReport\Fixers\Autoload_Optimizer` | `includes/fixers/class-autoload-optimizer.php` |
| `SystemReport\Fixers\Database_Optimizer` | `includes/fixers/class-database-optimizer.php` |
| `SystemReport\Fixers\Security_Hardener` | `includes/fixers/class-security-hardener.php` |
| `SystemReport\Fixers\Cron_Repair` | `includes/fixers/class-cron-repair.php` |

The Composer autoloader (`vendor/autoload.php`) is loaded separately for third-party dependencies (e.g. `wp-cli/wp-config-transformer`).

## Caching Strategy

All collector caching uses WordPress transients. The default TTL is 1 hour (`HOUR_IN_SECONDS`) and is filterable via the `wp_system_report_cache_ttl` filter (receives the TTL and cache key as arguments).

Collectors that do not override `get_cache_key()` are not cached.

| Transient Key | Collector | Default TTL |
|---|---|---|
| `sr_wordpress_environment` | WordPress_Environment | 1 hour |
| `sr_active_plugins` | Active_Plugins | 1 hour |
| `sr_inactive_plugins` | Inactive_Plugins | 1 hour |
| `sr_dropins_mu_plugins` | Dropins_MU_Plugins | 1 hour |
| `sr_theme_info` | Theme_Info | 1 hour |
| `sr_site_health` | Site_Health | 1 hour |
| `sr_database` | Database | 1 hour |
| `sr_post_type_counts` | Post_Type_Counts | 1 hour |
| `sr_advanced_diagnostics` | Advanced_Diagnostics | 1 hour |
| `sr_email_delivery` | Email_Delivery | 1 hour |
| `sr_media_uploads` | Media_Uploads | 1 hour |
| `sr_performance` | Performance | 1 hour |
| `sr_update_health` | Update_Health | 1 hour |
| `sr_network_connectivity` | Network_Connectivity | 1 hour |
| `sr_block_editor` | Block_Editor | 1 hour |
| `sr_github_update` | GitHub_Updater | 12 hours |
| `sr_github_update_failed` | GitHub_Updater (failure) | 30 minutes |
| `sr_notification_cooldown` | Notification_Manager | 1 hour (configurable) |

Cache invalidation is triggered by:
- Theme switch (`switch_theme`) — clears `sr_theme_info`, `sr_site_health`
- Plugin activation/deactivation (`activate_plugin`, `deactivate_plugin`) — clears `sr_active_plugins`, `sr_inactive_plugins`, `sr_dropins_mu_plugins`, `sr_site_health`
- Plugin/theme upgrades (`upgrader_process_complete`) — clears plugin or theme caches as appropriate

## Security Model

### Endpoint Authentication
- All REST endpoints require `manage_options` capability by default
- The capability for the main report and fixer endpoints is filterable via `wp_system_report_capability`
- The error log capability filter (`wp_system_report_error_log_capability`) is restricted to a hard-coded allowlist of admin-level capabilities to prevent privilege escalation

### Fixer Confirmation Guard
- Medium and High risk fixers require `confirmed=true` in the POST body
- Without it, `Fixer_Controller::execute_fix()` returns HTTP 409 before touching any system state
- `Risk_Level::requires_confirmation()` is the single authority for this check

### Notification Privacy
- Email notifications are dispatched per-recipient via individual `wp_mail()` calls (no bulk BCC)
- Webhook URLs are validated as HTTPS-only (localhost HTTP is permitted for development)

### Webhook HMAC Signing
- Every webhook payload is signed with HMAC-SHA256 using a stored secret
- The signature is delivered in the `X-WP-System-Report-Signature` header
- The secret is auto-generated via `wp_generate_password(40)` on first use if not configured

### SSE Stream Authentication
- The `GET /error-log/stream` endpoint uses the same capability check as the error log endpoints
- The `rest_pre_serve_request` filter takeover occurs only after the permission callback passes

### Error Log Safety
- `Error_Log_Reader` validates that the log file path is within allowed directories before reading
- Sensitive patterns (passwords, keys) are redacted from log output

### GitHub Formatter
- The `GitHub_Formatter` redacts URLs, database table prefixes, and other potentially sensitive values before producing Markdown output

### Uninstall Cleanup
- `uninstall.php` removes all plugin options, transients, and the custom history database table on deletion
