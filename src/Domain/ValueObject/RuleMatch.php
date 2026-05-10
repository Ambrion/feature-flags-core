<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\ValueObject;

/**
 * @internal Результат сопоставления правила — для внутреннего использования
 *           Не является частью публичного API, может меняться без предупреждения
 */
final readonly class RuleMatch
{
    public function __construct(
        public mixed $value,
        public ?string $condition = null,
        public ?float $weight = null,
        public int $ruleIndex = -1,
    ) {}

    /**
     * Фабричный метод для случая "правило не найдено"
     */
    public static function none(): self
    {
        return new self(value: null, condition: null, weight: null, ruleIndex: -1);
    }

    /**
     * Проверка: сработало ли какое-либо правило
     */
    public function matched(): bool
    {
        return $this->ruleIndex >= 0;
    }
}
