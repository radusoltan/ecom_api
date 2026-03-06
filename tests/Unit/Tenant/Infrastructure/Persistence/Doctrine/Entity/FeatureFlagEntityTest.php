<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Infrastructure\Persistence\Doctrine\Entity;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Domain\Model\FeatureFlagId;
use App\Tenant\Infrastructure\Persistence\Doctrine\Entity\FeatureFlagEntity;
use PHPUnit\Framework\TestCase;

final class FeatureFlagEntityTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const FLAG_ID = 'a1b2c3d4-e5f6-4000-8000-000000000001';

    private function makeFeatureFlag(
        bool $enabled = true,
        array $configuration = [],
        ?string $description = null,
        ?\DateTimeImmutable $updatedAt = null,
    ): FeatureFlag {
        return FeatureFlag::reconstituteFromPersistence(
            FeatureFlagId::fromString(self::FLAG_ID),
            TenantId::fromString(self::TENANT_ID),
            'checkout_v2',
            $enabled,
            $configuration,
            $description,
            new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            $updatedAt,
        );
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $flag = $this->makeFeatureFlag(
            enabled: true,
            configuration: ['max_items' => 10, 'beta_users_only' => true],
            description: 'New checkout flow v2',
        );

        $entity = FeatureFlagEntity::fromDomainModel($flag);
        $restored = $entity->toDomainModel();

        self::assertSame(self::FLAG_ID, $restored->id()->toString());
        self::assertSame(self::TENANT_ID, $restored->tenantId()->toString());
        self::assertSame('checkout_v2', $restored->featureName());
        self::assertTrue($restored->isEnabled());
        self::assertSame(['max_items' => 10, 'beta_users_only' => true], $restored->configuration());
        self::assertSame('New checkout flow v2', $restored->description());
        self::assertSame('2024-01-01T00:00:00+00:00', $restored->createdAt()->format(\DateTimeInterface::ATOM));
        self::assertNull($restored->updatedAt());
    }

    public function testFromDomainModelDisabledFlag(): void
    {
        $flag = $this->makeFeatureFlag(enabled: false);

        $entity = FeatureFlagEntity::fromDomainModel($flag);
        $restored = $entity->toDomainModel();

        self::assertFalse($restored->isEnabled());
    }

    public function testFromDomainModelWithNullDescription(): void
    {
        $flag = $this->makeFeatureFlag(description: null);

        $entity = FeatureFlagEntity::fromDomainModel($flag);
        $restored = $entity->toDomainModel();

        self::assertNull($restored->description());
    }

    public function testFromDomainModelWithUpdatedAt(): void
    {
        $updatedAt = new \DateTimeImmutable('2024-06-15T08:30:00+00:00');
        $flag = $this->makeFeatureFlag(updatedAt: $updatedAt);

        $entity = FeatureFlagEntity::fromDomainModel($flag);
        $restored = $entity->toDomainModel();

        self::assertSame('2024-06-15T08:30:00+00:00', $restored->updatedAt()->format(\DateTimeInterface::ATOM));
    }

    public function testFromDomainModelWithEmptyConfiguration(): void
    {
        $flag = $this->makeFeatureFlag(configuration: []);

        $entity = FeatureFlagEntity::fromDomainModel($flag);
        $restored = $entity->toDomainModel();

        self::assertSame([], $restored->configuration());
    }

    public function testFromDomainModelWithNestedConfiguration(): void
    {
        $config = [
            'rollout_percentage' => 25,
            'allowed_regions' => ['EU', 'US'],
            'overrides' => ['user_123' => true],
        ];
        $flag = $this->makeFeatureFlag(configuration: $config);

        $entity = FeatureFlagEntity::fromDomainModel($flag);
        $restored = $entity->toDomainModel();

        self::assertSame($config, $restored->configuration());
    }

    public function testUpdateFromDomainModelChangesMutableFields(): void
    {
        $original = $this->makeFeatureFlag(enabled: true, configuration: [], description: 'Original');
        $entity = FeatureFlagEntity::fromDomainModel($original);

        $updatedAt = new \DateTimeImmutable('2025-03-01T12:00:00+00:00');
        $updated = FeatureFlag::reconstituteFromPersistence(
            FeatureFlagId::fromString(self::FLAG_ID),
            TenantId::fromString(self::TENANT_ID),
            'checkout_v2',
            false,
            ['key' => 'value'],
            'Updated description',
            new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            $updatedAt,
        );

        $entity->updateFromDomainModel($updated);
        $restored = $entity->toDomainModel();

        self::assertFalse($restored->isEnabled());
        self::assertSame(['key' => 'value'], $restored->configuration());
        self::assertSame('Updated description', $restored->description());
        self::assertSame('2025-03-01T12:00:00+00:00', $restored->updatedAt()->format(\DateTimeInterface::ATOM));
    }

    public function testToDomainModelCreatedAtPreserved(): void
    {
        $createdAt = new \DateTimeImmutable('2023-11-20T14:22:00+00:00');
        $flag = FeatureFlag::reconstituteFromPersistence(
            FeatureFlagId::fromString(self::FLAG_ID),
            TenantId::fromString(self::TENANT_ID),
            'feature_x',
            true,
            [],
            null,
            $createdAt,
            null,
        );

        $entity = FeatureFlagEntity::fromDomainModel($flag);
        $restored = $entity->toDomainModel();

        self::assertSame('2023-11-20T14:22:00+00:00', $restored->createdAt()->format(\DateTimeInterface::ATOM));
    }
}
