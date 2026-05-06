<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Logger;

/**
 * Контракт для логирования использования флагов.
 * Живёт в Domain: не знает про БД, файлы или внешние сервисы.
 * Определяет только "что логировать", но не "куда и как".
 */
interface FlagUsageLoggerInterface
{
    /**
     * Логирует вызов флага.
     *
     * @param  string  $flagName  Имя флага
     * @param  bool  $result  Результат оценки (true/false)
     * @param  array<string, scalar|null>  $context  Контекст вызова (адаптер должен фильтровать чувствительные данные)
     */
    public function log(string $flagName, bool $result, array $context = []): void;

    /**
     * Логирует вызов A/B-теста (мульти-вариантного флага).
     *
     * @param  string  $flagName  Имя флага
     * @param  string|null  $variant  Выбранный вариант или null
     * @param  array<string, scalar|null>  $context  Контекст вызова
     */
    public function logVariant(string $flagName, ?string $variant, array $context = []): void;
}
