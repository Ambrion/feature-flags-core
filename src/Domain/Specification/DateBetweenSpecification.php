<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Specification;

use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;
use DateTimeImmutable;

/**
 * Спецификация для условий "current_date BETWEEN MM-DD AND MM-DD".
 * Реализует контракт проверки условий.
 * Поддерживает сезонные диапазоны (в т.ч. через смену года: 12-20 AND 01-10).
 */
final class DateBetweenSpecification implements ConditionSpecificationInterface
{
    public function supports(string $condition): bool
    {
        return (bool)preg_match('/^current_date\s+BETWEEN\s+\d{2}-\d{2}\s+AND\s+\d{2}-\d{2}$/i', $condition);
    }

    public function isSatisfiedBy(string $condition, EvaluationContext $context): bool
    {
        $currentDateStr = $context->get('current_date');
        if ($currentDateStr === null) {
            return false;
        }

        // Парсим: current_date BETWEEN MM-DD AND MM-DD
        if (!preg_match('/^current_date\s+BETWEEN\s+(\d{2}-\d{2})\s+AND\s+(\d{2}-\d{2})$/i', $condition, $matches)) {
            return false;
        }

        $startMd = $matches[1];
        $endMd = $matches[2];

        // Текущая дата из контекста
        $current = DateTimeImmutable::createFromFormat('Y-m-d', $currentDateStr);
        if (!$current) {
            return false;
        }

        $year = $current->format('Y');
        $start = DateTimeImmutable::createFromFormat('Y-m-d', "{$year}-{$startMd}");
        $end = DateTimeImmutable::createFromFormat('Y-m-d', "{$year}-{$endMd}");

        // Обработка диапазонов через смену года (напр. 12-20 AND 01-10)
        if ($start > $end) {
            $end = $end->modify('+1 year');
        }

        return $current >= $start && $current <= $end;
    }
}