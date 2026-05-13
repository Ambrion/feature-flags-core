<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Tests\Unit\Domain\Entity;

use FeatureFlags\Core\Domain\Entity\FeatureFlag;
use FeatureFlags\Core\Domain\Specification\CategorySpecification;
use FeatureFlags\Core\Domain\Specification\PercentageSpecification;
use FeatureFlags\Core\Domain\Specification\UserRoleSpecification;
use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;
use FeatureFlags\Core\Domain\ValueObject\FlagName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FeatureFlagTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────
    // Тесты для getMatchedRuleCondition() (уже были)
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_get_matched_rule_condition_returns_condition_when_rule_matches(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('test_flag'),
            default: false,
            rules: [
                ['condition' => 'user_role=admin', 'value' => true],
                ['condition' => 'user_hash PERCENTAGE 33', 'value' => 'B'],
            ],
            specifications: [
                new UserRoleSpecification,
                new PercentageSpecification,
            ]
        );

        $context = EvaluationContext::fromArray(['user_role' => 'admin']);
        $matchedRule = $flag->getMatchedRuleCondition($context);

        $this->assertEquals('user_role=admin', $matchedRule);
    }

    #[Test]
    public function test_get_matched_rule_condition_returns_null_when_no_rule_matches(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('test_flag'),
            default: false,
            rules: [['condition' => 'user_role=admin', 'value' => true]],
            specifications: [new UserRoleSpecification]
        );

        $context = EvaluationContext::fromArray(['user_role' => 'guest']);
        $matchedRule = $flag->getMatchedRuleCondition($context);

        $this->assertNull($matchedRule);
    }

    // ─────────────────────────────────────────────────────────────
    // 🔹 Тесты для evaluate() — булевы флаги
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_evaluate_returns_true_when_matching_rule_has_true_value(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('show_banner'),
            default: false,
            rules: [['condition' => 'user_role=admin', 'value' => true]],
            specifications: [new UserRoleSpecification]
        );

        $context = EvaluationContext::fromArray(['user_role' => 'admin']);
        $result = $flag->evaluate($context);

        $this->assertTrue($result);
    }

    #[Test]
    public function test_evaluate_returns_false_when_matching_rule_has_false_value(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('disable_feature'),
            default: true,
            rules: [['condition' => 'category=production', 'value' => false]],
            specifications: [new CategorySpecification]
        );

        $context = EvaluationContext::fromArray(['category' => 'production']);
        $result = $flag->evaluate($context);

        $this->assertFalse($result);
    }

    #[Test]
    public function test_evaluate_returns_default_when_no_rules_match(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('fallback_flag'),
            default: true,
            rules: [['condition' => 'user_role=admin', 'value' => false]],
            specifications: [new UserRoleSpecification]
        );

        $context = EvaluationContext::fromArray(['user_role' => 'guest']);
        $result = $flag->evaluate($context);

        $this->assertTrue($result); // вернулось default
    }

    #[Test]
    public function test_evaluate_returns_default_when_no_rules_defined(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('empty_rules_flag'),
            default: false,
            rules: [],
            specifications: []
        );

        $context = EvaluationContext::fromArray(['any' => 'value']);
        $result = $flag->evaluate($context);

        $this->assertFalse($result);
    }

    #[Test]
    public function test_evaluate_uses_first_matching_rule_short_circuit(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('priority_flag'),
            default: false,
            rules: [
                ['condition' => 'user_role=admin', 'value' => true],   // 1. высший приоритет
                ['condition' => 'category=electronics', 'value' => false], // 2. ниже
            ],
            specifications: [new UserRoleSpecification, new CategorySpecification]
        );

        // Контекст удовлетворяет обоим правилам, но первое должно победить
        $context = EvaluationContext::fromArray([
            'user_role' => 'admin',
            'category' => 'electronics',
        ]);
        $result = $flag->evaluate($context);

        $this->assertTrue($result); // правило 1 сработало первым
    }

    // ─────────────────────────────────────────────────────────────
    // 🔹 Тесты для getVariant() — A/B-тесты
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_get_variant_returns_string_when_matching_rule_has_string_value(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('header_test'),
            default: 'A',
            rules: [['condition' => 'user_hash PERCENTAGE 100', 'value' => 'B']],
            specifications: [new PercentageSpecification]
        );

        $context = EvaluationContext::fromArray(['user_hash' => 'any_hash']);
        $variant = $flag->getVariant($context);

        $this->assertSame('B', $variant);
    }

    #[Test]
    public function test_get_variant_returns_null_when_matching_rule_has_non_string_value(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('mixed_flag'),
            default: 'control',
            rules: [['condition' => 'user_role=admin', 'value' => true]], // bool, не string
            specifications: [new UserRoleSpecification]
        );

        $context = EvaluationContext::fromArray(['user_role' => 'admin']);
        $variant = $flag->getVariant($context);

        // Правило сработало, но значение не строка -> игнорируем, возвращаем дефолт
        $this->assertSame('control', $variant);
    }

    #[Test]
    public function test_get_variant_returns_string_default_when_no_rules_match(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('ab_with_default'),
            default: 'A', // строковый дефолт
            rules: [['condition' => 'user_role=admin', 'value' => 'B']],
            specifications: [new UserRoleSpecification]
        );

        $context = EvaluationContext::fromArray(['user_role' => 'guest']);
        $variant = $flag->getVariant($context);

        $this->assertSame('A', $variant); // вернулось строковое default
    }

    #[Test]
    public function test_get_variant_returns_null_when_default_is_not_string_and_no_rules_match(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('bool_default_flag'),
            default: true, // булев дефолт
            rules: [['condition' => 'user_role=admin', 'value' => 'B']],
            specifications: [new UserRoleSpecification]
        );

        $context = EvaluationContext::fromArray(['user_role' => 'guest']);
        $variant = $flag->getVariant($context);

        $this->assertNull($variant); // нет строкового дефолта -> null
    }

    #[Test]
    public function test_get_variant_returns_null_when_no_rules_and_no_string_default(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('empty_ab_flag'),
            default: false,
            rules: [],
            specifications: []
        );

        $context = EvaluationContext::fromArray(['any' => 'value']);
        $variant = $flag->getVariant($context);

        $this->assertNull($variant);
    }

    // ─────────────────────────────────────────────────────────────
    // 🔹 Тесты для getVariantWeight() — аналитика весов
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_get_variant_weight_returns_decimal_when_percentage_rule_matches(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('weighted_ab'),
            default: 'A',
            rules: [['condition' => 'user_hash PERCENTAGE 25', 'value' => 'B']],
            specifications: [new PercentageSpecification]
        );

        // Хеш, который гарантированно попадает в 25%
        $hashInRule = $this->findHashForPercentageRule(25, true);
        $context = EvaluationContext::fromArray(['user_hash' => $hashInRule]);
        $weight = $flag->getVariantWeight($context);

        $this->assertEquals(0.25, $weight);
    }

    #[Test]
    public function test_get_variant_weight_returns_null_when_percentage_rule_does_not_match(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('partial_rollout'),
            default: 'A',
            rules: [['condition' => 'user_hash PERCENTAGE 25', 'value' => 'B']],
            specifications: [new PercentageSpecification]
        );

        // Хеш, который гарантированно НЕ попадает в 25%
        $hashOutOfRule = $this->findHashForPercentageRule(25, false);
        $context = EvaluationContext::fromArray(['user_hash' => $hashOutOfRule]);
        $weight = $flag->getVariantWeight($context);

        $this->assertNull($weight);
    }

    #[Test]
    public function test_get_variant_weight_returns_null_for_non_percentage_rule(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('role_flag'),
            default: 'A',
            rules: [['condition' => 'user_role=admin', 'value' => 'B']],
            specifications: [new UserRoleSpecification]
        );

        $context = EvaluationContext::fromArray(['user_role' => 'admin']);
        $weight = $flag->getVariantWeight($context);

        $this->assertNull($weight); // правило не процентное -> веса нет
    }

    #[Test]
    public function test_get_variant_weight_returns_null_when_no_rules_match(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('no_match_weight'),
            default: 'A',
            rules: [['condition' => 'user_role=admin', 'value' => 'B']],
            specifications: [new UserRoleSpecification]
        );

        $context = EvaluationContext::fromArray(['user_role' => 'guest']);
        $weight = $flag->getVariantWeight($context);

        $this->assertNull($weight);
    }

    #[Test]
    public function test_get_variant_weight_is_deterministic_for_same_hash(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('deterministic_weight'),
            default: 'A',
            rules: [['condition' => 'user_hash PERCENTAGE 50', 'value' => 'B']],
            specifications: [new PercentageSpecification]
        );

        $userHash = 'consistent_hash_abc123';
        $context = EvaluationContext::fromArray(['user_hash' => $userHash]);

        // 20 вызовов с одним хешом
        $weights = array_map(
            fn () => $flag->getVariantWeight($context),
            range(1, 20)
        );

        // Все веса должны быть идентичны
        $this->assertCount(1, array_unique($weights), 'Weight must be deterministic for same hash');
    }

    // ─────────────────────────────────────────────────────────────
    // 🔹 Интеграционные тесты: совместное использование методов
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_evaluate_and_get_variant_and_get_variant_weight_can_be_called_together(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('combined_flag'),
            default: 'A',
            rules: [['condition' => 'user_hash PERCENTAGE 30', 'value' => 'B']],
            specifications: [new PercentageSpecification]
        );

        $hashInRule = $this->findHashForPercentageRule(30, true);
        $context = EvaluationContext::fromArray(['user_hash' => $hashInRule]);

        // Вызываем все три метода с одним контекстом
        $enabled = $flag->evaluate($context);
        $variant = $flag->getVariant($context);
        $weight = $flag->getVariantWeight($context);

        // Все результаты должны быть согласованы
        $this->assertTrue($enabled); // правило сработало -> true
        $this->assertSame('B', $variant); // строковое значение правила
        $this->assertEquals(0.30, $weight); // вес процентного правила
    }

    #[Test]
    public function test_get_matched_rule_condition_returns_correct_condition_for_matched_rule(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('debug_flag'),
            default: false,
            rules: [
                ['condition' => 'category=electronics', 'value' => true],
                ['condition' => 'user_hash PERCENTAGE 50', 'value' => true],
            ],
            specifications: [new CategorySpecification, new PercentageSpecification]
        );

        // Контекст, который удовлетворяет первому правилу
        $context = EvaluationContext::fromArray(['category' => 'electronics', 'user_hash' => 'any']);
        $matchedRule = $flag->getMatchedRuleCondition($context);

        $this->assertEquals('category=electronics', $matchedRule);
    }

    #[Test]
    public function test_evaluate_ignores_rule_with_unsupported_condition(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('mismatch_flag'),
            default: true,
            // Это условие НЕ поддерживается CategorySpecification
            rules: [['condition' => 'environment=production', 'value' => false]],
            specifications: [new CategorySpecification]
        );

        $context = EvaluationContext::fromArray(['environment' => 'production']);
        $result = $flag->evaluate($context);

        // Правило проигнорировано -> вернулось default
        $this->assertTrue($result);
    }

    /**
     * Находит строку, которая даёт бакет в нужном диапазоне для PERCENTAGE-правила.
     */
    private function findHashForPercentageRule(int $percentage, bool $shouldMatch, string $prefix = 'test_'): string
    {
        for ($i = 0; $i < 100000; $i++) {
            $candidate = $prefix.$i;
            $bucket = abs(crc32($candidate)) % 100;

            if ($shouldMatch && $bucket < $percentage) {
                return $candidate;
            }
            if (! $shouldMatch && $bucket >= $percentage) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            "Could not find hash for percentage=$percentage, shouldMatch=".($shouldMatch ? 'true' : 'false')
        );
    }
}
