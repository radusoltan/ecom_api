<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\DeletionRequestEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class DeletionRequestEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $request = DeletionRequest::create(
            DeletionRequestId::generate(),
            CustomerId::generate(),
            TenantId::generate(),
            'GDPR request',
        );

        $entity = DeletionRequestEntity::fromDomainModel($request);

        self::assertSame('pending', $entity->getStatus());
        self::assertSame('GDPR request', $entity->getReason());
        self::assertNull($entity->getHoldReason());
        self::assertNull($entity->getConfirmedAt());
        self::assertNull($entity->getCompletedAt());
        self::assertNotNull($entity->getScheduledFor());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());

        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($request->id()));
    }

    public function testUpdateFromDomainModel(): void
    {
        $request = DeletionRequest::create(
            DeletionRequestId::generate(),
            CustomerId::generate(),
            TenantId::generate(),
        );

        $entity = DeletionRequestEntity::fromDomainModel($request);
        $request->confirm();
        $entity->updateFromDomainModel($request);

        self::assertSame('confirmed', $entity->getStatus());
    }

    public function testSetters(): void
    {
        $entity = new DeletionRequestEntity();
        $entity->setId('req-001');
        $entity->setCustomerId('cust-001');
        $entity->setTenantId('tenant-001');
        $entity->setStatus('pending');
        $entity->setReason('User request');
        $entity->setHoldReason(null);
        $entity->setScheduledFor(new \DateTimeImmutable('+30 days'));

        self::assertSame('req-001', $entity->getId());
        self::assertSame('cust-001', $entity->getCustomerId());
        self::assertSame('tenant-001', $entity->getTenantId());
        self::assertSame('pending', $entity->getStatus());
        self::assertSame('User request', $entity->getReason());
    }
}
