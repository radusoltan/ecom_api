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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetAuditLogHandler::class)]
final class GetAuditLogHandlerExtendedTest extends TestCase
{
    private AuditLogRepositoryInterface&MockObject $repository;
    private GetAuditLogHandler $handler;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AuditLogRepositoryInterface::class);
        $this->handler = new GetAuditLogHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Basic query — minimal filters
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyArrayWhenNoEntries(): void
    {
        $query = new GetAuditLog(tenantId: self::TENANT_ID);

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->willReturn([]);

        $result = ($this->handler)($query);

        self::assertSame([], $result);
    }

    #[Test]
    public function itPassesDefaultLimitAndOffsetToRepository(): void
    {
        $query = new GetAuditLog(tenantId: self::TENANT_ID);

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::callback(fn (TenantId $tid) => self::TENANT_ID === $tid->toString()),
                self::callback(function (array $filters): bool {
                    return 100 === $filters['limit'] && 0 === $filters['offset'];
                })
            )
            ->willReturn([]);

        ($this->handler)($query);
    }

    #[Test]
    public function itPassesCustomLimitAndOffset(): void
    {
        $query = new GetAuditLog(tenantId: self::TENANT_ID, limit: 20, offset: 40);

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::anything(),
                self::callback(function (array $filters): bool {
                    return 20 === $filters['limit'] && 40 === $filters['offset'];
                })
            )
            ->willReturn([]);

        ($this->handler)($query);
    }

    // -----------------------------------------------------------------------
    // Optional filters passed to repository
    // -----------------------------------------------------------------------

    #[Test]
    public function itPassesUserIdFilterWhenProvided(): void
    {
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            userId: '00000000-0000-4000-8000-000000000002',
        );

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::anything(),
                self::callback(fn (array $f) => isset($f['userId']))
            )
            ->willReturn([]);

        ($this->handler)($query);
    }

    #[Test]
    public function itOmitsUserIdFilterWhenNull(): void
    {
        $query = new GetAuditLog(tenantId: self::TENANT_ID, userId: null);

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::anything(),
                self::callback(fn (array $f) => !isset($f['userId']))
            )
            ->willReturn([]);

        ($this->handler)($query);
    }

    #[Test]
    public function itPassesActionTypeFilterWhenProvided(): void
    {
        $query = new GetAuditLog(tenantId: self::TENANT_ID, actionType: 'create');

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::anything(),
                self::callback(fn (array $f) => isset($f['actionType']))
            )
            ->willReturn([]);

        ($this->handler)($query);
    }

    #[Test]
    public function itPassesResourceTypeFilterWhenProvided(): void
    {
        $query = new GetAuditLog(tenantId: self::TENANT_ID, resourceType: 'product');

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::anything(),
                self::callback(fn (array $f) => isset($f['resourceType']))
            )
            ->willReturn([]);

        ($this->handler)($query);
    }

    #[Test]
    public function itPassesResourceIdFilterWhenProvided(): void
    {
        $query = new GetAuditLog(tenantId: self::TENANT_ID, resourceId: 'prod-123');

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::anything(),
                self::callback(fn (array $f) => isset($f['resourceId']) && 'prod-123' === $f['resourceId'])
            )
            ->willReturn([]);

        ($this->handler)($query);
    }

    #[Test]
    public function itPassesDateRangeFiltersWhenProvided(): void
    {
        $query = new GetAuditLog(
            tenantId: self::TENANT_ID,
            fromDate: '2024-01-01',
            toDate: '2024-12-31',
        );

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::anything(),
                self::callback(function (array $f): bool {
                    return isset($f['fromDate'])
                        && isset($f['toDate'])
                        && $f['fromDate'] instanceof \DateTimeImmutable
                        && $f['toDate'] instanceof \DateTimeImmutable;
                })
            )
            ->willReturn([]);

        ($this->handler)($query);
    }

    #[Test]
    public function itOmitsDateFiltersWhenNull(): void
    {
        $query = new GetAuditLog(tenantId: self::TENANT_ID, fromDate: null, toDate: null);

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->with(
                self::anything(),
                self::callback(fn (array $f) => !isset($f['fromDate']) && !isset($f['toDate']))
            )
            ->willReturn([]);

        ($this->handler)($query);
    }

    // -----------------------------------------------------------------------
    // Returns entries from repository
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsEntriesFromRepository(): void
    {
        $entry = AuditLogEntry::log(
            TenantId::fromString(self::TENANT_ID),
            null,
            ActionType::create(),
            ResourceType::product(),
            'p1',
        );

        $query = new GetAuditLog(tenantId: self::TENANT_ID);

        $this->repository
            ->expects(self::once())
            ->method('findByTenant')
            ->willReturn([$entry]);

        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertSame($entry, $result[0]);
    }

    #[Test]
    public function itReturnsMultipleEntries(): void
    {
        $entries = [];
        $tenantId = TenantId::fromString(self::TENANT_ID);
        for ($i = 0; $i < 5; ++$i) {
            $entries[] = AuditLogEntry::log(
                $tenantId, null, ActionType::view(), ResourceType::order(), "ord-{$i}"
            );
        }

        $query = new GetAuditLog(tenantId: self::TENANT_ID);
        $this->repository->expects(self::once())->method('findByTenant')->willReturn($entries);

        $result = ($this->handler)($query);

        self::assertCount(5, $result);
    }
}
