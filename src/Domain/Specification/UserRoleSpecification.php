<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Specification;

use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;

/**
 * Спецификация для условий "user_role=VALUE" и "user_role IN (a,b,c)".
 * Реализует контракт проверки условий.
 */
final class UserRoleSpecification implements ConditionSpecificationInterface
{
    public function supports(string $condition): bool
    {
        // Ловим: user_role=... ИЛИ user_role IN (...)
        return (bool)preg_match('/^user_role\s*(=|IN\s*\()/i', $condition);
    }

    public function isSatisfiedBy(string $condition, EvaluationContext $context): bool
    {
        $role = $context->get('user_role');
        if ($role === null) {
            return false;
        }

        $current = strtolower((string)$role);

        // Поддержка: user_role=manager
        if (preg_match('/^user_role\s*=\s*(\w+)$/i', $condition, $match)) {
            return $current === strtolower($match[1]);
        }

        // Поддержка: user_role IN (manager,admin)
        if (preg_match('/^user_role\s+IN\s*\(([^)]+)\)$/i', $condition, $match)) {
            $allowed = array_map('trim', explode(',', $match[1]));
            $allowedLower = array_map('strtolower', $allowed);

            return in_array($current, $allowedLower, true);
        }

        return false;
    }
}