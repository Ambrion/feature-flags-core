<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Application\Service;

use FeatureFlags\Core\Domain\Repository\FlagRepositoryInterface;
use FeatureFlags\Core\Domain\ValueObject\FlagName;

/**
 * Application Service: оркестратор бизнес-логики фич-флагов.
 * Принимает запрос, делегирует домену/репозиторию, возвращает результат.
 * Минимальная реализация для прохождения первого теста (Красный -> Зелёный).
 */
final readonly class FeatureFlagService
{
    public function __construct(
        private FlagRepositoryInterface $repository
    )
    {
    }

    public function isEnabled(string $flagName): bool
    {
        $flag = $this->repository->findByName(new FlagName($flagName));

        // Если флаг не найден → возвращаем false (дефолт для неизвестного флага)
        return $flag !== null ? $flag->default : false;
    }
}