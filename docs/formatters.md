# Formatters

Formatters transform the raw report data from collectors into different output formats. WP System Report includes four built-in formatters.

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

Produces structured markdown optimized for consumption by LLMs (Claude, ChatGPT, etc.). Includes an executive summary with a computed health score, severity-scored issues grouped by category, and prioritized recommendations.

### Structure

1. **Header** - Site URL, generation timestamp, WordPress/PHP versions
2. **Executive Summary** - Health score (0-100), rating, issue counts, top 3 priorities
3. **Issues Summary** - Severity-scored issues grouped by category with fix references
4. **Section Tables** - Markdown tables with Setting | Value | Status | Recommended columns
5. **Contextual Descriptions** - Blockquotes explaining each section for AI context

### Executive Summary

The executive summary provides a quick overview of site health:

- **Health Score** - 0-100 score computed by deducting points per issue (10 for critical, 5 for warnings)
- **Rating** - Excellent (90+), Good (70-89), Fair (50-69), or Needs Attention (below 50)
- **Issue Counts** - Number of critical and warning issues detected
- **Top Priorities** - Up to 3 highest-severity issues listed for immediate attention

### Issue Categorization

Detected issues are automatically categorized into groups: Security, Performance, Updates, Configuration, Email, Media, Cron & Scheduling, Connectivity, Block Editor, REST API, and General. Categorization uses keyword matching on field labels with a fallback to section-based classification.

Issues include a severity score (`10` for critical, `5` for warning) and reference a `fix_id` when a corresponding fixer is available (Phase 3).

### Heuristic Checks

The AI formatter runs automatic heuristic checks beyond field-level statuses:

- **PHP End-of-Life** - Flags PHP versions below 8.1
- **Autoloaded Options** - Warns if autoloaded option size exceeds 1 MB
- **Object Cache** - Recommends persistent object cache when 15+ plugins are active without one
- **Database Engines** - Flags non-InnoDB tables that may cause performance issues
- **Email Configuration** - Detects sites using default PHP `mail()` and recommends an SMTP plugin
- **Update Posture** - Warns when 5 or more plugin updates are pending
- **Editor Bloat** - Flags sites with 500+ registered block types that may slow the editor

### Customization

| Filter | Description |
|--------|-------------|
| `wp_system_report_ai_header` | Customize the markdown header content |
| `wp_system_report_ai_issues` | Add, remove, or modify detected issues before rendering |
| `wp_system_report_ai_executive_summary` | Customize or extend the executive summary output |

## MCP Formatter

**Class:** `SystemReport\Formatters\MCP_Formatter`
**Content Type:** `application/json; charset=utf-8`
**File Extension:** `.json`

Produces structured JSON optimized for AI agents consuming data via the Model Context Protocol (MCP). Unlike the AI Formatter (which outputs markdown), the MCP Formatter returns compact, machine-readable JSON designed for token-efficient processing in LLM context windows.

### Design Principles

- **Token efficiency** — Only fields with warning or critical status are listed in detail; good/info fields are counted but not enumerated.
- **Actionable references** — Issues include `fix_id` links to available fixers.
- **Machine-readable** — All statuses, categories, and ratings use consistent lowercase enum strings.
- **Compact descriptions** — Field values are capped at 200 characters to prevent token bloat.

### Structure

The output is a JSON object with four top-level keys:

1. **`site`** — Site identity and metadata (URL, name, WordPress/PHP versions, multisite flag, generation timestamp)
2. **`health`** — Health score (0-100), rating (`excellent`/`good`/`fair`/`needs_attention`), issue counts, section/check totals, fixer availability flag
3. **`issues`** — Prioritised list of warning/critical findings sorted by weight, with severity, category, description, and optional `fix_id` and `recommended` fields
4. **`sections`** — Per-section summaries with `total_checks`, `passing` count, and `notable_fields` array (only non-good/non-info fields). Sections exceeding 50 notable fields are truncated.

### Health Score

The health score is computed identically to the AI Formatter:

- Starts at 100
- Deducts 10 points per critical issue
- Deducts 5 points per warning issue
- Minimum score is 0

### Issue Categorization

Issues are categorized using keyword matching on field labels with a fallback to section-based classification. Categories include: `security`, `performance`, `updates`, `email`, `media`, `cron`, `connectivity`, `rest_api`, `database`, `editor`, and `general`.

### Convenience Method

The `format_array()` method returns the payload as a decoded PHP array instead of a JSON string, useful for MCP ability callbacks that need structured data directly:

```php
$formatter = new MCP_Formatter();
$payload   = $formatter->format_array( $report_data );
// $payload is an array with 'site', 'health', 'issues', 'sections' keys
```

### Customization

| Filter | Description |
|--------|-------------|
| `wp_system_report_mcp_payload` | Modify the full MCP payload before JSON encoding |
| `wp_system_report_mcp_site_identity` | Customize the site identity metadata block |

## Using Formatters via REST API

```
GET /wp-json/wp-system-report/v1/report              # JSON (raw data)
GET /wp-json/wp-system-report/v1/report?format=plain  # Plain Text
GET /wp-json/wp-system-report/v1/report?format=github # GitHub (redacted)
GET /wp-json/wp-system-report/v1/report?format=ai     # AI Markdown
GET /wp-json/wp-system-report/v1/report?format=mcp    # MCP JSON
```

See the [REST API documentation](rest-api.md) for authentication details.

## Writing Custom Formatters

See the [Extending the Plugin](extending.md) guide for instructions on implementing the `Formatter` interface.
