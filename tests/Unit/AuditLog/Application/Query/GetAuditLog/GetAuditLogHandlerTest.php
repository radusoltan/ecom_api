<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Application\Query\GetAuditLog;

use App\AuditLog\Application\Query\GetAuditLog\GetAuditLog;
use App\AuditLog\Application\Query\GetAuditLog\GetAuditLogHandler;
use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetAuditLogHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private AuditLogRepositoryInterface&MockObject $repository;
    private GetAuditLogHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AuditLogRepositoryInterface::class);
        $this->handler = new GetAuditLogHandler($this->repository);
    }

    public function testItReturnsEntriesForTenant(): void
    {
        // Arrange
        $entry = AuditLogEntry::log(
            TenantId::fromString(self::TENANT_ID),
            UserId::fromString(self::USER_ID),
            ActionType::create(),
            ResourceType::product(),
            'product-123'
        );

        $query = new GetAuditLog(tenantId: self::TENANT_ID);

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::callback(fn (TenantId $id): bool => self::TENANT_ID === $id->toString()),
                self::callback(fn (array $filters): bool => 100 === $filters['limit'] && 0 === $filters['offset'])
            )
            ->willReturn([$entry]);

        // Act
        $result = ($this->handler)($query);

        // Assert
        self::assertCount(1, $result);
        self::assertSame($entry, $result[0]);
    }

    public function testItReturnsEmptyArrayWhenNoEntriesFound(): void
    {
        // Arrange
        $query = new GetAuditLog(tenantId: self::TENANT_ID);

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->willReturn([]);

        // Act
        $result = ($this->handler)($query);

        // Assert
        self::assertSame([], $result);
    }

    public function testItPassesUserIdFilterWhenProvided(): void
    {
        // Arrange
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
        );

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::isInstanceOf(TenantId::class),
                self::callback(function (array $filters): bool {
                    return isset($filters['userId'])
                        && $filters['userId'] instanceof UserId
                        && self::USER_ID === $filters['userId']->toString();
                })
            )
            ->willReturn([]);

        // Act
        ($this->handler)($query);
    }

    public function testItDoesNotPassUserIdFilterWhenNull(): void
    {
        // Arrange
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            userId: null,
        );

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::isInstanceOf(TenantId::class),
                self::callback(fn (array $filters): bool => !isset($filters['userId']))
            )
            ->willReturn([]);

        // Act
        ($this->handler)($query);
    }

    public function testItPassesActionTypeFilterWhenProvided(): void
    {
        // Arrange
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            actionType: 'create',
        );

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::isInstanceOf(TenantId::class),
                self::callback(function (array $filters): bool {
                    return isset($filters['actionType'])
                        && $filters['actionType'] instanceof ActionType
                        && 'create' === $filters['actionType']->toString();
                })
            )
            ->willReturn([]);

        // Act
        ($this->handler)($query);
    }

    public function testItDoesNotPassActionTypeFilterWhenNull(): void
    {
        // Arrange
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            actionType: null,
        );

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::isInstanceOf(TenantId::class),
                self::callback(fn (array $filters): bool => !isset($filters['actionType']))
            )
            ->willReturn([]);

        // Act
        ($this->handler)($query);
    }

    public function testItPassesResourceTypeFilterWhenProvided(): void
    {
        // Arrange
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            resourceType: 'order',
        );

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::isInstanceOf(TenantId::class),
                self::callback(function (array $filters): bool {
                    return isset($filters['resourceType'])
                        && $filters['resourceType'] instanceof ResourceType
                        && 'order' === $filters['resourceType']->toString();
                })
            )
            ->willReturn([]);

        // Act
        ($this->handler)($query);
    }

    public function testItPassesResourceIdFilterWhenProvided(): void
    {
        // Arrange
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            resourceId: 'order-456',
        );

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::isInstanceOf(TenantId::class),
                self::callback(fn (array $filters): bool => 'order-456' === ($filters['resourceId'] ?? null))
            )
            ->willReturn([]);

        // Act
        ($this->handler)($query);
    }

    public function testItPassesFromDateFilterWhenProvided(): void
    {
        // Arrange
        $fromDate = '2026-01-01T00:00:00+00:00';
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            fromDate: $fromDate,
        );

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::isInstanceOf(TenantId::class),
                self::callback(function (array $filters): bool {
                    return isset($filters['fromDate'])
                        && $filters['fromDate'] instanceof \DateTimeImmutable;
                })
            )
            ->willReturn([]);

        // Act
        ($this->handler)($query);
    }

    public function testItPassesToDateFilterWhenProvided(): void
    {
        // Arrange
        $toDate = '2026-12-31T23:59:59+00:00';
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            toDate: $toDate,
        );

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::isInstanceOf(TenantId::class),
                self::callback(function (array $filters): bool {
                    return isset($filters['toDate'])
                        && $filters['toDate'] instanceof \DateTimeImmutable;
                })
            )
            ->willReturn([]);

        // Act
        ($this->handler)($query);
    }

    public function testItPassesLimitAndOffsetToFilters(): void
    {
        // Arrange
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            limit: 25,
            offset: 75,
        );

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::isInstanceOf(TenantId::class),
                self::callback(fn (array $filters): bool => 25 === $filters['limit'] && 75 === $filters['offset'])
            )
            ->willReturn([]);

        // Act
        ($this->handler)($query);
    }

    public function testItReturnsMultipleEntries(): void
    {
        // Arrange
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $userId = UserId::fromString(self::USER_ID);

        $entries = [
            AuditLogEntry::log($tenantId, $userId, ActionType::create(), ResourceType::product(), 'product-1'),
            AuditLogEntry::log($tenantId, $userId, ActionType::update(), ResourceType::product(), 'product-2'),
            AuditLogEntry::log($tenantId, null, ActionType::delete(), ResourceType::order(), 'order-1'),
        ];

        $query = new GetAuditLog(tenantId: self::TENANT_ID);

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->willReturn($entries);

        // Act
        $result = ($this->handler)($query);

        // Assert
        self::assertCount(3, $result);
    }

    public function testItPassesAllFiltersTogether(): void
    {
        // Arrange
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'update',
            resourceType: 'product',
            resourceId: 'product-123',
            fromDate: '2026-01-01',
            toDate: '2026-06-30',
            limit: 10,
            offset: 20,
        );

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::callback(fn (TenantId $id): bool => self::TENANT_ID === $id->toString()),
                self::callback(function (array $filters): bool {
                    return isset($filters['userId'])
                        && isset($filters['actionType'])
                        && isset($filters['resourceType'])
                        && isset($filters['resourceId'])
                        && isset($filters['fromDate'])
                        && isset($filters['toDate'])
                        && 10 === $filters['limit']
                        && 20 === $filters['offset'];
                })
            )
            ->willReturn([]);

        // Act
        ($this->handler)($query);
    }
}
