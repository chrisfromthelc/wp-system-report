# ADR-001: Pro-Ready Architecture

## Status

Accepted

## Context

WP System Report is being enhanced from a diagnostic-only tool into a comprehensive site health and remediation platform. Some features (active fixers, AI/MCP integration) are candidates for a future Pro plugin split.

We need an architecture that:
1. Keeps all code in a single plugin for now
2. Makes extraction into a separate Pro plugin trivial when the time comes
3. Avoids any runtime cost for the separation concern

## Decision

### Namespace Separation

All Pro-candidate code uses the `WPSystemReport\Pro\` namespace prefix:
- `WPSystemReport\Pro\Fixers\` - Active fix capabilities (Phase 3)
- `WPSystemReport\Pro\MCP\` - AI and MCP integration (Phase 4)

### Directory Separation

Pro-candidate code lives under `includes/pro/`:
```
includes/pro/
  fixers/
    interface-fixer.php
    class-fixer-registry.php
    class-autoload-optimizer.php
    ...
  mcp/
    class-abilities-provider.php
    class-mcp-formatter.php
    ...
  class-features.php
```

### Feature Flag Pattern

A central `Features` class controls Pro feature availability:

```php
namespace WPSystemReport\Pro;

class Features {
    public static function is_pro(): bool {
        // For now, all features are enabled.
        // When splitting, this checks for a valid license.
        return true;
    }
}
```

Services register conditionally:
```php
if ( Features::is_pro() ) {
    $this->register_fixers();
    $this->register_mcp_abilities();
}
```

### Extraction Process

When splitting to a Pro plugin:
1. Move `includes/pro/` to the new plugin
2. Update `Features::is_pro()` to check license validation
3. The free plugin's feature flag returns `false` without the Pro plugin
4. The Pro plugin's feature flag checks the license and returns accordingly
5. No other refactoring needed

## Consequences

### Positive
- Zero refactoring when the Pro split happens
- Clear code organization by feature tier
- No runtime overhead (feature flag is a simple boolean check)
- Developers can easily identify which features are Pro candidates

### Negative
- Slightly more complex directory structure than a flat layout
- Namespace depth increases for Pro code
- Must be disciplined about keeping Pro code in the right directory

## Alternatives Considered

### WordPress Plugin Add-on Pattern
Register Pro features as a separate plugin from the start. Rejected because it adds unnecessary complexity during active development and testing.

### No Separation
Build everything flat and refactor later. Rejected because refactoring namespace boundaries is expensive and error-prone.
