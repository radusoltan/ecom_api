<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Domain\Model;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Domain\Model\FeatureFlagId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeatureFlag::class)]
final class FeatureFlagTest extends TestCase
{
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    private function createFlag(
        string $name = 'dark_mode',
        bool $enabled = false,
        array $config = [],
        ?string $description = null,
    ): FeatureFlag {
        return FeatureFlag::create(
            FeatureFlagId::generate(),
            $this->tenantId,
            $name,
            $enabled,
            $config,
            $description,
        );
    }

    #[Test]
    public function itCreatesFeatureFlag(): void
    {
        $flag = $this->createFlag('dark_mode', false, ['rollout' => 50], 'Dark mode toggle');

        self::assertSame('dark_mode', $flag->featureName());
        self::assertFalse($flag->isEnabled());
        self::assertSame(['rollout' => 50], $flag->configuration());
        self::assertSame('Dark mode toggle', $flag->description());
        self::assertNotNull($flag->createdAt());
        self::assertNull($flag->updatedAt());
    }

    #[Test]
    public function itThrowsOnEmptyFeatureName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createFlag('');
    }

    #[Test]
    public function itThrowsOnWhitespaceOnlyFeatureName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createFlag('   ');
    }

    #[Test]
    public function itEnablesFlag(): void
    {
        $flag = $this->createFlag('dark_mode', false);

        $flag->enable();

        self::assertTrue($flag->isEnabled());
        self::assertNotNull($flag->updatedAt());
    }

    #[Test]
    public function itThrowsWhenEnablingAlreadyEnabledFlag(): void
    {
        $flag = $this->createFlag('dark_mode', true);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already enabled');
        $flag->enable();
    }

    #[Test]
    public function itDisablesFlag(): void
    {
        $flag = $this->createFlag('dark_mode', true);

        $flag->disable();

        self::assertFalse($flag->isEnabled());
        self::assertNotNull($flag->updatedAt());
    }

    #[Test]
    public function itThrowsWhenDisablingAlreadyDisabledFlag(): void
    {
        $flag = $this->createFlag('dark_mode', false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already disabled');
        $flag->disable();
    }

    #[Test]
    public function itUpdatesConfiguration(): void
    {
        $flag = $this->createFlag('dark_mode', false, ['rollout' => 10]);

        $flag->updateConfiguration(['rollout' => 100, 'beta' => true]);

        self::assertSame(['rollout' => 100, 'beta' => true], $flag->configuration());
        self::assertNotNull($flag->updatedAt());
    }

    #[Test]
    public function itGetsConfigValue(): void
    {
        $flag = $this->createFlag('dark_mode', false, ['rollout' => 50]);

        self::assertSame(50, $flag->getConfigValue('rollout'));
        self::assertNull($flag->getConfigValue('missing'));
        self::assertSame('default', $flag->getConfigValue('missing', 'default'));
    }

    #[Test]
    public function itReconstitutesFromPersistence(): void
    {
        $id = FeatureFlagId::generate();
        $createdAt = new \DateTimeImmutable('2026-01-01');
        $updatedAt = new \DateTimeImmutable('2026-02-01');

        $flag = FeatureFlag::reconstituteFromPersistence(
            $id, $this->tenantId, 'chat', true, ['max_users' => 5], 'Chat feature',
            $createdAt, $updatedAt,
        );

        self::assertSame('chat', $flag->featureName());
        self::assertTrue($flag->isEnabled());
        self::assertSame($createdAt, $flag->createdAt());
        self::assertSame($updatedAt, $flag->updatedAt());
    }

    #[Test]
    public function itRecordsDomainEvents(): void
    {
        $flag = $this->createFlag('dark_mode', false);

        // Create records FeatureFlagCreated
        $events = $flag->pullDomainEvents();
        self::assertCount(1, $events);
    }

    #[Test]
    public function itRecordsToggleEvents(): void
    {
        $flag = $this->createFlag('dark_mode', false);
        $flag->pullDomainEvents(); // clear creation event

        $flag->enable();
        $events = $flag->pullDomainEvents();
        self::assertCount(1, $events);
    }

    #[Test]
    public function itExposesIdentity(): void
    {
        $id = FeatureFlagId::generate();
        $flag = FeatureFlag::create($id, $this->tenantId, 'test', false);

        self::assertTrue($flag->id()->equals($id));
        self::assertTrue($flag->tenantId()->equals($this->tenantId));
    }
}
