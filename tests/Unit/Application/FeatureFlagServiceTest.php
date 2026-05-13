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
use FeatureFlags\Core\Domain\ValueObject\EvaluationResult;
use FeatureFlags\Core\Domain\ValueObject\FlagName;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
        $specifications = [new CategorySpecification];

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
            specifications: [new CategorySpecification]
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
                new CategorySpecification,
                new UserRoleSpecification,
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
            specifications: [new DateBetweenSpecification]
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
            specifications: [new PercentageSpecification]
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
            specifications: [new PercentageSpecification]
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
                ['condition' => 'user_hash PERCENTAGE 100', 'value' => false],
            ],
            specifications: [new PercentageSpecification]
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
            specifications: [new TargetIdSpecification]
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
            specifications: [new TargetIdSpecification]
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
                new UserRoleSpecification,
                new CategorySpecification,
                new TargetIdSpecification,
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
        // ARRANGE: Мок логгера с ожиданием вызова logEvaluation()
        $logger = $this->createMock(FlagUsageLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logEvaluation')
            ->with(
                'test_flag',
                $this->callback(function (EvaluationResult $result) {
                    return $result->enabled === true;
                }),
                $this->callback(fn (array $ctx) => ($ctx['role'] ?? '') === 'admin')
            );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn(
            new FeatureFlag(new FlagName('test_flag'), default: true)
        );

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
    public function test_get_variant_returns_matching_variant(): void
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
                new UserRoleSpecification,
                new PercentageSpecification,
            ]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository);

        // ACT: Гость (не админ) + 100% трафика -> должен попасть в variant_b
        $variant = $service->getVariant('header_ab_test', [
            'user_role' => 'guest',
            'user_hash' => 'test_session_xyz',
        ]);

        // ASSERT
        $this->assertEquals('variant_b', $variant);
    }

    /**
     * Граничный случай getVariant() — возврат null при отсутствии совпадений.
     * Сценарий: Если ни одно правило не сработало, метод должен вернуть null,
     * а не дефолтное значение или ошибку
     */
    public function test_get_variant_returns_null_when_no_rules_match(): void
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
                new UserRoleSpecification,
                new CategorySpecification,
            ]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        // ACT: Контекст, не удовлетворяющий ни одному правилу
        $variant = $service->getVariant('ab_test_variant', [
            'user_role' => 'guest',
            'category' => 'clothing',
            'user_hash' => 'test_123',
        ]);

        // ASSERT: Ожидаем null
        $this->assertNull($variant);
    }

    /**
     * Сервис должен логировать оценку при получении варианта.
     */
    public function test_get_variant_logs_evaluation(): void
    {
        // ARRANGE: Мок логгера с ожиданием вызова logEvaluation()
        $logger = $this->createMock(FlagUsageLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logEvaluation')
            ->with(
                'header_ab_test',
                $this->callback(function (EvaluationResult $result) {
                    return $result->variant === 'variant_b';
                }),
                $this->callback(fn (array $ctx) => isset($ctx['user_hash']))
            );

        $flag = new FeatureFlag(
            name: new FlagName('header_ab_test'),
            default: false,
            rules: [['condition' => 'user_hash PERCENTAGE 100', 'value' => 'variant_b']],
            specifications: [new PercentageSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

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
    public function test_get_variant_is_deterministic_for_same_user_hash(): void
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
            specifications: [new PercentageSpecification]
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

    /**
     * Сценарий: Флаг для A/B-теста со строковым дефолтом.
     * Если ни одно правило не сработало, getVariant() должен вернуть строковый default.
     */
    public function test_get_variant_returns_string_default_when_no_rules_match(): void
    {
        // ARRANGE: Флаг с дефолтом 'A' (строка)
        $flag = new FeatureFlag(
            name: new FlagName('ab_test_with_default'),
            default: 'A', // Теперь это строка, а не bool
            rules: [
                // Правило, которое НЕ сработает (требует admin, а будет guest)
                ['condition' => 'user_role=admin', 'value' => 'B'],
            ],
            specifications: [new UserRoleSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        // ACT: Контекст, не удовлетворяющий правилу
        $variant = $service->getVariant('ab_test_with_default', ['user_role' => 'guest']);

        // ASSERT: Должен вернуть строковый дефолт 'A', а не null
        $this->assertSame('A', $variant);
    }

    /**
     * Сценарий: Обратная совместимость — evaluate() всегда возвращает bool.
     * Даже если дефолт — строка 'A', evaluate() должен вернуть (bool)'A' = true.
     */
    public function test_evaluate_returns_bool_even_when_default_is_string(): void
    {
        // ARRANGE: Флаг со строковым дефолтом
        $flag = new FeatureFlag(
            name: new FlagName('hybrid_flag'),
            default: 'A', // строка
            rules: [],    // нет правил
            specifications: []
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        // ACT
        $result = $service->isEnabled('hybrid_flag', []);

        // ASSERT: evaluate() всегда возвращает bool (backward compatibility)
        $this->assertIsBool($result);
        $this->assertTrue($result); // (bool)'A' === true
    }

    /**
     * Сценарий: getVariant() возвращает null, если дефолт — не строка.
     * Для булевых флагов getVariant() не должен "подхватывать" булев дефолт.
     */
    public function test_get_variant_returns_null_when_default_is_not_string(): void
    {
        // ARRANGE: Обычный булев флаг
        $flag = new FeatureFlag(
            name: new FlagName('boolean_flag'),
            default: true, // булево
            rules: [],
            specifications: []
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        // ACT
        $variant = $service->getVariant('boolean_flag', []);

        // ASSERT: getVariant() возвращает null для не-строковых дефолтов
        $this->assertNull($variant);
    }

    /**
     * Сценарий: Правило сработало, но вернуло не-строку -> getVariant() игнорирует.
     * Если правило вернуло true/false, getVariant() должен продолжить поиск
     * или вернуть строковый дефолт, но не булево значение.
     */
    public function test_get_variant_ignores_non_string_rule_values(): void
    {
        // ARRANGE: Флаг с правилом, возвращающим bool
        $flag = new FeatureFlag(
            name: new FlagName('mixed_rules_flag'),
            default: 'control', // строковый дефолт
            rules: [
                // Правило сработает, но вернёт bool, а не строку
                ['condition' => 'user_role=admin', 'value' => true],
            ],
            specifications: [new UserRoleSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        // ACT: Правило сработало (админ), но значение — bool
        $variant = $service->getVariant('mixed_rules_flag', ['user_role' => 'admin']);

        // ASSERT: getVariant() игнорирует не-строковые значения правил
        // и возвращается к строковому дефолту
        $this->assertSame('control', $variant);
    }

    /**
     * Сценарий: Детерминированность — один контекст = один результат.
     * Проверяем, что приватный хелпер не ломает детерминированность.
     */
    public function test_evaluate_and_get_variant_are_deterministic_with_shared_logic(): void
    {
        // ARRANGE: Флаг с процентным правилом
        $flag = new FeatureFlag(
            name: new FlagName('deterministic_flag'),
            default: 'A',
            rules: [
                ['condition' => 'user_hash PERCENTAGE 50', 'value' => 'B'],
            ],
            specifications: [new PercentageSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        $context = ['user_hash' => 'fixed_hash_123'];

        // ACT: Многократные вызовы
        $evalResults = array_map(fn () => $service->isEnabled('deterministic_flag', $context), range(1, 20));
        $variantResults = array_map(fn () => $service->getVariant('deterministic_flag', $context), range(1, 20));

        // ASSERT: Все результаты идентичны (детерминированность)
        $this->assertCount(1, array_unique($evalResults), 'evaluate() must be deterministic');
        $this->assertCount(1, array_unique($variantResults), 'getVariant() must be deterministic');
    }

    /**
     * Сценарий: Правило с процентом возвращает корректный вес.
     * user_hash PERCENTAGE 34 → вес 0.34 для пользователей, попавших в правило.
     */
    public function test_get_variant_weight_returns_percentage_for_matching_rule(): void
    {
        // ARRANGE: Флаг с процентным правилом
        $flag = new FeatureFlag(
            name: new FlagName('ab_test_weighted'),
            default: 'A',
            rules: [
                ['condition' => 'user_hash PERCENTAGE 34', 'value' => 'B'],
            ],
            specifications: [new PercentageSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        // ACT: Хеш, который попадает в первые 34% (bucket < 34)
        $context = ['user_hash' => 'hash_bucket_10'];
        $weight = $service->getVariantWeight('ab_test_weighted', $context);

        // ASSERT: Вес должен быть 0.34 (34%)
        $this->assertEquals(0.34, $weight);
    }

    /**
     * Сценарий: Правило без процента возвращает null (нет веса).
     * Например, rule по роли не имеет вероятностного распределения.
     */
    public function test_get_variant_weight_returns_null_for_non_percentage_rule(): void
    {
        // ARRANGE: Флаг с правилом по роли (не процентное)
        $flag = new FeatureFlag(
            name: new FlagName('role_based_flag'),
            default: 'A',
            rules: [
                ['condition' => 'user_role=admin', 'value' => 'admin_variant'],
            ],
            specifications: [new UserRoleSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        // ACT: Контекст, удовлетворяющий правилу
        $weight = $service->getVariantWeight('role_based_flag', ['user_role' => 'admin']);

        // ASSERT: Нет процентного правила → вес null
        $this->assertNull($weight);
    }

    /**
     * Сценарий: Если правило не сработало → getVariantWeight() возвращает null.
     * Даже если есть процентное правило, но пользователь не попал в него.
     */
    public function test_get_variant_weight_returns_null_when_rule_does_not_match(): void
    {
        // ARRANGE: Флаг с процентным правилом 34%
        $flag = new FeatureFlag(
            name: new FlagName('partial_rollout'),
            default: 'A',
            rules: [
                ['condition' => 'user_hash PERCENTAGE 34', 'value' => 'B'],
            ],
            specifications: [new PercentageSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        // ACT: Хеш, который НЕ попадает в 34% (bucket >= 34)
        $context = ['user_hash' => 'hash_bucket_50'];
        $weight = $service->getVariantWeight('partial_rollout', $context);

        // ASSERT: Правило не сработало → вес null
        $this->assertNull($weight);
    }

    /**
     * Сценарий: Детерминированность — один хеш = один вес.
     * Проверяем, что вес не «плавает» при повторных вызовах.
     */
    public function test_get_variant_weight_is_deterministic_for_same_hash(): void
    {
        // ARRANGE: Флаг с процентным правилом
        $flag = new FeatureFlag(
            name: new FlagName('deterministic_weight'),
            default: 'A',
            rules: [
                ['condition' => 'user_hash PERCENTAGE 50', 'value' => 'B'],
            ],
            specifications: [new PercentageSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        $userHash = 'consistent_hash_abc123';

        // ACT: 20 вызовов с одним хешом
        $weights = array_map(
            fn () => $service->getVariantWeight('deterministic_weight', ['user_hash' => $userHash]),
            range(1, 20)
        );

        // ASSERT: Все веса идентичны
        $this->assertCount(1, array_unique($weights), 'Weight must be deterministic for same hash');
    }

    /**
     * Сценарий: getVariantWeight() работает независимо от getVariant().
     * Можно получить и вариант, и его вес в одном контексте.
     */
    public function test_get_variant_and_weight_can_be_called_together(): void
    {
        // ARRANGE
        $flag = new FeatureFlag(
            name: new FlagName('combined_test'),
            default: 'A',
            rules: [
                ['condition' => 'user_hash PERCENTAGE 25', 'value' => 'B'],
            ],
            specifications: [new PercentageSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);
        $service = new FeatureFlagService($repository);

        // Динамически находим хеши под текущую среду
        $hashInRule = $this->findHashForPercentageRule(25, true);   // bucket < 25
        $hashOutOfRule = $this->findHashForPercentageRule(25, false); // bucket >= 25

        // ACT & ASSERT: для "попавшего" пользователя
        $contextIn = ['user_hash' => $hashInRule];
        $variant = $service->getVariant('combined_test', $contextIn);
        $weight = $service->getVariantWeight('combined_test', $contextIn);

        $this->assertSame('B', $variant, 'Variant should be B for hash in rule');
        $this->assertEquals(0.25, $weight, 'Weight should be 0.25 for 25% rule');

        // ACT & ASSERT: для "не попавшего"
        $contextOut = ['user_hash' => $hashOutOfRule];
        $variantOut = $service->getVariant('combined_test', $contextOut);
        $weightOut = $service->getVariantWeight('combined_test', $contextOut);

        $this->assertSame('A', $variantOut, 'Variant should be default A for hash out of rule');
        $this->assertNull($weightOut, 'Weight should be null when rule does not match');
    }

    /**
     * Находит строку, которая даёт бакет в нужном диапазоне для PERCENTAGE-правила.
     *
     * @param  int  $percentage  Процент правила (1-100)
     * @param  bool  $shouldMatch  Должен ли хеш попасть в правило (true) или нет (false)
     * @param  string  $prefix  Префикс для генерации кандидатов
     * @return string Строка, гарантированно дающая нужный результат
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

    /**
     * Сценарий: При сработавшем процентном правиле логгируется вес в EvaluationResult
     */
    public function test_get_variant_weight_logs_evaluation_with_weight(): void
    {
        // ARRANGE
        $logger = $this->createMock(FlagUsageLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logEvaluation')
            ->with(
                'combined_test',
                $this->callback(function (EvaluationResult $result) {
                    return $result->weight === 0.25;
                }),
                $this->callback(fn (array $ctx) => isset($ctx['user_hash']))
            );

        $flag = new FeatureFlag(
            name: new FlagName('combined_test'),
            default: 'A',
            rules: [['condition' => 'user_hash PERCENTAGE 25', 'value' => 'B']],
            specifications: [new PercentageSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository, $logger);

        // Хеш, который гарантированно попадает в 25%
        $hashInRule = $this->findHashForPercentageRule(25, true);

        // ACT
        $weight = $service->getVariantWeight('combined_test', ['user_hash' => $hashInRule]);

        // ASSERT
        $this->assertEquals(0.25, $weight);
    }

    /**
     * Сценарий: При не сработавшем правиле логгируется weight=null в EvaluationResult
     */
    public function test_get_variant_weight_logs_evaluation_with_null_weight(): void
    {
        // ARRANGE
        $logger = $this->createMock(FlagUsageLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logEvaluation')
            ->with(
                'combined_test',
                $this->callback(function (EvaluationResult $result) {
                    return $result->weight === null;
                }),
                $this->callback(fn (array $ctx) => isset($ctx['user_hash']))
            );

        $flag = new FeatureFlag(
            name: new FlagName('combined_test'),
            default: 'A',
            rules: [['condition' => 'user_hash PERCENTAGE 25', 'value' => 'B']],
            specifications: [new PercentageSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository, $logger);

        // Хеш, который гарантированно НЕ попадает в 25%
        $hashOutOfRule = $this->findHashForPercentageRule(25, false);

        // ACT
        $weight = $service->getVariantWeight('combined_test', ['user_hash' => $hashOutOfRule]);

        // ASSERT
        $this->assertNull($weight);
    }

    /**
     * Сценарий: evaluateForAnalytics() логирует ОДИН раз с variant и weight в EvaluationResult
     */
    public function test_evaluate_for_analytics_logs_evaluation_with_variant_and_weight(): void
    {
        // ARRANGE
        $logger = $this->createMock(FlagUsageLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logEvaluation')
            ->with(
                'ab_test_flag',
                $this->callback(function (EvaluationResult $result) {
                    return $result->variant === 'B' && $result->weight === 0.25;
                }),
                $this->callback(fn (array $ctx) => isset($ctx['user_hash']))
            );

        $flag = new FeatureFlag(
            name: new FlagName('ab_test_flag'),
            default: 'A',
            rules: [['condition' => 'user_hash PERCENTAGE 25', 'value' => 'B']],
            specifications: [new PercentageSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')
            ->with(new FlagName('ab_test_flag'))
            ->willReturn($flag);

        $service = new FeatureFlagService($repository, $logger);

        // Хеш, который гарантированно попадёт в 25%
        $hashInRule = $this->findHashForPercentageRule(25, true);

        // ACT
        $result = $service->evaluateForAnalytics('ab_test_flag', ['user_hash' => $hashInRule]);

        // ASSERT
        $this->assertSame('B', $result['variant']);
        $this->assertEquals(0.25, $result['weight']);
    }

    /**
     * Сценарий: Если пользователь не попал в процент, логируется null для variant и weight
     */
    public function test_evaluate_for_analytics_logs_evaluation_with_nulls(): void
    {
        // ARRANGE
        $logger = $this->createMock(FlagUsageLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logEvaluation')
            ->with(
                'ab_test_flag',
                $this->callback(function (EvaluationResult $result) {
                    return $result->variant === 'A' && $result->weight === null;
                }),
                $this->anything()
            );

        $flag = new FeatureFlag(
            name: new FlagName('ab_test_flag'),
            default: 'A',
            rules: [['condition' => 'user_hash PERCENTAGE 25', 'value' => 'B']],
            specifications: [new PercentageSpecification]
        );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')->willReturn($flag);

        $service = new FeatureFlagService($repository, $logger);

        // Хеш, который НЕ попадёт в 25%
        $hashOutOfRule = $this->findHashForPercentageRule(25, false);

        // ACT
        $result = $service->evaluateForAnalytics('ab_test_flag', ['user_hash' => $hashOutOfRule]);

        // ASSERT
        $this->assertSame('A', $result['variant']);
        $this->assertNull($result['weight']);
    }

    /**
     * Сценарий: При отсутствии флага логируется EvaluationResult::notFound()
     */
    public function test_evaluate_for_analytics_logs_evaluation_for_missing_flag(): void
    {
        // ARRANGE
        $logger = $this->createMock(FlagUsageLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logEvaluation')
            ->with(
                'unknown_flag',
                $this->callback(function (EvaluationResult $result) {
                    // notFound() возвращает все поля = null
                    return $result->variant === null && $result->weight === null && $result->enabled === null;
                }),
                $this->anything()
            );

        $repository = $this->createMock(FlagRepositoryInterface::class);
        $repository->method('findByName')
            ->with(new FlagName('unknown_flag'))
            ->willReturn(null);

        $service = new FeatureFlagService($repository, $logger);

        // ACT
        $result = $service->evaluateForAnalytics('unknown_flag', ['user_hash' => 'any']);

        // ASSERT
        $this->assertNull($result['variant']);
        $this->assertNull($result['weight']);
    }
}
