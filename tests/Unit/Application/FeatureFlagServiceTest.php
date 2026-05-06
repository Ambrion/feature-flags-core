<?php

declare(strict_types=1);

namespace FeatureFlags\Core\Tests\Unit\Application;

use FeatureFlags\Core\Application\Service\FeatureFlagService;
use FeatureFlags\Core\Domain\Entity\FeatureFlag;
use FeatureFlags\Core\Domain\Logger\FlagUsageLoggerInterface;
use FeatureFlags\Core\Domain\Repository\FlagRepositoryInterface;
use FeatureFlags\Core\Domain\Specification\CategorySpecification;
use FeatureFlags\Core\Domain\Specification\DateBetweenSpecification;
use FeatureFlags\Core\Domain\Specification\PercentageSpecification;
use FeatureFlags\Core\Domain\Specification\TargetIdSpecification;
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

        // ACT: PERCENTAGE 0 -> условие не выполняется -> правило не применяется
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

    /**
     * Поддержка условия "target_id IN (id1,id2,...)".
     * Сценарий: Флаг с правилом "target_id IN (101,102)"
     * должен вернуть true, если контекст содержит target_id=101.
     */
    public function test_flag_with_target_id_in_rule(): void
    {
        // ARRANGE: Флаг с правилом IN для ID
        $flag = new FeatureFlag(
            name: new FlagName('special_promo'),
            default: false,
            rules: [['condition' => 'target_id IN (101, 102)', 'value' => true]],
            specifications: [new TargetIdSpecification()]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository);

        // ACT: Контекст с target_id=101 (попадает в список)
        $result = $service->isEnabled('special_promo', ['target_id' => 101]);

        // ASSERT: Ожидаем true, потому что ID есть в списке
        $this->assertTrue($result);
    }

    /**
     * Поддержка условия "target_id=VALUE" (точное совпадение).
     * Сценарий: Флаг с правилом "target_id=101"
     * должен вернуть true, если контекст содержит target_id=101.
     */
    public function test_flag_with_target_id_exact_match_rule(): void
    {
        // ARRANGE: Флаг с правилом точного совпадения
        $flag = new FeatureFlag(
            name: new FlagName('exact_match_flag'),
            default: false,
            rules: [['condition' => 'target_id=101', 'value' => true]],
            specifications: [new TargetIdSpecification()]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository);

        // ACT: Контекст с совпадающим ID
        $result = $service->isEnabled('exact_match_flag', ['target_id' => 101]);

        // ASSERT
        $this->assertTrue($result);
    }

    /**
     * Интеграционный тест — приоритет правил и short-circuit.
     * Сценарий: Флаг с несколькими правилами должен вернуть значение
     * первого сработавшего правила, игнорируя остальные.
     */
    public function test_flag_rules_priority_short_circuit(): void
    {
        // ARRANGE: Флаг с тремя правилами разного типа
        $flag = new FeatureFlag(
            name: new FlagName('complex_flag'),
            default: false, // Базовое значение — ложь
            rules: [
                // Правило 1: админам — всегда true (высший приоритет)
                ['condition' => 'user_role=admin', 'value' => true],
                // Правило 2: категория electronics — true, но только если не админ
                ['condition' => 'category=electronics', 'value' => true],
                // Правило 3: конкретный ID — false (явное исключение)
                ['condition' => 'target_id=999', 'value' => false],
            ],
            specifications: [
                new UserRoleSpecification(),
                new CategorySpecification(),
                new TargetIdSpecification(),
            ]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        // ACT & ASSERT:

        // 1. Админ -> правило 1 срабатывает первым -> true (игнорируем категорию и ID)
        $this->assertTrue(
            $service->isEnabled('complex_flag', [
                'user_role' => 'admin',
                'category' => 'clothing', // не electronics
                'target_id' => 999,        // должен быть false, но админ побеждает
            ]),
            'Admin role should override other rules'
        );

        // 2. Не админ, но electronics -> правило 2 -> true
        $this->assertTrue(
            $service->isEnabled('complex_flag', [
                'user_role' => 'manager',
                'category' => 'electronics',
                'target_id' => 123,
            ]),
            'Category match should return true when role does not match'
        );

        // 3. Не админ, не electronics, но target_id=999 -> правило 3 -> false
        $this->assertFalse(
            $service->isEnabled('complex_flag', [
                'user_role' => 'guest',
                'category' => 'clothing',
                'target_id' => 999,
            ]),
            'Target ID rule should apply when higher-priority rules do not match'
        );

        // 4. Ничего не совпало -> возвращается default (false)
        $this->assertFalse(
            $service->isEnabled('complex_flag', [
                'user_role' => 'guest',
                'category' => 'clothing',
                'target_id' => 123,
            ]),
            'Default value should be returned when no rules match'
        );
    }

    /**
     * Сервис должен вызывать логгер при оценке флага.
     */
    public function test_evaluate_calls_logger(): void
    {
        // ARRANGE: Мок логгера
        $logger = $this->createMock(FlagUsageLoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(
                'test_flag',
                true,
                $this->callback(fn(array $ctx) => ($ctx['role'] ?? '') === 'admin')
            );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn(
            new FeatureFlag(new FlagName('test_flag'), default: true)
        );

        // Сервис требует логгер во втором аргументе (пока не реализовано)
        $service = new FeatureFlagService($repository, $logger);

        // ACT
        $result = $service->isEnabled('test_flag', ['role' => 'admin']);

        // ASSERT
        $this->assertTrue($result);
    }

    /**
     * A/B тестирование — получение варианта флага.
     * Сценарий: Метод getVariant() возвращает строковое значение
     * первого сработавшего правила, или null, если флаг не найден.
     */
    public function test_getVariant_returns_matching_variant(): void
    {
        // ARRANGE: Флаг с правилами, возвращающими строки (варианты)
        $flag = new FeatureFlag(
            name: new FlagName('header_ab_test'),
            default: false,
            rules: [
                // Правило 1: админам всегда показываем специальную версию
                ['condition' => 'user_role=admin', 'value' => 'admin_view'],
                // Правило 2: остальным — variant_b (100% трафика для теста)
                ['condition' => 'user_hash PERCENTAGE 100', 'value' => 'variant_b'],
            ],
            specifications: [
                new UserRoleSpecification(),
                new PercentageSpecification(),
            ]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository);

        // ACT: Гость (не админ) + 100% трафика -> должен попасть в variant_b
        $variant = $service->getVariant('header_ab_test', [
            'user_role' => 'guest',
            'user_hash' => 'test_session_xyz'
        ]);

        // ASSERT
        $this->assertEquals('variant_b', $variant);
    }

    /**
     * Граничный случай getVariant() — возврат null при отсутствии совпадений.
     * Сценарий: Если ни одно правило не сработало, метод должен вернуть null,
     * а не дефолтное значение или ошибку
     */
    public function test_getVariant_returns_null_when_no_rules_match(): void
    {
        // ARRANGE: Флаг с правилами, которые НЕ совпадут с переданным контекстом
        $flag = new FeatureFlag(
            name: new FlagName('ab_test_variant'),
            default: false,
            rules: [
                // Требует admin, но будет guest
                ['condition' => 'user_role=admin', 'value' => 'admin_view'],
                // Требует electronics, но будет clothing
                ['condition' => 'category=electronics', 'value' => 'variant_b'],
            ],
            specifications: [
                new UserRoleSpecification(),
                new CategorySpecification(),
            ]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        // ACT: Контекст, не удовлетворяющий ни одному правилу
        $variant = $service->getVariant('ab_test_variant', [
            'user_role' => 'guest',
            'category' => 'clothing',
            'user_hash' => 'test_123'
        ]);

        // ASSERT: Ожидаем null
        $this->assertNull($variant);
    }

    /**
     * Сервис должен вызывать логирование варианта для A/B-тестов.
     */
    public function test_getVariant_calls_variant_logger(): void
    {
        // ARRANGE: Мок логгера с ожиданием вызова logVariant()
        $logger = $this->createMock(FlagUsageLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logVariant') // Метод ещё не существует в интерфейсе
            ->with(
                'header_ab_test',
                'variant_b',
                $this->callback(fn(array $ctx) => isset($ctx['user_hash']))
            );

        $flag = new FeatureFlag(
            name: new FlagName('header_ab_test'),
            default: false,
            rules: [['condition' => 'user_hash PERCENTAGE 100', 'value' => 'variant_b']],
            specifications: [new PercentageSpecification()]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        // Инжектим логгер в сервис
        $service = new FeatureFlagService($repository, $logger);

        // ACT
        $variant = $service->getVariant('header_ab_test', ['user_hash' => 'session_xyz']);

        // ASSERT
        $this->assertEquals('variant_b', $variant);
    }

    /**
     * Детерминированность A/B-тестов — одинаковый хеш = одинаковый вариант.
     * Сценарий: При многократном вызове getVariant() с одним и тем же user_hash
     * должен всегда возвращаться один и тот же вариант.
     */
    public function test_getVariant_is_deterministic_for_same_user_hash(): void
    {
        // ARRANGE: Флаг с распределением 50/50
        $flag = new FeatureFlag(
            name: new FlagName('ab_deterministic_test'),
            default: false,
            rules: [
                // 50% трафика попадает сюда (buckets 0-49)
                ['condition' => 'user_hash PERCENTAGE 50', 'value' => 'variant_a'],
                // Оставшиеся 50% попадают сюда (buckets 50-99)
                ['condition' => 'user_hash PERCENTAGE 100', 'value' => 'variant_b'],
            ],
            specifications: [new PercentageSpecification()]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        $userHash = 'consistent_session_id_12345';

        // ACT & ASSERT: Вызываем 10 раз, результат должен быть идентичным
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $service->getVariant('ab_deterministic_test', ['user_hash' => $userHash]);
        }

        // Все результаты должны быть идентичны первому
        $uniqueResults = array_unique($results);
        $this->assertCount(1, $uniqueResults, 'Same user_hash must always yield the same variant');
    }
}