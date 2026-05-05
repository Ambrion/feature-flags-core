<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Repository;

use FeatureFlags\Core\Domain\Entity\FeatureFlag;
use FeatureFlags\Core\Domain\ValueObject\FlagName;

/**
 * Контракт репозитория фич-флагов.
 * Живёт в Domain Layer: не знает про файлы, БД или фреймворки.
 */
interface FlagRepositoryInterface
{
    /**
     * Находит конфигурацию флага по имени.
     *
     * @param FlagName $flagName Имя флага (Value Object)
     * @return FeatureFlag|null Сущность флага или null, если не найден
     */
    public function findByName(FlagName $flagName): ?FeatureFlag;
}