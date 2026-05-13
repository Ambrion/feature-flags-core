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
 */
final readonly class FeatureFlagService
{
    public function __construct(
        private FlagRepositoryInterface $repository,
        private FlagUsageLoggerInterface $logger = new NullFlagUsageLogger
    ) {}

    /**
     * Проверяет, включен ли флаг для заданного контекста.
     *
     * @param  string  $flagName  Имя флага
     * @param  array<string, scalar|null>  $context  Контекст оценки (ключ => значение)
     */
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
     *
     * @param  string  $flagName  Имя флага
     * @param  array<string, scalar|null>  $context  Контекст оценки (ключ => значение)
     */
    public function getVariant(string $flagName, array $context = []): ?string
    {
        $flag = $this->repository->findByName(new FlagName($flagName));

        // 1. Оцениваем вариант
        $variant = $flag?->getVariant(EvaluationContext::fromArray($context));

        // 2. Логируем выбор варианта (даже если это null)
        $this->logger->logVariant($flagName, $variant, $context);

        return $variant;
    }

    /**
     * Получает вес варианта флага для аналитики.
     * Возвращает нормализованный вес (0.0-1.0) процентного правила,
     * или null, если правило не процентное / не сработало.
     *
     * @param  string  $flagName  Имя флага
     * @param  array<string, scalar|null>  $context  Контекст оценки
     * @return float|null Вес варианта или null
     */
    public function getVariantWeight(string $flagName, array $context = []): ?float
    {
        $flag = $this->repository->findByName(new FlagName($flagName));

        // Делегируем вычисление веса доменной сущности
        $weight = $flag?->getVariantWeight(EvaluationContext::fromArray($context));

        // Логирование веса
        $this->logger->logWeight($flagName, $weight, $context);

        return $weight;
    }
}
