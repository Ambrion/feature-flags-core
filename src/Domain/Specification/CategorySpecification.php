<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Specification;

use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;

/**
 * Спецификация для условия "category=VALUE".
 * Реализует контракт проверки условий.
 */
final class CategorySpecification implements ConditionSpecificationInterface
{
    public function supports(string $condition): bool
    {
        return str_starts_with($condition, 'category=');
    }

    public function isSatisfiedBy(string $condition, EvaluationContext $context): bool
    {
        $expected = explode('=', $condition, 2)[1] ?? '';

        return $context->get('category') === $expected;
    }
}