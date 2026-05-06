<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Tests\Unit\Application;

use FeatureFlags\Core\Application\Service\FeatureFlagService;
use FeatureFlags\Core\Domain\Entity\FeatureFlag;
use FeatureFlags\Core\Domain\Repository\FlagRepositoryInterface;
use FeatureFlags\Core\Domain\Specification\CategorySpecification;
use FeatureFlags\Core\Domain\Specification\DateBetweenSpecification;
use FeatureFlags\Core\Domain\Specification\PercentageSpecification;
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

    /**
     * Поддержка условия "current_date BETWEEN MM-DD AND MM-DD".
     * Сценарий: Флаг с правилом "current_date BETWEEN 12-01 AND 12-31"
     * должен вернуть true, если контекст содержит дату внутри диапазона.
     */
    public function test_flag_with_date_between_rule(): void
    {
        // ARRANGE: Флаг с сезонным правилом
        $flag = new FeatureFlag(
            name: new FlagName('new_year_banner'),
            default: false,
            rules: [['condition' => 'current_date BETWEEN 12-01 AND 12-31', 'value' => true]],
            specifications: [new DateBetweenSpecification()]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository);

        // ACT: Дата внутри диапазона (15 декабря)
        $result = $service->isEnabled('new_year_banner', ['current_date' => '2025-12-15']);

        // ASSERT: Ожидаем true, потому что дата попадает в диапазон
        $this->assertTrue($result);
    }

    /**
     * Поддержка условия "user_hash PERCENTAGE 100".
     * Сценарий: При 100% трафика флаг должен возвращать true для любого хеша.
     */
    public function test_flag_with_percentage_100_rule(): void
    {
        // ARRANGE: Флаг с правилом 100% rollout
        $flag = new FeatureFlag(
            name: new FlagName('canary_release'),
            default: false,
            rules: [['condition' => 'user_hash PERCENTAGE 100', 'value' => true]],
            specifications: [new PercentageSpecification()]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository);

        // ACT: Любой хеш должен попасть в 100%
        $result = $service->isEnabled('canary_release', ['user_hash' => 'test_session_abc123']);

        // ASSERT
        $this->assertTrue($result);
    }

    /**
     * Граничный случай: "условие PERCENTAGE 0 никогда не выполняется".
     * Сценарий: При PERCENTAGE 0 правило не применяется ни для одного хеша,
     * поэтому возвращается значение по умолчанию (default).
     */
    public function test_flag_with_percentage_0_rule(): void
    {
        // ARRANGE: Флаг с правилом 0% rollout
        // PERCENTAGE 0 = условие никогда не выполняется -> правило игнорируется
        $flag = new FeatureFlag(
            name: new FlagName('canary_zero'),
            default: false, // Ожидаем возврат именно этого значения
            rules: [['condition' => 'user_hash PERCENTAGE 0', 'value' => true]],
            specifications: [new PercentageSpecification()]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        // ACT: PERCENTAGE 0 → условие не выполняется → правило не применяется
        $result = $service->isEnabled('canary_zero', ['user_hash' => 'any_hash_123']);

        // ASSERT: Возвращается default, потому что правило не сработало
        $this->assertFalse($result);
    }

    /**
     * Сценарий: "Явно выключить флаг для всех пользователей".
     * Решение: Использовать PERCENTAGE 100 + value=false, чтобы правило
     * гарантированно применялось и переопределяло дефолт.
     */
    public function test_flag_explicitly_disabled_for_all(): void
    {
        $flag = new FeatureFlag(
            name: new FlagName('force_disabled'),
            default: true, // Дефолт true, чтобы доказать переопределение
            rules: [
                // PERCENTAGE 100 + value=false = "всем вернуть false"
                ['condition' => 'user_hash PERCENTAGE 100', 'value' => false]
            ],
            specifications: [new PercentageSpecification()]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        $result = $service->isEnabled('force_disabled', ['user_hash' => 'any_hash_123']);

        // ASSERT: Правило переопределило дефолт
        $this->assertFalse($result);
    }
}