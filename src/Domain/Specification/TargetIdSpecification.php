<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Specification;

use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;

/**
 * Спецификация для условий "target_id=VALUE" и "target_id IN (id1,id2,...)".
 * Реализует контракт проверки условий.
 * Поддерживает точное совпадение и списки для таргетинга на сущности.
 */
final class TargetIdSpecification implements ConditionSpecificationInterface
{
    public function supports(string $condition): bool
    {
        return (bool)preg_match('/^target_id\s*(IN\s*\([^)]+\)|=\s*[\w-]+)$/i', $condition);
    }

    public function isSatisfiedBy(string $condition, EvaluationContext $context): bool
    {
        $targetId = $context->get('target_id');
        if ($targetId === null) {
            return false;
        }
        
        $targetIdStr = (string)$targetId;

        // Точное совпадение: target_id=VALUE
        if (preg_match('/^target_id\s*=\s*([\w-]+)$/i', $condition, $matches)) {
            return $targetIdStr === $matches[1];
        }

        // Список: target_id IN (id1, id2, ...)
        if (preg_match('/^target_id\s+IN\s*\(([^)]+)\)$/i', $condition, $matches)) {
            $allowedIds = array_map('trim', explode(',', $matches[1]));

            return in_array($targetIdStr, $allowedIds, true);
        }

        return false;
    }
}