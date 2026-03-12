# Collectors

WP System Report ships with 17 collectors that gather diagnostic data from every major aspect of a WordPress installation. Collectors are executed in priority order (lowest number first) and their results are combined into the final report.

## Core Diagnostics

### WordPress Environment (priority 10)

**ID:** `wordpress_environment`

Core WordPress installation settings and configuration.

| Field | Example Value | Description |
|-------|--------------|-------------|
| Home URL | `https://example.com` | `home_url()` |
| Site URL | `https://example.com` | `site_url()` |
| WordPress Version | `6.9.1` | Core version with update status |
| Multisite | No | Whether this is a multisite installation |
| Memory Limit | `256M` | `WP_MEMORY_LIMIT` |
| Debug Mode | Disabled | `WP_DEBUG` state |
| WP-Cron | Enabled | Whether `DISABLE_WP_CRON` is set |
| Language | `en_US` | Site locale |
| Environment Type | `production` | `wp_get_environment_type()` |
| Object Cache | No | Whether a persistent object cache is active |
| Search Engine Visibility | Allowed | Whether search engines are discouraged |

---

### Server Environment (priority 20)

**ID:** `server_environment`

Server software, PHP configuration, and database settings.

| Field | Example Value | Description |
|-------|--------------|-------------|
| Server Software | `nginx/1.25` | Web server identification |
| PHP Version | `8.2.20` | With EOL status check |
| Post Max Size | `128M` | `post_max_size` ini setting |
| Max Execution Time | `300` | `max_execution_time` in seconds |
| Max Input Vars | `3000` | `max_input_vars` |
| PHP Memory Limit | `512M` | `memory_limit` ini setting |
| Max Upload Size | `128M` | `upload_max_filesize` |
| MySQL Version | `8.0.36` | Database server version |
| cURL Version | `8.5.0` | With SSL library |
| DOMDocument | Yes | Extension availability |
| GZip | Yes | `zlib` extension |
| Multibyte String | Yes | `mbstring` extension |
| OpenSSL | `3.1.4` | OpenSSL version |
| Imagick | Yes | ImageMagick availability |

---

### Database (priority 30)

**ID:** `database` | **Cached:** 1 hour (`sr_database`)

Database tables, sizes, and engine information.

| Field | Example Value | Description |
|-------|--------------|-------------|
| Database Prefix | `wp_` | Table prefix |
| Database Charset | `utf8mb4` | Connection charset |
| Database Collation | `utf8mb4_unicode_520_ci` | Connection collation |
| Max Allowed Packet | `67108864` | Maximum packet size |
| *Per table* | `wp_posts: 2.5 MB (InnoDB)` | Engine, row count, size per table |

---

### Post Type Counts (priority 40)

**ID:** `post_type_counts` | **Cached:** 1 hour (`sr_post_type_counts`)

Published entry count for each registered post type.

---

### Security (priority 50)

**ID:** `security`

Security-related settings and configuration.

| Field | Example Value | Description |
|-------|--------------|-------------|
| Secure Connection (HTTPS) | Yes | Whether the site uses SSL |
| Hide Errors from Visitors | Yes | `WP_DEBUG_DISPLAY` state |
| File Editing Disabled | Yes | `DISALLOW_FILE_EDIT` |
| File Modifications Disabled | No | `DISALLOW_FILE_MODS` |
| Application Passwords | Enabled | WordPress application passwords support |

---

### Active Plugins (priority 60)

**ID:** `active_plugins` | **Cached:** 1 hour (`sr_active_plugins`)

Currently active plugins with version and update information.

| Field | Example Value | Description |
|-------|--------------|-------------|
| *Plugin Name* | `WooCommerce (9.4.1)` | Name, version, author, update availability |

---

### Inactive Plugins (priority 70)

**ID:** `inactive_plugins` | **Cached:** 1 hour (`sr_inactive_plugins`)

Installed but deactivated plugins.

---

### Drop-ins & MU Plugins (priority 80)

**ID:** `dropins_mu_plugins` | **Cached:** 1 hour (`sr_dropins_mu_plugins`)

Drop-in replacements (e.g., `object-cache.php`, `advanced-cache.php`) and must-use plugins.

---

### Theme Information (priority 90)

**ID:** `theme_info` | **Cached:** 1 hour (`sr_theme_info`)

Active theme details including parent/child theme relationships.

| Field | Example Value | Description |
|-------|--------------|-------------|
| Active Theme | `Twenty Twenty-Four (1.2)` | Theme name and version |
| Theme Author | `the WordPress team` | Theme author |
| Child Theme | No | Whether a child theme is active |
| Block Theme | Yes | Whether it's a Full Site Editing theme |
| Parent Theme | — | Parent theme name if child theme |

---

## Extended Diagnostics

### WordPress Constants (priority 100)

**ID:** `wordpress_constants`

Values of defined WordPress constants. The list of constants checked is filterable via `wp_system_report_constants`.

**Default constants checked:** `ABSPATH`, `WP_HOME`, `WP_SITEURL`, `WP_CONTENT_DIR`, `WP_PLUGIN_DIR`, `WP_MEMORY_LIMIT`, `WP_MAX_MEMORY_LIMIT`, `WP_DEBUG`, `WP_DEBUG_DISPLAY`, `WP_DEBUG_LOG`, `SCRIPT_DEBUG`, `WP_CACHE`, `CONCATENATE_SCRIPTS`, `COMPRESS_SCRIPTS`, `COMPRESS_CSS`, `WP_ENVIRONMENT_TYPE`, `WP_DEVELOPMENT_MODE`, `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`, `DISABLE_WP_CRON`, `WP_AUTO_UPDATE_CORE`, `FORCE_SSL_ADMIN`, `AUTOSAVE_INTERVAL`, `WP_POST_REVISIONS`

---

### Filesystem Permissions (priority 110)

**ID:** `filesystem_permissions`

Writability status of core WordPress directories.

| Directory | Description |
|-----------|-------------|
| WordPress Root | ABSPATH |
| wp-content | `WP_CONTENT_DIR` |
| Uploads | `wp_upload_dir()` |
| Plugins | `WP_PLUGIN_DIR` |
| Themes | Theme root directory |
| MU Plugins | `WPMU_PLUGIN_DIR` |

---

### Site Health (priority 120)

**ID:** `site_health` | **Cached:** 1 hour (`sr_site_health`)

WordPress Site Health test results grouped by status (good, recommended, critical) with summary counts.

---

### Cron Health (priority 130)

**ID:** `cron_health`

Scheduled event status and WP-Cron health.

| Field | Description |
|-------|-------------|
| WP-Cron Disabled | Whether `DISABLE_WP_CRON` is set |
| Total Scheduled Events | Count of all cron events |
| Next Cron Run | Timestamp of next scheduled event |
| Overdue Events | Count and hook names of events past due |
| Last Cron Run | Timestamp of last successful cron execution |

---

### REST API Info (priority 140)

**ID:** `rest_api_info`

REST API availability and configuration.

| Field | Description |
|-------|-------------|
| REST URL | Full REST API root URL |
| REST Prefix | URL prefix (default: `wp-json`) |
| Namespaces | All registered REST namespaces |
| Namespace Count | Total count |

---

### Custom Content Types (priority 150)

**ID:** `custom_content_types`

Custom post types, taxonomies, image sizes, shortcodes, and sidebars.

---

### WordPress Configuration (priority 160)

**ID:** `wordpress_configuration`

Site settings including permalink structure, user roles, comments, media, and timezone.

---

### Advanced Diagnostics (priority 170)

**ID:** `advanced_diagnostics` | **Cached:** 1 hour (`sr_advanced_diagnostics`)

| Field | Description |
|-------|-------------|
| Autoloaded Options | Count and total size (flags if > 1 MB) |
| Uploads Directory Size | Disk usage of uploads |
| Plugins Directory Size | Disk usage of plugins |
| Themes Directory Size | Disk usage of themes |
| Rewrite Rules Count | Total registered rewrite rules |
| PHP Error Log | Error log file path and size |
| .htaccess Present | Whether `.htaccess` exists |

---

## Caching

Collectors that perform expensive operations (database queries, directory scanning, API calls) use WordPress transient caching. The default TTL is 1 hour, configurable via the `wp_system_report_cache_ttl` filter.

Caches are automatically invalidated when:
- A theme is switched (`switch_theme`)
- A plugin is activated or deactivated
- A plugin or theme is updated via the upgrader

## Adding Custom Collectors

See the [Extending the Plugin](extending.md) guide for instructions on writing your own collectors.
