# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - v1.2.0-alpha
### Added
- `EvaluationResult` Value Object: Type-safe container for flag evaluation data (`enabled`, `variant`, `weight`, `matchedRule`) (#JAM-7729)
- `FeatureFlagService::evaluate(string, array): EvaluationResult`: Unified method returning all evaluation data in one call (#JAM-7729)
- `FlagUsageLoggerInterface::logEvaluation(string, EvaluationResult, array)`: Single logging method for all evaluation scenarios (#JAM-7729)
- `FeatureFlag::getMatchedRuleCondition(EvaluationContext): ?string`: Returns the condition string of the matched rule for debugging (#JAM-7729)
- Convenience wrapper methods: `isEnabled()`, `getVariant()`, `getVariantWeight()`, `evaluateForAnalytics()` — now delegate to `evaluate()` for consistency (#JAM-7729)
- `FeatureFlagService::evaluate()`: EvaluationResult — unified method returning all evaluation data for advanced analytics
- `FeatureFlag::getMatchedRuleCondition()` — returns the condition string of the matched rule for debugging
- `FeatureFlagService::evaluateForAnalytics(string, array): array{variant: ?string, weight: ?float}` for unified A/B test logging: returns variant and weight, logs once with weight in context (#JAM-7728)
- `findHashForPercentageRule(int, bool): string` test helper for deterministic testing of percentage-based rules (#JAM-7728)
- `FlagUsageLoggerInterface::logWeight(string, ?float, array)` for weight tracking in A/B tests (#JAM-7727)
- `DatabaseFlagUsageLogger::logWeight()` implementation: saves weight to `feature_flag_statistics.weight` column (#JAM-7727)
- `getVariantWeight(): ?float` for analytics: returns normalized weight (0.0-1.0) of percentage-based rules (#JAM-7726)
- Internal `RuleMatch` value object for reusable rule evaluation logic (#JAM-7726)
- `FeatureFlagService::getVariant()` for A/B testing with deterministic rollout
- `FlagUsageLoggerInterface::logVariant()` for variant tracking
- `TargetIdSpecification` for `target_id=VALUE` and `target_id IN (...)`
- `EvaluationContext` VO with type-safe access and minimal validation
- `NullFlagUsageLogger` implementing Null Object Pattern
- Integration-ready contracts (`FlagRepositoryInterface`, `FlagUsageLoggerInterface`)

### Changed
- Logging architecture: All evaluation paths now use `logEvaluation()` with `EvaluationResult` — no more duplicate DB records for A/B tests (#JAM-7729)
- FeatureFlagService internals: Single-pass evaluation via `evaluate()` — `isEnabled()`, `getVariant()`, etc. are now thin wrappers (#JAM-7729)
- Test expectations: Updated all service tests to mock `logEvaluation()` instead of `log()`/`logVariant()`/`logWeight()` (#JAM-7729)
- FeatureFlagService API: Convenience methods (`isEnabled`, `getVariant`, etc.) are now first-class citizens, not deprecated. They delegate to the unified `evaluate()` method internally for consistency and single-pass evaluation (#JAM-7728)
- EvaluationResult: Added `matchedRule` field for debugging: "Which rule was triggered?"
- A/B testing workflow: Use `evaluateForAnalytics()` instead of separate `getVariant()` + `getVariantWeight()` calls to prevent duplicate log entries (#JAM-7728)
- Test strategy for final classes: Use real `FeatureFlag` + `PercentageSpecification` instances instead of mocking, with dynamic hash selection via helper (#JAM-7728)
- `FeatureFlagService::getVariantWeight()` now calls `$this->logger->logWeight()` after weight calculation (#JAM-7727)
- Refactored `FeatureFlag` internals: `findMatchingRuleValue()` → `findMatchingRule(): RuleMatch` for extensibility (#JAM-7726)
- Extracted `extractPercentageWeight()` helper for deterministic weight calculation (#JAM-7726)
- `FeatureFlag::evaluate()` uses short-circuit rule evaluation (first match wins)
- Logger injection made optional with `NullFlagUsageLogger` as default
- Namespace migrated to `FeatureFlags\Core\*`
- PHP requirement bumped to `^8.3`

### Migration Notes
> ⚠️ **For custom logger implementations**: If you implement `FlagUsageLoggerInterface`, add the new `logEvaluation()` method:
> ```php
> public function logEvaluation(string $flagName, EvaluationResult $result, array $context = []): void
> {
>     // Save $result->enabled, $result->variant, $result->weight, $result->matchedRule
> }
> ```

>
> ⚠️ **For direct service usage**: If you called `getVariant()` + `getVariantWeight()` separately, consider using `evaluate()` for efficiency:
> ```php
> // Before (two calls, two log entries):
> $variant = $service->getVariant($flag, $ctx);
> $weight = $service->getVariantWeight($flag, $ctx);
> 
> // After (one call, one log entry with all data):
> $result = $service->evaluate($flag, $ctx);
> $variant = $result->variant;
> $weight = $result->weight;
> ```

> ⚠️ Snippet update recommended: If using separate `getVariant()` + manual `logVariant()` calls for A/B tests, migrate to `evaluateForAnalytics()` to avoid duplicate statistics records:
>
> ```php
> // Before (two calls → two log entries):
> $variant = $service->getVariant($flag, $context);
> $weight = $service->getVariantWeight($flag, $context);
> 
> // After (one call → one log entry with weight):
> $result = $service->evaluateForAnalytics($flag, $context);
> $variant = $result['variant'];
> // weight is auto-logged via context['weight']
> ```

> ⚠️ If storing flags in a database, change `default_value` column from `BOOLEAN` to `JSON` to support polymorphic defaults. Laravel's `'json'` cast handles serialization automatically.

### Fixed
- Type safety in evaluation results: `EvaluationResult` prevents accidental misuse of `null`/`bool`/`string` mixups (#JAM-7729)
- Duplicate statistics records: `evaluateForAnalytics()` ensures single log entry per evaluation when tracking both variant and weight (#JAM-7728)
- `PercentageSpecification` correctly handles `0%` and `100%` edge cases
- `TargetIdSpecification::supports()` regex allows no-space syntax (`target_id=101`)
- String comparison in `TargetIdSpecification` supports UUIDs and slugs
- Weight logging now correctly handles `null` for non-percentage rules (#JAM-7727)

### Dev Tools
- Pest ^2.34 for testing
- PHPStan ^1.10 for static analysis (level max)
- Laravel Pint ^1.13 for code formatting
- Composer scripts: `test`, `stan`, `format`, `check`