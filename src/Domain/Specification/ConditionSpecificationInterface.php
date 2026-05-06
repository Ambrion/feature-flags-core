<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Domain\Specification;

use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;

/**
 * Контракт для проверки условий фич-флагов.
 * Живёт в Domain: не знает про БД, файлы или фреймворки.
 * Отвечает на два вопроса: "Поддерживаю ли я это условие?" и "Выполнено ли оно?"
 */
interface ConditionSpecificationInterface
{
    /**
     * Проверяет, может ли эта спецификация обработать данное условие.
     *
     * @param  string  $condition  Строка условия, например "category=electronics"
     */
    public function supports(string $condition): bool;

    /**
     * Проверяет, выполнено ли условие в данном контексте.
     *
     * @param  string  $condition  Строка условия
     * @param  EvaluationContext  $context  Контекст оценки
     */
    public function isSatisfiedBy(string $condition, EvaluationContext $context): bool;
}
