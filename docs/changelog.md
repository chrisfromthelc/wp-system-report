# Changelog

All notable changes to WP System Report will be documented in this file.

This format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

### Added

**Phase 5: Modern UI & Advanced Features**

- Interactivity API reactive admin UI (Phase 5.1): migrated admin page from vanilla JS to
  WordPress Interactivity API (`@wordpress/interactivity` stores) with `data-wp-*` directives
  for the report, error-log, and fixes tabs; vanilla JS fallback retained for WordPress < 6.5;
  `has_interactivity()` feature flag added to `Features` class
- SSE live log streaming (Phase 5.2): real-time error log tailing via Server-Sent Events at
  `GET /wp-system-report/v1/error-log/stream`; detects file rotation and truncation, sends
  periodic heartbeats, enforces configurable maximum duration, reuses existing redaction
  pipeline; new `SSE_Log_Streamer` and `SSE_Log_Controller` classes
- Report history and snapshot storage (Phase 5.3): compressed JSON snapshots stored in a
  custom `wp_system_report_history` database table; paginated REST endpoints for listing and
  retrieving snapshots; health score trend tracking; `Report_History` class with feature flag
- Report diffing engine (Phase 5.5): structured diff between two snapshots identifying
  added/removed sections, added/removed fields, value changes, and status transitions
  (improved/degraded/changed); `Report_Diff` core engine with status ranking system;
  `Report_Diff_Controller` REST endpoint (`POST /wp-system-report/v1/diff`) accepting
  snapshot IDs or `"current"` for live comparison
- Health score calculator (Phase 5.6): weighted 0-100 score with letter grade (A+ through F)
  computed from report data; category weighting gives security, performance, and update health
  higher influence; info-only fields excluded from scoring; `Health_Score` class with
  filterable weights; `Health_Score_Controller` REST endpoint (`GET /health-score`)
- WP-CLI command suite (Phase 5.4): `wp system-report generate`, `export`, `fix`, `fixes`,
  `cron-check`, `health`, and `collectors` commands; supports multiple output formats
  (table, json, plain, github, ai, mcp, csv, md, yaml); colorized output for health grades
  and status indicators; capability checks on all destructive commands
- Six new diagnostic collectors: Email Delivery, Media & Uploads, Performance, Update Health,
  Network & Connectivity, and Block Editor (Phases 2.1-2.6)

**Phase 4: MCP & AI Integration**

- Abilities Provider (Phase 4.1): registers five WordPress Abilities API abilities
  (`generate-report`, `get-collector-data`, `get-error-log`, `list-fixes`, `run-fix`) for
  WordPress 6.9+ AI agent discovery via the MCP Adapter plugin; feature-gated via
  `Features::has_abilities()`; dismissible admin notice when MCP Adapter is not installed
- MCP-optimized formatter (Phase 4.2): compact structured JSON formatter for AI/MCP
  responses; includes site identity, health score, prioritized issues list with `fix_id`
  references, and token-efficient section summaries; available as `?format=mcp` on the REST
  report endpoint
- AI context file generator (Phase 4.3): generates a static `.ai-context.md` file in the
  WordPress root for AI agents that need site context without REST endpoints; hooks into
  `wp_system_report_generated` for automatic refresh; excludes sensitive data; filterable
  via `wp_system_report_ai_context_path` hook; uses `WP_Filesystem` for safe file writes
- Webhooks and notifications system (Phase 4.4): `Notification_Manager` hooks into
  `wp_system_report_generated` to evaluate findings after each report generation; configurable
  thresholds for critical and warning counts; one-hour cooldown prevents alert fatigue;
  three delivery channels: JSON POST webhooks with HMAC-SHA256 signatures, plain-text email
  alerts, and Slack Block Kit messages via incoming webhook; REST endpoints for settings and
  test notification delivery; `Webhook_Dispatcher` with HTTPS-only enforcement
- Enhanced AI formatter with executive summary and severity scoring (Phase 2.7): health score
  (0-100) with rating label and top priorities; severity scoring (critical=10, warning=5);
  issue categorisation by domain (Security, Performance, Updates, Email, etc.); `fix_id`
  references for available fixers; `wp_system_report_ai_executive_summary` filter

**Phase 3: Fixers System**

- Fixer interface and registry (Phase 1.4): `Fixer` interface contract for all fixer
  implementations; `Fixer_Registry` with `wp_system_report_fixers` filter for third-party
  extensibility; `RiskLevel` enum (Low, Medium, High); `Fix_Result` immutable value object
  with before/after snapshots; `Features` class central feature gate with
  `wp_system_report_is_pro` filter
- Autoload Optimizer fixer (Phase 3.1): detects and removes autoloaded options exceeding
  configurable size thresholds with dry-run support
- Database Optimizer fixer (Phase 3.2): runs `OPTIMIZE TABLE` on fragmented tables; capped
  at 20 tables per run (`MAX_TABLES_PER_RUN`) to limit locking impact; locking behavior
  documented in class docblock
- Security Hardener fixer (Phase 3.3): applies runtime security hardening via `header()`;
  explicit allowlist of permitted header names; CR/LF rejection to prevent HTTP response
  splitting
- Cron Repair fixer (Phase 3.4): detects and reschedules missed or broken cron events
- REST API fixer endpoints (Phase 3.5): `GET /wp-system-report/v1/fixes` to list available
  fixers; `POST /wp-system-report/v1/fixes/{id}/run` to execute; server-side confirmation
  guard requiring `confirmed=true` for medium/high risk fixers (HTTP 409 if absent)
- Fixes admin tab (Phase 3.6): third admin tab gated behind `Features::has_fixers()`;
  fixer cards with risk badges, status indicators, and one-click execution; confirmation
  modal for medium/high risk fixers; before/after JSON snapshot display in collapsible
  results notices

**Phase 1 & 2: Quality Infrastructure**

- `Status` backed string enum (`Good`, `Warning`, `Critical`, `Info`) for PHP 8.1+; all
  collectors refactored to use typed enum values instead of raw strings (Phase 1.3)
- `Field` value object implementing `ArrayAccess` and `JsonSerializable` for backward
  compatibility; `get_status_string()` helper (Phase 1.3)
- PHP 8.1 minimum requirement; CI matrix updated to PHP 8.1-8.4 (Phase 1.1/1.2)
- `REST_Envelope` helper class wrapping all JSON responses in a consistent
  `{ status, data, meta }` structure; `meta` block includes `generated_at`,
  `plugin_version`, `collector_count`, and `fixes_available`; canonical keys protected
  from override (Phase 1.5)
- Settings system (`Settings` class) with sanitized option storage, input validation
  callbacks, and REST settings endpoints
- Modernized database collector to use `$wpdb->charset`/`$wpdb->collate` runtime values;
  updated charset fallback from `utf8` to `utf8mb4` (Phase 1.1/1.2)
- Comprehensive documentation architecture: developer guide, hook reference, architecture
  overview, and per-feature docs added under `docs/` (Phase 0)

**CI & Tooling**

- Dependabot configuration for GitHub Actions SHA updates
- Third-party GitHub Actions pinned to full commit SHAs to prevent supply-chain attacks
- PHPStan static analysis integrated into CI with `--memory-limit=1G`
- Rector dry-run integrated into CI; PHP 8.1+ patterns enforced across codebase
- PHPCS WordPress Coding Standards enforced across PHP 8.1-8.4 matrix

### Fixed

**Code Review Cycle (37 findings)**

- Critical: directory traversal prefix confusion in `Error_Log_Reader::is_path_safe()` —
  added trailing separator to all prefix checks (finding #48)
- Critical: HTTP response splitting via tampered `wp_options` data in `Security_Hardener` —
  added explicit header name allowlist and CR/LF rejection (finding #49)
- Critical: binary gzip data stored with `%s` format specifier causing UTF-8 truncation in
  MySQL utf8mb4 columns — `Report_History` now base64-encodes before storage with
  transparent legacy decode on read (finding #50)
- High: unbounded filesystem recursion in `Media_Uploads::get_directory_size()` — added
  `MAX_FILES_TO_COUNT` (50,000) limit (finding #51)
- High: `catch(\Exception)` in `Site_Health` collector missed `TypeError` and other PHP 8.x
  throwables — changed to `catch(\Throwable)` (finding #52)
- High: missing server-side confirmation guard in `Fixer_Controller::execute_fix()` for
  medium/high risk fixers — returns HTTP 409 when `confirmed=true` is absent (finding #53)
- High: predictable backup file paths in `Debug_Toggle::get_backup_path()` used
  deterministic `md5()` — replaced with `random_bytes(16)` CSPRNG (finding #54)
- High: `Admin_Page::render_page()` ran all collectors on every page load — report
  generation now only executes when on the report tab (finding #55)
- High: full server path exposed in error log display — sanitized to `basename()` with
  full path moved to a private debug field (finding #56)
- High: `Database_Optimizer` ran `OPTIMIZE TABLE` on unlimited tables — capped at 20 per
  run with locking documentation added (finding #57)
- Medium: missing timeout and retry on AI context generator HTTP requests (finding #59)
- Medium: missing capability check on WP-CLI fix command execution (finding #60)
- Medium: `Fix_Result` data property lacked strict type declaration (finding #61)
- Medium: duplicate fixer registration possible in `Fixer_Registry` — added deduplication
  guard (finding #62)
- Medium: GitHub API response structure not validated in `GitHub_Updater` (finding #63)
- Medium: Settings inputs not fully sanitized — added proper validation callbacks for all
  fields (finding #64)
- Medium: SSE controller missing `Content-Type` header and connection-close (finding #65)
- Medium: missing fallback for absent block type source in `Block_Editor` collector
  (finding #66)
- Medium: `Custom_Content_Types` taxonomy query lacked a `LIMIT` clause (finding #67)
- Medium: `open_basedir` restrictions not detected or reported in
  `Filesystem_Permissions` collector (finding #68)
- Medium: `Media_Uploads` directory size calculation lacked file count cap (finding #69)
- Low: `WP_Theme_JSON_Resolver` used for global styles detection instead of
  `file_get_contents()` with fallback for WordPress < 5.8 (finding #76)
- Low: `count_users()` result cached via transient (1-hour TTL) to avoid expensive
  COUNT query on every report generation (finding #77)
- Low: `DISABLE_WP_CRON` downgraded from Warning to Info in constants collector to avoid
  duplicate warning with cron health collector (finding #78)
- Low: composite key (label + positional index) used in `Report_Diff::index_fields()` so
  duplicate field labels are preserved in diffs (finding #79)
- Low: `@gzuncompress` error suppression replaced with explicit
  `set_error_handler`/`restore_error_handler` wrapped in `try/finally` (finding #80)
- Low: `Notification_Manager` batches `wp_mail()` recipients into a single call; uses
  `Field::get_status_string()` instead of custom helper (finding #81)
- Low: `REST_Envelope::build_meta()` `array_merge` order reversed so canonical keys
  (`generated_at`, `plugin_version`) cannot be overridden by caller-supplied meta
  (finding #82)
- Low: `Abilities_Provider` confirmation guard added for medium/high risk fixers with new
  `confirmed` input schema property (finding #83)
- Low: null check added on `preg_replace()` return in `GitHub_Formatter` to prevent silent
  data loss from invalid redaction patterns (finding #84)
- Error log not loading regression introduced during Interactivity API migration (finding #87)
- `$wpdb->prepare()` with `%i` identifier placeholder used for options table queries to
  satisfy PHPCS `WordPress.DB.PreparedSQL` rules
- Email privacy: notifications send per-recipient to avoid exposing addresses to all
  recipients
- `set_time_limit()` and `fileperms()` calls guarded against disabled function environments
- Collector registration order: `Block_Editor` registered before `Network_Connectivity`
  to preserve expected tab ordering

**Test Infrastructure**

- 23 per-collector PHPUnit test files added in five batches covering all collectors:
  `ActivePlugins`, `AdvancedDiagnostics`, `BlockEditor`, `CronHealth`,
  `CustomContentTypes`, `Database`, `DropinsMuPlugins`, `EmailDelivery`,
  `FilesystemPermissions`, `InactivePlugins`, `MediaUploads`, `NetworkConnectivity`,
  `Performance`, `PostTypeCounts`, `RestApiInfo`, `Security`, `ServerEnvironment`,
  `SiteHealth`, `ThemeInfo`, `UpdateHealth`, `WordPressConfiguration`,
  `WordPressConstants`, `WordPressEnvironment`
- Additional integration test classes: `FixerControllerTest`, `FixersTest`,
  `HealthScoreTest`, `ReportDiffTest`, `ReportHistoryTest`, `SSELogStreamerTest`,
  `SettingsTest`, `GitHubUpdaterTest`
- CI failures resolved across all PHP 8.1-8.4 and MySQL 8.0 matrix combinations

---

## [1.1.0] - 2025-07-15

### Added
- PHPUnit test suite with CI integration (PHP 7.4-8.3, MySQL 8.0)
- Compatibility with WordPress 6.9.1

### Fixed
- `test_ai_no_issues_message` for EOL PHP versions
- `test_is_path_safe_within_abspath` for CI environments

---

## [1.0.0] - 2025-06-01

### Added
- 17 diagnostic collectors covering WordPress environment, server, database, plugins,
  themes, security, cron, REST API, and more
- Plain Text, GitHub (redacted), and AI-optimized export formatters
- Error Log Viewer with configurable line count (1-10,000)
- Debug Toggle for `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY` via `wp-config.php`
- REST API at `wp-system-report/v1/` with report and error log endpoints
- GitHub-based auto-update checker
- Transient caching for expensive collectors with automatic invalidation
- 11 filter hooks and 2 action hooks for extensibility
- Multisite support with per-site cleanup on uninstall
