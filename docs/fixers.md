# Fixers

WP System Report ships with four automated fixers that detect and remediate common WordPress site issues. Fixers are read-only diagnosis tools first and repair tools second: each fixer checks whether a problem exists before making any changes, and every operation returns a structured result with before/after state snapshots.

Fixers are only available when the plugin's fixer feature flag is active. The feature availability is tested via `Features::has_fixers()` before any fixer endpoint or command is exposed.

---

## How Fixers Work

### Lifecycle of a Fix

Every fixer follows the same execution lifecycle:

1. **Check applicability** — `can_fix()` runs a lightweight, read-only scan. If no issue is detected, the fixer reports success immediately without modifying anything.
2. **Capture before-state** — the fixer records a snapshot of the affected system state before any change is made.
3. **Apply the fix** — the fixer modifies only the specific items identified in step 1.
4. **Capture after-state** — a second snapshot records the system state post-modification.
5. **Return a `Fix_Result`** — an immutable value object encapsulating success/failure, a human-readable message, and the before/after snapshots.

### The `Fixer` Interface

All fixers implement `SystemReport\Fixer`:

| Method | Returns | Description |
|--------|---------|-------------|
| `get_id()` | `string` | Unique slug (e.g., `autoload_optimizer`) |
| `get_label()` | `string` | Translated display name |
| `get_description()` | `string` | Plain-language description of what the fixer does |
| `get_risk_level()` | `Risk_Level` | `Low`, `Medium`, or `High` enum case |
| `get_category()` | `string` | Category slug (`performance`, `security`, `database`, `cron`) |
| `can_fix()` | `bool` | `true` if an issue exists and can be fixed right now |
| `fix()` | `Fix_Result` | Execute the fix; must not throw exceptions |

### Risk Levels

Risk level is expressed as the `SystemReport\Risk_Level` backed enum:

| Level | Value | Confirmation Required | Meaning |
|-------|-------|-----------------------|---------|
| `Low` | `low` | No | Safe, easily reversible operation |
| `Medium` | `medium` | Yes | Requires caution; a backup is recommended |
| `High` | `high` | Yes | Potentially destructive; requires explicit confirmation |

Any fixer with `Medium` or `High` risk returns `Risk_Level::requires_confirmation() === true`. Attempting to run a medium or high risk fixer without passing `confirmed=true` is rejected by both the REST API and the Abilities API.

---

## Fix_Result Structure

`SystemReport\Fix_Result` is an immutable value object implementing `JsonSerializable`. It is returned directly by `fix()` and serialised automatically by REST endpoints.

### Properties

| Property | Type | Always Present | Description |
|----------|------|----------------|-------------|
| `success` | `bool` | Yes | `true` on full success, `false` if the operation partially or fully failed |
| `message` | `string` | Yes | Human-readable summary of the outcome |
| `before` | `array` | When applicable | Snapshot of the system state before the fix was applied |
| `after` | `array` | When applicable | Snapshot of the system state after the fix was applied |
| `errors` | `array` | On failure | Detailed per-item error strings |

### Factory Methods

```php
// Successful result with optional before/after snapshots.
Fix_Result::success( string $message, array $before = [], array $after = [] );

// Failed result with optional error detail array.
Fix_Result::failure( string $message, array $errors = [] );
```

### JSON Serialisation

`to_array()` / `jsonSerialize()` omit `before`, `after`, and `errors` when they are empty, keeping responses compact for the common case where a fix has no changes to report.

**Example — successful autoload optimisation:**

```json
{
  "success": true,
  "message": "Successfully disabled autoload for 3 oversized option(s).",
  "before": {
    "total_autoload_size": 4194304,
    "bloated_count": 3,
    "bloated_options": {
      "woocommerce_product_data": 524288,
      "acf_pro_license": 131072,
      "elementor_data_cache": 262144
    }
  },
  "after": {
    "total_autoload_size": 3276800,
    "optimized_count": 3,
    "optimized_options": [
      "woocommerce_product_data",
      "acf_pro_license",
      "elementor_data_cache"
    ]
  }
}
```

**Example — failed result:**

```json
{
  "success": false,
  "message": "Partially completed: 2 option(s) optimized, 1 failed.",
  "errors": [
    "Failed to update autoload for: some_locked_option"
  ]
}
```

---

## Available Fixers

### Autoload Optimizer

**ID:** `autoload_optimizer`
**Label:** Autoload Optimizer
**Category:** `performance`
**Risk Level:** Medium (confirmation required)

#### What It Fixes

WordPress loads every option flagged `autoload = 'yes'` on every page request. Large cached values, serialised plugin settings, or stale transient data left behind in `wp_options` with autoload enabled can significantly increase memory usage and slow response times. This fixer switches oversized options to `autoload = 'no'` so WordPress loads them on demand instead.

#### Detection Logic (`can_fix`)

Queries `wp_options` for any row where `autoload IN ('yes', 'on')` and `LENGTH(option_value) >= threshold`. Returns `true` if at least one non-protected match is found.

#### Threshold

The default threshold is **100 KB** per option value. It is filterable:

```php
add_filter( 'wp_system_report_autoload_threshold', function ( int $threshold ): int {
    return 50 * 1024; // Lower to 50 KB.
} );
```

#### Protected Options

The fixer maintains a hard-coded list of WordPress core options that must remain autoloaded (e.g., `siteurl`, `home`, `active_plugins`, `template`, `stylesheet`, `rewrite_rules`, and ~60 others). These are never modified regardless of their size.

Third-party options can also be protected via filter:

```php
add_filter( 'wp_system_report_autoload_protected', function ( bool $protected, string $option_name ): bool {
    if ( 'my_plugin_critical_cache' === $option_name ) {
        return true;
    }
    return $protected;
}, 10, 2 );
```

#### What It Modifies

- Sets `autoload = 'no'` in `wp_options` for each identified oversized, non-protected option.
- Calls `wp_cache_delete( 'alloptions', 'options' )` after the batch update to ensure WordPress picks up the changes immediately.

#### Before/After Snapshot Fields

| Key | Description |
|-----|-------------|
| `total_autoload_size` | Total bytes of all autoloaded option values |
| `bloated_count` (before) | Number of options exceeding the threshold |
| `bloated_options` (before) | Associative array of `option_name => size_in_bytes` |
| `optimized_count` (after) | Number of options successfully updated |
| `optimized_options` (after) | Array of option names that were updated |

---

### Security Hardener

**ID:** `security_hardener`
**Label:** Security Hardener
**Category:** `security`
**Risk Level:** Medium (confirmation required)

#### What It Fixes

Detects and remediates three common WordPress security misconfigurations:

1. **XML-RPC enabled** — XML-RPC is a legacy remote-procedure endpoint that is frequently targeted by brute-force and amplification attacks. Disabling it reduces the attack surface for sites that do not need remote publishing or Jetpack.
2. **File editor not disabled** — The built-in theme and plugin editor in `wp-admin` allows code injection if an administrator account is compromised. Adding `DISALLOW_FILE_EDIT` to `wp-config.php` closes this vector.
3. **Missing security headers** — The following HTTP response headers are recommended to defend against clickjacking, MIME-type sniffing, and referrer leakage:

| Header | Value |
|--------|-------|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |

#### Detection Logic (`can_fix`)

Returns `true` when at least one of the following is true:
- The `xmlrpc_enabled` WordPress filter has not been forced to `false` by this fixer's stored option.
- `DISALLOW_FILE_EDIT` is not defined or is `false`.
- At least one of the three recommended headers is not yet recorded in the stored option.

#### What It Modifies

All changes are stored in the `sr_security_hardening` WordPress option (with `autoload = false`) and applied at runtime from a hook registered during plugin bootstrap.

| Measure | Mechanism | Reversible |
|---------|-----------|------------|
| Disable XML-RPC | Sets `sr_security_hardening['xmlrpc_disabled'] = true`; adds `add_filter( 'xmlrpc_enabled', '__return_false' )` at runtime | Yes — delete or reset the option key |
| Security headers | Stores header name/value pairs in `sr_security_hardening['security_headers']`; sends via `send_headers` action at runtime | Yes — delete or reset the option key |
| Disable file editor | **Advisory only** — the fixer cannot set `DISALLOW_FILE_EDIT` at runtime because it is a PHP constant. The result message instructs the administrator to add `define( 'DISALLOW_FILE_EDIT', true );` to `wp-config.php` manually. | N/A |

#### Runtime Header Safety

Header names are validated against an explicit allowlist before being sent. Any header whose name or value contains carriage-return or line-feed characters is silently skipped to prevent HTTP response-splitting attacks. Only the following header names are ever emitted by the hardener: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Strict-Transport-Security`, `Permissions-Policy`.

#### Before/After Snapshot Fields

| Key | Description |
|-----|-------------|
| `xmlrpc_enabled` | Whether XML-RPC was enabled before/after |
| `file_editor_disabled` | Whether `DISALLOW_FILE_EDIT` is set |
| `missing_headers` | Boolean — whether any recommended headers were absent |
| `missing_headers_list` | Associative array of absent header names and their recommended values |
| `hardening_options` | Raw contents of the `sr_security_hardening` option |

---

### Database Optimizer

**ID:** `database_optimizer`
**Label:** Database Optimizer
**Category:** `database`
**Risk Level:** Low (no confirmation required)

#### What It Fixes

Two types of database bloat are addressed:

1. **Expired transients** — WordPress stores transients as rows in `wp_options`. Expired transients are not always cleaned up promptly, leaving orphaned data that inflates table size and slows option queries. The fixer deletes matched `_transient_timeout_*` rows whose stored timestamp has passed, along with their paired `_transient_*` data rows.
2. **Table overhead (fragmentation)** — As rows are inserted, updated, and deleted over time, MySQL/MariaDB tables accumulate `DATA_FREE` (unreclaimed space). The fixer runs `OPTIMIZE TABLE` on affected tables to reclaim this space.

#### Detection Logic (`can_fix`)

Returns `true` when either:
- At least one expired transient timeout row exists in `wp_options` (timeout value less than `time()`).
- The sum of `DATA_FREE` across all database tables is greater than zero.

#### Table Scope

Only tables whose name starts with `$wpdb->prefix` are candidates for optimisation. Tables belonging to other applications sharing the same MySQL database are never touched.

#### Per-Run Limit

To avoid holding write locks for excessive durations, each run optimises a maximum of **20 tables**, selected in descending order of `DATA_FREE` (most wasteful first). If the site has more than 20 tables with overhead, subsequent runs will optimise the remaining tables.

#### Configurable Overhead Threshold

A table must have at least 1 MB of `DATA_FREE` to be included. This is filterable:

```php
add_filter( 'wp_system_report_optimize_overhead_threshold', function ( int $threshold ): int {
    return 5 * MB_IN_BYTES; // Only optimise tables with 5+ MB overhead.
} );
```

#### Before/After Snapshot Fields

| Key | Description |
|-----|-------------|
| `expired_transients` | Count of expired transient timeout rows |
| `total_overhead` | Total `DATA_FREE` in bytes across all tables |
| `tables_with_waste` (before) | Count of tables with overhead above the threshold |
| `transients_deleted` (after) | Number of transient pairs deleted (one count = one timeout + one data row) |
| `tables_optimized` (after) | Number of tables successfully optimised |
| `tables_skipped` (after) | Number of tables deferred to the next run due to the per-run limit |

---

### Cron Repair

**ID:** `cron_repair`
**Label:** Cron Repair
**Category:** `cron`
**Risk Level:** Medium (confirmation required)

#### What It Fixes

Detects and remediates three categories of WP-Cron malfunction:

1. **Stuck cron lock** — WordPress sets a `doing_cron` transient when cron begins executing. If cron crashes or times out, this transient persists and blocks future cron runs. The fixer deletes a `doing_cron` lock that is older than **10 minutes** (600 seconds).
2. **Orphaned events** — A cron event is orphaned when its hook name has no registered PHP callback (`has_action( $hook ) === false`). This typically happens when a plugin is deactivated or removed without calling `wp_clear_scheduled_hook()`. Orphaned events accumulate silently and are never executed. The fixer calls `wp_unschedule_event()` for every orphaned event it finds.
3. **Overdue recurring events** — A recurring event is overdue when its scheduled timestamp is more than **5 minutes** (300 seconds) in the past. This grace period prevents rescheduling events that are only a few seconds late due to normal timing jitter. Overdue recurring events are unscheduled from their stale timestamp and rescheduled to `time() + interval` so they resume on their original cadence.

#### Detection Logic (`can_fix`)

Returns `true` when at least one of the following conditions holds:
- The `doing_cron` transient is set and older than 10 minutes.
- At least one scheduled hook has no registered PHP callback (excluding core hooks).
- At least one recurring event has a scheduled time more than 5 minutes in the past.

#### Orphan Detection — Core Hook Exclusion

WordPress core cron hooks may be registered conditionally or late in the bootstrap process, so they are excluded from orphan detection:

`wp_version_check`, `wp_update_plugins`, `wp_update_themes`, `wp_scheduled_delete`, `wp_scheduled_auto_draft_delete`, `wp_privacy_delete_old_export_files`, `delete_expired_transients`, `recovery_mode_clean_expired_keys`, `wp_site_health_scheduled_check`, `wp_https_detection`, `wp_delete_temp_updater_backups`

This list is filterable:

```php
add_filter( 'wp_system_report_core_cron_hooks', function ( array $core_hooks ): array {
    $core_hooks[] = 'my_plugin_considered_core_hook';
    return $core_hooks;
} );
```

#### Before/After Snapshot Fields

| Key | Description |
|-----|-------------|
| `total_events` | Total number of scheduled cron events |
| `has_stuck_lock` | Whether a stuck `doing_cron` lock was detected |
| `lock_age` | Age of the cron lock in seconds (`null` if no lock exists) |
| `orphaned_events` | Associative array of `hook_name => event_count` for orphaned hooks |
| `orphaned_count` | Total number of orphaned hooks |
| `overdue_recurring` | Array of overdue event objects (hook, timestamp, schedule, interval, overdue_by) |
| `overdue_count` | Total number of overdue recurring events |

---

## Fixer Registry

All fixers are registered in `SystemReport\Fixer_Registry`. The registry stores fixers keyed by their ID and exposes them via the `wp_system_report_fixers` filter, which allows third-party code to add, replace, or remove fixers:

```php
add_filter( 'wp_system_report_fixers', function ( array $fixers ): array {
    $fixers['my_custom_fixer'] = new My_Custom_Fixer();
    return $fixers;
} );
```

If a fixer is registered with an ID that is already in use, `_doing_it_wrong()` is triggered and the existing fixer is replaced by the new instance.

### Registry Methods

| Method | Signature | Description |
|--------|-----------|-------------|
| `register` | `register( Fixer $fixer ): void` | Add a fixer to the registry |
| `get` | `get( string $id ): ?Fixer` | Retrieve a fixer by ID; `null` if not found |
| `get_all` | `get_all(): Fixer[]` | All registered fixers (applies the filter) |
| `get_by_category` | `get_by_category( string $category ): Fixer[]` | Fixers filtered by category slug |
| `has` | `has( string $id ): bool` | Check whether a fixer ID is registered |

---

## How Fixers Are Triggered

### REST API

The `Fixer_Controller` registers two endpoints under the `wp-system-report/v1` namespace. Both require the `manage_options` capability (filterable via `wp_system_report_capability`).

#### List All Fixers

```
GET /wp-json/wp-system-report/v1/fixes
```

Optional query parameter `category` filters by category slug (e.g., `?category=performance`).

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": "autoload_optimizer",
      "label": "Autoload Optimizer",
      "description": "Disables autoload for oversized options to reduce memory usage on every page load.",
      "category": "performance",
      "risk_level": "medium",
      "risk_label": "Medium",
      "requires_confirmation": true,
      "can_fix": true
    }
  ],
  "meta": { "total": 4 }
}
```

#### Execute a Fixer

```
POST /wp-json/wp-system-report/v1/fixes/{fix_id}
```

**Body parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `confirmed` | `boolean` | `false` | Required for `Medium` and `High` risk fixers |

**Confirmation guard:** Sending a POST for a medium or high risk fixer without `confirmed: true` returns a `409 Conflict` error:

```json
{
  "code": "wp_system_report_confirmation_required",
  "message": "Fixer \"autoload_optimizer\" has Medium risk and requires explicit confirmation. Resend with confirmed=true."
}
```

**Successful response:**

```json
{
  "success": true,
  "data": {
    "fix_id": "database_optimizer",
    "result": {
      "success": true,
      "message": "5 expired transient(s) deleted, 2 table(s) optimized.",
      "before": { "expired_transients": 5, "total_overhead": 2097152, "tables_with_waste": 2 },
      "after": { "expired_transients": 0, "total_overhead": 0, "transients_deleted": 5, "tables_optimized": 2, "tables_skipped": 0 }
    },
    "applied": true
  }
}
```

When `can_fix()` returns `false`, the response still has `success: true` but `applied` is `false`:

```json
{
  "success": true,
  "data": {
    "fix_id": "database_optimizer",
    "result": { "success": true, "message": "No issues detected. Nothing to fix." },
    "applied": false
  }
}
```

### WP-CLI

Fixers are accessible from the `wp system-report` command group.

#### List Fixers

```bash
wp system-report fixes
wp system-report fixes --format=json
```

Output columns: `id`, `label`, `risk`, `has_issues`, `description`.

#### Run a Fixer

```bash
wp system-report fix <fix_id> [--dry-run] [--yes]
```

| Flag | Description |
|------|-------------|
| `--dry-run` | Checks whether issues are present without making any changes; exits with a success message if applicable |
| `--yes` | Skips the interactive `Are you sure?` confirmation prompt |

**Examples:**

```bash
# Check whether issues exist without changing anything.
wp system-report fix autoload_optimizer --dry-run

# Run the fixer; prompt for confirmation interactively.
wp system-report fix security_hardener

# Run the fixer and skip the confirmation prompt.
wp system-report fix cron_repair --yes
```

The CLI always prints the fixer label, description, and risk level before executing. After execution, before/after snapshots and any errors are printed to stdout.

### Abilities API

When the WordPress Abilities API is available (detected by the presence of `wp_register_ability()` and `wp_register_ability_category()`), the plugin registers two fixer-related abilities under the `wp-system-report` category. These abilities can be discovered and invoked by AI agents via the MCP Adapter plugin or any other Abilities API client.

#### `wp-system-report/list-fixes`

Lists all available fixers with their IDs, labels, descriptions, risk levels, categories, and current applicability. This is a read-only, idempotent operation.

**Input:** none

**Output:** Array of fixer objects (same shape as the REST list endpoint).

#### `wp-system-report/run-fix`

Executes a specific fixer. This ability is annotated as `destructive: true` and `idempotent: false`.

**Input:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `fix_id` | `string` | Yes | The unique identifier of the fixer to run |
| `confirmed` | `boolean` | No (default `false`) | Required for `Medium` and `High` risk fixers |

**Output:** The `Fix_Result` array (success, message, before, after).

Both abilities require the `manage_options` capability (filterable via `wp_system_report_capability`).

---

## Safety Features

### Confirmation Guard

Medium and high risk operations require explicit opt-in confirmation in all three invocation paths:

- **REST API**: POST body must include `"confirmed": true`.
- **WP-CLI**: Interactive prompt unless `--yes` flag is provided.
- **Abilities API**: Input must include `"confirmed": true`.

The fixer code itself does not enforce the confirmation requirement — that is the responsibility of each controller layer. This separation means a fixer can be tested directly in PHP without needing to work around a confirmation gate.

### Dry-Run Support (WP-CLI)

The `--dry-run` flag calls `can_fix()` but not `fix()`. No modifications are made to the database or configuration. The CLI reports whether issues exist so that the operator can make an informed decision.

### Protected Options (Autoload Optimizer)

The autoload optimizer maintains a hard-coded list of WordPress core options that are never modified regardless of their size. This prevents the fixer from inadvertently breaking the WordPress bootstrap sequence by disabling autoload on options that must be available on every request.

### Per-Run Table Limit (Database Optimizer)

The database optimizer processes at most 20 tables per run to bound the total time that `OPTIMIZE TABLE` write locks are held. Tables are sorted by `DATA_FREE` descending so the most impactful optimisations happen first. Remaining tables are processed on subsequent runs.

### Lock-Age Threshold (Cron Repair)

The cron repair fixer only clears a `doing_cron` lock when it is more than 600 seconds (10 minutes) old. WordPress core uses a 60-second lock timeout, so a 10-minute-old lock is unambiguously stale. This threshold prevents the fixer from interrupting a legitimately running cron process.

### Overdue-Event Grace Period (Cron Repair)

Recurring events are only rescheduled when they are more than 300 seconds (5 minutes) overdue. This grace period absorbs normal timing jitter from traffic-triggered cron execution and avoids rescheduling events that simply fired a few seconds late.

### Non-Throwing Contract

The `Fixer` interface contract requires that `fix()` must not throw exceptions. All error conditions must be expressed by returning `Fix_Result::failure()`. This ensures that a fixer error cannot surface as an unhandled exception in the REST response or CLI output.

---

## Writing a Custom Fixer

### Step 1: Implement the Interface

```php
<?php

namespace My_Plugin;

use SystemReport\Fixer;
use SystemReport\Fix_Result;
use SystemReport\Risk_Level;

class Debug_Mode_Disabler implements Fixer {

    public function get_id(): string {
        return 'debug_mode_disabler';
    }

    public function get_label(): string {
        return __( 'Debug Mode Disabler', 'my-plugin' );
    }

    public function get_description(): string {
        return __( 'Disables WP_DEBUG_DISPLAY on production to hide errors from visitors.', 'my-plugin' );
    }

    public function get_risk_level(): Risk_Level {
        return Risk_Level::Low;
    }

    public function get_category(): string {
        return 'security';
    }

    public function can_fix(): bool {
        return defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
    }

    public function fix(): Fix_Result {
        if ( ! $this->can_fix() ) {
            return Fix_Result::success(
                __( 'WP_DEBUG_DISPLAY is already disabled.', 'my-plugin' )
            );
        }

        $before = array( 'WP_DEBUG_DISPLAY' => WP_DEBUG_DISPLAY );

        // Apply the change — example stores an advisory option.
        update_option( 'my_plugin_debug_advisory', true );

        $after = array( 'advisory_stored' => true );

        return Fix_Result::success(
            __( 'Advisory stored. Add define( "WP_DEBUG_DISPLAY", false ) to wp-config.php.', 'my-plugin' ),
            $before,
            $after
        );
    }
}
```

### Step 2: Register the Fixer

```php
add_filter( 'wp_system_report_fixers', function ( array $fixers ): array {
    $fixers['debug_mode_disabler'] = new My_Plugin\Debug_Mode_Disabler();
    return $fixers;
} );
```

The filter receives the complete array of `Fixer` instances keyed by ID. Add, replace, or remove fixers as needed. To remove a built-in fixer:

```php
add_filter( 'wp_system_report_fixers', function ( array $fixers ): array {
    unset( $fixers['autoload_optimizer'] );
    return $fixers;
} );
```

### Implementation Guidelines

- `can_fix()` should be fast and read-only. It is called during list operations and before every execution.
- `fix()` must never throw an exception. Return `Fix_Result::failure()` with a descriptive message and errors array on failure.
- Use `Fix_Result::success()` with non-empty `$before` and `$after` arrays whenever state changes are made, so operators can verify the outcome.
- For partial failures (some items fixed, some not), return `Fix_Result::failure()` with the errors array. A partial failure is still a failure from the perspective of the result status.
- Use WordPress functions (`update_option`, `wp_unschedule_event`, etc.) rather than direct SQL wherever possible to respect WordPress's internal caching layers.
- Prefix any option keys or transients the fixer creates to avoid collision with other plugins.
