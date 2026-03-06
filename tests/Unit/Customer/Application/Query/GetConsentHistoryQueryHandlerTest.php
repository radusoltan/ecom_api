<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\ConsentHistoryDTO;
use App\Customer\Application\Query\GetConsentHistory\GetConsentHistoryQuery;
use App\Customer\Application\Query\GetConsentHistory\GetConsentHistoryQueryHandler;
use App\Customer\Domain\Model\ConsentHistory;
use App\Customer\Domain\Repository\ConsentHistoryRepositoryInterface;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetConsentHistoryQueryHandler::class)]
final class GetConsentHistoryQueryHandlerTest extends TestCase
{
    private ConsentHistoryRepositoryInterface $repository;
    private GetConsentHistoryQueryHandler $handler;
    private TenantId $tenantId;
    private CustomerId $customerId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ConsentHistoryRepositoryInterface::class);
        $this->handler = new GetConsentHistoryQueryHandler($this->repository);

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->customerId = CustomerId::generate();
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyArrayWhenNoHistory(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findByCustomerIdPaginated')
            ->with($this->customerId, $this->tenantId, 1, 20)
            ->willReturn([]);

        $query = new GetConsentHistoryQuery(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        $result = ($this->handler)($query);

        self::assertSame([], $result);
    }

    #[Test]
    public function itMapsHistoryRecordsToDTOs(): void
    {
        $record1 = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: true,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0',
        );

        $record2 = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::ANALYTICS_TRACKING,
            granted: false,
            ipAddress: null,
            userAgent: null,
        );

        $this->repository
            ->expects(self::once())
            ->method('findByCustomerIdPaginated')
            ->willReturn([$record1, $record2]);

        $query = new GetConsentHistoryQuery(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        $result = ($this->handler)($query);

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(ConsentHistoryDTO::class, $result);

        self::assertSame('marketing_email', $result[0]->consentType);
        self::assertSame('Marketing Emails', $result[0]->consentTypeLabel);
        self::assertTrue($result[0]->granted);
        self::assertSame('192.168.1.1', $result[0]->ipAddress);
        self::assertSame('Mozilla/5.0', $result[0]->userAgent);

        self::assertSame('analytics_tracking', $result[1]->consentType);
        self::assertFalse($result[1]->granted);
        self::assertNull($result[1]->ipAddress);
    }

    // ---------------------------------------------------------------
    // Pagination forwarding
    // ---------------------------------------------------------------

    #[Test]
    public function itForwardsPaginationParametersToRepository(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findByCustomerIdPaginated')
            ->with($this->customerId, $this->tenantId, 3, 50)
            ->willReturn([]);

        $query = new GetConsentHistoryQuery(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            page: 3,
            limit: 50,
        );

        $result = ($this->handler)($query);

        self::assertSame([], $result);
    }
}
