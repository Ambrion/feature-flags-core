<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Specification;

use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;

/**
 * Спецификация для условий "target_id IN (id1,id2,...)".
 * Реализует контракт проверки условий.
 * Поддерживает списки ID для таргетинга на конкретные сущности.
 */
final class TargetIdSpecification implements ConditionSpecificationInterface
{
    public function supports(string $condition): bool
    {
        return (bool)preg_match('/^target_id\s+IN\s*\([^)]+\)$/i', $condition);
    }

    public function isSatisfiedBy(string $condition, EvaluationContext $context): bool
    {
        $targetId = $context->get('target_id');
        if ($targetId === null) {
            return false;
        }

        if (!preg_match('/^target_id\s+IN\s*\(([^)]+)\)$/i', $condition, $matches)) {
            return false;
        }

        // Разбиваем список, убираем лишние пробелы
        $allowedIds = array_map('trim', explode(',', $matches[1]));

        // Приводим искомый ID к строке для строгого сравнения
        return in_array((string)$targetId, $allowedIds, true);
    }
}