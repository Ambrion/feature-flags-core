<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\ValueObject;

/**
 * Value Object для имени флага.
 * Гарантирует, что имя флага — непустая строка.
 * Живёт в Domain: не знает ни про БД, ни про фреймворки.
 * Минимальная реализация для текущей Красной-фазы.
 */
final readonly class FlagName
{
    public function __construct(
        public string $value
    )
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Flag name cannot be empty');
        }
    }
}