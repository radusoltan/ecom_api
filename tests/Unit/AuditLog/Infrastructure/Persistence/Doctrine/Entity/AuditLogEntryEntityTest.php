<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Infrastructure\Persistence\Doctrine\Entity;

use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\AuditLog\Infrastructure\Persistence\Doctrine\Entity\AuditLogEntryEntity;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class AuditLogEntryEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $entry = AuditLogEntry::log(
            TenantId::generate(),
            UserId::generate(),
            ActionType::create(),
            ResourceType::product(),
            'prod-123',
            ['name' => 'Widget'],
            '192.168.1.1',
            'Mozilla/5.0',
        );

        $entity = AuditLogEntryEntity::fromDomainModel($entry);

        self::assertSame('create', $entity->getActionType());
        self::assertSame('product', $entity->getResourceType());
        self::assertSame('prod-123', $entity->getResourceId());
        self::assertSame('192.168.1.1', $entity->getIpAddress());
        self::assertSame('Mozilla/5.0', $entity->getUserAgent());
        self::assertSame(['name' => 'Widget'], $entity->getMetadata());
        self::assertNotNull($entity->getUserId());

        $restored = $entity->toDomainModel();
        self::assertTrue($restored->actionType()->equals(ActionType::create()));
        self::assertTrue($restored->resourceType()->equals(ResourceType::product()));
    }

    public function testSettersAndGetters(): void
    {
        $entity = new AuditLogEntryEntity();
        $entity->setId('log-001');
        $entity->setTenantId('tenant-001');
        $entity->setUserId('user-001');
        $entity->setActionType('update');
        $entity->setResourceType('order');
        $entity->setResourceId('order-001');
        $entity->setMetadata(['key' => 'value']);
        $entity->setIpAddress('10.0.0.1');
        $entity->setUserAgent('Test/1.0');
        $entity->setOccurredAt(new \DateTimeImmutable());

        self::assertSame('log-001', $entity->getId());
        self::assertSame('tenant-001', $entity->getTenantId());
        self::assertSame('user-001', $entity->getUserId());
        self::assertSame('update', $entity->getActionType());
        self::assertSame('order', $entity->getResourceType());
        self::assertSame('order-001', $entity->getResourceId());
        self::assertSame(['key' => 'value'], $entity->getMetadata());
        self::assertSame('10.0.0.1', $entity->getIpAddress());
        self::assertSame('Test/1.0', $entity->getUserAgent());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getOccurredAt());
    }
}
