<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Tests\Unit\Application;

use FeatureFlags\Core\Application\Service\FeatureFlagService;
use FeatureFlags\Core\Domain\Entity\FeatureFlag;
use FeatureFlags\Core\Domain\Repository\FlagRepositoryInterface;
use FeatureFlags\Core\Domain\Specification\CategorySpecification;
use FeatureFlags\Core\Domain\Specification\UserRoleSpecification;
use FeatureFlags\Core\Domain\ValueObject\FlagName;
use PHPUnit\Framework\TestCase;

final class FeatureFlagServiceTest extends TestCase
{
    /**
     *  Флаг не найден в репозитории -> сервис возвращает false
     */
    public function test_unknown_flag_returns_false(): void
    {
        // ARRANGE: Мок репозитория (зависимость)
        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository
            ->method('findByName')
            ->willReturn(null); // Флага нет в системе

        $service = new FeatureFlagService($repository);

        // ACT: Вызываем метод
        $result = $service->isEnabled('unknown_new_year_banner');

        // ASSERT: Ожидаем false
        $this->assertFalse($result);
    }

    /**
     * Контекст должен влиять на оценку флага.
     * Сценарий: Флаг с правилом "category=electronics" должен вернуть true,
     * если в контексте передано category=electronics
     */
    public function test_flag_with_category_rule_evaluates_context(): void
    {
        $specifications = [new CategorySpecification()];

        // ARRANGE: Флаг с одним правилом
        $flag = new FeatureFlag(
            name: new FlagName('promo_banner'),
            default: false,
            rules: [['condition' => 'category=electronics', 'value' => true]],
            specifications: $specifications
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository);

        // ACT: Контекст с matching category
        $result = $service->isEnabled('promo_banner', ['category' => 'electronics']);

        // ASSERT: Ожидаем true, потому что правило совпало
        $this->assertTrue($result);
    }

    /**
     * Поддержка условия "category IN (a,b,c)"
     * Сценарий: Флаг с правилом "category IN (electronics,phones)"
     * должен вернуть true, если контекст содержит category=phones
     */
    public function test_flag_with_category_in_rule(): void
    {
        // ARRANGE: Флаг с правилом IN
        $flag = new FeatureFlag(
            name: new FlagName('promo_banner'),
            default: false,
            rules: [['condition' => 'category IN (electronics,phones)', 'value' => true]],
            specifications: [new CategorySpecification()]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository);

        // ACT: Контекст с category=phones (в списке IN)
        $result = $service->isEnabled('promo_banner', ['category' => 'phones']);

        // ASSERT: Ожидаем true, потому что phones есть в списке
        $this->assertTrue($result);
    }

    /**
     * Поддержка условия "user_role=manager".
     * Сценарий: Флаг с правилом "user_role=manager"
     * должен вернуть true, если контекст содержит user_role=manager
     */
    public function test_flag_with_user_role_rule(): void
    {
        // ARRANGE: Флаг с ролевым правилом
        $flag = new FeatureFlag(
            name: new FlagName('admin_dashboard'),
            default: false,
            rules: [['condition' => 'user_role=manager', 'value' => true]],
            specifications: [
                new CategorySpecification(),
                new UserRoleSpecification()
            ]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository);

        // ACT: Контекст с matching ролью
        $result = $service->isEnabled('admin_dashboard', ['user_role' => 'manager']);

        // ASSERT: Ожидаем true, потому что роль совпала
        $this->assertTrue($result);
    }
}