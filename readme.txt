=== System Report ===
Contributors: chrisfromthelc
Tags: system report, server info, debug, diagnostics, ai
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Comprehensive WordPress system status report with AI-optimized export. No WooCommerce required.

== Description ==

System Report provides a detailed overview of your WordPress installation, server environment, database, plugins, themes, and more. It is designed for developers, support teams, and site administrators who need quick access to diagnostic information.

**Key Features:**

* **17 diagnostic collectors** covering WordPress, PHP, MySQL, plugins, themes, security, cron, REST API, and more
* **Multiple export formats** - plain text, GitHub-friendly, and AI-optimized markdown
* **AI-ready export** - structured markdown with issue detection, status indicators, and recommendations designed for AI assistants like Claude and ChatGPT
* **REST API endpoint** with JSON, plain text, GitHub, and AI format support
* **Fully extensible** - add custom collectors and modify output via WordPress filters
* **Standalone** - works without WooCommerce or any other plugin
* **Lightweight** - vanilla JavaScript, no jQuery dependency

**Report Sections:**

* WordPress Environment (version, URLs, memory, debug, cron, locale)
* Server Environment (PHP, MySQL, cURL, extensions, connectivity)
* Database (all tables with sizes and engines)
* Post Type Counts
* Security (HTTPS, error display, file editing)
* Active Plugins (with update status)
* Inactive Plugins
* Drop-in & MU Plugins
* Theme Information
* WordPress Constants
* Filesystem Permissions
* Site Health Results
* Cron Health
* REST API Info
* Custom Content Types (post types, taxonomies, image sizes, shortcodes)
* WordPress Configuration (roles, permalinks, settings)
* Advanced Diagnostics (autoloaded options, disk usage, error log)

== Installation ==

1. Upload the `system-report` folder to `wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Navigate to **Tools > System Report** to view your report

== Frequently Asked Questions ==

= Does this require WooCommerce? =

No. System Report is fully standalone and works on any WordPress installation.

= What is the AI export? =

The AI export produces a structured markdown file optimized for AI assistants like Claude and ChatGPT. It includes contextual descriptions, status indicators, recommended values, and a summary of detected issues to help AI agents quickly understand your site's configuration and identify problems.

= How do I add a custom collector? =

Use the `system_report_collectors` filter to add your own collector class. See the plugin's README.md for a full example.

= What capability is required? =

By default, `manage_options` (Administrator). You can change this with the `system_report_capability` filter.

= Is data cached? =

Yes. Expensive collectors (plugins, themes, site health) use transient caching with a 1-hour TTL. Caches are automatically invalidated when plugins are activated/deactivated or themes are switched.

== Screenshots ==

1. System Report admin page showing diagnostic tables
2. Plain text report output
3. AI-optimized markdown export with issue detection

== Changelog ==

= 1.0.0 =
* Initial release
* 17 diagnostic collectors
* Plain text, GitHub, and AI export formats
* REST API endpoint with format parameter
* Transient caching with automatic invalidation
* Full extensibility via WordPress filters

== Upgrade Notice ==

= 1.0.0 =
Initial release.
