<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Specification;

use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;

/**
 * Спецификация для условий "user_hash PERCENTAGE N".
 * Реализует контракт проверки условий.
 * Детерминированное распределение: один хеш всегда попадает в один процент.
 * Использует crc32 для быстрого преобразования строки в число 0-99.
 */
final class PercentageSpecification implements ConditionSpecificationInterface
{
    public function supports(string $condition): bool
    {
        // Ловим: <ключ> PERCENTAGE <0-100>
        return (bool)preg_match('/^(\w+)\s+PERCENTAGE\s+\d{1,3}$/i', $condition);
    }

    public function isSatisfiedBy(string $condition, EvaluationContext $context): bool
    {
        if (!preg_match('/^(\w+)\s+PERCENTAGE\s+(\d{1,3})$/i', $condition, $matches)) {
            return false;
        }

        $contextKey = $matches[1];
        $percentage = (int)$matches[2];

        $hashValue = $context->get($contextKey);
        if ($hashValue === null) {
            return false;
        }

        // Детерминированное преобразование хеша в число 0-99
        // abs() гарантирует неотрицательное значение на всех архитектурах PHP
        $bucket = abs(crc32((string)$hashValue)) % 100;

        // Пример: PERCENTAGE 100 → bucket < 100 → всегда true
        // Пример: PERCENTAGE 50  → bucket < 50  → ~50% трафика
        return $bucket < $percentage;
    }
}