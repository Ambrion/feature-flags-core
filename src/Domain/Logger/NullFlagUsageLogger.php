<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Logger;

use FeatureFlags\Core\Domain\ValueObject\EvaluationResult;

/**
 * Null Object Pattern: "пустой" логгер, который ничего не делает.
 * Используется по умолчанию в сервисах и тестах.
 * Гарантирует, что сервис всегда может вызвать метод без проверок if ($logger)
 */
final class NullFlagUsageLogger implements FlagUsageLoggerInterface
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public function logEvaluation(string $flagName, EvaluationResult $result, array $context = []): void {}
}
