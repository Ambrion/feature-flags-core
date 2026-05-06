<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object для контекста оценки фич-флага.
 * Инкапсулирует массив данных с минимальной валидацией.
 */
final readonly class EvaluationContext
{
    /**
     * @param array<string, scalar|null> $data
     */
    public function __construct(
        private array $data = []
    ) {
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Context keys must be strings');
            }

            /** @phpstan-ignore-next-line */
            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException('Context values must be scalar or null');
            }
        }
    }


    /**
     * @param array<string, scalar|null> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function equals(self $other): bool
    {
        return $this->data === $other->data;
    }
}