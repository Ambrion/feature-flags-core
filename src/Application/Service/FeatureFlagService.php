<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Application\Service;

use FeatureFlags\Core\Domain\Logger\FlagUsageLoggerInterface;
use FeatureFlags\Core\Domain\Logger\NullFlagUsageLogger;
use FeatureFlags\Core\Domain\Repository\FlagRepositoryInterface;
use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;
use FeatureFlags\Core\Domain\ValueObject\FlagName;

/**
 * Application Service: оркестратор бизнес-логики фич-флагов.
 * Принимает запрос, делегирует домену/репозиторию, возвращает результат.
 * Минимальная реализация для прохождения первого теста (Красный -> Зелёный).
 */
final readonly class FeatureFlagService
{
    public function __construct(
        private FlagRepositoryInterface  $repository,
        private FlagUsageLoggerInterface $logger = new NullFlagUsageLogger()
    )
    {
    }

    public function isEnabled(string $flagName, array $context = []): bool
    {
        $flag = $this->repository->findByName(new FlagName($flagName));

        // 1. Оцениваем флаг
        $result = $flag !== null && $flag->evaluate(EvaluationContext::fromArray($context));

        // 2. Логируем вызов (всегда, даже если логгер — Null Object)
        $this->logger->log($flagName, $result, $context);

        return $result;
    }

    /**
     * Получает вариант флага для A/B-тестирования.
     * Делегирует оценку доменной сущности.
     * Возвращает null, если флаг не найден или правила не сработали.
     */
    public function getVariant(string $flagName, array $context = []): ?string
    {
        $flag = $this->repository->findByName(new FlagName($flagName));

        return $flag?->getVariant(EvaluationContext::fromArray($context));
    }
}