# WP-CLI Integration

WP System Report registers a full suite of WP-CLI commands under the `wp system-report` parent command. These commands let you generate reports, inspect health scores, manage fixers, and audit cron health entirely from the command line — no browser required.

## Requirements

- WP-CLI 2.0 or later
- The `manage_options` capability (commands run as the WordPress admin context)

## Command Reference

### `wp system-report generate`

Generate a full system report and print it to stdout.

**Syntax:**

```
wp system-report generate [--format=<format>] [--section=<section>]
```

**Options:**

| Option | Default | Accepted Values | Description |
|--------|---------|-----------------|-------------|
| `--format=<format>` | `table` | `table`, `json`, `plain`, `github`, `ai`, `mcp` | Controls the output format |
| `--section=<section>` | *(all)* | Any collector ID | Limits output to a single collector section |

**Format descriptions:**

| Format | Description |
|--------|-------------|
| `table` | Columnar output rendered by WP-CLI. Best for interactive terminal use. |
| `json` | Pretty-printed JSON. Suitable for piping to other tools or scripts. |
| `plain` | Plain text suitable for copying into a support ticket or email. |
| `github` | Markdown wrapped in a `<details>` block for pasting into GitHub issues. |
| `ai` | Optimized markdown for sending to an AI assistant for analysis. |
| `mcp` | JSON envelope formatted for the Model Context Protocol. |

**Examples:**

```bash
# Generate a full report as a terminal table.
wp system-report generate

# Generate a full report in JSON format.
wp system-report generate --format=json

# Generate only the security section.
wp system-report generate --section=security

# Generate the security section in plain text.
wp system-report generate --section=security --format=plain
```

---

### `wp system-report export`

Export the full system report to a file or stdout in a portable format.

**Syntax:**

```
wp system-report export [--format=<format>] [--output=<file>]
```

**Options:**

| Option | Default | Accepted Values | Description |
|--------|---------|-----------------|-------------|
| `--format=<format>` | `json` | `json`, `csv`, `md` | Export format |
| `--output=<file>` | *(stdout)* | Any writable file path | Write output to a file instead of stdout |

**Format descriptions:**

| Format | Description |
|--------|-------------|
| `json` | Pretty-printed JSON suitable for archiving or programmatic processing. |
| `csv` | Comma-separated values with columns `section`, `field`, `value`, `status`. |
| `md` | GitHub-flavored Markdown, identical to the `github` format from `generate`. |

**Examples:**

```bash
# Export to a JSON file.
wp system-report export --format=json --output=report.json

# Export to a CSV file using shell redirection.
wp system-report export --format=csv > report.csv

# Print a Markdown report to stdout.
wp system-report export --format=md

# Save a Markdown report to a file.
wp system-report export --format=md --output=report.md
```

---

### `wp system-report health`

Display the aggregate site health score, letter grade, and an optional per-section breakdown.

**Syntax:**

```
wp system-report health [--breakdown] [--format=<format>]
```

**Options:**

| Option | Default | Accepted Values | Description |
|--------|---------|-----------------|-------------|
| `--breakdown` | *(off)* | *(flag, no value)* | Include a per-section score breakdown |
| `--format=<format>` | `text` | `text`, `json`, `table` | Controls breakdown layout. `json` outputs the full score object and bypasses the summary. `table` renders the breakdown as a WP-CLI table. |

**Notes:**

- The summary line (score, grade, and field counts) is always rendered as plain text regardless of `--format`.
- `--format=table` only affects the breakdown table and has no effect unless `--breakdown` is also passed.
- `--format=json` outputs the complete score data structure and returns immediately, skipping the summary and breakdown display entirely.
- The breakdown sorts sections from worst to best score so problem areas appear first.

**Examples:**

```bash
# Show the health score and grade.
wp system-report health

# Show the score with a per-section breakdown in text format.
wp system-report health --breakdown

# Show the breakdown as a WP-CLI table.
wp system-report health --breakdown --format=table

# Output the full health score data as JSON.
wp system-report health --format=json
```

**Sample output (`wp system-report health`):**

```
Health Score: 78/100 (C)

Summary: 142 fields checked
  Good: 110  Warnings: 24  Critical: 8  Info: 0
```

---

### `wp system-report cron-check`

Inspect WordPress cron health, including overdue events, orphaned hooks, and the `doing_cron` lock status.

**Syntax:**

```
wp system-report cron-check
```

**Options:** None

Output lines are color-coded: green for good, yellow for warning, and red for critical. If any warnings or critical issues are found the command prints a remediation hint suggesting `wp system-report fix cron_repair`.

**Examples:**

```bash
# Run a cron health check.
wp system-report cron-check
```

**Sample output:**

```
Cron enabled: true
Overdue events: 0
Orphaned hooks: 2
doing_cron lock: inactive
Warning: Cron health issues detected. Consider running: wp system-report fix cron_repair
```

---

### `wp system-report collectors`

List all registered collector sections along with their IDs, labels, priorities, and descriptions.

**Syntax:**

```
wp system-report collectors [--format=<format>]
```

**Options:**

| Option | Default | Accepted Values | Description |
|--------|---------|-----------------|-------------|
| `--format=<format>` | `table` | `table`, `json`, `csv`, `yaml` | Output format |

**Examples:**

```bash
# List all collectors as a table.
wp system-report collectors

# List collectors in JSON format.
wp system-report collectors --format=json
```

**Columns returned:**

| Column | Description |
|--------|-------------|
| `id` | The collector identifier. Use this value with `--section` in `generate`. |
| `label` | Human-readable section name. |
| `priority` | Execution order (lower numbers run first). |
| `description` | Brief description of what the collector reports on. |

---

### `wp system-report fixes`

List all registered fixers with their IDs, labels, risk levels, and whether issues are currently detected.

**Syntax:**

```
wp system-report fixes [--format=<format>]
```

**Options:**

| Option | Default | Accepted Values | Description |
|--------|---------|-----------------|-------------|
| `--format=<format>` | `table` | `table`, `json`, `csv`, `yaml` | Output format |

**Note:** This command is only available when fixers are enabled in the current installation. If fixers are unavailable the command exits with an error.

**Examples:**

```bash
# List all fixers as a table.
wp system-report fixes

# List fixers in JSON format.
wp system-report fixes --format=json
```

**Columns returned:**

| Column | Description |
|--------|-------------|
| `id` | The fixer identifier. Use this value with `wp system-report fix`. |
| `label` | Human-readable fixer name. |
| `risk` | Risk level of applying this fix (`low`, `medium`, or `high`). |
| `has_issues` | Whether the fixer currently detects issues (`yes` or `no`). |
| `description` | Brief description of what the fixer remediates. |

---

### `wp system-report fix`

Run a specific fixer to remediate a detected issue.

**Syntax:**

```
wp system-report fix <fix_id> [--dry-run] [--yes]
```

**Arguments:**

| Argument | Required | Description |
|----------|----------|-------------|
| `<fix_id>` | Yes | The fixer identifier to run. Use `wp system-report fixes` to see all available IDs. |

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--dry-run` | *(off)* | Report whether issues are detected without making any changes. |
| `--yes` | *(off)* | Skip the interactive confirmation prompt and apply the fix immediately. |

**Notes:**

- This command is only available when fixers are enabled in the current installation.
- If no issues are detected the command exits with a warning rather than an error.
- After applying a fix the command prints before/after state as JSON if the fixer returns that data.
- Without `--yes` the command prompts for confirmation before making any changes.

**Examples:**

```bash
# Run a fixer interactively (prompts for confirmation).
wp system-report fix autoload_optimizer

# Check whether a fix is needed without applying it.
wp system-report fix database_optimizer --dry-run

# Apply a fix without a confirmation prompt (suitable for scripts).
wp system-report fix security_hardener --yes

# Repair cron without a prompt (e.g. in a cron job itself).
wp system-report fix cron_repair --yes
```

**Sample output:**

```
Fixer: Autoload Optimizer
Description: Removes stale autoloaded options from the database.
Risk level: low

Are you sure you want to run this fix? [y/n]
Success: Removed 14 stale autoloaded options.

Before:
  {"autoload_count": 312, "autoload_size_kb": 480}
After:
  {"autoload_count": 298, "autoload_size_kb": 421}
```

---

## Common Workflows

### Generating a report for a support ticket

```bash
# Plain text output ready to paste into a support forum or email.
wp system-report generate --format=plain

# GitHub Markdown for attaching to a bug report.
wp system-report generate --format=github
```

### Archiving a report for later comparison

```bash
# Save a timestamped JSON snapshot.
wp system-report export --format=json --output="report-$(date +%Y%m%d).json"
```

### Diagnosing a site in a CI or staging pipeline

```bash
# Exit non-zero if health score data reveals issues (requires jq).
score=$(wp system-report health --format=json | jq '.score')
echo "Health score: ${score}"
```

### Running all safe fixers non-interactively

```bash
# List fixers with issues and apply each safe one without prompting.
wp system-report fixes --format=json \
  | jq -r '.[] | select(.has_issues == "yes" and .risk == "low") | .id' \
  | while read -r id; do
      wp system-report fix "$id" --yes
    done
```

### Auditing cron and repairing in one step

```bash
wp system-report cron-check && echo "Cron OK" \
  || wp system-report fix cron_repair --yes
```

### Discovering available sections before generating a targeted report

```bash
# Step 1: see all collector IDs.
wp system-report collectors

# Step 2: generate only the section you care about.
wp system-report generate --section=database --format=json
```

---

## See Also

- **[REST API](rest-api.md)** - Access the same report data over HTTP
- **[Getting Started](getting-started.md)** - Installation and admin UI overview
- **[Extending the Plugin](extending.md)** - Register custom collectors and fixers
