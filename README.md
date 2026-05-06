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
- ✅ **A/B Testing**: Deterministic variant selection via `getVariant()`.
- ✅ **Extensible Logging**: Contract-based usage tracking with `NullFlagUsageLogger` out of the box.
- ✅ **TDD-Verified**: 74%+ code coverage with Pest/PHPUnit, safe for production.

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

### 2. Evaluate Flags
```php
// Boolean flag with context
$isEnabled = $flagService->isEnabled('new_product_template', [
    'user_role' => 'manager',
    'category' => 'electronics',
    'target_id' => 42
]);

// A/B Test variant (returns string|null)
$variant = $flagService->getVariant('article_header_test', [
    'user_hash' => md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'])
]);
```

## 🧩 How Rules Work
Flags are evaluated **in order** (short-circuit). First matching rule wins.

| Condition Syntax | Description | Example |
|------------------|-------------|---------|
| `user_role=VALUE` | Exact role match | `user_role=admin` |
| `category IN (a,b)` | List match | `category IN (electronics,phones)` |
| `target_id=VALUE` | Exact entity ID | `target_id=101` |
| `current_date BETWEEN MM-DD AND MM-DD` | Seasonal window | `current_date BETWEEN 12-01 AND 12-31` |
| `user_hash PERCENTAGE N` | Deterministic rollout | `user_hash PERCENTAGE 25` |

> 💡 **Platform Integration**: Map your platform-specific keys (e.g., `document_id`) to `target_id` in your adapter. The core stays neutral.

## 🏗️ Architecture Overview
```
Application/
  └── Service/FeatureFlagService.php  ← Orchestration layer
Domain/
  ├── Entity/FeatureFlag.php          ← Business rules & evaluation
  ├── Specification/                  ← Condition strategies
  ├── ValueObject/                    ← FlagName, EvaluationContext
  ├── Logger/FlagUsageLoggerInterface ← Contract for analytics
  └── Repository/FlagRepositoryInterface ← Contract for storage
```
- **No framework ties**: Core doesn't know about Laravel, Symfony, or DB drivers.
- **Ports & Adapters**: Implement `FlagRepositoryInterface` and `FlagUsageLoggerInterface` for your stack.

## 📊 Logging & Analytics
Implement `FlagUsageLoggerInterface` to track flag usage:
```php
public function log(string $flagName, bool $result, array $context = []): void;
public function logVariant(string $flagName, ?string $variant, array $context = []): void;
```
Use `NullFlagUsageLogger` in dev/tests. Swap to `DatabaseLogger` or `FileLogger` in production.

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

## 📄 License
MIT © Ambrion. See [LICENSE](LICENSE) for details.