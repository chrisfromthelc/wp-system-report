# WP System Report

[![CI](https://github.com/chrisfromthelc/wp-system-report/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/chrisfromthelc/wp-system-report/actions/workflows/ci.yml)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-blue)
![WordPress 6.2+](https://img.shields.io/badge/WordPress-6.2%2B-21759B)
![License: GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-green)

A comprehensive WordPress system diagnostics plugin with AI-optimized export. Standalone, no WooCommerce required.

## Features

- **17 Diagnostic Collectors** covering WordPress environment, server, database, plugins, themes, security, cron, REST API, and more
- **Multiple Export Formats** - Plain text, GitHub-friendly (with redactions), and AI-optimized markdown
- **AI-Ready Export** - Structured markdown with contextual descriptions, status indicators, and proactive issue detection designed for LLMs
- **Error Log Viewer** - View, copy, and download the PHP error log from the admin with configurable line count
- **Debug Toggle** - Enable/disable `WP_DEBUG`, `WP_DEBUG_LOG`, and `WP_DEBUG_DISPLAY` with a single click
- **REST API** - Full JSON API with format parameter support
- **Extensible** - Filter hooks for custom collectors, fields, formatters, and issue detection
- **Auto-Updates** - Checks GitHub Releases for new versions via the WordPress dashboard

## Quick Start

1. Download the [latest release](https://github.com/chrisfromthelc/wp-system-report/releases/latest/download/wp-system-report.zip)
2. Upload and activate through the WordPress admin
3. Navigate to **Tools > WP System Report**

See the [Getting Started guide](docs/getting-started.md) for detailed installation instructions.

## Requirements

- WordPress 6.2+ (tested up to 6.9.1)
- PHP 7.4+

## Documentation

| Guide | Description |
|-------|-------------|
| **[Getting Started](docs/getting-started.md)** | Installation and first report |
| **[Collectors](docs/collectors.md)** | All 17 collectors and their fields |
| **[Formatters](docs/formatters.md)** | Plain Text, GitHub, and AI export formats |
| **[REST API](docs/rest-api.md)** | Endpoints, authentication, examples |
| **[Error Log](docs/error-log.md)** | Log viewer and debug toggle |
| **[Extending](docs/extending.md)** | Custom collectors, formatters, integrations |
| **[Hooks Reference](docs/hooks-reference.md)** | All actions and filters |
| **[Architecture](docs/architecture.md)** | Plugin architecture and data flow |
| **[Changelog](docs/changelog.md)** | Release history |

## REST API

```bash
# Full report as JSON
GET /wp-json/wp-system-report/v1/report

# AI-optimized markdown
GET /wp-json/wp-system-report/v1/report?format=ai

# Error log (last 100 lines)
GET /wp-json/wp-system-report/v1/error-log
```

All endpoints require the `manage_options` capability. See the [REST API docs](docs/rest-api.md) for authentication and full endpoint reference.

## Extending

Add custom collectors with a single filter:

```php
add_filter( 'wp_system_report_collectors', function ( $collectors ) {
    $collectors['my_custom'] = new My_Custom_Collector();
    return $collectors;
} );
```

See the [Extending guide](docs/extending.md) for full examples and the [Hooks Reference](docs/hooks-reference.md) for all available hooks.

## Development

```bash
composer install          # Install dependencies
composer lint             # Run PHPCS + PHPStan + Rector dry-run
composer test             # Run PHPUnit tests
```

See the [Architecture docs](docs/architecture.md) for project structure and design patterns.

## Contributing

1. Fork the repository
2. Create a feature branch from `develop`
3. Make your changes with tests
4. Ensure `composer lint` passes
5. Open a PR targeting `develop`

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
