<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Application\Query\GetAuditLog;

use App\AuditLog\Application\Query\GetAuditLog\GetAuditLog;
use PHPUnit\Framework\TestCase;

final class GetAuditLogTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    public function testItCreatesQueryWithRequiredTenantId(): void
    {
        // Act
        $query = new GetAuditLog(tenantId: self::TENANT_ID);

        // Assert
        self::assertSame(self::TENANT_ID, $query->tenantId);
    }

    public function testItDefaultsOptionalFiltersToNull(): void
    {
        // Act
        $query = new GetAuditLog(tenantId: self::TENANT_ID);

        // Assert
        self::assertNull($query->userId);
        self::assertNull($query->actionType);
        self::assertNull($query->resourceType);
        self::assertNull($query->resourceId);
        self::assertNull($query->fromDate);
        self::assertNull($query->toDate);
    }

    public function testItDefaultsLimitTo100(): void
    {
        // Act
        $query = new GetAuditLog(tenantId: self::TENANT_ID);

        // Assert
        self::assertSame(100, $query->limit);
    }

    public function testItDefaultsOffsetToZero(): void
    {
        // Act
        $query = new GetAuditLog(tenantId: self::TENANT_ID);

        // Assert
        self::assertSame(0, $query->offset);
    }

    public function testItCreatesQueryWithUserIdFilter(): void
    {
        // Act
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
        );

        // Assert
        self::assertSame(self::USER_ID, $query->userId);
    }

    public function testItCreatesQueryWithActionTypeFilter(): void
    {
        // Act
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            actionType: 'create',
        );

        // Assert
        self::assertSame('create', $query->actionType);
    }

    public function testItCreatesQueryWithResourceTypeFilter(): void
    {
        // Act
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            resourceType: 'product',
        );

        // Assert
        self::assertSame('product', $query->resourceType);
    }

    public function testItCreatesQueryWithResourceIdFilter(): void
    {
        // Act
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            resourceId: 'product-123',
        );

        // Assert
        self::assertSame('product-123', $query->resourceId);
    }

    public function testItCreatesQueryWithDateRangeFilters(): void
    {
        // Act
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            fromDate: '2026-01-01T00:00:00+00:00',
            toDate: '2026-12-31T23:59:59+00:00',
        );

        // Assert
        self::assertSame('2026-01-01T00:00:00+00:00', $query->fromDate);
        self::assertSame('2026-12-31T23:59:59+00:00', $query->toDate);
    }

    public function testItCreatesQueryWithCustomLimitAndOffset(): void
    {
        // Act
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            limit: 50,
            offset: 100,
        );

        // Assert
        self::assertSame(50, $query->limit);
        self::assertSame(100, $query->offset);
    }

    public function testItCreatesQueryWithAllFilters(): void
    {
        // Act
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            userId: self::USER_ID,
            actionType: 'update',
            resourceType: 'order',
            resourceId: 'order-456',
            fromDate: '2026-01-01',
            toDate: '2026-06-30',
            limit: 25,
            offset: 50,
        );

        // Assert
        self::assertSame(self::TENANT_ID, $query->tenantId);
        self::assertSame(self::USER_ID, $query->userId);
        self::assertSame('update', $query->actionType);
        self::assertSame('order', $query->resourceType);
        self::assertSame('order-456', $query->resourceId);
        self::assertSame('2026-01-01', $query->fromDate);
        self::assertSame('2026-06-30', $query->toDate);
        self::assertSame(25, $query->limit);
        self::assertSame(50, $query->offset);
    }
}
