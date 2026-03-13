# Collectors

WP System Report ships with 23 collectors that gather diagnostic data from every major aspect of a WordPress installation. Collectors are executed in priority order (lowest number first) and their results are combined into the final report.

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

### Email Delivery (priority 180)

**ID:** `email_delivery` | **Cached:** 1 hour (`sr_email_delivery`)

Email configuration, mail transport method, SMTP status, and mail service plugins.

| Field | Status Conditions | Description |
|-------|-------------------|-------------|
| Admin Email | Critical if empty, Info otherwise | Site administrator email used as the default sender (marked private) |
| From Address | Info | The address used in the `From` header, resolved via `wp_mail_from` filter |
| From Name | Warning if still `"WordPress"`, Good otherwise | The name used in the `From` header |
| Mail Transport | Good if SMTP, Info for PHP `mail()` | Active transport method (`PHP mail()`, `SMTP`, or custom `phpmailer_init` override) |
| SMTP Host | Good if not `localhost`, Info otherwise | SMTP server hostname |
| SMTP Port | Good for 465/587, Warning for 25, Info otherwise | Recommended: 587 (STARTTLS) or 465 (SSL) |
| SMTP Encryption | Good for SSL/TLS or STARTTLS, Info for none | Encryption in use, inferred from `SMTP_PORT` constant |
| Mail Plugin | Good if detected, Info if none | Known mail delivery plugins: WP Mail SMTP, FluentSMTP, Post SMTP, Mailgun, SendGrid, WP Offload SES, and others |
| PHPMailer Override | Info | Whether any code hooks into `phpmailer_init` |
| Sendmail Path | Info | Path to sendmail binary from `php.ini` |
| PHP mail() Disabled | Critical if disabled, Good otherwise | Whether `mail` appears in `disable_functions` |

---

### Media & Uploads (priority 190)

**ID:** `media_uploads` | **Cached:** 1 hour (`sr_media_uploads`)

Media library statistics, upload directory health, image processing capabilities, and upload limits.

| Field | Status Conditions | Description |
|-------|-------------------|-------------|
| Upload Directory | Critical if `wp_upload_dir()` returns an error, Info otherwise | Base path for media uploads |
| Upload Dir Writable | Good if writable, Critical if not | Whether PHP can write to the uploads directory |
| Upload Directory Size | Warning if > 10 GB, Info otherwise | Total disk usage; shows an estimate if the directory exceeds 50,000 files |
| Total Attachments | Info | Count of all media library items |
| Media by Type | Info | Attachment breakdown: Images, Videos, Audio, PDFs, Other |
| Orphaned Attachments | Warning if > 100, Info if > 0, Good otherwise | Attachments whose parent post no longer exists |
| Upload / Post Max Alignment | Good if aligned, Warning if `upload_max_filesize` > `post_max_size` | PHP upload limit consistency check |
| WP Max Upload Size | Warning if < 2 MB, Info otherwise | Effective maximum upload size |
| Image Editor | Good for Imagick, Info for GD, Critical if none | Active WordPress image processing library |
| Registered Image Sizes | Warning if > 15, Info otherwise | Total image sizes including count of custom additions |
| Big Image Threshold | Warning if disabled (0), Info otherwise | Maximum pixel dimension before uploaded images are scaled down (default: 2560px) |

---

### Performance (priority 200)

**ID:** `performance` | **Cached:** 1 hour (`sr_performance`)

Object cache, page cache, OPcache status, `wp_options` table health, and database overhead.

| Field | Status Conditions | Description |
|-------|-------------------|-------------|
| Object Cache Backend | Good for Redis/Memcached/APCu, Info for default | The persistent object cache backend in use |
| Object Cache Drop-in | Good if present, Info if absent | Whether `wp-content/object-cache.php` exists |
| Page Cache Plugin | Good if detected, Info if none | Known page-caching plugins: WP Super Cache, W3 Total Cache, WP Rocket, LiteSpeed Cache, and others |
| OPcache | Good if enabled (Warning if wasted memory > 10%), Warning if disabled or unavailable | PHP OPcache hit rate, memory used, and wasted percentage |
| Total wp_options Rows | Warning if > 5,000, Info if > 2,000, Good otherwise | Total row count in the options table |
| wp_options Table Size | Warning if > 10 MB, Good otherwise | Combined data and index size of the options table |
| Expired Transients | Warning if > 500, Info if > 100, Good otherwise | Stale transient entries that can be cleaned up |
| Database Overhead | Warning if > 100 MB, Info if > 10 MB, Good otherwise | Total fragmentation (free space) across all tables |
| Top Autoloaded Options | Info | The 5 largest autoloaded options by value size (marked private) |
| Persistent Object Cache | Good if active, Warning if recommended (> 1,000 options rows or > 500 published posts), Info otherwise | Whether a persistent cache is in use or advised |

---

### Update Health (priority 210)

**ID:** `update_health` | **Cached:** 1 hour (`sr_update_health`)

WordPress core, plugin, and theme update status, auto-update configuration, and failed update history.

| Field | Status Conditions | Description |
|-------|-------------------|-------------|
| Core Update Status | Good if current, Warning for available update, Critical for available security update | Whether WordPress core is up to date |
| Core Update Channel | Good for Stable, Warning for Beta/RC or Development | Release channel derived from the version string |
| Core Auto-Updates | Good for minor or all updates, Warning if disabled | `WP_AUTO_UPDATE_CORE` / `AUTOMATIC_UPDATER_DISABLED` setting |
| Plugin Updates Available | Good if all current, Warning if > 5 pending, Info otherwise | Count of plugins with available updates |
| Plugin Auto-Updates | Good if all enabled, Warning if none enabled, Info otherwise | How many installed plugins have auto-updates turned on |
| Theme Updates Available | Good if all current, Warning if > 3 pending, Info otherwise | Count of themes with available updates |
| Theme Auto-Updates | Good if all enabled, Warning if none enabled, Info otherwise | How many installed themes have auto-updates turned on |
| Last Update Check | Good if < 12 hours ago, Info if < 24 hours, Warning otherwise | When WordPress last polled for updates |
| Failed Updates | Good if none, Warning for 1–2 failures, Critical for > 2 | Failed update attempts found in core update history |
| Translation Updates | Good if current, Info if pending | Pending translation updates for core, plugins, and themes |

---

### Network & Connectivity (priority 220)

**ID:** `network_connectivity` | **Cached:** 1 hour (`sr_network_connectivity`)

Outbound HTTP connectivity, proxy configuration, loopback request health, and SSL certificate status.

| Field | Status Conditions | Description |
|-------|-------------------|-------------|
| WordPress.org API | Good if HTTP 200, Warning for other codes, Critical on error | Connectivity to `api.wordpress.org` for update checks |
| WordPress.org Downloads | Good if 2xx/3xx, Warning otherwise, Critical on error | Connectivity to `downloads.wordpress.org` for package downloads |
| Loopback Request | Good if HTTP 200, Warning for other codes, Critical on error | Whether the site can make HTTP requests to itself (required for cron and Site Health) |
| HTTP Proxy | Info | Proxy host and port if `WP_PROXY_HOST` is defined |
| HTTP Transport | Good if any available, Critical if none | Available HTTP transports: cURL and/or PHP Streams |
| SSL Certificate | Good if > 30 days remaining, Warning if ≤ 30 days, Critical if expired | Certificate validity and days until expiry with issuer name |
| SSL Verification | Good if enabled, Warning if disabled | Whether WordPress verifies SSL certificates for outbound requests (`https_ssl_verify` filter) |
| External HTTP Blocked | Good if not blocked, Warning if `WP_HTTP_BLOCK_EXTERNAL` is set | Whether outbound HTTP is restricted, with any `WP_ACCESSIBLE_HOSTS` exceptions listed |
| DNS Resolution | Good if working, Critical if failed, Info if `dns_get_record()` unavailable | Whether the server can resolve DNS names |
| cURL Version | Good if available, Warning if unavailable | cURL library version and SSL library string |

---

### Block Editor (priority 230)

**ID:** `block_editor` | **Cached:** 1 hour (`sr_block_editor`)

Registered block types, block patterns, FSE/block theme detection, template parts, and editor performance indicators.

| Field | Status Conditions | Description |
|-------|-------------------|-------------|
| Block Theme (FSE) | Good if active, Info otherwise | Whether the active theme is a Full Site Editing theme with templates and template parts |
| Registered Block Types | Good if ≤ 300, Info if 301–500, Warning if > 500 | Total registered block types; a high count may impact editor load time |
| Block Sources | Info | Breakdown of registered blocks by source: Core, Plugin, Theme, Other |
| Registered Block Patterns | Info | Total block patterns from core, plugins, and the active theme |
| Pattern Categories | Info | Total registered block pattern categories |
| Template Parts | Info (N/A for classic themes) | Number of template parts available in the active block theme |
| Global Styles (theme.json) | Good if present and readable, Warning if present but unreadable, Info if absent | Whether the active theme includes a `theme.json` file, with schema version |
| Remote Block Patterns | Info | Whether remote patterns from the WordPress.org pattern directory are loaded (`should_load_remote_block_patterns` filter) |
| Editor Performance | Good if no concerns, Warning if > 400 block types, > 200 patterns, or > 100 dynamic blocks | Flags potential editor performance issues from excessive registrations |
| Classic Editor Override | Good if not active, Info if Classic Editor or Disable Gutenberg plugin is active | Whether a plugin is replacing the block editor with the classic editor |

---

## Caching

Collectors that perform expensive operations (database queries, directory scanning, API calls) use WordPress transient caching. The default TTL is 1 hour, configurable via the `wp_system_report_cache_ttl` filter.

Caches are automatically invalidated when:
- A theme is switched (`switch_theme`)
- A plugin is activated or deactivated
- A plugin or theme is updated via the upgrader

## Adding Custom Collectors

See the [Extending the Plugin](extending.md) guide for instructions on writing your own collectors.
