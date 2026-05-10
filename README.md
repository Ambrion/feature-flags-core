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
- ✅ **Rule-Based Evaluation**: `category=`, `user_role=`, `target_id IN (...)`, `current_date BETWEEN`, `PERCENTAGE N`.
- ✅ **A/B/C Testing**: Deterministic variant selection via `getVariant()` with **polymorphic default values** (`'A'`, `'B'`, `true`, `false`, `null`).
- ✅ **Extensible Logging**: Contract-based usage tracking with `NullFlagUsageLogger` out of the box.
- ✅ **TDD-Verified**: 74%+ code coverage with Pest/PHPUnit, safe for production.
- ✅ **Backward Compatible**: Existing boolean flags work without changes.

## 📦 Installation
```bash
composer require ambrion/feature-flags-core
```

## 🚀 Quick Start

### 1. Wire Dependencies
```php
use FeatureFlags\Core\Application\Service\FeatureFlagService;
use FeatureFlags\Core\Domain\Logger\NullFlagUsageLogger;
use FeatureFlags\Core\Domain\Repository\FlagRepositoryInterface;

// Implement your own repository (DB, config file, etc.)
$repository = new MyFlagRepository();
$logger = new NullFlagUsageLogger();

$flagService = new FeatureFlagService($repository, $logger);
```

### 2. Evaluate Boolean Flags (Feature Toggles)
```php
// Boolean flag with context — returns true/false
$isEnabled = $flagService->isEnabled('new_product_template', [
    'user_role' => 'manager',
    'category' => 'electronics',
    'target_id' => 42
]);

if ($isEnabled) {
    // Show new feature
}
```

### 3. Get A/B Test Variants (Experiment Flags)
```php
// A/B/C test flag with string default — returns 'A', 'B', 'C', or null
$variant = $flagService->getVariant('checkout_flow_test', [
    'user_hash' => md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'])
]);

// Use match for clean variant handling
return match($variant) {
    'B' => renderCheckoutV2(),
    'C' => renderCheckoutV3(),
    default => renderCheckoutV1(), // 'A' or fallback
};
```

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

### Default Value Behavior
| Flag Type      | `default` Value       | `evaluate()` Returns          | `getVariant()` Returns        |
|----------------|-----------------------|-------------------------------|-------------------------------|
| Boolean toggle | `true` / `false`      | `bool` (always)               | `null` (no variant)           |
| A/B test       | `'A'` / `'B'` / `'C'` | `bool` (`(bool)'A' === true`) | `string` (`'A'`, `'B'`, etc.) |
| Hybrid         | `null`                | `false`                       | `null`                        |

> 💡 **Key Insight**: `evaluate()` **always** returns `bool` for backward compatibility.  
> `getVariant()` returns `string|null` — use it for A/B/C experiments.

### Example: A/B Test Configuration
```php
// Config array or database record
'checkout_flow_test' => [
    'default' => 'A',  // Polymorphic default: string for variants
    'rules' => [
        // 34% of users get variant A
        ['condition' => 'user_hash PERCENTAGE 34', 'value' => 'A'],
        // Next 33% (buckets 34-66) get variant B
        ['condition' => 'user_hash PERCENTAGE 67', 'value' => 'B'],
        // Remaining 33% (buckets 67-99) get variant C
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
  │   ├── evaluate(): bool            ← For feature toggles
  │   └── getVariant(): ?string       ← For A/B/C experiments
  ├── Specification/                  ← Condition strategies
  ├── ValueObject/                    ← FlagName, EvaluationContext
  ├── Logger/FlagUsageLoggerInterface ← Contract for analytics
  └── Repository/FlagRepositoryInterface ← Contract for storage
```
- **No framework ties**: Core doesn't know about Laravel, Symfony, or DB drivers.
- **Ports & Adapters**: Implement `FlagRepositoryInterface` and `FlagUsageLoggerInterface` for your stack.
- **Polymorphic defaults**: `FeatureFlag::$default` is `mixed` — supports `bool|string|null`.

## 📊 Logging & Analytics
Implement `FlagUsageLoggerInterface` to track flag usage:
```php
public function log(string $flagName, bool $result, array $context = []): void;
public function logVariant(string $flagName, ?string $variant, array $context = []): void;
```
Use `NullFlagUsageLogger` in dev/tests. Swap to `DatabaseLogger` or `FileLogger` in production.

### 📊 Analytics: Getting Variant Weight

For statistical analysis of A/B tests, use `getVariantWeight()` to retrieve the normalized weight (0.0-1.0) of the assigned variant:

```php
$variant = $flagService->getVariant('checkout_test', $context);
$weight = $flagService->getVariantWeight('checkout_test', $context); // 0.34

// Normalize metrics for fair comparison
$normalizedConversion = $rawConversion / ($weight ?: 1);
```
> 💡 Note: Weight is only returned for PERCENTAGE-based rules. Role-based, category-based, or other deterministic rules return null.

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

// Test: evaluate always returns bool (backward compatibility)
it('evaluate returns bool even with string default', function() {
    $flag = new FeatureFlag(
        name: new FlagName('hybrid'),
        default: 'A',  // string
        rules: [],
    );
    
    $result = $flag->evaluate(new EvaluationContext([]));
    expect($result)->toBeBool();      // ✅ always bool
    expect($result)->toBeTrue();      // (bool)'A' === true
});
```

## 🔙 Backward Compatibility
Existing code continues to work without changes:

| Old Code                     | Behavior                | New Behavior |
|------------------------------|-------------------------|--------------|
| `default: false`             | `evaluate()` → `false`  | ✅ Same       |
| `default: true`              | `getVariant()` → `null` | ✅ Same       |
| `rules: [['value' => true]]` | `evaluate()` → `true`   | ✅ Same       |
| `rules: [['value' => 'B']]`  | `getVariant()` → `'B'`  | ✅ Same       |

> ⚠️ **Migration Note**: If storing flags in a database, change `default_value` column from `BOOLEAN` to `JSON` to support polymorphic values. Laravel's `'json'` cast handles serialization automatically.

## 📄 License
MIT © Ambrion. See [LICENSE](LICENSE) for details.