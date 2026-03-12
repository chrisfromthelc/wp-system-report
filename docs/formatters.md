# Formatters

Formatters transform the raw report data from collectors into different output formats. WP System Report includes three built-in formatters.

## Plain Text Formatter

**Class:** `SystemReport\Formatters\Plain_Text_Formatter`
**Content Type:** `text/plain; charset=utf-8`
**File Extension:** `.txt`

Produces a WooCommerce-style system status report with section headers and label/value pairs. Includes status symbols: `✔` for good, `❌` for critical or warning.

**Example output:**

```
### WordPress Environment ###

WordPress Version: 6.9.1 ✔
Home URL: https://example.com
Site URL: https://example.com
Debug Mode: Disabled ✔
```

## GitHub Formatter

**Class:** `SystemReport\Formatters\GitHub_Formatter`
**Content Type:** `text/plain; charset=utf-8`
**File Extension:** `.txt`

Wraps the plain text report in an HTML `<details>` tag for easy pasting into GitHub issues and pull requests. Automatically redacts sensitive information:

- Site URLs are replaced with `https://example.com`
- Database table prefixes are replaced with `wp_`
- Additional patterns can be added via the `wp_system_report_redactions` filter

**Example output:**

```html
<details>
<summary>System Status Report</summary>

```
### WordPress Environment ###

WordPress Version: 6.9.1 ✔
Home URL: https://example.com
...
```

</details>
```

## AI Formatter

**Class:** `SystemReport\Formatters\AI_Formatter`
**Content Type:** `text/markdown; charset=utf-8`
**File Extension:** `.md`

Produces structured markdown optimized for consumption by LLMs (Claude, ChatGPT, etc.).

### Structure

1. **Header** - Site URL, generation timestamp, WordPress/PHP versions
2. **Issues Summary** - Severity-ranked detected problems with recommendations
3. **Section Tables** - Markdown tables with Setting | Value | Status | Recommended columns
4. **Contextual Descriptions** - Blockquotes explaining each section for AI context

### Heuristic Checks

The AI formatter runs automatic heuristic checks and reports findings in the issues summary:

- **PHP End-of-Life** - Flags PHP versions past their supported dates
- **Autoloaded Options** - Warns if autoloaded option size exceeds 1 MB
- **Object Cache** - Recommends persistent object cache if not configured
- **Database Engines** - Flags non-InnoDB tables that may cause performance issues
- **No Issues** - Confirms when no issues are detected

### Customization

| Filter | Description |
|--------|-------------|
| `wp_system_report_ai_header` | Customize the markdown header content |
| `wp_system_report_ai_issues` | Add, remove, or modify detected issues |

## Using Formatters via REST API

```
GET /wp-json/wp-system-report/v1/report              # JSON (raw data)
GET /wp-json/wp-system-report/v1/report?format=plain  # Plain Text
GET /wp-json/wp-system-report/v1/report?format=github # GitHub (redacted)
GET /wp-json/wp-system-report/v1/report?format=ai     # AI Markdown
```

See the [REST API documentation](rest-api.md) for authentication details.

## Writing Custom Formatters

See the [Extending the Plugin](extending.md) guide for instructions on implementing the `Formatter` interface.
