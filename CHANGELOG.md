# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - v1.0.0-alpha
### Added
- **`getVariantWeight(): ?float`** for analytics: returns normalized weight (0.0-1.0) of percentage-based rules (#JAM-7726)
- Internal `RuleMatch` value object for reusable rule evaluation logic (#JAM-7726)
- `FeatureFlagService::getVariant()` for A/B testing with deterministic rollout
- `FlagUsageLoggerInterface::logVariant()` for variant tracking
- `TargetIdSpecification` for `target_id=VALUE` and `target_id IN (...)`
- `EvaluationContext` VO with type-safe access and minimal validation
- `NullFlagUsageLogger` implementing Null Object Pattern
- Integration-ready contracts (`FlagRepositoryInterface`, `FlagUsageLoggerInterface`)

### Changed
- Refactored `FeatureFlag` internals: `findMatchingRuleValue()` → `findMatchingRule(): RuleMatch` for extensibility (#JAM-7726)
- Extracted `extractPercentageWeight()` helper for deterministic weight calculation (#JAM-7726)
- `FeatureFlag::evaluate()` uses short-circuit rule evaluation (first match wins)
- Logger injection made optional with `NullFlagUsageLogger` as default
- Namespace migrated to `FeatureFlags\Core\*`
- PHP requirement bumped to `^8.3`

### Migration Notes
> ⚠️ If storing flags in a database, change `default_value` column from `BOOLEAN` to `JSON` to support polymorphic defaults. Laravel's `'json'` cast handles serialization automatically.

### Fixed
- `PercentageSpecification` correctly handles `0%` and `100%` edge cases
- `TargetIdSpecification::supports()` regex allows no-space syntax (`target_id=101`)
- String comparison in `TargetIdSpecification` supports UUIDs and slugs

### Dev Tools
- Pest ^2.34 for testing
- PHPStan ^1.10 for static analysis (level max)
- Laravel Pint ^1.13 for code formatting
- Composer scripts: `test`, `stan`, `format`, `check`