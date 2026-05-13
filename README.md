# 🚩 Feature Flags Core

Framework-agnostic Feature Flags engine built with **DDD** and **Clean Architecture** principles.  
Designed to be portable, testable, and easily integrated into any PHP project.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/coverage-74%25-yellowgreen.svg)]()
[![Pest](https://img.shields.io/badge/tested_with-Pest-cc3366.svg)](https://pestphp.com)

## ✨ Features
- ✅ **Framework-agnostic**: No dependencies on CMS, framework, or global state.
- ✅ **Domain-Driven Design**: Clean separation of Entities, Value Objects, Specifications, and Services.
- ✅ **Unified Evaluation API**: Single `evaluate()` method returns `EvaluationResult` with `enabled`, `variant`, `weight`, `matchedRule`.
- ✅ **Rule-Based Evaluation**: `category=`, `user_role=`, `target_id IN (...)`, `current_date BETWEEN`, `PERCENTAGE N`.
- ✅ **Logical Operators**: Support for `AND`, `OR`, `NOT`, `!=` via `CompositeSpecification` (#JAM-7731).
- ✅ **A/B/C Testing**: Deterministic variant selection with statistical weight tracking.
- ✅ **Extensible Logging**: Contract-based via `logEvaluation(EvaluationResult)` — no duplicate records.
- ✅ **TDD-Verified**: 74%+ code coverage with Pest/PHPUnit, safe for production.

## 📦 Installation
```bash
composer require ambrion/feature-flags-core:^1.4@alpha
```

## 🚀 Quick Start

### 1. Wire Dependencies
```php
use FeatureFlags\Core\Application\Service\FeatureFlagService;
use FeatureFlags\Core\Domain\Logger\NullFlagUsageLogger;
use FeatureFlags\Core\Domain\Repository\FlagRepositoryInterface;

// Implement your own repository (DB, config file, etc.)
$repository = new MyFlagRepository();
$logger = new NullFlagUsageLogger(); // or your implementation

$flagService = new FeatureFlagService($repository, $logger);
```

### 2. Evaluate Flags — Unified API
```php
// Single call returns all evaluation data
$result = $flagService->evaluate('new_product_template', [
    'user_role' => 'manager',
    'category' => 'electronics',
    'target_id' => 42,
]);

// Access what you need:
if ($result->enabled) {
    // Show new feature
}

// For A/B tests:
$variant = $result->variant; // 'A', 'B', 'C', or null
$weight = $result->weight;   // 0.34 for PERCENTAGE rules, null otherwise
$rule = $result->matchedRule; // 'category=electronics' — for debugging
```

### 3. Convenience Wrappers (Optional)
```php
// Boolean check — same as evaluate()->enabled
$isEnabled = $flagService->isEnabled('show_banner', $context);

// A/B variant — same as evaluate()->variant
$variant = $flagService->getVariant('checkout_test', $context);

// Statistical weight — same as evaluate()->weight
$weight = $flagService->getVariantWeight('checkout_test', $context);
```

> 💡 All wrappers delegate to `evaluate()` internally — no duplicate evaluations or log entries.

## 🧩 How Rules Work
Flags are evaluated **in order** (short-circuit). First matching rule wins.

### Condition Syntax
| Condition Syntax                       | Description           | Example                                |
|----------------------------------------|-----------------------|----------------------------------------|
| `user_role=VALUE`                      | Exact role match      | `user_role=admin`                      |
| `category IN (a,b)`                    | List match            | `category IN (electronics,phones)`     |
| `target_id=VALUE`                      | Exact entity ID       | `target_id=101`                        |
| `current_date BETWEEN MM-DD AND MM-DD` | Seasonal window       | `current_date BETWEEN 12-01 AND 12-31` |
| `user_hash PERCENTAGE N`               | Deterministic rollout | `user_hash PERCENTAGE 25`              |

### 🔗 Logical Operators (CompositeSpecification)
Combine conditions with `AND`, `OR`, `NOT`, `!=`:
```php
// AND: both conditions must match
['condition' => 'category=electronics AND user_tier=premium', 'value' => true]

// OR: at least one condition matches
['condition' => 'user_role=admin OR user_role=manager', 'value' => true]

// NOT / !=: negation
['condition' => 'category!=clothing', 'value' => true]
['condition' => 'NOT environment=production', 'value' => true]

// Precedence: NOT > AND > OR (like SQL/PHP)
// "A OR B AND C" is evaluated as "A OR (B AND C)"
```

> ⚠️ **Limitation**: Parentheses for explicit grouping are not supported in v1. Use De Morgan's laws: `NOT (A AND B)` → `NOT A OR NOT B`.

### Default Value Behavior
| Flag Type      | `default` Value       | `evaluate()->enabled` | `evaluate()->variant` |
|----------------|-----------------------|----------------------|----------------------|
| Boolean toggle | `true` / `false`      | `bool`               | `null`               |
| A/B test       | `'A'` / `'B'` / `'C'` | `(bool)'A'` → `true` | `'A'` (string)       |
| Hybrid         | `null`                | `false`              | `null`               |

### Example: A/B Test Configuration
```php
'checkout_flow_test' => [
    'default' => 'A',  // Polymorphic default: string for variants
    'rules' => [
        // 34% of users get variant A (weight = 0.34)
        ['condition' => 'user_hash PERCENTAGE 34', 'value' => 'A'],
        // Next 33% (buckets 34-66) get variant B (weight = 0.67)
        ['condition' => 'user_hash PERCENTAGE 67', 'value' => 'B'],
        // Remaining 33% (buckets 67-99) get variant C (weight = 1.0)
        ['condition' => 'user_hash PERCENTAGE 100', 'value' => 'C'],
    ]
]
```

> 💡 **Platform Integration**: Map your platform-specific keys (e.g., `document_id`) to `target_id` in your adapter. The core stays neutral.

## 🏗️ Architecture Overview
```
Application/
  └── Service/FeatureFlagService.php  ← Orchestration layer
Domain/
  ├── Entity/FeatureFlag.php          ← Business rules & evaluation
  ├── ValueObject/
  │   ├── EvaluationResult.php        ← Unified result: enabled/variant/weight/matchedRule
  │   ├── EvaluationContext.php       ← Type-safe context access
  │   └── FlagName.php                ← Strongly-typed flag identifier
  ├── Specification/                  ← Condition strategies
  │   ├── CategorySpecification.php
  │   ├── UserRoleSpecification.php
  │   ├── PercentageSpecification.php
  │   ├── DateBetweenSpecification.php
  │   ├── TargetIdSpecification.php
  │   └── CompositeSpecification.php  ← AND/OR/NOT/!= support
  ├── Logger/
  │   └── FlagUsageLoggerInterface.php ← Contract: logEvaluation(EvaluationResult)
  └── Repository/
      └── FlagRepositoryInterface.php  ← Contract for flag storage
```
- **No framework ties**: Core doesn't know about Laravel, Symfony, or DB drivers.
- **Ports & Adapters**: Implement interfaces for your stack.
- **Polymorphic defaults**: `FeatureFlag::$default` is `mixed` — supports `bool|string|null`.

## 📊 Logging & Analytics

### Unified Logging Contract
Implement `FlagUsageLoggerInterface` to track flag usage:
```php
public function logEvaluation(string $flagName, EvaluationResult $result, array $context = []): void;
```

The `EvaluationResult` contains all data for analytics:
```php
$result->enabled;      // bool — for feature toggles
$result->variant;      // ?string — for A/B/C tests
$result->weight;       // ?float — normalized weight (0.0-1.0) for PERCENTAGE rules
$result->matchedRule;  // ?string — condition string of the matched rule (debugging)
```

### Example: Custom Logger Implementation
```php
class DatabaseFlagUsageLogger implements FlagUsageLoggerInterface
{
    public function logEvaluation(string $flagName, EvaluationResult $result, array $context = []): void
    {
        // Save to your analytics store
        $this->analytics->record([
            'flag' => $flagName,
            'enabled' => $result->enabled,
            'variant' => $result->variant,
            'weight' => $result->weight,
            'matched_rule' => $result->matchedRule,
            'context_hash' => md5(json_encode($context)),
            'evaluated_at' => now(),
        ]);
    }
}
```

> 💡 **No duplicate logs**: Since `evaluate()` calls `logEvaluation()` once, you get exactly one record per flag evaluation — even when accessing `variant` and `weight`.

### 📈 Statistical Analysis Example
```php
$result = $flagService->evaluate('checkout_test', $context);

if ($result->weight !== null) {
    // Normalize metrics for fair A/B comparison
    $normalizedConversion = $rawConversion / $result->weight;
    
    // Log to external analytics
    $analytics->track('checkout_variant', [
        'variant' => $result->variant,
        'weight' => $result->weight,
        'conversion' => $normalizedConversion,
    ]);
}
```

---

## 🧪 Development & Testing
```bash
composer install
composer test          # Run unit tests
composer test:coverage # Run with coverage report
composer stan          # Static analysis with PHPStan
composer format        # Auto-format with Pint
composer check         # Run all checks: lint + test + stan
```
Built with **Pest**. Follow TDD: Red → Green → Refactor.

### Testing Polymorphic Defaults
```php
// Test: getVariant returns string default when no rules match
it('returns string default for A/B test', function() {
    $flag = new FeatureFlag(
        name: new FlagName('ab_test'),
        default: 'A',  // string default
        rules: [],
    );
    
    expect($flag->getVariant(new EvaluationContext([])))->toBe('A');
});

// Test: evaluate always returns bool for enabled (backward compatibility)
it('evaluate returns bool even with string default', function() {
    $flag = new FeatureFlag(
        name: new FlagName('hybrid'),
        default: 'A',  // string
        rules: [],
    );
    
    $result = $flag->evaluate(new EvaluationContext([]));
    expect($result->enabled)->toBeBool();      // ✅ always bool
    expect($result->enabled)->toBeTrue();      // (bool)'A' === true
});
```

## 🔙 Backward Compatibility
Existing code continues to work without changes:

| Old Code                     | Behavior                | New Behavior |
|------------------------------|-------------------------|--------------|
| `default: false`             | `evaluate()->enabled` → `false`  | ✅ Same       |
| `default: true`              | `getVariant()` → `null` | ✅ Same       |
| `rules: [['value' => true]]` | `evaluate()->enabled` → `true`   | ✅ Same       |
| `rules: [['value' => 'B']]`  | `getVariant()` → `'B'`  | ✅ Same       |

> ⚠️ **Migration Note**: If storing flags in a database, change `default_value` column from `BOOLEAN` to `JSON` to support polymorphic values. Laravel's `'json'` cast handles serialization automatically.

## 🔄 Migration: v1.3 → v1.4
### Logging Interface Change
```php
// Before (v1.3):
public function log(string $flagName, bool $result, array $context = []): void;
public function logVariant(string $flagName, ?string $variant, array $context = []): void;

// After (v1.4):
public function logEvaluation(string $flagName, EvaluationResult $result, array $context = []): void;
```

Update your logger implementation:
```php
public function logEvaluation(string $flagName, EvaluationResult $result, array $context = []): void
{
    // Access all data from EvaluationResult
    $this->save([
        'flag' => $flagName,
        'enabled' => $result->enabled,
        'variant' => $result->variant,
        'weight' => $result->weight,
        'matched_rule' => $result->matchedRule,
        'context' => $context,
    ]);
}
```

### Service API: Prefer `evaluate()`
```php
// Before:
$variant = $service->getVariant('flag', $ctx);
$weight = $service->getVariantWeight('flag', $ctx); // Two calls, two logs

// After:
$result = $service->evaluate('flag', $ctx); // One call, one log
$variant = $result->variant;
$weight = $result->weight;
```

## 📄 License
MIT © Ambrion. See [LICENSE](LICENSE) for details.