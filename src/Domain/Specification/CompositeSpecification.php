<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Specification;

use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;

/**
 * Simplified composite specification for logical operators: AND, OR, NOT.
 *
 * Precedence: NOT > AND > OR
 * Syntax examples:
 * - "category=electronics AND user_tier=premium"
 * - "user_role!=guest OR environment=dev"
 * - "NOT category=clothing AND user_role=admin"
 *
 * Limitations of this simplified version:
 * - Does not support parentheses for grouping: use De Morgan's laws instead
 *   (e.g., instead of NOT (A AND B), write NOT A OR NOT B)
 * - Operators must be surrounded by whitespace (e.g., "A AND B", not "AANDB")
 */
final readonly class CompositeSpecification implements ConditionSpecificationInterface
{
    /**
     * @param  iterable<ConditionSpecificationInterface>  $specifications
     */
    public function __construct(
        private iterable $specifications
    ) {}

    public function supports(string $condition): bool
    {
        $upper = strtoupper($condition);

        return str_contains($upper, ' AND ')
            || str_contains($upper, ' OR ')
            || str_contains($upper, 'NOT ')
            || str_contains($condition, '!=');
    }

    public function isSatisfiedBy(string $condition, EvaluationContext $context): bool
    {
        // 1. Normalize != to NOT prefix
        $normalized = preg_replace('/([a-zA-Z_]\w*)!=/', 'NOT $1=', $condition);
        if ($normalized === null) {
            $normalized = $condition;
        }

        // 2. Split by OR (lowest precedence)
        $orParts = preg_split('/\s+OR\s+/i', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        if ($orParts === false) {
            $orParts = [$normalized];
        }

        foreach ($orParts as $orPart) {
            // 3. Split each OR part by AND (higher precedence)
            $andParts = preg_split('/\s+AND\s+/i', $orPart, -1, PREG_SPLIT_NO_EMPTY);
            if ($andParts === false) {
                $andParts = [$orPart];
            }

            $allAndTrue = true;
            foreach ($andParts as $andPart) {
                $isNot = false;
                $trimmed = trim($andPart);

                // 4. Handle NOT prefix
                if (stripos($trimmed, 'NOT ') === 0) {
                    $isNot = true;
                    $trimmed = ltrim(substr($trimmed, 4)); // "NOT " = 4 chars
                }

                // 5. Evaluate atomic condition
                $atomicResult = $this->evaluateAtomic($trimmed, $context);

                if ($isNot) {
                    $atomicResult = ! $atomicResult;
                }

                // Short-circuit AND: if any part is false, the whole AND group is false
                if (! $atomicResult) {
                    $allAndTrue = false;
                    break;
                }
            }

            // Short-circuit OR: if any AND group is true, the whole expression is true
            if ($allAndTrue) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delegates evaluation of a single atomic condition to the first matching specification.
     */
    private function evaluateAtomic(string $condition, EvaluationContext $context): bool
    {
        foreach ($this->specifications as $spec) {
            // Skip self-reference to prevent infinite recursion
            if ($spec instanceof self) {
                continue;
            }

            if ($spec->supports($condition) && $spec->isSatisfiedBy($condition, $context)) {
                return true;
            }
        }

        // Fail-safe: unsupported atomic condition is treated as false
        return false;
    }
}
