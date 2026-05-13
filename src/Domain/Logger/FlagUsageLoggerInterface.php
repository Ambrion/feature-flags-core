<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Logger;

use FeatureFlags\Core\Domain\ValueObject\EvaluationResult;

/**
 * Контракт для логирования использования флагов.
 * Живёт в Domain: не знает про БД, файлы или внешние сервисы.
 * Определяет только "что логировать", но не "куда и как".
 */
interface FlagUsageLoggerInterface
{
    /**
     * Логирование вызов флага.
     *
     * @param  array<string, scalar|null>  $context  Контекст вызова
     */
    public function logEvaluation(string $flagName, EvaluationResult $result, array $context = []): void;
}
