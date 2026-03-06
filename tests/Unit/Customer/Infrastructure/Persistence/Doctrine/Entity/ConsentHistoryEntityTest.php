<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\ConsentHistory;
use App\Customer\Domain\ValueObject\ConsentHistoryId;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\ConsentHistoryEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ConsentHistoryEntityTest extends TestCase
{
    public function testFromDomainModelRoundtripWithAllFields(): void
    {
        $id = ConsentHistoryId::generate();
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $consentType = ConsentType::MARKETING_EMAIL;
        $granted = true;
        $ipAddress = '192.168.1.100';
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $createdAt = new \DateTimeImmutable('2026-01-15T10:30:00+00:00');

        $history = ConsentHistory::reconstituteFromPersistence(
            id: $id,
            customerId: $customerId,
            tenantId: $tenantId,
            consentType: $consentType,
            granted: $granted,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            createdAt: $createdAt,
        );

        $entity = ConsentHistoryEntity::fromDomainModel($history);

        self::assertSame($id->toString(), $entity->getId());
        self::assertSame($customerId->toString(), $entity->getCustomerId());
        self::assertSame($tenantId->toString(), $entity->getTenantId());
        self::assertSame('marketing_email', $entity->getConsentType());
        self::assertTrue($entity->isGranted());
        self::assertSame($ipAddress, $entity->getIpAddress());
        self::assertSame($userAgent, $entity->getUserAgent());
        self::assertSame($createdAt, $entity->getCreatedAt());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertSame($id->toString(), $restored->id()->toString());
        self::assertSame($customerId->toString(), $restored->customerId()->toString());
        self::assertSame($tenantId->toString(), $restored->tenantId()->toString());
        self::assertSame(ConsentType::MARKETING_EMAIL, $restored->consentType());
        self::assertTrue($restored->granted());
        self::assertSame($ipAddress, $restored->ipAddress());
        self::assertSame($userAgent, $restored->userAgent());
        self::assertSame($createdAt, $restored->createdAt());
    }

    public function testFromDomainModelWithNullOptionalFields(): void
    {
        $id = ConsentHistoryId::generate();
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $createdAt = new \DateTimeImmutable('2026-02-01T08:00:00+00:00');

        $history = ConsentHistory::reconstituteFromPersistence(
            id: $id,
            customerId: $customerId,
            tenantId: $tenantId,
            consentType: ConsentType::ANALYTICS_TRACKING,
            granted: false,
            ipAddress: null,
            userAgent: null,
            createdAt: $createdAt,
        );

        $entity = ConsentHistoryEntity::fromDomainModel($history);

        self::assertNull($entity->getIpAddress());
        self::assertNull($entity->getUserAgent());
        self::assertFalse($entity->isGranted());
        self::assertSame('analytics_tracking', $entity->getConsentType());

        $restored = $entity->toDomainModel();
        self::assertNull($restored->ipAddress());
        self::assertNull($restored->userAgent());
        self::assertFalse($restored->granted());
        self::assertSame(ConsentType::ANALYTICS_TRACKING, $restored->consentType());
    }

    public function testFromDomainModelRoundtripForAllConsentTypes(): void
    {
        $consentTypes = [
            ConsentType::MARKETING_EMAIL,
            ConsentType::MARKETING_SMS,
            ConsentType::THIRD_PARTY_SHARING,
            ConsentType::ANALYTICS_TRACKING,
        ];

        foreach ($consentTypes as $consentType) {
            $history = ConsentHistory::reconstituteFromPersistence(
                id: ConsentHistoryId::generate(),
                customerId: CustomerId::generate(),
                tenantId: TenantId::generate(),
                consentType: $consentType,
                granted: true,
                ipAddress: null,
                userAgent: null,
                createdAt: new \DateTimeImmutable(),
            );

            $entity = ConsentHistoryEntity::fromDomainModel($history);
            self::assertSame($consentType->value, $entity->getConsentType());

            $restored = $entity->toDomainModel();
            self::assertSame($consentType, $restored->consentType());
        }
    }

    public function testFromDomainModelGrantedFalseRoundtrip(): void
    {
        $id = ConsentHistoryId::generate();
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $history = ConsentHistory::reconstituteFromPersistence(
            id: $id,
            customerId: $customerId,
            tenantId: $tenantId,
            consentType: ConsentType::THIRD_PARTY_SHARING,
            granted: false,
            ipAddress: '10.0.0.1',
            userAgent: 'curl/7.88.1',
            createdAt: new \DateTimeImmutable('2026-03-01T00:00:00+00:00'),
        );

        $entity = ConsentHistoryEntity::fromDomainModel($history);
        self::assertFalse($entity->isGranted());
        self::assertSame('10.0.0.1', $entity->getIpAddress());
        self::assertSame('curl/7.88.1', $entity->getUserAgent());

        $restored = $entity->toDomainModel();
        self::assertFalse($restored->granted());
        self::assertSame('10.0.0.1', $restored->ipAddress());
        self::assertSame('curl/7.88.1', $restored->userAgent());
    }
}
