<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Application\Service;

use FeatureFlags\Core\Domain\Logger\FlagUsageLoggerInterface;
use FeatureFlags\Core\Domain\Logger\NullFlagUsageLogger;
use FeatureFlags\Core\Domain\Repository\FlagRepositoryInterface;
use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;
use FeatureFlags\Core\Domain\ValueObject\EvaluationResult;
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
     * Метод оценки флага (источник истины).
     * Возвращает структурированный результат со всеми данными для аналитики.
     *
     * @param  string  $flagName  Имя флага
     * @param  array<string, scalar|null>  $context  Контекст оценки
     * @return EvaluationResult Структурированный результат
     */
    public function evaluate(string $flagName, array $context = []): EvaluationResult
    {
        $flag = $this->repository->findByName(new FlagName($flagName));
        $evaluationContext = EvaluationContext::fromArray($context);

        if ($flag === null) {
            $result = EvaluationResult::notFound();
        } else {
            $result = EvaluationResult::success(
                enabled: $flag->evaluate($evaluationContext),
                variant: $flag->getVariant($evaluationContext),
                weight: $flag->getVariantWeight($evaluationContext),
                matchedRule: $flag->getMatchedRuleCondition($evaluationContext),
            );
        }

        // Единый вызов логгера
        $this->logger->logEvaluation($flagName, $result, $context);

        return $result;
    }

    /**
     * Проверяет, включен ли флаг для заданного контекста.
     * Удобный метод для булевых проверок.
     *
     * @param  string  $flagName  Имя флага
     * @param  array<string, scalar|null>  $context  Контекст оценки
     * @return bool true, если флаг включён
     */
    public function isEnabled(string $flagName, array $context = []): bool
    {
        return $this->evaluate($flagName, $context)->enabled ?? false;
    }

    /**
     * Получает вариант флага для A/B-тестирования.
     * Удобный метод, когда нужен только вариант.
     *
     * @param  string  $flagName  Имя флага
     * @param  array<string, scalar|null>  $context  Контекст оценки
     * @return string|null Вариант ('A', 'B', ...) или null
     */
    public function getVariant(string $flagName, array $context = []): ?string
    {
        return $this->evaluate($flagName, $context)->variant;
    }

    /**
     * Получает вес варианта флага для аналитики.
     * Удобный метод, когда нужен только вес.
     *
     * @param  string  $flagName  Имя флага
     * @param  array<string, scalar|null>  $context  Контекст оценки
     * @return float|null Нормализованный вес (0.0-1.0) или null
     */
    public function getVariantWeight(string $flagName, array $context = []): ?float
    {
        return $this->evaluate($flagName, $context)->weight;
    }

    /**
     * Оценивает флаг для A/B-теста и возвращает вариант с весом.
     * Удобный метод для аналитики: одна запись в БД с вариантом и весом.
     *
     * @param  string  $flagName  Имя флага
     * @param  array<string, scalar|null>  $context  Контекст оценки
     * @return array{variant: string|null, weight: float|null}
     */
    public function evaluateForAnalytics(string $flagName, array $context = []): array
    {
        $result = $this->evaluate($flagName, $context);

        return ['variant' => $result->variant, 'weight' => $result->weight];
    }
}
