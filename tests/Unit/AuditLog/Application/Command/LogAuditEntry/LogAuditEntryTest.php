<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Application\Command\LogAuditEntry;

use App\AuditLog\Application\Command\LogAuditEntry\LogAuditEntry;
use PHPUnit\Framework\TestCase;

final class LogAuditEntryTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    public function testItCreatesCommandWithRequiredFields(): void
    {
        // Act
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'create',
            resourceType: 'product',
            resourceId: 'product-123',
        );

        // Assert
        self::assertSame(self::TENANT_ID, $command->tenantId);
        self::assertSame(self::USER_ID, $command->userId);
        self::assertSame('create', $command->actionType);
        self::assertSame('product', $command->resourceType);
        self::assertSame('product-123', $command->resourceId);
    }

    public function testItDefaultsMetadataToEmptyArray(): void
    {
        // Act
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'update',
            resourceType: 'order',
            resourceId: 'order-456',
        );

        // Assert
        self::assertSame([], $command->metadata);
    }

    public function testItDefaultsIpAddressToNull(): void
    {
        // Act
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: null,
            actionType: 'delete',
            resourceType: 'customer',
            resourceId: 'customer-789',
        );

        // Assert
        self::assertNull($command->ipAddress);
    }

    public function testItDefaultsUserAgentToNull(): void
    {
        // Act
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: null,
            actionType: 'delete',
            resourceType: 'customer',
            resourceId: 'customer-789',
        );

        // Assert
        self::assertNull($command->userAgent);
    }

    public function testItAcceptsNullUserIdForSystemActions(): void
    {
        // Act
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: null,
            actionType: 'create',
            resourceType: 'tenant',
            resourceId: 'tenant-001',
        );

        // Assert
        self::assertNull($command->userId);
    }

    public function testItCreatesCommandWithAllOptionalFields(): void
    {
        // Arrange
        $metadata = ['name' => 'Test', 'price' => 1999];

        // Act
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'update',
            resourceType: 'product',
            resourceId: 'product-abc',
            metadata: $metadata,
            ipAddress: '192.168.1.100',
            userAgent: 'Mozilla/5.0 (compatible; Test)',
        );

        // Assert
        self::assertSame($metadata, $command->metadata);
        self::assertSame('192.168.1.100', $command->ipAddress);
        self::assertSame('Mozilla/5.0 (compatible; Test)', $command->userAgent);
    }

    public function testItAcceptsComplexMetadata(): void
    {
        // Arrange
        $metadata = [
            'changes' => [
                'name' => ['old' => 'Old Name', 'new' => 'New Name'],
                'price' => ['old' => 1000, 'new' => 1500],
            ],
            'reason' => 'Price adjustment',
        ];

        // Act
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'update',
            resourceType: 'product',
            resourceId: 'product-123',
            metadata: $metadata,
        );

        // Assert
        self::assertSame($metadata, $command->metadata);
    }
}
