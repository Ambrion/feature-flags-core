<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Tests\Unit\Application;

use FeatureFlags\Core\Application\Service\FeatureFlagService;
use FeatureFlags\Core\Domain\Repository\FlagRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class FeatureFlagServiceTest extends TestCase
{
    /**
     * 🟥 RED: Флаг не найден в репозитории → сервис возвращает false
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
}