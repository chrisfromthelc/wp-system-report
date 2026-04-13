---
name: wp-system-report
description: >
  WordPress site health monitoring via the WP System Report MCP plugin. Use this skill whenever:
  (1) the user asks about WordPress site health, status, issues, or problems;
  (2) the user wants to check PHP errors, debug logs, or troubleshoot a WP site;
  (3) the user mentions "my WordPress sites", "WP issues", "site report", or similar;
  (4) you see MCP servers with "wordpress-" prefixes connected.
  This skill eliminates the need for tool discovery — all abilities are pre-documented here.
  Trigger liberally for any WordPress health/diagnostics query.
---

# WordPress System Report Skill

This skill provides pre-documented access to WordPress sites connected via the **WP System Report MCP plugin**. It eliminates the tool discovery dance — everything you need is documented here.

## Identifying Connected WordPress Sites

WordPress sites appear as MCP servers with names like:
- `wordpress-sitename`
- `wordpress-examplecom`
- `wordpress-coolsitenet`

Each connected site exposes the same set of abilities. To work with a site, use its MCP server name as the prefix, e.g.:
- `wordpress-examplecom:mcp-adapter-execute-ability`
- `wordpress-coolsitenet:mcp-adapter-execute-ability`

**To list all connected sites**: Look for MCP servers with `wordpress-` prefix in your available tools.

---

## ⚠️ REQUIRED WORKFLOW

**Always call `get-agent-context` FIRST** before any other ability on a site. This returns:
- Environment type (production/staging/local/development)
- Safety rules and thresholds
- Environment-aware severity calibration

This is critical because the same issue may be critical on production but informational on local dev.

---

## Available Abilities

All abilities use this call pattern:
```
<server>:mcp-adapter-execute-ability
  ability_name: "wp-system-report/<ability>"
  parameters: { ... }
```

### 1. get-agent-context (CALL FIRST)

Returns environment-aware guidance, rules, and thresholds.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| (none) | — | — | No parameters needed |

**Returns**: Environment type, safety rules, thresholds, PHP lifecycle info, ability hints.

**Example**:
```json
{
  "ability_name": "wp-system-report/get-agent-context",
  "parameters": {}
}
```

---

### 2. get-issues

Returns detected warnings and critical issues. This is the fastest way to get an executive summary.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| (none) | — | — | No parameters needed |

**Returns**: Array of issues with severity (critical/warning), title, description. Also includes counts and environment context.

**Example**:
```json
{
  "ability_name": "wp-system-report/get-issues",
  "parameters": {}
}
```

---

### 3. get-report

Returns the complete system report. Use sparingly — it's large.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| format | string | No | "markdown" | `markdown` for AI-optimized, `json` for structured |

**Example**:
```json
{
  "ability_name": "wp-system-report/get-report",
  "parameters": { "format": "markdown" }
}
```

---

### 4. get-section

Returns a single section by collector ID. More efficient than full report.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| section | string | Yes | Collector ID (see list below) |

**Known section IDs**:
- `wordpress_environment` — WP version, site URLs, multisite, theme
- `database` — DB version, tables, sizes, engines
- `security` — SSL, file permissions, constants
- `active_plugins` — Currently active plugins
- `server` — PHP, memory, upload limits, cron

If you request an invalid section, the error response includes `available_sections` array.

**Example**:
```json
{
  "ability_name": "wp-system-report/get-section",
  "parameters": { "section": "database" }
}
```

---

### 5. get-error-log

Reads the last N lines of the PHP error log. Sensitive data is auto-redacted.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| lines | integer | No | 100 | Number of lines (1-10000) |

**Example**:
```json
{
  "ability_name": "wp-system-report/get-error-log",
  "parameters": { "lines": 200 }
}
```

---

### 6. get-debug-status

Returns current WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY state.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| (none) | — | — | No parameters needed |

**Returns**: Current debug state and whether modification is possible (`can_modify`).

---

### 7. toggle-debug ⚠️

Enables or disables WP_DEBUG by modifying wp-config.php.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| enable | boolean | Yes | `true` to enable, `false` to disable |

**⚠️ CAUTION**: 
- Always confirm with user before toggling on production
- Always disable after investigation on production
- Debug logs may contain sensitive information

**Example**:
```json
{
  "ability_name": "wp-system-report/toggle-debug",
  "parameters": { "enable": true }
}
```

---

## Environment-Aware Severity Rules

The agent context includes environment overrides. Key rules:

| Issue | Local/Dev | Production |
|-------|-----------|------------|
| No HTTPS | Informational | Critical |
| WP_DEBUG enabled | Expected/Good | Critical |
| File permissions 777 | Informational | Critical |
| No object cache | OK | Warning (if 15+ plugins) |
| Database root user | Common | Critical |
| Outdated PHP (EOL) | Informational | Critical |
| DISALLOW_FILE_EDIT not set | OK | Warning |

---

## Common Task Patterns

### Executive Summary (Health Check)

```
1. get-agent-context → understand environment
2. get-issues → get all warnings/critical issues
3. Summarize findings with environment-appropriate severity
```

### Debug PHP Errors

```
1. get-agent-context → check environment
2. get-debug-status → see if logging is enabled
3. If needed: toggle-debug (enable: true) [CONFIRM ON PRODUCTION]
4. get-error-log (lines: 500) → review errors
5. After investigation: toggle-debug (enable: false) [ON PRODUCTION]
```

### Database Investigation

```
1. get-agent-context
2. get-section (section: "database")
3. Compare against thresholds from agent context
```

### Multi-Site Health Dashboard

```
For each connected wordpress-* server:
  1. get-agent-context
  2. get-issues
Compile cross-site summary prioritizing production sites
```

---

## Safety Rules (Never Recommend)

These are absolute prohibitions from the WP System Report plugin:

- ❌ chmod 777 or world-writable permissions
- ❌ Disabling HTTPS or SSL verification
- ❌ Editing WordPress core files directly
- ❌ WP_DEBUG_DISPLAY = true on production
- ❌ DROP, TRUNCATE, DELETE queries without explicit user confirmation
- ❌ Deactivating security plugins without alternative protection
- ❌ Using MySQL root account for WordPress
- ❌ Deleting xmlrpc.php (disable via filter instead)
- ❌ Running multiple WAF/firewall plugins
- ❌ Changing table prefix on existing production site
- ❌ Caching plugins on managed hosting with built-in caching
- ❌ Outputting API keys, passwords, or security tokens

---

## Thresholds Reference

From agent context, for environment-aware analysis:

| Metric | Minimum | Warning | Critical |
|--------|---------|---------|----------|
| PHP Version | 8.1 | — | Below 8.1 (EOL) |
| PHP Memory Limit | 256M | — | Below 256M |
| WP Memory Limit | 128M | — | Below 128M |
| Autoload Size | — | 800KB | 2048KB |
| Database Size | — | 1024MB | — |
| Rewrite Rules | — | 500 | 2000 |
| Cron Events | — | 100 | 200 |
| Queries/Page | — | 100 | 200 |

---

## Quick Reference Card

| Task | Ability | Key Parameters |
|------|---------|----------------|
| First call (always) | `get-agent-context` | — |
| Issues summary | `get-issues` | — |
| Full report | `get-report` | `format`: markdown/json |
| Single section | `get-section` | `section`: database, security, etc. |
| PHP errors | `get-error-log` | `lines`: 100-10000 |
| Debug state | `get-debug-status` | — |
| Toggle debug | `toggle-debug` | `enable`: true/false |
