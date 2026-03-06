<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\DataExportRequestEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class DataExportRequestEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $request = DataExportRequest::create(
            CustomerId::generate(),
            TenantId::generate(),
            'privacy-req-123',
        );

        $entity = DataExportRequestEntity::fromDomainModel($request);

        self::assertSame('pending', $entity->getStatus());
        self::assertSame('privacy-req-123', $entity->getPrivacyRequestId());
        self::assertNull($entity->getFilePath());
        self::assertNull($entity->getDownloadToken());
        self::assertNull($entity->getExpiresAt());
        self::assertNull($entity->getCompletedAt());
        self::assertNull($entity->getErrorMessage());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());

        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($request->id()));
    }

    public function testUpdateFromDomainModel(): void
    {
        $request = DataExportRequest::create(CustomerId::generate(), TenantId::generate());
        $entity = DataExportRequestEntity::fromDomainModel($request);

        $request->markProcessing();
        $entity->updateFromDomainModel($request);

        self::assertSame('processing', $entity->getStatus());
    }

    public function testSetPrivacyRequestId(): void
    {
        $entity = DataExportRequestEntity::fromDomainModel(
            DataExportRequest::create(CustomerId::generate(), TenantId::generate())
        );

        $entity->setPrivacyRequestId('new-req-456');
        self::assertSame('new-req-456', $entity->getPrivacyRequestId());
    }
}
