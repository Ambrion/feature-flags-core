<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Entity;

use FeatureFlags\Core\Domain\Specification\ConditionSpecificationInterface;
use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;
use FeatureFlags\Core\Domain\ValueObject\FlagName;

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
     * Приватный хелпер: находит значение первого сработавшего правила.
     * Возвращает сырое mixed-значение или null, если правила не совпали.
     *
     * @return mixed|null Значение из правила или null
     */
    private function findMatchingRuleValue(EvaluationContext $context): mixed
    {
        foreach ($this->rules as $rule) {
            $condition = $rule['condition'] ?? '';

            foreach ($this->specifications as $spec) {
                if ($spec->supports($condition) && $spec->isSatisfiedBy($condition, $context)) {
                    return $rule['value'] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Публичный метод для булевых флагов (фич-тогглы).
     * Всегда возвращает bool — безопасно для if/else.
     *
     * @param  EvaluationContext  $context  Контекст оценки
     * @return bool Результат оценки флага
     */
    public function evaluate(EvaluationContext $context): bool
    {
        $matchedValue = $this->findMatchingRuleValue($context);

        if ($matchedValue !== null) {
            return (bool) $matchedValue;
        }

        return (bool) $this->default;
    }

    /**
     * Публичный метод для вариантов (A/B-тесты)
     * Возвращает строку-вариант или null — безопасно для match/switch
     *
     * @param  EvaluationContext  $context  Контекст оценки
     * @return string|null Название варианта или null
     */
    public function getVariant(EvaluationContext $context): ?string
    {
        $matchedValue = $this->findMatchingRuleValue($context);

        if (is_string($matchedValue)) {
            return $matchedValue;
        }

        return is_string($this->default) ? (string) $this->default : null;
    }
}
