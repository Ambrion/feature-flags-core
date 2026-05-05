<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Entity;

use FeatureFlags\Core\Domain\ValueObject\FlagName;

/**
 * Доменная сущность фич-флага.
 * Хранит конфигурацию флага и правила оценки.
 * Живёт в Domain: чистая бизнес-логика, без зависимостей от инфраструктуры.
 * Минимальная реализация для текущей Красной-фазы.
 */
final readonly class FeatureFlag
{
    public function __construct(
        public FlagName $name,
        public bool     $default = false,
        // Правила и спецификации добавим позже, когда тесты потребуют
    )
    {
    }
}