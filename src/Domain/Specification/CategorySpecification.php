<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Specification;

use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;

/**
 * Спецификация для условий "category=VALUE" и "category IN (a,b,c)".
 * Реализует контракт проверки условий.
 * Минимальная логика: парсинг строки + нестрогое сравнение (case-insensitive).
 */
final class CategorySpecification implements ConditionSpecificationInterface
{
    public function supports(string $condition): bool
    {
        // Ловим: category=... ИЛИ category IN (...)
        return (bool)preg_match('/^category\s*(=|IN\s*\()/i', $condition);
    }

    public function isSatisfiedBy(string $condition, EvaluationContext $context): bool
    {
        $category = $context->get('category');
        if ($category === null) {
            return false;
        }

        if (!is_string($category) && !is_numeric($category)) {
            return false;
        }

        $current = strtolower((string)$category);

        // Поддержка: category=electronics
        if (preg_match('/^category\s*=\s*(\w+)$/i', $condition, $match)) {
            return $current === strtolower($match[1]);
        }

        // Поддержка: category IN (electronics, phones)
        if (preg_match('/^category\s+IN\s*\(([^)]+)\)$/i', $condition, $match)) {
            $allowed = array_map('trim', explode(',', $match[1]));
            $allowedLower = array_map('strtolower', $allowed);

            return in_array($current, $allowedLower, true);
        }

        return false;
    }
}