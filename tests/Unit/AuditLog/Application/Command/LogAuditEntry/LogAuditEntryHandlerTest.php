<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Application\Command\LogAuditEntry;

use App\AuditLog\Application\Command\LogAuditEntry\LogAuditEntry;
use App\AuditLog\Application\Command\LogAuditEntry\LogAuditEntryHandler;
use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LogAuditEntryHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private AuditLogRepositoryInterface&MockObject $repository;
    private LogAuditEntryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AuditLogRepositoryInterface::class);
        $this->handler = new LogAuditEntryHandler($this->repository);
    }

    public function testItSavesAuditLogEntry(): void
    {
        // Arrange
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'create',
            resourceType: 'product',
            resourceId: 'product-123',
        );

        // Assert
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(AuditLogEntry::class));

        // Act
        ($this->handler)($command);
    }

    public function testItSavesEntryWithSystemActionWhenUserIdIsNull(): void
    {
        // Arrange
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: null,
            actionType: 'create',
            resourceType: 'tenant',
            resourceId: 'tenant-001',
        );

        // Assert
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuditLogEntry $entry): bool {
                return $entry->isSystemAction();
            }));

        // Act
        ($this->handler)($command);
    }

    public function testItPassesCorrectTenantIdToEntry(): void
    {
        // Arrange
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'update',
            resourceType: 'order',
            resourceId: 'order-456',
        );

        // Assert
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuditLogEntry $entry): bool {
                return self::TENANT_ID === $entry->tenantId()->toString();
            }));

        // Act
        ($this->handler)($command);
    }

    public function testItPassesCorrectActionTypeToEntry(): void
    {
        // Arrange
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'delete',
            resourceType: 'customer',
            resourceId: 'customer-789',
        );

        // Assert
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuditLogEntry $entry): bool {
                return 'delete' === $entry->actionType()->toString();
            }));

        // Act
        ($this->handler)($command);
    }

    public function testItPassesCorrectResourceTypeToEntry(): void
    {
        // Arrange
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'create',
            resourceType: 'payment',
            resourceId: 'payment-abc',
        );

        // Assert
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuditLogEntry $entry): bool {
                return 'payment' === $entry->resourceType()->toString();
            }));

        // Act
        ($this->handler)($command);
    }

    public function testItPassesMetadataToEntry(): void
    {
        // Arrange
        $metadata = ['changes' => ['name' => 'New Name']];

        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'update',
            resourceType: 'product',
            resourceId: 'product-123',
            metadata: $metadata,
        );

        // Assert
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuditLogEntry $entry) use ($metadata): bool {
                return $entry->metadata() === $metadata;
            }));

        // Act
        ($this->handler)($command);
    }

    public function testItPassesIpAddressAndUserAgentToEntry(): void
    {
        // Arrange
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'view',
            resourceType: 'product',
            resourceId: 'product-123',
            metadata: [],
            ipAddress: '10.0.0.1',
            userAgent: 'TestBrowser/1.0',
        );

        // Assert
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuditLogEntry $entry): bool {
                return '10.0.0.1' === $entry->ipAddress()
                    && 'TestBrowser/1.0' === $entry->userAgent();
            }));

        // Act
        ($this->handler)($command);
    }

    public function testItThrowsForInvalidActionType(): void
    {
        // Arrange
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'invalid_action',
            resourceType: 'product',
            resourceId: 'product-123',
        );

        $this->repository->expects(self::never())->method('save');

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        ($this->handler)($command);
    }

    public function testItThrowsForInvalidResourceType(): void
    {
        // Arrange
        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'create',
            resourceType: 'invalid_resource',
            resourceId: 'resource-123',
        );

        $this->repository->expects(self::never())->method('save');

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        ($this->handler)($command);
    }

    public function testItSavesEntryWithResourceId(): void
    {
        // Arrange
        $resourceId = 'product-uuid-1234';

        $command = new LogAuditEntry(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'create',
            resourceType: 'product',
            resourceId: $resourceId,
        );

        // Assert
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuditLogEntry $entry) use ($resourceId): bool {
                return $entry->resourceId() === $resourceId;
            }));

        // Act
        ($this->handler)($command);
    }
}
