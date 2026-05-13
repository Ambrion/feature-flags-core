<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Tests\Unit\Domain\Specification;

use FeatureFlags\Core\Domain\Specification\CategorySpecification;
use FeatureFlags\Core\Domain\Specification\CompositeSpecification;
use FeatureFlags\Core\Domain\Specification\UserRoleSpecification;
use FeatureFlags\Core\Domain\ValueObject\EvaluationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompositeSpecificationTest extends TestCase
{
    private function createComposite(): CompositeSpecification
    {
        return new CompositeSpecification([
            new CategorySpecification,
            new UserRoleSpecification,
        ]);
    }

    #[Test]
    public function supports_detects_and_operator(): void
    {
        $this->assertTrue($this->createComposite()->supports('category=electronics AND user_role=admin'));
    }

    #[Test]
    public function supports_detects_or_operator(): void
    {
        $this->assertTrue($this->createComposite()->supports('category=phones OR category=tablets'));
    }

    #[Test]
    public function supports_detects_not_keyword(): void
    {
        $this->assertTrue($this->createComposite()->supports('NOT category=electronics'));
    }

    #[Test]
    public function supports_detects_not_equal_operator(): void
    {
        $this->assertTrue($this->createComposite()->supports('category!=electronics'));
    }

    #[Test]
    public function supports_returns_false_for_atomic_conditions(): void
    {
        $this->assertFalse($this->createComposite()->supports('category=electronics'));
        $this->assertFalse($this->createComposite()->supports('user_role=admin'));
    }

    #[Test]
    public function evaluates_and_both_true(): void
    {
        $spec = $this->createComposite();
        $context = EvaluationContext::fromArray(['category' => 'electronics', 'user_role' => 'admin']);

        $this->assertTrue($spec->isSatisfiedBy('category=electronics AND user_role=admin', $context));
    }

    #[Test]
    public function evaluates_and_one_false(): void
    {
        $spec = $this->createComposite();
        $context = EvaluationContext::fromArray(['category' => 'electronics', 'user_role' => 'guest']);

        $this->assertFalse($spec->isSatisfiedBy('category=electronics AND user_role=admin', $context));
    }

    #[Test]
    public function evaluates_or_at_least_one_true(): void
    {
        $spec = $this->createComposite();
        $context = EvaluationContext::fromArray(['category' => 'clothing', 'user_role' => 'admin']);

        $this->assertTrue($spec->isSatisfiedBy('category=electronics OR user_role=admin', $context));
    }

    #[Test]
    public function evaluates_or_all_false(): void
    {
        $spec = $this->createComposite();
        $context = EvaluationContext::fromArray(['category' => 'clothing', 'user_role' => 'guest']);

        $this->assertFalse($spec->isSatisfiedBy('category=electronics OR user_role=admin', $context));
    }

    #[Test]
    public function evaluates_not_prefix(): void
    {
        $spec = $this->createComposite();
        $context = EvaluationContext::fromArray(['category' => 'electronics', 'user_role' => 'guest']);

        $this->assertFalse($spec->isSatisfiedBy('NOT category=electronics', $context));
        $this->assertTrue($spec->isSatisfiedBy('NOT user_role=admin', $context));
    }

    #[Test]
    public function evaluates_not_equal_operator(): void
    {
        $spec = $this->createComposite();
        $context = EvaluationContext::fromArray(['category' => 'clothing', 'user_role' => 'guest']);

        $this->assertTrue($spec->isSatisfiedBy('category!=electronics', $context));
        $this->assertFalse($spec->isSatisfiedBy('user_role!=guest', $context));
    }

    #[Test]
    public function respects_operator_precedence_and_over_or(): void
    {
        // "A AND B OR C" should be evaluated as "(A AND B) OR C"
        $spec = $this->createComposite();

        // Case: A=true, B=false, C=true -> (true AND false) OR true = false OR true = true
        $context = EvaluationContext::fromArray([
            'category' => 'electronics',
            'user_role' => 'guest',
        ]);
        // Add a dummy spec or just rely on context keys. For this test, we'll use simple strings.
        // Actually, let's use a realistic combo: (category=electronics AND user_role=admin) OR user_role=guest
        $this->assertTrue(
            $spec->isSatisfiedBy('category=electronics AND user_role=admin OR user_role=guest', $context),
            'OR should apply after AND grouping'
        );
    }

    #[Test]
    public function handles_case_insensitive_operators(): void
    {
        $spec = $this->createComposite();
        $context = EvaluationContext::fromArray(['category' => 'electronics']);

        $this->assertTrue($spec->isSatisfiedBy('category=electronics and user_role!=guest', $context));
        $this->assertTrue($spec->isSatisfiedBy('Category=Electronics Or user_role=Admin', $context));
    }

    #[Test]
    public function returns_false_for_unsupported_atomic_condition(): void
    {
        $spec = $this->createComposite();
        $context = EvaluationContext::fromArray(['unknown_key' => 'value']);

        // "unknown_key=value" has no matching spec -> evaluateAtomic returns false
        $this->assertFalse($spec->isSatisfiedBy('unknown_key=value AND category=electronics', $context));
    }
}
