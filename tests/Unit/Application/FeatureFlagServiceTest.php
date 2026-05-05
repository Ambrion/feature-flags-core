<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Tests\Unit\Application;

use FeatureFlags\Core\Application\Service\FeatureFlagService;
use FeatureFlags\Core\Domain\Entity\FeatureFlag;
use FeatureFlags\Core\Domain\Repository\FlagRepositoryInterface;
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
        // ARRANGE: Флаг с одним правилом
        $flag = new FeatureFlag(
            name: new FlagName('promo_banner'),
            default: false,
            rules: [['condition' => 'category=electronics', 'value' => true]]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository);

        // ACT: Контекст с matching category
        $result = $service->isEnabled('promo_banner', ['category' => 'electronics']);

        // ASSERT: Ожидаем true, потому что правило совпало
        $this->assertTrue($result);
    }
}