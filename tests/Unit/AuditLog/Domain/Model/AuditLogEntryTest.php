<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Domain\Model;

use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\AuditLogId;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class AuditLogEntryTest extends TestCase
{
    public function testLogCreatesNewAuditLogEntry(): void
    {
        $tenantId = TenantId::generate();
        $userId = UserId::generate();
        $actionType = ActionType::create();
        $resourceType = ResourceType::product();
        $resourceId = 'product-123';
        $metadata = ['name' => 'Test Product', 'price' => 1999];
        $ipAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0';

        $entry = AuditLogEntry::log(
            $tenantId,
            $userId,
            $actionType,
            $resourceType,
            $resourceId,
            $metadata,
            $ipAddress,
            $userAgent
        );

        $this->assertInstanceOf(AuditLogId::class, $entry->id());
        $this->assertTrue($entry->tenantId()->equals($tenantId));
        $this->assertNotNull($entry->userId());
        $this->assertTrue($entry->userId()->equals($userId));
        $this->assertTrue($entry->actionType()->equals($actionType));
        $this->assertTrue($entry->resourceType()->equals($resourceType));
        $this->assertEquals($resourceId, $entry->resourceId());
        $this->assertEquals($metadata, $entry->metadata());
        $this->assertEquals($ipAddress, $entry->ipAddress());
        $this->assertEquals($userAgent, $entry->userAgent());
        $this->assertInstanceOf(\DateTimeImmutable::class, $entry->occurredAt());
    }

    public function testLogCreatesEntryWithoutUserId(): void
    {
        $tenantId = TenantId::generate();
        $actionType = ActionType::create();
        $resourceType = ResourceType::product();
        $resourceId = 'product-123';

        $entry = AuditLogEntry::log(
            $tenantId,
            null, // System-initiated action
            $actionType,
            $resourceType,
            $resourceId
        );

        $this->assertNull($entry->userId());
        $this->assertTrue($entry->isSystemAction());
    }

    public function testLogCreatesEntryWithMinimalData(): void
    {
        $tenantId = TenantId::generate();
        $userId = UserId::generate();
        $actionType = ActionType::update();
        $resourceType = ResourceType::order();
        $resourceId = 'order-456';

        $entry = AuditLogEntry::log(
            $tenantId,
            $userId,
            $actionType,
            $resourceType,
            $resourceId
        );

        $this->assertEquals([], $entry->metadata());
        $this->assertNull($entry->ipAddress());
        $this->assertNull($entry->userAgent());
    }

    public function testReconstituteCreatesEntryFromPersistence(): void
    {
        $id = AuditLogId::generate();
        $tenantId = TenantId::generate();
        $userId = UserId::generate();
        $actionType = ActionType::delete();
        $resourceType = ResourceType::customer();
        $resourceId = 'customer-789';
        $metadata = ['reason' => 'GDPR request'];
        $ipAddress = '10.0.0.1';
        $userAgent = 'Admin Panel';
        $occurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $entry = AuditLogEntry::reconstitute(
            $id,
            $tenantId,
            $userId,
            $actionType,
            $resourceType,
            $resourceId,
            $metadata,
            $ipAddress,
            $userAgent,
            $occurredAt
        );

        $this->assertTrue($entry->id()->equals($id));
        $this->assertTrue($entry->tenantId()->equals($tenantId));
        $this->assertTrue($entry->userId()->equals($userId));
        $this->assertTrue($entry->actionType()->equals($actionType));
        $this->assertTrue($entry->resourceType()->equals($resourceType));
        $this->assertEquals($resourceId, $entry->resourceId());
        $this->assertEquals($metadata, $entry->metadata());
        $this->assertEquals($ipAddress, $entry->ipAddress());
        $this->assertEquals($userAgent, $entry->userAgent());
        $this->assertEquals($occurredAt, $entry->occurredAt());
    }

    public function testWasPerformedByReturnsTrueForMatchingUser(): void
    {
        $userId = UserId::generate();
        $entry = AuditLogEntry::log(
            TenantId::generate(),
            $userId,
            ActionType::update(),
            ResourceType::product(),
            'product-123'
        );

        $this->assertTrue($entry->wasPerformedBy($userId));
    }

    public function testWasPerformedByReturnsFalseForDifferentUser(): void
    {
        $userId1 = UserId::generate();
        $userId2 = UserId::generate();
        $entry = AuditLogEntry::log(
            TenantId::generate(),
            $userId1,
            ActionType::update(),
            ResourceType::product(),
            'product-123'
        );

        $this->assertFalse($entry->wasPerformedBy($userId2));
    }

    public function testWasPerformedByReturnsFalseForSystemAction(): void
    {
        $entry = AuditLogEntry::log(
            TenantId::generate(),
            null, // System action
            ActionType::create(),
            ResourceType::order(),
            'order-123'
        );

        $this->assertFalse($entry->wasPerformedBy(UserId::generate()));
    }

    public function testAffectedResourceReturnsTrueForMatchingResource(): void
    {
        $resourceType = ResourceType::payment();
        $resourceId = 'payment-abc';
        $entry = AuditLogEntry::log(
            TenantId::generate(),
            UserId::generate(),
            ActionType::capture(),
            $resourceType,
            $resourceId
        );

        $this->assertTrue($entry->affectedResource($resourceType, $resourceId));
    }

    public function testAffectedResourceReturnsFalseForDifferentResource(): void
    {
        $entry = AuditLogEntry::log(
            TenantId::generate(),
            UserId::generate(),
            ActionType::capture(),
            ResourceType::payment(),
            'payment-abc'
        );

        $this->assertFalse($entry->affectedResource(ResourceType::order(), 'payment-abc'));
        $this->assertFalse($entry->affectedResource(ResourceType::payment(), 'payment-xyz'));
    }

    public function testIsSystemActionReturnsTrueWhenNoUserId(): void
    {
        $entry = AuditLogEntry::log(
            TenantId::generate(),
            null,
            ActionType::create(),
            ResourceType::product(),
            'product-123'
        );

        $this->assertTrue($entry->isSystemAction());
    }

    public function testIsSystemActionReturnsFalseWhenUserIdPresent(): void
    {
        $entry = AuditLogEntry::log(
            TenantId::generate(),
            UserId::generate(),
            ActionType::create(),
            ResourceType::product(),
            'product-123'
        );

        $this->assertFalse($entry->isSystemAction());
    }

    public function testMetadataCanBeEmptyArray(): void
    {
        $entry = AuditLogEntry::log(
            TenantId::generate(),
            UserId::generate(),
            ActionType::view(),
            ResourceType::product(),
            'product-123',
            []
        );

        $this->assertEquals([], $entry->metadata());
    }

    public function testMetadataCanContainComplexData(): void
    {
        $metadata = [
            'changes' => [
                'name' => ['old' => 'Old Name', 'new' => 'New Name'],
                'price' => ['old' => 1000, 'new' => 1500],
            ],
            'tenant' => 'tenant-123',
            'timestamp' => '2025-01-01T12:00:00Z',
        ];

        $entry = AuditLogEntry::log(
            TenantId::generate(),
            UserId::generate(),
            ActionType::update(),
            ResourceType::product(),
            'product-123',
            $metadata
        );

        $this->assertEquals($metadata, $entry->metadata());
    }
}
