# Changelog

All notable changes to WP System Report will be documented in this file.

## [1.1.0] - 2025-07-15

### Added
- PHPUnit test suite with CI integration (PHP 7.4-8.3, MySQL 8.0)
- Compatibility with WordPress 6.9.1

### Fixed
- `test_ai_no_issues_message` for EOL PHP versions
- `test_is_path_safe_within_abspath` for CI environments

## [1.0.0] - 2025-06-01

### Added
- 17 diagnostic collectors covering WordPress environment, server, database, plugins, themes, security, cron, REST API, and more
- Plain Text, GitHub (redacted), and AI-optimized export formatters
- Error Log Viewer with configurable line count (1-10,000)
- Debug Toggle for `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY` via `wp-config.php`
- REST API at `wp-system-report/v1/` with report and error log endpoints
- GitHub-based auto-update checker
- Transient caching for expensive collectors with automatic invalidation
- 11 filter hooks and 2 action hooks for extensibility
- Multisite support with per-site cleanup on uninstall
