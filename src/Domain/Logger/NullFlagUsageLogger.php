<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Logger;

/**
 * Null Object Pattern: "пустой" логгер, который ничего не делает.
 * Используется по умолчанию в сервисах и тестах.
 * Позволяет вызывать $logger->log() без проверок if ($logger !== null).
 */
final class NullFlagUsageLogger implements FlagUsageLoggerInterface
{
    public function log(string $flagName, bool $result, array $context = []): void
    {
        // Намеренно пусто: не логируем, не падаем, не шумим
    }
}