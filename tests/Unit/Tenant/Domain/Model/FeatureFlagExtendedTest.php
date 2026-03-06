<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Domain\Model;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Event\FeatureFlagConfigurationUpdated;
use App\Tenant\Domain\Event\FeatureFlagCreated;
use App\Tenant\Domain\Event\FeatureFlagToggled;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Domain\Model\FeatureFlagId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeatureFlag::class)]
final class FeatureFlagExtendedTest extends TestCase
{
    private FeatureFlagId $flagId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->flagId = FeatureFlagId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    private function createEnabledFlag(string $featureName = 'dark_mode'): FeatureFlag
    {
        return FeatureFlag::create(
            id: $this->flagId,
            tenantId: $this->tenantId,
            featureName: $featureName,
            enabled: true,
            configuration: [],
        );
    }

    private function createDisabledFlag(string $featureName = 'dark_mode'): FeatureFlag
    {
        return FeatureFlag::create(
            id: $this->flagId,
            tenantId: $this->tenantId,
            featureName: $featureName,
            enabled: false,
            configuration: [],
        );
    }

    // -----------------------------------------------------------------------
    // FeatureFlag::create()
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesFeatureFlagWithAllFields(): void
    {
        $config = ['max_items' => 10, 'enabled_for' => ['premium']];
        $flag = FeatureFlag::create(
            id: $this->flagId,
            tenantId: $this->tenantId,
            featureName: 'new_checkout',
            enabled: true,
            configuration: $config,
            description: 'New checkout flow',
        );

        self::assertTrue($this->flagId->equals($flag->id()));
        self::assertTrue($this->tenantId->equals($flag->tenantId()));
        self::assertSame('new_checkout', $flag->featureName());
        self::assertTrue($flag->isEnabled());
        self::assertSame($config, $flag->configuration());
        self::assertSame('New checkout flow', $flag->description());
        self::assertInstanceOf(\DateTimeImmutable::class, $flag->createdAt());
        self::assertNull($flag->updatedAt());
    }

    #[Test]
    public function itCreatesDisabledFlag(): void
    {
        $flag = $this->createDisabledFlag();

        self::assertFalse($flag->isEnabled());
    }

    #[Test]
    public function itRecordsFeatureFlagCreatedEvent(): void
    {
        $flag = $this->createEnabledFlag('test_feature');
        $events = $flag->popEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(FeatureFlagCreated::class, $events[0]);
    }

    #[Test]
    public function itThrowsWhenFeatureNameIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Feature name cannot be empty');

        FeatureFlag::create(
            id: $this->flagId,
            tenantId: $this->tenantId,
            featureName: '',
            enabled: false,
        );
    }

    #[Test]
    public function itThrowsWhenFeatureNameIsOnlyWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Feature name cannot be empty');

        FeatureFlag::create(
            id: $this->flagId,
            tenantId: $this->tenantId,
            featureName: '   ',
            enabled: false,
        );
    }

    // -----------------------------------------------------------------------
    // FeatureFlag::reconstituteFromPersistence()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstitutesFromPersistenceWithoutEvents(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-01');
        $updatedAt = new \DateTimeImmutable('2024-06-01');

        $flag = FeatureFlag::reconstituteFromPersistence(
            id: $this->flagId,
            tenantId: $this->tenantId,
            featureName: 'feature_x',
            enabled: true,
            configuration: ['key' => 'value'],
            description: 'desc',
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        self::assertSame('feature_x', $flag->featureName());
        self::assertTrue($flag->isEnabled());
        self::assertSame('value', $flag->configuration()['key']);
        self::assertSame($createdAt->getTimestamp(), $flag->createdAt()->getTimestamp());
        self::assertSame($updatedAt->getTimestamp(), $flag->updatedAt()->getTimestamp());
        self::assertCount(0, $flag->popEvents());
    }

    #[Test]
    public function itReconstitutesWithNullUpdatedAt(): void
    {
        $flag = FeatureFlag::reconstituteFromPersistence(
            id: $this->flagId,
            tenantId: $this->tenantId,
            featureName: 'feature_y',
            enabled: false,
            configuration: [],
            description: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: null,
        );

        self::assertNull($flag->updatedAt());
    }

    // -----------------------------------------------------------------------
    // enable() / disable()
    // -----------------------------------------------------------------------

    #[Test]
    public function itEnablesDisabledFlag(): void
    {
        $flag = $this->createDisabledFlag();
        $flag->popEvents();

        $flag->enable();

        self::assertTrue($flag->isEnabled());
        self::assertNotNull($flag->updatedAt());
        $events = $flag->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(FeatureFlagToggled::class, $events[0]);
    }

    #[Test]
    public function itThrowsWhenEnablingAlreadyEnabledFlag(): void
    {
        $flag = $this->createEnabledFlag();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already enabled');

        $flag->enable();
    }

    #[Test]
    public function itDisablesEnabledFlag(): void
    {
        $flag = $this->createEnabledFlag();
        $flag->popEvents();

        $flag->disable();

        self::assertFalse($flag->isEnabled());
        self::assertNotNull($flag->updatedAt());
        $events = $flag->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(FeatureFlagToggled::class, $events[0]);
    }

    #[Test]
    public function itThrowsWhenDisablingAlreadyDisabledFlag(): void
    {
        $flag = $this->createDisabledFlag();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already disabled');

        $flag->disable();
    }

    // -----------------------------------------------------------------------
    // updateConfiguration()
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesConfiguration(): void
    {
        $flag = $this->createEnabledFlag();
        $flag->popEvents();
        $newConfig = ['threshold' => 0.5, 'users' => ['admin']];

        $flag->updateConfiguration($newConfig);

        self::assertSame($newConfig, $flag->configuration());
        self::assertNotNull($flag->updatedAt());
        $events = $flag->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(FeatureFlagConfigurationUpdated::class, $events[0]);
    }

    #[Test]
    public function itUpdatesConfigurationToEmpty(): void
    {
        $flag = FeatureFlag::create(
            id: $this->flagId,
            tenantId: $this->tenantId,
            featureName: 'my_feature',
            enabled: true,
            configuration: ['old_key' => 'old_value'],
        );
        $flag->popEvents();

        $flag->updateConfiguration([]);

        self::assertSame([], $flag->configuration());
    }

    // -----------------------------------------------------------------------
    // getConfigValue()
    // -----------------------------------------------------------------------

    #[Test]
    public function itGetsConfigValueByKey(): void
    {
        $flag = FeatureFlag::create(
            id: $this->flagId,
            tenantId: $this->tenantId,
            featureName: 'settings',
            enabled: true,
            configuration: ['color' => 'blue', 'limit' => 100],
        );

        self::assertSame('blue', $flag->getConfigValue('color'));
        self::assertSame(100, $flag->getConfigValue('limit'));
    }

    #[Test]
    public function itReturnsDefaultWhenKeyNotFound(): void
    {
        $flag = $this->createEnabledFlag();

        self::assertNull($flag->getConfigValue('nonexistent'));
        self::assertSame('fallback', $flag->getConfigValue('nonexistent', 'fallback'));
        self::assertSame(42, $flag->getConfigValue('nonexistent', 42));
    }

    // -----------------------------------------------------------------------
    // FeatureFlagId
    // -----------------------------------------------------------------------

    #[Test]
    public function itGeneratesUniqueIds(): void
    {
        $id1 = FeatureFlagId::generate();
        $id2 = FeatureFlagId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itEqualsWhenSameValue(): void
    {
        $id1 = FeatureFlagId::fromString('12345678-1234-4123-8123-123456789abc');
        $id2 = FeatureFlagId::fromString('12345678-1234-4123-8123-123456789abc');

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function itConvertsToString(): void
    {
        $uuid = '12345678-1234-4123-8123-123456789abc';
        $id = FeatureFlagId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
        self::assertSame($uuid, (string) $id);
    }

    #[Test]
    public function itThrowsWhenFeatureFlagIdIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FeatureFlagId::fromString('');
    }

    #[Test]
    public function itThrowsWhenFeatureFlagIdIsNotUuidV4(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FeatureFlagId::fromString('not-a-uuid');
    }
}
