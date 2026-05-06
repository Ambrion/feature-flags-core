<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Logger;

/**
 * Null Object Pattern: "пустой" логгер, который ничего не делает.
 * Используется по умолчанию в сервисах и тестах.
 * Гарантирует, что сервис всегда может вызвать метод без проверок if ($logger)
 */
final class NullFlagUsageLogger implements FlagUsageLoggerInterface
{
    public function log(string $flagName, bool $result, array $context = []): void
    {
        // Намеренно пусто: не логируем, не падаем, не шумим
    }

    public function logVariant(string $flagName, ?string $variant, array $context = []): void
    {
    }
}