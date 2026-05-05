<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Entity;

use FeatureFlags\Core\Domain\ValueObject\FlagName;

/**
 * Доменная сущность фич-флага.
 * Хранит конфигурацию флага и правила оценки.
 * Живёт в Domain: чистая бизнес-логика, без зависимостей от инфраструктуры.
 */
final readonly class FeatureFlag
{
    public function __construct(
        public FlagName $name,
        public bool     $default = false,
        public array    $rules = []
    )
    {
    }

    public function evaluate(array $context): bool
    {
        foreach ($this->rules as $rule) {
            $condition = $rule['condition'] ?? '';
            $value = $rule['value'] ?? false;

            if (preg_match('/^category=(.+)$/', $condition, $matches)) {
                $expectedCategory = $matches[1];
                if (isset($context['category']) && $context['category'] === $expectedCategory) {
                    return (bool) $value;
                }
            }
        }

        // Если ни одно правило не совпало — возвращаем дефолт
        return $this->default;
    }
}