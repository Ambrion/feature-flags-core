<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\ValueObject;

/**
 * Результат оценки флага — агрегат всех данных для аналитики.
 * Расширяем без изменения интерфейсов: просто добавляем свойства.
 */
final readonly class EvaluationResult
{
    public function __construct(
        public ?bool $enabled = null,
        public ?string $variant = null,
        public ?float $weight = null,
        public ?string $matchedRule = null,
    ) {}

    public static function success(
        ?bool $enabled = null,
        ?string $variant = null,
        ?float $weight = null,
        ?string $matchedRule = null
    ): self {
        return new self($enabled, $variant, $weight, $matchedRule);
    }

    public static function notFound(): self
    {
        return new self;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return array_filter([
            'enabled' => $this->enabled,
            'variant' => $this->variant,
            'weight' => $this->weight,
            'matchedRule' => $this->matchedRule,
        ], fn ($v) => $v !== null);
    }
}
