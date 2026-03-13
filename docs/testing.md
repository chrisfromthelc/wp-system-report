# Testing Guide

This guide covers how to write PHPUnit tests for custom collectors and fixers that extend WP System Report. It documents the test infrastructure, patterns used throughout the existing test suite, and provides a complete worked example.

---

## Prerequisites

- PHP 8.1 or higher (required for the `Status` backed enum and named arguments)
- Composer dependencies installed (`composer install`)
- A WordPress test library available at `WP_TESTS_DIR` or `/tmp/wordpress-tests-lib`
- MySQL 8.0+ for the database-backed test environment

---

## Test Infrastructure

### Directory Structure

```
tests/
├── bootstrap.php           # Test environment bootstrap
├── CollectorsTest.php       # Structural contract tests for all registered collectors
├── FixersTest.php           # Fixer infrastructure and per-fixer tests
└── collectors/
    ├── SecurityTest.php
    ├── NetworkConnectivityTest.php
    ├── PerformanceTest.php
    └── ...                  # One file per collector
```

Place collector tests in `tests/collectors/`. The name must match the pattern `{DescriptiveName}Test.php`. PHPUnit discovers all files matching `*Test.php` recursively from the `tests/` directory as configured in `phpunit.xml.dist`.

### File Naming

| What you are testing | File name |
|---|---|
| Custom collector `My_Hosting` | `tests/collectors/MyHostingTest.php` |
| Custom fixer `Cache_Purger` | Extend `tests/FixersTest.php` or create `tests/CachePurgerTest.php` |

### Test Configuration

`phpunit.xml.dist` at the project root sets the bootstrap and discovery path:

```xml
<phpunit bootstrap="tests/bootstrap.php" colors="true">
    <testsuites>
        <testsuite name="WP System Report Test Suite">
            <directory suffix="Test.php">./tests/</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### How the Bootstrap Works

`tests/bootstrap.php` performs three tasks:

1. Loads the Composer autoloader so plugin classes are available.
2. Resolves the WordPress test library path from the `WP_TESTS_DIR` or `WP_DEVELOP_DIR` environment variable, falling back to `/tmp/wordpress-tests-lib`.
3. Bootstraps the full WordPress testing environment, which provides `WP_UnitTestCase`, a real in-memory database with transaction rollback per test, and all WordPress functions.

It also calls `\SystemReport\Report_History::create_table()` directly, because the `register_activation_hook` callback that normally creates the table does not fire during the test bootstrap sequence.

```php
// tests/bootstrap.php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
    require dirname( __DIR__ ) . '/wp-system-report.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

\SystemReport\Report_History::create_table();
```

### Base Class

All tests extend `WP_UnitTestCase`. This provides:

- Automatic database transaction rollback after each test, so `update_option()`, `wp_insert_post()`, and similar calls do not persist between tests.
- The `set_up()` / `tear_down()` lifecycle (note: lowercase, not `setUp` / `tearDown`).
- All standard PHPUnit assertions plus WordPress-specific helpers.

```php
class My_Collector_Test extends WP_UnitTestCase {

    private \My_Plugin\Collectors\My_Collector $collector;

    public function set_up(): void {
        parent::set_up();
        $this->collector = new \My_Plugin\Collectors\My_Collector();
    }

    public function tear_down(): void {
        // Remove filters, delete transients, or reset options set during the test.
        remove_all_filters( 'my_custom_filter' );
        delete_transient( 'sr_my_collector' );
        parent::tear_down();
    }
}
```

Always call `parent::set_up()` first and `parent::tear_down()` last to preserve the transaction rollback and WordPress state management.

---

## Testing Collectors

### Instantiation

Instantiate the collector directly. No factory or registry is needed for unit tests:

```php
$collector = new \My_Plugin\Collectors\My_Hosting_Collector();
```

### Calling collect()

`collect()` returns a `Field[]` array. Every element is an instance of `SystemReport\Field`:

```php
$fields = $collector->collect();

$this->assertIsArray( $fields );
$this->assertNotEmpty( $fields );

foreach ( $fields as $field ) {
    $this->assertInstanceOf( \SystemReport\Field::class, $field );
}
```

### Asserting Field Properties

`Field` implements `ArrayAccess`, so you can access properties either as object properties or as array keys. Both styles are valid in tests, but prefer the typed property access for clarity:

```php
// Object property access (preferred).
$this->assertSame( 'My Label', $field->label );
$this->assertSame( '42', $field->value );
$this->assertSame( \SystemReport\Status::Good, $field->status );
$this->assertTrue( $field->private );
$this->assertSame( 42, $field->debug ); // raw value, may differ from value

// Array key access (backward-compatible; 'status' returns the string value).
$this->assertSame( 'good', $field['status'] );
$this->assertSame( 'My Label', $field['label'] );
```

Note that `$field['status']` returns the string backing value (`'good'`, `'warning'`, `'critical'`, `'info'`), while `$field->status` returns the `Status` enum case. For assertions that need only the string, use `Field::get_status_string()`:

```php
$this->assertSame( 'warning', \SystemReport\Field::get_status_string( $field ) );
```

This helper accepts both `Field` objects and legacy associative arrays, making it safe to use in shared assertion logic that may encounter either form.

### Status Enum

The `Status` enum is a PHP 8.1 backed string enum with four cases:

| Case | String value | Meaning |
|---|---|---|
| `Status::Good` | `'good'` | No action needed |
| `Status::Warning` | `'warning'` | Should be reviewed |
| `Status::Critical` | `'critical'` | Requires immediate attention |
| `Status::Info` | `'info'` | Informational only |

Assert the enum case directly for type safety:

```php
$this->assertSame( \SystemReport\Status::Good, $field->status );
```

To verify that every field in the output has a valid status:

```php
$valid_cases = \SystemReport\Status::cases();

foreach ( $fields as $field ) {
    $this->assertContains(
        $field->status,
        $valid_cases,
        "Field '{$field->label}' must carry a valid Status enum case."
    );
}
```

### Finding a Field by Label

The `find_field_by_label()` helper pattern appears in every per-collector test file. Add it as a private method:

```php
private function find_field_by_label( array $fields, string $label ): ?\SystemReport\Field {
    foreach ( $fields as $field ) {
        if ( $field instanceof \SystemReport\Field && $label === $field->label ) {
            return $field;
        }
    }
    return null;
}
```

Usage:

```php
$field = $this->find_field_by_label( $this->collector->collect(), 'PHP Version' );

$this->assertNotNull( $field, '"PHP Version" field should be present.' );
$this->assertSame( phpversion(), $field->value );
```

### Testing Status Determination Logic

Use `update_option()` or other WordPress state-setters to drive the collector into each status branch, then assert the resulting status:

```php
public function test_memory_limit_warning_below_threshold(): void {
    // Simulate a low memory limit by filtering the ini value.
    add_filter( 'option_memory_limit_override', function () {
        return '32M';
    } );

    $fields = $this->collector->collect();
    $field  = $this->find_field_by_label( $fields, 'PHP Memory Limit' );

    $this->assertSame( \SystemReport\Status::Warning, $field->status );

    remove_all_filters( 'option_memory_limit_override' );
}
```

---

## Mocking Strategies

Because collectors run inside a full WordPress environment, most dependencies can be controlled without PHP mocking frameworks. The following table covers the common cases:

| Dependency | Approach | Notes |
|---|---|---|
| WP Options | `update_option()` / `delete_option()` | Rolled back automatically by `WP_UnitTestCase` |
| WP Filters | `add_filter()` / `remove_all_filters()` | Always clean up in `tear_down()` |
| HTTP Requests | `pre_http_request` filter | Return an array or `WP_Error` to short-circuit `wp_remote_get()` |
| Database | `$wpdb->query()` / `$wpdb->replace()` | Use prepared statements; annotate with PHPCS ignore |
| PHP Constants | `@runInSeparateProcess` annotation | Required because constants cannot be redefined in a shared process |
| Filesystem | `sys_get_temp_dir()` + `chmod()` | Create real temp directories; clean up in `tear_down()` |
| Transients | `set_transient()` / `delete_transient()` | Delete in both `set_up()` and `tear_down()` to prevent pollution |

### WP Options

```php
public function test_debug_mode_warning_when_enabled(): void {
    // update_option() is rolled back after each test by WP_UnitTestCase.
    update_option( 'my_plugin_debug_mode', '1' );

    $fields = $this->collector->collect();
    $field  = $this->find_field_by_label( $fields, 'Debug Mode' );

    $this->assertSame( \SystemReport\Status::Warning, $field->status );
}
```

### WP Filters

```php
public function test_threshold_filter_changes_status(): void {
    add_filter( 'wp_system_report_autoload_threshold', function (): int {
        return 200 * 1024;
    } );

    $fixer = new \SystemReport\Fixers\Autoload_Optimizer();
    $this->assertSame( 200 * 1024, $fixer->get_threshold() );

    // Always remove filters so they do not leak into other tests.
    remove_all_filters( 'wp_system_report_autoload_threshold' );
}
```

### HTTP Requests

Use the `pre_http_request` filter to intercept any call to `wp_remote_get()`, `wp_remote_post()`, or `wp_safe_remote_get()`. The filter receives `( $preempt, $args, $url )` and should return either a response array or a `WP_Error`.

**Successful response:**

```php
$mock_http = static function ( $preempt, $args, $url ) {
    if ( false !== strpos( $url, 'api.wordpress.org' ) ) {
        return array(
            'response' => array( 'code' => 200, 'message' => 'OK' ),
            'body'     => '{"offers":[]}',
            'headers'  => array(),
            'cookies'  => array(),
        );
    }
    return $preempt;
};

add_filter( 'pre_http_request', $mock_http, 10, 3 );

$fields = $this->collector->collect();

remove_filter( 'pre_http_request', $mock_http, 10 );
```

**Failed response:**

```php
$mock_http = static function ( $preempt, $args, $url ) {
    if ( false !== strpos( $url, 'api.wordpress.org' ) ) {
        return new \WP_Error( 'http_request_failed', 'Connection timed out' );
    }
    return $preempt;
};

add_filter( 'pre_http_request', $mock_http, 10, 3 );

$fields = $this->collector->collect();

remove_filter( 'pre_http_request', $mock_http, 10 );

$field = $this->find_field_by_label( $fields, 'WordPress.org API' );
$this->assertSame( \SystemReport\Status::Critical, $field->status );
```

Always cache-bust the collector transient before the test and remove the filter after `collect()` to isolate the mock.

### Database

Use `$wpdb` directly for setup that the WordPress Options API cannot express, such as creating rows with specific `autoload` values or fabricating expired transients:

```php
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test setup.
$wpdb->replace(
    $wpdb->options,
    array(
        'option_name'  => '_transient_timeout_sr_test_key',
        'option_value' => (string) ( time() - 3600 ), // Already expired.
        'autoload'     => 'no',
    )
);

wp_cache_flush(); // Clear the object cache so the fixer sees the change.
```

Add PHPCS ignore annotations with a brief justification. `WP_UnitTestCase` wraps each test in a database transaction, so these rows will be rolled back automatically.

### PHP Constants

PHP constants cannot be undefined once set, so tests that depend on a constant taking a specific value must run in an isolated process:

```php
/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
public function test_disallow_file_edit_reports_good(): void {
    define( 'DISALLOW_FILE_EDIT', true );

    $fields = $this->collector->collect();
    $field  = $this->find_field_by_label( $fields, 'File Editing Disabled' );

    $this->assertSame( \SystemReport\Status::Good, $field->status );
}
```

When a constant is already set in the bootstrap (for example, `WP_DEBUG`), test both the defined and undefined paths by writing one test with `@runInSeparateProcess` that defines it, and one test without the annotation that relies on the bootstrap default (undefined). Use `markTestSkipped()` when the environment has already committed to a state that makes a branch unreachable:

```php
public function test_proxy_not_configured(): void {
    if ( defined( 'WP_PROXY_HOST' ) && '' !== WP_PROXY_HOST ) {
        $this->markTestSkipped( 'WP_PROXY_HOST is defined in this environment.' );
    }

    $fields = $this->collector->collect();
    $field  = $this->find_field_by_label( $fields, 'HTTP Proxy' );
    $this->assertStringContainsString( 'Not configured', $field->value );
}
```

### Filesystem

Create real temporary directories using PHP's filesystem functions:

```php
private string $temp_dir;

public function set_up(): void {
    parent::set_up();
    $this->temp_dir = sys_get_temp_dir() . '/sr_test_' . uniqid();
    mkdir( $this->temp_dir, 0755, true );
}

public function tear_down(): void {
    // Remove the directory and any files created during the test.
    array_map( 'unlink', glob( $this->temp_dir . '/*' ) );
    rmdir( $this->temp_dir );
    parent::tear_down();
}

public function test_unwritable_upload_dir_reports_critical(): void {
    chmod( $this->temp_dir, 0444 ); // Remove write permission.

    // Pass the temp dir to the collector via a filter or direct constructor argument.
    add_filter( 'upload_dir', function ( $dirs ) {
        $dirs['basedir'] = $this->temp_dir;
        $dirs['path']    = $this->temp_dir;
        return $dirs;
    } );

    $fields = $this->collector->collect();
    $field  = $this->find_field_by_label( $fields, 'Upload Dir Writable' );

    $this->assertSame( \SystemReport\Status::Critical, $field->status );

    remove_all_filters( 'upload_dir' );
    chmod( $this->temp_dir, 0755 ); // Restore before rmdir in tear_down().
}
```

### Transients

Clear the collector's transient in both `set_up()` and `tear_down()` to avoid stale cache data leaking between tests:

```php
public function set_up(): void {
    parent::set_up();
    delete_transient( 'sr_my_collector' );
    $this->collector = new \My_Plugin\Collectors\My_Collector();
}

public function tear_down(): void {
    delete_transient( 'sr_my_collector' );
    parent::tear_down();
}
```

To test the caching behavior itself:

```php
public function test_caching_stores_and_returns_same_data(): void {
    delete_transient( 'sr_my_collector' );

    $first = $this->collector->get_cached_data();

    $this->assertNotFalse(
        get_transient( 'sr_my_collector' ),
        'Transient must be set after the first get_cached_data() call.'
    );

    $second = $this->collector->get_cached_data();

    $this->assertEquals(
        $first,
        $second,
        'Second call must return the same data as the first.'
    );
}
```

---

## Testing Fixers

### Structure

Fixer tests follow the same `WP_UnitTestCase` structure as collector tests. The gold standard is `tests/FixersTest.php`.

### Testing can_fix()

`can_fix()` returns `true` if the fixer has work to do. Set up the expected state with WordPress functions, then assert:

```php
public function test_can_fix_returns_true_when_bloated_option_exists(): void {
    update_option( 'sr_test_bloated', str_repeat( 'x', 150 * 1024 ), 'yes' );

    $fixer = new \SystemReport\Fixers\Autoload_Optimizer();
    $this->assertTrue( $fixer->can_fix() );

    delete_option( 'sr_test_bloated' );
}

public function test_can_fix_returns_false_when_clean(): void {
    $fixer = new \SystemReport\Fixers\Autoload_Optimizer();
    $this->assertFalse( $fixer->can_fix() );
}
```

### Testing fix()

`fix()` returns a `Fix_Result` value object. Assert `success`, `message`, `before`, `after`, and `errors`:

```php
public function test_fix_returns_success_result(): void {
    update_option( 'sr_test_large', str_repeat( 'y', 150 * 1024 ), 'yes' );

    $fixer  = new \SystemReport\Fixers\Autoload_Optimizer();
    $result = $fixer->fix();

    $this->assertInstanceOf( \SystemReport\Fix_Result::class, $result );
    $this->assertTrue( $result->success );
    $this->assertNotEmpty( $result->message );
    $this->assertNotEmpty( $result->before );
    $this->assertNotEmpty( $result->after );
    $this->assertSame( array(), $result->errors );

    delete_option( 'sr_test_large' );
}
```

### Fix_Result Properties

| Property | Type | Description |
|---|---|---|
| `$result->success` | `bool` | Whether the operation succeeded |
| `$result->message` | `string` | Human-readable outcome summary |
| `$result->before` | `array` | State snapshot captured before the fix |
| `$result->after` | `array` | State snapshot captured after the fix |
| `$result->errors` | `string[]` | Non-fatal errors encountered during the fix |

### Fix_Result Factory Methods

Use the factory methods to verify their contracts:

```php
// Success with before/after snapshots.
$result = \SystemReport\Fix_Result::success(
    'Optimized 3 options',
    array( 'count' => 3 ),
    array( 'count' => 0 )
);

$this->assertTrue( $result->success );
$this->assertSame( array( 'count' => 3 ), $result->before );
$this->assertSame( array( 'count' => 0 ), $result->after );
$this->assertSame( array(), $result->errors );

// Failure.
$result = \SystemReport\Fix_Result::failure(
    'Database error',
    array( 'SQLSTATE 42000' )
);

$this->assertFalse( $result->success );
$this->assertSame( array( 'SQLSTATE 42000' ), $result->errors );
```

### Noop Behavior

A well-written fixer returns a successful result even when there is nothing to do:

```php
public function test_fix_noop_when_already_clean(): void {
    $fixer  = new \SystemReport\Fixers\Autoload_Optimizer();
    $result = $fixer->fix();

    $this->assertTrue( $result->success );
    $this->assertStringContainsString( 'Nothing to optimize', $result->message );
}
```

### Before/After Snapshot Assertions

Verify that the snapshots accurately reflect state change:

```php
public function test_fix_snapshots_reflect_change(): void {
    update_option( 'sr_test_snap', str_repeat( 'z', 150 * 1024 ), 'yes' );

    $fixer  = new \SystemReport\Fixers\Autoload_Optimizer();
    $result = $fixer->fix();

    $this->assertTrue( $result->success );

    // Before snapshot should include the bloated option.
    $this->assertArrayHasKey( 'sr_test_snap', $result->before['bloated_options'] );

    // After snapshot should confirm the option was processed.
    $this->assertContains( 'sr_test_snap', $result->after['optimized_options'] );

    // The total autoload size should decrease.
    $this->assertLessThan(
        $result->before['total_autoload_size'],
        $result->after['total_autoload_size']
    );

    delete_option( 'sr_test_snap' );
}
```

### Testing Metadata

Always cover the fixer's identity metadata in a dedicated group of tests:

```php
public function test_fixer_metadata(): void {
    $fixer = new \My_Plugin\Fixers\Cache_Purger();

    $this->assertSame( 'cache_purger', $fixer->get_id() );
    $this->assertNotEmpty( $fixer->get_label() );
    $this->assertNotEmpty( $fixer->get_description() );
    $this->assertSame( 'performance', $fixer->get_category() );
    $this->assertSame( \SystemReport\Risk_Level::Low, $fixer->get_risk_level() );
}
```

### Risk Level

`Risk_Level` determines whether the fixer requires user confirmation before running:

```php
$this->assertFalse( \SystemReport\Risk_Level::Low->requires_confirmation() );
$this->assertTrue( \SystemReport\Risk_Level::Medium->requires_confirmation() );
$this->assertTrue( \SystemReport\Risk_Level::High->requires_confirmation() );
```

---

## Complete Example

The following is a fully annotated test class for a hypothetical `Custom_Option_Collector` that reads a numeric option and returns `Good`, `Warning`, or `Critical` based on thresholds.

```php
<?php
/**
 * Tests for Custom_Option_Collector.
 *
 * @package MyPlugin
 */

use SystemReport\Field;
use SystemReport\Status;

/**
 * Test the Custom_Option_Collector output, status thresholds, and edge cases.
 *
 * The collector reads 'my_plugin_score' from wp_options and classifies it:
 *   >= 80  -> Good
 *   >= 50  -> Warning
 *   < 50   -> Critical
 */
class Custom_Option_Collector_Test extends WP_UnitTestCase {

    /**
     * Collector under test.
     *
     * @var \MyPlugin\Collectors\Custom_Option_Collector
     */
    private \MyPlugin\Collectors\Custom_Option_Collector $collector;

    /**
     * Set up fresh state before each test.
     *
     * Call parent::set_up() first so WP_UnitTestCase wraps this test
     * in a database transaction and resets the WordPress state.
     */
    public function set_up(): void {
        parent::set_up();

        // Clear the option and any cache transient before each test.
        delete_option( 'my_plugin_score' );
        delete_transient( 'sr_custom_option' );

        $this->collector = new \MyPlugin\Collectors\Custom_Option_Collector();
    }

    /**
     * Clean up after each test.
     *
     * Remove any filters added during the test and purge the transient.
     * Call parent::tear_down() last so the transaction rollback fires correctly.
     */
    public function tear_down(): void {
        delete_option( 'my_plugin_score' );
        delete_transient( 'sr_custom_option' );
        remove_all_filters( 'my_plugin_score_thresholds' );
        parent::tear_down();
    }

    // -------------------------------------------------------
    // Metadata tests.
    // -------------------------------------------------------

    /**
     * Test that the collector reports the correct ID.
     */
    public function test_collector_id(): void {
        $this->assertSame( 'custom_option', $this->collector->get_id() );
    }

    /**
     * Test that the label is a non-empty string.
     */
    public function test_collector_label(): void {
        $this->assertIsString( $this->collector->get_label() );
        $this->assertNotEmpty( $this->collector->get_label() );
    }

    /**
     * Test that the description is a non-empty string.
     */
    public function test_collector_description(): void {
        $this->assertNotEmpty( $this->collector->get_description() );
    }

    /**
     * Test that the priority is the expected integer.
     */
    public function test_collector_priority(): void {
        $this->assertSame( 150, $this->collector->get_priority() );
    }

    // -------------------------------------------------------
    // Return type tests.
    // -------------------------------------------------------

    /**
     * Test that collect() returns an array of Field instances.
     */
    public function test_collect_returns_field_objects(): void {
        update_option( 'my_plugin_score', '75' );

        $fields = $this->collector->collect();

        $this->assertIsArray( $fields );
        $this->assertNotEmpty( $fields );

        foreach ( $fields as $index => $field ) {
            $this->assertInstanceOf(
                Field::class,
                $field,
                "Item at index {$index} should be a Field instance."
            );
        }
    }

    /**
     * Test that collect() always returns exactly 1 field.
     */
    public function test_field_count(): void {
        update_option( 'my_plugin_score', '75' );

        $this->assertCount( 1, $this->collector->collect() );
    }

    /**
     * Test that every field carries a valid Status enum case.
     */
    public function test_fields_have_valid_status(): void {
        update_option( 'my_plugin_score', '75' );

        $valid_cases = Status::cases();
        $fields      = $this->collector->collect();

        foreach ( $fields as $field ) {
            $this->assertContains(
                $field->status,
                $valid_cases,
                "Field '{$field->label}' must carry a valid Status enum case."
            );
        }
    }

    // -------------------------------------------------------
    // Status threshold tests (data provider).
    // -------------------------------------------------------

    /**
     * Data provider for threshold status tests.
     *
     * Each entry is [ score_value, expected_status_case ].
     *
     * @return array<string, array{string, Status}>
     */
    public static function provide_threshold_cases(): array {
        return array(
            'score of 100 is good'           => array( '100', Status::Good ),
            'score of 80 is good (boundary)' => array( '80', Status::Good ),
            'score of 79 is warning'         => array( '79', Status::Warning ),
            'score of 50 is warning (bound)' => array( '50', Status::Warning ),
            'score of 49 is critical'        => array( '49', Status::Critical ),
            'score of 0 is critical'         => array( '0', Status::Critical ),
        );
    }

    /**
     * Test that the collector assigns the correct status for each score.
     *
     * @dataProvider provide_threshold_cases
     *
     * @param string $score    The option value to set.
     * @param Status $expected The expected Status enum case.
     */
    public function test_status_for_score( string $score, Status $expected ): void {
        update_option( 'my_plugin_score', $score );

        $fields = $this->collector->collect();
        $field  = $this->find_field_by_label( $fields, 'Plugin Score' );

        $this->assertNotNull( $field, '"Plugin Score" field should be present.' );
        $this->assertSame(
            $expected,
            $field->status,
            "Score {$score} should produce status {$expected->value}."
        );
    }

    // -------------------------------------------------------
    // Edge case tests.
    // -------------------------------------------------------

    /**
     * Test the output when the option has not been set yet.
     *
     * The collector should not fatal and should return an Info or Warning field
     * indicating the option is missing.
     */
    public function test_missing_option_does_not_fatal(): void {
        // The option was deleted in set_up(); do not set it here.
        $fields = $this->collector->collect();

        $this->assertIsArray( $fields );
        $this->assertNotEmpty( $fields );

        $field = $this->find_field_by_label( $fields, 'Plugin Score' );
        $this->assertNotNull( $field );

        // The exact status depends on the collector's design, but it must be valid.
        $this->assertContains( $field->status, Status::cases() );
    }

    /**
     * Test that an empty string option value is handled gracefully.
     */
    public function test_empty_string_option(): void {
        update_option( 'my_plugin_score', '' );

        $fields = $this->collector->collect();
        $field  = $this->find_field_by_label( $fields, 'Plugin Score' );

        $this->assertNotNull( $field );
        // A non-numeric value should not cause a PHP warning or error.
        $this->assertIsString( $field->value );
    }

    /**
     * Test that a non-numeric option value is handled gracefully.
     */
    public function test_non_numeric_option(): void {
        update_option( 'my_plugin_score', 'not-a-number' );

        $fields = $this->collector->collect();
        $field  = $this->find_field_by_label( $fields, 'Plugin Score' );

        $this->assertNotNull( $field );
        $this->assertIsString( $field->value );
    }

    // -------------------------------------------------------
    // HTTP mock example.
    // -------------------------------------------------------

    /**
     * Test that the collector reports Good status when the remote validation
     * endpoint confirms the score is acceptable.
     *
     * Uses pre_http_request to avoid any real network dependency.
     */
    public function test_remote_validation_success(): void {
        update_option( 'my_plugin_score', '90' );

        $mock = static function ( $preempt, $args, $url ) {
            if ( false !== strpos( $url, 'my-plugin.example.com/validate' ) ) {
                return array(
                    'response' => array( 'code' => 200, 'message' => 'OK' ),
                    'body'     => '{"valid":true}',
                    'headers'  => array(),
                    'cookies'  => array(),
                );
            }
            return $preempt;
        };

        add_filter( 'pre_http_request', $mock, 10, 3 );
        delete_transient( 'sr_custom_option' );

        $fields = $this->collector->collect();

        remove_filter( 'pre_http_request', $mock, 10 );

        $field = $this->find_field_by_label( $fields, 'Remote Validation' );

        $this->assertNotNull( $field );
        $this->assertSame( Status::Good, $field->status );
    }

    /**
     * Test that the collector reports Critical status when the remote endpoint fails.
     */
    public function test_remote_validation_failure(): void {
        update_option( 'my_plugin_score', '90' );

        $mock = static function ( $preempt, $args, $url ) {
            if ( false !== strpos( $url, 'my-plugin.example.com/validate' ) ) {
                return new \WP_Error( 'http_request_failed', 'Connection refused' );
            }
            return $preempt;
        };

        add_filter( 'pre_http_request', $mock, 10, 3 );
        delete_transient( 'sr_custom_option' );

        $fields = $this->collector->collect();

        remove_filter( 'pre_http_request', $mock, 10 );

        $field = $this->find_field_by_label( $fields, 'Remote Validation' );

        $this->assertNotNull( $field );
        $this->assertSame( Status::Critical, $field->status );
    }

    // -------------------------------------------------------
    // Caching tests.
    // -------------------------------------------------------

    /**
     * Test that get_cached_data() sets a transient and returns the same data twice.
     */
    public function test_caching(): void {
        update_option( 'my_plugin_score', '75' );
        delete_transient( 'sr_custom_option' );

        $first = $this->collector->get_cached_data();

        $this->assertNotFalse(
            get_transient( 'sr_custom_option' ),
            'Transient must be set after the first get_cached_data() call.'
        );

        $second = $this->collector->get_cached_data();

        $this->assertEquals( $first, $second );
    }

    // -------------------------------------------------------
    // Helper methods.
    // -------------------------------------------------------

    /**
     * Find a field in the collected array by its label.
     *
     * @param Field[] $fields Array of Field objects.
     * @param string  $label  The label to find.
     * @return Field|null The matching field, or null if not found.
     */
    private function find_field_by_label( array $fields, string $label ): ?Field {
        foreach ( $fields as $field ) {
            if ( $field instanceof Field && $label === $field->label ) {
                return $field;
            }
        }
        return null;
    }
}
```

---

## Running Tests

### Basic Run

```bash
vendor/bin/phpunit
```

### Run a Single Test File

```bash
vendor/bin/phpunit tests/collectors/SecurityTest.php
```

### Run a Single Test Method

```bash
vendor/bin/phpunit --filter test_https_field_present tests/collectors/SecurityTest.php
```

### Run with Verbose Output

```bash
vendor/bin/phpunit --testdox
```

### CI Environment Variables

The CI matrix sets:

```
WP_TESTS_DIR=/tmp/wordpress-tests-lib
```

If you run tests locally against a different WordPress checkout, set the variable in your shell or in a `.env` file loaded by your test runner:

```bash
WP_TESTS_DIR=/path/to/wordpress-develop/tests/phpunit vendor/bin/phpunit
```

### CI Matrix

The plugin is tested against PHP 8.1, 8.2, 8.3, and 8.4 with MySQL 8.0. PHPStan requires an increased memory limit:

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

### Separate Process Tests

Tests annotated with `@runInSeparateProcess` and `@preserveGlobalState disabled` spawn an isolated PHP process. They run considerably slower and should be used only when defining a PHP constant is the only way to reach the code path under test. Group them together so they are easy to skip or parallelize:

```php
/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
public function test_wp_debug_constant_set(): void {
    define( 'WP_DEBUG', true );

    $fields = $this->collector->collect();
    $field  = $this->find_field_by_label( $fields, 'Debug Mode' );

    $this->assertSame( Status::Warning, $field->status );
}
```

---

## Reference

### Living Collector Test Examples

`tests/collectors/` contains one test file per built-in collector:

| File | Patterns demonstrated |
|---|---|
| `SecurityTest.php` | Field presence, status enum validation, `find_field_by_label()` helper |
| `NetworkConnectivityTest.php` | `pre_http_request` HTTP mocking (success and failure), `markTestSkipped()` for constants |
| `PerformanceTest.php` | Transient cache testing, privacy flag assertions, `assertCount()` on fixed field sets |
| `WordPressEnvironmentTest.php` | Option-driven status assertions, `WP_UnitTestCase` rollback verification |
| `DatabaseTest.php` | Direct `$wpdb` usage for setup, PHPCS ignore annotation patterns |

### Fixer Testing Pattern

`tests/FixersTest.php` is the authoritative reference for fixer tests. It demonstrates:

- `Fix_Result::success()` and `Fix_Result::failure()` factory assertions
- `can_fix()` precondition testing with real database state
- `fix()` execution with before/after snapshot verification
- Protected-option and protected-hook filter testing
- Multi-item fix results with count assertions
- `Fixer_Registry` registration and `get_by_category()` filtering

### Key Classes and Namespaces

| Class | Namespace | Purpose |
|---|---|---|
| `Field` | `SystemReport` | Value object for a single diagnostic field |
| `Status` | `SystemReport` | Backed enum: `Good`, `Warning`, `Critical`, `Info` |
| `Abstract_Collector` | `SystemReport\Collectors` | Base class with `make_field()`, `format_size()`, `get_cached_data()` |
| `Fix_Result` | `SystemReport` | Value object returned by `fix()` |
| `Fixer_Registry` | `SystemReport` | Holds and categorizes fixer instances |
| `Risk_Level` | `SystemReport` | Backed enum: `Low`, `Medium`, `High` |
