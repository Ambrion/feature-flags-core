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
     * @param  array<array{condition?: string, value: mixed}>  $rules
     * @param  ConditionSpecificationInterface[]  $specifications
     */
    public function __construct(
        public FlagName $name,
        public bool $default = false,
        public array $rules = [],
        private array $specifications = []
    ) {}

    public function evaluate(EvaluationContext $context): bool
    {
        foreach ($this->rules as $rule) {
            $condition = $rule['condition'] ?? '';

            foreach ($this->specifications as $spec) {
                if ($spec->supports($condition) && $spec->isSatisfiedBy($condition, $context)) {
                    return (bool) ($rule['value'] ?? false);
                }
            }
        }

        return $this->default;
    }

    /**
     * Возвращает вариант флага для A/B-тестирования.
     * Возвращает строковое значение первого сработавшего правила.
     * Возвращает null, если ни одно правило не совпало.
     */
    public function getVariant(EvaluationContext $context): ?string
    {
        foreach ($this->rules as $rule) {
            $condition = $rule['condition'] ?? '';

            foreach ($this->specifications as $spec) {
                if ($spec->supports($condition) && $spec->isSatisfiedBy($condition, $context)) {
                    $value = $rule['value'] ?? null;

                    // Возвращаем только если значение явно строковое (вариант теста)
                    return is_string($value) ? $value : null;
                }
            }
        }

        return null;
    }
}
