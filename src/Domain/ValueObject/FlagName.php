<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object для имени флага.
 * Гарантирует: непустая строка в snake_case.
 */
final readonly class FlagName
{
    public function __construct(
        public string $value
    )
    {
        if ($value === '') {
            throw new InvalidArgumentException('Flag name cannot be empty');
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException(
                "Invalid flag name '{$value}'. Use snake_case (e.g. show_new_year_banner)."
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}