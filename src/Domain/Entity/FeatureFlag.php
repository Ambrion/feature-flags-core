<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Entity;

use FeatureFlags\Core\Domain\Specification\ConditionSpecificationInterface;
use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;
use FeatureFlags\Core\Domain\ValueObject\FlagName;
use FeatureFlags\Core\Domain\ValueObject\RuleMatch;

/**
 * Доменная сущность фич-флага.
 * Хранит конфигурацию флага и правила оценки.
 * Живёт в Domain: чистая бизнес-логика, без зависимостей от инфраструктуры.
 */
final readonly class FeatureFlag
{
    /**
     * @param  FlagName  $name  Имя флага
     * @param  bool|string|null  $default  Значение по умолчанию (полиморфное)
     * @param  array<array{condition?: string, value: mixed}>  $rules  Правила оценки
     * @param  ConditionSpecificationInterface[]  $specifications  Спецификации условий
     */
    public function __construct(
        public FlagName $name,
        public mixed $default = false,
        public array $rules = [],
        private array $specifications = []
    ) {}

    /**
     * Приватный хелпер: находит первое сработавшее правило.
     * Возвращает структурированный результат для переиспользования.
     */
    private function findMatchingRule(EvaluationContext $context): RuleMatch
    {
        foreach ($this->rules as $index => $rule) {
            $condition = $rule['condition'] ?? '';

            foreach ($this->specifications as $spec) {
                if ($spec->supports($condition) && $spec->isSatisfiedBy($condition, $context)) {
                    $weight = $this->extractPercentageWeight($condition, $context);

                    return new RuleMatch(
                        value: $rule['value'] ?? null,
                        condition: $condition,
                        weight: $weight,
                        ruleIndex: $index
                    );
                }
            }
        }

        return RuleMatch::none();
    }

    /**
     * Извлекает нормализованный вес из условия вида "user_hash PERCENTAGE 34".
     * Возвращает вес только если пользователь попал в этот процент.
     */
    private function extractPercentageWeight(string $condition, EvaluationContext $context): ?float
    {
        if (! preg_match('/PERCENTAGE\s+(\d{1,3})/i', $condition, $matches)) {
            return null;
        }

        $percentage = (int) $matches[1];

        $hashSource = $context->get('user_hash') ?? $context->get('target_id');
        if ($hashSource === null) {
            return null;
        }

        if (! is_scalar($hashSource)) {
            return null;
        }

        $bucket = abs(crc32((string) $hashSource)) % 100;

        if ($bucket < $percentage) {
            return $percentage / 100.0; // 34 -> 0.34
        }

        return null;
    }

    /**
     * Публичный метод для булевых флагов (фич-тогглы).
     */
    public function evaluate(EvaluationContext $context): bool
    {
        $match = $this->findMatchingRule($context);

        if ($match->matched()) {
            return (bool) $match->value;
        }

        return (bool) $this->default;
    }

    /**
     * Публичный метод для вариантов (A/B-тесты).
     */
    public function getVariant(EvaluationContext $context): ?string
    {
        $match = $this->findMatchingRule($context);

        if ($match->matched() && is_string($match->value)) {
            return $match->value;
        }

        return is_string($this->default) ? (string) $this->default : null;
    }

    /**
     * Возвращает вес варианта для аналитики.
     *
     * @param  EvaluationContext  $context  Контекст оценки
     * @return float|null Нормализованный вес (0.0-1.0) или null
     */
    public function getVariantWeight(EvaluationContext $context): ?float
    {
        $match = $this->findMatchingRule($context);

        if ($match->matched() && $match->weight !== null) {
            return $match->weight;
        }

        return null;
    }
}
